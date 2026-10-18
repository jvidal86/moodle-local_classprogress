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
 * Tests for the plan parser: a Markdown year plan read into module, blocks, days and issues.
 *
 * Expected results follow tools/check_plan.py, the executable reference. The two provider-driven tests at the end
 * compare with the results recorded for the fixtures copied from test-data/.
 *
 * @package    local_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\local\plan_parser
 * @covers     \local_classprogress\local\plan_result
 * @covers     \local_classprogress\local\plan_module
 * @covers     \local_classprogress\local\plan_day
 * @covers     \local_classprogress\local\plan_issue
 */
final class plan_parser_test extends \basic_testcase {
    /** @var float[] Timetable of the edge cases: Monday and Friday, two hours each (Monday = 0). */
    private const TIMETABLE = [0 => 2.0, 4 => 2.0];

    /** @var float[] A class every day, two hours, so that only the checks under test give issues. */
    private const EVERY_DAY = [0 => 2.0, 1 => 2.0, 2 => 2.0, 3 => 2.0, 4 => 2.0, 5 => 2.0, 6 => 2.0];

    /** @var string A code fence of backticks, written with escapes because phpcs warns on backticks in strings. */
    private const BACKTICKS = "\x60\x60\x60";

    /**
     * Module headings, with the code and title they give.
     *
     * @return array
     */
    public static function module_heading_provider(): array {
        return [
            'middle dot' => ['MP 0372 · Gestió de bases de dades', 'MP 0372', 'Gestió de bases de dades'],
            'em dash' => ['MP03 — Programació', 'MP03', 'Programació'],
            'en dash' => ['MP03 – Programació', 'MP03', 'Programació'],
            'hyphen' => ['MP03 - Programació', 'MP03', 'Programació'],
            'colon' => ['MP03: Programació', 'MP03', 'Programació'],
            'first separator wins' => ['MP03: Programació · Java', 'MP03', 'Programació · Java'],
            'code word without separator' => ['MP03 Programació', 'MP03', 'Programació'],
            'digits only code word' => ['0372 Bases de dades', '0372', 'Bases de dades'],
            'no code' => ['Programació', null, 'Programació'],
            'first word is not a code' => ['Bases de dades', null, 'Bases de dades'],
            'five letters before the digits' => ['MODUL1 Programació', null, 'MODUL1 Programació'],
            'tab after a code word' => ["MP03\tProgramació", null, "MP03\tProgramació"],
            'letters of any script after the digits' => ['MP03é Programació', 'MP03é', 'Programació'],
            'digits other than 0-9 are not code digits' => ['MP٠٣ Programació', null, 'MP٠٣ Programació'],
            'hash at the end of the title' => ['Programació en C#', null, 'Programació en C#'],
            'empty code before a colon' => [': Programació', '', 'Programació'],
            'closing hashes' => ['MP03 · Programació ##', 'MP03', 'Programació'],
        ];
    }

    /**
     * The module code is the text before the first separator, or a first word that looks like a code.
     *
     * @group REQ-PLN-001
     * @dataProvider module_heading_provider
     * @param string $heading Text of the # heading.
     * @param string|null $code Expected code.
     * @param string $title Expected title.
     */
    public function test_module_heading_gives_code_and_title(string $heading, ?string $code, string $title): void {
        $result = self::parse(self::md('# ' . $heading, '## RA1', '### 2026-10-05 [2h]', '- A day'));

        $this->assertNotNull($result->module);
        $this->assertSame($code, $result->module->code);
        $this->assertSame($title, $result->module->title);
        $this->assertSame(1, $result->module->line);
        $this->assertSame([], self::issues($result));
    }

    /**
     * A plan without a # heading is read, with a warning.
     *
     * @group REQ-PLN-001
     */
    public function test_plan_without_module_heading_warns(): void {
        $result = self::parse(self::md('## RA1 · Starts without a module heading', '### 2026-10-05 [2h]', '- A day'));

        $this->assertNull($result->module);
        $this->assertCount(1, $result->days);
        $this->assertSame(['warning no_module_heading 1'], self::issues($result));
    }

    /**
     * A file saved with a UTF-8 byte order mark still has its module heading on line 1.
     *
     * @group REQ-PLN-001
     * @group REQ-PLN-015
     */
    public function test_byte_order_mark_does_not_hide_module_heading(): void {
        $text = self::md('# MP01 · Saved with a byte order mark', '## RA1', '### 2026-10-05 [2h]', '- A day');
        $result = self::parse("\u{FEFF}" . $text);

        $this->assertNotNull($result->module);
        $this->assertSame('MP01', $result->module->code);
        $this->assertSame([], self::issues($result));
    }

    /**
     * A second # heading is an error; the days under it are still read.
     *
     * @group REQ-PLN-002
     */
    public function test_second_module_heading_is_an_error(): void {
        $result = self::parse(self::md(
            '# MP01 · First module',
            '## RA1',
            '### 2026-10-05 [2h]',
            '- First',
            '# MP02 · Second module',
            '### 2026-10-09 [2h]',
            '- Second',
        ));

        $this->assertSame(['error multiple_modules 5'], self::issues($result));
        $this->assertSame('MP01', $result->module->code);
        $this->assertCount(2, $result->days);
        $this->assertSame('RA1', $result->days[1]->block);
        $this->assertSame(['multiple_modules'], $result->summary()['errors']);
    }

