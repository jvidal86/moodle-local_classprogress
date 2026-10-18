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

namespace local_classprogress\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_classprogress\local\data_eraser;

/**
 * Privacy provider: all data of the plugin belongs to a teacher and lives in that teacher's user context.
 *
 * A teacher owns their school years (with holidays and evaluations) and their plans (with slots, plan items, entries,
 * entry history and imports). The user who made a change and the real user behind a "log in as" are kept in the
 * owner's history as user ids: they are exported to the owner as part of that history and deleted with it, and they
 * do not make the history part of the acting user's own data.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] Calendar strings for the weekdays of a slot, Monday = 0. */
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Describes every table and field the plugin stores.
     *
     * @param collection $collection The collection to add to.
     * @return collection The same collection.
     */
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $tables = [
            'schoolyear' => ['userid', 'name', 'startdate', 'enddate', 'timecreated', 'timemodified'],
            'holiday' => ['schoolyearid', 'startdate', 'enddate', 'label', 'timecreated', 'timemodified'],
            'evaluation' => ['schoolyearid', 'name', 'startdate', 'enddate', 'timecreated', 'timemodified'],
            'plan' => ['userid', 'schoolyearid', 'courseid', 'modulecode', 'moduletitle', 'startdate', 'enddate', 'status',
                'sourcetext', 'timeapproved', 'timecreated', 'timemodified'],
            'slot' => ['planid', 'weekday', 'hours', 'timecreated', 'timemodified'],
            'planitem' => ['planid', 'sortorder', 'sessiondate', 'block', 'title', 'content', 'hours', 'timecreated'],
            'entry' => ['planid', 'planitemid', 'sessiondate', 'status', 'content', 'contentformat', 'reason', 'reasonnote',
                'measure', 'carry', 'writtenon', 'timecreated', 'timemodified'],
            'entry_hist' => ['entryid', 'planid', 'userid', 'realuserid', 'action', 'snapshot', 'timecreated'],
            'import' => ['planid', 'source', 'filename', 'contenthash', 'daycount', 'timecreated'],
        ];
        foreach ($tables as $name => $fields) {
            $table = 'local_classprogress_' . $name;
            $strings = [];
            foreach ($fields as $field) {
                $strings[$field] = "privacy:metadata:{$table}:{$field}";
            }
            $collection->add_database_table($table, $strings, "privacy:metadata:{$table}");
        }
        return $collection;
    }

    /**
     * Finds the contexts holding a user's data: their own user context, if they own anything.
     *
     * @param int $userid The user.
     * @return contextlist The user's context, or an empty list.
     */
    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND ctx.instanceid = :userid
                   AND (EXISTS (SELECT 1 FROM {local_classprogress_schoolyear} y WHERE y.userid = :yearuserid)
                        OR EXISTS (SELECT 1 FROM {local_classprogress_plan} p WHERE p.userid = :planuserid))";
        $params = ['contextlevel' => CONTEXT_USER, 'userid' => $userid, 'yearuserid' => $userid, 'planuserid' => $userid];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Finds the users with data in a context: the owner of a user context, if they own anything.
     *
     * @param userlist $userlist The list to fill, for one context.
     */
    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }

        $sql = "SELECT userid FROM {local_classprogress_schoolyear} WHERE userid = :yearuserid
                 UNION
                SELECT userid FROM {local_classprogress_plan} WHERE userid = :planuserid";
        $userlist->add_from_sql('userid', $sql, ['yearuserid' => $context->instanceid, 'planuserid' => $context->instanceid]);
    }

    /**
     * Exports everything the user owns, history included, when their own user context is approved.
     *
     * @param approved_contextlist $contextlist The approved contexts of one user.
     */
    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (self::is_own_context($context, $userid)) {
                self::export_owner_data($context);
            }
        }
    }

    /**
     * Deletes everything owned by the owner of a user context; other kinds of context hold no data.
     *
     * @param \context $context The context to clear.
     */
    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_user) {
            data_eraser::delete_user_data((int) $context->instanceid);
        }
    }

    /**
     * Deletes everything the user owns, history included, when their own user context is approved.
     *
     * @param approved_contextlist $contextlist The approved contexts of one user.
     */
    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (self::is_own_context($context, $userid)) {
                data_eraser::delete_user_data($userid);
                return;
            }
        }
    }

    /**
     * Deletes everything owned by the owner of a user context, when that owner is one of the approved users.
     *
     * @param approved_userlist $userlist The approved users of one context.
     */
    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }
        $owner = (int) $context->instanceid;
        if (in_array($owner, array_map('intval', $userlist->get_userids()), true)) {
            data_eraser::delete_user_data($owner);
        }
    }

    /**
     * Whether a context is the user context of the given user, the only place their data lives.
     *
     * @param \context $context The context.
     * @param int $userid The user.
     * @return bool True for the user's own user context.
     */
    private static function is_own_context(\context $context, int $userid): bool {
        return $context instanceof \context_user && (int) $context->instanceid === $userid;
    }

    /**
     * Writes the school years and plans of the owner of a user context, one subcontext each.
     *
     * @param \context_user $context The owner's user context.
     */
    private static function export_owner_data(\context_user $context): void {
        global $DB;

        $userid = (int) $context->instanceid;
        $writer = writer::with_context($context);
        $root = get_string('pluginname', 'local_classprogress');

        $years = $DB->get_records('local_classprogress_schoolyear', ['userid' => $userid], 'startdate, id');
        foreach ($years as $year) {
            $path = [$root, get_string('privacy:path:schoolyear', 'local_classprogress', $year->id)];
            $writer->export_data($path, self::export_schoolyear($year));
        }

        $plans = $DB->get_records('local_classprogress_plan', ['userid' => $userid], 'id');
        foreach ($plans as $plan) {
            $path = [$root, get_string('privacy:path:plan', 'local_classprogress', $plan->id)];
            $writer->export_data($path, self::export_plan($plan, $years[$plan->schoolyearid] ?? null));
        }
    }

    /**
     * Builds the export of a school year with its holidays and evaluation periods.
     *
     * @param \stdClass $year The school year row.
     * @return \stdClass The data to write.
     */
    private static function export_schoolyear(\stdClass $year): \stdClass {
        global $DB;

        $holidays = [];
        foreach ($DB->get_records('local_classprogress_holiday', ['schoolyearid' => $year->id], 'startdate, id') as $row) {
            $holidays[] = (object) ([
                'startdate' => $row->startdate,
                'enddate' => $row->enddate,
                'label' => $row->label,
            ] + self::times($row));
        }
        $evaluations = [];
        foreach ($DB->get_records('local_classprogress_evaluation', ['schoolyearid' => $year->id], 'startdate, id') as $row) {
            $evaluations[] = (object) ([
                'name' => $row->name,
                'startdate' => $row->startdate,
                'enddate' => $row->enddate,
            ] + self::times($row));
        }

        return (object) ([
            'name' => $year->name,
            'startdate' => $year->startdate,
            'enddate' => $year->enddate,
        ] + self::times($year) + [
            'holidays' => $holidays,
            'evaluations' => $evaluations,
        ]);
    }

    /**
     * Builds the export of a plan: its fields and source text, timetable, imports, planned days with the entry of
     * each, unplanned entries, and the whole history, including that of deleted entries.
     *
     * @param \stdClass $plan The plan row.
     * @param \stdClass|null $year The plan's school year, if the owner has it.
     * @return \stdClass The data to write.
     */
    private static function export_plan(\stdClass $plan, ?\stdClass $year): \stdClass {
        global $DB;

        $byplan = ['planid' => $plan->id];

        $slots = [];
        foreach ($DB->get_records('local_classprogress_slot', $byplan, 'weekday, id') as $row) {
            $weekday = self::WEEKDAYS[(int) $row->weekday] ?? null;
            $slots[] = (object) ([
                'weekday' => $weekday ? get_string($weekday, 'calendar') : $row->weekday,
                'hours' => (float) $row->hours,
            ] + self::times($row));
        }

        $imports = [];
        foreach ($DB->get_records('local_classprogress_import', $byplan, 'timecreated, id') as $row) {
            $imports[] = (object) ([
                'source' => $row->source,
                'filename' => $row->filename,
                'contenthash' => $row->contenthash,
                'daycount' => (int) $row->daycount,
            ] + self::times($row));
        }

        $entries = [];
        foreach ($DB->get_records('local_classprogress_entry', $byplan, 'sessiondate, id') as $row) {
            $entries[(int) $row->planitemid][] = self::export_entry($row);
        }

        $items = [];
        foreach ($DB->get_records('local_classprogress_planitem', $byplan, 'sortorder, id') as $row) {
            $items[] = (object) ([
                'sortorder' => (int) $row->sortorder,
                'sessiondate' => $row->sessiondate,
                'block' => $row->block,
                'title' => $row->title,
                'content' => $row->content,
                'hours' => $row->hours === null ? null : (float) $row->hours,
            ] + self::times($row) + [
                'entry' => $entries[(int) $row->id][0] ?? null,
            ]);
            unset($entries[(int) $row->id]);
        }

        $history = [];
        foreach ($DB->get_records('local_classprogress_entry_hist', $byplan, 'timecreated, id') as $row) {
            $snapshot = json_decode($row->snapshot);
            $history[] = (object) ([
                'entryid' => (int) $row->entryid,
                'action' => $row->action,
                'userid' => transform::user((int) $row->userid),
                'realuserid' => $row->realuserid === null ? null : transform::user((int) $row->realuserid),
                'snapshot' => $snapshot ?? $row->snapshot,
            ] + self::times($row));
        }

        return (object) ([
            'schoolyear' => $year ? $year->name : null,
            'courseid' => $plan->courseid === null ? null : (int) $plan->courseid,
            'modulecode' => $plan->modulecode,
            'moduletitle' => $plan->moduletitle,
            'startdate' => $plan->startdate,
            'enddate' => $plan->enddate,
            'status' => $plan->status,
            'timeapproved' => $plan->timeapproved ? transform::datetime($plan->timeapproved) : null,
        ] + self::times($plan) + [
            'sourcetext' => $plan->sourcetext,
            'slots' => $slots,
            'imports' => $imports,
            'items' => $items,
            // Entries without a plan item, and any whose item is not in this plan.
            'unplannedentries' => array_merge(...array_values($entries)),
            'history' => $history,
        ]);
    }

    /**
     * Builds the export of an entry. The id is kept so that the history rows can be matched to it.
     *
     * @param \stdClass $entry The entry row.
     * @return \stdClass The data to write.
     */
    private static function export_entry(\stdClass $entry): \stdClass {
        return (object) ([
            'id' => (int) $entry->id,
            'sessiondate' => $entry->sessiondate,
            'status' => $entry->status,
            'content' => $entry->content,
            'contentformat' => (int) $entry->contentformat,
            'reason' => $entry->reason,
            'reasonnote' => $entry->reasonnote,
            'measure' => $entry->measure,
            'carry' => transform::yesno($entry->carry),
            'writtenon' => $entry->writtenon,
        ] + self::times($entry));
    }

    /**
     * Formats the audit times a row has.
     *
     * @param \stdClass $row A row with timecreated and maybe timemodified.
     * @return array Field => readable date and time.
     */
    private static function times(\stdClass $row): array {
        $times = ['timecreated' => transform::datetime($row->timecreated)];
        if (property_exists($row, 'timemodified')) {
            $times['timemodified'] = transform::datetime($row->timemodified);
        }
        return $times;
    }
}
