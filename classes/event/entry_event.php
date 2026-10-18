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
 * Base of the events about an entry (what was done in a session).
 *
 * Triggered after the transaction that writes the entry and its history row commits, with the entry as object, in
 * the owner's user context.
 *
 * @property-read array $other {
 *      Ids only.
 *
 *      - int planid: the entry's plan.
 *      - int|null planitemid: the planned day; null for an unplanned session.
 * }
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class entry_event extends base {
    /** @var string Table of the event's object. */
    protected const OBJECTTABLE = 'local_classprogress_entry';

    /**
     * The ids that other must hold.
     *
     * @return array Key of other => [table the id points to, whether it may be null].
     */
    protected static function other_ids(): array {
        return [
            'planid' => ['local_classprogress_plan', false],
            'planitemid' => ['local_classprogress_planitem', true],
        ];
    }

    /**
     * The log page of the entry's plan, which still exists after the entry is deleted.
     *
     * @return \core\url The URL of the plan's log.
     */
    public function get_url(): \core\url {
        return new \core\url('/local/classprogress/log.php', ['id' => $this->other['planid']]);
    }
}