    /**
     * A ## heading applies to the days below it; a day with none above it gets a warning.
     *
     * @group REQ-PLN-003
     */
    public function test_block_heading_applies_to_the_days_below(): void {
        $result = self::parse(self::md(
            '# MP01 · Blocks',
            '### 2026-10-05 [2h]',
            '- Before any block',
            '## RA1 · First',
            '### 2026-10-09 [2h]',
            '- In RA1',
            '### 2026-10-16 [2h]',
            '- Still in RA1',
            '## RA2',
            '### 2026-10-19 [2h]',
            '- In RA2',
        ));

        $this->assertSame(['RA1 · First', 'RA2'], $result->blocks);
        $blocks = array_map(fn(plan_day $day): ?string => $day->block, $result->days);
        $this->assertSame([null, 'RA1 · First', 'RA1 · First', 'RA2'], $blocks);
        $this->assertSame(['warning no_block 2'], self::issues($result));
    }

    /**
     * Heading text loses white space at both ends, Unicode white space included, as in the reference.
     *
     * @group REQ-PLN-003
     */
    public function test_heading_text_is_trimmed_like_the_reference(): void {
        $result = self::parse(self::md(
            '# MP01 · Trimming',
            "## Bloc 2\u{00A0}",
            "## Bloc 3 #\u{00A0}",
            "## Bloc 4\u{200B}",
            "## Bloc 5\x1F",
            "## Bloc 6\u{180E}",
        ));

        // White space is Python's: U+00A0 and U+001F are, U+200B and U+180E are not. A '#' before a no-break space is
        // not a closing run.
        $this->assertSame(['Bloc 2', 'Bloc 3 #', "Bloc 4\u{200B}", 'Bloc 5', "Bloc 6\u{180E}"], $result->blocks);
    }

    /**
     * Block headings and the text they give once a closing run of hashes is removed.
     *
     * @return array
     */
    public static function closing_hashes_provider(): array {
        return [
            'closing run after a space' => ['## Bloc 1 ##', 'Bloc 1'],
            'closing run after a tab, spaces after it' => ["## Bloc 1\t###   ", 'Bloc 1'],
            'hash with no space before it' => ['## C#', 'C#'],
            'two hashes with no space before them' => ['## C##', 'C##'],
            'only the last run' => ['## a # #', 'a #'],
            'nothing but hashes' => ['## ##', ''],
        ];
    }

    /**
     * A trailing run of # is removed from a heading only when a space comes before it.
     *
     * @group REQ-PLN-001
     * @dataProvider closing_hashes_provider
     * @param string $heading The ## heading line.
     * @param string $block Expected block text.
     */
    public function test_closing_hashes_are_removed_only_after_a_space(string $heading, string $block): void {
        $result = self::parse(self::md('# MP01 · Hashes', $heading));

        $this->assertSame([$block], $result->blocks);
    }

    /**
     * The rule for closing hashes applies to module and day headings too.
     *
     * @group REQ-PLN-001
     */
    public function test_closing_hashes_in_module_and_day_headings(): void {
        $result = self::parse(self::md('# Programació en C#', '## Bloc 1 ##', '### 2026-10-05 [2h] Punters ##', '- A day'));

        $this->assertSame('Programació en C#', $result->module->title);
        $this->assertNull($result->module->code);
        $this->assertSame('Punters', $result->days[0]->title);
        $this->assertSame([], self::issues($result));
    }

    /**
     * Day headings in every accepted form.
     *
     * @return array
     */
    public static function day_heading_provider(): array {
        return [
            'iso date' => ['2026-09-14 [2h]', '2026-09-14', ''],
            'day/month/year' => ['16/09/2026 [2h]', '2026-09-16', ''],
            'one-digit day and month' => ['1/2/2027 [2h]', '2027-02-01', ''],
            'leap day' => ['29/02/2028 [2h]', '2028-02-29', ''],
            'catalan weekday and a title' => ["dl 14/09/2026 [2h] S1 · L'entorn", '2026-09-14', "S1 · L'entorn"],
            'catalan dt' => ['dt 15/09/2026 [2h]', '2026-09-15', ''],
            'catalan dm' => ['dm 15/09/2026 [2h]', '2026-09-15', ''],
            'english weekday' => ['Wed 30/09/2026 [2h] A title after the hours', '2026-09-30', 'A title after the hours'],
            'spanish weekday with an accent' => ['mié 16/09/2026 [2h]', '2026-09-16', ''],
            'upper case with an accent' => ['MIÉ 16/09/2026 [2h]', '2026-09-16', ''],
            'saturday with an accent' => ['Sáb 19/09/2026 [2h]', '2026-09-19', ''],
            'mixed letter case' => ['sÁB 19/09/2026 [2h]', '2026-09-19', ''],
            'weekday with a dot' => ['DL. 14/09/2026 [2h]', '2026-09-14', ''],
            'no-break space after the weekday' => ["dc\u{00A0}23/09/2026 [2h]", '2026-09-23', ''],
            'title without hours' => ['dg 20/09/2026 Sunday session', '2026-09-20', 'Sunday session'],
        ];
    }

