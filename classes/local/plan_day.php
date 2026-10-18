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
 * One day of a plan, read from a ### heading and the Markdown under it.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_day {
    /**
     * Creates the day.
     *
     * @param string $date Date, yyyy-mm-dd.
     * @param float|null $hours Hours from the heading or else the timetable; null when neither gives them.
     * @param string $title Title after the date and hours; may be empty.
     * @param string|null $block Text of the ## heading above the day, or null when there is none.
     * @param int $line Line of the heading, from 1.
     * @param string $content The day's plan as Markdown, trimmed; may be empty.
     */
    public function __construct(
        public readonly string $date,
        public readonly ?float $hours,
        public readonly string $title,
        public readonly ?string $block,
        public readonly int $line,
        public readonly string $content,
    ) {
    }
}
