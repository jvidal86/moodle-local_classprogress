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

namespace local_classprogress\form;

use local_classprogress\local\dates;

/**
 * The date selector of the forms, and its days as the services keep them.
 *
 * Moodle's date_selector element gives and takes the timestamp of midnight in the user's time zone (0 for an optional
 * one left disabled); the services and the database keep yyyy-mm-dd days. The forms convert here only, always in the
 * user's time zone, so a day never moves by one around a daylight saving change or in a zone far from UTC.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class date_field {
    /**
     * Options for a date_selector whose years are those a teacher may enter (REQ-CAL-004).
     *
     * @param bool $optional True to let the teacher leave the date out.
     * @return array Element options.
     */
    public static function options(bool $optional = false): array {
        return [
            'startyear' => (int) substr(dates::FIRST_DAY, 0, 4),
            'stopyear' => (int) substr(dates::LAST_DAY, 0, 4),
            'optional' => $optional,
        ];
    }

    /**
     * Turns a date_selector timestamp into the day it shows to the user.
     *
     * @param int $timestamp Timestamp from the element; 0 for no date.
     * @return string The day, yyyy-mm-dd; empty for no date.
     */
    public static function to_day(int $timestamp): string {
        if ($timestamp === 0) {
            return '';
        }
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(\core_date::get_user_timezone_object())->format('Y-m-d');
    }

    /**
     * Turns a stored day into the timestamp a date_selector shows as that day.
     *
     * @param string|null $day The day, yyyy-mm-dd; empty or null for no date.
     * @return int Timestamp of the start of the day in the user's time zone; 0 for no date.
     */
    public static function to_timestamp(?string $day): int {
        if ($day === null || !dates::is_date($day)) {
            return 0;
        }
        return \DateTimeImmutable::createFromFormat('!Y-m-d', $day, \core_date::get_user_timezone_object())->getTimestamp();
    }
}