    /**
     * A day heading is a date in either format, an optional weekday before it, optional hours and an optional title.
     *
     * @group REQ-PLN-004
     * @dataProvider day_heading_provider
     * @param string $heading Text of the ### heading.
     * @param string $date Expected ISO date.
     * @param string $title Expected title.
     */
    public function test_day_heading_gives_date_and_title(string $heading, string $date, string $title): void {
        $result = self::parse(self::md('# MP01 · Days', '## RA1', '### ' . $heading, '- A day'), null, self::EVERY_DAY);

        $this->assertCount(1, $result->days);
        $this->assertSame($date, $result->days[0]->date);
        $this->assertSame($title, $result->days[0]->title);
        $this->assertSame(3, $result->days[0]->line);
        $this->assertSame([], self::issues($result));
    }

    /**
     * Unreadable or impossible dates.
     *
     * @return array
     */
    public static function unreadable_date_provider(): array {
        return [
            '30 February' => ['2026-02-30 [2h]'],
            'month 13' => ['2026-13-01 [2h]'],
            'month 00' => ['2026-00-10 [2h]'],
            'day 00' => ['00/01/2026 [2h]'],
            'day 32' => ['32/01/2026 [2h]'],
            '29 February in a common year' => ['29/02/2027 [2h]'],
            'year 0000' => ['0000-01-01 [2h]'],
            'one-digit month in an iso date' => ['2026-9-14 [2h]'],
            'dots' => ['14.09.2026 [2h]'],
            'words' => ['sometime in October'],
            'empty heading' => [''],
            'weekday glued to the date' => ['dl14/09/2026 [2h]'],
            'full weekday name' => ['Monday 14/09/2026 [2h]'],
            'a word that is not a weekday' => ['Setmana 16/10/2026 [2h]'],
            'arabic-indic digits' => ['١٤/٠٩/٢٠٢٦ [2h]'],
            'full-width digits' => ['２０２６-１０-０５ [2h]'],
            'long s in a weekday' => ['ſat 10/10/2026 [2h]'],
            'dotless i in a weekday' => ['frı 09/10/2026 [2h]'],
            'dotted capital I in a weekday' => ['FRİ 09/10/2026 [2h]'],
            'cyrillic o in a weekday' => ["M\u{043E}n 14/09/2026 [2h]"],
        ];
    }

    /**
     * A day heading without a readable, existing date, or with a word before it that is not a weekday, is an error and
     * gives no day.
     *
     * @group REQ-PLN-005
     * @group REQ-PLN-015
     * @dataProvider unreadable_date_provider
     * @param string $heading Text of the ### heading.
     */
    public function test_unreadable_date_is_an_error(string $heading): void {
        $result = self::parse(self::md('# MP01 · Dates', '## RA1', '### ' . $heading, '- Lost'));

        $this->assertSame([], $result->days);
        $this->assertSame(['error bad_date 3'], self::issues($result));
        $this->assertSame(['text' => $heading], $result->issues[0]->params);
    }

    /**
     * Days after an unreadable date are still read, and the lines under it belong to no day.
     *
     * @group REQ-PLN-005
     */
    public function test_days_around_an_unreadable_date_are_read(): void {
        $result = self::parse(self::md(
            '# MP01 · Dates that cannot be read',
            '## RA1',
            '### 2026-10-05 [2h]',
            '- Fine',
            '### 2026-02-30 [2h]',
            '- There is no 30 February',
            '### sometime in October',
            '- No date at all',
            '### 2026-10-09 [2h]',
            '- Fine too',
        ));

        $this->assertSame(['2026-10-05', '2026-10-09'], array_map(fn(plan_day $day): string => $day->date, $result->days));
        $this->assertSame('- Fine', $result->days[0]->content);
        $this->assertSame(['error bad_date 5', 'error bad_date 7'], self::issues($result));
        $this->assertSame(['text' => 'sometime in October'], $result->issues[1]->params);
    }

    /**
     * Ways to write the hours.
     *
     * @return array
     */
    public static function hours_provider(): array {
        return [
            'whole hours' => ['[2h]', 2.0],
            'decimal point' => ['[1.5h]', 1.5],
            'decimal comma and a space' => ['[1,5 h]', 1.5],
            'spaces and upper case' => ['[ 2 H ]', 2.0],
            'zero' => ['[0h]', 0.0],
            'two decimals' => ['[10,25h]', 10.25],
        ];
    }

    /**
     * Hours written in the heading are the day's hours.
     *
     * @group REQ-PLN-006
     * @dataProvider hours_provider
     * @param string $hours Hours as written after the date.
     * @param float $expected Expected hours.
     */
    public function test_hours_written_in_the_heading(string $hours, float $expected): void {
        $result = self::parse(self::md('# MP01 · Hours', '## RA1', '### 2026-10-14 ' . $hours . ' Title', '- A day'), null, null);

        $this->assertSame($expected, $result->days[0]->hours);
        $this->assertSame('Title', $result->days[0]->title);
        $this->assertSame([], self::issues($result));
    }

    /**
     * Without hours in the heading, the hours come from the weekday's timetable.
     *
     * @group REQ-PLN-006
     */
    public function test_hours_come_from_the_timetable(): void {
        $result = self::parse(self::md(
            '# MP01 · Hours',
            '## RA1',
            '### 2026-10-05',
            '- Monday',
            '### 2026-10-09 Friday',
            '- Friday',
        ), null, [0 => 1.5, 4 => 2.0]);

        $this->assertSame([1.5, 2.0], array_map(fn(plan_day $day): ?float => $day->hours, $result->days));
        $this->assertSame([], self::issues($result));
    }

