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
 * Tests for the export summary: the five states of a planned day, the summary figures, and the overall, per block and
 * per evaluation period tables.
 *
 * "Today" is always given explicitly: 2026-10-20 in these tests.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\local\export_summary
 * @covers     \local_classprogress\local\export_session
 * @covers     \local_classprogress\local\summary_figures
 * @covers     \local_classprogress\local\summary_group
 */
final class export_summary_test extends \basic_testcase {
    /** @var string The day the tests run on. */
    private const TODAY = '2026-10-20';

    /**
     * A planned day.
     *
     * @param string $date The day.
     * @param string $state Its state.
     * @param string $block Its block; empty for none.
     * @param float|null $hours Planned hours; null when unknown.
     * @param int|null $dayslate Days late of its entry; null without an entry.
     * @return export_session The session.
     */
    private static function day(
        string $date,
        string $state,
        string $block = 'RA1',
        ?float $hours = 2.0,
        ?int $dayslate = null
    ): export_session {
        $logged = !in_array($state, [export_summary::NOT_LOGGED, export_summary::UPCOMING], true);
        return new export_session($date, $state, $block, $hours, $dayslate ?? ($logged ? 0 : null));
    }

    /**
     * An unplanned session.
     *
     * @param string $date The day.
     * @param int $dayslate Days late of its entry.
     * @return export_session The session.
     */
    private static function added(string $date, int $dayslate = 0): export_session {
        return new export_session($date, export_summary::ADDED, null, null, $dayslate);
    }

    /**
     * A plan of every kind of session, in the order of the Plan vs done rows: RA1 (done, partly done 1.5 h, not
     * done in advance, not logged), a day with no block, RA2 (done 3 days late, not logged today, upcoming) and two
     * unplanned sessions, one written 5 days late.
     *
     * @return export_session[] The sessions.
     */
    private static function plan(): array {
        return [
            self::day('2026-09-14', export_summary::DONE),
            self::day('2026-09-21', export_summary::PARTIAL, 'RA1', 1.5),
            self::added('2026-09-22'),
            self::day('2026-09-28', export_summary::NOT_LOGGED),
            self::day('2026-10-05', export_summary::DONE, 'RA2', 2.0, 3),
            self::day('2026-10-06', export_summary::NOT_DONE, ''),
            self::added('2026-10-07', 5),
            self::day('2026-10-20', export_summary::NOT_LOGGED, 'RA2'),
            self::day('2026-10-26', export_summary::NOT_DONE, 'RA1'),
            self::day('2026-10-27', export_summary::UPCOMING, 'RA2', 1.0),
        ];
    }

    /**
     * Evaluation periods, given out of date order; the third has no sessions.
     *
     * @return \stdClass[] Periods with name, startdate and enddate.
     */
    private static function periods(): array {
        return [
            (object) ['name' => '2a avaluació', 'startdate' => '2026-10-06', 'enddate' => '2026-10-20'],
            (object) ['name' => '1a avaluació', 'startdate' => '2026-09-14', 'enddate' => '2026-10-05'],
            (object) ['name' => '3a avaluació', 'startdate' => '2027-01-11', 'enddate' => '2027-03-26'],
        ];
    }

    /**
     * The figures as an array, for compact assertions.
     *
     * @param summary_figures $figures The figures.
     * @return array Field => value.
     */
    private static function values(summary_figures $figures): array {
        return get_object_vars($figures);
    }

    /**
     * Group kinds and names, for compact assertions.
     *
     * @param summary_group[] $groups The groups.
     * @return string[] "kind:name" for each.
     */
    private static function names(array $groups): array {
        return array_map(fn(summary_group $group): string => $group->kind . ':' . $group->name, $groups);
    }

    /**
     * Entry statuses, dates and the state each gives.
     *
     * @return array
     */
    public static function state_provider(): array {
        return [
            'done in the past' => ['done', '2026-10-01', export_summary::DONE],
            'partly done today' => ['partial', '2026-10-20', export_summary::PARTIAL],
            'not done in the past' => ['not_done', '2026-10-01', export_summary::NOT_DONE],
            'cancelled in advance' => ['not_done', '2026-11-03', export_summary::NOT_DONE],
            'no entry in the past' => [null, '2026-10-19', export_summary::NOT_LOGGED],
            'no entry today' => [null, '2026-10-20', export_summary::NOT_LOGGED],
            'no entry tomorrow' => [null, '2026-10-21', export_summary::UPCOMING],
            'no entry next year' => [null, '2027-01-04', export_summary::UPCOMING],
        ];
    }

