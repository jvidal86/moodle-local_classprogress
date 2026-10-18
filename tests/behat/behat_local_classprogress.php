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
 * Named pages and steps for the class progress plugin.
 *
 * @package    local_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Named pages and steps for the class progress plugin.
 *
 * Pages: "local_classprogress > Home"; instances "local_classprogress > Plan" (by module code or title) and
 * "local_classprogress > School year" (by name).
 *
 * @package    local_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_classprogress extends behat_base {
    /**
     * Returns the URL of a page of the plugin.
     *
     * @param string $page Page name: Home.
     * @return moodle_url The URL.
     * @throws Exception For an unknown page.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'home':
                return new moodle_url('/local/classprogress/index.php');
            default:
                throw new Exception("Unrecognised local_classprogress page \"{$page}\".");
        }
    }

    /**
     * Returns the URL of the page of one plan or school year, whoever owns it.
     *
     * @param string $type Page type: Plan or School year.
     * @param string $identifier Module code or title of the plan; name of the school year.
     * @return moodle_url The URL.
     * @throws Exception For an unknown type.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'plan':
                $id = $DB->get_field_select(
                    'local_classprogress_plan',
                    'id',
                    'modulecode = ? OR moduletitle = ?',
                    [$identifier, $identifier],
                    MUST_EXIST
                );
                return new moodle_url('/local/classprogress/plan.php', ['id' => $id]);
            case 'school year':
                $id = $DB->get_field('local_classprogress_schoolyear', 'id', ['name' => $identifier], MUST_EXIST);
                return new moodle_url('/local/classprogress/schoolyear.php', ['id' => $id]);
            default:
                throw new Exception("Unrecognised local_classprogress page type \"{$type}\".");
        }
    }

    /**
     * Opens the page of a plan or school year and checks that Moodle refuses it with the given error.
     *
     * @Then the :identifier :page page shows the error :message
     *
     * @param string $identifier Module code or title of the plan; name of the school year.
     * @param string $page Page type with the component: "local_classprogress > Plan" or "local_classprogress > School year".
     * @param string $message Text the error must contain.
     * @throws Exception For a page of another component.
     */
    public function the_page_shows_the_error(string $identifier, string $page, string $message): void {
        $prefix = 'local_classprogress > ';
        if (strpos($page, $prefix) !== 0) {
            throw new Exception("Unrecognised page \"{$page}\": it must start with \"{$prefix}\".");
        }
        $this->open_refused_page($this->resolve_page_instance_url(substr($page, strlen($prefix)), $identifier), $message);
    }

    /**
     * Opens a URL of the plugin and checks that Moodle refuses it with the given error.
     *
     * @Then the page at :url shows the error :message
     *
     * @param string $url URL relative to the site root, such as /local/classprogress/plan.php?id=1.
     * @param string $message Text the error must contain.
     */
    public function the_page_at_url_shows_the_error(string $url, string $message): void {
        $this->open_refused_page(new moodle_url($url), $message);
    }

    /**
     * Opens a page, checks Moodle's error box on it, then goes back to the page shown before.
     *
     * Leaving is needed because the check that Behat runs after every step fails on any page with an error box.
     *
     * @param moodle_url $url The page.
     * @param string $message Text the error must contain.
     * @throws ExpectationException When the page shows no error, or another one.
     */
    private function open_refused_page(moodle_url $url, string $message): void {
        $previous = $this->getSession()->getCurrentUrl();
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $box = $this->getSession()->getPage()->find('xpath', "//div[@data-rel='fatalerror']");
        $text = $box ? $box->getText() : '';
        if (strpos($text, $message) === false) {
            $found = $box ? "the error \"{$text}\"" : 'no error';
            throw new ExpectationException("Expected the error \"{$message}\" on {$url}, found {$found}.", $this->getSession());
        }
        $this->getSession()->visit($previous);
    }
}
