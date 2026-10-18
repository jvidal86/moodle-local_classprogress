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
 * A teacher's plans: the draft shell of each plan (module, dates, optional course, weekly timetable) and its calendar.
 *
 * Every method first checks, live, that the acting user may use the plugin (access::qualifies()), and then reaches only
 * that user's rows: a missing id and another user's id give the same exception, error_notfound, and a list of ids is
 * refused as a whole when one of them is not the user's. The
 * calendar of a plan uses the holidays of its school year, so they apply to every plan of that year.
 *
 * Plan data, as taken by validate(), create() and update(): schoolyearid; courseid (0 or null for none); modulecode
 * (may be empty); moduletitle; startdate and enddate (ISO yyyy-mm-dd, or empty or null while unknown); optionally
 * timetable, weekday => hours with Monday = 0 to Sunday = 6, where 0 hours means no class. On update, data without a
 * timetable leaves it as it is. Importing the plan text and approving it come in a later phase; until then every plan
 * is a draft.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_service {
    /** @var int Longest module code, in characters. */
    public const MAX_CODE = 100;

    /** @var int Longest module title, in characters. */
    public const MAX_TITLE = 255;

    /** @var float Most hours of class on one weekday. */
    public const MAX_HOURS = 24.0;

    /** @var string Plan table. */
    private const PLAN = 'local_classprogress_plan';

    /** @var string Timetable slot table. */
    private const SLOT = 'local_classprogress_slot';

    /** @var string[] Tables of the rows that belong to a plan through their planid, deleted with it. */
    private const CHILDREN = [
        'local_classprogress_entry_hist',
        'local_classprogress_entry',
        'local_classprogress_planitem',
        'local_classprogress_import',
        self::SLOT,
    ];

    /**
     * Creates a draft plan with its timetable.
     *
     * @param int $userid The acting user, who owns the new plan.
     * @param \stdClass $data Plan data (see the class description).
     * @return int The new plan id.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception With the first error of validate() when the data is not valid.
     */
    public static function create(int $userid, \stdClass $data): int {
        global $DB;

        self::require_access($userid);
        self::require_valid($userid, $data);
        return self::in_transaction(function () use ($DB, $userid, $data): int {
            $now = time();
            $id = (int) $DB->insert_record(self::PLAN, (object) (self::fields($data) + [
                'userid' => $userid,
                'status' => 'draft',
                'sourcetext' => null,
                'timeapproved' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]));
            self::write_timetable($id, $data, $now);
            return $id;
        });
    }

    /**
     * Changes a plan's shell; a timetable given in the data replaces the stored one.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @param \stdClass $data Plan data (see the class description).
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, or the first error of validate().
     */
    public static function update(int $userid, int $id, \stdClass $data): void {
        global $DB;

        self::require_access($userid);
        self::own($userid, $id);
        self::require_valid($userid, $data);
        self::in_transaction(function () use ($DB, $id, $data): void {
            $now = time();
            $DB->update_record(self::PLAN, (object) (self::fields($data) + ['id' => $id, 'timemodified' => $now]));
            self::write_timetable($id, $data, $now);
        });
    }

    /**
     * Deletes a plan with every row that hangs from it: timetable, items, import log, entries and their history.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function delete(int $userid, int $id): void {
        global $DB;

        self::require_access($userid);
        self::own($userid, $id);
        self::in_transaction(function () use ($DB, $id): void {
            foreach (self::CHILDREN as $table) {
                $DB->delete_records($table, ['planid' => $id]);
            }
            $DB->delete_records(self::PLAN, ['id' => $id]);
        });
    }

    /**
     * Returns one plan.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return \stdClass The plan row.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function get(int $userid, int $id): \stdClass {
        self::require_access($userid);
        return self::own($userid, $id);
    }

    /**
     * Lists the user's plans by module code and title.
     *
     * @param int $userid The acting user.
     * @return \stdClass[] Plan rows, keyed by id.
     * @throws \required_capability_exception When the user may not use the plugin.
     */
    public static function list(int $userid): array {
        global $DB;

        self::require_access($userid);
        return $DB->get_records(self::PLAN, ['userid' => $userid], 'modulecode, moduletitle, id');
    }

    /**
     * Returns a plan's weekly timetable.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return float[] Weekday => hours, Monday = 0 to Sunday = 6, only weekdays with class, in weekday order.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function timetable(int $userid, int $id): array {
        self::require_access($userid);
        self::own($userid, $id);
        return self::timetables([$id])[$id];
    }

    /**
     * Computes a plan's calendar: its sessions from the first to the last class day, minus its school year's holidays.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return calendar_result The calendar; its lost sessions are keyed by holiday id, as schoolyear_service::holidays().
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function calendar(int $userid, int $id): calendar_result {
        return self::calendars($userid, [$id])[$id];
    }

    /**
     * Computes the calendars of several plans.
     *
     * @param int $userid The acting user.
     * @param int[] $ids Plan ids; repeated ids count once.
     * @return calendar_result[] Plan id => calendar, in the order of the ids.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound when any of the ids is missing or another user's.
     */
    public static function calendars(int $userid, array $ids): array {
        global $DB;

        self::require_access($userid);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $plans = $DB->get_records_select(self::PLAN, "id {$insql} AND userid = :userid", $params + ['userid' => $userid]);
        if (count($plans) !== count($ids)) {
            throw new \moodle_exception('error_notfound', 'local_classprogress');
        }
        $timetables = self::timetables($ids);
        $holidays = [];
        $results = [];
        foreach ($ids as $id) {
            $plan = $plans[$id];
            $yearid = (int) $plan->schoolyearid;
            $holidays[$yearid] ??= schoolyear_service::holidays($userid, $yearid);
            $results[$id] = calendar::compute($plan->startdate, $plan->enddate, $timetables[$id], $holidays[$yearid]);
        }
        return $results;
    }

    /**
     * Lists the courses a plan of the user may be linked to: those where the user holds the plugin's capability.
     *
     * @param int $userid The acting user.
     * @return \stdClass[] Course rows with id and fullname, keyed by id, by full name.
     * @throws \required_capability_exception When the user may not use the plugin.
     */
    public static function linkable_courses(int $userid): array {
        self::require_access($userid);
        $courses = get_user_capability_course(access::CAPABILITY, $userid, true, 'fullname', 'fullname') ?: [];
        $linkable = [];
        foreach ($courses as $course) {
            if ((int) $course->id !== SITEID) {
                $linkable[(int) $course->id] = $course;
            }
        }
        return $linkable;
    }

    /**
     * Checks plan data, including that the school year and the course are ones the user may use.
     *
     * Error keys: schoolyearid, courseid, modulecode, moduletitle, startdate, enddate, timetable (for a weekday that
     * does not exist) and timetable-N for the hours of weekday N.
     *
     * @param int $userid The acting user.
     * @param \stdClass $data Plan data (see the class description).
     * @return string[] Field => lang string id of the error, in field order; empty when the data is valid.
     * @throws \required_capability_exception When the user may not use the plugin.
     */
    public static function validate(int $userid, \stdClass $data): array {
        self::require_access($userid);
        return self::errors($userid, $data);
    }

    /**
     * Checks plan data for a user already known to qualify; see validate().
     *
     * @param int $userid The acting user.
     * @param \stdClass $data Plan data.
     * @return string[] Field => lang string id of the error.
     */
    private static function errors(int $userid, \stdClass $data): array {
        global $DB;

        $errors = [];
        $yearid = (int) ($data->schoolyearid ?? 0);
        if (!$DB->record_exists('local_classprogress_schoolyear', ['id' => $yearid, 'userid' => $userid])) {
            $errors['schoolyearid'] = 'error_notfound';
        }
        $courseid = (int) ($data->courseid ?? 0);
        if ($courseid !== 0 && !self::can_link_course($userid, $courseid)) {
            $errors['courseid'] = 'error_course';
        }
        if (\core_text::strlen(trim((string) ($data->modulecode ?? ''))) > self::MAX_CODE) {
            $errors['modulecode'] = 'error_modulecode';
        }
        $title = trim((string) ($data->moduletitle ?? ''));
        if ($title === '' || \core_text::strlen($title) > self::MAX_TITLE) {
            $errors['moduletitle'] = 'error_moduletitle';
        }
        $start = (string) ($data->startdate ?? '');
        $end = (string) ($data->enddate ?? '');
        if ($start !== '' && !schoolyear_service::is_date($start)) {
            $errors['startdate'] = 'error_baddate';
        }
        if ($end !== '' && !schoolyear_service::is_date($end)) {
            $errors['enddate'] = 'error_baddate';
        } else if ($start !== '' && $end !== '' && !isset($errors['startdate']) && $end < $start) {
            $errors['enddate'] = 'error_endbeforestart';
        }
        foreach ($data->timetable ?? [] as $weekday => $hours) {
            if (!is_int($weekday) || $weekday < 0 || $weekday > 6) {
                $errors['timetable'] = 'error_weekday';
            } else if (!is_numeric($hours) || !is_finite((float) $hours) || $hours < 0 || $hours > self::MAX_HOURS) {
                $errors["timetable-{$weekday}"] = 'error_hours';
            }
        }
        return $errors;
    }

    /**
     * Throws the first validation error, if there is one.
     *
     * @param int $userid The acting user.
     * @param \stdClass $data Plan data.
     * @throws \moodle_exception With the lang string id of the first error.
     */
    private static function require_valid(int $userid, \stdClass $data): void {
        $errors = self::errors($userid, $data);
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
     * Returns a plan of the user.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return \stdClass The plan row.
     * @throws \moodle_exception error_notfound, whether the plan is missing or another user's.
     */
    private static function own(int $userid, int $id): \stdClass {
        global $DB;

        $plan = $DB->get_record(self::PLAN, ['id' => $id, 'userid' => $userid]);
        if (!$plan) {
            throw new \moodle_exception('error_notfound', 'local_classprogress');
        }
        return $plan;
    }

    /**
     * Tells whether a plan of the user may be linked to a course.
     *
     * @param int $userid The acting user.
     * @param int $courseid Course id.
     * @return bool True for an existing course, other than the site, where the user holds the plugin's capability.
     */
    private static function can_link_course(int $userid, int $courseid): bool {
        global $DB;

        if ($courseid === SITEID || !$DB->record_exists('course', ['id' => $courseid])) {
            return false;
        }
        return has_capability(access::CAPABILITY, \context_course::instance($courseid), $userid);
    }

    /**
     * The plan fields that valid data sets, cleaned for storage.
     *
     * @param \stdClass $data Valid plan data.
     * @return array Field => value.
     */
    private static function fields(\stdClass $data): array {
        return [
            'schoolyearid' => (int) $data->schoolyearid,
            'courseid' => empty($data->courseid) ? null : (int) $data->courseid,
            'modulecode' => trim((string) ($data->modulecode ?? '')),
            'moduletitle' => trim($data->moduletitle),
            'startdate' => empty($data->startdate) ? null : $data->startdate,
            'enddate' => empty($data->enddate) ? null : $data->enddate,
        ];
    }

    /**
     * Reads the timetables of plans.
     *
     * @param int[] $ids Plan ids, already checked to be the user's.
     * @return array Plan id => (weekday => hours), every id present, weekdays in order.
     */
    private static function timetables(array $ids): array {
        global $DB;

        $timetables = array_fill_keys($ids, []);
        [$insql, $params] = $DB->get_in_or_equal($ids);
        foreach ($DB->get_records_select(self::SLOT, "planid {$insql}", $params, 'planid, weekday') as $slot) {
            $timetables[$slot->planid][(int) $slot->weekday] = (float) $slot->hours;
        }
        return $timetables;
    }

    /**
     * Replaces the timetable when the data gives one: one slot per weekday with hours above zero.
     *
     * @param int $id Plan id.
     * @param \stdClass $data Valid plan data.
     * @param int $now Time of the change.
     */
    private static function write_timetable(int $id, \stdClass $data, int $now): void {
        global $DB;

        if (!isset($data->timetable)) {
            return;
        }
        $rows = [];
        foreach ($data->timetable as $weekday => $hours) {
            $hours = round((float) $hours, 2);
            if ($hours > 0) {
                $rows[] = ['planid' => $id, 'weekday' => $weekday, 'hours' => $hours, 'timecreated' => $now,
                    'timemodified' => $now];
            }
        }
        $DB->delete_records(self::SLOT, ['planid' => $id]);
        $DB->insert_records(self::SLOT, $rows);
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