    /**
     * When the timetable has no hours for the weekday, the day has no hours and a warning.
     *
     * @group REQ-PLN-006
     */
    public function test_missing_hours_is_a_warning(): void {
        $text = self::md('# MP01 · Hours', '## RA1', '### 2026-10-14', '- A Wednesday', '### 2026-10-16 [2]', '- Not hours');

        $withtimetable = self::parse($text);
        $this->assertNull($withtimetable->days[0]->hours);
        $this->assertSame(2.0, $withtimetable->days[1]->hours);
        $this->assertSame('[2]', $withtimetable->days[1]->title);
        $this->assertSame(['warning no_class_weekday 3', 'warning missing_hours 3'], self::issues($withtimetable));
        $this->assertSame(['date' => '2026-10-14'], $withtimetable->issues[1]->params);

        $without = self::parse($text, null, null);
        $this->assertSame(['warning missing_hours 3', 'warning missing_hours 5'], self::issues($without));
    }

    /**
     * Hours written with digits other than 0 to 9 are not hours but part of the title.
     *
     * @group REQ-PLN-006
     * @group REQ-PLN-015
     */
    public function test_hours_need_digits_0_to_9(): void {
        $result = self::parse(self::md('# MP01 · Hours', '## RA1', '### 2026-10-05 [٢h]', '- A Monday'));

        $this->assertSame(2.0, $result->days[0]->hours);
        $this->assertSame('[٢h]', $result->days[0]->title);
        $this->assertSame([], self::issues($result));
    }

    /**
     * Everything under a ### until the next heading of level 1 to 3 is the day's plan, kept as Markdown.
     *
     * @group REQ-PLN-007
     */
    public function test_day_body_is_kept_as_markdown(): void {
        $result = self::parse(self::md(
            '# MP01 · Bodies',
            '## RA1',
            '### 2026-10-05 [2h]',
            '',
            'Intro with **bold** and a [link](https://example.org).',
            '- item',
            '',
            '#### A level 4 heading stays in the body',
            '',
            '## RA2',
            'Text under a block belongs to no day.',
            '### 2026-10-09 [2h]',
            '   ',
            '### 2026-10-16 [2h]',
            'Last day',
        ));

        $this->assertSame([
            "Intro with **bold** and a [link](https://example.org).\n- item\n\n#### A level 4 heading stays in the body",
            '',
            'Last day',
        ], array_map(fn(plan_day $day): string => $day->content, $result->days));
        $this->assertSame(['warning empty_day 12'], self::issues($result));
        $this->assertSame(['date' => '2026-10-09'], $result->issues[0]->params);
    }

    /**
     * Windows and old Mac line endings give the same lines, and the body is joined with "\n".
     *
     * @group REQ-PLN-007
     * @group REQ-PLN-015
     */
    public function test_line_endings(): void {
        $windows = self::parse("# MP01 · Line endings\r\n## RA1\r\n### 2026-10-05 [2h]\r\n- one\r\n- two\r\n");
        $oldmac = self::parse("# MP01 · Line endings\r## RA1\r### 2026-10-05 [2h]\r- one\r- two\r");

        foreach ([$windows, $oldmac] as $result) {
            $this->assertSame('Line endings', $result->module->title);
            $this->assertSame(3, $result->days[0]->line);
            $this->assertSame("- one\n- two", $result->days[0]->content);
            $this->assertSame([], self::issues($result));
        }
    }

    /**
     * Only LF, CRLF and CR end lines; VT, FF, FS, NEL, LS and PS are characters of the line (and white space at its ends).
     *
     * @group REQ-PLN-007
     * @group REQ-PLN-015
     */
    public function test_only_lf_crlf_and_cr_end_lines(): void {
        $result = self::parse("# MP01 · Line breaks\n## RA1\n### 2026-10-05 [2h]\n- a\x0B- b\u{2028}- c\x1C- d\x0C\u{85}\n"
            . "### 2026-10-09 [2h]\u{2029}\n- e");

        $this->assertSame(['RA1'], $result->blocks);
        $this->assertSame([3, 5], array_map(fn(plan_day $day): int => $day->line, $result->days));
        $this->assertSame("- a\x0B- b\u{2028}- c\x1C- d", $result->days[0]->content);
        $this->assertSame('', $result->days[1]->title);
        $this->assertSame([], self::issues($result));
    }

    /**
     * Headings inside code fences and headings of level 4 or more are body text.
     *
     * @group REQ-PLN-008
     */
    public function test_headings_in_code_fences_and_deeper_headings_are_body(): void {
        $lines = [
            'How to write a day in the plan:',
            self::BACKTICKS . 'markdown',
            '### 2026-12-25 [2h]',
            '# Another module',
            '## Another block',
            self::BACKTICKS,
            '~~~',
            '### 2026-12-26 [2h]',
            '~~~',
            '  ' . self::BACKTICKS,
            '### 2026-12-28 [2h]',
            '   ' . self::BACKTICKS . '  ',
            '#### Level 4',
            '##### Level 5',
            '###### Level 6',
            '####### Seven hashes',
            '#No space after the hashes',
        ];
        $result = self::parse(self::md('# MP99 · Headings inside code', '## RA1', '### 2026-10-05 [2h]', ...$lines));

        $this->assertCount(1, $result->days);
        $this->assertSame(implode("\n", $lines), $result->days[0]->content);
        $this->assertSame(['RA1'], $result->blocks);
        $this->assertSame('MP99', $result->module->code);
        $this->assertSame([], self::issues($result));
    }

