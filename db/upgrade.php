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
 * Upgrade steps for the class progress plugin.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs the upgrade steps from the installed version to the one in version.php.
 *
 * Every step checks with table_exists or field_exists before changing anything and ends with
 * upgrade_plugin_savepoint(true, <version>, 'local', 'classprogress'), where <version> is the new version.php value.
 *
 * @param int $oldversion The version installed before this upgrade.
 * @return bool True when every step succeeded.
 */
function xmldb_local_classprogress_upgrade(int $oldversion): bool {
    return true;
}
