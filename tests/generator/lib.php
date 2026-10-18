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
 * Each method takes the fields to set and fills the rest with defaults that make a valid row. A missing school year is
 * created on the way. The owner defaults to the current user, or to a new user when nobody is logged in. Every method
 * returns the row as stored in the database. Plans, entries and their history are made by mod_classprogress's
 * generator.
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
     * The owner for a new school year: the current user, or a new user when nobody is logged in.
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
