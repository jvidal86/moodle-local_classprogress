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
 * One row of a summary table: a group of sessions and its figures.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class summary_group {
    /**
     * Creates the group.
     *
     * @param string $kind One of the export_summary::GROUP_ codes.
     * @param string $name The block as typed for GROUP_BLOCK, the period's name for GROUP_PERIOD; empty otherwise.
     * @param summary_figures $figures The group's figures.
     */
    public function __construct(
        /** @var string One of the export_summary::GROUP_ codes. */
        public readonly string $kind,
        /** @var string The block as typed for GROUP_BLOCK, the period's name for GROUP_PERIOD; empty otherwise. */
        public readonly string $name,
        /** @var summary_figures The group's figures. */
        public readonly summary_figures $figures,
    ) {
    }
}
