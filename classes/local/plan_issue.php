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
 * A problem found in a plan: a level, a code, a line and the values a message about it needs.
 *
 * Codes and parameter names are stable identifiers (for example for language strings), never sentences.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_issue {
    /** @var string Level of an issue that blocks approval. */
    public const ERROR = 'error';

    /** @var string Level of an issue the teacher must confirm. */
    public const WARNING = 'warning';

    /** @var string Error: a ### heading without a readable, existing date, or with a non-weekday word. Parameter: text. */
    public const BAD_DATE = 'bad_date';

    /** @var string Error: the text is not UTF-8, so nothing is read; the line is the first that is not. No parameters. */
    public const NOT_UTF8 = 'not_utf8';

    /** @var string Error: a second # heading. No parameters. */
    public const MULTIPLE_MODULES = 'multiple_modules';

    /** @var string Warning: the plan has no # heading. No parameters. */
    public const NO_MODULE_HEADING = 'no_module_heading';

    /** @var string Warning: a day with no ## heading above it. Parameter: date. */
    public const NO_BLOCK = 'no_block';

    /** @var string Warning: a day with no plan text. Parameter: date. */
    public const EMPTY_DAY = 'empty_day';

    /** @var string Warning: a day dated before the previous day. Parameters: date, previous. */
    public const OUT_OF_ORDER = 'out_of_order';

    /** @var string Warning: a day on a holiday. Parameters: date, holiday. */
    public const ON_HOLIDAY = 'on_holiday';

    /** @var string Warning: the weekday before the date is not the date's weekday. Parameters: weekday, date. */
    public const WEEKDAY_MISMATCH = 'weekday_mismatch';

    /** @var string Warning: a day on a weekday with no class in the timetable. Parameter: date. */
    public const NO_CLASS_WEEKDAY = 'no_class_weekday';

    /** @var string Warning: a day outside the school year. Parameter: date. */
    public const OUTSIDE_YEAR = 'outside_year';

    /** @var string Warning: neither the heading nor the timetable gives the day's hours. Parameter: date. */
    public const MISSING_HOURS = 'missing_hours';

    /** @var string Warning: a code fence is still open at the end of the text; the line is where it opened. No parameters. */
    public const UNCLOSED_FENCE = 'unclosed_fence';

    /** @var string Warning: the plan was read from inside the code block that opens at the line (REQ-PLN-017). No parameters. */
    public const UNWRAPPED_FENCE = 'unwrapped_fence';

    /**
     * Creates the issue.
     *
     * @param string $level self::ERROR or self::WARNING.
     * @param string $code One of the code constants.
     * @param int $line Line the issue is about, from 1.
     * @param string[] $params Values for a message, by name (dates are yyyy-mm-dd).
     */
    public function __construct(
        /** @var string self::ERROR or self::WARNING. */
        public readonly string $level,
        /** @var string One of the code constants. */
        public readonly string $code,
        /** @var int Line the issue is about, from 1. */
        public readonly int $line,
        /** @var string[] Values for a message, by name (dates are yyyy-mm-dd). */
        public readonly array $params = [],
    ) {
    }
}
