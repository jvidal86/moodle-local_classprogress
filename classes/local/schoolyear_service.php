<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_classprogress\local;

/**
 * A teacher's school years, with their holidays and evaluation periods.
 *
 * Every method that takes the acting user first checks, live, that the user may use the plugin (access::qualifies()),
 * and then reaches only that user's rows: a missing id and another user's id give the same exception, error_notfound.
 * The holidays of a school year apply to all of its plans. They are stored one row per holiday line, and holiday text
 * with a line that cannot be read is refused as a whole, so no bad line is ever stored and the teacher keeps the text
 * to correct it. A school year that still has plans is not deleted. Dates are ISO yyyy-mm-dd strings.
 *
 * School year data, as taken by validate(), create() and update(): name, startdate, enddate; optionally holidays (the
 * holiday text) and evaluations (a list of objects or arrays with name, startdate and enddate). On update, data without
 * holidays or evaluations leaves them as they are (the stored evaluation periods must still fit the new dates), and data
 * with timemodified, the version the change was made from, is refused when the stored school year is newer. Every date
 * lies within dates::FIRST_DAY to dates::LAST_DAY, and a school year is at most dates::MAX_DAYS long.
 *
 * Changing or deleting a school year, and creating or moving a plan into it, hold the school year's lock (lock()), so
 * no plan is ever left in a school year that was deleted at the same moment.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schoolyear_service {
    /** @var int Longest name of a school year or an evaluation period, in characters. */
    public const MAX_NAME = 255;

    /** @var int Longest holiday label, in characters. */
    public const MAX_LABEL = 255;

    /** @var int Largest holiday text, in bytes. */
    public const MAX_HOLIDAY_TEXT = 32768;

    /** @var int Most evaluation periods in one school year. */
    public const MAX_EVALUATIONS = 20;

    /** @var string Holiday error code added to the parser's: the label is longer than MAX_LABEL characters. */
    public const LABEL_TOO_LONG = 'label_too_long';

    /** @var int Seconds a change waits for the lock of its school year. */
    public const LOCK_TIMEOUT = 5;

    /** @var string School year table. */
    private const YEAR = 'local_classprogress_schoolyear';

    /** @var string Holiday table. */
    private const HOLIDAY = 'local_classprogress_holiday';

    /** @var string Evaluation period table. */
    private const EVALUATION = 'local_classprogress_evaluation';

    /**
     * Creates a school year with its holidays and evaluation periods.
     *
     * @param int $userid The acting user, who owns the new school year.
     * @param \stdClass $data School year data (see the class description).
     * @return int The new school year id.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception With the first error of validate() when the data is not valid.
     */
    public static function create(int $userid, \stdClass $data): int {
        global $DB;

        self::require_access($userid);
        self::require_valid($data);
        return self::in_transaction(function () use ($DB, $userid, $data): int {
            $now = time();
            $id = (int) $DB->insert_record(self::YEAR, (object) [
                'userid' => $userid,
                'name' => trim($data->name),
                'startdate' => $data->startdate,
                'enddate' => $data->enddate,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            self::write_children($id, $data, $now);
            return $id;
        });
    }

    /**
     * Changes a school year; holidays and evaluation periods given in the data replace the stored ones.
     *
     * The change holds the school year's lock, and its timemodified is always later than the one it replaces.
     *
     * @param int $userid The acting user.
     * @param int $id School year id.
     * @param \stdClass $data School year data (see the class description).
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, error_changedelsewhere, the first error of validate(), or locktimeout.
     */
    public static function update(int $userid, int $id, \stdClass $data): void {
        self::require_access($userid);
        self::own($userid, $id);
        $lock = self::lock($id);
        try {
            $year = self::own($userid, $id);
            self::require_current($year, $data);
            $check = clone $data;
            $check->evaluations ??= array_values(self::evaluations($userid, $id));
            self::require_valid($check);
            self::write($id, $data, max(time(), (int) $year->timemodified + 1));
        } finally {
            $lock->release();
        }
    }

    /**
     * Deletes a school year with its holidays and evaluation periods. A school year that has plans is kept.
     *
     * @param int $userid The acting user.
     * @param int $id School year id.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, error_schoolyearhasplans, or locktimeout.
     */
    public static function delete(int $userid, int $id): void {
        global $DB;

        self::require_access($userid);
        self::own($userid, $id);
        $lock = self::lock($id);
        try {
            if (self::plan_count($userid, $id) > 0) {
                throw new \moodle_exception('error_schoolyearhasplans', 'local_classprogress');
            }
            self::in_transaction(function () use ($DB, $id): void {
                $DB->delete_records(self::HOLIDAY, ['schoolyearid' => $id]);
                $DB->delete_records(self::EVALUATION, ['schoolyearid' => $id]);
                $DB->delete_records(self::YEAR, ['id' => $id]);
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Takes the lock of a school year: changing or deleting it, and creating or moving a plan into it, hold it.
     *
     * @param int $id School year id.
     * @return \core\lock\lock The lock; the caller releases it.
     * @throws \moodle_exception locktimeout, when another request holds it longer than LOCK_TIMEOUT seconds.
     */
    public static function lock(int $id): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory('local_classprogress');
        $lock = $factory->get_lock('schoolyear-' . $id, self::LOCK_TIMEOUT);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        return $lock;
    }

    /**
     * Returns one school year.
     *
     * @param int $userid The acting user.
     * @param int $id School year id.
     * @return \stdClass The school year row.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function get(int $userid, int $id): \stdClass {
        self::require_access($userid);
        return self::own($userid, $id);
    }

    /**
     * Lists the user's school years, the one that starts last first.
     *
     * @param int $userid The acting user.
     * @return \stdClass[] School year rows, keyed by id.
     * @throws \required_capability_exception When the user may not use the plugin.
     */
    public static function list(int $userid): array {
        global $DB;

        self::require_access($userid);
        return $DB->get_records(self::YEAR, ['userid' => $userid], 'startdate DESC, id DESC');
    }

    /**
     * Returns the holidays of a school year, in the order of the text they were read from.
     *
     * @param int $userid The acting user.
     * @param int $id School year id.
     * @return holiday_entry[] Holidays keyed by holiday id; each line is its position in holidays_to_text(), from 1.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function holidays(int $userid, int $id): array {
        global $DB;

        self::require_access($userid);
        self::own($userid, $id);
        $entries = [];
        foreach ($DB->get_records(self::HOLIDAY, ['schoolyearid' => $id], 'id') as $row) {
            $entries[$row->id] = new holiday_entry(count($entries) + 1, $row->startdate, $row->enddate, $row->label);
        }
        return $entries;
    }

    /**
     * Returns the evaluation periods of a school year, in date order.
     *
     * @param int $userid The acting user.
     * @param int $id School year id.
     * @return \stdClass[] Evaluation rows keyed by id.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function evaluations(int $userid, int $id): array {
        global $DB;

        self::require_access($userid);
        self::own($userid, $id);
        return $DB->get_records(self::EVALUATION, ['schoolyearid' => $id], 'startdate, id');
    }

    /**
     * Counts the plans of a school year.
     *
     * @param int $userid The acting user.
     * @param int $id School year id.
     * @return int Number of plans.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function count_plans(int $userid, int $id): int {
        self::require_access($userid);
        self::own($userid, $id);
        return self::plan_count($userid, $id);
    }

    /**
     * Reads holiday text as it would be stored: the parser's result, plus labels too long to store as bad lines.
     *
     * @param string $text Holiday text, one holiday per line.
     * @return holiday_parse_result The holidays that would be stored, and every bad line in line order.
     */
    public static function check_holidays(string $text): holiday_parse_result {
        $parsed = holiday_parser::parse($text);
        $long = array_filter($parsed->entries, fn(holiday_entry $h): bool => \core_text::strlen($h->label) > self::MAX_LABEL);
        if (!$long) {
            return $parsed;
        }
        if (strncmp($text, "\u{FEFF}", 3) === 0) {
            $text = substr($text, 3);
        }
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $errors = $parsed->errors;
        foreach ($long as $entry) {
            $errors[] = new holiday_error($entry->line, self::LABEL_TOO_LONG, trim($lines[$entry->line - 1]));
        }
        usort($errors, fn(holiday_error $a, holiday_error $b): int => $a->line <=> $b->line);
        return new holiday_parse_result(array_values(array_diff_key($parsed->entries, $long)), $errors);
    }

    /**
     * Writes holidays as text, one line each, that check_holidays() reads back as the same holidays.
     *
     * @param holiday_entry[] $entries Holidays, usually from holidays().
     * @return string The text, lines separated by a line feed.
     */
    public static function holidays_to_text(array $entries): string {
        $lines = [];
        foreach ($entries as $entry) {
            $line = trim($entry->start . ' ' . $entry->label);
            $read = holiday_parser::parse($line)->entries;
            $same = count($read) === 1 && $read[0]->end === $entry->end && $read[0]->label === $entry->label;
            if (!$same) {
                // A period, or a label such as "to 5" that after a single day reads as a mistyped period.
                $line = trim("{$entry->start} to {$entry->end} {$entry->label}");
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    /**
     * Checks school year data.
     *
     * Error keys: name, startdate, enddate, holidays, evaluations (for their number) and evaluations-N for the
     * evaluation under key N of the evaluations array.
     *
     * @param \stdClass $data School year data (see the class description).
     * @return string[] Field => lang string id of the error, in field order; empty when the data is valid.
     */
    public static function validate(\stdClass $data): array {
        $errors = [];
        if (!self::is_name((string) ($data->name ?? ''), self::MAX_NAME)) {
            $errors['name'] = 'error_name';
        }
        $start = (string) ($data->startdate ?? '');
        $end = (string) ($data->enddate ?? '');
        $errors += array_filter(['startdate' => self::date_error($start), 'enddate' => self::date_error($end)]);
        if (!isset($errors['startdate']) && !isset($errors['enddate'])) {
            $range = dates::check_range($start, $end);
            if ($range === dates::REVERSED) {
                $errors['enddate'] = 'error_endbeforestart';
            } else if ($range === dates::TOO_LONG) {
                $errors['enddate'] = 'error_yeartoolong';
            }
        }
        if (isset($data->holidays)) {
            if (strlen($data->holidays) > self::MAX_HOLIDAY_TEXT) {
                $errors['holidays'] = 'error_holidaytexttoolong';
            } else if (self::check_holidays($data->holidays)->errors) {
                $errors['holidays'] = 'error_holidaylines';
            }
        }
        if (isset($data->evaluations)) {
            $inyear = isset($errors['startdate']) || isset($errors['enddate']) ? null : [$start, $end];
            $errors += self::validate_evaluations($data->evaluations, $inyear);
        }
        return $errors;
    }

    /**
     * Checks one date a teacher entered.
     *
     * @param string $text The date as entered.
     * @return string|null error_baddate for a text that is not an existing day written yyyy-mm-dd, error_daterange for
     *     a day outside dates::FIRST_DAY to dates::LAST_DAY, null for a valid date.
     */
    public static function date_error(string $text): ?string {
        if (!dates::is_date($text)) {
            return 'error_baddate';
        }
        return dates::in_window($text) ? null : 'error_daterange';
    }

    /**
     * Tells whether a name, once trimmed, is not empty, fits the length and is one line of valid UTF-8 text.
     *
     * Any other character, such as <, > or &, is allowed: names are stored as typed and escaped when displayed.
     *
     * @param string $name The name as typed.
     * @param int $max Most characters.
     * @return bool True for a valid name.
     */
    public static function is_name(string $name, int $max): bool {
        $name = trim($name);
        return $name !== '' && \core_text::strlen($name) <= $max && preg_match('/^\P{Cc}*$/u', $name) === 1;
    }

    /**
     * Checks evaluation periods: each named, with valid dates in order, inside the school year, none sharing a day.
     *
     * @param array $evaluations Evaluation periods as objects or arrays.
     * @param string[]|null $year First and last day of the school year; null when they are not valid.
     * @return string[] evaluations, or evaluations-N for the period under key N => lang string id.
     */
    private static function validate_evaluations(array $evaluations, ?array $year): array {
        if (count($evaluations) > self::MAX_EVALUATIONS) {
            return ['evaluations' => 'error_toomanyevaluations'];
        }
        $errors = [];
        $ranges = [];
        foreach ($evaluations as $i => $evaluation) {
            $evaluation = (object) $evaluation;
            $start = (string) ($evaluation->startdate ?? '');
            $end = (string) ($evaluation->enddate ?? '');
            $dateerror = self::date_error($start) ?? self::date_error($end);
            if (!self::is_name((string) ($evaluation->name ?? ''), self::MAX_NAME)) {
                $errors["evaluations-{$i}"] = 'error_name';
            } else if ($dateerror !== null) {
                $errors["evaluations-{$i}"] = $dateerror;
            } else if (dates::check_range($start, $end) === dates::REVERSED) {
                $errors["evaluations-{$i}"] = 'error_endbeforestart';
            } else if ($year !== null && (!dates::within($start, ...$year) || !dates::within($end, ...$year))) {
                $errors["evaluations-{$i}"] = 'error_evaluationoutsideyear';
            } else {
                $ranges[$i] = [dates::day_number($start), dates::day_number($end)];
            }
        }
        uasort($ranges, fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $lastend = PHP_INT_MIN;
        foreach ($ranges as $i => [$start, $end]) {
            if ($start <= $lastend) {
                $errors["evaluations-{$i}"] = 'error_evaluationsoverlap';
            }
            $lastend = max($lastend, $end);
        }
        ksort($errors, SORT_NATURAL);
        return $errors;
    }

    /**
     * Refuses a change made from an older version of the school year than the stored one.
     *
     * @param \stdClass $year The stored school year.
     * @param \stdClass $data School year data; its timemodified, when set, is the version the change was made from.
     * @throws \moodle_exception error_changedelsewhere.
     */
    private static function require_current(\stdClass $year, \stdClass $data): void {
        if (!empty($data->timemodified) && (int) $data->timemodified !== (int) $year->timemodified) {
            throw new \moodle_exception('error_changedelsewhere', 'local_classprogress');
        }
    }

    /**
     * Throws the first validation error, if there is one.
     *
     * @param \stdClass $data School year data.
     * @throws \moodle_exception With the lang string id of the first error.
     */
    private static function require_valid(\stdClass $data): void {
        $errors = self::validate($data);
        if ($errors) {
            throw new \moodle_exception(reset($errors), 'local_classprogress');
        }
    }

    /**
     * Refuses a user who may not use the plugin, checked live.
     *
     * @param int $userid The acting user.
     * @throws \required_capability_exception When the user does not qualify.
     */
    private static function require_access(int $userid): void {
        if (!access::qualifies($userid)) {
            throw new \required_capability_exception(\context_system::instance(), access::CAPABILITY, 'nopermissions', '');
        }
    }

    /**
     * Counts the plans of a school year of the user.
     *
     * @param int $userid The acting user.
     * @param int $id School year id, already checked to be the user's.
     * @return int Number of plans.
     */
    private static function plan_count(int $userid, int $id): int {
        global $DB;

        return $DB->count_records('local_classprogress_plan', ['schoolyearid' => $id, 'userid' => $userid]);
    }

    /**
     * Returns a school year of the user.
     *
     * @param int $userid The acting user.
     * @param int $id School year id.
     * @return \stdClass The school year row.
     * @throws \moodle_exception error_notfound, whether the school year is missing or another user's.
     */
    private static function own(int $userid, int $id): \stdClass {
        global $DB;

        $year = $DB->get_record(self::YEAR, ['id' => $id, 'userid' => $userid]);
        if (!$year) {
            throw new \moodle_exception('error_notfound', 'local_classprogress');
        }
        return $year;
    }

    /**
     * Writes valid data over a school year, in one transaction.
     *
     * @param int $id School year id.
     * @param \stdClass $data Valid school year data.
     * @param int $now Time of the change, later than the stored timemodified.
     */
    private static function write(int $id, \stdClass $data, int $now): void {
        global $DB;

        self::in_transaction(function () use ($DB, $id, $data, $now): void {
            $DB->update_record(self::YEAR, (object) [
                'id' => $id,
                'name' => trim($data->name),
                'startdate' => $data->startdate,
                'enddate' => $data->enddate,
                'timemodified' => $now,
            ]);
            self::write_children($id, $data, $now);
        });
    }

    /**
     * Replaces the holidays and evaluation periods that the data gives.
     *
     * @param int $id School year id.
     * @param \stdClass $data Valid school year data.
     * @param int $now Time of the change.
     */
    private static function write_children(int $id, \stdClass $data, int $now): void {
        global $DB;

        if (isset($data->holidays)) {
            $DB->delete_records(self::HOLIDAY, ['schoolyearid' => $id]);
            $DB->insert_records(self::HOLIDAY, array_map(fn(holiday_entry $h): array => [
                'schoolyearid' => $id,
                'startdate' => $h->start,
                'enddate' => $h->end,
                'label' => $h->label,
                'timecreated' => $now,
                'timemodified' => $now,
            ], self::check_holidays($data->holidays)->entries));
        }
        if (isset($data->evaluations)) {
            $rows = [];
            foreach ($data->evaluations as $evaluation) {
                $evaluation = (object) $evaluation;
                $rows[] = [
                    'schoolyearid' => $id,
                    'name' => trim($evaluation->name),
                    'startdate' => $evaluation->startdate,
                    'enddate' => $evaluation->enddate,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
            }
            $DB->delete_records(self::EVALUATION, ['schoolyearid' => $id]);
            $DB->insert_records(self::EVALUATION, $rows);
        }
    }

    /**
     * Runs writes in one delegated transaction: all of them are stored, or none.
     *
     * @param callable $writes The writes; what they return is returned.
     * @return mixed What the writes return.
     */
    private static function in_transaction(callable $writes): mixed {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $result = $writes();
            $transaction->allow_commit();
            return $result;
        } catch (\Throwable $e) {
            // Rolls back and throws the exception again.
            $transaction->rollback($e);
        }
    }
}
