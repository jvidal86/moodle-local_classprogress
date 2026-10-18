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
 * The written-on day of an entry and how many days late it is.
 *
 * The caller passes the moment and the teacher's time zone, so this reads no clock. Pure logic: no Moodle API.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class written_on {
    /**
     * Gives the day a moment falls on in a time zone.
     *
     * @param int $timestamp The moment, Unix time.
     * @param string $timezone A valid PHP time-zone identifier, such as core_date::get_user_timezone() returns.
     * @return string The day, yyyy-mm-dd.
     */
    public static function day(int $timestamp, string $timezone): string {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d');
    }

    /**
     * Counts the days an entry was written after its session.
     *
     * @param string $writtenon The written-on day, an existing yyyy-mm-dd day.
     * @param string $sessiondate The session day, an existing yyyy-mm-dd day.
     * @return int Days late; 0 when written on or before the session day.
     */
    public static function days_late(string $writtenon, string $sessiondate): int {
        return max(0, dates::day_number($writtenon) - dates::day_number($sessiondate));
    }
}
