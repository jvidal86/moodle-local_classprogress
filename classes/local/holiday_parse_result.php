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
 * What {@see holiday_parser::parse()} found: the valid entries and the rejected lines, both in line order.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class holiday_parse_result {
    /**
     * Constructor.
     *
     * @param holiday_entry[] $entries Valid entries, as a list in line order.
     * @param holiday_error[] $errors Rejected lines, as a list in line order.
     */
    public function __construct(
        /** @var holiday_entry[] Valid entries, as a list in line order. */
        public readonly array $entries,
        /** @var holiday_error[] Rejected lines, as a list in line order. */
        public readonly array $errors,
    ) {
    }
}
