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
 * Creates school years and holidays for Behat scenarios, through the plugin's data generator.
 *
 * Entities: "local_classprogress > school years" (user, name) and "holidays" (schoolyear by name, startdate). Plan
 * activities are made by mod_classprogress's generator.
 *
 * @package    local_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_classprogress_generator extends behat_generator_base {
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
}
