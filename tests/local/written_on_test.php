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
 * Tests for the written-on date of an entry and how many days late it is.
 *
 * Timestamps are literals, each with its UTC time in a comment. In 2026 Europe/Madrid moves from UTC+1 to UTC+2 at
 * 01:00 UTC on Sunday 29 March and back at 01:00 UTC on Sunday 25 October.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class written_on_test extends \basic_testcase {
    /**
     * Instants around local midnight, in and out of summer time, with the Madrid day each falls on.
     *
     * @return array Cases: timestamp, expected day.
     */
    public static function madrid_midnight_provider(): array {
        return [
            'before midnight, winter time, eve of the March change' => [1774737000, '2026-03-28'], // 2026-03-28 22:30 UTC.
            'after midnight, winter time, March change day' => [1774740600, '2026-03-29'], // 2026-03-28 23:30 UTC.
            'before midnight, summer time, March change day' => [1774819800, '2026-03-29'], // 2026-03-29 21:30 UTC.
            'after midnight, summer time, day after the March change' => [1774823400, '2026-03-30'], // 2026-03-29 22:30 UTC.
            'before midnight, summer time, eve of the October change' => [1792877400, '2026-10-24'], // 2026-10-24 21:30 UTC.
            'after midnight, summer time, October change day' => [1792881000, '2026-10-25'], // 2026-10-24 22:30 UTC.
            'before midnight, winter time, October change day' => [1792967400, '2026-10-25'], // 2026-10-25 22:30 UTC.
            'after midnight, winter time, day after the October change' => [1792971000, '2026-10-26'], // 2026-10-25 23:30 UTC.
        ];
    }

    /**
     * The written-on day is the teacher's own day in Madrid, across midnight and both changes of the clock.
     *
     * @dataProvider madrid_midnight_provider
     * @covers \local_classprogress\local\written_on
     * @group REQ-LOG-004
     * @param int $timestamp The moment the entry is first saved.
     * @param string $expected The Madrid day, yyyy-mm-dd.
     */
    public function test_written_on_is_the_madrid_day_around_midnight_and_clock_changes(int $timestamp, string $expected): void {
        $this->assertSame($expected, written_on::day($timestamp, 'Europe/Madrid'));
    }

    /**
     * The same moment is a different day in time zones far from UTC on either side.
     *
     * @covers \local_classprogress\local\written_on
     * @group REQ-LOG-004
     */
    public function test_written_on_follows_time_zones_far_from_utc(): void {
        $timestamp = 1792319400; // 2026-10-18 10:30 UTC.

        $this->assertSame('2026-10-19', written_on::day($timestamp, 'Pacific/Kiritimati'));
        $this->assertSame('2026-10-18', written_on::day($timestamp, 'UTC'));
        $this->assertSame('2026-10-17', written_on::day($timestamp, 'Pacific/Pago_Pago'));
    }

    /**
     * The first and the last second of a UTC day both belong to that day.
     *
     * @covers \local_classprogress\local\written_on
     * @group REQ-LOG-004
     */
    public function test_written_on_includes_both_ends_of_the_day(): void {
        $this->assertSame('2026-10-18', written_on::day(1792281600, 'UTC')); // 2026-10-18 00:00:00 UTC.
        $this->assertSame('2026-10-18', written_on::day(1792367999, 'UTC')); // 2026-10-18 23:59:59 UTC.
    }

    /**
     * Written-on and session days with the days late each pair gives.
     *
     * @return array Cases: written-on day, session day, expected days late.
     */
    public static function days_late_provider(): array {
        return [
            'written the same day' => ['2026-10-18', '2026-10-18', 0],
            'written the next day' => ['2026-10-19', '2026-10-18', 1],
            'written in advance' => ['2026-10-16', '2026-10-18', 0],
            'across the end of February' => ['2026-03-02', '2026-02-27', 3],
            'across the end of February in a leap year' => ['2028-03-02', '2028-02-27', 4],
            'across the March clock change' => ['2026-03-30', '2026-03-28', 2],
            'across the new year' => ['2027-01-08', '2026-12-21', 18],
        ];
    }

    /**
     * An entry written after its session is late by the difference in days; written on or before it, not late.
     *
     * @dataProvider days_late_provider
     * @covers \local_classprogress\local\written_on
     * @group REQ-LOG-004
     * @param string $writtenon The written-on day.
     * @param string $sessiondate The session day.
     * @param int $expected Days late.
     */
    public function test_days_late_counts_days_after_the_session(string $writtenon, string $sessiondate, int $expected): void {
        $this->assertSame($expected, written_on::days_late($writtenon, $sessiondate));
    }
}
