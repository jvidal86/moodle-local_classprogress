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
 * Tests for the holiday text parser.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class holiday_parser_test extends \basic_testcase {
    /**
     * A line with one ISO date and a label is a one-day holiday.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @group REQ-HOL-001
     */
    public function test_single_iso_date_with_label_is_one_day_holiday(): void {
        $result = holiday_parser::parse("2026-10-12 Festa Nacional d'Espanya");

        $this->assertSame([], $result->errors);
        $this->assertCount(1, $result->entries);
        $entry = $result->entries[0];
        $this->assertSame(1, $entry->line);
        $this->assertSame('2026-10-12', $entry->start);
        $this->assertSame('2026-10-12', $entry->end);
        $this->assertSame("Festa Nacional d'Espanya", $entry->label);
    }

    /**
     * Lines in every accepted date format and range separator.
     *
     * @return array<string, array{string, string, string, string}> Line, expected start, end and label.
     */
    public static function valid_line_provider(): array {
        return [
            'dd/mm/yyyy' => ['07/12/2026 Pont', '2026-12-07', '2026-12-07', 'Pont'],
            'd/m/yyyy' => ['1/5/2027 Festa del Treball', '2027-05-01', '2027-05-01', 'Festa del Treball'],
            'no label' => ['2026-11-01', '2026-11-01', '2026-11-01', ''],
            'surrounding spaces' => ["  \t2026-11-01   Tots Sants  ", '2026-11-01', '2026-11-01', 'Tots Sants'],
            'no-break space before label' => ["2026-11-01\u{00a0}Tots Sants", '2026-11-01', '2026-11-01', 'Tots Sants'],
            'to' => ['2026-12-22 to 2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'TO in capitals' => ['2026-12-22 TO 2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'a' => ['2026-12-22 a 2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'en dash' => ['2026-12-22 – 2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'em dash' => ['2026-12-22 — 2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'hyphen' => ['2026-12-22 - 2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'hyphen without spaces' => ['2026-12-22-2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'two dots' => ['2026-12-22..2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'arrow' => ['2026-12-22 → 2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'mixed formats' => ['22/12/2026 to 2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'one-day range' => ['2027-02-01 to 2027-02-01 Lliure', '2027-02-01', '2027-02-01', 'Lliure'],
            'label starting with a separator word' => ['2026-09-11 a casa', '2026-09-11', '2026-09-11', 'a casa'],
        ];
    }

    /**
     * Each date format and range separator gives the expected period and label.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @dataProvider valid_line_provider
     * @group REQ-HOL-001
     * @param string $line The holiday line.
     * @param string $start Expected first day.
     * @param string $end Expected last day.
     * @param string $label Expected label.
     */
    public function test_date_formats_and_separators_give_period_and_label(
        string $line,
        string $start,
        string $end,
        string $label
    ): void {
        $result = holiday_parser::parse($line);

        $this->assertSame([], $result->errors);
        $this->assertCount(1, $result->entries);
        $entry = $result->entries[0];
        $this->assertSame([$start, $end, $label], [$entry->start, $entry->end, $entry->label]);
    }

    /**
     * Lines where a range word or dash after the date is not followed by a digit, so the rest is the label.
     *
     * @return array<string, array{string, string, string, string}> Line, expected start, end and label.
     */
    public static function range_word_then_text_provider(): array {
        return [
            'dash then text' => ['2026-10-12 - Festa', '2026-10-12', '2026-10-12', '- Festa'],
            'to then text' => ['2026-10-12 to be confirmed', '2026-10-12', '2026-10-12', 'to be confirmed'],
            'arrow then text' => ['2026-10-12 → pont', '2026-10-12', '2026-10-12', '→ pont'],
            'digit later in the label' => ['2026-10-12 Festa a 2 bandes', '2026-10-12', '2026-10-12', 'Festa a 2 bandes'],
            'correct period' => ['2026-12-22 to 2027-01-07 Nadal', '2026-12-22', '2027-01-07', 'Nadal'],
            'period, label with dash and digit' => ['2026-12-22 to 2026-12-23 - 2 dies', '2026-12-22', '2026-12-23', '- 2 dies'],
        ];
    }

    /**
     * A range word or dash followed by text, not a digit, is part of the label of a one-day holiday.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @dataProvider range_word_then_text_provider
     * @group REQ-HOL-002
     * @param string $line The holiday line.
     * @param string $start Expected first day.
     * @param string $end Expected last day.
     * @param string $label Expected label.
     */
    public function test_range_word_then_text_is_part_of_label(string $line, string $start, string $end, string $label): void {
        $result = holiday_parser::parse($line);

        $this->assertSame([], $result->errors);
        $this->assertCount(1, $result->entries);
        $entry = $result->entries[0];
        $this->assertSame([$start, $end, $label], [$entry->start, $entry->end, $entry->label]);
    }

    /**
     * A leading byte-order mark is ignored, and lines may end with LF, CRLF or CR, also mixed.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @group REQ-HOL-001
     */
    public function test_leading_bom_is_ignored_and_lf_crlf_cr_end_lines(): void {
        $text = "\u{FEFF}2026-09-11 Diada\r2026-10-12 Festa\r\n\r\n2026-11-01 Tots Sants\n2026-12-08";

        $result = holiday_parser::parse($text);

        $this->assertSame([], $result->errors);
        $this->assertSame(
            [[1, 'Diada'], [2, 'Festa'], [4, 'Tots Sants'], [5, '']],
            array_map(fn(holiday_entry $e): array => [$e->line, $e->label], $result->entries)
        );
    }

    /**
     * Blank lines and lines starting with # are skipped, but still counted for line numbers.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @group REQ-HOL-001
     */
    public function test_comments_and_blank_lines_are_ignored_but_counted(): void {
        $text = "# Holidays 2026-27\r\n\r\n   # 2027-01-19 Jornada, commented out\r\n"
            . "2026-11-01 Tots Sants\r\n \t \r\n2026-12-08\r\n";

        $result = holiday_parser::parse($text);

        $this->assertSame([], $result->errors);
        $this->assertSame([4, 6], array_map(fn(holiday_entry $e): int => $e->line, $result->entries));
        $this->assertSame(['2026-11-01', '2026-12-08'], array_map(fn(holiday_entry $e): string => $e->start, $result->entries));
    }

    /**
     * Lines that cannot be a holiday, with the reason code each must get.
     *
     * @return array<string, array{string, string}> Line and expected code.
     */
    public static function bad_line_provider(): array {
        return [
            'words only' => ['Vacances de Nadal', holiday_parser::NOT_A_DATE],
            'slashes in ISO order' => ['2026/12/07 Pont', holiday_parser::NOT_A_DATE],
            'two-digit year' => ['07/12/26 Pont', holiday_parser::NOT_A_DATE],
            'one-digit month in ISO' => ['2026-1-07 Pont', holiday_parser::NOT_A_DATE],
            'label before the date' => ['Pont 2026-12-07', holiday_parser::NOT_A_DATE],
            'not UTF-8' => ["\xff\xfe 2026-12-07", holiday_parser::NOT_A_DATE],
            '30 February' => ['30/02/2027 Festa', holiday_parser::NO_SUCH_DATE],
            '29 February in a common year' => ['2027-02-29', holiday_parser::NO_SUCH_DATE],
            'month 13' => ['2026-13-01', holiday_parser::NO_SUCH_DATE],
            'day 0' => ['0/1/2027', holiday_parser::NO_SUCH_DATE],
            'year 0' => ['0000-01-01', holiday_parser::NO_SUCH_DATE],
            'end of period does not exist' => ['2026-12-22 to 2027-02-30 Nadal', holiday_parser::NO_SUCH_DATE],
            'end before start' => ['2027-01-07 to 2026-12-22 Nadal', holiday_parser::END_BEFORE_START],
            'end one day before start' => ['08/12/2026 - 07/12/2026', holiday_parser::END_BEFORE_START],
            'period end with one-digit month' => ['2026-12-22 to 2027-1-07 Nadal', holiday_parser::BAD_END_DATE],
            'period end without year' => ['2026-12-07 a 08/12 pont', holiday_parser::BAD_END_DATE],
            'TO in capitals, end in wrong order' => ['2026-12-22 TO 2027/01/07', holiday_parser::BAD_END_DATE],
            'en dash, end with two-digit year' => ['22/12/2026 – 7/1/27 Nadal', holiday_parser::BAD_END_DATE],
            'em dash, end with one-digit day' => ['2026-12-22 — 2027-01-7', holiday_parser::BAD_END_DATE],
            'hyphen without spaces' => ['2026-12-22-2027-1-07 Nadal', holiday_parser::BAD_END_DATE],
            'two dots' => ['2026-12-22..2027-1-07', holiday_parser::BAD_END_DATE],
            'arrow' => ['2026-12-22 → 2027-1-07', holiday_parser::BAD_END_DATE],
            'start that does not exist comes first' => ['2026-02-30 to 2027-1-07', holiday_parser::NO_SUCH_DATE],
        ];
    }

    /**
     * A bad line is reported with its line number, the reason code and its text, and is never a holiday.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @dataProvider bad_line_provider
     * @group REQ-HOL-002
     * @param string $line The holiday line.
     * @param string $code Expected reason code.
     */
    public function test_bad_line_is_reported_with_reason_code(string $line, string $code): void {
        $result = holiday_parser::parse("# header\n" . $line);

        $this->assertSame([], $result->entries);
        $this->assertCount(1, $result->errors);
        $this->assertSame(2, $result->errors[0]->line);
        $this->assertSame($code, $result->errors[0]->code);
        $this->assertSame($line, $result->errors[0]->text);
    }

    /**
     * A bad line does not stop the lines after it.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @group REQ-HOL-002
     */
    public function test_bad_line_does_not_stop_other_lines(): void {
        $result = holiday_parser::parse("2026-11-01 Tots Sants\n31/11/2026 Typo\nNadal\n2026-12-08 La Immaculada");

        $this->assertSame(['2026-11-01', '2026-12-08'], array_map(fn(holiday_entry $e): string => $e->start, $result->entries));
        $this->assertSame(
            [[2, holiday_parser::NO_SUCH_DATE], [3, holiday_parser::NOT_A_DATE]],
            array_map(fn(holiday_error $e): array => [$e->line, $e->code], $result->errors)
        );
    }

    /**
     * 29 February exists in a leap year.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @group REQ-HOL-002
     */
    public function test_29_february_in_leap_year_is_valid(): void {
        $result = holiday_parser::parse('29/02/2028 Leap day');

        $this->assertSame([], $result->errors);
        $this->assertSame('2028-02-29', $result->entries[0]->start);
    }

    /**
     * Expanding lists every day of each period, across a year end, with its label.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @group REQ-HOL-001
     */
    public function test_expand_lists_every_day_of_each_period(): void {
        $entries = holiday_parser::parse("2026-12-30 to 2027-01-02 Nadal\n2027-02-01")->entries;

        $this->assertSame([
            '2026-12-30' => 'Nadal',
            '2026-12-31' => 'Nadal',
            '2027-01-01' => 'Nadal',
            '2027-01-02' => 'Nadal',
            '2027-02-01' => '',
        ], holiday_parser::expand($entries));
    }

    /**
     * On overlapping entries the first line keeps the day; the days come out sorted by date.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @group REQ-HOL-001
     */
    public function test_expand_first_entry_wins_on_overlap_and_days_are_sorted(): void {
        $text = "2026-12-22 to 2026-12-24 Nadal\n2026-12-01 Earlier\n2026-12-23 to 2026-12-26 Overlap";

        $days = holiday_parser::expand(holiday_parser::parse($text)->entries);

        $this->assertSame([
            '2026-12-01' => 'Earlier',
            '2026-12-22' => 'Nadal',
            '2026-12-23' => 'Nadal',
            '2026-12-24' => 'Nadal',
            '2026-12-25' => 'Overlap',
            '2026-12-26' => 'Overlap',
        ], $days);
    }

    /**
     * The school's real holiday file expands to exactly the days the Python reference parser gives.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @group REQ-HOL-001
     */
    public function test_school_holiday_file_matches_reference_parser(): void {
        $text = file_get_contents(__DIR__ . '/../fixtures/holidays-centre-2026-27.txt');
        $expected = json_decode(file_get_contents(__DIR__ . '/../fixtures/holidays-expanded.json'), true);

        $result = holiday_parser::parse($text);

        $this->assertSame([], $result->errors);
        $this->assertCount(39, $expected);
        $this->assertSame($expected, holiday_parser::expand($result->entries));
    }
}
