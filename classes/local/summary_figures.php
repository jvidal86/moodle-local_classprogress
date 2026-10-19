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
 * The summary figures of a set of sessions (REQ-EXP-005), as export_summary::figures() gives them.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class summary_figures {
    /**
     * Creates the figures.
     *
     * @param int $planned Planned days.
     * @param int $done Done days.
     * @param int $partial Partly done days.
     * @param int $notdone Not done days.
     * @param int $notlogged Days up to today without an entry.
     * @param int $upcoming Days after today without an entry.
     * @param int $added Unplanned sessions.
     * @param int|null $coverage Whole percentage of the days due that were covered; null when none is due.
     * @param float $plannedhours Planned hours, exact, with up to three decimals.
     * @param float $hoursdone Hours done, exact, with up to three decimals.
     * @param float $hoursnotdone Hours not done, exact, with up to three decimals.
     * @param int $writtenlate Entries written after their session day.
     * @param int $mostdayslate The most days late of an entry; 0 when none was late.
     */
    public function __construct(
        /** @var int Planned days. */
        public readonly int $planned,
        /** @var int Done days. */
        public readonly int $done,
        /** @var int Partly done days. */
        public readonly int $partial,
        /** @var int Not done days. */
        public readonly int $notdone,
        /** @var int Days up to today without an entry. */
        public readonly int $notlogged,
        /** @var int Days after today without an entry. */
        public readonly int $upcoming,
        /** @var int Unplanned sessions. */
        public readonly int $added,
        /** @var int|null Whole percentage of the days due that were covered; null when none is due. */
        public readonly ?int $coverage,
        /** @var float Planned hours, exact, with up to three decimals. */
        public readonly float $plannedhours,
        /** @var float Hours done, exact, with up to three decimals. */
        public readonly float $hoursdone,
        /** @var float Hours not done, exact, with up to three decimals. */
        public readonly float $hoursnotdone,
        /** @var int Entries written after their session day. */
        public readonly int $writtenlate,
        /** @var int The most days late of an entry; 0 when none was late. */
        public readonly int $mostdayslate,
    ) {
    }
}
