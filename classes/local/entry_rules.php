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
 * The rules an entry's data follows, as issue codes, and the form it is stored in.
 *
 * Codes are stable identifiers (for example for language strings), never sentences. Text is checked, never changed,
 * except that normalise() empties the fields a status does not keep. Pure logic: no Moodle API, no clock.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_rules {
    /** @var string Status: the planned session was done. */
    public const DONE = 'done';

    /** @var string Status: the planned session was partly done. */
    public const PARTIAL = 'partial';

    /** @var string Status: the planned session was not done. */
    public const NOT_DONE = 'not_done';

    /** @var string Status: an unplanned session. */
    public const ADDED = 'added';

    /** @var string[] Reason codes for a partial or not done session. */
    public const REASONS = ['strike', 'incident', 'trip', 'school_event', 'teacher_absent', 'other'];

    /** @var string Issue: the status does not fit the session (planned or unplanned). Other checks are skipped. */
    public const BAD_STATUS = 'bad_status';

    /** @var string Issue: what was done is empty or blank, and the status is not not done. */
    public const CONTENT_REQUIRED = 'content_required';

    /** @var string Issue: a partial or not done session has no reason. */
    public const REASON_REQUIRED = 'reason_required';

    /** @var string Issue: a partial or not done session has a reason outside REASONS. */
    public const BAD_REASON = 'bad_reason';

    /** @var string Issue: an unplanned date is not an existing day written yyyy-mm-dd. */
    public const BAD_DATE = 'bad_date';

    /** @var string Issue: an unplanned date lies outside dates::FIRST_DAY to dates::LAST_DAY. */
    public const OUT_OF_RANGE = 'out_of_range';

    /** @var string Issue: an unplanned date lies outside the school year. */
    public const OUTSIDE_YEAR = 'outside_year';

    /**
     * Checks an entry for a plan item: done, partial or not done. Its date comes from the item and is not checked.
     *
     * @param entry_data $entry The entry as typed.
     * @return string[] Issue codes in a fixed order (status, content, reason); empty when valid.
     */
    public static function check_planned(entry_data $entry): array {
        if (!in_array($entry->status, [self::DONE, self::PARTIAL, self::NOT_DONE], true)) {
            return [self::BAD_STATUS];
        }
        return self::check_text($entry);
    }

    /**
     * Checks an entry for an unplanned session: added, on a day inside the date window and the school year.
     *
     * @param entry_data $entry The entry as typed.
     * @param string $yearfirst First day of the school year, an existing yyyy-mm-dd day.
     * @param string $yearlast Last day of the school year, an existing yyyy-mm-dd day.
     * @return string[] Issue codes in a fixed order (status, content, date); empty when valid.
     */
    public static function check_unplanned(entry_data $entry, string $yearfirst, string $yearlast): array {
        if ($entry->status !== self::ADDED) {
            return [self::BAD_STATUS];
        }
        $issues = self::check_text($entry);
        if (!dates::is_date($entry->date)) {
            $issues[] = self::BAD_DATE;
        } else if (!dates::in_window($entry->date)) {
            $issues[] = self::OUT_OF_RANGE;
        } else if (!dates::within($entry->date, $yearfirst, $yearlast)) {
            $issues[] = self::OUTSIDE_YEAR;
        }
        return $issues;
    }

    /**
     * Gives the entry as stored: done and added keep no reason, note or measure; only partial keeps carry-over.
     *
     * @param entry_data $entry The entry as typed, with a valid status.
     * @return entry_data The entry to store.
     */
    public static function normalise(entry_data $entry): entry_data {
        $deviation = in_array($entry->status, [self::PARTIAL, self::NOT_DONE], true);
        return new entry_data(
            status: $entry->status,
            content: $entry->content,
            reason: $deviation ? $entry->reason : '',
            note: $deviation ? $entry->note : '',
            measure: $deviation ? $entry->measure : '',
            carry: $entry->status === self::PARTIAL && $entry->carry,
            date: $entry->date,
        );
    }

    /**
     * Checks what was done and the reason of an entry with a valid status.
     *
     * @param entry_data $entry The entry as typed.
     * @return string[] Issue codes: content, then reason.
     */
    private static function check_text(entry_data $entry): array {
        $issues = [];
        if ($entry->status !== self::NOT_DONE && trim($entry->content) === '') {
            $issues[] = self::CONTENT_REQUIRED;
        }
        if ($entry->status === self::PARTIAL || $entry->status === self::NOT_DONE) {
            if ($entry->reason === '') {
                $issues[] = self::REASON_REQUIRED;
            } else if (!in_array($entry->reason, self::REASONS, true)) {
                $issues[] = self::BAD_REASON;
            }
        }
        return $issues;
    }
}