    /**
     * Lines that open no fence: backticks in a backtick fence's info text, four spaces or a tab before the fence.
     *
     * @return array
     */
    public static function not_a_fence_provider(): array {
        return [
            'inline code' => [self::BACKTICKS . 'ls' . self::BACKTICKS . ' on one line'],
            'a backtick in the info text' => [self::BACKTICKS . "sql \x60x\x60"],
            'two backticks' => ["\x60\x60"],
            'four spaces before it' => ['    ' . self::BACKTICKS],
            'a tab before it' => ["\t" . self::BACKTICKS],
            'two tildes' => ['~~'],
        ];
    }

    /**
     * A line that opens no fence leaves the next heading as structure.
     *
     * @group REQ-PLN-008
     * @dataProvider not_a_fence_provider
     * @param string $line The line that looks like a fence.
     */
    public function test_line_that_opens_no_fence(string $line): void {
        $result = self::parse(self::md('# MP01 · Fences', '## RA1', '### 2026-10-05 [2h]', $line, '### 2026-10-09 [2h]', '- b'));

        $this->assertCount(2, $result->days);
        $this->assertSame(ltrim($line, " \t"), $result->days[0]->content);
        $this->assertSame([], self::issues($result));
    }

    /**
     * A fence closes only with a line of its own character, at least as long, followed by nothing but spaces.
     *
     * @group REQ-PLN-008
     */
    public function test_fence_closes_only_with_its_own_character_at_least_as_long(): void {
        $fourticks = self::BACKTICKS . "\x60";
        $lines = [
            $fourticks,
            '### inside',
            self::BACKTICKS,
            '~~~~',
            $fourticks . ' x',
            '    ' . $fourticks,
            '   ' . $fourticks . "\x60 \t",
        ];
        $tildes = ["~~~ info with \x60backticks\x60", '### inside', self::BACKTICKS . "\x60\x60", '~~~~'];
        $text = self::md('# MP01 · Fences', '## RA1', '### 2026-10-05 [2h]', ...$lines, ...['### 2026-10-09 [2h]', ...$tildes,
            '### 2026-10-16 [2h]', '- c']);
        $result = self::parse($text);

        $dates = array_map(fn(plan_day $day): string => $day->date, $result->days);
        $this->assertSame(['2026-10-05', '2026-10-09', '2026-10-16'], $dates);
        $this->assertSame(rtrim(implode("\n", $lines)), $result->days[0]->content);
        $this->assertSame(implode("\n", $tildes), $result->days[1]->content);
        $this->assertSame([], self::issues($result));
    }

    /**
     * A fence still open at the end of the text gives a warning at the line that opened it.
     *
     * @group REQ-PLN-008
     */
    public function test_unclosed_fence_warns_at_its_opening_line(): void {
        $result = self::parse(self::md(
            '# MP01 · A fence that is never closed',
            '## RA1',
            '### 2026-10-05 [2h]',
            self::BACKTICKS . 'sql',
            'SELECT 1;',
            '### 2026-10-09 [2h]',
            '- Swallowed by the open fence',
        ));

        $this->assertCount(1, $result->days);
        $this->assertSame(['warning unclosed_fence 4'], self::issues($result));
        $this->assertSame([], $result->issues[0]->params);
    }

    /**
     * Text before the first heading is ignored.
     *
     * @group REQ-PLN-009
     */
    public function test_text_before_the_first_heading_is_ignored(): void {
        $result = self::parse(self::md(
            'Notes before the plan: they are ignored.',
            '- 2026-10-05 is not a day here',
            '#hashtag-is-not-a-heading',
            '',
            '# MP01 · Notes',
            '## RA1',
            '### 2026-10-05 [2h]',
            '- The only day',
        ));

        $this->assertSame(5, $result->module->line);
        $this->assertCount(1, $result->days);
        $this->assertSame('- The only day', $result->days[0]->content);
        $this->assertSame([], self::issues($result));
    }

    /**
     * Two days on the same date are allowed, with no warning, and keep their order.
     *
     * @group REQ-PLN-010
     */
    public function test_several_days_on_one_date(): void {
        $result = self::parse(self::md(
            '# MP01 · Two sessions on the same date',
            '## RA1',
            '### 2026-10-05 [1h] Morning',
            '- First hour',
            '### 2026-10-05 [1h] Afternoon',
            '- Second hour',
        ));

        $this->assertSame(['Morning', 'Afternoon'], array_map(fn(plan_day $day): string => $day->title, $result->days));
        $this->assertSame([], self::issues($result));
        $this->assertSame(2.0, $result->summary()['hours']);
    }

    /**
     * A day dated before the previous day gives a warning.
     *
     * @group REQ-PLN-011
     */
    public function test_day_before_the_previous_day_warns(): void {
        $result = self::parse(self::md(
            '# MP01 · Order',
            '## RA1',
            '### 2026-10-09 [2h]',
            '- Friday',
            '### 2026-10-05 [2h]',
            '- Monday, written after the Friday',
            '### 2026-10-16 [2h]',
            '- Next Friday',
        ));

        $this->assertSame(['warning out_of_order 5'], self::issues($result));
        $this->assertSame(['date' => '2026-10-05', 'previous' => '2026-10-09'], $result->issues[0]->params);
    }

