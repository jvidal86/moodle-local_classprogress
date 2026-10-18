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
 * A plan's Markdown was imported into a draft plan.
 *
 * Triggered after the import transaction commits, with the plan as object, in the owner's user context.
 *
 * @property-read array $other {
 *      Ids only.
 *
 *      - int schoolyearid: the plan's school year.
 *      - int importid: the row of the import log.
 * }
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_imported extends base {
    /** @var string An import creates the draft plan's items. */
    protected const CRUD = 'c';

    /** @var string Table of the event's object. */
    protected const OBJECTTABLE = 'local_classprogress_plan';

    /**
     * The ids that other must hold.
     *
     * @return array Key of other => [table the id points to, whether it may be null].
     */
    #[\Override]
    protected static function other_ids(): array {
        return [
            'schoolyearid' => ['local_classprogress_schoolyear', false],
            'importid' => ['local_classprogress_import', false],
        ];
    }

    /**
     * The event's name.
     *
     * @return string Localised name.
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('eventplanimported', 'local_classprogress');
    }

    /**
     * Describes the event with ids only.
     *
     * @return string Non-localised description.
     */
    #[\Override]
    public function get_description(): string {
        return "The user with id '{$this->userid}' imported the plan with id '{$this->objectid}' " .
            "(import with id '{$this->other['importid']}').";
    }

    /**
     * The plan's page.
     *
     * @return \core\url The URL of the plan.
     */
    #[\Override]
    public function get_url(): \core\url {
        return new \core\url('/local/classprogress/plan.php', ['id' => $this->objectid]);
    }
}
