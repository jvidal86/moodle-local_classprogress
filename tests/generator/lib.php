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

/**
 * Data generator for the class progress plugin.
 *
 * @package    local_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates rows in the plugin tables for tests, one method per table.
 *
 * Each method takes the fields to set and fills the rest with defaults that make a valid row. A missing parent is
 * created on the way, owned by the same user: a plan without a school year gets one, a plan item without a plan gets
 * one, and so on. The owner defaults to the current user, or to a new user when nobody is logged in. Every method
 * returns the row as stored in the database.
 *
 * @package    local_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_classprogress_generator extends component_generator_base {
    /** @var int Records created since the last reset, used to make default names unique. */
    protected int $count = 0;

    /**
     * Clears the counter between tests.
     */
    #[\Override]
    public function reset() {
        $this->count = 0;
    }

    /**
     * Creates a school year.
     *
     * @param array $record Fields to set. Defaults: the default owner, 2026-09-14 to 2027-06-21.
     * @return stdClass The stored row.
     */
    public function create_schoolyear(array $record = []): stdClass {
        $this->count++;
        $record['userid'] ??= $this->default_userid();

        return $this->insert('schoolyear', [
            'name' => 'School year ' . $this->count,
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * Creates a holiday.
     *
     * @param array $record Fields to set. Defaults: a new school year, the single day 2026-10-12; enddate defaults to
     *     startdate.
     * @return stdClass The stored row.
     */
    public function create_holiday(array $record = []): stdClass {
        $this->count++;
        $record['schoolyearid'] ??= $this->create_schoolyear()->id;
        $record['startdate'] ??= '2026-10-12';
        $record['enddate'] ??= $record['startdate'];

        return $this->insert('holiday', [
            'label' => 'Holiday ' . $this->count,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * Creates an evaluation period.
     *
     * @param array $record Fields to set. Defaults: a new school year, 2026-09-14 to 2026-12-22.
     * @return stdClass The stored row.
     */
    public function create_evaluation(array $record = []): stdClass {
        $this->count++;
        $record['schoolyearid'] ??= $this->create_schoolyear()->id;

        return $this->insert('evaluation', [
            'name' => 'Evaluation ' . $this->count,
            'startdate' => '2026-09-14',
            'enddate' => '2026-12-22',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * Creates a plan.
     *
     * @param array $record Fields to set. Defaults: the school year's owner (or the default owner, with a new school
     *     year), no course, a draft spanning the school year with a one-line Markdown source. An approved plan gets
     *     the current time as timeapproved unless one is given.
     * @return stdClass The stored row.
     */
    public function create_plan(array $record = []): stdClass {
        global $DB;

        $this->count++;
        if (!isset($record['schoolyearid'])) {
            $record['userid'] ??= $this->default_userid();
            $record['schoolyearid'] = $this->create_schoolyear(['userid' => $record['userid']])->id;
        }
        $year = $DB->get_record('local_classprogress_schoolyear', ['id' => $record['schoolyearid']], '*', MUST_EXIST);
        $record['userid'] ??= $year->userid;
        $record['status'] ??= 'draft';
        $record['timeapproved'] ??= $record['status'] === 'approved' ? time() : 0;
        $code = sprintf('MP%02d', $this->count);

        return $this->insert('plan', [
            'courseid' => null,
            'modulecode' => $code,
            'moduletitle' => 'Module ' . $this->count,
            'startdate' => $year->startdate,
            'enddate' => $year->enddate,
            'sourcetext' => "# {$code} · Module {$this->count}\n",
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * Creates a timetable slot.
     *
     * @param array $record Fields to set. Defaults: a new plan, the plan's first weekday without a slot (Monday = 0),
     *     2 hours.
     * @return stdClass The stored row.
     */
    public function create_slot(array $record = []): stdClass {
        global $DB;

        $this->count++;
        $record['planid'] ??= $this->create_plan()->id;
        if (!isset($record['weekday'])) {
            $used = $DB->get_fieldset_select('local_classprogress_slot', 'weekday', 'planid = ?', [$record['planid']]);
            $record['weekday'] = array_values(array_diff(range(0, 6), array_map('intval', $used)))[0] ?? 0;
        }

        return $this->insert('slot', [
            'hours' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * Creates a plan item (a planned day).
     *
     * @param array $record Fields to set. Defaults: a new plan, placed after the plan's last item, on 2026-09-14,
     *     block "RA1", no title, a one-line body, 2 hours.
     * @return stdClass The stored row.
     */
    public function create_planitem(array $record = []): stdClass {
        global $DB;

        $this->count++;
        $record['planid'] ??= $this->create_plan()->id;
        $last = 'SELECT MAX(sortorder) FROM {local_classprogress_planitem} WHERE planid = ?';
        $record['sortorder'] ??= 1 + (int) $DB->get_field_sql($last, [$record['planid']]);

        return $this->insert('planitem', [
            'sessiondate' => '2026-09-14',
            'block' => 'RA1',
            'title' => '',
            'content' => 'Planned content ' . $this->count,
            'hours' => 2,
            'timecreated' => time(),
        ], $record);
    }

    /**
     * Creates an entry.
     *
     * @param array $record Fields to set. Defaults: unplanned (planitemid null) in a new plan on 2026-09-14; with a
     *     planitemid, the item's plan and date. Status "done", a one-line Markdown text, no reason, written on the
     *     session date.
     * @return stdClass The stored row.
     */
    public function create_entry(array $record = []): stdClass {
        global $DB;

        $this->count++;
        if (!empty($record['planitemid'])) {
            $item = $DB->get_record('local_classprogress_planitem', ['id' => $record['planitemid']], '*', MUST_EXIST);
            $record['planid'] ??= $item->planid;
            $record['sessiondate'] ??= $item->sessiondate;
        }
        $record['planid'] ??= $this->create_plan()->id;
        $record['sessiondate'] ??= '2026-09-14';
        $record['writtenon'] ??= $record['sessiondate'];

        return $this->insert('entry', [
            'planitemid' => null,
            'status' => 'done',
            'content' => 'Done ' . $this->count,
            'contentformat' => FORMAT_MARKDOWN,
            'reason' => '',
            'reasonnote' => null,
            'measure' => null,
            'carry' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * Creates a history row for an entry.
     *
     * @param array $record Fields to set. Defaults: a new entry (in planid when given), the entry's plan, written by
     *     the plan's owner, not logged in as anyone, action "create" with a JSON snapshot of the entry. For an entry
     *     that no longer exists, planid must be given.
     * @return stdClass The stored row.
     */
    public function create_entry_hist(array $record = []): stdClass {
        global $DB;

        $this->count++;
        $record['entryid'] ??= $this->create_entry(array_intersect_key($record, ['planid' => true]))->id;
        $entry = $DB->get_record('local_classprogress_entry', ['id' => $record['entryid']]);
        $record['planid'] ??= $entry ? $entry->planid : null;
        $plan = $DB->get_record('local_classprogress_plan', ['id' => $record['planid']], '*', MUST_EXIST);
        $record['userid'] ??= $plan->userid;

        return $this->insert('entry_hist', [
            'realuserid' => null,
            'action' => 'create',
            'snapshot' => json_encode($entry ?: new stdClass()),
            'timecreated' => time(),
        ], $record);
    }

    /**
     * Creates an import log row.
     *
     * @param array $record Fields to set. Defaults: a new plan, pasted text (no file name), a SHA-1 of a made-up text,
     *     no days.
     * @return stdClass The stored row.
     */
    public function create_import(array $record = []): stdClass {
        $this->count++;
        $record['planid'] ??= $this->create_plan()->id;

        return $this->insert('import', [
            'source' => 'paste',
            'filename' => '',
            'contenthash' => sha1('Plan text ' . $this->count),
            'daycount' => 0,
            'timecreated' => time(),
        ], $record);
    }

    /**
     * The owner for a new school year or plan: the current user, or a new user when nobody is logged in.
     *
     * @return int A user id.
     */
    protected function default_userid(): int {
        global $USER;

        return !empty($USER->id) ? (int) $USER->id : (int) $this->datagenerator->create_user()->id;
    }

    /**
     * Inserts a row and reads it back.
     *
     * @param string $table Table name without the local_classprogress_ prefix.
     * @param array $defaults Default values.
     * @param array $record Values given by the caller; they win over the defaults.
     * @return stdClass The stored row.
     */
    protected function insert(string $table, array $defaults, array $record): stdClass {
        global $DB;

        $id = $DB->insert_record('local_classprogress_' . $table, (object) array_merge($defaults, $record));
        return $DB->get_record('local_classprogress_' . $table, ['id' => $id], '*', MUST_EXIST);
    }
}
