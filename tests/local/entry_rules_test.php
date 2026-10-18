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
 * Tests for the rules an entry's data must follow: status, what was done, reason, carry-over and unplanned date.
 *
 * The school year in the unplanned cases runs from 2026-09-01 to 2027-06-30.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_rules_test extends \basic_testcase {
    /**
     * Checks an unplanned entry against the 2026-2027 school year used in these tests.
     *
     * @param entry_data $entry The entry.
     * @return string[] Issue codes.
     */
    private static function check_in_year(entry_data $entry): array {
        return entry_rules::check_unplanned($entry, '2026-09-01', '2027-06-30');
    }

    /**
     * Valid planned entries, one per planned status.
     *
     * @return array Cases: entry.
     */
    public static function valid_planned_provider(): array {
        return [
            'done' => [new entry_data(status: 'done', content: 'Exercises 1 to 4')],
            'partial' => [new entry_data(status: 'partial', content: 'Exercises 1 and 2', reason: 'incident')],
            'not done' => [new entry_data(status: 'not_done', reason: 'strike')],
        ];
    }

    /**
     * A planned entry takes done, partial or not done.
     *
     * @dataProvider valid_planned_provider
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-002
     * @param entry_data $entry A valid planned entry.
     */
    public function test_planned_entry_takes_done_partial_or_not_done(entry_data $entry): void {
        $this->assertSame([], entry_rules::check_planned($entry));
    }

    /**
     * Statuses a planned entry may not take.
     *
     * @return array Cases: status.
     */
    public static function bad_planned_status_provider(): array {
        return [
            'added belongs to unplanned sessions' => ['added'],
            'empty' => [''],
            'wrong case' => ['Done'],
            'unknown' => ['finished'],
        ];
    }

    /**
     * Any other status on a planned entry is refused, and nothing else is reported.
     *
     * @dataProvider bad_planned_status_provider
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-002
     * @param string $status The status.
     */
    public function test_planned_entry_refuses_other_statuses(string $status): void {
        $entry = new entry_data(status: $status);

        $this->assertSame([entry_rules::BAD_STATUS], entry_rules::check_planned($entry));
    }

    /**
     * An unplanned entry takes added and nothing else.
     *
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-002
     */
    public function test_unplanned_entry_takes_only_added(): void {
        $this->assertSame([], self::check_in_year(new entry_data(status: 'added', content: 'Revision', date: '2026-10-20')));
        foreach (['done', 'partial', 'not_done', ''] as $status) {
            $entry = new entry_data(status: $status, content: 'Revision', reason: 'other', date: '2026-10-20');
            $this->assertSame([entry_rules::BAD_STATUS], self::check_in_year($entry), $status);
        }
    }

    /**
     * What was done is required for done, partial and added; blank text counts as empty.
     *
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-002
     */
    public function test_what_was_done_is_required_except_for_not_done(): void {
        foreach (['', " \n\t "] as $content) {
            $done = new entry_data(status: 'done', content: $content);
            $partial = new entry_data(status: 'partial', content: $content, reason: 'trip');
            $added = new entry_data(status: 'added', content: $content, date: '2026-10-20');
            $notdone = new entry_data(status: 'not_done', content: $content, reason: 'trip');

            $this->assertSame([entry_rules::CONTENT_REQUIRED], entry_rules::check_planned($done));
            $this->assertSame([entry_rules::CONTENT_REQUIRED], entry_rules::check_planned($partial));
            $this->assertSame([entry_rules::CONTENT_REQUIRED], self::check_in_year($added));
            $this->assertSame([], entry_rules::check_planned($notdone));
        }
    }

    /**
     * Partial and not done require a reason.
     *
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-003
     */
    public function test_partial_and_not_done_require_a_reason(): void {
        $partial = new entry_data(status: 'partial', content: 'Half the exercises');
        $notdone = new entry_data(status: 'not_done');

        $this->assertSame([entry_rules::REASON_REQUIRED], entry_rules::check_planned($partial));
        $this->assertSame([entry_rules::REASON_REQUIRED], entry_rules::check_planned($notdone));
    }

    /**
     * The fixed reason codes.
     *
     * @return array Cases: reason.
     */
    public static function reason_provider(): array {
        return [
            'strike' => ['strike'],
            'incident' => ['incident'],
            'trip' => ['trip'],
            'school event' => ['school_event'],
            'teacher absent' => ['teacher_absent'],
            'other' => ['other'],
        ];
    }

    /**
     * Each fixed reason is accepted, with or without a note and a measure.
     *
     * @dataProvider reason_provider
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-003
     * @param string $reason A reason code.
     */
    public function test_each_fixed_reason_is_accepted_with_optional_note_and_measure(string $reason): void {
        $bare = new entry_data(status: 'not_done', reason: $reason);
        $full = new entry_data(status: 'partial', content: 'Half', reason: $reason, note: 'Fire drill', measure: 'Homework');

        $this->assertSame([], entry_rules::check_planned($bare));
        $this->assertSame([], entry_rules::check_planned($full));
    }

    /**
     * A reason outside the fixed codes is refused.
     *
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-003
     */
    public function test_unknown_reason_is_refused(): void {
        foreach (['Strike', 'holiday', 'vaga', ' other'] as $reason) {
            $entry = new entry_data(status: 'not_done', reason: $reason);
            $this->assertSame([entry_rules::BAD_REASON], entry_rules::check_planned($entry), $reason);
        }
    }

    /**
     * Several problems are all reported, in a fixed order.
     *
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-002
     * @group REQ-LOG-003
     */
    public function test_several_issues_are_reported_together(): void {
        $entry = new entry_data(status: 'partial', content: ' ', reason: 'nope');

        $this->assertSame([entry_rules::CONTENT_REQUIRED, entry_rules::BAD_REASON], entry_rules::check_planned($entry));
    }

    /**
     * Done and added keep no reason, note or measure; partial and not done keep them as typed.
     *
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-003
     */
    public function test_normalise_drops_reason_note_and_measure_for_done_and_added(): void {
        foreach (['done', 'added'] as $status) {
            $entry = new entry_data(status: $status, content: 'All', reason: 'trip', note: 'n', measure: 'm', date: '2026-10-20');

            $this->assertEquals(
                new entry_data(status: $status, content: 'All', date: '2026-10-20'),
                entry_rules::normalise($entry),
                $status,
            );
        }
        foreach (['partial', 'not_done'] as $status) {
            $entry = new entry_data(status: $status, content: ' <b>Half</b> ', reason: 'trip', note: ' n&', measure: 'm ');

            $this->assertEquals($entry, entry_rules::normalise($entry), $status);
        }
    }

    /**
     * Only a partial entry keeps "continue in the next session"; any other status drops it.
     *
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-006
     */
    public function test_normalise_keeps_carry_over_only_for_partial(): void {
        $partial = new entry_data(status: 'partial', content: 'Half', reason: 'incident', carry: true);
        $this->assertTrue(entry_rules::normalise($partial)->carry);

        foreach (['done', 'not_done', 'added'] as $status) {
            $entry = new entry_data(status: $status, content: 'All', reason: 'incident', carry: true);
            $this->assertFalse(entry_rules::normalise($entry)->carry, $status);
        }
    }

    /**
     * Unplanned dates with the issue each gives, the school year being 2026-09-01 to 2027-06-30.
     *
     * @return array Cases: date, expected issues.
     */
    public static function unplanned_date_provider(): array {
        return [
            'first day of the year' => ['2026-09-01', []],
            'last day of the year' => ['2027-06-30', []],
            'empty' => ['', [entry_rules::BAD_DATE]],
            'not ISO' => ['20/10/2026', [entry_rules::BAD_DATE]],
            'no such day' => ['2027-02-29', [entry_rules::BAD_DATE]],
            'trailing text' => ['2026-10-20x', [entry_rules::BAD_DATE]],
            'typo far in the future' => ['9027-01-01', [entry_rules::OUT_OF_RANGE]],
            'before 2000' => ['1999-12-31', [entry_rules::OUT_OF_RANGE]],
            'day before the year' => ['2026-08-31', [entry_rules::OUTSIDE_YEAR]],
            'day after the year' => ['2027-07-01', [entry_rules::OUTSIDE_YEAR]],
        ];
    }

    /**
     * An unplanned date must be an existing day inside the date window and the school year.
     *
     * @dataProvider unplanned_date_provider
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-007
     * @param string $date The unplanned session's date.
     * @param string[] $expected Expected issue codes.
     */
    public function test_unplanned_date_lies_inside_the_school_year(string $date, array $expected): void {
        $entry = new entry_data(status: 'added', content: 'Revision', date: $date);

        $this->assertSame($expected, self::check_in_year($entry));
    }

    /**
     * A planned entry takes its date from its plan item, so its own date is not checked.
     *
     * @covers \local_classprogress\local\entry_rules
     * @group REQ-LOG-007
     */
    public function test_planned_entry_date_is_not_checked(): void {
        $this->assertSame([], entry_rules::check_planned(new entry_data(status: 'done', content: 'All', date: 'whatever')));
    }
}
