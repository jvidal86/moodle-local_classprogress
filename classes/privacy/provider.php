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
 * A teacher owns their school years, with their holidays and evaluation periods. Plans, entries and their history are
 * data of the course activity mod_classprogress, which has its own provider.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
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
                   AND EXISTS (SELECT 1 FROM {local_classprogress_schoolyear} y WHERE y.userid = :yearuserid)";
        $params = ['contextlevel' => CONTEXT_USER, 'userid' => $userid, 'yearuserid' => $userid];

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

        $sql = "SELECT userid FROM {local_classprogress_schoolyear} WHERE userid = :yearuserid";
        $userlist->add_from_sql('userid', $sql, ['yearuserid' => $context->instanceid]);
    }

    /**
     * Exports everything the user owns when their own user context is approved.
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
     * Deletes everything the user owns when their own user context is approved.
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
     * Writes the school years of the owner of a user context, one subcontext each.
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
