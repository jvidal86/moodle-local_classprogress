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

namespace local_classprogress\form;

use local_classprogress\local\plan_service;
use local_classprogress\local\schoolyear_service;

/**
 * Tests for the plan form: what a posted form gives the service.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\form\plan_form
 */
final class plan_form_test extends \advanced_testcase {
    /**
     * A new user with a teacher role in a course, logged in.
     *
     * @param \stdClass|null $course The course; a new one when null.
     * @return \stdClass The user.
     */
    private function teacher(?\stdClass $course = null): \stdClass {
        $course ??= $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);
        return $user;
    }

    /**
     * A school year of the user.
     *
     * @param int $userid Owner.
     * @return int School year id.
     */
    private static function year(int $userid): int {
        return schoolyear_service::create($userid, (object) [
            'name' => 'Curs 2026-2027',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
        ]);
    }

    /**
     * Posts the plan form and returns the data it gives, or null when it is not valid.
     *
     * @param int $userid The acting user.
     * @param array $post Posted fields.
     * @param \stdClass|null $plan The plan being edited.
     * @return \stdClass|null Data from get_data().
     */
    private static function submit(int $userid, array $post, ?\stdClass $plan = null): ?\stdClass {
        plan_form::mock_submit($post);
        $years = array_map(fn(\stdClass $y): string => s($y->name), schoolyear_service::list($userid));
        $form = new plan_form(new \moodle_url('/local/classprogress/plan.php'), [
            'userid' => $userid,
            'years' => $years,
            'courses' => plan_form::course_options($userid, $plan),
        ]);
        return $form->get_data();
    }

    /**
     * A module code and title with <, > and & are stored exactly as typed.
     *
     * @group REQ-DAT-004
     */
    public function test_module_code_and_title_with_angle_brackets_are_kept_as_typed(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $yearid = self::year($user->id);

        $data = self::submit($user->id, [
            'schoolyearid' => $yearid,
            'courseid' => 0,
            'modulecode' => 'MP<03>',
            'moduletitle' => 'Programació <C++> i Java & més',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
            'hours0' => '2',
        ]);
        $this->assertNotNull($data);
        $id = plan_service::create($user->id, plan_form::to_service_data($data));

        $plan = plan_service::get($user->id, $id);
        $this->assertSame(['MP<03>', 'Programació <C++> i Java & més'], [$plan->modulecode, $plan->moduletitle]);
    }

    /**
     * Re-saving a plan unchanged keeps its link to a course where the teacher no longer teaches.
     */
    public function test_resaving_keeps_a_course_link_the_teacher_did_not_change(): void {
        global $DB;

        $this->resetAfterTest();
        $old = $this->getDataGenerator()->create_course(['fullname' => 'DAM <1>']);
        $user = $this->teacher();
        $this->getDataGenerator()->enrol_user($user->id, $old->id, 'editingteacher');
        $yearid = self::year($user->id);
        $id = plan_service::create($user->id, (object) [
            'schoolyearid' => $yearid, 'courseid' => $old->id, 'modulecode' => 'MP03', 'moduletitle' => 'Programació',
            'startdate' => '2026-09-14', 'enddate' => '2027-06-21', 'timetable' => [0 => 2],
        ]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        role_unassign($roleid, $user->id, \context_course::instance($old->id)->id);
        $plan = plan_service::get($user->id, $id);

        $options = plan_form::course_options($user->id, $plan);
        $data = self::submit($user->id, (array) plan_form::from_record($plan, plan_service::timetable($user->id, $id)), $plan);
        $this->assertNotNull($data);
        plan_service::update($user->id, $id, plan_form::to_service_data($data));

        $this->assertSame((string) $old->id, (string) plan_service::get($user->id, $id)->courseid);
        $this->assertArrayHasKey((int) $old->id, $options);
        $this->assertArrayNotHasKey((int) $old->id, plan_form::course_options($user->id, null));
    }

    /**
     * The form carries the version of the plan it was opened on.
     *
     * @group REQ-DAT-003
     */
    public function test_form_carries_the_version_it_was_opened_on(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $id = plan_service::create($user->id, (object) [
            'schoolyearid' => self::year($user->id), 'modulecode' => 'MP03', 'moduletitle' => 'Programació',
        ]);
        $plan = plan_service::get($user->id, $id);

        $data = self::submit($user->id, (array) plan_form::from_record($plan, []), $plan);

        $this->assertSame((int) $plan->timemodified, plan_form::to_service_data($data)->timemodified);
    }
}
