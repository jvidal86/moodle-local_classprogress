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
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026101805) {
        // The M0 skeleton (2026101800) had no tables, and the versions that added them had no step: create every
        // table of install.xml that is missing.
        $file = __DIR__ . '/install.xml';
        $xmldbfile = new xmldb_file($file);
        if (!$xmldbfile->fileExists() || !$xmldbfile->loadXMLStructure()) {
            throw new coding_exception('db/install.xml cannot be read');
        }
        foreach ($xmldbfile->getStructure()->getTables() as $table) {
            if (!$dbman->table_exists($table->getName())) {
                $dbman->install_one_table_from_xmldb_file($file, $table->getName());
            }
        }
        upgrade_plugin_savepoint(true, 2026101805, 'local', 'classprogress');
    }

    if ($oldversion < 2026101806) {
        // Plans, their days, entries, history and imports moved to the course activity mod_classprogress (decision 12).
        // Nothing had been released, so their tables go without a copy, children first, and so do the import limits,
        // now settings of the activity.
        $moved = ['entry_hist', 'entry', 'planitem', 'slot', 'import', 'plan'];
        foreach ($moved as $name) {
            $table = new xmldb_table('local_classprogress_' . $name);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }
        unset_config('maxplansize', 'local_classprogress');
        unset_config('maxplandays', 'local_classprogress');
        upgrade_plugin_savepoint(true, 2026101806, 'local', 'classprogress');
    }

    if ($oldversion < 2026101900) {
        // The export library (M4) changes no table; the savepoint keeps the upgrade ending at the current version.
        upgrade_plugin_savepoint(true, 2026101900, 'local', 'classprogress');
    }

    return true;
}
