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
 * The release zip (REQ-REL-002): what `git archive --prefix=classprogress/` of the plugin holds, and Moodle's own
 * package validator accepting it.
 *
 * The test copies of the plugin have no .git (CI copies the folder), so the zip is built the way git archive would
 * build it: every file of the plugin folder except .git, the paths marked export-ignore in .gitattributes and the
 * paths .gitignore ignores, under one root folder classprogress/. It is then unpacked and checked the way the
 * "Install plugins" page does (core_plugin_manager::unzip_plugin_file() and \core\update\validator). The real zip
 * of git archive is checked by the release workflow and installed by hand in review R5.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class release_package_test extends \advanced_testcase {
    /** @var string The plugin's component name. */
    private const COMPONENT = 'local_classprogress';

    /** @var string The root folder of the zip. */
    private const ROOT = 'classprogress';

    /** @var string[] Files and folders the zip must hold, relative to its root. */
    private const REQUIRED = ['README.md', 'CHANGES.md', 'version.php', 'lang/en/', 'lang/ca/', 'lang/es/', 'tests/'];

    /** @var string[] Development material the zip must not hold, relative to its root. */
    private const LEFT_OUT = ['PLAN.md', 'docs/', 'prototype/', 'test-data/', 'tools/', '.github/', '.gitattributes',
        '.gitignore', '.moodle-plugin-ci.yml'];

    /**
     * The zip holds the plugin with its README, its changes, its three language packs and its tests.
     *
     * @group REQ-REL-002
     */
    public function test_the_zip_holds_the_plugin_its_docs_languages_and_tests(): void {
        $this->resetAfterTest();
        $files = self::unpack(self::build_zip());

        $this->assertSame([], array_values(array_diff(self::REQUIRED, self::found(self::REQUIRED, $files))));
        $this->assertArrayHasKey(self::ROOT . '/lang/en/local_classprogress.php', $files);
        $this->assertArrayHasKey(self::ROOT . '/db/install.xml', $files);
    }

    /**
     * The zip has a single root folder and leaves out the development material.
     *
     * @group REQ-REL-002
     */
    public function test_the_zip_leaves_out_the_development_material(): void {
        $this->resetAfterTest();
        $files = self::unpack(self::build_zip());

        $roots = array_map(fn(string $path): string => strstr($path, '/', true) ?: $path, array_keys($files));
        $this->assertSame([self::ROOT], array_values(array_unique($roots)));
        $this->assertSame([], self::found(self::LEFT_OUT, $files));
        $this->assertSame([], preg_grep('~/\.git/~', array_keys($files)));
    }

    /**
     * Moodle's package validator, the one behind "Install plugins", accepts the zip as a local plugin for this site's
     * version.
     *
     * Its last step checks the place the plugin would go: the plugin is installed there already, which is only a
     * warning as long as the user running the test can write that folder (root in the test stacks, the runner in CI).
     *
     * @group REQ-REL-002
     */
    public function test_moodles_package_validator_accepts_the_zip(): void {
        global $CFG;

        $this->resetAfterTest();
        $target = make_request_directory();
        $files = \core_plugin_manager::instance()->unzip_plugin_file(self::build_zip(), $target, '');

        $validator = \core\update\validator::instance($target, $files);
        $validator->assert_plugin_type('local');
        $validator->assert_moodle_version($CFG->version);
        $result = $validator->execute();

        $this->assertTrue($result, self::describe($validator));
        $this->assertSame(self::COMPONENT, $validator->get_versionphp_info()['component']);
        $this->assertSame(self::ROOT, $validator->get_rootdir());
        $this->assertSame('local_classprogress', $validator->get_language_file_name());
    }

    /**
     * Builds the release zip from the plugin folder, as git archive --prefix=classprogress/ would.
     *
     * @return string Full path of the zip.
     */
    private static function build_zip(): string {
        $dir = \core_component::get_component_directory(self::COMPONENT);
        $ignored = array_merge(
            self::patterns($dir . '/.gitattributes', true),
            self::patterns($dir . '/.gitignore', false)
        );

        $entries = [];
        $folder = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($folder) as $file) {
            $path = substr($file->getPathname(), strlen($dir) + 1);
            if (!$file->isFile() || in_array('.git', explode('/', $path), true) || self::is_ignored($path, $ignored)) {
                continue;
            }
            $entries[self::ROOT . '/' . $path] = $file->getPathname();
        }
        ksort($entries);

        $zip = make_request_directory() . '/' . self::ROOT . '.zip';
        if (!get_file_packer('application/zip')->archive_to_pathname($entries, $zip)) {
            throw new \coding_exception('The release zip cannot be built');
        }
        return $zip;
    }

    /**
     * Unpacks a zip the way the "Install plugins" page does, which lists every folder too.
     *
     * @param string $zip Full path of the zip.
     * @return array Path inside the zip (folders end with /) => true.
     */
    private static function unpack(string $zip): array {
        return \core_plugin_manager::instance()->unzip_plugin_file($zip, make_request_directory(), '');
    }

    /**
     * The paths that a zip holds, out of a list.
     *
     * @param string[] $paths Paths relative to the zip's root; folders end with /.
     * @param array $files What the zip holds, from unpack().
     * @return string[] The paths of the list that it holds.
     */
    private static function found(array $paths, array $files): array {
        return array_values(array_filter($paths, fn(string $path): bool => isset($files[self::ROOT . '/' . $path])));
    }

    /**
     * Reads the patterns of .gitattributes lines with export-ignore, or of .gitignore lines.
     *
     * @param string $file Full path of the file; a missing file gives no pattern.
     * @param bool $attributes True for .gitattributes, false for .gitignore.
     * @return string[] Patterns.
     * @throws \coding_exception For a negated .gitignore pattern, which this emulation does not follow.
     */
    private static function patterns(string $file, bool $attributes): array {
        $patterns = [];
        foreach (is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if ($attributes) {
                $words = preg_split('/\s+/', $line);
                if (in_array('export-ignore', array_slice($words, 1), true)) {
                    $patterns[] = $words[0];
                }
            } else if ($line[0] === '!') {
                throw new \coding_exception('Negated .gitignore patterns are not emulated: ' . $line);
            } else {
                $patterns[] = $line;
            }
        }
        return $patterns;
    }

    /**
     * Whether git leaves a file out for one of the patterns: a pattern matches the file or one of its folders.
     *
     * A pattern with a slash before its end is anchored at the plugin folder; one without matches a name at any
     * depth; a trailing slash matches folders only.
     *
     * @param string $path File path relative to the plugin folder.
     * @param string[] $patterns Patterns of .gitattributes (export-ignore) and .gitignore.
     * @return bool True when the file is left out.
     */
    private static function is_ignored(string $path, array $patterns): bool {
        $parts = explode('/', $path);
        foreach ($patterns as $pattern) {
            $foldersonly = str_ends_with($pattern, '/');
            $pattern = rtrim($pattern, '/');
            $anchored = str_contains($pattern, '/');
            $pattern = ltrim($pattern, '/');
            for ($depth = 1; $depth <= count($parts); $depth++) {
                if ($foldersonly && $depth === count($parts)) {
                    break;
                }
                $prefix = implode('/', array_slice($parts, 0, $depth));
                $subject = $anchored ? $prefix : $parts[$depth - 1];
                if (fnmatch($pattern, $subject, FNM_PATHNAME)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * The validator's messages, for a failure message.
     *
     * @param \core\update\validator $validator A validator that has run.
     * @return string One line per message.
     */
    private static function describe(\core\update\validator $validator): string {
        $lines = [];
        foreach ($validator->get_messages() as $message) {
            $lines[] = $message->level . ' ' . $message->msgcode . ' ' . json_encode($message->addinfo);
        }
        return implode("\n", $lines);
    }
}