    /**
     * Holiday, class-day and weekday checks.
     *
     * @group REQ-PLN-012
     */
    public function test_holiday_weekday_and_class_day_checks(): void {
        $text = self::md(
            '# MP01 · Checks',
            '## RA1',
            '### 2026-10-12 [2h]',
            '- A Monday that is a holiday',
            '### 2026-10-14 [2h]',
            '- A Wednesday, with no class on Wednesdays',
            '### dl 14/10/2026 [2h]',
            '- Says Monday, but it is a Wednesday',
            '### Fri 16/10/2026 [2h]',
            '- Fine',
        );

        $result = self::parse($text, ['2026-10-12' => "Festa Nacional d'Espanya"]);
        $this->assertSame([
            'warning on_holiday 3',
            'warning no_class_weekday 5',
            'warning weekday_mismatch 7',
            'warning no_class_weekday 7',
        ], self::issues($result));
        $this->assertSame(['date' => '2026-10-12', 'holiday' => "Festa Nacional d'Espanya"], $result->issues[0]->params);
        $this->assertSame(['date' => '2026-10-14'], $result->issues[1]->params);
        $this->assertSame(['weekday' => 'dl', 'date' => '2026-10-14'], $result->issues[2]->params);

        // Without holidays and timetable only the weekday prefix can be checked.
        $this->assertSame(['warning weekday_mismatch 7'], self::issues(self::parse($text, null, null)));
    }

    /**
     * An empty timetable is a timetable with no class day, not a missing timetable.
     *
     * @group REQ-PLN-012
     */
    public function test_empty_timetable_has_no_class_day(): void {
        $result = self::parse(self::md('# MP01 · Checks', '## RA1', '### 2026-10-05', '- A Monday'), null, []);

        $this->assertSame(['warning no_class_weekday 3', 'warning missing_hours 3'], self::issues($result));
    }

    /**
     * A day outside the school year gives a warning; the first and last days are inside.
     *
     * @group REQ-PLN-013
     */
    public function test_day_outside_the_school_year_warns(): void {
        $text = self::md(
            '# MP01 · School year',
            '## RA1',
            '### 2026-09-13 [2h]',
            '- The day before',
            '### 2026-09-14 [2h]',
            '- First day',
            '### 2027-06-21 [2h]',
            '- Last day',
            '### 2027-06-22 [2h]',
            '- The day after',
        );

        $result = self::parse($text, null, null, ['2026-09-14', '2027-06-21']);
        $this->assertSame(['warning outside_year 3', 'warning outside_year 9'], self::issues($result));
        $this->assertSame(['date' => '2026-09-13'], $result->issues[0]->params);

        $this->assertSame([], self::issues(self::parse($text, null, null, null)));
    }

    /**
     * School years that are not two ISO dates.
     *
     * @return array
     */
    public static function bad_year_provider(): array {
        return [
            'one date' => [['2026-09-14']],
            'three dates' => [['2026-09-14', '2027-06-21', '2027-07-01']],
            'impossible date' => [['2026-09-14', '2027-02-30']],
            'day/month/year' => [['14/09/2026', '2027-06-21']],
        ];
    }

    /**
     * A school year that is not two ISO dates is a programming error.
     *
     * @group REQ-PLN-013
     * @dataProvider bad_year_provider
     * @param array $year The school year given to the parser.
     */
    public function test_school_year_must_be_two_iso_dates(array $year): void {
        $this->expectException(\InvalidArgumentException::class);
        self::parse(self::md('# MP01 · School year'), null, null, $year);
    }

    /**
     * The summary has the keys, order and meaning of summarize() in the reference.
     *
     * @group REQ-PLN-014
     */
    public function test_summary_follows_the_reference(): void {
        $result = self::parse(self::md(
            '# MP 0372 · Gestió',
            '## RA1',
            '### 2026-10-09 [2h]',
            '- Friday',
            '### 2026-10-05 [1,5h]',
            '### 2026-02-30',
            '### never',
            '### 2026-10-14',
        ));

        $this->assertSame([
            'module' => 'Gestió',
            'code' => 'MP 0372',
            'blocks' => 1,
            'days' => 3,
            'hours' => 3.5,
            'first' => '2026-10-05',
            'last' => '2026-10-14',
            'errors' => ['bad_date', 'bad_date'],
            'warnings' => ['empty_day', 'empty_day', 'missing_hours', 'no_class_weekday', 'out_of_order'],
        ], $result->summary());
    }

    /**
     * Issues are listed by line and, on one line, in the order the reference finds them.
     *
     * @group REQ-PLN-014
     */
    public function test_issues_are_ordered_by_line(): void {
        $result = self::parse(self::md(
            '# MP01 · Order of issues',
            '### dl 09/10/2026',
            '### 2026-02-30',
            '# MP02 · Second module',
            '### 2026-10-09 [2h]',
            self::BACKTICKS,
            '### 2026-10-12 [2h]',
        ), null, [0 => 2.0]);

        $this->assertSame([
            'warning weekday_mismatch 2',
            'warning no_class_weekday 2',
            'warning missing_hours 2',
            'warning no_block 2',
            'warning empty_day 2',
            'error bad_date 3',
            'error multiple_modules 4',
            'warning no_class_weekday 5',
            'warning no_block 5',
            'warning unclosed_fence 6',
        ], self::issues($result));
    }

