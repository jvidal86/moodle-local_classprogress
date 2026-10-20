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
 * The README and CHANGES of the plugin (REQ-REL-004): the README's sections in their order, and the changes starting
 * with the release of version.php.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class release_docs_test extends \advanced_testcase {
    /** @var string The plugin's component name. */
    private const COMPONENT = 'local_classprogress';

    /** @var string[] The README's sections, in order. */
    private const SECTIONS = ['Requirements', 'Install', 'Languages', 'Settings and limits', 'Uninstall', 'Privacy',
        'Known limits'];

    /** @var string[] Sections that may follow, in order. */
    private const OPTIONAL_SECTIONS = ['Development', 'License'];

    /**
     * Reads a file of the plugin folder.
     *
     * @param string $name File name.
     * @return string Its text.
     */
    private function read(string $name): string {
        $file = \core_component::get_component_directory(self::COMPONENT) . '/' . $name;
        $this->assertFileExists($file);
        return file_get_contents($file);
    }

    /**
     * The README says what the plugin does before its first section, then has the agreed sections in order.
     *
     * @group REQ-REL-004
     */
    public function test_the_readme_has_the_agreed_sections_in_order(): void {
        $readme = $this->read('README.md');

        preg_match_all('/^## (.+?)\s*$/m', $readme, $headings);
        $this->assertSame(self::SECTIONS, array_slice($headings[1], 0, count(self::SECTIONS)));
        $rest = array_slice($headings[1], count(self::SECTIONS));
        $this->assertSame($rest, array_values(array_intersect(self::OPTIONAL_SECTIONS, $rest)));

        $intro = preg_replace('/^# .*$/m', '', strstr($readme, "\n## ", true));
        $this->assertNotSame('', trim($intro), 'What the plugin does, before the first section');
    }

    /**
     * CHANGES lists the releases newest first, and its first entry is the release of version.php.
     *
     * @group REQ-REL-004
     */
    public function test_changes_starts_with_the_release_of_version_php(): void {
        $plugin = new \stdClass();
        require(\core_component::get_component_directory(self::COMPONENT) . '/version.php');
        $changes = $this->read('CHANGES.md');

        preg_match_all('/^## (\S+)(?: .*)?$/m', $changes, $entries);
        $this->assertNotEmpty($entries[1]);
        $this->assertSame($plugin->release, $entries[1][0]);
        $sorted = $entries[1];
        usort($sorted, fn(string $a, string $b): int => version_compare($b, $a));
        $this->assertSame($sorted, $entries[1]);
    }
}
