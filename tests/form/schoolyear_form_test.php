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

use local_classprogress\local\holiday_entry;
use local_classprogress\local\plan_service;
use local_classprogress\local\schoolyear_service;

/**
 * Tests for the school year form: what a posted form gives the service.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\form\schoolyear_form
 */
final class schoolyear_form_test extends \advanced_testcase {
    /** @var string Real school calendar lines; the second has a "<", which once deleted it and every later line. */
    private const HOLIDAYS = "2026-10-12 Festa Nacional\n"
        . "2026-12-07 Pont <- Immaculada\n"
        . "2026-12-08 La Immaculada\n"
        . "2026-12-22 to 2027-01-07 Vacances de Nadal & Reis\n"
        . "2027-03-22 to 2027-03-29 Vacances de Setmana Santa\n"
        . "2027-05-01 Festa del Treball";

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
     * School year form fields as a browser posts them.
     *
     * @param array $fields Fields to replace.
     * @return array Posted fields.
     */
    private static function post(array $fields = []): array {
        return array_merge([
            'name' => 'Curs 2026-2027',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
            'holidays' => self::HOLIDAYS,
            'evaluationrows' => 3,
            'evalname' => ['1a avaluació', '', ''],
            'evalstartdate' => ['2026-09-14', '', ''],
            'evalenddate' => ['2026-12-21', '', ''],
        ], $fields);
    }

    /**
     * Posts the form and returns the data it gives, or null when it is not valid.
     *
     * @param array $post Posted fields.
     * @param int $evaluationcount Stored evaluation periods.
     * @return \stdClass|null Data from get_data().
     */
    private static function submit(array $post, int $evaluationcount = 0): ?\stdClass {
        schoolyear_form::mock_submit($post);
        $url = new \moodle_url('/local/classprogress/schoolyear.php');
        $form = new schoolyear_form($url, ['evaluationcount' => $evaluationcount]);
        return $form->get_data();
    }

    /**
     * Names, labels and holiday text with <, > and & are stored exactly as typed, and every holiday line counts.
     *
     * Mondays and Fridays, 2 h, from 2026-09-14 to 2027-06-21, with the six holidays: 72 sessions. When the form
     * cleaned the text as PARAM_TEXT, everything after "<-" was lost and the plan showed 80.
     *
     * @group REQ-DAT-004
     * @group REQ-HOL-001
     */
    public function test_text_with_angle_brackets_and_ampersands_is_kept_as_typed(): void {
        $this->resetAfterTest();
        $user = $this->teacher();

        $data = self::submit(self::post([
            'name' => 'Curs <2026-27> & co',
            'evalname' => ['1a <avaluació> & més', '', ''],
        ]));
        $this->assertNotNull($data);
        $id = schoolyear_service::create($user->id, schoolyear_form::to_service_data($data));

        $this->assertSame('Curs <2026-27> & co', schoolyear_service::get($user->id, $id)->name);
        $evaluations = schoolyear_service::evaluations($user->id, $id);
        $this->assertSame(['1a <avaluació> & més'], array_values(array_column($evaluations, 'name')));
        $this->assertSame([
            'Festa Nacional',
            'Pont <- Immaculada',
            'La Immaculada',
            'Vacances de Nadal & Reis',
            'Vacances de Setmana Santa',
            'Festa del Treball',
        ], array_values(array_map(fn(holiday_entry $h): string => $h->label, schoolyear_service::holidays($user->id, $id))));
        $planid = plan_service::create($user->id, (object) [
            'schoolyearid' => $id,
            'modulecode' => 'MP 0372',
            'moduletitle' => 'Gestió de bases de dades',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
            'timetable' => [0 => 2, 4 => 2],
        ]);
        $this->assertCount(72, plan_service::calendar($user->id, $planid)->sessions);
    }

    /**
     * The form carries the version of the school year it was opened on, so a save from a stale tab is refused.
     *
     * @group REQ-DAT-003
     */
    public function test_form_carries_the_version_it_was_opened_on(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $id = schoolyear_service::create($user->id, schoolyear_form::to_service_data((object) self::post()));
        $year = schoolyear_service::get($user->id, $id);
        $opened = (array) schoolyear_form::from_record(
            $year,
            schoolyear_service::holidays($user->id, $id),
            schoolyear_service::evaluations($user->id, $id)
        );
        $this->assertSame((int) $year->timemodified, (int) $opened['timemodified']);

        $tab1 = (object) array_merge($opened, ['name' => 'Tab 1']);
        schoolyear_service::update($user->id, $id, schoolyear_form::to_service_data($tab1));
        $stale = self::submit(['name' => 'Tab 2', 'evaluationrows' => 3] + $opened, 1);

        $this->assertSame((int) $year->timemodified, schoolyear_form::to_service_data($stale)->timemodified);
        try {
            schoolyear_service::update($user->id, $id, schoolyear_form::to_service_data($stale));
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_changedelsewhere', $e->errorcode);
        }
        $this->assertSame('Tab 1', schoolyear_service::get($user->id, $id)->name);
    }

    /**
     * Requested evaluation rows, including the one the "add" button adds, never go beyond the 20 periods allowed.
     *
     * @return array Case => [posted fields, stored periods, rows expected].
     */
    public static function evaluation_rows_cases(): array {
        return [
            'default' => [[], 0, 3],
            'stored periods' => [[], 7, 7],
            'add one' => [['evaluationrows' => 5, 'addevaluation' => 'Add'], 0, 6],
            'crafted request' => [['evaluationrows' => 300], 0, 20],
            'add at the maximum' => [['evaluationrows' => 20, 'addevaluation' => 'Add'], 0, 20],
        ];
    }

    /**
     * The form builds at most 20 evaluation rows, whatever the request asks for.
     *
     * @dataProvider evaluation_rows_cases
     * @param array $post Posted fields.
     * @param int $stored Stored evaluation periods.
     * @param int $rows Rows expected.
     */
    public function test_evaluation_rows_are_capped(array $post, int $stored, int $rows): void {
        $this->resetAfterTest();
        $this->teacher();
        if ($post) {
            schoolyear_form::mock_submit($post);
        }

        $form = new schoolyear_form(new \moodle_url('/local/classprogress/schoolyear.php'), ['evaluationcount' => $stored]);

        $this->assertSame($rows, substr_count($form->render(), 'name="evalname['));
    }

    /**
     * The holiday box is monospaced with a class that exists on Moodle 4.5 (Bootstrap 4) and on 5.x (Bootstrap 5).
     */
    public function test_holiday_box_is_monospaced_on_both_versions(): void {
        $this->resetAfterTest();
        $this->teacher();

        $html = (new schoolyear_form(new \moodle_url('/local/classprogress/schoolyear.php'), ['evaluationcount' => 0]))->render();

        // Moodle puts the element's classes on the row around the box, whose font the box takes.
        $this->assertMatchesRegularExpression('~id="fitem_id_holidays" class="[^"]*\btext-monospace font-monospace\b~', $html);
    }
}
