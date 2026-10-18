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

/**
 * Who may use the plugin: the capability, the live check on pages and the cached link decision.
 *
 * Teachers hold their roles in courses, so every case is built with course enrolments, never with a
 * system role, except for the site administrator.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\local\access
 */
final class access_test extends \advanced_testcase {
    /**
     * The capability is a write capability flagged as personal data, defined at course level.
     *
     * @group REQ-ACC-004
     */
    public function test_capability_definition(): void {
        $this->resetAfterTest();

        $info = get_capability_info(access::CAPABILITY);

        $this->assertNotFalse($info);
        $this->assertSame('local/classprogress:use', access::CAPABILITY);
        $this->assertSame('write', $info->captype);
        $this->assertSame(RISK_PERSONAL, (int) $info->riskbitmask);
        $this->assertSame(CONTEXT_COURSE, (int) $info->contextlevel);
        $this->assertSame('local_classprogress', $info->component);
        $this->assertSame(get_string('classprogress:use', 'local_classprogress'), get_capability_string(access::CAPABILITY));
    }

    /**
     * The link decision is kept in a session cache, which has a name in the cache administration page.
     *
     * @group REQ-ACC-003
     */
    public function test_link_cache_definition(): void {
        $this->resetAfterTest();

        $registered = \cache_config::instance()->get_definition_by_id('local_classprogress/showlink');
        $this->assertNotEmpty($registered);
        $definition = \cache_definition::load('local_classprogress/showlink', $registered);

        $this->assertSame(\cache_store::MODE_SESSION, $definition->get_mode());
        $this->assertSame(get_string('cachedef_showlink', 'local_classprogress'), (string) $definition->get_name());
    }

    /**
     * Only the editing teacher, teacher and manager archetypes are allowed by default; never user or guest.
     *
     * @group REQ-ACC-004
     */
    public function test_capability_archetypes(): void {
        $this->resetAfterTest();

        $allowed = ['editingteacher', 'teacher', 'manager'];
        foreach (get_role_archetypes() as $archetype) {
            $defaults = get_default_capabilities($archetype);
            if (in_array($archetype, $allowed, true)) {
                $this->assertSame(CAP_ALLOW, $defaults[access::CAPABILITY] ?? null, $archetype);
            } else {
                $this->assertArrayNotHasKey(access::CAPABILITY, $defaults, $archetype);
            }
        }
    }

    /**
     * The installed roles carry the archetype defaults, so a fresh site gives teachers the capability.
     *
     * @group REQ-ACC-004
     */
    public function test_installed_roles_follow_the_archetypes(): void {
        global $DB;
        $this->resetAfterTest();

        $syscontext = \context_system::instance();
        $conditions = ['capability' => access::CAPABILITY, 'contextid' => $syscontext->id];
        $granted = $DB->get_records_menu('role_capabilities', $conditions, '', 'roleid, permission');
        $byname = [];
        foreach ($granted as $roleid => $permission) {
            $byname[$DB->get_field('role', 'shortname', ['id' => $roleid])] = (int) $permission;
        }
        ksort($byname);

        $this->assertSame(['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW, 'teacher' => CAP_ALLOW], $byname);
    }

    /**
     * The capability is listed where course permissions are edited (role overrides in a course).
     *
     * @group REQ-ACC-004
     */
    public function test_capability_is_listed_for_course_contexts(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $names = array_column(\context_course::instance($course->id)->get_capabilities(), 'name');

        $this->assertContains(access::CAPABILITY, $names);
    }

    /**
     * Course roles that hold the capability qualify and pass the page check; a student does neither.
     *
     * @dataProvider course_role_provider
     * @group REQ-ACC-001
     * @group REQ-ACC-002
     * @param string $role Short name of the role the user is enrolled with.
     * @param bool $expected Whether the user qualifies.
     */
    public function test_course_role(string $role, bool $expected): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, $role);

        $this->assertSame($expected, access::qualifies((int) $user->id));

        $this->setUser($user);
        $this->assertSame($expected, access::show_link());
        if (!$expected) {
            $this->expectException(\required_capability_exception::class);
        }
        access::require_user();
    }

    /**
     * Course roles and whether they qualify.
     *
     * @return array
     */
    public static function course_role_provider(): array {
        return [
            'editing teacher' => ['editingteacher', true],
            'non-editing teacher' => ['teacher', true],
            'manager enrolled in a course' => ['manager', true],
            'student' => ['student', false],
        ];
    }

    /**
     * A teacher in two courses qualifies, and still does after losing the role in one of them.
     *
     * @group REQ-ACC-001
     * @group REQ-ACC-002
     */
    public function test_teacher_in_two_courses(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $teacher = $generator->create_and_enrol($course1, 'editingteacher');
        $generator->enrol_user($teacher->id, $course2->id, 'editingteacher');

        $this->assertTrue(access::qualifies((int) $teacher->id));

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        role_unassign($roleid, $teacher->id, \context_course::instance($course1->id)->id);

        $this->assertTrue(access::qualifies((int) $teacher->id));
        $this->setUser($teacher);
        access::require_user();
    }

    /**
     * An authenticated user with no course role does not qualify and is refused.
     *
     * @group REQ-ACC-001
     * @group REQ-ACC-002
     */
    public function test_user_without_course_role(): void {
        $this->resetAfterTest();

        $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(access::qualifies((int) $user->id));

        $this->setUser($user);
        $this->assertFalse(access::show_link());
        $this->expectException(\required_capability_exception::class);
        access::require_user();
    }

