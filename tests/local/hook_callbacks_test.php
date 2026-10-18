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

namespace local_classprogress\local;

use core\hook\navigation\primary_extend;
use core\navigation\views\primary;

/**
 * The "Class progress" link in the primary navigation.
 *
 * The callback is called directly with a primary view of its own, as core's primary_extend_test builds one;
 * the hook dispatch itself is core's and is not tested here. Link visibility in a real page is the Behat
 * scenario J1.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\local\hook_callbacks
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Builds a fresh primary view of the site home page, as core's primary_extend_test does.
     *
     * @return primary The view, not yet extended.
     */
    private function new_primary_view(): primary {
        global $PAGE;

        $PAGE = new \moodle_page();
        $PAGE->set_url('/');
        return new primary($PAGE);
    }

    /**
     * Runs the callback on a fresh primary view.
     *
     * @return primary The view, with the link added or not.
     */
    private function extend_primary_view(): primary {
        $view = $this->new_primary_view();
        hook_callbacks::extend_primary_navigation(new primary_extend($view));
        return $view;
    }

    /**
     * Creates the user of a case and makes them the current user.
     *
     * @param string $who A course role short name, 'nocourserole', 'guest' or 'notloggedin'.
     */
    private function become(string $who): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        if ($who === 'guest') {
            $this->setGuestUser();
        } else if ($who === 'notloggedin') {
            $this->setUser(null);
        } else if ($who === 'nocourserole') {
            $this->setUser($generator->create_user());
        } else {
            $this->setUser($generator->create_and_enrol($course, $who));
        }
    }

    /**
     * The callback is registered for the primary navigation hook.
     *
     * @group REQ-ACC-001
     */
    public function test_callback_is_registered(): void {
        $this->resetAfterTest();

        $callbacks = \core\di::get(\core\hook\manager::class)->get_callbacks_for_hook(primary_extend::class);
        $ours = array_filter($callbacks, fn(array $callback): bool => $callback['component'] === 'local_classprogress');

        $this->assertCount(1, $ours);
        $this->assertSame(hook_callbacks::class . '::extend_primary_navigation', reset($ours)['callback']);
    }

    /**
     * The link is added for users who qualify and only for them.
     *
     * @dataProvider visibility_provider
     * @group REQ-ACC-001
     * @param string $who A course role short name, 'nocourserole', 'guest' or 'notloggedin'.
     * @param bool $expected Whether the link is added.
     */
    public function test_link_visibility(string $who, bool $expected): void {
        $this->resetAfterTest();

        $this->become($who);
        $node = $this->extend_primary_view()->get('local_classprogress');

        if (!$expected) {
            $this->assertFalse($node);
            return;
        }
        $this->assertInstanceOf(\navigation_node::class, $node);
        $this->assertSame(get_string('pluginname', 'local_classprogress'), $node->text);
        $this->assertSame('/local/classprogress/index.php', $node->action->out_as_local_url(false));
    }

    /**
     * Users and whether they get the link.
     *
     * @return array
     */
    public static function visibility_provider(): array {
        return [
            'editing teacher' => ['editingteacher', true],
            'non-editing teacher' => ['teacher', true],
            'student' => ['student', false],
            'user without a course role' => ['nocourserole', false],
            'guest' => ['guest', false],
            'not logged in' => ['notloggedin', false],
        ];
    }

    /**
     * The site administrator gets the link, following the decision in access_test.
     *
     * @group REQ-ACC-001
     */
    public function test_admin_gets_the_link(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        $this->assertInstanceOf(\navigation_node::class, $this->extend_primary_view()->get('local_classprogress'));
    }

    /**
     * With the decision cached, building the navigation again runs no database query.
     *
     * @dataProvider warm_cache_provider
     * @group REQ-ACC-003
     * @param string $who A course role short name.
     */
    public function test_warm_cache_runs_no_query(string $who): void {
        global $DB;
        $this->resetAfterTest();

        $this->become($who);
        $this->extend_primary_view();
        $hook = new primary_extend($this->new_primary_view());

        $before = $DB->perf_get_queries();
        hook_callbacks::extend_primary_navigation($hook);

        $this->assertSame(0, $DB->perf_get_queries() - $before);
    }

    /**
     * Users whose decision is cached, one positive and one negative.
     *
     * @return array
     */
    public static function warm_cache_provider(): array {
        return [
            'editing teacher' => ['editingteacher'],
            'student' => ['student'],
        ];
    }

    /**
     * During the initial installation the callback returns at once, without touching roles or caches.
     *
     * @group REQ-ACC-001
     */
    public function test_nothing_during_initial_install(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $this->become('editingteacher');
        $view = $this->new_primary_view();
        $CFG->rolesactive = 0;

        $before = $DB->perf_get_queries();
        hook_callbacks::extend_primary_navigation(new primary_extend($view));

        $this->assertSame(0, $DB->perf_get_queries() - $before);
        $this->assertFalse($view->get('local_classprogress'));
    }

    /**
     * Before the plugin's own upgrade has run (the "Plugins check" page shows the navigation), there is no
     * link and no debugging message, even for the administrator; the test fails on any debugging() call.
     *
     * @group REQ-ACC-001
     */
    public function test_nothing_before_the_plugin_is_upgraded(): void {
        global $DB;
        $this->resetAfterTest();

        $this->setAdminUser();
        $DB->delete_records('capabilities', ['name' => access::CAPABILITY]);
        \cache::make('core', 'capabilities')->purge();

        $this->assertFalse($this->extend_primary_view()->get('local_classprogress'));
    }
}
