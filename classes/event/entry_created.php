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

namespace local_classprogress\event;

/**
 * A new entry was written.
 *
 * Other holds the ids described in entry_event.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_created extends entry_event {
    /** @var string Writing an entry creates it. */
    protected const CRUD = 'c';

    /**
     * The event's name.
     *
     * @return string Localised name.
     */
    public static function get_name(): string {
        return get_string('evententrycreated', 'local_classprogress');
    }

    /**
     * Describes the event with ids only.
     *
     * @return string Non-localised description.
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' created the entry with id '{$this->objectid}' " .
            "in the plan with id '{$this->other['planid']}'.";
    }
}
