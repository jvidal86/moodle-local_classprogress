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

namespace local_classprogress\output;

use local_classprogress\local\schoolyear_service;

/**
 * Tests for the landing page: what it shows of the teacher's school years and plan activities.
 *
 * The plan activities come from mod_classprogress, whose own tests check what it lists; here they are given to the page.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\output\index_page
 */
final class index_page_test extends \advanced_testcase {
    /**
     * A new user with a teacher role in a new course, logged in.
     *
     * @return \stdClass The user.
     */
    private function teacher(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);
        return $user;
    }

    /**
     * Renders a landing page.
     *
     * @param index_page $page The page.
     * @return string HTML.
     */
    private static function render(index_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('core')->render($page);
    }

    /**
     * School year names with <, > and & are shown as typed, escaped, never as markup.
     *
     * @group REQ-DAT-004
     */
    public function test_names_are_shown_as_typed_and_escaped(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        schoolyear_service::create($user->id, (object) [
            'name' => 'Curs <b>2026</b> & co',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
        ]);

        $html = self::render(index_page::for_user($user->id));

        $this->assertStringContainsString('Curs &lt;b&gt;2026&lt;/b&gt; &amp; co', $html);
        $this->assertStringNotContainsString('<b>2026', $html);
    }

    /**
     * The plan activities are listed with their course, status and pending sessions, linking to each; without any,
     * the page says how to make one.
     *
     * @group REQ-MOD-005
     */
    public function test_plan_activities_are_listed_with_course_status_and_pending_sessions(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $activity = [
            'name' => 'MP 0372 &amp; co',
            'course' => 'DAW 1',
            'status' => 'Approved',
            'pending' => 3,
            'url' => 'https://example.com/mod/classprogress/view.php?id=7',
        ];
        $draft = ['name' => 'MP 0374', 'course' => 'DAW 2', 'status' => 'Draft', 'pending' => null,
            'url' => 'https://example.com/mod/classprogress/view.php?id=8'];

        $context = (new index_page([], [$activity, $draft]))->export_for_template($GLOBALS['PAGE']->get_renderer('core'));
        $html = self::render(new index_page([], [$activity, $draft]));
        $empty = self::render(new index_page([], []));

        $this->assertTrue($context->hasactivities);
        $this->assertSame(['3', ''], array_column($context->activities, 'pending'));
        $this->assertStringContainsString(
            '<a href="https://example.com/mod/classprogress/view.php?id=7">MP 0372 &amp; co</a>',
            $html
        );
        $this->assertStringContainsString('DAW 1', $html);
        $this->assertStringContainsString('Approved', $html);
        $this->assertStringContainsString(get_string('noplanactivities', 'local_classprogress'), $empty);
        $this->assertStringNotContainsString(get_string('noplanactivities', 'local_classprogress'), $html);
    }
}
