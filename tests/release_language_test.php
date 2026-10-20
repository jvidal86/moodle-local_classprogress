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
 * The Catalan and Spanish strings inside the plugin (REQ-REL-003): the same keys and placeholders as English, nothing
 * left in English unless listed, and Moodle reading them from the plugin's own lang folder.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class release_language_test extends \advanced_testcase {
    /** @var string The plugin's component name. */
    private const COMPONENT = 'local_classprogress';

    /**
     * @var string[] Keys whose text is the same in English, Catalan and Spanish (such as "XLSX").
     *
     * Empty until the translations are merged; the translators' reports list the keys that belong here.
     */
    private const SAME_IN_ALL_LANGUAGES = [];

    /** @var string A placeholder: {$a}, {$a->name}, or a plain {name} that core fills in, such as {no}. */
    private const PLACEHOLDER = '/\{\$a(?:->\w+)?\}|\{\w+\}/';

    /**
     * The translations.
     *
     * @return array Language => [language].
     */
    public static function languages(): array {
        return ['Catalan' => ['ca'], 'Spanish' => ['es']];
    }

    /**
     * Reads the plugin's strings in one language, from its own lang folder.
     *
     * @param string $lang Language code.
     * @return string[] Key => text.
     */
    private function strings(string $lang): array {
        $file = \core_component::get_component_directory(self::COMPONENT) . "/lang/{$lang}/" . self::COMPONENT . '.php';
        $this->assertFileExists($file);
        $string = [];
        include($file);
        return $string;
    }

    /**
     * The placeholders of a text, sorted.
     *
     * @param string $text A string.
     * @return string[] Placeholders, repeated as often as they appear.
     */
    private static function placeholders(string $text): array {
        preg_match_all(self::PLACEHOLDER, $text, $matches);
        sort($matches[0]);
        return $matches[0];
    }

    /**
     * Every English key has a translation, and the translation has no other key.
     *
     * @dataProvider languages
     * @group REQ-REL-003
     * @param string $lang Language code.
     */
    public function test_the_translation_has_the_same_keys_as_english(string $lang): void {
        $english = array_keys($this->strings('en'));
        $translated = array_keys($this->strings($lang));

        $this->assertSame([], array_values(array_diff($english, $translated)), 'Keys without a translation');
        $this->assertSame([], array_values(array_diff($translated, $english)), 'Keys that English does not have');
    }

    /**
     * Every translated string has exactly the placeholders of its English string.
     *
     * @dataProvider languages
     * @group REQ-REL-003
     * @param string $lang Language code.
     */
    public function test_the_translation_keeps_every_placeholder(string $lang): void {
        $english = $this->strings('en');
        $wrong = [];
        foreach ($this->strings($lang) as $key => $text) {
            if (isset($english[$key]) && self::placeholders($text) !== self::placeholders($english[$key])) {
                $wrong[] = $key;
            }
        }

        $this->assertSame([], $wrong);
    }

    /**
     * No string is left in English, except those listed as the same in every language.
     *
     * @dataProvider languages
     * @group REQ-REL-003
     * @param string $lang Language code.
     */
    public function test_no_string_is_left_in_english(string $lang): void {
        $english = $this->strings('en');
        $same = [];
        foreach ($this->strings($lang) as $key => $text) {
            if (($english[$key] ?? null) === $text && !in_array($key, self::SAME_IN_ALL_LANGUAGES, true)) {
                $same[] = $key;
            }
        }

        $this->assertSame([], $same);
        $this->assertSame([], array_values(array_diff(self::SAME_IN_ALL_LANGUAGES, array_keys($english))));
    }

    /**
     * Once the site has the language pack, Moodle reads the plugin's strings from the plugin's own lang folder; no
     * file is copied anywhere.
     *
     * @dataProvider languages
     * @group REQ-REL-003
     * @param string $lang Language code.
     */
    public function test_moodle_reads_the_translation_from_the_plugin(string $lang): void {
        global $CFG;

        $this->resetAfterTest();
        $expected = $this->strings($lang)['pluginname'];
        $this->assertNotSame($this->strings('en')['pluginname'], $expected);

        // The smallest language pack: Moodle resolves a language only when its langconfig.php is in the dataroot.
        $dir = $CFG->dataroot . '/lang/' . $lang;
        $config = $dir . '/langconfig.php';
        $this->assertFileDoesNotExist($config);
        check_dir_exists($dir);
        file_put_contents($config, "<?php\n\$string['thislanguage'] = '{$lang}';\n\$string['parentlanguage'] = '';\n");
        try {
            get_string_manager()->reset_caches();

            $this->assertSame($expected, get_string_manager()->get_string('pluginname', self::COMPONENT, null, $lang));
        } finally {
            unlink($config);
            rmdir($dir);
            get_string_manager()->reset_caches();
        }
    }
}
