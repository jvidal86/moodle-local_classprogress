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
 * Parses the holiday text of a school year: one holiday per line, a date or a period, then an optional label.
 *
 * The grammar follows parse_holidays() in tools/check_plan.py. Pure logic: no Moodle API, no clock.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class holiday_parser {
    /** @var string Reason code: the line does not start with a date in an accepted format. */
    public const NOT_A_DATE = 'not_a_date';

    /** @var string Reason code: a date has the right format but does not exist, such as 30/02/2027. */
    public const NO_SUCH_DATE = 'no_such_date';

    /** @var string Reason code: the period ends before it starts. */
    public const END_BEFORE_START = 'end_before_start';

    /** @var string One date as written: yyyy-mm-dd or dd/mm/yyyy (day and month may have one digit). */
    private const DATE = '([0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{1,2}/[0-9]{1,2}/[0-9]{4})';

    /** @var string Words and signs accepted between the two dates of a period. */
    private const SEPARATOR = '(?:to|a|–|—|-|\.\.|→)';

    /**
     * Parses holiday text.
     *
     * @param string $text The holiday text, one entry per line.
     * @return holiday_parse_result The valid entries and the rejected lines.
     */
    public static function parse(string $text): holiday_parse_result {
        $entries = [];
        $errors = [];
        $pattern = '~^' . self::DATE . '\s*(?:' . self::SEPARATOR . '\s*' . self::DATE . ')?\s*(.*)$~iu';
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        foreach ($lines as $index => $raw) {
            $line = self::trim($raw);
            $number = $index + 1;
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (preg_match($pattern, $line, $m) !== 1) {
                $errors[] = new holiday_error($number, self::NOT_A_DATE, $line);
                continue;
            }
            $start = self::to_iso($m[1]);
            $end = $m[2] === '' ? $start : self::to_iso($m[2]);
            if ($start === null || $end === null) {
                $errors[] = new holiday_error($number, self::NO_SUCH_DATE, $line);
            } else if ($end < $start) {
                $errors[] = new holiday_error($number, self::END_BEFORE_START, $line);
            } else {
                $entries[] = new holiday_entry($number, $start, $end, self::trim($m[3]));
            }
        }
        return new holiday_parse_result($entries, $errors);
    }

    /**
     * Lists every holiday day of the given entries with its label.
     *
     * When entries overlap, the first one in the list keeps the day, as in the Python reference.
     *
     * @param holiday_entry[] $entries Entries, usually holiday_parse_result::$entries.
     * @return array<string, string> ISO date => label, sorted by date.
     */
    public static function expand(array $entries): array {
        $days = [];
        $utc = new \DateTimeZone('UTC');
        foreach ($entries as $entry) {
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $entry->start, $utc);
            for ($iso = $entry->start; $iso <= $entry->end; $iso = $day->format('Y-m-d')) {
                $days[$iso] ??= $entry->label;
                $day = $day->modify('+1 day');
            }
        }
        ksort($days, SORT_STRING);
        return $days;
    }

    /**
     * Writes a date as ISO yyyy-mm-dd, if it exists.
     *
     * @param string $date A date matched by {@see self::DATE}.
     * @return string|null The same date as yyyy-mm-dd, zero-padded; null if there is no such day.
     */
    private static function to_iso(string $date): ?string {
        if (strpos($date, '/') !== false) {
            [$day, $month, $year] = array_map('intval', explode('/', $date));
        } else {
            [$year, $month, $day] = array_map('intval', explode('-', $date));
        }
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Trims Unicode white space (such as a no-break space pasted from a web page) from both ends.
     *
     * @param string $text Text to trim.
     * @return string The trimmed text; text that is not valid UTF-8 is returned unchanged.
     */
    private static function trim(string $text): string {
        return preg_replace('/^\s+|\s+$/u', '', $text) ?? $text;
    }
}
