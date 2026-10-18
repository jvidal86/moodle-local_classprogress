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
 * The class calendar of a plan: the sessions from the first to the last class day on timetable weekdays, minus holidays.
 *
 * Pure logic: no Moodle API, no clock. Dates are ISO yyyy-mm-dd strings, handled as UTC days and compared as day
 * numbers (see dates).
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar {
    /** @var string Result code: the first or the last class day is not set. */
    public const MISSING_DATES = 'missing_dates';

    /** @var string Result code: the first or the last class day is not an existing day written yyyy-mm-dd. */
    public const INVALID_DATE = 'invalid_date';

    /** @var string Result code: the first or the last class day lies outside dates::FIRST_DAY to dates::LAST_DAY. */
    public const OUT_OF_RANGE = 'out_of_range';

    /** @var string Result code: the last class day is before the first. */
    public const LAST_BEFORE_FIRST = 'last_before_first';

    /** @var string Result code: the class days span more than dates::MAX_DAYS days. */
    public const TOO_LONG = 'too_long';

    /**
     * Computes the sessions of a plan.
     *
     * Missing, invalid, out-of-range, reversed and too long ranges are checked in that order and give an empty result
     * with the matching code, so no input can make the loop over the days run long.
     *
     * @param string|null $first First class day, ISO yyyy-mm-dd.
     * @param string|null $last Last class day, ISO yyyy-mm-dd.
     * @param float[] $timetable Weekday => hours, Monday = 0 to Sunday = 6; missing days and hours <= 0 mean no class.
     * @param holiday_entry[] $holidays Holiday entries; when they overlap, the first one owns the day.
     * @return calendar_result The sessions and totals, or an empty result with a code.
     */
    public static function compute(?string $first, ?string $last, array $timetable, array $holidays): calendar_result {
        if ($first === null || $first === '' || $last === null || $last === '') {
            return new calendar_result(self::MISSING_DATES, [], 0.0, [], []);
        }
        if (!dates::is_date($first) || !dates::is_date($last)) {
            return new calendar_result(self::INVALID_DATE, [], 0.0, [], []);
        }
        $code = match (dates::check_range($first, $last)) {
            dates::OUT_OF_RANGE => self::OUT_OF_RANGE,
            dates::REVERSED => self::LAST_BEFORE_FIRST,
            dates::TOO_LONG => self::TOO_LONG,
            null => null,
        };
        if ($code !== null) {
            return new calendar_result($code, [], 0.0, [], []);
        }
        $ranges = [];
        foreach ($holidays as $key => $entry) {
            if (dates::is_date($entry->start) && dates::is_date($entry->end)) {
                $ranges[$key] = [dates::day_number($entry->start), dates::day_number($entry->end)];
            }
        }
        $sessions = [];
        $totalhours = 0.0;
        $permonth = [];
        $lost = array_fill_keys(array_keys($holidays), 0);
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $first, new \DateTimeZone('UTC'));
        $lastday = dates::day_number($last);
        for ($number = dates::day_number($first); $number <= $lastday; $number++) {
            $iso = $day->format('Y-m-d');
            $month = substr($iso, 0, 7);
            $permonth[$month] ??= 0;
            $hours = (float) ($timetable[(int) $day->format('N') - 1] ?? 0);
            if ($hours > 0) {
                $holiday = self::holiday_of($number, $ranges);
                if ($holiday !== null) {
                    $lost[$holiday]++;
                } else {
                    $sessions[] = new calendar_session($iso, $hours);
                    $totalhours += $hours;
                    $permonth[$month]++;
                }
            }
            $day = $day->modify('+1 day');
        }
        return new calendar_result(null, $sessions, $totalhours, $permonth, $lost);
    }

    /**
     * Finds the holiday entry that owns a day.
     *
     * @param int $day The day number (see dates::day_number()).
     * @param int[][] $ranges Key in the holiday array => [first, last] day numbers.
     * @return int|string|null Key in $holidays of the first entry covering the day; null if it is not a holiday.
     */
    private static function holiday_of(int $day, array $ranges): int|string|null {
        foreach ($ranges as $key => [$start, $end]) {
            if ($start <= $day && $day <= $end) {
                return $key;
            }
        }
        return null;
    }
}