    /**
     * The summary of an empty text.
     *
     * @group REQ-PLN-014
     */
    public function test_summary_of_an_empty_text(): void {
        $this->assertSame([
            'module' => null,
            'code' => null,
            'blocks' => 0,
            'days' => 0,
            'hours' => 0.0,
            'first' => null,
            'last' => null,
            'errors' => [],
            'warnings' => ['no_module_heading'],
        ], self::parse('')->summary());
    }

    /**
     * Long runs of spaces, which make backtracking regular expressions fail, are read like short ones, and quickly.
     *
     * @group REQ-PLN-001
     * @group REQ-PLN-004
     * @group REQ-PLN-016
     */
    public function test_long_runs_of_spaces(): void {
        $spaces = str_repeat(' ', 200000);
        $started = hrtime(true);
        $result = self::parse(self::md(
            '# MP01' . $spaces . '· Module',
            '## RA1' . $spaces . '#',
            '### 2026-10-05 [2h]' . $spaces . 'Title' . $spaces . '#' . $spaces,
            '- a' . $spaces . 'b' . $spaces,
        ));

        $this->assertSame('MP01', $result->module->code);
        $this->assertSame('Module', $result->module->title);
        $this->assertSame(['RA1'], $result->blocks);
        $this->assertSame('Title', $result->days[0]->title);
        $this->assertSame('- a' . $spaces . 'b', $result->days[0]->content);

        $digits = str_repeat('1', 200000);
        $this->assertNull(self::parse('# ' . $digits . '! Not a code')->module->code);
        $this->assertLessThan(1.0, (hrtime(true) - $started) / 1e9);
    }

    /**
     * Lines of thousands of hashes, backticks, tildes, digits, letters or spaces, about 2 MB in all, take well under a
     * second.
     *
     * @group REQ-PLN-016
     */
    public function test_hostile_text_is_read_in_linear_time(): void {
        $n = 100000;
        $lines = [
            '# ' . str_repeat('1', $n) . '! ' . str_repeat(' ', $n) . 'x',
            '## ' . str_repeat('#', $n) . 'x' . str_repeat(' #', $n),
            '### dl' . str_repeat(' ', $n) . 'x',
            '### ' . str_repeat('a', $n) . ' 2026-10-05',
            '### 2026-10-05 [' . str_repeat('1', $n) . str_repeat(' ', $n) . 'x',
            str_repeat(self::BACKTICKS, intdiv($n, 3)) . 'x',
            '~~~',
            str_repeat('~', $n) . 'x',
            '- ' . str_repeat(" \u{A0}", $n) . 'x',
            str_repeat('~', $n),
        ];
        $started = hrtime(true);
        $result = self::parse(self::md(...$lines));

        $this->assertLessThan(1.0, (hrtime(true) - $started) / 1e9);
        $this->assertSame(['error bad_date 3', 'error bad_date 4', 'warning unclosed_fence 6'], self::issues($result));
        $this->assertNull($result->module->code);
        $this->assertCount(1, $result->days);
        $this->assertSame([str_repeat('#', $n) . 'x' . str_repeat(' #', $n - 1)], $result->blocks);
    }

    /**
     * The hostile-input fixture gives its recorded results in well under a second.
     *
     * @group REQ-PLN-016
     */
    public function test_long_lines_fixture_is_read_quickly(): void {
        $text = file_get_contents(__DIR__ . '/../fixtures/cases/long-lines.md');
        $started = hrtime(true);
        $summary = self::parse($text)->summary();

        $this->assertLessThan(1.0, (hrtime(true) - $started) / 1e9);
        $this->assertSame(['multiple_modules'], $summary['errors']);
        $this->assertSame(1, $summary['days']);
        $this->assertSame(1, $summary['blocks']);
    }

    /**
     * Texts that are not UTF-8 at some line.
     *
     * @return array
     */
    public static function not_utf8_provider(): array {
        return [
            'latin-1 letter' => ["# MP01 · Plan\n## RA1\n### 2026-10-05 [2h]\n- caf\xE9\n", 4],
            'invalid sequence on line 1' => ["# MP01 \xC3\x28 Not UTF-8\n", 1],
            'truncated sequence at the end' => ["# MP01 · Plan\r\n\r\n### 2026-10-05\xC3", 3],
            'encoded surrogate' => ["# MP01\r## \xED\xA0\x80", 2],
            'overlong slash' => ["# MP01\n\n\n\xC0\xAF", 4],
        ];
    }

    /**
     * A text that is not UTF-8 is refused as a whole: one error at the first bad line, nothing read.
     *
     * @group REQ-PLN-015
     * @dataProvider not_utf8_provider
     * @param string $text The bytes of the plan.
     * @param int $line The first line that is not UTF-8.
     */
    public function test_text_that_is_not_utf8_is_refused(string $text, int $line): void {
        $result = self::parse($text);

        $this->assertNull($result->module);
        $this->assertSame([], $result->blocks);
        $this->assertSame([], $result->days);
        $this->assertSame(["error not_utf8 $line"], self::issues($result));
        $this->assertSame(['not_utf8'], $result->summary()['errors']);
        $this->assertSame([], $result->summary()['warnings']);
    }

    /**
     * The three real plans with the results recorded in fixtures/expected.json.
     *
     * @return array
     */
    public static function real_plan_provider(): array {
        $expected = self::read_json('expected.json');
        $year = [$expected['school_year']['start'], $expected['school_year']['end']];
        $data = [];
        foreach ($expected['plans'] as $code => $plan) {
            $timetable = [];
            foreach ($plan['timetable'] as $weekday => $hours) {
                $timetable[(int) $weekday] = (float) $hours;
            }
            $expected = array_diff_key($plan, array_flip(['file', 'req', 'timetable']));
            $data['plan ' . $code] = [$plan['file'], $timetable, $year, $expected];
        }
        return $data;
    }

