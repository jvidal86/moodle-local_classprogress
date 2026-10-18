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

use local_classprogress\local\calendar;
use local_classprogress\local\holiday_parser;

/**
 * Tests for the calendar view of a plan: its wording and how it shows the teacher's holiday labels.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\output\calendar_view
 */
final class calendar_view_test extends \advanced_testcase {
    /**
     * Renders the calendar of a range of days with Monday classes.
     *
     * @param string $first First class day.
     * @param string $last Last class day.
     * @param float $hours Hours on Mondays.
     * @param string $holidays Holiday text.
     * @return string HTML.
     */
    private static function render(string $first, string $last, float $hours, string $holidays = ''): string {
        global $PAGE;

        $entries = holiday_parser::parse($holidays)->entries;
        $view = new calendar_view(calendar::compute($first, $last, [0 => $hours], $entries), $entries);
        return $PAGE->get_renderer('core')->render($view);
    }

    /**
     * Summaries of one session, one hour and of several, with the words each must use.
     *
     * @return array Case => [last day, hours on Mondays, expected summary].
     */
    public static function summary_cases(): array {
        return [
            'one session of one hour' => ['2026-09-14', 1.0, '1 session, 1 hour of class'],
            'one session of one and a half hours' => ['2026-09-14', 1.5, '1 session, 1.5 hours of class'],
            'two sessions' => ['2026-09-21', 1.0, '2 sessions, 2 hours of class'],
            'no session' => ['2026-09-20', 0.0, '0 sessions, 0 hours of class'],
        ];
    }

    /**
     * The summary says "session" and "hour" in the singular for exactly one.
     *
     * @dataProvider summary_cases
     * @param string $last Last class day; the first is Monday 2026-09-14.
     * @param float $hours Hours on Mondays.
     * @param string $summary Expected summary.
     */
    public function test_summary_uses_singular_and_plural(string $last, float $hours, string $summary): void {
        $this->resetAfterTest();

        $html = self::render('2026-09-14', $last, $hours);

        $this->assertStringContainsString('<p class="lead" data-region="calendar-summary">' . $summary . '</p>', $html);
    }

    /**
     * A holiday label with <, > and & is shown as typed, escaped.
     *
     * @group REQ-DAT-004
     */
    public function test_holiday_label_is_shown_as_typed_and_escaped(): void {
        $this->resetAfterTest();

        $html = self::render('2026-12-07', '2026-12-14', 2.0, "2026-12-07 Pont <- Immaculada & <b>festa</b>");

        $this->assertStringContainsString('Pont &lt;- Immaculada &amp; &lt;b&gt;festa&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>festa', $html);
    }

    /**
     * A plan with dates outside the limits shows a message instead of a calendar.
     *
     * @group REQ-CAL-004
     */
    public function test_dates_outside_the_limits_show_a_message(): void {
        $this->resetAfterTest();

        $html = self::render('2026-09-14', '9999-12-31', 2.0);

        $this->assertStringContainsString(get_string('calendar_out_of_range', 'local_classprogress'), $html);
        $this->assertStringNotContainsString('calendar-summary', $html);
    }
}
