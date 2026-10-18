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

use local_classprogress\local\plan_service;
use local_classprogress\local\schoolyear_service;

/**
 * Tests for the landing page: what it shows of the teacher's school years and plans.
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
     * Creates a school year and a plan in it through the services.
     *
     * @param int $userid Owner.
     * @param string $name School year name.
     * @param string $code Module code.
     * @param string $title Module title.
     * @return int[] School year id and plan id.
     */
    private static function year_and_plan(int $userid, string $name, string $code, string $title): array {
        $yearid = schoolyear_service::create($userid, (object) [
            'name' => $name,
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
        ]);
        $planid = plan_service::create($userid, (object) [
            'schoolyearid' => $yearid,
            'modulecode' => $code,
            'moduletitle' => $title,
            'startdate' => '2026-09-14',
            'enddate' => '2026-09-25',
            'timetable' => [0 => 2],
        ]);
        return [$yearid, $planid];
    }

    /**
     * Renders the landing page of a user.
     *
     * @param int $userid The user.
     * @return string HTML.
     */
    private static function render(int $userid): string {
        global $PAGE;

        return $PAGE->get_renderer('core')->render(index_page::for_user($userid));
    }

    /**
     * Names with <, > and & are shown as typed, escaped, never as markup.
     *
     * @group REQ-DAT-004
     */
    public function test_names_are_shown_as_typed_and_escaped(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        self::year_and_plan($user->id, 'Curs <b>2026</b> & co', 'MP<03>', 'Programació <C++> i Java');

        $html = self::render($user->id);

        $this->assertStringContainsString('Curs &lt;b&gt;2026&lt;/b&gt; &amp; co', $html);
        $this->assertStringContainsString('MP&lt;03&gt; · Programació &lt;C++&gt; i Java', $html);
        $this->assertStringNotContainsString('<b>2026', $html);
        $this->assertStringNotContainsString('<C++>', $html);
    }

    /**
     * The hidden column heading uses a class that exists on Moodle 4.5 (Bootstrap 4) and on 5.x (Bootstrap 5).
     */
    public function test_hidden_text_uses_a_class_of_both_versions(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        self::year_and_plan($user->id, 'Curs 2026-2027', 'MP 0372', 'Gestió de bases de dades');

        $html = self::render($user->id);

        $this->assertStringContainsString('<span class="accesshide">', $html);
        $this->assertStringNotContainsString('sr-only', $html);
    }

    /**
     * A plan whose school year is missing does not break the page: it is listed apart, with its delete link.
     *
     * @group REQ-DAT-006
     */
    public function test_a_plan_without_its_school_year_is_listed_apart(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $user = $this->teacher();
        [$yearid, $planid] = self::year_and_plan($user->id, 'Curs perdut', 'MP 0372', 'Gestió de bases de dades');
        self::year_and_plan($user->id, 'Curs 2026-2027', 'MP 0374', 'Programació');
        $DB->delete_records('local_classprogress_schoolyear', ['id' => $yearid]);

        $context = index_page::for_user($user->id)->export_for_template($PAGE->get_renderer('core'));
        $html = self::render($user->id);

        $this->assertTrue($context->hasyears);
        $this->assertSame(['Curs 2026-2027'], array_column($context->years, 'name'));
        $this->assertTrue($context->hasorphans);
        $this->assertSame(['MP 0372 · Gestió de bases de dades'], array_column($context->orphans, 'name'));
        $deleteurl = new \moodle_url('/local/classprogress/plan.php', ['id' => $planid, 'action' => 'delete']);
        $this->assertSame($deleteurl->out(false), $context->orphans[0]['deleteurl']);
        $this->assertStringContainsString(get_string('plansnoschoolyear', 'local_classprogress'), $html);
        $this->assertStringContainsString('MP 0372 · Gestió de bases de dades', $html);
    }
}
