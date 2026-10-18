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

/**
 * Behat data generator for the class progress plugin.
 *
 * @package    local_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates school years, holidays, plans and timetable slots for Behat scenarios, through the plugin's data generator.
 *
 * Entities: "local_classprogress > school years" (user, name), "holidays" (schoolyear by name, startdate),
 * "plans" (schoolyear by name, modulecode; a draft that was never imported, unless sourcetext is given) and "slots"
 * (plan by module code, weekday by English name or number with Monday = 0, hours).
 *
 * @package    local_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_classprogress_generator extends behat_generator_base {
    /** @var string[] English weekday names, Monday = 0 to Sunday = 6. */
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * The entities that scenarios can create.
     *
     * @return array Entity name => how to create it.
     */
    #[\Override]
    protected function get_creatable_entities(): array {
        return [
            'school years' => [
                'singular' => 'school year',
                'datagenerator' => 'schoolyear',
                'required' => ['user', 'name'],
                'switchids' => ['user' => 'userid'],
            ],
            'holidays' => [
                'singular' => 'holiday',
                'datagenerator' => 'holiday',
                'required' => ['schoolyear', 'startdate'],
                'switchids' => ['schoolyear' => 'schoolyearid'],
            ],
            'plans' => [
                'singular' => 'plan',
                'datagenerator' => 'plan',
                'required' => ['schoolyear', 'modulecode'],
                'switchids' => ['schoolyear' => 'schoolyearid'],
            ],
            'slots' => [
                'singular' => 'slot',
                'datagenerator' => 'slot',
                'required' => ['plan', 'weekday', 'hours'],
                'switchids' => ['plan' => 'planid'],
            ],
        ];
    }

    /**
     * Finds a school year by name.
     *
     * @param string $name Its name.
     * @return int Its id.
     */
    protected function get_schoolyear_id(string $name): int {
        global $DB;

        return (int) $DB->get_field('local_classprogress_schoolyear', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * Finds a plan by module code.
     *
     * @param string $code Its module code.
     * @return int Its id.
     */
    protected function get_plan_id(string $code): int {
        global $DB;

        return (int) $DB->get_field('local_classprogress_plan', 'id', ['modulecode' => $code], MUST_EXIST);
    }

    /**
     * A plan from a scenario has no text until it is imported.
     *
     * @param array $data Plan fields.
     * @return array The fields.
     */
    protected function preprocess_plan(array $data): array {
        return $data + ['sourcetext' => null];
    }

    /**
     * Reads the weekday of a slot as a name or a number.
     *
     * @param array $data Slot fields.
     * @return array The fields, with the weekday as a number.
     */
    protected function preprocess_slot(array $data): array {
        $name = array_search(strtolower($data['weekday']), self::WEEKDAYS, true);
        $data['weekday'] = $name === false ? (int) $data['weekday'] : $name;
        return $data;
    }
}
