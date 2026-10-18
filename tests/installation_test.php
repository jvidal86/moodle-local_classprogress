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
 * Checks that the plugin is found by Moodle and installed at the version on disk.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class installation_test extends \advanced_testcase {
    /**
     * The component resolves to the plugin folder, in both the 4.5 and the 5.x layout.
     */
    public function test_component_directory_exists(): void {
        $dir = \core_component::get_component_directory('local_classprogress');

        $this->assertIsString($dir);
        $this->assertDirectoryExists($dir);
        $this->assertStringEndsWith('/local/classprogress', $dir);
    }

    /**
     * The version recorded in the database equals the version in version.php.
     */
    public function test_installed_version_matches_version_file(): void {
        $info = \core_plugin_manager::instance()->get_plugin_info('local_classprogress');

        $this->assertNotNull($info);
        $this->assertNotEmpty($info->versiondisk);
        $this->assertNotNull($info->versiondb);
        $this->assertSame((int) $info->versiondisk, (int) $info->versiondb);
    }
}
