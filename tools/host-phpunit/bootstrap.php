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
 * Host-side bootstrap: runs the plugin's database-free tests (those extending \basic_testcase) without Moodle.
 *
 * Development tool only, excluded from the release by .gitattributes. In Moodle the same test files run with
 * Moodle's own \basic_testcase; here a minimal stand-in is defined, so production code under test must not use
 * any Moodle function or global.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('MOODLE_INTERNAL', true);

require_once(__DIR__ . '/vendor/autoload.php');

// The plugin root is two levels up from this file, so this also works inside a git worktree.
$pluginroot = dirname(__DIR__, 2);

spl_autoload_register(function (string $class) use ($pluginroot): void {
    $prefix = 'local_classprogress\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = $pluginroot . '/classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once($file);
    }
});

if (!class_exists('basic_testcase')) {
    /**
     * Stand-in for Moodle's \basic_testcase (lib/phpunit/classes/basic_testcase.php) on the host.
     */
    abstract class basic_testcase extends \PHPUnit\Framework\TestCase {
    }
}
