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

namespace local_classprogress;

/**
 * Tests for the plugin data generator: every method stores a valid row with defaults and honours overrides.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_classprogress_generator
 */
final class generator_test extends \advanced_testcase {
    /**
     * The plugin generator.
     *
     * @return \local_classprogress_generator The generator.
     */
    private function generator(): \local_classprogress_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_classprogress');
    }

    /**
     * Checks that a record returned by the generator is the row stored in the table.
     *
     * @param string $table Table name without the prefix.
     * @param \stdClass $record The returned record.
     */
    private function assert_stored(string $table, \stdClass $record): void {
        global $DB;

        $this->assertNotEmpty($record->id);
        $this->assertEquals($DB->get_record('local_classprogress_' . $table, ['id' => $record->id]), $record);
    }

    /**
     * A school year belongs to the current user and runs from 2026-09-14 to 2027-06-21 by default.
     *
     * @covers ::create_schoolyear
     */
    public function test_create_schoolyear_defaults(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $year = $this->generator()->create_schoolyear();

        $this->assert_stored('schoolyear', $year);
        $this->assertEquals($user->id, $year->userid);
        $this->assertNotSame('', $year->name);
        $this->assertSame('2026-09-14', $year->startdate);
        $this->assertSame('2027-06-21', $year->enddate);
        $this->assertGreaterThan(0, (int) $year->timecreated);
        $this->assertGreaterThan(0, (int) $year->timemodified);
    }

    /**
     * With nobody logged in, a school year gets a new user as its owner.
     *
     * @covers ::create_schoolyear
     */
    public function test_create_schoolyear_without_a_user_creates_the_owner(): void {
        global $DB;
        $this->resetAfterTest();

        $year = $this->generator()->create_schoolyear();

        $this->assertTrue($DB->record_exists('user', ['id' => $year->userid, 'deleted' => 0]));
    }

    /**
     * Every field of a school year can be given.
     *
     * @covers ::create_schoolyear
     */
    public function test_create_schoolyear_overrides(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $year = $this->generator()->create_schoolyear(['userid' => $user->id, 'name' => 'Curs 2027-28',
            'startdate' => '2027-09-13', 'enddate' => '2028-06-20', 'timecreated' => 100, 'timemodified' => 200]);

        $this->assert_stored('schoolyear', $year);
        $this->assertEquals($user->id, $year->userid);
        $this->assertSame('Curs 2027-28', $year->name);
        $this->assertSame('2027-09-13', $year->startdate);
        $this->assertSame('2028-06-20', $year->enddate);
        $this->assertEquals(100, $year->timecreated);
        $this->assertEquals(200, $year->timemodified);
    }

    /**
     * A holiday is a single day in a new school year by default; its end defaults to its start.
     *
     * @covers ::create_holiday
     */
    public function test_create_holiday(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->generator();

        $holiday = $generator->create_holiday();
        $year = $generator->create_schoolyear();
        $period = $generator->create_holiday(['schoolyearid' => $year->id, 'startdate' => '2026-12-23',
            'enddate' => '2027-01-06', 'label' => 'Nadal']);
        $single = $generator->create_holiday(['schoolyearid' => $year->id, 'startdate' => '2027-05-01']);

        $this->assert_stored('holiday', $holiday);
        $this->assertTrue($DB->record_exists('local_classprogress_schoolyear', ['id' => $holiday->schoolyearid]));
        $this->assertSame($holiday->startdate, $holiday->enddate);
        $this->assertNotSame('', $holiday->label);
        $this->assert_stored('holiday', $period);
        $this->assertEquals($year->id, $period->schoolyearid);
        $this->assertSame(['2026-12-23', '2027-01-06', 'Nadal'], [$period->startdate, $period->enddate, $period->label]);
        $this->assertSame(['2027-05-01', '2027-05-01'], [$single->startdate, $single->enddate]);
    }

    /**
     * An evaluation period lies inside a new school year by default and takes overrides.
     *
     * @covers ::create_evaluation
     */
    public function test_create_evaluation(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->generator();

        $default = $generator->create_evaluation();
        $year = $generator->create_schoolyear();
        $second = $generator->create_evaluation(['schoolyearid' => $year->id, 'name' => '2a avaluació',
            'startdate' => '2027-01-07', 'enddate' => '2027-03-19']);

        $this->assert_stored('evaluation', $default);
        $parent = $DB->get_record('local_classprogress_schoolyear', ['id' => $default->schoolyearid], '*', MUST_EXIST);
        $this->assertNotSame('', $default->name);
        $this->assertGreaterThanOrEqual($parent->startdate, $default->startdate);
        $this->assertLessThanOrEqual($parent->enddate, $default->enddate);
        $this->assertLessThanOrEqual($default->enddate, $default->startdate);
        $this->assert_stored('evaluation', $second);
        $this->assertEquals($year->id, $second->schoolyearid);
        $this->assertSame(['2a avaluació', '2027-01-07', '2027-03-19'], [$second->name, $second->startdate, $second->enddate]);
    }

    /**
     * A plan is a draft owned by its school year's owner, spanning the school year, by default.
     *
     * @covers ::create_plan
     */
    public function test_create_plan_defaults(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plan = $this->generator()->create_plan();

        $this->assert_stored('plan', $plan);
        $year = $DB->get_record('local_classprogress_schoolyear', ['id' => $plan->schoolyearid], '*', MUST_EXIST);
        $this->assertEquals($user->id, $plan->userid);
        $this->assertEquals($user->id, $year->userid);
        $this->assertNull($plan->courseid);
        $this->assertNotSame('', $plan->modulecode);
        $this->assertNotSame('', $plan->moduletitle);
        $this->assertSame([$year->startdate, $year->enddate], [$plan->startdate, $plan->enddate]);
        $this->assertSame('draft', $plan->status);
        $this->assertNotEmpty($plan->sourcetext);
        $this->assertEquals(0, $plan->timeapproved);
    }

    /**
     * A plan for a given school year belongs to that year's owner, even when someone else is logged in.
     *
     * @covers ::create_plan
     */
    public function test_create_plan_takes_the_owner_from_its_school_year(): void {
        $this->resetAfterTest();
        $generator = $this->generator();
        $owner = $this->getDataGenerator()->create_user();
        $year = $generator->create_schoolyear(['userid' => $owner->id]);
        $this->setAdminUser();

        $plan = $generator->create_plan(['schoolyearid' => $year->id]);

        $this->assertEquals($owner->id, $plan->userid);
    }

    /**
     * Every field of a plan can be given; an approved plan gets an approval time unless one is given.
     *
     * @covers ::create_plan
     */
    public function test_create_plan_overrides(): void {
        $this->resetAfterTest();
        $generator = $this->generator();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $plan = $generator->create_plan(['userid' => $user->id, 'courseid' => $course->id, 'modulecode' => 'MP 0372',
            'moduletitle' => 'Gestió de bases de dades', 'startdate' => '2026-09-14', 'enddate' => '2027-05-28',
            'status' => 'approved', 'sourcetext' => "# MP 0372 · Gestió de bases de dades\n"]);
        $stamped = $generator->create_plan(['status' => 'approved', 'timeapproved' => 12345]);

        $this->assert_stored('plan', $plan);
        $this->assertEquals($user->id, $plan->userid);
        $this->assertEquals($course->id, $plan->courseid);
        $this->assertSame(['MP 0372', 'Gestió de bases de dades'], [$plan->modulecode, $plan->moduletitle]);
        $this->assertSame(['2026-09-14', '2027-05-28'], [$plan->startdate, $plan->enddate]);
        $this->assertSame('approved', $plan->status);
        $this->assertSame("# MP 0372 · Gestió de bases de dades\n", $plan->sourcetext);
        $this->assertGreaterThan(0, (int) $plan->timeapproved);
        $this->assertEquals(12345, $stamped->timeapproved);
    }

    /**
     * Slots of one plan take the next free weekday by default, Monday = 0, and take overrides.
     *
     * @covers ::create_slot
     */
    public function test_create_slot(): void {
        $this->resetAfterTest();
        $generator = $this->generator();
        $plan = $generator->create_plan();

        $first = $generator->create_slot(['planid' => $plan->id]);
        $second = $generator->create_slot(['planid' => $plan->id]);
        $friday = $generator->create_slot(['planid' => $plan->id, 'weekday' => 4, 'hours' => 1.5]);
        $other = $generator->create_slot();

        $this->assert_stored('slot', $first);
        $this->assertSame([0, 1, 4], [(int) $first->weekday, (int) $second->weekday, (int) $friday->weekday]);
        $this->assertGreaterThan(0, (float) $first->hours);
        $this->assertEquals(1.5, (float) $friday->hours);
        $this->assertNotEquals($plan->id, $other->planid);
        $this->assertEquals(0, $other->weekday);
    }

    /**
     * Plan items are numbered in creation order within their plan, and take overrides.
     *
     * @covers ::create_planitem
     */
    public function test_create_planitem(): void {
        $this->resetAfterTest();
        $generator = $this->generator();
        $plan = $generator->create_plan();

        $first = $generator->create_planitem(['planid' => $plan->id]);
        $second = $generator->create_planitem(['planid' => $plan->id, 'sessiondate' => '2026-09-18',
            'block' => 'RA2 · Escriu i prova codi', 'title' => 'Entorn de treball', 'content' => "- Primer programa\n",
            'hours' => null]);
        $other = $generator->create_planitem();

        $this->assert_stored('planitem', $first);
        $this->assertSame([1, 2], [(int) $first->sortorder, (int) $second->sortorder]);
        $this->assertSame('2026-09-14', $first->sessiondate);
        $this->assertNotSame('', $first->block);
        $this->assertNotSame('', $first->content);
        $this->assertGreaterThan(0, (float) $first->hours);
        $this->assert_stored('planitem', $second);
        $this->assertSame('2026-09-18', $second->sessiondate);
        $this->assertSame('RA2 · Escriu i prova codi', $second->block);
        $this->assertSame('Entorn de treball', $second->title);
        $this->assertSame("- Primer programa\n", $second->content);
        $this->assertNull($second->hours);
        $this->assertNotEquals($plan->id, $other->planid);
        $this->assertEquals(1, $other->sortorder);
    }

    /**
     * An entry is unplanned by default; for a plan item it takes the item's plan and date, written on that date.
     *
     * @covers ::create_entry
     */
    public function test_create_entry(): void {
        $this->resetAfterTest();
        $generator = $this->generator();
        $item = $generator->create_planitem(['sessiondate' => '2026-09-21']);

        $unplanned = $generator->create_entry();
        $another = $generator->create_entry(['planid' => $unplanned->planid]);
        $planned = $generator->create_entry(['planitemid' => $item->id]);

        $this->assert_stored('entry', $unplanned);
        $this->assertNull($unplanned->planitemid);
        $this->assertNull($another->planitemid);
        $this->assertNotSame('', $unplanned->status);
        $this->assertEquals(FORMAT_MARKDOWN, $unplanned->contentformat);
        $this->assertSame($unplanned->sessiondate, $unplanned->writtenon);
        $this->assertEquals(0, $unplanned->carry);
        $this->assert_stored('entry', $planned);
        $this->assertEquals($item->planid, $planned->planid);
        $this->assertSame(['2026-09-21', '2026-09-21'], [$planned->sessiondate, $planned->writtenon]);
    }

    /**
     * Every field of an entry can be given.
     *
     * @covers ::create_entry
     */
    public function test_create_entry_overrides(): void {
        $this->resetAfterTest();
        $generator = $this->generator();
        $item = $generator->create_planitem();

        $given = ['planitemid' => $item->id, 'sessiondate' => '2026-09-16', 'status' => 'partial',
            'content' => 'Only the first part', 'contentformat' => FORMAT_PLAIN, 'reason' => 'strike',
            'reasonnote' => 'Vaga', 'measure' => 'Finish next day', 'carry' => 1, 'writtenon' => '2026-09-20',
            'timecreated' => 100, 'timemodified' => 200];

        $entry = $generator->create_entry($given);

        $this->assert_stored('entry', $entry);
        $this->assertEquals($item->planid, $entry->planid);
        foreach ($given as $field => $value) {
            $this->assertEquals($value, $entry->$field, $field);
        }
    }

    /**
     * A history row snapshots its entry as JSON and is written by the plan's owner by default.
     *
     * @covers ::create_entry_hist
     */
    public function test_create_entry_hist(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->generator();
        $entry = $generator->create_entry();
        $plan = $DB->get_record('local_classprogress_plan', ['id' => $entry->planid], '*', MUST_EXIST);
        $admin = get_admin();

        $default = $generator->create_entry_hist(['entryid' => $entry->id]);
        $new = $generator->create_entry_hist();
        $loggedinas = $generator->create_entry_hist(['entryid' => $entry->id, 'action' => 'update',
            'realuserid' => $admin->id, 'snapshot' => '{"status":"done"}']);

        $this->assert_stored('entry_hist', $default);
        $this->assertEquals($entry->planid, $default->planid);
        $this->assertEquals($plan->userid, $default->userid);
        $this->assertNull($default->realuserid);
        $this->assertSame('create', $default->action);
        $this->assertEquals((array) $entry, json_decode($default->snapshot, true));
        $this->assertTrue($DB->record_exists('local_classprogress_entry', ['id' => $new->entryid]));
        $this->assertSame(['update', '{"status":"done"}'], [$loggedinas->action, $loggedinas->snapshot]);
        $this->assertEquals($admin->id, $loggedinas->realuserid);
    }

    /**
     * History survives its entry: a row for a deleted entry only needs the plan.
     *
     * @covers ::create_entry_hist
     */
    public function test_create_entry_hist_for_a_deleted_entry(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->generator();
        $entry = $generator->create_entry();
        $DB->delete_records('local_classprogress_entry', ['id' => $entry->id]);

        $hist = $generator->create_entry_hist(['entryid' => $entry->id, 'planid' => $entry->planid, 'action' => 'delete']);

        $this->assert_stored('entry_hist', $hist);
        $this->assertSame('delete', $hist->action);
    }

    /**
     * An import records a pasted text for a new plan by default, and takes overrides.
     *
     * @covers ::create_import
     */
    public function test_create_import(): void {
        $this->resetAfterTest();
        $generator = $this->generator();
        $plan = $generator->create_plan();

        $default = $generator->create_import();
        $upload = $generator->create_import(['planid' => $plan->id, 'source' => 'upload', 'filename' => '0372.md',
            'contenthash' => sha1('x'), 'daycount' => 60]);

        $this->assert_stored('import', $default);
        $this->assertSame('paste', $default->source);
        $this->assertSame('', $default->filename);
        $this->assertSame(40, strlen($default->contenthash));
        $this->assert_stored('import', $upload);
        $this->assertSame(['upload', '0372.md', sha1('x')], [$upload->source, $upload->filename, $upload->contenthash]);
        $this->assertEquals([$plan->id, 60], [$upload->planid, $upload->daycount]);
    }
}
