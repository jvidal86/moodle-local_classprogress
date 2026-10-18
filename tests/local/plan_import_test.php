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

use local_classprogress\event\plan_approved;
use local_classprogress\event\plan_imported;

/**
 * Tests for importing a plan text into a draft, approving it, and the lock on an approved plan.
 *
 * Timetables are weekday => hours with Monday = 0 and Sunday = 6. 2026-09-14 and 2026-10-12 are Mondays, 2026-09-15 is
 * a Tuesday and 2026-09-18 a Friday.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\local\plan_service
 */
final class plan_import_test extends \advanced_testcase {
    /** @var string A small clean plan: two blocks, three days, hours from the text and from the timetable. */
    private const PLAN = "Notes before the first heading.\n"
        . "# MP 0372 · Gestió de bases de dades\n"
        . "\n"
        . "## RA1 · Reconeix els elements\n"
        . "\n"
        . "### 2026-09-14 [1,5h] Presentació\n"
        . "- Normes de l'aula\n"
        . "- **Prova** diagnòstica\n"
        . "\n"
        . "### dv 18/09/2026\n"
        . "Entorn de treball\n"
        . "\n"
        . "## RA2 · Escriu i prova codi\n"
        . "\n"
        . "### 2026-09-21 [2h]\n"
        . "Primer programa\n";

    /**
     * A new user with a teacher role in a course.
     *
     * @return \stdClass The user.
     */
    private function teacher(): \stdClass {
        return $this->getDataGenerator()->create_and_enrol($this->getDataGenerator()->create_course(), 'editingteacher');
    }

    /**
     * A draft plan of a new teacher, in a school year 2026-09-14 to 2027-06-21, through the services.
     *
     * @param string $holidays Holiday text of the school year.
     * @param float[] $timetable Weekday => hours.
     * @param array $fields Plan fields to replace.
     * @return array [user, plan id].
     */
    private function draft(string $holidays = '', array $timetable = [0 => 2, 4 => 2], array $fields = []): array {
        $user = $this->teacher();
        $yearid = schoolyear_service::create($user->id, (object) [
            'name' => 'Curs 2026-2027',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
            'holidays' => $holidays,
        ]);
        $id = plan_service::create($user->id, (object) array_merge([
            'schoolyearid' => $yearid,
            'courseid' => null,
            'modulecode' => 'MP 0000',
            'moduletitle' => 'Typed title',
            'startdate' => '2026-09-14',
            'enddate' => '2027-05-24',
            'timetable' => $timetable,
        ], $fields));
        return [$user, $id];
    }

    /**
     * Approves a plan as the approval page would: with the fingerprint of what it shows.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @param bool $warningsread Whether the teacher ticked "I have read the warnings".
     */
    private static function approve(int $userid, int $id, bool $warningsread = true): void {
        plan_service::approve($userid, $id, plan_service::fingerprint(plan_service::check_stored($userid, $id)), $warningsread);
    }

    /**
     * Lists the issues of a result as "level code line".
     *
     * @param plan_result $result The result.
     * @return string[] The issues.
     */
    private static function issues(plan_result $result): array {
        return array_map(fn(plan_issue $i): string => "{$i->level} {$i->code} {$i->line}", $result->issues);
    }

    /**
     * The rows that hang from a plan, to compare before and after.
     *
     * @param int $id Plan id.
     * @return array Table => rows.
     */
    private static function rows(int $id): array {
        global $DB;

        return [
            'plan' => $DB->get_record('local_classprogress_plan', ['id' => $id]),
            'slot' => $DB->get_records('local_classprogress_slot', ['planid' => $id], 'id'),
            'planitem' => $DB->get_records('local_classprogress_planitem', ['planid' => $id], 'id'),
            'import' => $DB->get_records('local_classprogress_import', ['planid' => $id], 'id'),
        ];
    }

