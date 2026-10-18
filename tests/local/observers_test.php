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
 * Tests for the event observers: deleting a user account removes that user's rows at once.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\local\observers
 * @covers     \local_classprogress\local\data_eraser
 */
final class observers_test extends \advanced_testcase {
    /**
     * Creates one row in every table, owned by one teacher, including the kept history of a deleted entry.
     *
     * @param \stdClass $user The owner.
     * @return array Table name without prefix => ids of the rows created in that table.
     */
    private function create_teacher_data(\stdClass $user): array {
        global $DB;

        $gen = $this->getDataGenerator()->get_plugin_generator('local_classprogress');
        $year = $gen->create_schoolyear(['userid' => $user->id]);
        $plan = $gen->create_plan(['schoolyearid' => $year->id]);
        $item = $gen->create_planitem(['planid' => $plan->id]);
        $entry = $gen->create_entry(['planitemid' => $item->id]);
        $gone = $gen->create_entry(['planid' => $plan->id]);
        $hist = [
            $gen->create_entry_hist(['entryid' => $entry->id]),
            $gen->create_entry_hist(['entryid' => $gone->id, 'action' => 'delete']),
        ];
        $DB->delete_records('local_classprogress_entry', ['id' => $gone->id]);

        return [
            'schoolyear' => [$year->id],
            'holiday' => [$gen->create_holiday(['schoolyearid' => $year->id])->id],
            'evaluation' => [$gen->create_evaluation(['schoolyearid' => $year->id])->id],
            'plan' => [$plan->id],
            'slot' => [$gen->create_slot(['planid' => $plan->id])->id],
            'planitem' => [$item->id],
            'entry' => [$entry->id],
            'entry_hist' => array_column($hist, 'id'),
            'import' => [$gen->create_import(['planid' => $plan->id])->id],
        ];
    }

    /**
     * Counts how many of the given rows still exist.
     *
     * @param array $ids Rows as returned by create_teacher_data().
     * @return int[] Table name => rows left.
     */
    private static function rows_left(array $ids): array {
        global $DB;

        $left = [];
        foreach ($ids as $table => $tableids) {
            [$insql, $params] = $DB->get_in_or_equal($tableids);
            $left[$table] = $DB->count_records_select('local_classprogress_' . $table, "id {$insql}", $params);
        }
        return $left;
    }

    /**
     * Deleting a teacher's account removes all of their rows, history included, and keeps another teacher's.
     *
     * @group REQ-PRV-004
     */
    public function test_deleting_a_user_removes_their_rows(): void {
        $this->resetAfterTest();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $ids1 = $this->create_teacher_data($teacher1);
        $ids2 = $this->create_teacher_data($teacher2);

        $this->assertTrue(delete_user($teacher1));

        $this->assertSame(array_fill_keys(array_keys($ids1), 0), self::rows_left($ids1));
        $this->assertSame(array_map('count', $ids2), self::rows_left($ids2));
    }

    /**
     * Deleting the account of a user who owns nothing here leaves every row alone.
     *
     * @group REQ-PRV-004
     */
    public function test_deleting_a_user_without_data_keeps_all_rows(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $ids = $this->create_teacher_data($teacher);

        $this->assertTrue(delete_user($other));

        $this->assertSame(array_map('count', $ids), self::rows_left($ids));
    }
}