    /**
     * The guest account never qualifies, even if an administrator gives the guest role the capability.
     *
     * @group REQ-ACC-001
     * @group REQ-ACC-002
     */
    public function test_guest(): void {
        global $CFG;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        assign_capability(access::CAPABILITY, CAP_ALLOW, $CFG->guestroleid, \context_system::instance()->id, true);
        $guest = guest_user();

        $this->assertFalse(access::qualifies((int) $guest->id));

        $this->setGuestUser();
        $this->assertFalse(access::show_link());
        $this->expectException(\required_capability_exception::class);
        access::require_user();
    }

    /**
     * The visitor who is not logged in never qualifies; user id 0 is not read as "the current user".
     *
     * @group REQ-ACC-001
     * @group REQ-ACC-002
     */
    public function test_not_logged_in(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        $this->assertFalse(access::qualifies(0));

        $this->setUser(null);
        $this->assertFalse(access::qualifies(0));
        $this->assertFalse(access::show_link());
        $this->expectException(\required_capability_exception::class);
        access::require_user();
    }

    /**
     * Decision: the site administrator qualifies, as in every other capability check of Moodle.
     *
     * Ownership, not the capability, keeps other teachers' records out of reach (REQ-OWN-001).
     *
     * @group REQ-ACC-001
     * @group REQ-ACC-002
     */
    public function test_admin_qualifies(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
        $admin = get_admin();

        $this->assertTrue(access::qualifies((int) $admin->id));
        $this->assertTrue(access::show_link());
        access::require_user();
    }

    /**
     * Decision: a teacher whose enrolment is suspended still qualifies.
     *
     * The check reads role assignments, as has_capability() does in the course; the plugin only shows the
     * teacher's own records, and removing the role is what takes access away.
     *
     * @group REQ-ACC-001
     * @group REQ-ACC-002
     */
    public function test_teacher_with_suspended_enrolment_qualifies(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $teacher, '', true));

        $this->assertTrue(access::qualifies((int) $teacher->id));

        $this->setUser($teacher);
        $this->assertTrue(access::show_link());
        access::require_user();
    }

    /**
     * A prohibit override in the course takes the capability away from a teacher there.
     *
     * @group REQ-ACC-001
     * @group REQ-ACC-002
     */
    public function test_prohibit_in_course_refuses_teacher(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability(access::CAPABILITY, CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id, true);

        $this->assertFalse(access::qualifies((int) $teacher->id));

        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        access::require_user();
    }

    /**
     * A teacher whose role is removed while logged in is refused at once; only the cached link lingers.
     *
     * PHPUnit runs as CLI, where get_user_accessdata() re-reads the dirty flag of the user by itself. Moving the
     * load time of the session's access data forward switches that off, as in a web request, where the
     * session keeps its copy until something reloads it. Only the live check's own reload can then notice.
     *
     * @group REQ-ACC-003
     * @group REQ-ACC-002
     */
    public function test_role_removed_while_logged_in(): void {
        global $DB, $USER;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $coursecontext = \context_course::instance($course->id);

        $this->setUser($teacher);
        $this->assertTrue(has_capability(access::CAPABILITY, $coursecontext));
        $this->assertTrue(access::show_link());
        access::require_user();
        $USER->access['time'] = time() + HOURSECS;

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        role_unassign($roleid, $teacher->id, $coursecontext->id);

        $this->assertTrue(access::show_link(), 'The cached link may linger until the session cache is cleared.');
        $this->assertFalse(access::qualifies((int) $teacher->id));
        $this->expectException(\required_capability_exception::class);
        access::require_user();
    }

    /**
     * While the plugin's code is newer than its installation (no capability yet), nobody qualifies and no
     * debugging message is printed; the test fails on any unexpected debugging() call.
     *
     * @group REQ-ACC-001
     * @group REQ-ACC-002
     */
    public function test_capability_not_installed_yet(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $DB->delete_records('capabilities', ['name' => access::CAPABILITY]);
        \cache::make('core', 'capabilities')->purge();

        $this->assertFalse(access::qualifies((int) $teacher->id));

        $this->setUser($teacher);
        $this->assertFalse(access::show_link());
        $this->expectException(\required_capability_exception::class);
        access::require_user();
    }

    /**
     * Nothing is cached before the upgrade has created the capability, so a teacher who browses meanwhile
     * sees the link on the first page after the upgrade, not only after the next login.
     *
     * @group REQ-ACC-001
     */
    public function test_link_shows_right_after_the_capability_is_installed(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $DB->delete_records('capabilities', ['name' => access::CAPABILITY]);
        \cache::make('core', 'capabilities')->purge();

        $this->setUser($teacher);
        $this->assertFalse(access::show_link());

        update_capabilities('local_classprogress');
        \cache::make('core', 'capabilities')->purge();

        $this->assertTrue(access::show_link());
    }

    /**
     * The cached link decision belongs to one user: logging in as a student does not reuse the admin's answer.
     *
     * @group REQ-ACC-001
     * @group REQ-ACC-003
     */
    public function test_cached_link_follows_the_user(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setAdminUser();
        $this->assertTrue(access::show_link());

        \core\session\manager::loginas($student->id, \context_system::instance(), false);
        $this->assertFalse(access::show_link());
    }
}