    /**
     * The real plans give the module, counts, hours, dates and (no) issues recorded for them.
     *
     * @group REQ-PLN-014
     * @dataProvider real_plan_provider
     * @param string $file Plan file, relative to tests/fixtures.
     * @param float[] $timetable Weekday to hours.
     * @param string[] $year First and last day of the school year.
     * @param array $expected Expected summary values.
     */
    public function test_real_plans_match_the_reference(string $file, array $timetable, array $year, array $expected): void {
        $this->assert_summary($expected, self::parse_fixture($file, $timetable, $year));
    }

    /**
     * The edge cases with the results recorded in fixtures/cases-expected.json.
     *
     * @return array
     */
    public static function edge_case_provider(): array {
        $schoolyear = self::read_json('expected.json')['school_year'];
        $data = [];
        foreach (self::read_json('cases-expected.json') as $file => $case) {
            $timetable = self::TIMETABLE;
            if (array_key_exists('timetable', $case)) {
                $timetable = $case['timetable'] === 'none' ? null : self::read_timetable($case['timetable']);
            }
            $expected = array_diff_key($case, array_flip(['req', 'timetable']));
            $data[$file] = ['cases/' . $file, $timetable, [$schoolyear['start'], $schoolyear['end']], $expected];
        }
        return $data;
    }

    /**
     * The edge cases give the summary values recorded for them.
     *
     * @group REQ-PLN-001
     * @group REQ-PLN-002
     * @group REQ-PLN-003
     * @group REQ-PLN-004
     * @group REQ-PLN-005
     * @group REQ-PLN-006
     * @group REQ-PLN-007
     * @group REQ-PLN-008
     * @group REQ-PLN-010
     * @group REQ-PLN-011
     * @group REQ-PLN-012
     * @group REQ-PLN-013
     * @group REQ-PLN-015
     * @group REQ-PLN-016
     * @dataProvider edge_case_provider
     * @param string $file Case file, relative to tests/fixtures.
     * @param float[]|null $timetable Weekday to hours, or null for none.
     * @param string[] $year First and last day of the school year.
     * @param array $expected Expected summary values.
     */
    public function test_edge_cases_match_the_reference(string $file, ?array $timetable, array $year, array $expected): void {
        $this->assert_summary($expected, self::parse_fixture($file, $timetable, $year));
    }

    /**
     * Asserts that the summary has the expected value for every expected key.
     *
     * @param array $expected Expected values, a subset of the summary keys.
     * @param array $summary Summary from the parser.
     */
    private function assert_summary(array $expected, array $summary): void {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $summary);
            if ($key === 'hours') {
                $this->assertEqualsWithDelta((float) $value, $summary[$key], 1e-9, $key);
            } else {
                $this->assertSame($value, $summary[$key], $key);
            }
        }
    }

    /**
     * Parses a fixture plan with the fixture holidays.
     *
     * @param string $file Plan file, relative to tests/fixtures.
     * @param float[]|null $timetable Weekday to hours.
     * @param string[] $year First and last day of the school year.
     * @return array The summary.
     */
    private static function parse_fixture(string $file, ?array $timetable, array $year): array {
        $text = file_get_contents(__DIR__ . '/../fixtures/' . $file);
        return self::parse($text, self::read_json('holidays-expanded.json'), $timetable, $year)->summary();
    }

    /**
     * Parses a plan; by default with the edge-case timetable and no holidays or school year.
     *
     * @param string $text The Markdown plan.
     * @param string[]|null $holidays ISO date to label.
     * @param float[]|null $timetable Weekday (Monday = 0) to hours.
     * @param string[]|null $year First and last day of the school year.
     * @return plan_result
     */
    private static function parse(
        string $text,
        ?array $holidays = null,
        ?array $timetable = self::TIMETABLE,
        ?array $year = null,
    ): plan_result {
        return (new plan_parser())->parse($text, $holidays, $timetable, $year);
    }

    /**
     * Joins lines into a plan text that ends with a line break.
     *
     * @param string ...$lines The lines.
     * @return string
     */
    private static function md(string ...$lines): string {
        return implode("\n", $lines) . "\n";
    }

    /**
     * Lists the issues as "level code line", in their order.
     *
     * @param plan_result $result A parse result.
     * @return string[]
     */
    private static function issues(plan_result $result): array {
        return array_map(fn(plan_issue $issue): string => "{$issue->level} {$issue->code} {$issue->line}", $result->issues);
    }

    /**
     * Reads a JSON fixture.
     *
     * @param string $file File name, relative to tests/fixtures.
     * @return array
     */
    private static function read_json(string $file): array {
        return json_decode(file_get_contents(__DIR__ . '/../fixtures/' . $file), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Reads a timetable written as in tools/check_plan.py, such as "0:2,4:2".
     *
     * @param string $spec Weekday:hours pairs separated by commas.
     * @return float[]
     */
    private static function read_timetable(string $spec): array {
        $timetable = [];
        foreach (explode(',', $spec) as $pair) {
            [$weekday, $hours] = explode(':', $pair);
            $timetable[(int) $weekday] = (float) $hours;
        }
        return $timetable;
    }
}
