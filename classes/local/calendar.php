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
 * Pure logic: no Moodle API, no clock. Dates are ISO yyyy-mm-dd strings, handled as UTC days.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar {
    /**
     * Computes the sessions of a plan.
     *
     * @param string|null $first First class day, ISO yyyy-mm-dd.
     * @param string|null $last Last class day, ISO yyyy-mm-dd.
     * @param array<int, float> $timetable Weekday => hours, Monday = 0 to Sunday = 6; missing days and hours <= 0 mean no class.
     * @param holiday_entry[] $holidays Holiday entries; when they overlap, the first one owns the day.
     * @return calendar_result The sessions.
     */
    public static function compute(?string $first, ?string $last, array $timetable, array $holidays): calendar_result {
        $sessions = [];
        $utc = new \DateTimeZone('UTC');
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $first, $utc);
        for ($iso = $first; $iso <= $last; $iso = $day->format('Y-m-d')) {
            $hours = (float) ($timetable[(int) $day->format('N') - 1] ?? 0);
            if ($hours > 0 && self::holiday_of($iso, $holidays) === null) {
                $sessions[] = new calendar_session($iso, $hours);
            }
            $day = $day->modify('+1 day');
        }
        return new calendar_result(null, $sessions);
    }

    /**
     * Finds the holiday entry that owns a day.
     *
     * @param string $iso The day, ISO yyyy-mm-dd.
     * @param holiday_entry[] $holidays Holiday entries.
     * @return int|string|null Key in $holidays of the first entry covering the day; null if it is not a holiday.
     */
    private static function holiday_of(string $iso, array $holidays): int|string|null {
        foreach ($holidays as $key => $entry) {
            if ($entry->start <= $iso && $iso <= $entry->end) {
                return $key;
            }
        }
        return null;
    }
}
