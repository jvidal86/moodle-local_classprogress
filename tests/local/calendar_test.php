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
     * The entries of the school's real holiday file.
     *
     * @return holiday_entry[] The parsed entries.
     */
    private static function school_holidays(): array {
        return holiday_parser::parse(file_get_contents(__DIR__ . '/../fixtures/holidays-centre-2026-27.txt'))->entries;
    }
}
