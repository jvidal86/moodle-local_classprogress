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
 * Reads a year plan written in Markdown: # module, ## RA or block, ### one day, free Markdown under each day.
 *
 * The executable specification is tools/check_plan.py: this class gives the same results as its parse() and
 * summarize(), and tools/compare_parsers.py checks that on the fixtures and on random plans. It differs on purpose
 * only where the reference contradicts the requirements, on inputs a real plan does not contain: a leading byte order
 * mark is ignored (REQ-PLN-001), dates and hours are read only in ASCII digits (REQ-PLN-004), and a weekday name
 * matches only its own upper and lower case letters (the reference fails on 'ſat' or 'frı').
 *
 * Every pattern is written to run in linear time; the reference's own patterns backtrack on long runs of spaces.
 * No Moodle functions, constants or globals, and no clock.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_parser {
    /** @var array<string, int> Weekday names allowed before a date, in lower case, to their weekday (Monday = 0). */
    private const WEEKDAYS = [
        'dl' => 0, 'dt' => 1, 'dm' => 1, 'dc' => 2, 'dj' => 3, 'dv' => 4, 'ds' => 5, 'dg' => 6,
        'mon' => 0, 'tue' => 1, 'wed' => 2, 'thu' => 3, 'fri' => 4, 'sat' => 5, 'sun' => 6,
        'lun' => 0, 'mar' => 1, 'mié' => 2, 'mie' => 2, 'jue' => 3, 'vie' => 4, 'sáb' => 5, 'sab' => 5, 'dom' => 6,
    ];

    /** @var string White space as Python's str.strip() and \s see it, as the inside of a character class. */
    private const SPACE = '\t\n\x0b\x0c\r\x1c-\x20\x{85}\x{a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}';

    /** @var string Line breaks as Python's str.splitlines() sees them. */
    private const LINE_BREAK = '/\r\n|[\n\x0b\x0c\r\x1c-\x1e\x{85}\x{2028}\x{2029}]/u';

    /** @var string White space at the start of a text. */
    private const LEADING_SPACE = '/^[' . self::SPACE . ']++/u';

    /** @var string White space at the end of a text; the look-behind tries each run of spaces only once. */
    private const TRAILING_SPACE = '/(?<![' . self::SPACE . '])[' . self::SPACE . ']++\z/u';

    /** @var string Start of a heading: one to six hashes, then spaces or tabs. */
    private const HEADING = '/^(#{1,6})[ \t]++/u';

    /** @var string A line that opens or closes a code fence, of either kind. */
    private const FENCE = '/^[' . self::SPACE . ']*+(?:\x60{3}|~{3})/u';

    /** @var string Separator between module code and title: ' · ', ' — ', ' – ', ' - ' (any spaces) or ': '. */
    private const SEPARATOR = '/(?<![' . self::SPACE . '])[' . self::SPACE . ']++[\x{b7}\x{2014}\x{2013}-][' . self::SPACE . ']++'
        . '|:[' . self::SPACE . ']++/u';

    /** @var string A first word that is a module code: at most four ASCII letters, a digit, then letters, digits, _ . -. */
    private const CODE_WORD = '/^[A-Za-z]{0,4}+\p{Nd}[\p{L}\p{N}_.\-]*+\z/u';

    /** @var string A strict ISO date, yyyy-mm-dd. */
    private const ISO_DATE = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}\z/';

    /** @var string Pattern of a day heading: weekday, date, hours, title. Built from WEEKDAYS. */
    private readonly string $daypattern;

    /**
     * Builds the day heading pattern.
     */
    public function __construct() {
        $names = array_keys(self::WEEKDAYS);
        // Longest first, as in the reference.
        usort($names, fn(string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
        $weekdays = implode('|', array_map(fn(string $name): string => self::caseless($name), $names));
        $space = '[' . self::SPACE . ']';
        $this->daypattern = '~^(?:(' . $weekdays . ')\.?' . $space . '++)?'
            . '([0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{1,2}/[0-9]{1,2}/[0-9]{4})'
            . '(?:' . $space . '*+\[' . $space . '*+([0-9]++(?:[.,][0-9]++)?)' . $space . '*+[hH]' . $space . '*+\])?'
            . $space . '*+(.*)$~u';
    }

    /**
     * Reads a plan.
     *
     * @param string $text The plan: UTF-8 Markdown.
     * @param array<string, string>|null $holidays Holiday date (yyyy-mm-dd) to label; null or empty for no holiday check.
     * @param array<int, float>|null $timetable Weekday (Monday = 0) to class hours; null for no timetable. An empty
     *     timetable has no class day.
     * @param string[]|null $year First and last day of the school year, yyyy-mm-dd; null for no school year check.
     * @return plan_result
     * @throws \InvalidArgumentException If the text is not UTF-8 or the school year is not two ISO dates.
     */
    public function parse(string $text, ?array $holidays = null, ?array $timetable = null, ?array $year = null): plan_result {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new \InvalidArgumentException('The plan is not valid UTF-8.');
        }
        if ($year !== null) {
            $year = self::school_year($year);
        }
        if (str_starts_with($text, "\u{FEFF}")) {
            $text = substr($text, strlen("\u{FEFF}"));
        }

        $modules = [];
        $blocks = [];
        $days = [];
        $issues = [];
        $block = null;
        $current = null;
        $infence = false;
        $previous = null;
        foreach (self::lines($text) as $index => $line) {
            $number = $index + 1;
            if (self::find(self::FENCE, $line) !== null) {
                $infence = !$infence;
            }
            $heading = $infence ? null : self::heading($line);
            if ($heading === null) {
                if ($current !== null) {
                    $days[$current]['body'][] = $line;
                }
                continue;
            }

            [$level, $title] = $heading;
            $current = null;
            if ($level === 1) {
                [$code, $name] = self::split_module($title);
                $modules[] = new plan_module($code, $name, $number);
                continue;
            }
            if ($level === 2) {
                $block = $title;
                $blocks[] = $title;
                continue;
            }

            $day = $this->day_heading($title);
            if ($day === null) {
                $issues[] = new plan_issue(plan_issue::ERROR, plan_issue::BAD_DATE, $number, ['text' => $title]);
                continue;
            }
            $date = $day['date'];
            $hours = $day['hours'];
            if ($day['prefix'] !== null && self::WEEKDAYS[mb_strtolower($day['prefix'], 'UTF-8')] !== $day['weekday']) {
                $issues[] = self::warning(plan_issue::WEEKDAY_MISMATCH, $number, ['weekday' => $day['prefix'], 'date' => $date]);
            }
            if ($previous !== null && strcmp($date, $previous) < 0) {
                $issues[] = self::warning(plan_issue::OUT_OF_ORDER, $number, ['date' => $date, 'previous' => $previous]);
            }
            $previous = $date;
            if (!empty($holidays) && array_key_exists($date, $holidays)) {
                $label = (string) $holidays[$date];
                $issues[] = self::warning(plan_issue::ON_HOLIDAY, $number, ['date' => $date, 'holiday' => $label]);
            }
            $classday = $timetable !== null && array_key_exists($day['weekday'], $timetable);
            if ($timetable !== null && !$classday) {
                $issues[] = self::warning(plan_issue::NO_CLASS_WEEKDAY, $number, ['date' => $date]);
            }
            if ($year !== null && (strcmp($date, $year[0]) < 0 || strcmp($date, $year[1]) > 0)) {
                $issues[] = self::warning(plan_issue::OUTSIDE_YEAR, $number, ['date' => $date]);
            }
            if ($hours === null && $classday) {
                $hours = (float) $timetable[$day['weekday']];
            } else if ($hours === null) {
                $issues[] = self::warning(plan_issue::MISSING_HOURS, $number, ['date' => $date]);
            }
            if ($block === null) {
                $issues[] = self::warning(plan_issue::NO_BLOCK, $number, ['date' => $date]);
            }
            $days[] = ['date' => $date, 'hours' => $hours, 'title' => $day['title'], 'block' => $block, 'line' => $number,
                'body' => []];
            $current = count($days) - 1;
        }

        $result = [];
        foreach ($days as $day) {
            $content = self::strip(implode("\n", $day['body']));
            $result[] = new plan_day($day['date'], $day['hours'], $day['title'], $day['block'], $day['line'], $content);
            if ($content === '') {
                $issues[] = self::warning(plan_issue::EMPTY_DAY, $day['line'], ['date' => $day['date']]);
            }
        }
        if (count($modules) > 1) {
            $issues[] = new plan_issue(plan_issue::ERROR, plan_issue::MULTIPLE_MODULES, $modules[1]->line);
        }
        if (!$modules) {
            $issues[] = self::warning(plan_issue::NO_MODULE_HEADING, 1);
        }
        // Stable since PHP 8.0, so issues on one line keep the order in which they were found, as in the reference.
        usort($issues, fn(plan_issue $a, plan_issue $b): int => $a->line <=> $b->line);
        return new plan_result($modules[0] ?? null, $blocks, $result, $issues);
    }

    /**
     * Reads the text of a ### heading.
     *
     * @param string $title Heading text, trimmed.
     * @return array{date: string, weekday: int, prefix: ?string, hours: ?float, title: string}|null Null when there is
     *     no readable, existing date.
     */
    private function day_heading(string $title): ?array {
        $groups = self::find($this->daypattern, $title);
        if ($groups === null) {
            return null;
        }
        $parts = explode('-', $groups[2]);
        if (str_contains($groups[2], '/')) {
            $parts = array_reverse(explode('/', $groups[2]));
        }
        [$year, $month, $day] = array_map('intval', $parts);
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        return [
            'date' => $date,
            'weekday' => (int) (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format('N') - 1,
            'prefix' => $groups[1],
            'hours' => $groups[3] === null ? null : (float) str_replace(',', '.', $groups[3]),
            'title' => self::strip($groups[4]),
        ];
    }

    /**
     * Reads a line as a heading of level 1 to 3.
     *
     * @param string $line One line of the plan.
     * @return array{0: int, 1: string}|null Level and text, or null when the line is not a #, ## or ### heading.
     */
    private static function heading(string $line): ?array {
        $groups = self::find(self::HEADING, $line);
        if ($groups === null || strlen($groups[1]) > 3) {
            return null;
        }
        // Drop a closing sequence (spaces, hashes, spaces) right to left, which is what the reference's lazy pattern
        // does, without its backtracking. Like the reference, it also drops a '#' with no space before it.
        $text = rtrim(rtrim(rtrim(substr($line, strlen($groups[0])), " \t"), '#'), " \t");
        return [strlen($groups[1]), self::strip($text)];
    }

    /**
     * Splits the text of the # heading into module code and title.
     *
     * @param string $text Heading text, trimmed.
     * @return array{0: ?string, 1: string} Code (null when there is none) and title.
     */
    private static function split_module(string $text): array {
        $separator = self::find(self::SEPARATOR, $text, PREG_OFFSET_CAPTURE);
        if ($separator !== null) {
            [$found, $offset] = $separator[0];
            return [self::strip(substr($text, 0, $offset)), self::strip(substr($text, $offset + strlen($found)))];
        }
        $space = strpos($text, ' ');
        if ($space !== false) {
            $first = substr($text, 0, $space);
            $rest = substr($text, $space + 1);
            if ($rest !== '' && self::find(self::CODE_WORD, $first) !== null) {
                return [$first, self::strip($rest)];
            }
        }
        return [null, self::strip($text)];
    }

    /**
     * Checks and returns the school year.
     *
     * @param array $year First and last day, yyyy-mm-dd.
     * @return string[] The two dates.
     * @throws \InvalidArgumentException If the year is not two existing ISO dates.
     */
    private static function school_year(array $year): array {
        $dates = array_values($year);
        foreach ($dates as $date) {
            $iso = is_string($date) && self::find(self::ISO_DATE, $date) !== null;
            if (!$iso || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                $dates = [];
            }
        }
        if (count($dates) !== 2) {
            throw new \InvalidArgumentException('The school year must be two dates, yyyy-mm-dd.');
        }
        return $dates;
    }

    /**
     * Splits a text into lines the way Python's str.splitlines() does.
     *
     * @param string $text The plan.
     * @return string[] The lines, without their line breaks.
     */
    private static function lines(string $text): array {
        $lines = preg_split(self::LINE_BREAK, $text);
        if ($lines === false) {
            throw new \RuntimeException('Cannot split the plan into lines: ' . preg_last_error_msg());
        }
        if (end($lines) === '') {
            array_pop($lines);
        }
        return $lines;
    }

    /**
     * Removes white space from both ends, as Python's str.strip() does.
     *
     * @param string $text Any text.
     * @return string
     */
    private static function strip(string $text): string {
        $stripped = preg_replace([self::LEADING_SPACE, self::TRAILING_SPACE], '', $text);
        if ($stripped === null) {
            throw new \RuntimeException('Cannot trim a text: ' . preg_last_error_msg());
        }
        return $stripped;
    }

    /**
     * Matches a pattern; a PCRE failure is an exception, never a silent "no match".
     *
     * @param string $pattern The pattern.
     * @param string $subject The text.
     * @param int $flags Extra preg_match() flags.
     * @return array|null The groups (null for those that did not take part), or null when there is no match.
     */
    private static function find(string $pattern, string $subject, int $flags = 0): ?array {
        $found = preg_match($pattern, $subject, $groups, $flags | PREG_UNMATCHED_AS_NULL);
        if ($found === false) {
            throw new \RuntimeException('Cannot match a pattern: ' . preg_last_error_msg());
        }
        return $found === 1 ? $groups : null;
    }

    /**
     * Writes a word as a pattern that matches it in upper or lower case, letter by letter.
     *
     * @param string $word A weekday name in lower case.
     * @return string
     */
    private static function caseless(string $word): string {
        $pattern = '';
        foreach (mb_str_split($word, 1, 'UTF-8') as $char) {
            $upper = mb_strtoupper($char, 'UTF-8');
            $pattern .= $upper === $char ? preg_quote($char, '~') : '[' . $char . $upper . ']';
        }
        return $pattern;
    }

    /**
     * Creates a warning.
     *
     * @param string $code One of the plan_issue code constants.
     * @param int $line Line, from 1.
     * @param array<string, string> $params Values for a message.
     * @return plan_issue
     */
    private static function warning(string $code, int $line, array $params = []): plan_issue {
        return new plan_issue(plan_issue::WARNING, $code, $line, $params);
    }
}