    /**
     * A planned day takes its entry's status whatever its date; without an entry it is not logged up to today and
     * upcoming after today.
     *
     * @group REQ-EXP-004
     * @dataProvider state_provider
     * @param string|null $status The entry's status; null without an entry.
     * @param string $date The planned day.
     * @param string $expected The state.
     */
    public function test_a_planned_day_is_in_one_of_five_states(?string $status, string $date, string $expected): void {
        $this->assertSame($expected, export_summary::state($status, $date, self::TODAY));
    }

    /**
     * Planned counts every planned day once, in one of the five states; unplanned sessions are counted apart as added.
     *
     * @group REQ-EXP-004
     */
    public function test_planned_is_the_sum_of_the_five_states_and_added_is_apart(): void {
        $figures = export_summary::figures(self::plan());

        $this->assertSame(8, $figures->planned);
        $this->assertSame(
            $figures->planned,
            $figures->done + $figures->partial + $figures->notdone + $figures->notlogged + $figures->upcoming
        );
        $this->assertSame([2, 1, 2, 2, 1, 2], [$figures->done, $figures->partial, $figures->notdone,
            $figures->notlogged, $figures->upcoming, $figures->added]);
    }

    /**
     * The summary figures: coverage counts a partly done day as half and leaves out the upcoming days; hours of a
     * partly done day count half as done and half as not done; written late counts planned and unplanned entries.
     *
     * @group REQ-EXP-005
     */
    public function test_the_figures_of_a_plan(): void {
        $figures = export_summary::figures(self::plan());

        $this->assertSame([
            'planned' => 8,
            'done' => 2,
            'partial' => 1,
            'notdone' => 2,
            'notlogged' => 2,
            'upcoming' => 1,
            'added' => 2,
            // Coverage: (2 + 0.5) / 7 = 35.7 %.
            'coverage' => 36,
            'plannedhours' => 14.5,
            'hoursdone' => 4.75,
            'hoursnotdone' => 4.75,
            'writtenlate' => 2,
            'mostdayslate' => 5,
        ], self::values($figures));
    }

    /**
     * Coverage is empty when no planned day is due, and the hours and late figures are 0 when there is nothing.
     *
     * @group REQ-EXP-005
     */
    public function test_coverage_is_empty_without_days_due(): void {
        $none = export_summary::figures([]);
        $future = export_summary::figures([self::day('2026-10-27', export_summary::UPCOMING), self::added('2026-10-01')]);

        $this->assertNull($none->coverage);
        $this->assertSame([0, 0.0, 0.0, 0.0, 0, 0], [$none->planned, $none->plannedhours, $none->hoursdone,
            $none->hoursnotdone, $none->writtenlate, $none->mostdayslate]);
        $this->assertNull($future->coverage);
        $this->assertSame([1, 1, 2.0], [$future->planned, $future->added, $future->plannedhours]);
    }

    /**
     * Days, and the coverage they give, rounded half up to a whole percentage.
     *
     * @return array
     */
    public static function coverage_provider(): array {
        $done = export_summary::DONE;
        $partial = export_summary::PARTIAL;
        $notdone = export_summary::NOT_DONE;
        $notlogged = export_summary::NOT_LOGGED;
        return [
            'all done' => [[$done, $done], 100],
            'one half' => [[$partial], 50],
            'two of three' => [[$done, $done, $notdone], 67],
            'one of three' => [[$done, $notlogged, $notdone], 33],
            // 1 / 8 = 12.5 %.
            'exactly half a point' => [[$done, $notdone, $notdone, $notdone, $notdone, $notdone, $notdone, $notlogged], 13],
            // 0.5 / 8 = 6.25 %.
            'a quarter point' => [[$partial, $notdone, $notdone, $notdone, $notdone, $notdone, $notdone, $notlogged], 6],
            'nothing done' => [[$notdone, $notlogged], 0],
        ];
    }

    /**
     * Coverage is a whole percentage, rounded half up.
     *
     * @group REQ-EXP-005
     * @dataProvider coverage_provider
     * @param string[] $states States of past planned days.
     * @param int $expected Coverage.
     */
    public function test_coverage_is_rounded_half_up(array $states, int $expected): void {
        $days = array_map(fn(string $state): export_session => self::day('2026-10-01', $state), $states);

        $this->assertSame($expected, export_summary::figures($days)->coverage);
    }

    /**
     * Hours are exact, with up to three decimals (half of a partly done day's two-decimal hours), and a day without
     * hours counts none.
     *
     * @group REQ-EXP-005
     */
    public function test_hours_are_exact_to_three_decimals(): void {
        $figures = export_summary::figures([
            self::day('2026-10-01', export_summary::PARTIAL, 'RA1', 1.25),
            self::day('2026-10-02', export_summary::DONE, 'RA1', 0.1),
            self::day('2026-10-05', export_summary::DONE, 'RA1', 0.2),
            self::day('2026-10-06', export_summary::NOT_LOGGED, 'RA1', null),
        ]);

        $this->assertSame(1.55, $figures->plannedhours);
        // 0.1 + 0.2 + 0.625 = 0.925.
        $this->assertSame(0.925, $figures->hoursdone);
        $this->assertSame(0.625, $figures->hoursnotdone);
    }

