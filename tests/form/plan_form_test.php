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
     * @param string $timezone The user's time zone; the server's when empty.
     * @return \stdClass The user.
     */
    private function teacher(?\stdClass $course = null, string $timezone = ''): \stdClass {
        $course ??= $this->getDataGenerator()->create_course();
        $params = $timezone === '' ? null : ['timezone' => $timezone];
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher', $params);
        $this->setUser($user);
        return $user;
    }

    /**
     * A day as a browser posts a date selector set to it.
     *
     * @param string $day The day, yyyy-mm-dd.
     * @return array Posted tick, day, month and year.
     */
    private static function day(string $day): array {
        [$year, $month, $mday] = array_map('intval', explode('-', $day));
        return ['enabled' => 1, 'day' => $mday, 'month' => $month, 'year' => $year];
    }

    /**
     * Form data for set_data() as a browser posts it back unchanged: a disabled date selector posts nothing.
     *
     * @param \stdClass $data Data from from_record().
     * @return array Posted fields.
     */
    private static function posted(\stdClass $data): array {
        $post = (array) $data;
        foreach (['startdate', 'enddate'] as $name) {
            unset($post[$name]);
            if ($data->$name) {
                $post[$name] = self::day(date_field::to_day($data->$name));
            }
        }
        return $post;
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
            'startdate' => self::day('2026-09-14'),
            'enddate' => self::day('2027-06-21'),
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
        $opened = plan_form::from_record($plan, plan_service::timetable($user->id, $id));
        $data = self::submit($user->id, self::posted($opened), $plan);
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

        $data = self::submit($user->id, self::posted(plan_form::from_record($plan, [])), $plan);

        $this->assertSame((int) $plan->timemodified, plan_form::to_service_data($data)->timemodified);
    }

    /**
     * Class days left disabled give the service no dates, and a plan without them opens with them disabled.
     *
     * @group REQ-CAL-002
     * @group REQ-CAL-004
     */
    public function test_class_days_can_be_left_out(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $yearid = self::year($user->id);

        $data = self::submit($user->id, ['schoolyearid' => $yearid, 'courseid' => 0, 'moduletitle' => 'Programació']);
        $this->assertNotNull($data);
        $plandata = plan_form::to_service_data($data);
        $this->assertSame(['', ''], [$plandata->startdate, $plandata->enddate]);
        $id = plan_service::create($user->id, $plandata);

        $plan = plan_service::get($user->id, $id);
        $form = new plan_form(new \moodle_url('/local/classprogress/plan.php'), [
            'userid' => $user->id,
            'years' => [$yearid => 'Curs 2026-2027'],
            'courses' => [],
        ]);
        $form->set_data(plan_form::from_record($plan, []));
        $html = $form->render();
        foreach (['startdate', 'enddate'] as $name) {
            preg_match('~<input[^>]*name="' . $name . '\[enabled\]"[^>]*>~', $html, $checkbox);
            $this->assertStringNotContainsString('checked', $checkbox[0] ?? 'missing', $name);
        }
    }

    /**
     * Class days picked far from UTC are the days saved.
     *
     * @group REQ-CAL-004
     */
    public function test_class_days_keep_their_day_far_from_utc(): void {
        $this->resetAfterTest();
        $user = $this->teacher(null, 'Pacific/Kiritimati');
        $yearid = self::year($user->id);

        $data = self::submit($user->id, [
            'schoolyearid' => $yearid,
            'courseid' => 0,
            'moduletitle' => 'Programació',
            'startdate' => self::day('2026-09-14'),
            'enddate' => self::day('2027-06-21'),
        ]);
        $this->assertNotNull($data);
        $id = plan_service::create($user->id, plan_form::to_service_data($data));
        $plan = plan_service::get($user->id, $id);
        $this->assertSame(['2026-09-14', '2027-06-21'], [$plan->startdate, $plan->enddate]);
    }
}
