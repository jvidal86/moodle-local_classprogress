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

use local_classprogress\local\calendar;
use local_classprogress\local\holiday_entry;
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
     * @param string $timezone The user's time zone; the server's when empty.
     * @return \stdClass The user.
     */
    private function teacher(string $timezone = ''): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $params = $timezone === '' ? null : ['timezone' => $timezone];
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher', $params);
        $this->setUser($user);
        return $user;
    }

    /**
     * A day as a browser posts a date selector set to it.
     *
     * @param string $day The day, yyyy-mm-dd.
     * @return array Posted day, month, year, and the tick of an optional selector.
     */
    private static function day(string $day): array {
        [$year, $month, $mday] = array_map('intval', explode('-', $day));
        return ['enabled' => 1, 'day' => $mday, 'month' => $month, 'year' => $year];
    }

    /**
     * The day a date selector of a rendered form shows.
     *
     * @param string $html The rendered form.
     * @param string $name The element name.
     * @return string The day, yyyy-mm-dd.
     */
    private static function shown(string $html, string $name): string {
        $part = function (string $part) use ($html, $name): int {
            $select = '~<select[^>]*name="' . preg_quote("{$name}[{$part}]", '~') . '"[^>]*>(.*?)</select>~s';
            preg_match($select, $html, $options);
            preg_match('~<option value="(\d+)" selected~', $options[1] ?? '', $selected);
            return (int) ($selected[1] ?? 0);
        };
        return sprintf('%04d-%02d-%02d', $part('year'), $part('month'), $part('day'));
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
            'startdate' => self::day('2026-09-14'),
            'enddate' => self::day('2027-06-21'),
            'holidays' => self::HOLIDAYS,
            'evaluationrows' => 3,
            // A browser posts nothing for an optional date selector left disabled.
            'evalname' => ['1a avaluació', '', ''],
            'evalstartdate' => [self::day('2026-09-14')],
            'evalenddate' => [self::day('2026-12-21')],
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
     * cleaned the text as PARAM_TEXT, everything after "<-" was lost and the calendar showed 80.
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
        $holidays = schoolyear_service::holidays($user->id, $id);
        $this->assertCount(72, calendar::compute('2026-09-14', '2027-06-21', [0 => 2.0, 4 => 2.0], $holidays)->sessions);
    }

    /**
     * The form carries the version of the school year it was opened on, so a save from a stale tab is refused.
     *
     * @group REQ-DAT-003
     */
    public function test_form_carries_the_version_it_was_opened_on(): void {
        $this->resetAfterTest();
        $user = $this->teacher();
        $id = schoolyear_service::create($user->id, schoolyear_form::to_service_data(self::submit(self::post())));
        $year = schoolyear_service::get($user->id, $id);
        $opened = (array) schoolyear_form::from_record(
            $year,
            schoolyear_service::holidays($user->id, $id),
            schoolyear_service::evaluations($user->id, $id)
        );
        $this->assertSame((int) $year->timemodified, (int) $opened['timemodified']);

        $tab1 = (object) array_merge($opened, ['name' => 'Tab 1']);
        schoolyear_service::update($user->id, $id, schoolyear_form::to_service_data($tab1));
        $stale = self::submit(self::post(['id' => $id, 'timemodified' => $opened['timemodified'], 'name' => 'Tab 2']), 1);

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

    /**
     * Days where a date could move by one: daylight saving changes, a midnight that does not exist, zones far from UTC,
     * and the first and last days a teacher may enter.
     *
     * @return array Case => [user time zone, day].
     */
    public static function day_cases(): array {
        return [
            'Madrid, clocks go forward' => ['Europe/Madrid', '2026-03-29'],
            'Madrid, clocks go back' => ['Europe/Madrid', '2026-10-25'],
            'Santiago, the day starts at 01:00' => ['America/Santiago', '2026-09-06'],
            'Kiritimati, UTC+14' => ['Pacific/Kiritimati', '2026-09-14'],
            'Pago Pago, UTC-11' => ['Pacific/Pago_Pago', '2026-09-14'],
            'first day allowed' => ['Pacific/Kiritimati', '2000-01-01'],
            'last day allowed' => ['Pacific/Pago_Pago', '2099-12-31'],
        ];
    }

    /**
     * The day picked in a date selector is the day the service gets, in the teacher's time zone.
     *
     * @dataProvider day_cases
     * @group REQ-CAL-004
     * @param string $timezone User time zone.
     * @param string $day The day.
     */
    public function test_picked_day_is_the_day_saved(string $timezone, string $day): void {
        $this->resetAfterTest();
        $this->teacher($timezone);

        $data = self::submit(self::post([
            'startdate' => self::day($day),
            'enddate' => self::day($day),
            'holidays' => '',
            'evalstartdate' => [self::day($day)],
            'evalenddate' => [self::day($day)],
        ]));

        $this->assertNotNull($data);
        $yeardata = schoolyear_form::to_service_data($data);
        $this->assertSame([$day, $day], [$yeardata->startdate, $yeardata->enddate]);
        $evaluation = (object) ['name' => '1a avaluació', 'startdate' => $day, 'enddate' => $day];
        $this->assertEquals([0 => $evaluation], $yeardata->evaluations);
    }

    /**
     * A stored day is the day the date selector shows, in the teacher's time zone.
     *
     * @dataProvider day_cases
     * @group REQ-CAL-004
     * @param string $timezone User time zone.
     * @param string $day The day.
     */
    public function test_stored_day_is_the_day_shown(string $timezone, string $day): void {
        $this->resetAfterTest();
        $this->teacher($timezone);
        $year = (object) ['id' => 0, 'timemodified' => 0, 'name' => 'Curs', 'startdate' => $day, 'enddate' => $day];
        $evaluation = (object) ['name' => '1a avaluació', 'startdate' => $day, 'enddate' => $day];

        $form = new schoolyear_form(new \moodle_url('/local/classprogress/schoolyear.php'), ['evaluationcount' => 1]);
        $form->set_data(schoolyear_form::from_record($year, [], [$evaluation]));
        $html = $form->render();

        foreach (['startdate', 'enddate', 'evalstartdate[0]', 'evalenddate[0]'] as $name) {
            $this->assertSame($day, self::shown($html, $name), $name);
        }
    }

    /**
     * The date selectors offer the years 2000 to 2099 only; those of an evaluation row can be left disabled.
     *
     * @group REQ-CAL-004
     */
    public function test_date_selectors_offer_the_years_a_teacher_may_enter(): void {
        $this->resetAfterTest();
        $this->teacher();

        $html = (new schoolyear_form(new \moodle_url('/local/classprogress/schoolyear.php'), ['evaluationcount' => 0]))->render();

        foreach (['startdate', 'enddate', 'evalstartdate[0]', 'evalenddate[2]'] as $name) {
            preg_match('~<select[^>]*name="' . preg_quote("{$name}[year]", '~') . '"[^>]*>(.*?)</select>~s', $html, $select);
            preg_match_all('~<option value="(\d+)"~', $select[1] ?? '', $years);
            $this->assertSame(array_map('strval', range(2000, 2099)), $years[1], $name);
        }
        $this->assertStringContainsString('name="evalstartdate[0][enabled]"', $html);
        $this->assertStringNotContainsString('name="startdate[enabled]"', $html);
    }

    /**
     * A named evaluation row whose dates were left disabled is refused, with a message that says what to do.
     *
     * @group REQ-CAL-004
     */
    public function test_named_row_without_dates_asks_for_them(): void {
        $this->resetAfterTest();
        $this->teacher();
        schoolyear_form::mock_submit(self::post(['evalname' => ['1a avaluació', '2a avaluació', '']]));

        $form = new schoolyear_form(new \moodle_url('/local/classprogress/schoolyear.php'), ['evaluationcount' => 0]);

        $this->assertNull($form->get_data());
        $this->assertStringContainsString(get_string('error_baddate', 'local_classprogress'), $form->render());
    }
}