    /**
     * Partly done days of 1.25, 0.25 and 1.75 hours: hours are exact to three decimals, so the hours of the per block
     * and per period rows add up exactly to overall.
     *
     * @group REQ-EXP-005
     * @group REQ-EXP-006
     */
    public function test_hours_of_the_rows_add_up_exactly(): void {
        $sessions = [
            self::day('2026-09-30', export_summary::PARTIAL, 'RA1', 1.25),
            self::day('2026-10-01', export_summary::PARTIAL, 'RA1', 0.25),
            self::day('2026-10-02', export_summary::PARTIAL, 'RA2', 1.75),
            self::day('2026-10-05', export_summary::DONE, 'RA2', 0.05),
            self::day('2026-10-21', export_summary::PARTIAL, '', 1.25),
        ];
        $periods = [
            (object) ['name' => 'A', 'startdate' => '2026-09-14', 'enddate' => '2026-10-01'],
            (object) ['name' => 'B', 'startdate' => '2026-10-02', 'enddate' => '2026-10-20'],
        ];

        $overall = export_summary::overall($sessions)[0]->figures;
        $tables = ['per block' => export_summary::per_block($sessions),
            'per period' => export_summary::per_period($sessions, $periods)];

        $this->assertSame([4.55, 2.3, 2.25], [$overall->plannedhours, $overall->hoursdone, $overall->hoursnotdone]);
        $this->assertSame([0.75, 0.75], [$tables['per block'][0]->figures->hoursdone,
            $tables['per block'][0]->figures->hoursnotdone]);
        $this->assertSame([0.625, 0.625], [$tables['per block'][2]->figures->hoursdone,
            $tables['per block'][2]->figures->hoursnotdone]);
        $this->assertSame([0.925, 0.875], [$tables['per period'][1]->figures->hoursdone,
            $tables['per period'][1]->figures->hoursnotdone]);
        foreach ($tables as $name => $groups) {
            foreach (['plannedhours', 'hoursdone', 'hoursnotdone'] as $column) {
                // In thousandths of an hour, so that the sum is compared exactly.
                $thousandths = fn(summary_group $group): int => (int) round($group->figures->$column * 1000);
                $sum = array_sum(array_map($thousandths, $groups));
                $this->assertSame((int) round($overall->$column * 1000), $sum, "{$name}: {$column}");
            }
        }
    }

    /**
     * The overall table has one row of every session.
     *
     * @group REQ-EXP-006
     */
    public function test_overall_is_one_row_of_all_sessions(): void {
        $groups = export_summary::overall(self::plan());

        $this->assertSame([export_summary::GROUP_ALL . ':'], self::names($groups));
        $this->assertEquals(export_summary::figures(self::plan()), $groups[0]->figures);
    }

    /**
     * The per block table has one row per block in order of first appearance, then No block and Unplanned sessions.
     *
     * @group REQ-EXP-006
     */
    public function test_per_block_follows_the_order_of_first_appearance(): void {
        $groups = export_summary::per_block(self::plan());

        $this->assertSame(['block:RA1', 'block:RA2', 'no_block:', 'unplanned:'], self::names($groups));
        $this->assertSame([4, 1, 1, 1, 1, 0, 0], self::counts($groups[0]->figures));
        $this->assertSame([3, 1, 0, 0, 1, 1, 0], self::counts($groups[1]->figures));
        $this->assertSame([1, 0, 0, 1, 0, 0, 0], self::counts($groups[2]->figures));
        $this->assertSame([0, 0, 0, 0, 0, 0, 2], self::counts($groups[3]->figures));
        $this->assertSame([1, 5], [$groups[3]->figures->writtenlate, $groups[3]->figures->mostdayslate]);
        $this->assertNull($groups[3]->figures->coverage);
    }

    /**
     * No block and Unplanned sessions appear only when there are such sessions.
     *
     * @group REQ-EXP-006
     */
    public function test_per_block_leaves_out_empty_groups(): void {
        $groups = export_summary::per_block([self::day('2026-09-14', export_summary::DONE, 'Bloc A · RA1')]);

        $this->assertSame(['block:Bloc A · RA1'], self::names($groups));
        $this->assertSame([], export_summary::per_block([]));
    }

