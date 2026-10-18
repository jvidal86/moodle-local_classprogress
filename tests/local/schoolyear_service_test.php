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
 * Tests for the school year service: school years, their holidays and evaluation periods, owned by one teacher.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\local\schoolyear_service
 */
final class schoolyear_service_test extends \advanced_testcase {
    /**
     * The plugin generator.
     *
     * @return \local_classprogress_generator The generator.
     */
    private function generator(): \local_classprogress_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_classprogress');
    }

    /**
     * A new user with a teacher role in a new course.
     *
     * @return \stdClass The user.
     */
    private function teacher(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        return $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
    }

    /**
     * School year data as a form would send it, with two holidays and three evaluation periods.
     *
     * @param array $fields Fields to replace.
     * @return \stdClass The data.
     */
    private static function yeardata(array $fields = []): \stdClass {
        return (object) array_merge([
            'name' => 'Curs 2026-2027',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
            'holidays' => "2026-10-12 Festa Nacional\n2026-12-22 to 2027-01-07 Vacances de Nadal\n",
            'evaluations' => [
                (object) ['name' => '1a avaluació', 'startdate' => '2026-09-14', 'enddate' => '2026-12-21'],
                (object) ['name' => '2a avaluació', 'startdate' => '2027-01-08', 'enddate' => '2027-03-19'],
                (object) ['name' => '3a avaluació', 'startdate' => '2027-03-30', 'enddate' => '2027-06-21'],
            ],
        ], $fields);
    }

    /**
     * Holiday entries as [start, end, label] triples, for compact assertions.
     *
     * @param holiday_entry[] $entries Entries.
     * @return array One [start, end, label] triple per entry, in order.
     */
    private static function triples(array $entries): array {
        return array_values(array_map(fn(holiday_entry $h): array => [$h->start, $h->end, $h->label], $entries));
    }

    /**
     * Evaluation rows as [name, start, end] triples, for compact assertions.
     *
     * @param \stdClass[] $rows Evaluation rows.
     * @return array One [name, start, end] triple per row, in order.
     */
    private static function evaluation_triples(array $rows): array {
        return array_values(array_map(fn(\stdClass $e): array => [$e->name, $e->startdate, $e->enddate], $rows));
    }

    /**
     * Creating a school year stores its dates, every holiday line and the evaluation periods, owned by the user.
     *
     * @group REQ-HOL-001
     * @group REQ-HOL-003
     */
    public function test_create_stores_year_holidays_and_evaluations(): void {
        $this->resetAfterTest();
        $user = $this->teacher();

        $id = schoolyear_service::create($user->id, self::yeardata());

        $year = schoolyear_service::get($user->id, $id);
        $this->assertSame((string) $user->id, (string) $year->userid);
        $this->assertSame('Curs 2026-2027', $year->name);
        $this->assertSame('2026-09-14', $year->startdate);
        $this->assertSame('2027-06-21', $year->enddate);
        $this->assertSame([
            ['2026-10-12', '2026-10-12', 'Festa Nacional'],
            ['2026-12-22', '2027-01-07', 'Vacances de Nadal'],
        ], self::triples(schoolyear_service::holidays($user->id, $id)));
        $this->assertSame([
            ['1a avaluació', '2026-09-14', '2026-12-21'],
            ['2a avaluació', '2027-01-08', '2027-03-19'],
            ['3a avaluació', '2027-03-30', '2027-06-21'],
        ], self::evaluation_triples(schoolyear_service::evaluations($user->id, $id)));
    }

    /**
     * The real school calendar (14 single days and 2 periods, with comments) is stored line by line.
     *
     * @group REQ-HOL-001
     */
    public function test_create_stores_the_real_school_calendar(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $text = file_get_contents(__DIR__ . '/../fixtures/holidays-centre-2026-27.txt');

        $id = schoolyear_service::create($user->id, self::yeardata(['holidays' => $text]));

        $holidays = self::triples(schoolyear_service::holidays($user->id, $id));
        $this->assertCount(16, $holidays);
        $this->assertSame(['2026-09-11', '2026-09-11', 'Diada Nacional de Catalunya'], $holidays[0]);
        $this->assertSame(['2026-12-22', '2027-01-07', 'Vacances de Nadal'], $holidays[7]);
        $this->assertSame(['2027-06-25', '2027-06-25', 'Festa local de Valls'], $holidays[15]);
    }

    /**
     * Holiday text with a bad line is refused as a whole: nothing is stored, and the check names the line and reason.
     *
     * @group REQ-HOL-002
     */
    public function test_holiday_text_with_a_bad_line_is_refused_and_nothing_is_stored(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->teacher();
        $text = "2026-10-12 Festa\n2026-12-22 to 2027-1-07 Nadal\n2026-11-01 Tots Sants\n";

        try {
            schoolyear_service::create($user->id, self::yeardata(['holidays' => $text]));
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_holidaylines', $e->errorcode);
        }

        $this->assertSame(0, $DB->count_records('local_classprogress_schoolyear'));
        $this->assertSame(0, $DB->count_records('local_classprogress_holiday'));
        $this->assertSame(0, $DB->count_records('local_classprogress_evaluation'));
    }

    /**
     * Updating with bad holiday text keeps the stored year, holidays and evaluations as they were.
     *
     * @group REQ-HOL-002
     */
    public function test_update_with_a_bad_holiday_line_changes_nothing(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $id = schoolyear_service::create($user->id, self::yeardata());

        try {
            schoolyear_service::update($user->id, $id, self::yeardata(['name' => 'Changed', 'holidays' => "Nadal\n"]));
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_holidaylines', $e->errorcode);
        }

        $this->assertSame('Curs 2026-2027', schoolyear_service::get($user->id, $id)->name);
        $this->assertCount(2, schoolyear_service::holidays($user->id, $id));
        $this->assertCount(3, schoolyear_service::evaluations($user->id, $id));
    }

    /**
     * The holiday check returns the parser's codes with line numbers, for every bad line, and the good lines.
     *
     * @group REQ-HOL-002
     */
    public function test_check_holidays_reports_every_bad_line_with_its_number_and_code(): void {
        $text = "# comment\n2026-10-12 Festa\nNadal\n2027-02-30 Bad day\n2027-03-29 to 2027-03-22 Back\n"
            . "2026-12-22 to 2027-1-07 Nadal\n2026-11-01 Tots Sants\n";

        $result = schoolyear_service::check_holidays($text);

        $this->assertSame([
            [3, holiday_parser::NOT_A_DATE, 'Nadal'],
            [4, holiday_parser::NO_SUCH_DATE, '2027-02-30 Bad day'],
            [5, holiday_parser::END_BEFORE_START, '2027-03-29 to 2027-03-22 Back'],
            [6, holiday_parser::BAD_END_DATE, '2026-12-22 to 2027-1-07 Nadal'],
        ], array_map(fn(holiday_error $e): array => [$e->line, $e->code, $e->text], $result->errors));
        $this->assertSame([2, 7], array_map(fn(holiday_entry $h): int => $h->line, $result->entries));
    }

    /**
     * A label that does not fit the database column is a bad line of its own, never cut and stored.
     *
     * @group REQ-HOL-002
     */
    public function test_check_holidays_reports_a_label_that_is_too_long(): void {
        $label = str_repeat('é', 256);

        $result = schoolyear_service::check_holidays("2026-10-12 {$label}\n2026-11-01 " . str_repeat('é', 255));

        $this->assertCount(1, $result->errors);
        $this->assertSame(1, $result->errors[0]->line);
        $this->assertSame(schoolyear_service::LABEL_TOO_LONG, $result->errors[0]->code);
        $this->assertSame([2], array_map(fn(holiday_entry $h): int => $h->line, $result->entries));
    }

    /**
     * Stored holidays written back as text read as the same holidays, including labels that look like a range.
     *
     * @group REQ-HOL-001
     */
    public function test_stored_holidays_written_as_text_read_back_the_same(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $year = $this->generator()->create_schoolyear(['userid' => $user->id]);
        $holidays = [
            ['2026-10-12', '2026-10-12', 'Festa'],
            ['2026-12-22', '2027-01-07', 'Nadal'],
            ['2026-11-01', '2026-11-01', ''],
            ['2026-11-02', '2026-11-02', 'to 5 dies'],
            ['2026-12-07', '2026-12-07', '- 7 Pont'],
            ['2027-02-01', '2027-02-03', 'a 2027-02-05'],
        ];
        foreach ($holidays as [$start, $end, $label]) {
            $this->generator()->create_holiday([
                'schoolyearid' => $year->id, 'startdate' => $start, 'enddate' => $end, 'label' => $label,
            ]);
        }
        $stored = schoolyear_service::holidays($user->id, $year->id);

        $text = schoolyear_service::holidays_to_text($stored);

        $result = schoolyear_service::check_holidays($text);
        $this->assertSame([], $result->errors);
        $this->assertSame(self::triples($stored), self::triples($result->entries));
        $this->assertSame(range(1, 6), array_values(array_map(fn(holiday_entry $h): int => $h->line, $stored)));
    }

    /**
     * Updating replaces the dates, the holidays and the evaluation periods.
     *
     * @group REQ-HOL-003
     */
    public function test_update_replaces_dates_holidays_and_evaluations(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $id = schoolyear_service::create($user->id, self::yeardata());

        schoolyear_service::update($user->id, $id, self::yeardata([
            'name' => 'Curs 26-27',
            'enddate' => '2027-06-30',
            'holidays' => '2027-05-01 Festa del Treball',
            'evaluations' => [(object) ['name' => 'Única', 'startdate' => '2026-09-14', 'enddate' => '2027-06-30']],
        ]));

        $year = schoolyear_service::get($user->id, $id);
        $this->assertSame('Curs 26-27', $year->name);
        $this->assertSame('2027-06-30', $year->enddate);
        $holidays = self::triples(schoolyear_service::holidays($user->id, $id));
        $this->assertSame([['2027-05-01', '2027-05-01', 'Festa del Treball']], $holidays);
        $evaluations = self::evaluation_triples(schoolyear_service::evaluations($user->id, $id));
        $this->assertSame([['Única', '2026-09-14', '2027-06-30']], $evaluations);
    }

    /**
     * Data without holiday text or evaluations leaves them as they are on update, and empty on create.
     */
    public function test_holidays_and_evaluations_are_optional(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $bare = (object) ['name' => 'Bare', 'startdate' => '2026-09-14', 'enddate' => '2027-06-21'];
        $empty = schoolyear_service::create($user->id, $bare);
        $full = schoolyear_service::create($user->id, self::yeardata());

        schoolyear_service::update($user->id, $full, $bare);

        $this->assertSame([], schoolyear_service::holidays($user->id, $empty));
        $this->assertSame([], schoolyear_service::evaluations($user->id, $empty));
        $this->assertSame('Bare', schoolyear_service::get($user->id, $full)->name);
        $this->assertCount(2, schoolyear_service::holidays($user->id, $full));
        $this->assertCount(3, schoolyear_service::evaluations($user->id, $full));
    }

    /**
     * Invalid data and the error each field gets.
     *
     * @return array Case name => [fields to replace, expected errors].
     */
    public static function invalid_data_cases(): array {
        $eval = fn(string $name, string $start, string $end): \stdClass =>
            (object) ['name' => $name, 'startdate' => $start, 'enddate' => $end];
        return [
            'empty name' => [['name' => '  '], ['name' => 'error_name']],
            'name too long' => [['name' => str_repeat('a', 256)], ['name' => 'error_name']],
            'date not ISO' => [['startdate' => '14/09/2026'], ['startdate' => 'error_baddate']],
            'no such date' => [['enddate' => '2027-02-30'], ['enddate' => 'error_baddate']],
            'date with extra text' => [['startdate' => '2026-09-14x'], ['startdate' => 'error_baddate']],
            'empty date' => [['enddate' => ''], ['enddate' => 'error_baddate']],
            'end before start' => [['enddate' => '2026-09-13'], ['enddate' => 'error_endbeforestart']],
            'holiday text too long' => [
                ['holidays' => str_repeat("2026-10-12 Festa\n", 2000)],
                ['holidays' => 'error_holidaytexttoolong'],
            ],
            'bad holiday line' => [['holidays' => "2026-10-12\nNadal"], ['holidays' => 'error_holidaylines']],
            'evaluation without name' => [
                ['evaluations' => [$eval('', '2026-09-14', '2026-12-21')]],
                ['evaluations-0' => 'error_name'],
            ],
            'evaluation errors keep the caller key' => [
                ['evaluations' => [3 => $eval('1a', '2026-09-14', '2026-12-21'), 5 => $eval('2a', 'x', '2027-03-19')]],
                ['evaluations-5' => 'error_baddate'],
            ],
            'evaluation with bad date' => [
                ['evaluations' => [$eval('1a', '2026-09-14', '2026-13-01')]],
                ['evaluations-0' => 'error_baddate'],
            ],
            'evaluation ending before it starts' => [
                ['evaluations' => [$eval('1a', '2026-12-21', '2026-09-14')]],
                ['evaluations-0' => 'error_endbeforestart'],
            ],
            'evaluation outside the year' => [
                ['evaluations' => [$eval('1a', '2026-09-01', '2026-12-21')]],
                ['evaluations-0' => 'error_evaluationoutsideyear'],
            ],
            'evaluations overlapping' => [
                ['evaluations' => [$eval('2a', '2027-01-08', '2027-03-19'), $eval('1a', '2026-09-14', '2027-01-08')]],
                ['evaluations-0' => 'error_evaluationsoverlap'],
            ],
            'too many evaluations' => [
                ['evaluations' => array_fill(0, 21, $eval('x', '2026-09-14', '2026-09-14'))],
                ['evaluations' => 'error_toomanyevaluations'],
            ],
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
        $data = self::yeardata($fields);

        $this->assertSame($expected, schoolyear_service::validate($data));

        try {
            schoolyear_service::create($user->id, $data);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame(reset($expected), $e->errorcode);
            $this->assertSame('local_classprogress', $e->module);
        }
        $this->assertSame(0, $DB->count_records('local_classprogress_schoolyear'));
    }

    /**
     * Valid data has no errors; names and labels are trimmed; two evaluations may touch but not share a day.
     */
    public function test_valid_data_has_no_errors_and_text_is_trimmed(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $data = self::yeardata(['name' => "  Curs\t", 'evaluations' => [
            (object) ['name' => ' 1a ', 'startdate' => '2026-09-14', 'enddate' => '2026-12-21'],
            (object) ['name' => '2a', 'startdate' => '2026-12-22', 'enddate' => '2027-06-21'],
        ]]);

        $this->assertSame([], schoolyear_service::validate($data));
        $id = schoolyear_service::create($user->id, $data);

        $this->assertSame('Curs', schoolyear_service::get($user->id, $id)->name);
        $evaluations = self::evaluation_triples(schoolyear_service::evaluations($user->id, $id));
        $this->assertSame([['1a', '2026-09-14', '2026-12-21'], ['2a', '2026-12-22', '2027-06-21']], $evaluations);
    }

    /**
     * Deleting a school year without plans removes it with its holidays and evaluations, and nothing else.
     */
    public function test_delete_removes_the_year_with_its_holidays_and_evaluations(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->teacher();
        $id = schoolyear_service::create($user->id, self::yeardata());
        $other = schoolyear_service::create($user->id, self::yeardata(['name' => 'Other']));

        schoolyear_service::delete($user->id, $id);

        $this->assertFalse($DB->record_exists('local_classprogress_schoolyear', ['id' => $id]));
        $this->assertSame(0, $DB->count_records('local_classprogress_holiday', ['schoolyearid' => $id]));
        $this->assertSame(0, $DB->count_records('local_classprogress_evaluation', ['schoolyearid' => $id]));
        $this->assertCount(2, schoolyear_service::holidays($user->id, $other));
    }

    /**
     * A school year that has plans is not deleted: its plans must be deleted first.
     */
    public function test_delete_refuses_a_year_with_plans(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->teacher();
        $id = schoolyear_service::create($user->id, self::yeardata());
        $this->generator()->create_plan(['schoolyearid' => $id]);

        try {
            schoolyear_service::delete($user->id, $id);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_schoolyearhasplans', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_classprogress_schoolyear', ['id' => $id]));
        $this->assertSame(2, $DB->count_records('local_classprogress_holiday', ['schoolyearid' => $id]));
        $this->assertSame(1, schoolyear_service::count_plans($user->id, $id));
    }

    /**
     * The list holds only the user's own school years, newest first.
     *
     * @group REQ-OWN-001
     */
    public function test_list_holds_only_own_years_newest_first(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $old = $this->generator()->create_schoolyear(['userid' => $user->id, 'startdate' => '2025-09-15']);
        $new = $this->generator()->create_schoolyear(['userid' => $user->id, 'startdate' => '2026-09-14']);
        $this->generator()->create_schoolyear(['userid' => $this->teacher()->id]);
        $this->generator()->create_schoolyear(['userid' => get_admin()->id]);

        $this->assertSame([(int) $new->id, (int) $old->id], array_keys(schoolyear_service::list($user->id)));
    }

    /**
     * Who tries to reach the owner's school year: a teacher with the same capability, and a site administrator.
     *
     * @return array Case name => [actor, operation].
     */
    public static function foreign_access_cases(): array {
        $cases = [];
        foreach (['other teacher', 'admin'] as $actor) {
            foreach (['get', 'update', 'delete', 'holidays', 'evaluations', 'count_plans'] as $operation) {
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
     * @param int $id School year id.
     */
    private static function call(string $operation, int $userid, int $id): void {
        match ($operation) {
            'get' => schoolyear_service::get($userid, $id),
            'update' => schoolyear_service::update($userid, $id, self::yeardata(['name' => 'Taken'])),
            'delete' => schoolyear_service::delete($userid, $id),
            'holidays' => schoolyear_service::holidays($userid, $id),
            'evaluations' => schoolyear_service::evaluations($userid, $id),
            'count_plans' => schoolyear_service::count_plans($userid, $id),
        };
    }

    /**
     * Another teacher with the same capability, or an administrator, can neither read, change nor delete the year.
     *
     * @dataProvider foreign_access_cases
     * @group REQ-OWN-001
     * @param string $actor Who acts.
     * @param string $operation What they try.
     */
    public function test_nobody_else_reaches_a_school_year(string $actor, string $operation): void {
        global $DB;

        $this->resetAfterTest();
        $owner = $this->teacher();
        $id = schoolyear_service::create($owner->id, self::yeardata());
        $before = [
            $DB->get_record('local_classprogress_schoolyear', ['id' => $id]),
            $DB->get_records('local_classprogress_holiday', ['schoolyearid' => $id]),
            $DB->get_records('local_classprogress_evaluation', ['schoolyearid' => $id]),
        ];
        $userid = $actor === 'admin' ? (int) get_admin()->id : (int) $this->teacher()->id;

        try {
            self::call($operation, $userid, $id);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_notfound', $e->errorcode);
        }

        $this->assertEquals($before, [
            $DB->get_record('local_classprogress_schoolyear', ['id' => $id]),
            $DB->get_records('local_classprogress_holiday', ['schoolyearid' => $id]),
            $DB->get_records('local_classprogress_evaluation', ['schoolyearid' => $id]),
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
    public function test_missing_and_foreign_years_give_the_same_error(string $actor, string $operation): void {
        $this->resetAfterTest();
        $owner = $this->teacher();
        $id = schoolyear_service::create($owner->id, self::yeardata());
        $userid = $actor === 'admin' ? (int) get_admin()->id : (int) $this->teacher()->id;
        $caught = [];

        foreach ([$id, $id + 1000] as $target) {
            try {
                self::call($operation, $userid, $target);
                $this->fail('Expected an exception');
            } catch (\moodle_exception $e) {
                $caught[] = [get_class($e), $e->errorcode, $e->module, $e->getMessage(), $e->link, $e->a, $e->debuginfo];
            }
        }

        $this->assertSame($caught[0], $caught[1]);
    }
}
