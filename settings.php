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
 * Admin settings: the largest plan text and the most days a plan can have.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_classprogress\local\plan_service;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_classprogress', get_string('pluginname', 'local_classprogress'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'local_classprogress/maxplansize',
            get_string('maxplansize', 'local_classprogress'),
            get_string('maxplansize_desc', 'local_classprogress'),
            plan_service::DEFAULT_MAX_SIZE,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_classprogress/maxplandays',
            get_string('maxplandays', 'local_classprogress'),
            get_string('maxplandays_desc', 'local_classprogress'),
            plan_service::DEFAULT_MAX_DAYS,
            PARAM_INT
        ));
    }
}