    /**
     * The per period table has one row per period in date order, including both its days, with zeros for a period
     * without sessions, then the sessions outside every period; unplanned sessions go by their date.
     *
     * @group REQ-EXP-006
     */
    public function test_per_period_follows_the_dates_of_the_periods(): void {
        $groups = export_summary::per_period(self::plan(), self::periods());

        $expected = ['period:1a avaluació', 'period:2a avaluació', 'period:3a avaluació', 'outside:'];
        $this->assertSame($expected, self::names($groups));
        $this->assertSame([4, 2, 1, 0, 1, 0, 1], self::counts($groups[0]->figures));
        $this->assertSame([2, 0, 0, 1, 1, 0, 1], self::counts($groups[1]->figures));
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], self::counts($groups[2]->figures));
        $this->assertSame([2, 0, 0, 1, 0, 1, 0], self::counts($groups[3]->figures));
        $this->assertNull($groups[2]->figures->coverage);
        $this->assertSame(0.0, $groups[2]->figures->plannedhours);
    }

    /**
     * Outside evaluation periods appears only when a session lies outside every period, and a session in two
     * overlapping periods counts in the first one by date.
     *
     * @group REQ-EXP-006
     */
    public function test_per_period_leaves_out_an_empty_outside_row(): void {
        $periods = [
            (object) ['name' => 'B', 'startdate' => '2026-10-01', 'enddate' => '2026-12-22'],
            (object) ['name' => 'A', 'startdate' => '2026-09-14', 'enddate' => '2026-10-15'],
        ];

        $groups = export_summary::per_period([self::day('2026-10-05', export_summary::DONE)], $periods);

        $this->assertSame(['period:A', 'period:B'], self::names($groups));
        $this->assertSame(1, $groups[0]->figures->planned);
        $this->assertSame(0, $groups[1]->figures->planned);
        $this->assertSame(['outside:'], self::names(export_summary::per_period([self::added('2026-10-05')], [])));
    }

    /**
     * A day's evaluation period, for its row, is the first period in date order whose first and last days include it.
     *
     * @group REQ-EXP-003
     * @group REQ-EXP-006
     */
    public function test_a_day_belongs_to_the_first_period_that_includes_it(): void {
        $overlapping = [
            (object) ['name' => 'B', 'startdate' => '2026-10-01', 'enddate' => '2026-12-22'],
            (object) ['name' => 'A', 'startdate' => '2026-09-14', 'enddate' => '2026-10-15'],
        ];

        $this->assertNull(export_summary::period_of('2026-09-13', self::periods()));
        $this->assertSame('1a avaluació', export_summary::period_of('2026-09-14', self::periods()));
        $this->assertSame('1a avaluació', export_summary::period_of('2026-10-05', self::periods()));
        $this->assertSame('2a avaluació', export_summary::period_of('2026-10-06', self::periods()));
        $this->assertSame('2a avaluació', export_summary::period_of('2026-10-20', self::periods()));
        $this->assertNull(export_summary::period_of('2026-10-21', self::periods()));
        $this->assertSame('A', export_summary::period_of('2026-10-05', $overlapping));
        $this->assertSame('B', export_summary::period_of('2026-10-16', $overlapping));
    }

    /**
     * In each table the rows add up to the overall row in every count and hours column.
     *
     * @group REQ-EXP-006
     */
    public function test_the_rows_of_each_table_add_up_to_the_overall_row(): void {
        $overall = self::values(export_summary::overall(self::plan())[0]->figures);
        $tables = [
            'per block' => export_summary::per_block(self::plan()),
            'per period' => export_summary::per_period(self::plan(), self::periods()),
        ];

        $columns = ['planned', 'done', 'partial', 'notdone', 'notlogged', 'upcoming', 'added', 'writtenlate',
            'plannedhours', 'hoursdone', 'hoursnotdone'];
        foreach ($tables as $name => $groups) {
            foreach ($columns as $column) {
                $sum = array_sum(array_map(fn(summary_group $group) => $group->figures->$column, $groups));
                $this->assertEqualsWithDelta($overall[$column], $sum, 0.001, "{$name}: {$column}");
            }
            $most = max(array_map(fn(summary_group $group): int => $group->figures->mostdayslate, $groups));
            $this->assertSame($overall['mostdayslate'], $most, "{$name}: mostdayslate");
        }
    }

    /**
     * The counts of a row, for compact assertions.
     *
     * @param summary_figures $figures The figures.
     * @return int[] Planned, done, partly done, not done, not logged, upcoming, added.
     */
    private static function counts(summary_figures $figures): array {
        return [$figures->planned, $figures->done, $figures->partial, $figures->notdone, $figures->notlogged,
            $figures->upcoming, $figures->added];
    }
}
