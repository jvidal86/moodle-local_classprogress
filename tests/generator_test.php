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
}