    /**
     * Checking a text reads it with the school year's holidays, the plan's timetable and the school year, and writes
     * nothing.
     *
     * @group REQ-IMP-002
     * @group REQ-PLN-012
     * @group REQ-PLN-013
     */
    public function test_check_reads_the_text_with_holidays_timetable_and_school_year_and_writes_nothing(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft("2026-10-12 Festa Nacional\n", [0 => 2]);
        $before = self::rows($id);
        $text = "# MP 0372 · Gestió\n## RA1\n### 2026-09-15\nx\n### 2026-10-12 [2h]\ny\n### 2027-07-01 [2h]\nz\n";

        $result = plan_service::check($user->id, $id, $text);

        $this->assertSame([
            'warning no_class_weekday 3',
            'warning missing_hours 3',
            'warning on_holiday 5',
            'warning no_class_weekday 7',
            'warning outside_year 7',
        ], self::issues($result));
        $this->assertSame(['date' => '2026-10-12', 'holiday' => 'Festa Nacional'], $result->issues[2]->params);
        $this->assertEquals($before, self::rows($id));
    }

    /**
     * A plan without a timetable has no class-day check, and a day without hours in the text has none.
     *
     * @group REQ-PLN-006
     * @group REQ-IMP-002
     */
    public function test_a_plan_without_timetable_does_not_check_class_days(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft('', []);

        $result = plan_service::check($user->id, $id, "# MP03 · P\n## RA1\n### 2026-09-15\nx\n");

        $this->assertSame(['warning missing_hours 3'], self::issues($result));
        $this->assertNull($result->days[0]->hours);
    }

    /**
     * Importing stores the text, one item per day with its date, block, title, Markdown and hours (from the text, or
     * else the timetable), and a log row; the # heading's code and title replace the typed ones.
     *
     * @group REQ-IMP-003
     * @group REQ-PLN-001
     * @group REQ-PLN-006
     */
    public function test_import_saves_the_text_the_items_and_a_log_row(): void {
        global $DB;

        $this->resetAfterTest();
        [$user, $id] = $this->draft();

        $importid = plan_service::import($user->id, $id, self::PLAN, plan_service::SOURCE_UPLOAD, 'MP0372 pla.md');

        $plan = plan_service::get($user->id, $id);
        $this->assertSame(self::PLAN, $plan->sourcetext);
        $this->assertSame('MP 0372', $plan->modulecode);
        $this->assertSame('Gestió de bases de dades', $plan->moduletitle);
        $this->assertSame('draft', $plan->status);
        $items = array_values(plan_service::items($user->id, $id));
        $this->assertSame(
            [
                ['1', '2026-09-14', 'RA1 · Reconeix els elements', 'Presentació', "- Normes de l'aula\n- **Prova** diagnòstica",
                    1.5],
                ['2', '2026-09-18', 'RA1 · Reconeix els elements', '', 'Entorn de treball', 2.0],
                ['3', '2026-09-21', 'RA2 · Escriu i prova codi', '', 'Primer programa', 2.0],
            ],
            array_map(fn(\stdClass $i): array => [(string) $i->sortorder, $i->sessiondate, $i->block, $i->title, $i->content,
                (float) $i->hours], $items)
        );
        $import = $DB->get_record('local_classprogress_import', ['id' => $importid], '*', MUST_EXIST);
        $this->assertSame((string) $id, (string) $import->planid);
        $this->assertSame('upload', $import->source);
        $this->assertSame('MP0372 pla.md', $import->filename);
        $this->assertSame(sha1(self::PLAN), $import->contenthash);
        $this->assertSame('3', (string) $import->daycount);
    }

    /**
     * A day with no block is stored with an empty block, and a day without hours anywhere with no hours.
     *
     * @group REQ-IMP-003
     * @group REQ-PLN-003
     */
    public function test_a_day_without_block_or_hours_is_stored_empty(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft('', [0 => 2]);

        plan_service::import($user->id, $id, "### 2026-09-15\nx\n");

        $item = current(plan_service::items($user->id, $id));
        $this->assertSame('', $item->block);
        $this->assertNull($item->hours);
    }

    /**
     * The # heading decides the module: its code and its title replace the typed ones, each only when the heading
     * gives it; without a heading the typed code and title stay.
     *
     * @dataProvider module_cases
     * @group REQ-PLN-001
     * @group REQ-IMP-003
     * @param string $heading The text's first lines.
     * @param string $code Expected module code.
     * @param string $title Expected module title.
     */
    public function test_the_module_heading_replaces_the_typed_code_and_title(string $heading, string $code, string $title): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        $text = $heading . "## RA1\n### 2026-09-14\nx\n";
        $plan = plan_service::get($user->id, $id);

