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
 * Tests for the sessions of a plan: pending, upcoming and logged, and the left-over carried into a session.
 *
 * "Today" is always given explicitly: 2026-10-20 in these tests.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_list_test extends \basic_testcase {
    /** @var string The day the tests run on. */
    private const TODAY = '2026-10-20';

    /**
     * Item ids, for compact assertions.
     *
     * @param session_item[] $items Items.
     * @return int[] Their ids, in order.
     */
    private static function ids(array $items): array {
        return array_map(fn(session_item $item): int => $item->id, $items);
    }

    /**
     * A plan of five items, given out of order: two past, today, two future.
     *
     * @return session_item[] The items.
     */
    private static function plan(): array {
        return [
            new session_item(id: 15, date: '2026-10-27', sortorder: 5),
            new session_item(id: 12, date: '2026-10-14', sortorder: 2),
            new session_item(id: 13, date: '2026-10-20', sortorder: 3),
            new session_item(id: 11, date: '2026-10-13', sortorder: 1),
            new session_item(id: 14, date: '2026-10-21', sortorder: 4),
        ];
    }

    /**
     * Items up to today without an entry are pending oldest first; items after today are upcoming.
     *
     * @covers \local_classprogress\local\session_list
     * @group REQ-LOG-001
     */
    public function test_items_up_to_today_are_pending_and_later_items_upcoming(): void {
        $groups = session_list::classify(session_list::APPROVED, self::plan(), [], self::TODAY);

        $this->assertSame([11, 12, 13], self::ids($groups->pending));
        $this->assertSame([14, 15], self::ids($groups->upcoming));
        $this->assertSame([], $groups->logged);
    }

    /**
     * An item with an entry is logged, not pending, whatever its date.
     *
     * @covers \local_classprogress\local\session_list
     * @group REQ-LOG-001
     */
    public function test_items_with_an_entry_are_logged(): void {
        $entries = [
            12 => new entry_data(status: 'done', content: 'All'),
            11 => new entry_data(status: 'not_done', reason: 'strike'),
            14 => new entry_data(status: 'done', content: 'Written in advance'),
        ];

        $groups = session_list::classify(session_list::APPROVED, self::plan(), $entries, self::TODAY);

        $this->assertSame([13], self::ids($groups->pending));
        $this->assertSame([15], self::ids($groups->upcoming));
        $this->assertSame([11, 12, 14], self::ids($groups->logged));
    }

    /**
     * Pending items are ordered by date even when the plan text has them out of order, then by plan order.
     *
     * @covers \local_classprogress\local\session_list
     * @group REQ-LOG-001
     */
    public function test_pending_is_oldest_first_then_plan_order(): void {
        $items = [
            new session_item(id: 1, date: '2026-10-19', sortorder: 1),
            new session_item(id: 2, date: '2026-09-30', sortorder: 2),
            new session_item(id: 4, date: '2026-10-05', sortorder: 4),
            new session_item(id: 3, date: '2026-10-05', sortorder: 3),
        ];

        $groups = session_list::classify(session_list::APPROVED, $items, [], self::TODAY);

        $this->assertSame([2, 3, 4, 1], self::ids($groups->pending));
    }

    /**
     * A draft plan has nothing pending, upcoming or logged.
     *
     * @covers \local_classprogress\local\session_list
     * @group REQ-LOG-008
     * @group REQ-LOG-001
     */
    public function test_draft_plan_has_no_sessions_to_log(): void {
        $entries = [11 => new entry_data(status: 'done', content: 'All')];

        $groups = session_list::classify('draft', self::plan(), $entries, self::TODAY);

        $this->assertSame([], $groups->pending);
        $this->assertSame([], $groups->upcoming);
        $this->assertSame([], $groups->logged);
    }

    /**
     * The editor of a session shows what the previous item of the plan left over when it was partial with carry-over.
     *
     * @covers \local_classprogress\local\session_list
     * @group REQ-LOG-006
     */
    public function test_partial_with_carry_over_passes_its_content_to_the_next_item(): void {
        $entries = [12 => new entry_data(status: 'partial', content: 'Exercises 1 and 2', reason: 'incident', carry: true)];

        $this->assertSame('Exercises 1 and 2', session_list::left_over(self::plan(), $entries, 13));
        $this->assertNull(session_list::left_over(self::plan(), $entries, 14));
        $this->assertNull(session_list::left_over(self::plan(), $entries, 12));
    }

    /**
     * Previous entries that pass nothing on: no entry, partial without carry-over, other statuses.
     *
     * @return array Cases: entries keyed by item id.
     */
    public static function no_left_over_provider(): array {
        return [
            'no entry' => [[]],
            'partial without carry-over' => [[12 => new entry_data(status: 'partial', content: 'Half', reason: 'trip')]],
            'done' => [[12 => new entry_data(status: 'done', content: 'All', carry: true)]],
            'not done' => [[12 => new entry_data(status: 'not_done', reason: 'trip', carry: true)]],
        ];
    }

    /**
     * Without a partial entry with carry-over on the previous item, nothing is left over.
     *
     * @dataProvider no_left_over_provider
     * @covers \local_classprogress\local\session_list
     * @group REQ-LOG-006
     * @param entry_data[] $entries Entries keyed by item id.
     */
    public function test_nothing_is_left_over_without_partial_carry_over(array $entries): void {
        $this->assertNull(session_list::left_over(self::plan(), $entries, 13));
    }

    /**
     * The first item of the plan, and an id that is not in the plan, get nothing.
     *
     * @covers \local_classprogress\local\session_list
     * @group REQ-LOG-006
     */
    public function test_first_or_unknown_item_gets_nothing(): void {
        $entries = [15 => new entry_data(status: 'partial', content: 'Half', reason: 'trip', carry: true)];

        $this->assertNull(session_list::left_over(self::plan(), $entries, 11));
        $this->assertNull(session_list::left_over(self::plan(), $entries, 99));
    }

    /**
     * The previous item is the previous one in the plan text, even when the plan's dates are out of order.
     *
     * @covers \local_classprogress\local\session_list
     * @group REQ-LOG-006
     */
    public function test_previous_item_follows_plan_order(): void {
        $items = [
            new session_item(id: 1, date: '2026-10-05', sortorder: 1),
            new session_item(id: 2, date: '2026-10-19', sortorder: 2),
            new session_item(id: 3, date: '2026-10-12', sortorder: 3),
        ];
        $entries = [
            1 => new entry_data(status: 'partial', content: 'From the first', reason: 'trip', carry: true),
            2 => new entry_data(status: 'partial', content: 'From the second', reason: 'trip', carry: true),
        ];

        $this->assertSame('From the second', session_list::left_over($items, $entries, 3));
        $this->assertSame('From the first', session_list::left_over($items, $entries, 2));
    }
}
