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
 * One class session: a date and the hours the timetable gives that weekday.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_session {
    /**
     * Constructor.
     *
     * @param string $date The day, ISO yyyy-mm-dd.
     * @param float $hours Hours of class that day, above zero.
     */
    public function __construct(
        /** @var string The day, ISO yyyy-mm-dd. */
        public readonly string $date,
        /** @var float Hours of class that day, above zero. */
        public readonly float $hours,
    ) {
    }
}
