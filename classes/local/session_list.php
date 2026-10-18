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
 * Which sessions of a plan are pending, upcoming or logged, and what a session carries over from the previous one.
 *
 * The caller passes "today" as the teacher's own day (see written_on::day()), so this reads no clock. Days are
 * compared as day numbers, never as text. Pure logic: no Moodle API.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_list {
    /** @var string Status of a plan whose sessions are logged; any other status (draft) has none. */
    public const APPROVED = 'approved';

    /**
     * Sorts the items of a plan into pending, upcoming and logged.
     *
     * @param string $planstatus The plan's status; only self::APPROVED gives sessions.
     * @param session_item[] $items The plan's items, in any order.
     * @param entry_data[] $entries The plan's entries, keyed by plan item id.
     * @param string $today The teacher's own day, an existing yyyy-mm-dd day.
     * @return session_groups The groups, each by date and then plan order.
     */
    public static function classify(string $planstatus, array $items, array $entries, string $today): session_groups {
        if ($planstatus !== self::APPROVED) {
            return new session_groups();
        }
        usort($items, fn(session_item $a, session_item $b): int =>
            [dates::day_number($a->date), $a->sortorder] <=> [dates::day_number($b->date), $b->sortorder]);
        $day = dates::day_number($today);
        $pending = $upcoming = $logged = [];
        foreach ($items as $item) {
            if (isset($entries[$item->id])) {
                $logged[] = $item;
            } else if (dates::day_number($item->date) <= $day) {
                $pending[] = $item;
            } else {
                $upcoming[] = $item;
            }
        }
        return new session_groups($pending, $upcoming, $logged);
    }

    /**
     * Gives what the previous item in the plan text left over for an item: the content of its entry, when partial
     * with carry-over.
     *
     * @param session_item[] $items The plan's items, in any order.
     * @param entry_data[] $entries The plan's entries, keyed by plan item id.
     * @param int $itemid The item being logged.
     * @return string|null The previous entry's content; null when there is nothing to carry over.
     */
    public static function left_over(array $items, array $entries, int $itemid): ?string {
        $current = null;
        foreach ($items as $item) {
            if ($item->id === $itemid) {
                $current = $item;
            }
        }
        if ($current === null) {
            return null;
        }
        $previous = null;
        foreach ($items as $item) {
            if ($item->sortorder < $current->sortorder && ($previous === null || $item->sortorder > $previous->sortorder)) {
                $previous = $item;
            }
        }
        $entry = $previous === null ? null : ($entries[$previous->id] ?? null);
        if ($entry === null || $entry->status !== entry_rules::PARTIAL || !$entry->carry) {
            return null;
        }
        return $entry->content;
    }
}
