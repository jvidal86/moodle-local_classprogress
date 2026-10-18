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

use local_classprogress\event\plan_approved;
use local_classprogress\event\plan_imported;

/**
 * A teacher's plans: the shell of each plan (module, dates, optional course, weekly timetable), its calendar, the
 * import of its Markdown text into a draft, and its approval.
 *
 * Every method first checks, live, that the acting user may use the plugin (access::qualifies()), and then reaches only
 * that user's rows: a missing id and another user's id give the same exception, error_notfound, and a list of ids is
 * refused as a whole when one of them is not the user's. The
 * calendar of a plan uses the holidays of its school year, so they apply to every plan of that year.
 *
 * Plan data, as taken by validate(), create() and update(): schoolyearid; courseid (0 or null for none); modulecode
 * (may be empty); moduletitle; startdate and enddate (ISO yyyy-mm-dd, or empty or null while unknown); optionally
 * timetable, weekday => hours with Monday = 0 to Sunday = 6, where 0 hours means no class. On update, data without a
 * timetable leaves it as it is, and data with timemodified, the version the change was made from, is refused when the
 * stored plan is newer. The dates, when given, lie within the plan's school year. Creating a plan or moving it into a
 * school year holds that school year's lock (schoolyear_service::lock()), so the school year cannot be deleted at the
 * same moment.
 *
 * Import (check(), import()): the text is read by plan_parser with the holidays of the plan's school year, the plan's
 * timetable (none when it has no slots, so class days are not checked) and the school year's dates. To the parser's
 * issues come errors for what could not be stored (the codes below), so they show in the preview instead of failing the
 * save. A text with any error is not saved. Saving replaces the draft's text and items and adds a row to the import
 * log; the # heading's code and title replace the typed ones, each where the heading gives it (imported_module()).
 * Line ends are stored as line feeds. A draft's items follow a later timetable change where the text gives no hours.
 *
 * Approval (check_stored(), approve()) reads the stored text again, now, and goes ahead only if it has no errors, is
 * what the teacher saw (fingerprint()), and its warnings were confirmed. It is final: an approved plan refuses every
 * change through this class (error_planlocked), so its items, dates and hours never move. Import and approval hold the
 * plan's lock and its school year's lock, run in one delegated transaction, and trigger their event after it commits.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_service {
    /** @var string Status of a plan that can still change. */
    public const STATUS_DRAFT = 'draft';

    /** @var string Status of an approved plan, locked for good. */
    public const STATUS_APPROVED = 'approved';

    /** @var string Import source: text pasted into the form. */
    public const SOURCE_PASTE = 'paste';

    /** @var string Import source: an uploaded file, read and not stored. */
    public const SOURCE_UPLOAD = 'upload';

    /** @var int Largest plan text by default, in KB (admin setting maxplansize). */
    public const DEFAULT_MAX_SIZE = 200;

    /** @var int Most days in a plan by default (admin setting maxplandays). */
    public const DEFAULT_MAX_DAYS = 400;

    /** @var string Error added to the parser's: the text has no day. No parameters. */
    public const NO_DAYS = 'no_days';

    /** @var string Error: more days than max_days(), at the first day over the limit. Parameter: max. */
    public const TOO_MANY_DAYS = 'too_many_days';

    /** @var string Error: the module code is longer than MAX_CODE or the title than MAX_TITLE. No parameters. */
    public const MODULE_TOO_LONG = 'module_too_long';

    /** @var string Error: the block above a day is longer than MAX_TITLE, at its first day. Parameter: max. */
    public const BLOCK_TOO_LONG = 'block_too_long';

    /** @var string Error: a day title longer than MAX_TITLE. Parameters: date, max. */
    public const TITLE_TOO_LONG = 'title_too_long';

    /** @var string Error: a day with more than MAX_HOURS hours. Parameters: date, hours. */
    public const TOO_MANY_HOURS = 'too_many_hours';

    /** @var string Error: a day outside dates::FIRST_DAY to dates::LAST_DAY. Parameter: date. */
    public const OUT_OF_RANGE = 'out_of_range';

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

    /** @var string Plan item table: one row per day of the plan text. */
    private const PLANITEM = 'local_classprogress_planitem';

    /** @var string Import log table. */
    private const IMPORT = 'local_classprogress_import';

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
     * @throws \moodle_exception With the first error of validate() when the data is not valid, or locktimeout.
     */
    public static function create(int $userid, \stdClass $data): int {
        global $DB;

        self::require_access($userid);
        $lock = schoolyear_service::lock((int) ($data->schoolyearid ?? 0));
        try {
            self::require_valid($userid, $data, null);
            return self::in_transaction(function () use ($DB, $userid, $data): int {
                $now = time();
                $id = (int) $DB->insert_record(self::PLAN, (object) (self::fields($data) + [
                    'userid' => $userid,
                    'status' => self::STATUS_DRAFT,
                    'sourcetext' => null,
                    'timeapproved' => 0,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]));
                self::write_timetable($id, $data, $now);
                return $id;
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Changes a draft plan's shell; a timetable given in the data replaces the stored one.
     *
     * The change holds the plan's lock and the lock of its (new) school year, and its timemodified is always later than
     * the one it replaces. A course link equal to the stored one is kept even if the user can no longer link that
     * course. When the timetable changes, the items of an imported draft take their hours from it where the text gives
     * none.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @param \stdClass $data Plan data (see the class description).
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, error_planlocked, error_changedelsewhere, the first error of validate(), or
     *     locktimeout.
     */
    public static function update(int $userid, int $id, \stdClass $data): void {
        global $DB;

        self::require_access($userid);
        self::own($userid, $id);
        $work = function (\stdClass $plan) use ($DB, $userid, $id, $data): void {
            self::require_draft($plan);
            if (!empty($data->timemodified) && (int) $data->timemodified !== (int) $plan->timemodified) {
                throw new \moodle_exception('error_changedelsewhere', 'local_classprogress');
            }
            self::require_valid($userid, $data, $plan);
            $now = max(time(), (int) $plan->timemodified + 1);
            self::in_transaction(function () use ($DB, $id, $data, $now, $plan): void {
                $DB->update_record(self::PLAN, (object) (self::fields($data) + ['id' => $id, 'timemodified' => $now]));
                if (self::write_timetable($id, $data, $now) && $plan->sourcetext !== null) {
                    $timetable = self::timetables([$id])[$id];
                    self::write_items($id, (new plan_parser())->parse($plan->sourcetext, null, $timetable ?: null)->days, $now);
                }
            });
        };
        self::locked($userid, $id, (int) ($data->schoolyearid ?? 0), $work);
    }

    /**
     * Deletes a draft plan with every row that hangs from it: timetable, items, import log, entries and their history.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, error_planlocked, or locktimeout.
     */
    public static function delete(int $userid, int $id): void {
        global $DB;

        self::require_access($userid);
        self::own($userid, $id);
        self::locked($userid, $id, null, function (\stdClass $plan) use ($DB, $id): void {
            self::require_draft($plan);
            self::in_transaction(function () use ($DB, $id): void {
                foreach (self::CHILDREN as $table) {
                    $DB->delete_records($table, ['planid' => $id]);
                }
                $DB->delete_records(self::PLAN, ['id' => $id]);
            });
        });
    }

    /**
     * Reads a plan text for a draft plan as import() would, and saves nothing.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @param string $text The plan text, as pasted or uploaded.
     * @return plan_result What the text gives, with the errors for what could not be stored.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, error_planlocked, or error_plantoolarge (with the limit in KB).
     */
    public static function check(int $userid, int $id, string $text): plan_result {
        self::require_access($userid);
        $plan = self::own($userid, $id);
        self::require_draft($plan);
        return self::read($userid, $plan, self::normalise($text));
    }

    /**
     * Saves a plan text as the content of a draft plan: its text, one item per day and a row of the import log, replacing
     * any earlier content; the # heading's code and title replace the plan's (imported_module()).
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @param string $text The plan text, as pasted or uploaded.
     * @param string $source self::SOURCE_PASTE or self::SOURCE_UPLOAD.
     * @param string $filename Name of the uploaded file; empty for pasted text.
     * @return int Id of the import log row.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, error_planlocked, error_plantoolarge, error_planhaserrors when check()
     *     gives an error, or locktimeout.
     */
    public static function import(
        int $userid,
        int $id,
        string $text,
        string $source = self::SOURCE_PASTE,
        string $filename = ''
    ): int {
        self::require_access($userid);
        self::own($userid, $id);
        if ($source !== self::SOURCE_PASTE && $source !== self::SOURCE_UPLOAD) {
            throw new \coding_exception("Unknown import source '{$source}'.");
        }
        $text = self::normalise($text);
        $log = ['source' => $source, 'filename' => \core_text::substr($filename, 0, 255), 'contenthash' => sha1($text)];
        $work = function (\stdClass $plan) use ($userid, $text, $log): array {
            self::require_draft($plan);
            $result = self::read($userid, $plan, $text);
            if (self::count_issues($result, plan_issue::ERROR)) {
                throw new \moodle_exception('error_planhaserrors', 'local_classprogress');
            }
            $importid = self::in_transaction(fn(): int => self::write_content($plan, $text, $result, $log));
            return [$importid, (int) $plan->schoolyearid];
        };
        [$importid, $yearid] = self::locked($userid, $id, null, $work);

        plan_imported::create([
            'objectid' => $id,
            'userid' => $userid,
            'context' => \context_user::instance($userid),
            'other' => ['schoolyearid' => $yearid, 'importid' => $importid],
        ])->trigger();
        return $importid;
    }

    /**
     * Reads the stored text of a plan again, now, with the current holidays, timetable and school year: what approval
     * checks.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return plan_result What the stored text gives now.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, error_notimported, or error_plantoolarge when the limit was lowered.
     */
    public static function check_stored(int $userid, int $id): plan_result {
        self::require_access($userid);
        $plan = self::own($userid, $id);
        if ($plan->sourcetext === null) {
            throw new \moodle_exception('error_notimported', 'local_classprogress');
        }
        return self::read($userid, $plan, $plan->sourcetext);
    }

    /**
     * Approves a draft plan, for good: it then refuses every change.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @param string $seen fingerprint() of the check_stored() result the teacher was shown.
     * @param bool $warningsread Whether the teacher confirmed that they read the warnings.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, error_planlocked, error_notimported, error_planhaserrors,
     *     error_changedelsewhere when the stored text now gives something else than what was seen, error_confirmwarnings,
     *     error_plantoolarge, or locktimeout.
     */
    public static function approve(int $userid, int $id, string $seen, bool $warningsread): void {
        global $DB;

        self::require_access($userid);
        self::own($userid, $id);
        $yearid = self::locked($userid, $id, null, function (\stdClass $plan) use ($DB, $userid, $id, $seen, $warningsread): int {
            self::require_draft($plan);
            if ($plan->sourcetext === null) {
                throw new \moodle_exception('error_notimported', 'local_classprogress');
            }
            $result = self::read($userid, $plan, $plan->sourcetext);
            if (self::count_issues($result, plan_issue::ERROR)) {
                throw new \moodle_exception('error_planhaserrors', 'local_classprogress');
            }
            if (!hash_equals(self::fingerprint($result), $seen)) {
                throw new \moodle_exception('error_changedelsewhere', 'local_classprogress');
            }
            if (self::count_issues($result, plan_issue::WARNING) && !$warningsread) {
                throw new \moodle_exception('error_confirmwarnings', 'local_classprogress');
            }
            $now = max(time(), (int) $plan->timemodified + 1);
            self::in_transaction(fn() => $DB->update_record(self::PLAN, (object) ['id' => $id,
                'status' => self::STATUS_APPROVED, 'timeapproved' => $now, 'timemodified' => $now]));
            return (int) $plan->schoolyearid;
        });

        plan_approved::create([
            'objectid' => $id,
            'userid' => $userid,
            'context' => \context_user::instance($userid),
            'other' => ['schoolyearid' => $yearid],
        ])->trigger();
    }

    /**
     * Returns the items of a plan: one per day of its text.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return \stdClass[] Plan item rows keyed by id, in the order of the text.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function items(int $userid, int $id): array {
        global $DB;

        self::require_access($userid);
        self::own($userid, $id);
        return $DB->get_records(self::PLANITEM, ['planid' => $id], 'sortorder, id');
    }

    /**
     * Identifies what a reading of a plan gives, so that approval can tell whether it is still what the teacher saw.
     *
     * @param plan_result $result A result of check() or check_stored().
     * @return string A SHA-1 of the module, blocks, days and issues.
     */
    public static function fingerprint(plan_result $result): string {
        return sha1(json_encode($result, JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /**
     * Counts the issues of one level.
     *
     * @param plan_result $result A result of check() or check_stored().
     * @param string $level plan_issue::ERROR or plan_issue::WARNING.
     * @return int The number of issues of that level.
     */
    public static function count_issues(plan_result $result, string $level): int {
        return count(array_filter($result->issues, fn(plan_issue $issue): bool => $issue->level === $level));
    }

    /**
     * The module code and title that importing a text stores: those of its # heading where it gives them, else the
     * plan's own.
     *
     * @param \stdClass $plan The plan row.
     * @param plan_result $result What the text gives.
     * @return string[] [code, title].
     */
    public static function imported_module(\stdClass $plan, plan_result $result): array {
        $code = $result->module?->code ?? '';
        $title = $result->module?->title ?? '';
        return [$code !== '' ? $code : $plan->modulecode, $title !== '' ? $title : $plan->moduletitle];
    }

    /**
     * The largest plan text, from the admin setting maxplansize.
     *
     * @return int Bytes.
     */
    public static function max_size(): int {
        $kb = (int) get_config('local_classprogress', 'maxplansize');
        return ($kb > 0 ? $kb : self::DEFAULT_MAX_SIZE) * 1024;
    }

    /**
     * The most days a plan can have, from the admin setting maxplandays.
     *
     * @return int Days.
     */
    public static function max_days(): int {
        $days = (int) get_config('local_classprogress', 'maxplandays');
        return $days > 0 ? $days : self::DEFAULT_MAX_DAYS;
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
     * A plan whose school year is missing, which should never happen, gets a calendar without holidays.
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
            $holidays[$yearid] ??= self::year_holidays($userid, $yearid);
            $results[$id] = calendar::compute($plan->startdate, $plan->enddate, $timetables[$id], $holidays[$yearid]);
        }
        return $results;
    }

    /**
     * Returns the holidays of a plan's school year, which apply to the plan.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return holiday_entry[] Holidays keyed by holiday id, as schoolyear_service::holidays(); none when the school year
     *     is missing.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound.
     */
    public static function holidays(int $userid, int $id): array {
        self::require_access($userid);
        return self::year_holidays($userid, (int) self::own($userid, $id)->schoolyearid);
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
     * @param int $id The plan being changed, whose course link is kept even if it could not be made now; 0 for a new one.
     * @return string[] Field => lang string id of the error, in field order; empty when the data is valid.
     * @throws \required_capability_exception When the user may not use the plugin.
     * @throws \moodle_exception error_notfound, when $id is not one of the user's plans.
     */
    public static function validate(int $userid, \stdClass $data, int $id = 0): array {
        self::require_access($userid);
        return self::errors($userid, $data, $id ? self::own($userid, $id) : null);
    }

    /**
     * Checks plan data for a user already known to qualify; see validate().
     *
     * @param int $userid The acting user.
     * @param \stdClass $data Plan data.
     * @param \stdClass|null $plan The stored plan being changed; null for a new one.
     * @return string[] Field => lang string id of the error.
     */
    private static function errors(int $userid, \stdClass $data, ?\stdClass $plan): array {
        global $DB;

        $errors = [];
        $yearid = (int) ($data->schoolyearid ?? 0);
        $year = $DB->get_record('local_classprogress_schoolyear', ['id' => $yearid, 'userid' => $userid]);
        if (!$year) {
            $errors['schoolyearid'] = 'error_notfound';
        }
        $courseid = (int) ($data->courseid ?? 0);
        $linked = (int) ($plan->courseid ?? 0);
        if ($courseid !== 0 && $courseid !== $linked && !self::can_link_course($userid, $courseid)) {
            $errors['courseid'] = 'error_course';
        }
        $code = trim((string) ($data->modulecode ?? ''));
        if ($code !== '' && !schoolyear_service::is_name($code, self::MAX_CODE)) {
            $errors['modulecode'] = 'error_modulecode';
        }
        if (!schoolyear_service::is_name((string) ($data->moduletitle ?? ''), self::MAX_TITLE)) {
            $errors['moduletitle'] = 'error_moduletitle';
        }
        $start = (string) ($data->startdate ?? '');
        $end = (string) ($data->enddate ?? '');
        foreach (['startdate' => $start, 'enddate' => $end] as $field => $date) {
            if ($date === '') {
                continue;
            }
            $error = schoolyear_service::date_error($date);
            $afterstart = $field === 'enddate' && $start !== '' && !isset($errors['startdate']);
            if ($error === null && $afterstart && dates::check_range($start, $end) === dates::REVERSED) {
                $error = 'error_endbeforestart';
            }
            if ($error === null && $year && !dates::within($date, $year->startdate, $year->enddate)) {
                $error = 'error_dateoutsideyear';
            }
            if ($error !== null) {
                $errors[$field] = $error;
            }
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
     * @param \stdClass|null $plan The stored plan being changed; null for a new one.
     * @throws \moodle_exception With the lang string id of the first error.
     */
    private static function require_valid(int $userid, \stdClass $data, ?\stdClass $plan): void {
        $errors = self::errors($userid, $data, $plan);
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
     * Refuses a change to a plan that is not a draft.
     *
     * @param \stdClass $plan The plan row.
     * @throws \moodle_exception error_planlocked.
     */
    private static function require_draft(\stdClass $plan): void {
        if ($plan->status !== self::STATUS_DRAFT) {
            throw new \moodle_exception('error_planlocked', 'local_classprogress');
        }
    }

    /**
     * Runs a change holding the plan's lock and then its school year's lock, with the plan as read under the lock.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id, already checked to be the user's.
     * @param int|null $yearid School year whose lock to hold; null for the plan's own.
     * @param callable $work The change; it gets the plan row, and what it returns is returned.
     * @return mixed What the change returns.
     * @throws \moodle_exception error_notfound when the plan went in the meantime, or locktimeout.
     */
    private static function locked(int $userid, int $id, ?int $yearid, callable $work): mixed {
        $factory = \core\lock\lock_config::get_lock_factory('local_classprogress');
        $planlock = $factory->get_lock('plan-' . $id, schoolyear_service::LOCK_TIMEOUT);
        if (!$planlock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $plan = self::own($userid, $id);
            $yearlock = schoolyear_service::lock($yearid ?? (int) $plan->schoolyearid);
            try {
                return $work($plan);
            } finally {
                $yearlock->release();
            }
        } finally {
            $planlock->release();
        }
    }

    /**
     * Reads a plan text for a plan, with the errors for what could not be stored.
     *
     * @param int $userid The acting user, already checked to qualify and to own the plan.
     * @param \stdClass $plan The plan row.
     * @param string $text The text, with normalised line ends.
     * @return plan_result What the text gives.
     * @throws \moodle_exception error_plantoolarge, with the limit in KB.
     */
    private static function read(int $userid, \stdClass $plan, string $text): plan_result {
        global $DB;

        $max = self::max_size();
        if (strlen($text) > $max) {
            throw new \moodle_exception('error_plantoolarge', 'local_classprogress', '', intdiv($max, 1024));
        }
        $year = $DB->get_record('local_classprogress_schoolyear', ['id' => $plan->schoolyearid, 'userid' => $userid]);
        $holidays = holiday_parser::expand(self::year_holidays($userid, (int) $plan->schoolyearid));
        $timetable = self::timetables([(int) $plan->id])[(int) $plan->id];
        $range = $year ? [$year->startdate, $year->enddate] : null;
        $result = (new plan_parser())->parse($text, $holidays, $timetable ?: null, $range);
        return self::with_storage_errors($result);
    }

    /**
     * Adds to a reading the errors for what the plan tables cannot hold or the date limits do not allow.
     *
     * A text that is not UTF-8 keeps its single error.
     *
     * @param plan_result $result What the parser read.
     * @return plan_result The same, with those errors among the issues, ordered by line.
     */
    private static function with_storage_errors(plan_result $result): plan_result {
        foreach ($result->issues as $issue) {
            if ($issue->code === plan_issue::NOT_UTF8) {
                return $result;
            }
        }
        $error = fn(string $code, int $line, array $params = []): plan_issue =>
            new plan_issue(plan_issue::ERROR, $code, $line, $params);
        $issues = [];
        $module = $result->module;
        $codelength = \core_text::strlen($module?->code ?? '');
        if ($module && ($codelength > self::MAX_CODE || \core_text::strlen($module->title) > self::MAX_TITLE)) {
            $issues[] = $error(self::MODULE_TOO_LONG, $module->line);
        }
        if (!$result->days) {
            $issues[] = $error(self::NO_DAYS, 1);
        }
        $maxdays = self::max_days();
        $blocks = [];
        foreach ($result->days as $index => $day) {
            if ($index === $maxdays) {
                $issues[] = $error(self::TOO_MANY_DAYS, $day->line, ['max' => (string) $maxdays]);
            }
            if ($day->block !== null && !isset($blocks[$day->block]) && \core_text::strlen($day->block) > self::MAX_TITLE) {
                $issues[] = $error(self::BLOCK_TOO_LONG, $day->line, ['max' => (string) self::MAX_TITLE]);
            }
            $blocks[$day->block ?? ''] = true;
            if (\core_text::strlen($day->title) > self::MAX_TITLE) {
                $issues[] = $error(self::TITLE_TOO_LONG, $day->line, ['date' => $day->date, 'max' => (string) self::MAX_TITLE]);
            }
            if ($day->hours !== null && $day->hours > self::MAX_HOURS) {
                $issues[] = $error(self::TOO_MANY_HOURS, $day->line, ['date' => $day->date, 'hours' => (string) $day->hours]);
            }
            if (!dates::in_window($day->date)) {
                $issues[] = $error(self::OUT_OF_RANGE, $day->line, ['date' => $day->date]);
            }
        }
        if (!$issues) {
            return $result;
        }
        $issues = array_merge($result->issues, $issues);
        // Stable, so the parser's issues on a line come before these.
        usort($issues, fn(plan_issue $a, plan_issue $b): int => $a->line <=> $b->line);
        return new plan_result($result->module, $result->blocks, $result->days, $issues);
    }

    /**
     * Stores line ends as line feeds and drops null bytes, as Moodle does for posted text.
     *
     * @param string $text A plan text.
     * @return string The same text.
     */
    private static function normalise(string $text): string {
        return str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text);
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
     * Returns the holidays of a school year of the user; none when the school year is missing.
     *
     * @param int $userid The acting user, already checked to qualify.
     * @param int $yearid School year id.
     * @return holiday_entry[] Holidays keyed by holiday id.
     */
    private static function year_holidays(int $userid, int $yearid): array {
        global $DB;

        if (!$DB->record_exists('local_classprogress_schoolyear', ['id' => $yearid, 'userid' => $userid])) {
            return [];
        }
        return schoolyear_service::holidays($userid, $yearid);
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
     * @return bool Whether the data gave a timetable.
     */
    private static function write_timetable(int $id, \stdClass $data, int $now): bool {
        global $DB;

        if (!isset($data->timetable)) {
            return false;
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
        return true;
    }

    /**
     * Writes a checked text as the content of a draft: the plan's module and text, its items and a row of the import log.
     *
     * @param \stdClass $plan The plan row, read under its lock.
     * @param string $text The text, with normalised line ends.
     * @param plan_result $result What the text gives, without errors.
     * @param array $log Fields of the import log row: source, filename and contenthash.
     * @return int Id of the import log row.
     */
    private static function write_content(\stdClass $plan, string $text, plan_result $result, array $log): int {
        global $DB;

        [$code, $title] = self::imported_module($plan, $result);
        $now = max(time(), (int) $plan->timemodified + 1);
        $DB->update_record(self::PLAN, (object) ['id' => $plan->id, 'modulecode' => $code, 'moduletitle' => $title,
            'sourcetext' => $text, 'timemodified' => $now]);
        self::write_items((int) $plan->id, $result->days, $now);
        return (int) $DB->insert_record(self::IMPORT, (object) ($log + [
            'planid' => $plan->id,
            'daycount' => count($result->days),
            'timecreated' => $now,
        ]));
    }

    /**
     * Replaces the items of a plan with one per day.
     *
     * @param int $id Plan id.
     * @param plan_day[] $days The days of its text, in order.
     * @param int $now Time of the change.
     */
    private static function write_items(int $id, array $days, int $now): void {
        global $DB;

        $rows = [];
        foreach (array_values($days) as $index => $day) {
            $rows[] = [
                'planid' => $id,
                'sortorder' => $index + 1,
                'sessiondate' => $day->date,
                'block' => $day->block ?? '',
                'title' => $day->title,
                'content' => $day->content,
                'hours' => $day->hours === null ? null : round($day->hours, 2),
                'timecreated' => $now,
            ];
        }
        $DB->delete_records(self::PLANITEM, ['planid' => $id]);
        $DB->insert_records(self::PLANITEM, $rows);
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
