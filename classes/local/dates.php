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
 * The days a teacher may enter, and ranges of them: ISO yyyy-mm-dd strings handled as UTC days.
 *
 * Every date lies from FIRST_DAY to LAST_DAY, and a range (a school year, a holiday period, the class days of a plan)
 * is at most MAX_DAYS long, so no loop over the days of a range can run long. Days are compared as day numbers,
 * never as text. Pure logic: no Moodle API, no clock.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dates {
    /** @var string The first day a teacher may enter. */
    public const FIRST_DAY = '2000-01-01';

    /** @var string The last day a teacher may enter. */
    public const LAST_DAY = '2099-12-31';

    /** @var int Most days in a range, counting its first and its last day. */
    public const MAX_DAYS = 400;

    /** @var string Range code: a day lies outside FIRST_DAY to LAST_DAY. */
    public const OUT_OF_RANGE = 'out_of_range';

    /** @var string Range code: the last day is before the first. */
    public const REVERSED = 'reversed';

    /** @var string Range code: the range is longer than MAX_DAYS. */
    public const TOO_LONG = 'too_long';

    /**
     * Tells whether a string is an existing day written exactly yyyy-mm-dd.
     *
     * @param string $text The text to check.
     * @return bool True for an existing day.
     */
    public static function is_date(string $text): bool {
        if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $text, $m) !== 1) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Numbers a day: days since 1970-01-01.
     *
     * @param string $date An existing day, yyyy-mm-dd (see is_date()).
     * @return int The day number.
     */
    public static function day_number(string $date): int {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        return intdiv($day->getTimestamp(), 86400);
    }

    /**
     * Tells whether an existing day lies from FIRST_DAY to LAST_DAY.
     *
     * @param string $date An existing day, yyyy-mm-dd (see is_date()).
     * @return bool True inside the window.
     */
    public static function in_window(string $date): bool {
        $day = self::day_number($date);
        return $day >= self::day_number(self::FIRST_DAY) && $day <= self::day_number(self::LAST_DAY);
    }

    /**
     * Tells whether a day lies within a range, ends included.
     *
     * @param string $date An existing day, yyyy-mm-dd.
     * @param string $first First day of the range.
     * @param string $last Last day of the range.
     * @return bool True within the range.
     */
    public static function within(string $date, string $first, string $last): bool {
        $day = self::day_number($date);
        return $day >= self::day_number($first) && $day <= self::day_number($last);
    }

    /**
     * Checks a range of existing days, in this order: both in the window, in order, at most MAX_DAYS long.
     *
     * @param string $first First day, yyyy-mm-dd (see is_date()).
     * @param string $last Last day, yyyy-mm-dd.
     * @return string|null Null for a valid range; otherwise OUT_OF_RANGE, REVERSED or TOO_LONG.
     */
    public static function check_range(string $first, string $last): ?string {
        if (!self::in_window($first) || !self::in_window($last)) {
            return self::OUT_OF_RANGE;
        }
        $length = self::day_number($last) - self::day_number($first) + 1;
        if ($length < 1) {
            return self::REVERSED;
        }
        return $length > self::MAX_DAYS ? self::TOO_LONG : null;
    }
}