        $this->assertSame([$code, $title], plan_service::imported_module($plan, plan_service::check($user->id, $id, $text)));
        plan_service::import($user->id, $id, $text);

        $plan = plan_service::get($user->id, $id);
        $this->assertSame([$code, $title], [$plan->modulecode, $plan->moduletitle]);
    }

    /**
     * Module headings and the module they give.
     *
     * @return array Case => [heading, code, title].
     */
    public static function module_cases(): array {
        return [
            'code and title' => ["# MP 0372 · Gestió de bases de dades\n", 'MP 0372', 'Gestió de bases de dades'],
            'title only' => ["# Programació <C++> i Java\n", 'MP 0000', 'Programació <C++> i Java'],
            'no heading' => ['', 'MP 0000', 'Typed title'],
            'empty heading' => ["# \n", 'MP 0000', 'Typed title'],
        ];
    }

    /**
     * Importing into a draft that already has content replaces its text and items, and adds a row to the import log.
     *
     * @group REQ-IMP-003
     */
    public function test_a_new_import_replaces_the_draft_content(): void {
        global $DB;

        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);
        $text = "# MP 0372 · Gestió\n## RA9\n### 2026-10-05 [2h]\nNou\n";

        plan_service::import($user->id, $id, $text);

        $this->assertSame($text, plan_service::get($user->id, $id)->sourcetext);
        $items = plan_service::items($user->id, $id);
        $this->assertCount(1, $items);
        $this->assertSame(['2026-10-05', 'RA9', 'Nou'], [current($items)->sessiondate, current($items)->block,
            current($items)->content]);
        $this->assertSame(1, $DB->count_records('local_classprogress_planitem', ['planid' => $id]));
        $this->assertSame(2, $DB->count_records('local_classprogress_import', ['planid' => $id]));
    }

    /**
     * Line ends are stored as line feeds, so a text gives the same result and fingerprint however the browser sent it;
     * a null byte is dropped as it is from posted text. Everything else is stored exactly as typed.
     *
     * @group REQ-DAT-004
     * @group REQ-PLN-015
     */
    public function test_line_ends_are_stored_as_line_feeds_and_text_as_typed(): void {
        global $DB;

        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        $typed = "# MP 0372 · A <b>&amp;</b>\r\n## RA1 <- x & y\r### 2026-09-14 [2h]\r\n<script>alert(1)</script>\0\r\n";
        $stored = "# MP 0372 · A <b>&amp;</b>\n## RA1 <- x & y\n### 2026-09-14 [2h]\n<script>alert(1)</script>\n";

        $importid = plan_service::import($user->id, $id, $typed);

        $this->assertSame($stored, plan_service::get($user->id, $id)->sourcetext);
        $this->assertSame('A <b>&amp;</b>', plan_service::get($user->id, $id)->moduletitle);
        $this->assertSame('RA1 <- x & y', current(plan_service::items($user->id, $id))->block);
        $this->assertSame(sha1($stored), $DB->get_field('local_classprogress_import', 'contenthash', ['id' => $importid]));
        $this->assertSame(
            plan_service::fingerprint(plan_service::check($user->id, $id, $typed)),
            plan_service::fingerprint(plan_service::check($user->id, $id, $stored))
        );
    }

    /**
     * The real plan 0372, with the school's holidays and a Monday and Friday timetable, imports cleanly: 60 items in 7
     * blocks, 120 hours, module MP 0372.
     *
     * @group REQ-PLN-014
     * @group REQ-IMP-003
     */
    public function test_the_real_plan_0372_imports_cleanly(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft(file_get_contents(__DIR__ . '/../fixtures/holidays-centre-2026-27.txt'), [0 => 2, 4 => 2]);
        $text = file_get_contents(__DIR__ . '/../fixtures/plans/0372.md');

        $this->assertSame([], self::issues(plan_service::check($user->id, $id, $text)));
        plan_service::import($user->id, $id, $text);

        $plan = plan_service::get($user->id, $id);
        $items = plan_service::items($user->id, $id);
        $this->assertSame('MP 0372', $plan->modulecode);
        $this->assertSame('Gestió de bases de dades', $plan->moduletitle);
        $this->assertCount(60, $items);
        $this->assertCount(7, array_unique(array_column($items, 'block')));
        $this->assertEqualsWithDelta(120.0, array_sum(array_map(fn(\stdClass $i): float => (float) $i->hours, $items)), 0.001);
        $this->assertSame('2026-09-14', reset($items)->sessiondate);
        $this->assertSame('2027-05-24', end($items)->sessiondate);
        $this->assertSame([], self::issues(plan_service::check_stored($user->id, $id)));
    }

    /**
     * A text larger than the size limit is refused before it is read: by default 200 KB, or the admin setting in KB.
     *
     * @group REQ-IMP-001
     */
    public function test_a_text_above_the_size_limit_is_refused(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        $fits = static fn(int $bytes): string => "### 2026-09-14\n" . str_repeat('x', $bytes - 15);

        unset_config('maxplansize', 'local_classprogress');
        $this->assertCount(1, plan_service::check($user->id, $id, $fits(200 * 1024))->days);
        set_config('maxplansize', 1, 'local_classprogress');
        $this->assertCount(1, plan_service::check($user->id, $id, $fits(1024))->days);
        $calls = [
            fn() => plan_service::check($user->id, $id, $fits(1025)),
            fn() => plan_service::import($user->id, $id, $fits(1025)),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('Expected an exception');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_plantoolarge', $e->errorcode);
                $this->assertSame(1, $e->a);
            }
        }
        $this->assertNull(plan_service::get($user->id, $id)->sourcetext);
    }

    /**
     * More days than the day limit is an error at the first day over it, so the text cannot be saved: by default 400
     * days, or the admin setting.
     *
     * @group REQ-IMP-001
     */
    public function test_more_days_than_the_day_limit_is_an_error(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        $text = "# MP03 · P\n## RA1\n### 2026-09-14\na\n### 2026-09-18\nb\n### 2026-09-21\nc\n";

        $this->assertSame(400, plan_service::max_days());
        set_config('maxplandays', 2, 'local_classprogress');
        $result = plan_service::check($user->id, $id, $text);

        $this->assertSame(['error too_many_days 7'], self::issues($result));
        $this->assertSame(['max' => '2'], $result->issues[0]->params);
        $this->expectExceptionObject(new \moodle_exception('error_planhaserrors', 'local_classprogress'));
        plan_service::import($user->id, $id, $text);
    }

    /**
     * A text that is not UTF-8, which only an uploaded file can give, is refused as a whole.
     *
     * @group REQ-PLN-015
     * @group REQ-IMP-001
     */
    public function test_a_text_that_is_not_utf8_is_refused(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        $text = "# MP03 · P\n### 2026-09-14\nProgramaci\xf3\n";

        $this->assertSame(['error not_utf8 3'], self::issues(plan_service::check($user->id, $id, $text)));
        try {
            plan_service::import($user->id, $id, $text);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_planhaserrors', $e->errorcode);
        }
        $this->assertNull(plan_service::get($user->id, $id)->sourcetext);
    }

    /**
     * Values the database cannot hold, or dates outside the limits, are errors at their line, so they are shown in the
     * preview instead of failing the save.
     *
     * @dataProvider unstorable_cases
     * @group REQ-CAL-004
     * @group REQ-IMP-002
     * @param string $text The plan.
     * @param string[] $expected Issues as "level code line".
     */
    public function test_values_that_cannot_be_stored_are_errors(string $text, array $expected): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();

        $this->assertSame($expected, self::issues(plan_service::check($user->id, $id, "# MP03 · P\n## RA1\n" . $text)));
    }

    /**
     * Texts with values that cannot be stored.
     *
     * @return array Case => [text after "# MP03 · P" and "## RA1", issues].
     */
    public static function unstorable_cases(): array {
        return [
            'block of 256 characters' => ["## " . str_repeat('é', 256) . "\n### 2026-09-14\nx\n### 2026-09-18\ny\n",
                ['error block_too_long 4']],
            'block of 255 characters' => ["## " . str_repeat('é', 255) . "\n### 2026-09-14\nx\n", []],
            'title of 256 characters' => ["### 2026-09-14 " . str_repeat('t', 256) . "\nx\n", ['error title_too_long 3']],
            'more than 24 hours' => ["### 2026-09-14 [24,5h]\nx\n", ['error too_many_hours 3']],
            '24 hours' => ["### 2026-09-14 [24h]\nx\n", []],
            'year 2100' => ["### 2100-01-04 [2h]\nx\n", ['warning outside_year 3', 'error out_of_range 3']],
            'no days' => ["Just notes.\n", ['error no_days 1']],
        ];
    }

    /**
     * A module code longer than 100 characters or a module title longer than 255 cannot be stored either.
     *
     * @group REQ-IMP-002
     */
    public function test_a_module_heading_too_long_to_store_is_an_error(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        $days = "\n## RA1\n### 2026-09-14\nx\n";

        foreach (['C' => 100, 'T' => 255] as $char => $max) {
            $heading = $char === 'C' ? '# ' . str_repeat('C', $max) . ' · T' : '# MP03 · ' . str_repeat('T', $max);
            $this->assertSame([], self::issues(plan_service::check($user->id, $id, $heading . $days)));
            $heading = $char === 'C' ? '# ' . str_repeat('C', $max + 1) . ' · T' : '# MP03 · ' . str_repeat('T', $max + 1);
            $this->assertSame(['error module_too_long 1'], self::issues(plan_service::check($user->id, $id, $heading . $days)));
        }
    }

    /**
     * A text with errors is not saved: nothing changes.
     *
     * @group REQ-PLN-005
     * @group REQ-IMP-004
     * @group REQ-IMP-005
     */
    public function test_a_text_with_errors_saves_nothing(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);
        $before = self::rows($id);

        try {
            plan_service::import($user->id, $id, self::PLAN . "### 2026-02-30\nx\n");
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_planhaserrors', $e->errorcode);
        }

        $this->assertEquals($before, self::rows($id));
    }

    /**
     * Every write of an import is part of one transaction: when it is rolled back, the text, the items and the log row
     * all go, and the plan is as before.
     *
     * @group REQ-IMP-005
     */
    public function test_an_import_is_written_in_one_transaction(): void {
        global $DB;

        $this->resetAfterTest();
        // The test's own transaction would make this one nested, and only the outermost one really rolls back.
        $this->preventResetByRollback();
        [$user, $id] = $this->draft();
        $before = self::rows($id);

        $transaction = $DB->start_delegated_transaction();
        plan_service::import($user->id, $id, self::PLAN);
        $this->assertCount(3, plan_service::items($user->id, $id));
        try {
            $transaction->rollback(new \moodle_exception('error_notfound', 'local_classprogress'));
        } catch (\moodle_exception $e) {
            $this->assertSame('error_notfound', $e->errorcode);
        }

        $this->assertEquals($before, self::rows($id));
    }

    /**
     * An import triggers plan_imported once its transaction is committed, in the owner's user context, with ids only.
     *
     * @group REQ-AUD-001
     */
    public function test_an_import_triggers_plan_imported(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        $this->setUser($user);
        $sink = $this->redirectEvents();

        $importid = plan_service::import($user->id, $id, self::PLAN);

        $events = array_values(array_filter($sink->get_events(), fn($e): bool => $e instanceof plan_imported));
        $this->assertCount(1, $events);
        $this->assertSame($id, (int) $events[0]->objectid);
        $this->assertSame((int) $user->id, (int) $events[0]->userid);
        $this->assertSame(\context_user::instance($user->id)->id, $events[0]->get_context()->id);
        $this->assertEquals(
            ['schoolyearid' => plan_service::get($user->id, $id)->schoolyearid, 'importid' => $importid],
            $events[0]->other
        );
    }

    /**
     * Approving locks the plan: status approved, the time of approval, and plan_approved after the commit.
     *
     * @group REQ-LCK-002
     * @group REQ-AUD-001
     */
    public function test_approval_records_the_time_and_triggers_plan_approved(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);
        $this->setUser($user);
        $sink = $this->redirectEvents();
        $before = time();

        self::approve($user->id, $id, false);

        $plan = plan_service::get($user->id, $id);
        $this->assertSame('approved', $plan->status);
        $this->assertGreaterThanOrEqual($before, (int) $plan->timeapproved);
        $events = array_values(array_filter($sink->get_events(), fn($e): bool => $e instanceof plan_approved));
        $this->assertCount(1, $events);
        $this->assertSame($id, (int) $events[0]->objectid);
        $this->assertSame(\context_user::instance($user->id)->id, $events[0]->get_context()->id);
        $this->assertEquals(['schoolyearid' => $plan->schoolyearid], $events[0]->other);
    }

    /**
     * Warnings must be confirmed: without "I have read the warnings" the approval is refused; with it, it goes ahead.
     *
     * @group REQ-IMP-004
     */
    public function test_approval_needs_the_warnings_confirmed(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft("2026-09-14 Festa\n");
        plan_service::import($user->id, $id, self::PLAN);

        try {
            self::approve($user->id, $id, false);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_confirmwarnings', $e->errorcode);
        }
        $this->assertSame('draft', plan_service::get($user->id, $id)->status);

        self::approve($user->id, $id, true);
        $this->assertSame('approved', plan_service::get($user->id, $id)->status);
    }

    /**
     * A stored text that has errors now (here because the day limit was lowered) cannot be approved.
     *
     * @group REQ-IMP-004
     */
    public function test_a_plan_with_errors_cannot_be_approved(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);
        set_config('maxplandays', 2, 'local_classprogress');

        try {
            self::approve($user->id, $id);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_planhaserrors', $e->errorcode);
        }
        $this->assertSame('draft', plan_service::get($user->id, $id)->status);
    }

    /**
     * Approval is refused when what the teacher saw is not what would be approved: a holiday added in the meantime
     * changes the warnings.
     *
     * @group REQ-IMP-004
     * @group REQ-DAT-003
     */
    public function test_approval_of_a_preview_that_changed_is_refused(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);
        $seen = plan_service::fingerprint(plan_service::check_stored($user->id, $id));
        $plan = plan_service::get($user->id, $id);
        $year = schoolyear_service::get($user->id, $plan->schoolyearid);
        schoolyear_service::update($user->id, $year->id, (object) ['name' => $year->name, 'startdate' => $year->startdate,
            'enddate' => $year->enddate, 'holidays' => "2026-09-21 Festa\n"]);

        try {
            plan_service::approve($user->id, $id, $seen, true);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_changedelsewhere', $e->errorcode);
        }
        $this->assertSame('draft', plan_service::get($user->id, $id)->status);
    }

    /**
     * A plan whose text was never imported cannot be approved.
     *
     * @group REQ-IMP-004
     */
    public function test_a_plan_never_imported_cannot_be_approved(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();

        $this->expectExceptionObject(new \moodle_exception('error_notimported', 'local_classprogress'));
        plan_service::approve($user->id, $id, '', true);
    }

    /**
     * Approval is final: approving again is refused, and no call changes the status back.
     *
     * @group REQ-LCK-002
     */
    public function test_approval_is_final(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);
        self::approve($user->id, $id);
        $approved = plan_service::get($user->id, $id);

        try {
            plan_service::approve($user->id, $id, '', true);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_planlocked', $e->errorcode);
        }

        $this->assertEquals($approved, plan_service::get($user->id, $id));
    }

    /**
     * Every change to an approved plan is refused, whatever the path, and nothing is written: the shell, its
     * timetable, its text and its items stay as approved. A tampered form (the plan form posted for the approved plan,
     * with status fields added) ends in the same refusal.
     *
     * @dataProvider locked_cases
     * @group REQ-LCK-001
     * @group REQ-IMP-003
     * @param string $operation What is tried.
     */
    public function test_an_approved_plan_refuses_every_change(string $operation): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);
        self::approve($user->id, $id);
        $plan = plan_service::get($user->id, $id);
        $before = self::rows($id);
        $shell = (object) ['schoolyearid' => $plan->schoolyearid, 'modulecode' => 'X', 'moduletitle' => 'Y'];

        try {
            match ($operation) {
                'update the shell' => plan_service::update($user->id, $id, $shell),
                'update the timetable' => plan_service::update($user->id, $id,
                    (object) (['timetable' => [0 => 3], 'moduletitle' => $plan->moduletitle] + (array) $shell)),
                'tampered form' => plan_service::update($user->id, $id, (object) ((array) $shell + ['status' => 'draft',
                    'timeapproved' => 0, 'timemodified' => $plan->timemodified, 'timetable' => [0 => 1]])),
                'delete' => plan_service::delete($user->id, $id),
                'import' => plan_service::import($user->id, $id, self::PLAN),
                'check' => plan_service::check($user->id, $id, self::PLAN),
            };
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_planlocked', $e->errorcode);
        }

        $this->assertEquals($before, self::rows($id));
    }

    /**
     * Ways to change a plan.
     *
     * @return array Case => [operation].
     */
    public static function locked_cases(): array {
        $cases = [];
        foreach (['update the shell', 'update the timetable', 'tampered form', 'delete', 'import', 'check'] as $operation) {
            $cases[$operation] = [$operation];
        }
        return $cases;
    }

    /**
     * After approval, new holidays on its days, a refused timetable change and a refused shorter school year leave the
     * dates and hours of every item as they were.
     *
     * @group REQ-LCK-004
     * @group REQ-CAL-004
     */
    public function test_the_dates_and_hours_of_an_approved_plan_do_not_move(): void {
        global $DB;

        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);
        self::approve($user->id, $id);
        $items = $DB->get_records('local_classprogress_planitem', ['planid' => $id], 'id');
        $plan = plan_service::get($user->id, $id);
        $year = schoolyear_service::get($user->id, $plan->schoolyearid);

        schoolyear_service::update($user->id, $year->id, (object) ['name' => $year->name, 'startdate' => $year->startdate,
            'enddate' => $year->enddate, 'holidays' => "2026-09-14 to 2026-09-21 Tancat\n"]);
        $changes = [
            fn() => plan_service::update($user->id, $id, (object) ['schoolyearid' => $plan->schoolyearid,
                'moduletitle' => $plan->moduletitle, 'timetable' => [4 => 1]]),
            fn() => schoolyear_service::update($user->id, $year->id, (object) ['name' => $year->name,
                'startdate' => '2026-09-15', 'enddate' => $year->enddate]),
        ];
        foreach ($changes as $change) {
            try {
                $change();
                $this->fail('Expected an exception');
            } catch (\moodle_exception $e) {
                $this->assertContains($e->errorcode, ['error_planlocked', 'error_yearexcludesplans']);
            }
        }

        $this->assertEquals($items, $DB->get_records('local_classprogress_planitem', ['planid' => $id], 'id'));
        $this->assertSame([0 => 2.0, 4 => 2.0], plan_service::timetable($user->id, $id));
    }

    /**
     * A timetable change of a draft gives its items the new hours where the text gives none.
     *
     * @group REQ-PLN-006
     * @group REQ-IMP-003
     */
    public function test_a_timetable_change_of_a_draft_updates_the_hours_of_its_items(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);
        $plan = plan_service::get($user->id, $id);

        plan_service::update($user->id, $id, (object) ['schoolyearid' => $plan->schoolyearid,
            'modulecode' => $plan->modulecode, 'moduletitle' => $plan->moduletitle, 'timetable' => [0 => 2, 4 => 3]]);

        $hours = array_map(fn(\stdClass $i): float => (float) $i->hours, array_values(plan_service::items($user->id, $id)));
        $this->assertSame([1.5, 3.0, 2.0], $hours);
    }

    /**
     * A draft can still be deleted, with its items and import log.
     *
     * @group REQ-IMP-003
     */
    public function test_a_draft_with_content_can_be_deleted(): void {
        global $DB;

        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, self::PLAN);

        plan_service::delete($user->id, $id);

        $this->assertSame(0, $DB->count_records('local_classprogress_planitem', ['planid' => $id]));
        $this->assertSame(0, $DB->count_records('local_classprogress_import', ['planid' => $id]));
    }
}
