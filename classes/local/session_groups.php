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
 * The items of a plan sorted into pending, upcoming and logged, each by date and then plan order.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_groups {
    /**
     * Creates the groups.
     *
     * @param session_item[] $pending Items dated today or earlier with no entry, oldest first.
     * @param session_item[] $upcoming Items dated after today with no entry, soonest first.
     * @param session_item[] $logged Items with an entry.
     */
    public function __construct(
        /** @var session_item[] Items dated today or earlier with no entry, oldest first. */
        public readonly array $pending = [],
        /** @var session_item[] Items dated after today with no entry, soonest first. */
        public readonly array $upcoming = [],
        /** @var session_item[] Items with an entry. */
        public readonly array $logged = [],
    ) {
    }
}
