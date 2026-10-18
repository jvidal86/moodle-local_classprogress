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
 * One valid holiday line: a single day or a period, with its label.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class holiday_entry {
    /**
     * Constructor.
     *
     * @param int $line Line number in the holiday text, starting at 1.
     * @param string $start First day, ISO yyyy-mm-dd.
     * @param string $end Last day, ISO yyyy-mm-dd, never before $start (equal for a single day).
     * @param string $label Label after the dates, trimmed; empty when there is none.
     */
    public function __construct(
        /** @var int Line number in the holiday text, starting at 1. */
        public readonly int $line,
        /** @var string First day, ISO yyyy-mm-dd. */
        public readonly string $start,
        /** @var string Last day, ISO yyyy-mm-dd. */
        public readonly string $end,
        /** @var string Label after the dates; empty when there is none. */
        public readonly string $label,
    ) {
    }
}
