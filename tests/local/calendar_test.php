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
 * Tests for the class calendar: sessions, totals and sessions lost to holidays.
 *
 * Timetables are weekday => hours with Monday = 0 and Sunday = 6. 2026-09-14 is a Monday.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_test extends \basic_testcase {
    /**
     * Sessions as [date, hours] pairs, for compact assertions.
     *
     * @param calendar_result $result A calendar result.
     * @return array<int, array{string, float}> One [date, hours] pair per session.
     */
    private static function pairs(calendar_result $result): array {
        return array_map(fn(calendar_session $s): array => [$s->date, $s->hours], $result->sessions);
    }

    /**
     * Sessions fall on the weekdays with hours, Monday = 0 to Sunday = 6, and carry that day's hours.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-001
     */
    public function test_sessions_fall_on_timetable_weekdays_with_their_hours(): void {
        $result = calendar::compute('2026-09-14', '2026-09-27', [0 => 2, 2 => 1.5, 6 => 1], []);

        $this->assertNull($result->code);
        $this->assertSame([
            ['2026-09-14', 2.0],
            ['2026-09-16', 1.5],
            ['2026-09-20', 1.0],
            ['2026-09-21', 2.0],
            ['2026-09-23', 1.5],
            ['2026-09-27', 1.0],
        ], self::pairs($result));
    }

    /**
     * Weekdays with zero hours, or left out of the timetable, have no session.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-001
     */
    public function test_weekday_with_zero_hours_has_no_session(): void {
        $timetable = [0 => 0, 1 => 0.0, 2 => 1, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        $result = calendar::compute('2026-09-14', '2026-09-20', $timetable, []);

        $this->assertSame([['2026-09-16', 1.0]], self::pairs($result));
    }

    /**
     * The first and last class days are both included; a one-day range gives one session.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-001
     */
    public function test_first_and_last_day_are_included(): void {
        $timetable = [0 => 2, 2 => 1];

        $week = calendar::compute('2026-09-16', '2026-09-21', $timetable, []);
        $oneday = calendar::compute('2026-09-14', '2026-09-14', $timetable, []);

        $this->assertSame([['2026-09-16', 1.0], ['2026-09-21', 2.0]], self::pairs($week));
        $this->assertSame([['2026-09-14', 2.0]], self::pairs($oneday));
    }

    /**
     * A holiday on a class day removes that session, also when it is part of a period.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-001
     */
    public function test_holiday_removes_class_session(): void {
        $holidays = holiday_parser::parse("2026-09-16 Festa\n2026-09-21 to 2026-09-22 Pont")->entries;

        $result = calendar::compute('2026-09-14', '2026-09-23', [0 => 2, 2 => 1], $holidays);

        $this->assertSame([['2026-09-14', 2.0], ['2026-09-23', 1.0]], self::pairs($result));
    }

    /**
     * Three real modules with the school's holiday file, and the useful sessions the teacher's own tool counted.
     *
     * @return array<string, array{string, string, array<int, float>, int}> First day, last day, timetable, sessions.
     */
    public static function real_module_provider(): array {
        return [
            '0372 Monday and Friday 2 h' => ['2026-09-14', '2027-05-24', [0 => 2.0, 4 => 2.0], 60],
            '0374 Monday and Thursday 2 h' => ['2026-09-14', '2027-05-28', [0 => 2.0, 3 => 2.0], 61],
            '0377 Tuesday 1 h' => ['2026-09-14', '2027-05-24', [1 => 1.0], 30],
        ];
    }

    /**
     * The session count of three real modules equals the count from the teacher's existing tool.
     *
     * @covers \local_classprogress\local\calendar
     * @dataProvider real_module_provider
     * @group REQ-CAL-001
     * @param string $first First class day.
     * @param string $last Last class day.
     * @param array<int, float> $timetable Weekday => hours.
     * @param int $sessions Sessions counted by the teacher's tool.
     */
    public function test_real_modules_match_teachers_tool(string $first, string $last, array $timetable, int $sessions): void {
        $result = calendar::compute($first, $last, $timetable, self::school_holidays());

        $this->assertCount($sessions, $result->sessions);
    }

    /**
     * The calendar gives the number of sessions, the total hours and the sessions of each month.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-002
     */
    public function test_totals_give_count_hours_and_sessions_per_month(): void {
        $holidays = holiday_parser::parse("2026-10-12 Festa Nacional\n2026-11-01 Tots Sants")->entries;

        $result = calendar::compute('2026-09-28', '2026-11-02', [0 => 1.5, 3 => 2], $holidays);

        $this->assertCount(10, $result->sessions);
        $this->assertSame(17.5, $result->totalhours);
        $this->assertSame(['2026-09' => 1, '2026-10' => 8, '2026-11' => 1], $result->sessionspermonth);
    }

    /**
     * Every month from the first to the last class day is listed, also one without sessions.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-002
     */
    public function test_month_without_sessions_is_listed_with_zero(): void {
        $holidays = holiday_parser::parse('2026-08-01 to 2026-08-31 Estiu')->entries;

        $result = calendar::compute('2026-07-27', '2026-09-14', [0 => 2], $holidays);

        $this->assertSame(['2026-07' => 1, '2026-08' => 0, '2026-09' => 2], $result->sessionspermonth);
    }

    /**
     * Total hours are a float even when the timetable gives whole hours as integers.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-002
     */
    public function test_total_hours_is_float_for_integer_timetable(): void {
        $result = calendar::compute('2026-09-14', '2026-09-27', [0 => 2], []);

        $this->assertSame(4.0, $result->totalhours);
    }

    /**
     * First and last class days that cannot give a calendar, with the code each must get.
     *
     * @return array<string, array{string|null, string|null, string}> First day, last day and expected code.
     */
    public static function invalid_range_provider(): array {
        return [
            'first missing' => [null, '2027-05-24', calendar::MISSING_DATES],
            'last empty' => ['2026-09-14', '', calendar::MISSING_DATES],
            'both missing' => [null, null, calendar::MISSING_DATES],
            'missing comes before invalid' => [null, 'someday', calendar::MISSING_DATES],
            'first does not exist' => ['2026-02-30', '2027-05-24', calendar::INVALID_DATE],
            'last does not exist' => ['2026-09-14', '2027-13-01', calendar::INVALID_DATE],
            'dd/mm/yyyy is not stored' => ['14/09/2026', '2027-05-24', calendar::INVALID_DATE],
            'words' => ['2026-09-14', 'end of May', calendar::INVALID_DATE],
            'trailing newline' => ["2026-09-14\n", '2027-05-24', calendar::INVALID_DATE],
            'surrounding space' => [' 2026-09-14', '2027-05-24', calendar::INVALID_DATE],
            'reversed' => ['2027-05-24', '2026-09-14', calendar::LAST_BEFORE_FIRST],
            'reversed by one day' => ['2026-09-15', '2026-09-14', calendar::LAST_BEFORE_FIRST],
        ];
    }

    /**
     * Missing, invalid or reversed dates give an empty result with a code, not an exception.
     *
     * @covers \local_classprogress\local\calendar
     * @dataProvider invalid_range_provider
     * @group REQ-CAL-002
     * @param string|null $first First class day.
     * @param string|null $last Last class day.
     * @param string $code Expected code.
     */
    public function test_invalid_range_gives_empty_result_with_code(?string $first, ?string $last, string $code): void {
        $result = calendar::compute($first, $last, [0 => 2, 1 => 2, 2 => 2, 3 => 2, 4 => 2], self::school_holidays());

        $this->assertSame($code, $result->code);
        $this->assertSame([], $result->sessions);
        $this->assertSame(0.0, $result->totalhours);
        $this->assertSame([], $result->sessionspermonth);
        $this->assertSame([], $result->lost);
    }

    /**
     * Each holiday entry reports how many class sessions it removed, zero included.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-003
     */
    public function test_each_holiday_reports_sessions_it_removed(): void {
        $text = "2026-09-16 Festa\n2026-09-19 to 2026-09-20 Cap de setmana\n2026-09-21 to 2026-09-25 Setmana";
        $holidays = holiday_parser::parse($text)->entries;

        $result = calendar::compute('2026-09-14', '2026-09-27', [0 => 2, 2 => 1], $holidays);

        $this->assertSame([0 => 1, 1 => 0, 2 => 2], $result->lost);
        $this->assertSame([['2026-09-14', 2.0]], self::pairs($result));
    }

    /**
     * A holiday on a weekend or another day without class removes no session.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-001
     * @group REQ-CAL-003
     */
    public function test_holiday_on_weekend_removes_no_session(): void {
        $nowednesday = [0 => 1, 1 => 1, 2 => 0, 3 => 1, 4 => 1];
        $holidays = holiday_parser::parse("2026-10-31 to 2026-11-01 Tots Sants\n2026-10-28 Sense classe")->entries;

        $result = calendar::compute('2026-10-26', '2026-11-01', $nowednesday, $holidays);

        $this->assertSame([0 => 0, 1 => 0], $result->lost);
        $this->assertCount(4, $result->sessions);
    }

    /**
     * A day covered by two entries is lost once, and counts for the first entry only.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-003
     */
    public function test_overlapping_holidays_count_lost_session_for_first_entry_only(): void {
        $holidays = holiday_parser::parse("2026-12-21 to 2026-12-23 Abans de Nadal\n2026-12-22 Pont")->entries;

        $result = calendar::compute('2026-12-21', '2026-12-23', [1 => 1], $holidays);

        $this->assertSame([0 => 1, 1 => 0], $result->lost);
        $this->assertSame([], $result->sessions);
    }

    /**
     * Only class days between the first and last class day count as lost, and lost keeps the holiday array keys.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-003
     */
    public function test_only_days_inside_range_count_as_lost_and_keys_are_kept(): void {
        $entries = holiday_parser::parse("2026-09-07 to 2026-09-15 Abans\n2026-09-28 Despres")->entries;
        $holidays = ['crossing' => $entries[0], 42 => $entries[1]];

        $result = calendar::compute('2026-09-14', '2026-09-27', [0 => 1, 1 => 1], $holidays);

        $this->assertSame(['crossing' => 2, 42 => 0], $result->lost);
        $this->assertCount(2, $result->sessions);
    }

    /**
     * Module 0372 loses the sessions the teacher's tool reports for each holiday label.
     *
     * @covers \local_classprogress\local\calendar
     * @group REQ-CAL-003
     */
    public function test_real_module_loses_sessions_per_label_as_teachers_tool(): void {
        $holidays = self::school_holidays();

        $result = calendar::compute('2026-09-14', '2027-05-24', [0 => 2.0, 4 => 2.0], $holidays);

        $bylabel = [];
        foreach ($result->lost as $key => $lost) {
            $label = $holidays[$key]->label;
            $bylabel[$label] = ($bylabel[$label] ?? 0) + $lost;
        }
        $this->assertSame([
            "Festa Nacional d'Espanya" => 1,
            'Lliure disposició del centre' => 5,
            'Vacances de Nadal' => 4,
            'Vacances de Setmana Santa' => 3,
        ], array_filter($bylabel));
        $this->assertCount(count($holidays), $result->lost);
        $this->assertSame(73, count($result->sessions) + array_sum($result->lost));
    }

    /**
     * The entries of the school's real holiday file.
     *
     * @return holiday_entry[] The parsed entries.
     */
    private static function school_holidays(): array {
        return holiday_parser::parse(file_get_contents(__DIR__ . '/../fixtures/holidays-centre-2026-27.txt'))->entries;
    }
}
