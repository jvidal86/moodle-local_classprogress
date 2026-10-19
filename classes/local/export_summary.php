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
 * The summary of an export: the state of each planned day, the figures of a set of sessions, and the overall, per
 * block and per evaluation period tables (REQ-EXP-004 to 006).
 *
 * Every planned day is in exactly one of five states (done, partly done, not done, not logged, upcoming); unplanned
 * sessions are added and never planned. Coverage is (done + ½ partly done) ÷ (every planned day but the upcoming
 * ones), a whole percentage rounded half up; hours are counted in hundredths and rounded half up to two decimals, a
 * partly done day giving half its hours to done and half to not done. The caller passes "today" as the teacher's own
 * day (see written_on::day()), so this reads no clock. Pure logic: no Moodle API.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class export_summary {
    /** @var string State: the planned day's entry says done. */
    public const DONE = entry_rules::DONE;

    /** @var string State: the planned day's entry says partly done. */
    public const PARTIAL = entry_rules::PARTIAL;

    /** @var string State: the planned day's entry says not done, also when logged in advance. */
    public const NOT_DONE = entry_rules::NOT_DONE;

    /** @var string State: no entry, dated today or earlier. */
    public const NOT_LOGGED = 'not_logged';

    /** @var string State: no entry, dated after today. */
    public const UPCOMING = 'upcoming';

    /** @var string An unplanned session, counted apart from the planned days. */
    public const ADDED = entry_rules::ADDED;

    /** @var string Group: every session. */
    public const GROUP_ALL = 'all';

    /** @var string Group: the planned days of one RA or block. */
    public const GROUP_BLOCK = 'block';

    /** @var string Group: the planned days without an RA or block. */
    public const GROUP_NO_BLOCK = 'no_block';

    /** @var string Group: the unplanned sessions. */
    public const GROUP_UNPLANNED = 'unplanned';

    /** @var string Group: the sessions of one evaluation period. */
    public const GROUP_PERIOD = 'period';

    /** @var string Group: the sessions outside every evaluation period. */
    public const GROUP_OUTSIDE = 'outside';

    /**
     * Gives the state of a planned day.
     *
     * @param string|null $entrystatus The status of the day's entry; null when it has none.
     * @param string $date The planned day, an existing yyyy-mm-dd day.
     * @param string $today The teacher's own day, an existing yyyy-mm-dd day.
     * @return string The entry's status when there is an entry; otherwise NOT_LOGGED up to today, UPCOMING after.
     */
    public static function state(?string $entrystatus, string $date, string $today): string {
        if ($entrystatus !== null) {
            return $entrystatus;
        }
        return dates::day_number($date) <= dates::day_number($today) ? self::NOT_LOGGED : self::UPCOMING;
    }

    /**
     * Computes the summary figures of a set of sessions.
     *
     * @param export_session[] $sessions The sessions.
     * @return summary_figures The figures.
     */
    public static function figures(array $sessions): summary_figures {
        $count = array_fill_keys([self::DONE, self::PARTIAL, self::NOT_DONE, self::NOT_LOGGED, self::UPCOMING,
            self::ADDED], 0);
        $cents = array_fill_keys(array_keys($count), 0);
        $late = $most = 0;
        foreach ($sessions as $session) {
            $count[$session->state]++;
            $cents[$session->state] += (int) round(($session->hours ?? 0) * 100);
            if ($session->dayslate > 0) {
                $late++;
                $most = max($most, $session->dayslate);
            }
        }
        $planned = array_sum($count) - $count[self::ADDED];
        $due = $planned - $count[self::UPCOMING];
        // Twice the covered days, so that a partly done day counts half without leaving the integers.
        $covered = 2 * $count[self::DONE] + $count[self::PARTIAL];
        return new summary_figures(
            planned: $planned,
            done: $count[self::DONE],
            partial: $count[self::PARTIAL],
            notdone: $count[self::NOT_DONE],
            notlogged: $count[self::NOT_LOGGED],
            upcoming: $count[self::UPCOMING],
            added: $count[self::ADDED],
            coverage: $due === 0 ? null : intdiv(200 * $covered + 2 * $due, 4 * $due),
            plannedhours: self::hours(2 * (array_sum($cents) - $cents[self::ADDED])),
            hoursdone: self::hours(2 * $cents[self::DONE] + $cents[self::PARTIAL]),
            hoursnotdone: self::hours(2 * $cents[self::NOT_DONE] + $cents[self::PARTIAL]),
            writtenlate: $late,
            mostdayslate: $most,
        );
    }

    /**
     * The overall table: one row of every session.
     *
     * @param export_session[] $sessions The sessions.
     * @return summary_group[] The one row.
     */
    public static function overall(array $sessions): array {
        return [new summary_group(self::GROUP_ALL, '', self::figures($sessions))];
    }

    /**
     * The per block table: one row per RA or block in order of first appearance, then the planned days without one
     * and the unplanned sessions, each when there are any.
     *
     * @param export_session[] $sessions The sessions, in the order of the Plan vs done rows.
     * @return summary_group[] The rows.
     */
    public static function per_block(array $sessions): array {
        $blocks = $noblock = $unplanned = [];
        foreach ($sessions as $session) {
            if ($session->block === null) {
                $unplanned[] = $session;
            } else if ($session->block === '') {
                $noblock[] = $session;
            } else {
                // A string key, so that a block typed as a number keeps its place.
                $blocks['b' . $session->block][] = $session;
            }
        }
        $groups = [];
        foreach ($blocks as $key => $own) {
            $groups[] = new summary_group(self::GROUP_BLOCK, substr($key, 1), self::figures($own));
        }
        if ($noblock) {
            $groups[] = new summary_group(self::GROUP_NO_BLOCK, '', self::figures($noblock));
        }
        if ($unplanned) {
            $groups[] = new summary_group(self::GROUP_UNPLANNED, '', self::figures($unplanned));
        }
        return $groups;
    }

    /**
     * The per evaluation period table: one row per period in date order, also for a period without sessions, then
     * the sessions outside every period when there are any. A session belongs to the first period, in date order,
     * whose first and last days include it.
     *
     * @param export_session[] $sessions The sessions.
     * @param \stdClass[] $periods The periods, with name, startdate and enddate (existing yyyy-mm-dd days), in any order.
     * @return summary_group[] The rows.
     */
    public static function per_period(array $sessions, array $periods): array {
        $periods = self::sorted_periods($periods);
        $own = array_fill(0, count($periods), []);
        $outside = [];
        foreach ($sessions as $session) {
            $index = self::period_index($session->date, $periods);
            if ($index === null) {
                $outside[] = $session;
            } else {
                $own[$index][] = $session;
            }
        }
        $groups = [];
        foreach ($periods as $index => $period) {
            $groups[] = new summary_group(self::GROUP_PERIOD, (string) $period->name, self::figures($own[$index]));
        }
        if ($outside) {
            $groups[] = new summary_group(self::GROUP_OUTSIDE, '', self::figures($outside));
        }
        return $groups;
    }

    /**
     * Gives the evaluation period a day belongs to.
     *
     * @param string $date An existing yyyy-mm-dd day.
     * @param \stdClass[] $periods The periods, with name, startdate and enddate, in any order.
     * @return string|null The name of the first period, in date order, that includes the day; null for none.
     */
    public static function period_of(string $date, array $periods): ?string {
        $periods = self::sorted_periods($periods);
        $index = self::period_index($date, $periods);
        return $index === null ? null : (string) $periods[$index]->name;
    }

    /**
     * Sorts evaluation periods by date: first day, then last day, then as given.
     *
     * @param \stdClass[] $periods The periods.
     * @return \stdClass[] The periods, indexed from 0.
     */
    private static function sorted_periods(array $periods): array {
        $periods = array_values($periods);
        usort($periods, fn(\stdClass $a, \stdClass $b): int =>
            [dates::day_number($a->startdate), dates::day_number($a->enddate)]
            <=> [dates::day_number($b->startdate), dates::day_number($b->enddate)]);
        return $periods;
    }

    /**
     * Finds the first sorted period that includes a day.
     *
     * @param string $date An existing yyyy-mm-dd day.
     * @param \stdClass[] $periods The periods, sorted by sorted_periods().
     * @return int|null The period's index; null for none.
     */
    private static function period_index(string $date, array $periods): ?int {
        foreach ($periods as $index => $period) {
            if (dates::within($date, $period->startdate, $period->enddate)) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Turns half-hundredths of an hour into hours, rounded half up to two decimals.
     *
     * @param int $halfcents Hours times 200.
     * @return float The hours.
     */
    private static function hours(int $halfcents): float {
        return round(intdiv($halfcents + 1, 2) / 100, 2);
    }
}
