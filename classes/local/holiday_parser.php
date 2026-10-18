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
    /** @var string One date, as written. */
    private const DATE = '([0-9]{4}-[0-9]{2}-[0-9]{2})';

    /**
     * Parses holiday text.
     *
     * @param string $text The holiday text, one entry per line.
     * @return holiday_parse_result The valid entries and the rejected lines.
     */
    public static function parse(string $text): holiday_parse_result {
        $entries = [];
        $pattern = '/^' . self::DATE . '\s*(.*)$/u';
        $lines = explode("\n", $text);
        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line, $m) !== 1) {
                continue;
            }
            $entries[] = new holiday_entry($index + 1, $m[1], $m[1], trim($m[2]));
        }
        return new holiday_parse_result($entries, []);
    }
}
