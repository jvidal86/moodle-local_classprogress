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
 * A session as the export summary needs it: a planned day or an unplanned session, with its state, block, planned
 * hours and how late its entry was written.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class export_session {
    /**
     * Creates the session.
     *
     * @param string $date The session day, an existing yyyy-mm-dd day.
     * @param string $state One of the five export_summary states for a planned day; export_summary::ADDED for an
     *     unplanned session.
     * @param string|null $block The planned day's RA or block as typed, empty for none; null for an unplanned session.
     * @param float|null $hours Planned hours; null for an unplanned session or a day without hours.
     * @param int|null $dayslate Days late of the entry (see written_on::days_late()); null without an entry.
     */
    public function __construct(
        /** @var string The session day, an existing yyyy-mm-dd day. */
        public readonly string $date,
        /** @var string One of the five states of a planned day, or export_summary::ADDED. */
        public readonly string $state,
        /** @var string|null The planned day's RA or block as typed, empty for none; null for an unplanned session. */
        public readonly ?string $block = null,
        /** @var float|null Planned hours; null for an unplanned session or a day without hours. */
        public readonly ?float $hours = null,
        /** @var int|null Days late of the entry; null without an entry. */
        public readonly ?int $dayslate = null,
    ) {
    }
}
