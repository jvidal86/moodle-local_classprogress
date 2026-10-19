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

namespace local_classprogress;

/**
 * Tests for the database schema: install, upgrade and uninstall. The plan tables moved to mod_classprogress.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class schema_test extends \advanced_testcase {
    /** @var string Prefix of every table of the plugin. */
    private const PREFIX = 'local_classprogress_';

    /** @var string[] The tables of the data model in PLAN.md section 3b, without the prefix. */
    private const TABLES = ['schoolyear', 'holiday', 'evaluation'];

    /** @var string[] The tables that moved to mod_classprogress in version 2026101806, without the prefix. */
    private const MOVED = ['entry_hist', 'entry', 'planitem', 'slot', 'import', 'plan'];

    /**
     * Reads the plugin's install.xml.
     *
     * @return \xmldb_structure The tables declared in db/install.xml.
     */
    private static function install_xml(): \xmldb_structure {
        global $DB;

        $DB->get_manager(); // Loads the XMLDB classes.
        $file = new \xmldb_file(\core_component::get_component_directory('local_classprogress') . '/db/install.xml');
        if (!$file->fileExists() || !$file->loadXMLStructure() || !$file->isLoaded()) {
            throw new \coding_exception('db/install.xml cannot be read');
        }
        return $file->getStructure();
    }

    /**
     * The columns of every table of the data model.
     *
     * @return array Table name without prefix => [table name, column names].
     */
    public static function key_columns(): array {
        $times = ['timecreated', 'timemodified'];
        return [
            'schoolyear' => ['schoolyear', ['id', 'userid', 'name', 'startdate', 'enddate', ...$times]],
            'holiday' => ['holiday', ['id', 'schoolyearid', 'startdate', 'enddate', 'label', ...$times]],
            'evaluation' => ['evaluation', ['id', 'schoolyearid', 'name', 'startdate', 'enddate', ...$times]],
        ];
    }

    /**
     * Installing creates each table of the data model with exactly these columns.
     *
     * @dataProvider key_columns
     * @group REQ-DAT-001
     * @param string $table Table name without the prefix.
     * @param string[] $columns Every column the table must have, and no other.
     */
    public function test_table_exists_with_its_key_columns(string $table, array $columns): void {
        global $DB;
        $this->resetAfterTest();

        $this->assertTrue($DB->get_manager()->table_exists(self::PREFIX . $table), "Table $table is missing");
        $this->assertEqualsCanonicalizing($columns, array_keys($DB->get_columns(self::PREFIX . $table)));
    }

    /**
     * install.xml declares exactly the tables of the data model.
     *
     * @group REQ-DAT-001
     */
    public function test_install_xml_declares_every_table_of_the_data_model(): void {
        $this->resetAfterTest();

        $declared = array_map(fn(\xmldb_table $t): string => $t->getName(), self::install_xml()->getTables());

        $this->assertEqualsCanonicalizing(array_map(fn(string $t): string => self::PREFIX . $t, self::TABLES), $declared);
    }

    /**
     * The installed tables match install.xml in every column type, size, null rule, default, key and index.
     *
     * @group REQ-DAT-001
     */
    public function test_installed_schema_matches_install_xml(): void {
        global $DB;
        $this->resetAfterTest();

        $errors = $DB->get_manager()->check_database_schema(self::install_xml(), ['extratables' => false]);

        $this->assertSame([], $errors);
    }

    /**
     * Every plugin table in the database is declared in install.xml, so uninstalling (which drops the tables named
     * there) leaves no local_classprogress_* table behind.
     *
     * @group REQ-DAT-001
     */
    public function test_every_plugin_table_in_the_database_is_dropped_by_uninstall(): void {
        global $DB;
        $this->resetAfterTest();

        $declared = array_map(fn(\xmldb_table $t): string => $t->getName(), self::install_xml()->getTables());
        $indb = array_filter($DB->get_tables(false), fn(string $t): bool => str_starts_with($t, self::PREFIX));

        $this->assertNotEmpty($indb);
        $this->assertEqualsCanonicalizing($declared, array_values($indb));
    }

    /**
     * The upgrade function exists and succeeds on the installed version.
     *
     * @group REQ-DAT-001
     */
    public function test_upgrade_function_succeeds(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once(\core_component::get_component_directory('local_classprogress') . '/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');

        $this->assertTrue(xmldb_local_classprogress_upgrade((int) get_config('local_classprogress', 'version')));
    }

    /**
     * Upgrading from the M0 skeleton (2026101800), which had no tables, creates every missing table of install.xml,
     * leaves the existing ones as they are and reaches the current version.
     *
     * @group REQ-DAT-001
     * @group REQ-DAT-005
     */
    public function test_upgrade_from_m0_creates_every_missing_table(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        // Changes of the schema are not undone by a rollback on MariaDB, so the test does not run in a transaction.
        $this->preventResetByRollback();
        require_once(\core_component::get_component_directory('local_classprogress') . '/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        $dbman = $DB->get_manager();
        $plugin = new \stdClass();
        require(\core_component::get_component_directory('local_classprogress') . '/version.php');
        $missing = [self::PREFIX . 'holiday', self::PREFIX . 'evaluation'];
        $this->getDataGenerator()->get_plugin_generator('local_classprogress')->create_schoolyear();

        try {
            foreach ($missing as $table) {
                $dbman->drop_table(new \xmldb_table($table));
            }
            set_config('version', 2026101800, 'local_classprogress');

            $this->assertTrue(xmldb_local_classprogress_upgrade(2026101800));
            $created = array_values(array_filter($missing, fn(string $table): bool => $dbman->table_exists($table)));
        } finally {
            // Leaves the schema whole for the other tests, whatever happened above.
            foreach ($missing as $table) {
                if (!$dbman->table_exists($table)) {
                    $dbman->install_one_table_from_xmldb_file(
                        \core_component::get_component_directory('local_classprogress') . '/db/install.xml',
                        $table
                    );
                }
            }
        }

        $this->assertSame($missing, $created);
        $this->assertSame([], $dbman->check_database_schema(self::install_xml(), ['extratables' => false]));
        $this->assertSame(1, $DB->count_records(self::PREFIX . 'schoolyear'));
        $this->assertSame((string) $plugin->version, (string) get_config('local_classprogress', 'version'));
    }

    /**
     * Upgrading from the version before the course activity (2026101805) drops the plan tables that moved to
     * mod_classprogress and the import limits that became its settings, keeps the school years and reaches the current
     * version.
     *
     * @group REQ-DAT-001
     * @group REQ-DAT-005
     */
    public function test_upgrade_drops_the_tables_that_moved_to_the_activity(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        require_once(\core_component::get_component_directory('local_classprogress') . '/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        $dbman = $DB->get_manager();
        $plugin = new \stdClass();
        require(\core_component::get_component_directory('local_classprogress') . '/version.php');
        foreach (self::MOVED as $name) {
            $table = new \xmldb_table(self::PREFIX . $name);
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }
        set_config('maxplansize', 100, 'local_classprogress');
        set_config('version', 2026101805, 'local_classprogress');
        $this->getDataGenerator()->get_plugin_generator('local_classprogress')->create_schoolyear();

        try {
            $this->assertTrue(xmldb_local_classprogress_upgrade(2026101805));
        } finally {
            foreach (self::MOVED as $name) {
                if ($dbman->table_exists(self::PREFIX . $name)) {
                    $dbman->drop_table(new \xmldb_table(self::PREFIX . $name));
                    $this->fail("Table {$name} was not dropped");
                }
            }
        }

        $this->assertFalse(get_config('local_classprogress', 'maxplansize'));
        $this->assertSame((string) $plugin->version, (string) get_config('local_classprogress', 'version'));
        $this->assertSame([], $dbman->check_database_schema(self::install_xml(), ['extratables' => false]));
        $this->assertSame(1, $DB->count_records(self::PREFIX . 'schoolyear'));
    }
}
