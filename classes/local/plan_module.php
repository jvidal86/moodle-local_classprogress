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
 * The module of a plan, read from its # heading.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_module {
    /**
     * Creates the module.
     *
     * @param string|null $code Module code such as "MP 0372", or null when the heading has none.
     * @param string $title Module title.
     * @param int $line Line of the heading, from 1.
     */
    public function __construct(
        /** @var string|null Module code such as "MP 0372", or null when the heading has none. */
        public readonly ?string $code,
        /** @var string Module title. */
        public readonly string $title,
        /** @var int Line of the heading, from 1. */
        public readonly int $line,
    ) {
    }
}
