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
 * A plan item as the session list needs it: id, day and position in the plan text.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_item {
    /**
     * Creates the item.
     *
     * @param int $id The plan item id.
     * @param string $date The planned day, an existing yyyy-mm-dd day.
     * @param int $sortorder Position in the plan text, from 1.
     */
    public function __construct(
        /** @var int The plan item id. */
        public readonly int $id,
        /** @var string The planned day, an existing yyyy-mm-dd day. */
        public readonly string $date,
        /** @var int Position in the plan text, from 1. */
        public readonly int $sortorder,
    ) {
    }
}
