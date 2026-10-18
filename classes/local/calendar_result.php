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
 * What {@see calendar::compute()} found for one plan.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_result {
    /**
     * Constructor.
     *
     * @param string|null $code Null when the dates are valid; otherwise a calendar constant saying why the result is empty.
     * @param calendar_session[] $sessions The sessions in date order, as a list.
     * @param float $totalhours Sum of the hours of all sessions.
     * @param array<string, int> $sessionspermonth yyyy-mm => sessions, for every month from the first to the last class day.
     * @param array<int|string, int> $lost Sessions each holiday entry removed, under the same key as in the holiday array.
     */
    public function __construct(
        /** @var string|null Null when the dates are valid; otherwise a calendar constant saying why the result is empty. */
        public readonly ?string $code,
        /** @var calendar_session[] The sessions in date order, as a list. */
        public readonly array $sessions,
        /** @var float Sum of the hours of all sessions. */
        public readonly float $totalhours,
        /** @var array<string, int> yyyy-mm => sessions, for every month from the first to the last class day. */
        public readonly array $sessionspermonth,
        /** @var array<int|string, int> Sessions each holiday entry removed, under the same key as in the holiday array. */
        public readonly array $lost,
    ) {
    }
}
