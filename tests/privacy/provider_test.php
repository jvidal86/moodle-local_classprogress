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
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the privacy provider: metadata, export and the three ways of deleting a teacher's data.
 *
 * Every test sets up two teachers who each own a school year with a holiday and an evaluation, so each test also
 * checks that the other teacher's data is left alone. Plans, entries and history are mod_classprogress's.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\privacy\provider
 * @covers     \local_classprogress\local\data_eraser
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var string The plugin's component name. */
    private const COMPONENT = 'local_classprogress';

    /**
     * Creates a full set of rows owned by one teacher.
     *
     * @param \stdClass $user The owner.
     * @param string $name Short name used in the free text, so each owner's text can be told apart.
     * @return array Table name without prefix => ids of the rows created in that table.
     */
    private function create_teacher_data(\stdClass $user, string $name): array {
        $gen = $this->getDataGenerator()->get_plugin_generator(self::COMPONENT);

        $year = $gen->create_schoolyear(['userid' => $user->id, 'name' => "Year of {$name}"]);
        $holiday = $gen->create_holiday(['schoolyearid' => $year->id, 'startdate' => '2026-12-23',
            'enddate' => '2027-01-06', 'label' => "Christmas of {$name}"]);
        $evaluation = $gen->create_evaluation(['schoolyearid' => $year->id, 'name' => "First term of {$name}"]);

        return [
            'schoolyear' => [$year->id],
            'holiday' => [$holiday->id],
            'evaluation' => [$evaluation->id],
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
     * Asserts that none of the given rows exist any more.
     *
     * @param array $ids Rows as returned by create_teacher_data().
     */
    private function assert_all_deleted(array $ids): void {
        $left = self::rows_left($ids);
        $this->assertSame(array_fill_keys(array_keys($left), 0), $left);
    }

    /**
     * Asserts that all of the given rows still exist.
     *
     * @param array $ids Rows as returned by create_teacher_data().
     */
    private function assert_all_kept(array $ids): void {
        $this->assertSame(array_map('count', $ids), self::rows_left($ids));
    }

    /**
     * Lists the users the provider finds in a context.
     *
     * @param \context $context The context to look in.
     * @return int[] User ids.
     */
    private static function users_in(\context $context): array {
        $userlist = new userlist($context, self::COMPONENT);
        provider::get_users_in_context($userlist);
        return $userlist->get_userids();
    }

    /**
     * Every table and every field of db/install.xml, except the id, is described with an existing string.
     *
     * @group REQ-PRV-001
     */
    public function test_metadata_describes_every_table_and_field(): void {
        global $DB;

        $DB->get_manager(); // Loads the XMLDB classes.
        $file = new \xmldb_file(\core_component::get_component_directory(self::COMPONENT) . '/db/install.xml');
        $this->assertTrue($file->loadXMLStructure());
        $expected = [];
        foreach ($file->getStructure()->getTables() as $table) {
            $fields = array_map(fn(\xmldb_field $field): string => $field->getName(), $table->getFields());
            $expected[$table->getName()] = array_values(array_diff($fields, ['id']));
            sort($expected[$table->getName()]);
        }

        $described = [];
        foreach (provider::get_metadata(new collection(self::COMPONENT))->get_collection() as $item) {
            $this->assertInstanceOf(\core_privacy\local\metadata\types\database_table::class, $item);
            $this->assertTrue(get_string_manager()->string_exists($item->get_summary(), self::COMPONENT), $item->get_name());
            foreach ($item->get_privacy_fields() as $field => $stringid) {
                $this->assertSame("privacy:metadata:{$item->get_name()}:{$field}", $stringid);
                $this->assertTrue(get_string_manager()->string_exists($stringid, self::COMPONENT), $stringid);
            }
            $fields = array_keys($item->get_privacy_fields());
            sort($fields);
            $described[$item->get_name()] = $fields;
        }
        ksort($expected);
        ksort($described);

        $this->assertCount(3, $expected);
        $this->assertSame($expected, $described);
    }

    /**
     * A teacher's data lives in their own user context, and only there; users without data have none.
     *
     * @group REQ-PRV-002
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator(self::COMPONENT);
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $yearonly = $this->getDataGenerator()->create_user();
        $nobody = $this->getDataGenerator()->create_user();
        $this->create_teacher_data($teacher1, 'T1');
        $this->create_teacher_data($teacher2, 'T2');
        $gen->create_schoolyear(['userid' => $yearonly->id]);

        $contextids = fn(int $userid): array => array_map('intval', provider::get_contexts_for_userid($userid)->get_contextids());
        $this->assertSame([(int) \context_user::instance($teacher1->id)->id], $contextids($teacher1->id));
        $this->assertSame([(int) \context_user::instance($yearonly->id)->id], $contextids($yearonly->id));
        $this->assertSame([], $contextids($nobody->id));
    }

    /**
     * The only user found in a teacher's user context is that teacher; other kinds of context hold nobody.
     *
     * @group REQ-PRV-002
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $nobody = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->create_teacher_data($teacher1, 'T1');
        $this->create_teacher_data($teacher2, 'T2');

        $this->assertSame([(int) $teacher1->id], self::users_in(\context_user::instance($teacher1->id)));
        $this->assertSame([(int) $teacher2->id], self::users_in(\context_user::instance($teacher2->id)));
        $this->assertSame([], self::users_in(\context_user::instance($nobody->id)));
        $this->assertSame([], self::users_in(\context_user::instance(get_admin()->id)));
        $this->assertSame([], self::users_in(\context_system::instance()));
        $this->assertSame([], self::users_in(\context_course::instance($course->id)));
    }

    /**
     * The export holds the teacher's school year with its holidays and evaluations.
     *
     * @group REQ-PRV-002
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $ids = $this->create_teacher_data($teacher1, 'T1');
        $other = $this->create_teacher_data($teacher2, 'T2');
        $context = \context_user::instance($teacher1->id);
        $root = get_string('pluginname', self::COMPONENT);
        $yearpath = [$root, get_string('privacy:path:schoolyear', self::COMPONENT, $ids['schoolyear'][0])];

        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data());
        $this->export_context_data_for_user($teacher1->id, $context, self::COMPONENT);
        $this->assertTrue($writer->has_any_data());

        $year = $writer->get_data($yearpath);
        $this->assertSame('Year of T1', $year->name);
        $this->assertSame('2026-09-14', $year->startdate);
        $this->assertSame('2027-06-21', $year->enddate);
        $this->assertCount(1, $year->holidays);
        $this->assertSame('Christmas of T1', $year->holidays[0]->label);
        $this->assertSame('2026-12-23', $year->holidays[0]->startdate);
        $this->assertSame('2027-01-06', $year->holidays[0]->enddate);
        $this->assertCount(1, $year->evaluations);
        $this->assertSame('First term of T1', $year->evaluations[0]->name);

        // Nothing of the other teacher is exported, here or in their own context.
        $otherpath = [$root, get_string('privacy:path:schoolyear', self::COMPONENT, $other['schoolyear'][0])];
        $this->assertSame([], $writer->get_data($otherpath));
        $this->assertFalse(writer::with_context(\context_user::instance($teacher2->id))->has_any_data());
    }

    /**
     * Exporting in a context the user does not own, or in a context of another kind, writes nothing.
     *
     * @group REQ-PRV-002
     */
    public function test_export_ignores_other_contexts(): void {
        $this->resetAfterTest();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $this->create_teacher_data($teacher1, 'T1');
        $this->create_teacher_data($teacher2, 'T2');
        $context2 = \context_user::instance($teacher2->id);

        $this->export_context_data_for_user($teacher1->id, $context2, self::COMPONENT);
        $this->export_context_data_for_user($teacher1->id, \context_system::instance(), self::COMPONENT);

        $this->assertFalse(writer::with_context($context2)->has_any_data());
        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }

    /**
     * Deleting all data in a teacher's user context removes every row of that teacher and nothing else; other kinds
     * of context delete nothing.
     *
     * @group REQ-PRV-003
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $this->resetAfterTest();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ids1 = $this->create_teacher_data($teacher1, 'T1');
        $ids2 = $this->create_teacher_data($teacher2, 'T2');

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));
        $this->assert_all_kept($ids1);

        provider::delete_data_for_all_users_in_context(\context_user::instance($teacher1->id));

        $this->assert_all_deleted($ids1);
        $this->assert_all_kept($ids2);
    }

    /**
     * Deleting a user's data in their approved contexts removes all their rows, and only theirs.
     *
     * @group REQ-PRV-003
     */
    public function test_delete_data_for_user(): void {
        $this->resetAfterTest();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $ids1 = $this->create_teacher_data($teacher1, 'T1');
        $ids2 = $this->create_teacher_data($teacher2, 'T2');

        $contextids = [\context_user::instance($teacher1->id)->id];
        provider::delete_data_for_user(new approved_contextlist($teacher1, self::COMPONENT, $contextids));

        $this->assert_all_deleted($ids1);
        $this->assert_all_kept($ids2);
    }

    /**
     * A context list that does not hold the user's own context deletes nothing, even if it holds another teacher's.
     *
     * @group REQ-PRV-003
     */
    public function test_delete_data_for_user_ignores_other_contexts(): void {
        $this->resetAfterTest();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $ids1 = $this->create_teacher_data($teacher1, 'T1');
        $ids2 = $this->create_teacher_data($teacher2, 'T2');

        $contextids = [\context_user::instance($teacher2->id)->id, \context_system::instance()->id];
        provider::delete_data_for_user(new approved_contextlist($teacher1, self::COMPONENT, $contextids));

        $this->assert_all_kept($ids1);
        $this->assert_all_kept($ids2);
    }

    /**
     * Deleting a list of users in a teacher's context removes the teacher's rows only when the teacher is in the
     * list; other contexts delete nothing.
     *
     * @group REQ-PRV-003
     */
    public function test_delete_data_for_users(): void {
        $this->resetAfterTest();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $ids1 = $this->create_teacher_data($teacher1, 'T1');
        $ids2 = $this->create_teacher_data($teacher2, 'T2');
        $context1 = \context_user::instance($teacher1->id);
        $both = [$teacher1->id, $teacher2->id];

        provider::delete_data_for_users(new approved_userlist($context1, self::COMPONENT, [$teacher2->id]));
        provider::delete_data_for_users(new approved_userlist(\context_system::instance(), self::COMPONENT, $both));
        $this->assert_all_kept($ids1);

        provider::delete_data_for_users(new approved_userlist($context1, self::COMPONENT, $both));

        $this->assert_all_deleted($ids1);
        $this->assert_all_kept($ids2);
        $this->assertSame([], self::users_in($context1));
        $this->assertSame([(int) $teacher2->id], self::users_in(\context_user::instance($teacher2->id)));
    }
}
