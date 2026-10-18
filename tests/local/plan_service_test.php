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
 * Tests for the plan service: draft plan shells (module, dates, course, weekly timetable) and their calendar.
 *
 * Timetables are weekday => hours with Monday = 0 and Sunday = 6. 2026-09-14 and 2026-10-12 are Mondays.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\local\plan_service
 */
final class plan_service_test extends \advanced_testcase {
    /**
     * The plugin generator.
     *
     * @return \local_classprogress_generator The generator.
     */
    private function generator(): \local_classprogress_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_classprogress');
    }

    /**
     * A new user with a teacher role in a course.
     *
     * @param \stdClass|null $course The course; a new one when null.
     * @return \stdClass The user.
     */
    private function teacher(?\stdClass $course = null): \stdClass {
        $course ??= $this->getDataGenerator()->create_course();
        return $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
    }

    /**
     * A school year of the user, created through the school year service.
     *
     * @param int $userid Owner.
     * @param string $holidays Holiday text.
     * @return int School year id.
     */
    private static function year(int $userid, string $holidays = ''): int {
        return schoolyear_service::create($userid, (object) [
            'name' => 'Curs 2026-2027',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
            'holidays' => $holidays,
        ]);
    }

    /**
     * Plan data as a form would send it: MP 0372, Monday and Friday 2 hours, for the whole school year.
     *
     * @param int $yearid School year id.
     * @param array $fields Fields to replace.
     * @return \stdClass The data.
     */
    private static function plandata(int $yearid, array $fields = []): \stdClass {
        return (object) array_merge([
            'schoolyearid' => $yearid,
            'courseid' => null,
            'modulecode' => 'MP 0372',
            'moduletitle' => 'Gestió de bases de dades',
            'startdate' => '2026-09-14',
            'enddate' => '2027-05-24',
            'timetable' => [0 => 2, 4 => 2],
        ], $fields);
    }

    /**
     * Creating a plan stores a draft shell owned by the user, with one slot per weekday that has hours.
     */
    public function test_create_stores_a_draft_with_its_timetable(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $yearid = self::year($user->id);

        $id = plan_service::create($user->id, self::plandata($yearid, ['timetable' => [0 => 2, 2 => 0, 4 => 1.5]]));

        $plan = plan_service::get($user->id, $id);
        $this->assertSame((string) $user->id, (string) $plan->userid);
        $this->assertSame((string) $yearid, (string) $plan->schoolyearid);
        $this->assertNull($plan->courseid);
        $this->assertSame('MP 0372', $plan->modulecode);
        $this->assertSame('Gestió de bases de dades', $plan->moduletitle);
        $this->assertSame('2026-09-14', $plan->startdate);
        $this->assertSame('2027-05-24', $plan->enddate);
        $this->assertSame('draft', $plan->status);
        $this->assertNull($plan->sourcetext);
        $this->assertSame('0', (string) $plan->timeapproved);
        $this->assertSame([0 => 2.0, 4 => 1.5], plan_service::timetable($user->id, $id));
    }

    /**
     * Updating changes the shell and replaces the timetable; a weekday set to zero hours loses its slot.
     */
    public function test_update_replaces_the_shell_and_the_timetable(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->teacher();
        $yearid = self::year($user->id);
        $otheryearid = self::year($user->id);
        $id = plan_service::create($user->id, self::plandata($yearid));

        plan_service::update($user->id, $id, self::plandata($otheryearid, [
            'modulecode' => '',
            'moduletitle' => '  Programació ',
            'startdate' => '2026-10-01',
            'enddate' => '2027-05-31',
            'timetable' => [0 => 0, 1 => 1.25, 3 => 2],
        ]));

        $plan = plan_service::get($user->id, $id);
        $this->assertSame((string) $otheryearid, (string) $plan->schoolyearid);
        $this->assertSame('', $plan->modulecode);
        $this->assertSame('Programació', $plan->moduletitle);
        $this->assertSame('2026-10-01', $plan->startdate);
        $this->assertSame('2027-05-31', $plan->enddate);
        $this->assertSame([1 => 1.25, 3 => 2.0], plan_service::timetable($user->id, $id));
        $this->assertSame(2, $DB->count_records('local_classprogress_slot', ['planid' => $id]));
    }

    /**
     * Data without a timetable leaves the stored timetable as it is.
     */
    public function test_update_without_a_timetable_keeps_it(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $id = plan_service::create($user->id, self::plandata(self::year($user->id)));
        $data = self::plandata(self::year($user->id), ['moduletitle' => 'Renamed']);
        unset($data->timetable);

        plan_service::update($user->id, $id, $data);

        $this->assertSame('Renamed', plan_service::get($user->id, $id)->moduletitle);
        $this->assertSame([0 => 2.0, 4 => 2.0], plan_service::timetable($user->id, $id));
    }

    /**
     * The first and last class days may be left empty; the calendar is then empty with a code, not an error.
     *
     * @group REQ-CAL-002
     */
    public function test_dates_may_be_empty_and_the_calendar_says_so(): void {
        $this->resetAfterTest();
        $user = $this->teacher();

        $id = plan_service::create($user->id, self::plandata(self::year($user->id), ['startdate' => '', 'enddate' => null]));

        $plan = plan_service::get($user->id, $id);
        $this->assertNull($plan->startdate);
        $this->assertNull($plan->enddate);
        $result = plan_service::calendar($user->id, $id);
        $this->assertSame(calendar::MISSING_DATES, $result->code);
        $this->assertSame([], $result->sessions);
    }

    /**
     * Invalid plan data and the error each field gets.
     *
     * @return array Case name => [fields to replace, expected errors].
     */
    public static function invalid_data_cases(): array {
        return [
            'empty title' => [['moduletitle' => ' '], ['moduletitle' => 'error_moduletitle']],
            'title too long' => [['moduletitle' => str_repeat('à', 256)], ['moduletitle' => 'error_moduletitle']],
            'code too long' => [['modulecode' => str_repeat('M', 101)], ['modulecode' => 'error_modulecode']],
            'code on two lines' => [['modulecode' => "MP\n0372"], ['modulecode' => 'error_modulecode']],
            'title with a control character' => [['moduletitle' => "Bases\x07 de dades"], ['moduletitle' => 'error_moduletitle']],
            'date not ISO' => [['startdate' => '14/09/2026'], ['startdate' => 'error_baddate']],
            'no such date' => [['enddate' => '2027-02-29'], ['enddate' => 'error_baddate']],
            'end before start' => [['enddate' => '2026-09-13'], ['enddate' => 'error_endbeforestart']],
            'hours above 24' => [['timetable' => [0 => 24.5]], ['timetable-0' => 'error_hours']],
            'negative hours' => [['timetable' => [3 => -1]], ['timetable-3' => 'error_hours']],
            'hours not a number' => [['timetable' => [1 => 'two']], ['timetable-1' => 'error_hours']],
            'hours not finite' => [['timetable' => [1 => NAN]], ['timetable-1' => 'error_hours']],
            'weekday 7' => [['timetable' => [7 => 1]], ['timetable' => 'error_weekday']],
            'weekday -1' => [['timetable' => [-1 => 1]], ['timetable' => 'error_weekday']],
            'weekday not a number' => [['timetable' => ['mon' => 1]], ['timetable' => 'error_weekday']],
        ];
    }

    /**
     * Validation names the field and the error; the service refuses to store such data.
     *
     * @dataProvider invalid_data_cases
     * @param array $fields Fields to replace in valid data.
     * @param array $expected Expected errors, field => lang string id.
     */
    public function test_invalid_data_is_reported_and_refused(array $fields, array $expected): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->teacher();
        $data = self::plandata(self::year($user->id), $fields);

        $this->assertSame($expected, plan_service::validate($user->id, $data));

        try {
            plan_service::create($user->id, $data);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame(reset($expected), $e->errorcode);
            $this->assertSame('local_classprogress', $e->module);
        }
        $this->assertSame(0, $DB->count_records('local_classprogress_plan'));
        $this->assertSame(0, $DB->count_records('local_classprogress_slot'));
    }

    /**
     * Hours from 0 to 24 are accepted, as numbers or numeric text, and kept to two decimals.
     */
    public function test_hours_from_zero_to_twentyfour_are_stored_with_two_decimals(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $data = self::plandata(self::year($user->id), ['timetable' => [0 => 0, 1 => '1.5', 2 => 24, 5 => 0.333, 6 => 1]]);

        $this->assertSame([], plan_service::validate($user->id, $data));
        $id = plan_service::create($user->id, $data);

        $this->assertSame([1 => 1.5, 2 => 24.0, 5 => 0.33, 6 => 1.0], plan_service::timetable($user->id, $id));
    }

    /**
     * A plan may be linked to a course where the user teaches, and to no other course.
     */
    public function test_a_plan_links_only_to_a_course_where_the_user_teaches(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->teacher($course);
        $student = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $student->id, 'student');
        $other = $this->getDataGenerator()->create_course();
        $yearid = self::year($user->id);

        $id = plan_service::create($user->id, self::plandata($yearid, ['courseid' => $course->id]));

        $this->assertSame((string) $course->id, (string) plan_service::get($user->id, $id)->courseid);
        foreach ([$student->id, $other->id, SITEID, $other->id + 1000] as $courseid) {
            $errors = plan_service::validate($user->id, self::plandata($yearid, ['courseid' => $courseid]));
            $this->assertSame(['courseid' => 'error_course'], $errors);
        }
        plan_service::update($user->id, $id, self::plandata($yearid, ['courseid' => 0]));
        $this->assertNull(plan_service::get($user->id, $id)->courseid);
        $this->assertSame([(int) $course->id], array_keys(plan_service::linkable_courses($user->id)));
    }

    /**
     * Deleting a plan removes every row that hangs from it (timetable, items, import log, entries and their history,
     * including the history of an entry already deleted), and nothing of other plans or of the school year.
     */
    public function test_delete_removes_the_plan_with_all_its_rows(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->teacher();
        $yearid = self::year($user->id, '2026-10-12 Festa');
        $id = plan_service::create($user->id, self::plandata($yearid));
        $keep = plan_service::create($user->id, self::plandata($yearid));
        $children = ['slot', 'planitem', 'import', 'entry', 'entry_hist'];
        foreach ([$id, $keep] as $planid) {
            $item = $this->generator()->create_planitem(['planid' => $planid]);
            $this->generator()->create_import(['planid' => $planid]);
            $planned = $this->generator()->create_entry(['planitemid' => $item->id]);
            $unplanned = $this->generator()->create_entry(['planid' => $planid]);
            $this->generator()->create_entry_hist(['entryid' => $planned->id]);
            $this->generator()->create_entry_hist(['entryid' => $unplanned->id]);
            $this->generator()->create_entry_hist(['entryid' => $unplanned->id + 1000, 'planid' => $planid, 'action' => 'delete']);
        }
        $kept = [];
        foreach ($children as $table) {
            $kept[$table] = $DB->get_records('local_classprogress_' . $table, ['planid' => $keep]);
            $this->assertNotEmpty($DB->get_records('local_classprogress_' . $table, ['planid' => $id]), $table);
        }

        plan_service::delete($user->id, $id);

        $this->assertFalse($DB->record_exists('local_classprogress_plan', ['id' => $id]));
        foreach ($children as $table) {
            $this->assertSame(0, $DB->count_records('local_classprogress_' . $table, ['planid' => $id]), $table);
            $this->assertEquals($kept[$table], $DB->get_records('local_classprogress_' . $table, ['planid' => $keep]), $table);
        }
        $this->assertTrue($DB->record_exists('local_classprogress_plan', ['id' => $keep]));
        $this->assertTrue($DB->record_exists('local_classprogress_schoolyear', ['id' => $yearid]));
        $this->assertCount(1, schoolyear_service::holidays($user->id, $yearid));
    }

    /**
     * Each real plan's calendar, from its first to its last day with the real school holidays, has exactly the
     * plan's number of days and hours: every day of those plans is a class day.
     *
     * @group REQ-CAL-001
     * @group REQ-CAL-002
     * @group REQ-HOL-003
     */
    public function test_calendar_of_the_real_plans_matches_their_days_and_hours(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $expected = json_decode(file_get_contents(__DIR__ . '/../fixtures/expected.json'), true);
        $yearid = self::year($user->id, file_get_contents(__DIR__ . '/../fixtures/holidays-centre-2026-27.txt'));

        foreach ($expected['plans'] as $name => $real) {
            $id = plan_service::create($user->id, self::plandata($yearid, [
                'modulecode' => $real['code'],
                'moduletitle' => $real['module'],
                'startdate' => $real['first'],
                'enddate' => $real['last'],
                'timetable' => $real['timetable'],
            ]));

            $result = plan_service::calendar($user->id, $id);

            $this->assertNull($result->code, $name);
            $this->assertCount($real['days'], $result->sessions, $name);
            $this->assertEquals($real['hours'], $result->totalhours, $name);
            $this->assertSame($real['first'], $result->sessions[0]->date, $name);
            $this->assertSame($real['last'], $result->sessions[$real['days'] - 1]->date, $name);
        }
    }

    /**
     * The service's calendar is the calendar class's result for the plan's dates, timetable and school year holidays,
     * with sessions lost to each holiday under the holiday's id.
     *
     * @group REQ-CAL-001
     * @group REQ-CAL-002
     * @group REQ-CAL-003
     */
    public function test_calendar_is_the_calendar_class_result_for_the_stored_plan(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $yearid = self::year($user->id, "2026-10-12 Festa\n2026-12-22 to 2027-01-07 Nadal\n2027-02-02 Festa local");
        $id = plan_service::create($user->id, self::plandata($yearid, ['timetable' => [0 => 2, 1 => 1.5, 4 => 2]]));
        $holidays = schoolyear_service::holidays($user->id, $yearid);

        $result = plan_service::calendar($user->id, $id);

        $this->assertEquals(calendar::compute('2026-09-14', '2027-05-24', [0 => 2.0, 1 => 1.5, 4 => 2.0], $holidays), $result);
        $ids = array_flip(array_map(fn(holiday_entry $h): string => $h->label, $holidays));
        $this->assertSame([$ids['Festa'] => 1, $ids['Nadal'] => 7, $ids['Festa local'] => 1], $result->lost);
    }

    /**
     * The calendar on screen shows the calendar class's figures as they are: sessions, hours, per month, per holiday.
     *
     * @covers \local_classprogress\output\calendar_view
     * @group REQ-CAL-001
     * @group REQ-CAL-002
     * @group REQ-CAL-003
     */
    public function test_calendar_view_shows_the_calendar_figures(): void {
        global $PAGE;

        $this->resetAfterTest();
        $user = $this->teacher();
        $this->setUser($user);
        $yearid = self::year($user->id, "2026-10-12 Festa\n2026-12-22 to 2027-01-07 Nadal\n2027-07-01 Estiu");
        $plan = plan_service::get($user->id, plan_service::create($user->id, self::plandata($yearid)));
        $result = plan_service::calendar($user->id, $plan->id);

        $view = \local_classprogress\output\calendar_view::for_plan($user->id, $plan);
        $context = $view->export_for_template($PAGE->get_renderer('core'));

        $this->assertNull($context->message);
        $this->assertSame('68 sessions, 136 hours of class', $context->summary);
        $this->assertCount(68, $result->sessions);
        $this->assertEquals(136, $result->totalhours);
        $this->assertSame(array_values($result->sessionspermonth), array_column($context->months, 'sessions'));
        $this->assertSame('September 2026', $context->months[0]['month']);
        $this->assertSame([1, 4, 0], array_column($context->holidays, 'lost'));
        $this->assertSame(array_values($result->lost), array_column($context->holidays, 'lost'));
        $this->assertSame(['Festa', 'Nadal', 'Estiu'], array_column($context->holidays, 'label'));
        $this->assertSame('2026-12-22 to 2027-01-07', $context->holidays[1]['dates']);
        $dates = array_map(fn(calendar_session $s): string => $s->date, $result->sessions);
        $this->assertSame($dates, array_column($context->sessions, 'date'));
        $this->assertSame(['Monday', '2'], [$context->sessions[0]['weekday'], $context->sessions[0]['hours']]);

        plan_service::update($user->id, $plan->id, self::plandata($yearid, ['startdate' => '']));
        $view = \local_classprogress\output\calendar_view::for_plan($user->id, plan_service::get($user->id, $plan->id));
        $context = $view->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame(get_string('calendar_missing_dates', 'local_classprogress'), $context->message);
    }

    /**
     * The holidays of a school year remove sessions from every plan of that year, and from no plan of another year or
     * of another teacher; editing them changes the calendar of every plan of the year.
     *
     * @group REQ-HOL-003
     */
    public function test_holidays_apply_to_every_plan_of_their_school_year(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $other = $this->teacher();
        $yearid = self::year($user->id, '2026-10-12 Festa');
        $secondyearid = self::year($user->id, '2026-10-19 Other festa');
        $otheryearid = self::year($other->id, '2026-10-19 Other festa');
        $range = ['startdate' => '2026-10-05', 'enddate' => '2026-10-26'];
        $mondays = plan_service::create($user->id, self::plandata($yearid, $range + ['timetable' => [0 => 2]]));
        $alsomondays = plan_service::create($user->id, self::plandata($yearid, $range + ['timetable' => [0 => 1, 4 => 1]]));
        $secondyear = plan_service::create($user->id, self::plandata($secondyearid, $range + ['timetable' => [0 => 2]]));
        $othersplan = plan_service::create($other->id, self::plandata($otheryearid, $range + ['timetable' => [0 => 2]]));
        $dates = fn(calendar_result $r): array => array_map(fn(calendar_session $s): string => $s->date, $r->sessions);

        $this->assertSame(['2026-10-05', '2026-10-19', '2026-10-26'], $dates(plan_service::calendar($user->id, $mondays)));
        $sessions = $dates(plan_service::calendar($user->id, $alsomondays));
        $this->assertSame(['2026-10-05', '2026-10-09', '2026-10-16', '2026-10-19', '2026-10-23', '2026-10-26'], $sessions);
        $this->assertSame(['2026-10-05', '2026-10-12', '2026-10-26'], $dates(plan_service::calendar($user->id, $secondyear)));
        $this->assertSame(['2026-10-05', '2026-10-12', '2026-10-26'], $dates(plan_service::calendar($other->id, $othersplan)));

        schoolyear_service::update($user->id, $yearid, (object) [
            'name' => 'Curs 2026-2027', 'startdate' => '2026-09-14', 'enddate' => '2027-06-21', 'holidays' => '2026-10-26 Pont',
        ]);

        $this->assertSame(['2026-10-05', '2026-10-12', '2026-10-19'], $dates(plan_service::calendar($user->id, $mondays)));
        $sessions = $dates(plan_service::calendar($user->id, $alsomondays));
        $this->assertSame(['2026-10-05', '2026-10-09', '2026-10-12', '2026-10-16', '2026-10-19', '2026-10-23'], $sessions);
    }

    /**
     * A stored plan whose last day is before its first gives an empty calendar with a code, not an error.
     *
     * @group REQ-CAL-002
     */
    public function test_calendar_of_reversed_stored_dates_is_empty_with_a_code(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $plan = $this->generator()->create_plan([
            'userid' => $user->id, 'schoolyearid' => self::year($user->id), 'startdate' => '2027-01-10', 'enddate' => '2027-01-01',
        ]);
        $this->generator()->create_slot(['planid' => $plan->id, 'weekday' => 0, 'hours' => 2]);

        $result = plan_service::calendar($user->id, $plan->id);

        $this->assertSame(calendar::LAST_BEFORE_FIRST, $result->code);
        $this->assertSame([], $result->sessions);
        $this->assertSame(0.0, $result->totalhours);
    }

    /**
     * A stored plan whose dates the forms no longer accept gives an empty calendar with a code, at once.
     *
     * @group REQ-CAL-004
     */
    public function test_calendar_of_stored_dates_outside_the_limits_is_empty_with_a_code(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $plan = $this->generator()->create_plan([
            'userid' => $user->id, 'schoolyearid' => self::year($user->id), 'startdate' => '9999-12-31', 'enddate' => '9999-12-31',
        ]);
        $this->generator()->create_slot(['planid' => $plan->id, 'weekday' => 4, 'hours' => 2]);

        $result = plan_service::calendar($user->id, $plan->id);

        $this->assertSame(calendar::OUT_OF_RANGE, $result->code);
        $this->assertSame([], $result->sessions);
    }

    /**
     * Plan dates outside the window or outside the plan's school year, and the errors they get.
     *
     * @return array Case name => [fields to replace, expected errors].
     */
    public static function out_of_limits_cases(): array {
        return [
            'one-digit typo in the last day' => [['enddate' => '9027-06-21'], ['enddate' => 'error_daterange']],
            'every four-digit year' => [
                ['startdate' => '0001-01-01', 'enddate' => '9999-12-30'],
                ['startdate' => 'error_daterange', 'enddate' => 'error_daterange'],
            ],
            'the last day of year 9999' => [
                ['startdate' => '9999-12-31', 'enddate' => '9999-12-31'],
                ['startdate' => 'error_daterange', 'enddate' => 'error_daterange'],
            ],
            'first day before the school year' => [['startdate' => '2026-09-13'], ['startdate' => 'error_dateoutsideyear']],
            'last day after the school year' => [['enddate' => '2027-06-22'], ['enddate' => 'error_dateoutsideyear']],
            'only the last day, after the school year' => [
                ['startdate' => '', 'enddate' => '2028-01-10'],
                ['enddate' => 'error_dateoutsideyear'],
            ],
        ];
    }

    /**
     * Plan dates outside 2000-01-01 to 2099-12-31 or outside the school year are reported and refused.
     *
     * @dataProvider out_of_limits_cases
     * @group REQ-CAL-004
     * @param array $fields Fields to replace in valid data.
     * @param array $expected Expected errors, field => lang string id.
     */
    public function test_dates_outside_the_limits_are_refused(array $fields, array $expected): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->teacher();
        $data = self::plandata(self::year($user->id), $fields);

        $this->assertSame($expected, plan_service::validate($user->id, $data));
        try {
            plan_service::create($user->id, $data);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame(reset($expected), $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_classprogress_plan'));
    }

    /**
     * Plan dates on the first and the last day of the school year are accepted.
     *
     * @group REQ-CAL-004
     */
    public function test_dates_on_the_edges_of_the_school_year_are_accepted(): void {
        $this->resetAfterTest();
        $user = $this->teacher();

        $errors = plan_service::validate($user->id, self::plandata(self::year($user->id), [
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
        ]));

        $this->assertSame([], $errors);
    }

    /**
     * A save made from a version older than the stored plan is refused and changes nothing.
     *
     * @group REQ-DAT-003
     */
    public function test_a_save_from_an_older_version_is_refused(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $yearid = self::year($user->id);
        $id = plan_service::create($user->id, self::plandata($yearid));
        $opened = (int) plan_service::get($user->id, $id)->timemodified;

        plan_service::update($user->id, $id, self::plandata($yearid, ['timetable' => [0 => 2], 'timemodified' => $opened]));
        $saved = plan_service::get($user->id, $id);
        $this->assertGreaterThan($opened, (int) $saved->timemodified);

        try {
            plan_service::update($user->id, $id, self::plandata($yearid, ['moduletitle' => 'Stale', 'timemodified' => $opened]));
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_changedelsewhere', $e->errorcode);
        }
        $this->assertEquals($saved, plan_service::get($user->id, $id));
        $this->assertSame([0 => 2.0], plan_service::timetable($user->id, $id));
    }

    /**
     * Creating a plan takes the lock of its school year, the one a delete of that school year takes, so the school
     * year cannot be deleted between the check and the insert.
     *
     * @group REQ-DAT-006
     */
    public function test_create_waits_for_the_school_year_lock(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->lock_factory = '\core\lock\file_lock_factory';
        $user = $this->teacher();
        $yearid = self::year($user->id);
        $lock = schoolyear_service::lock($yearid);

        try {
            plan_service::create($user->id, self::plandata($yearid));
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('locktimeout', $e->errorcode);
        } finally {
            $lock->release();
        }
        $this->assertSame(0, $DB->count_records('local_classprogress_plan'));

        plan_service::create($user->id, self::plandata($yearid));
        $this->assertSame(1, $DB->count_records('local_classprogress_plan'));
    }

    /**
     * The calendars of a plan whose school year is missing are computed without holidays instead of failing.
     *
     * @group REQ-DAT-006
     */
    public function test_calendars_of_a_plan_without_its_school_year(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->teacher();
        $id = plan_service::create($user->id, self::plandata(self::year($user->id, '2026-10-12 Festa')));
        $DB->delete_records('local_classprogress_schoolyear', ['id' => plan_service::get($user->id, $id)->schoolyearid]);

        $calendars = plan_service::calendars($user->id, [$id]);

        $this->assertNull($calendars[$id]->code);
        $this->assertSame([], $calendars[$id]->lost);
        $this->assertSame([], plan_service::holidays($user->id, $id));
    }

    /**
     * Re-saving a plan keeps its course link when the teacher no longer teaches there; a new link must still be to a
     * course where the teacher teaches.
     */
    public function test_update_keeps_an_unchanged_course_link(): void {
        global $DB;

        $this->resetAfterTest();
        $old = $this->getDataGenerator()->create_course();
        $user = $this->teacher($old);
        $other = $this->getDataGenerator()->create_course();
        $yearid = self::year($user->id);
        $id = plan_service::create($user->id, self::plandata($yearid, ['courseid' => $old->id]));
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($user->id, $this->getDataGenerator()->create_course()->id, 'editingteacher');
        role_unassign($roleid, $user->id, \context_course::instance($old->id)->id);

        $this->assertSame([], plan_service::validate($user->id, self::plandata($yearid, ['courseid' => $old->id]), $id));
        plan_service::update($user->id, $id, self::plandata($yearid, ['courseid' => $old->id, 'moduletitle' => 'Nou']));

        $this->assertSame((string) $old->id, (string) plan_service::get($user->id, $id)->courseid);
        $this->assertSame(['courseid' => 'error_course'], plan_service::validate($user->id, self::plandata($yearid, [
            'courseid' => $old->id,
        ])));
        $this->assertSame(['courseid' => 'error_course'], plan_service::validate($user->id, self::plandata($yearid, [
            'courseid' => $other->id,
        ]), $id));
    }

    /**
     * The calendars of several plans come keyed by plan id, each equal to the single-plan calendar.
     *
     * @group REQ-OWN-003
     */
    public function test_calendars_of_several_own_plans(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $yearid = self::year($user->id, '2026-10-12 Festa');
        $first = plan_service::create($user->id, self::plandata($yearid));
        $second = plan_service::create($user->id, self::plandata(self::year($user->id), ['timetable' => [1 => 1]]));

        $results = plan_service::calendars($user->id, [$second, $first, $second]);

        $this->assertSame([$second, $first], array_keys($results));
        $this->assertEquals(plan_service::calendar($user->id, $first), $results[$first]);
        $this->assertEquals(plan_service::calendar($user->id, $second), $results[$second]);
        $this->assertSame([], plan_service::calendars($user->id, []));
    }

    /**
     * A list of plan ids with one that is another teacher's, or missing, is refused as a whole with the same error.
     *
     * @group REQ-OWN-003
     * @group REQ-OWN-002
     */
    public function test_calendars_refuse_the_whole_list_when_one_id_is_not_the_users(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $other = $this->teacher();
        $own = plan_service::create($user->id, self::plandata(self::year($user->id)));
        $foreign = plan_service::create($other->id, self::plandata(self::year($other->id)));
        $caught = [];

        foreach ([[$own, $foreign], [$foreign], [$own, $foreign + 1000]] as $ids) {
            try {
                plan_service::calendars($user->id, $ids);
                $this->fail('Expected an exception');
            } catch (\moodle_exception $e) {
                $caught[] = [get_class($e), $e->errorcode, $e->module, $e->getMessage()];
            }
        }

        $this->assertSame('error_notfound', $caught[0][1]);
        $this->assertSame([$caught[0], $caught[0]], [$caught[1], $caught[2]]);
    }

    /**
     * The list holds only the user's own plans.
     *
     * @group REQ-OWN-001
     */
    public function test_list_holds_only_own_plans(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $other = $this->teacher();
        $yearid = self::year($user->id);
        $b = plan_service::create($user->id, self::plandata($yearid, ['modulecode' => 'MP02']));
        $a = plan_service::create($user->id, self::plandata($yearid, ['modulecode' => 'MP01']));
        plan_service::create($other->id, self::plandata(self::year($other->id)));
        $this->generator()->create_plan(['userid' => get_admin()->id]);

        $this->assertSame([$a, $b], array_keys(plan_service::list($user->id)));
    }

    /**
     * Who tries to reach the owner's plan: a teacher with the same capability, and a site administrator.
     *
     * @return array Case name => [actor, operation].
     */
    public static function foreign_access_cases(): array {
        $cases = [];
        foreach (['other teacher', 'admin'] as $actor) {
            $operations = ['get', 'update', 'delete', 'timetable', 'calendar', 'calendars', 'check', 'import', 'check_stored',
                'approve', 'items'];
            foreach ($operations as $operation) {
                $cases["{$actor} {$operation}"] = [$actor, $operation];
            }
        }
        return $cases;
    }

    /**
     * Calls one service operation as a user.
     *
     * @param string $operation Operation name, as in foreign_access_cases().
     * @param int $userid Acting user.
     * @param int $id Plan id.
     * @param int $yearid A school year of the acting user, for update.
     */
    private static function call(string $operation, int $userid, int $id, int $yearid): void {
        match ($operation) {
            'create' => plan_service::create($userid, self::plandata($yearid)),
            'list' => plan_service::list($userid),
            'linkable_courses' => plan_service::linkable_courses($userid),
            'validate' => plan_service::validate($userid, self::plandata($yearid)),
            'get' => plan_service::get($userid, $id),
            'update' => plan_service::update($userid, $id, self::plandata($yearid, ['moduletitle' => 'Taken'])),
            'delete' => plan_service::delete($userid, $id),
            'timetable' => plan_service::timetable($userid, $id),
            'calendar' => plan_service::calendar($userid, $id),
            'calendars' => plan_service::calendars($userid, [$id]),
            'check' => plan_service::check($userid, $id, "### 2026-09-14\nx\n"),
            'import' => plan_service::import($userid, $id, "### 2026-09-14\nx\n"),
            'check_stored' => plan_service::check_stored($userid, $id),
            'approve' => plan_service::approve($userid, $id, '', true),
            'items' => plan_service::items($userid, $id),
        };
    }

    /**
     * Users who do not hold the capability in any course: a student, and a user with no role at all.
     *
     * @return array Case name => [actor, operation].
     */
    public static function no_access_cases(): array {
        $operations = ['create', 'update', 'delete', 'get', 'list', 'timetable', 'calendar', 'calendars', 'linkable_courses',
            'validate', 'check', 'import', 'check_stored', 'approve', 'items'];
        $cases = [];
        foreach (['student', 'user without a role'] as $actor) {
            foreach ($operations as $operation) {
                $cases["{$actor} {$operation}"] = [$actor, $operation];
            }
        }
        return $cases;
    }

    /**
     * Every method checks the capability live, before ownership: without it, even the user's own plan is refused and
     * nothing is written.
     *
     * @dataProvider no_access_cases
     * @group REQ-ACC-003
     * @param string $actor Who acts.
     * @param string $operation What they try.
     */
    public function test_every_method_refuses_a_user_without_the_capability(string $actor, string $operation): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        if ($actor === 'student') {
            $this->getDataGenerator()->enrol_user($user->id, $this->getDataGenerator()->create_course()->id, 'student');
        }
        $plan = $this->generator()->create_plan(['userid' => $user->id]);
        $this->generator()->create_slot(['planid' => $plan->id]);
        $before = [$DB->get_records('local_classprogress_plan'), $DB->get_records('local_classprogress_slot')];

        try {
            self::call($operation, $user->id, $plan->id, $plan->schoolyearid);
            $this->fail('Expected an exception');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        $this->assertEquals($before, [$DB->get_records('local_classprogress_plan'), $DB->get_records('local_classprogress_slot')]);
    }

    /**
     * A teacher whose role is removed is refused at the next call, with no wait for a cache or a new login.
     *
     * @group REQ-ACC-003
     */
    public function test_a_removed_role_is_refused_at_once(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->teacher($course);
        $this->setUser($user);
        $id = plan_service::create($user->id, self::plandata(self::year($user->id)));
        $this->assertNotEmpty(plan_service::calendar($user->id, $id)->sessions);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);

        role_unassign($roleid, $user->id, \context_course::instance($course->id)->id);

        $this->expectException(\required_capability_exception::class);
        plan_service::calendar($user->id, $id);
    }

    /**
     * Another teacher with the same capability, or an administrator, can neither read, change nor delete the plan.
     *
     * @dataProvider foreign_access_cases
     * @group REQ-ACC-003
     * @group REQ-OWN-001
     * @param string $actor Who acts.
     * @param string $operation What they try.
     */
    public function test_nobody_else_reaches_a_plan(string $actor, string $operation): void {
        global $DB;

        $this->resetAfterTest();
        $owner = $this->teacher();
        $id = plan_service::create($owner->id, self::plandata(self::year($owner->id)));
        $before = [
            $DB->get_record('local_classprogress_plan', ['id' => $id]),
            $DB->get_records('local_classprogress_slot', ['planid' => $id]),
        ];
        $userid = $actor === 'admin' ? (int) get_admin()->id : (int) $this->teacher()->id;
        $yearid = self::year($userid);

        try {
            self::call($operation, $userid, $id, $yearid);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_notfound', $e->errorcode);
        }

        $this->assertEquals($before, [
            $DB->get_record('local_classprogress_plan', ['id' => $id]),
            $DB->get_records('local_classprogress_slot', ['planid' => $id]),
        ]);
    }

    /**
     * A missing id and another owner's id give exactly the same exception, for every operation.
     *
     * @dataProvider foreign_access_cases
     * @group REQ-OWN-002
     * @param string $actor Who acts.
     * @param string $operation What they try.
     */
    public function test_missing_and_foreign_plans_give_the_same_error(string $actor, string $operation): void {
        $this->resetAfterTest();
        $owner = $this->teacher();
        $id = plan_service::create($owner->id, self::plandata(self::year($owner->id)));
        $userid = $actor === 'admin' ? (int) get_admin()->id : (int) $this->teacher()->id;
        $yearid = self::year($userid);
        $caught = [];

        foreach ([$id, $id + 1000] as $target) {
            try {
                self::call($operation, $userid, $target, $yearid);
                $this->fail('Expected an exception');
            } catch (\moodle_exception $e) {
                $caught[] = [get_class($e), $e->errorcode, $e->module, $e->getMessage(), $e->link, $e->a, $e->debuginfo];
            }
        }

        $this->assertSame($caught[0], $caught[1]);
    }

    /**
     * A plan cannot be created in, or moved to, another teacher's school year, and that gives the same error as a
     * school year that does not exist.
     *
     * @group REQ-OWN-001
     * @group REQ-OWN-002
     */
    public function test_no_plan_goes_into_another_teachers_school_year(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->teacher();
        $foreignyearid = self::year($this->teacher()->id);
        $id = plan_service::create($user->id, self::plandata(self::year($user->id)));
        $before = $DB->get_record('local_classprogress_plan', ['id' => $id]);
        $caught = [];

        foreach ([$foreignyearid, $foreignyearid + 1000] as $yearid) {
            $this->assertSame(['schoolyearid' => 'error_notfound'], plan_service::validate($user->id, self::plandata($yearid)));
            $writes = [
                fn() => plan_service::create($user->id, self::plandata($yearid)),
                fn() => plan_service::update($user->id, $id, self::plandata($yearid)),
            ];
            foreach ($writes as $write) {
                try {
                    $write();
                    $this->fail('Expected an exception');
                } catch (\moodle_exception $e) {
                    $caught[] = [get_class($e), $e->errorcode, $e->module, $e->getMessage()];
                }
            }
        }

        $this->assertSame('error_notfound', $caught[0][1]);
        $this->assertSame(array_fill(0, 4, $caught[0]), $caught);
        $this->assertSame(0, $DB->count_records('local_classprogress_plan', ['schoolyearid' => $foreignyearid]));
        $this->assertEquals($before, $DB->get_record('local_classprogress_plan', ['id' => $id]));
    }
}
