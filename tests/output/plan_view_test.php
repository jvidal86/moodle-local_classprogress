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

namespace local_classprogress\output;

use local_classprogress\local\plan_service;
use local_classprogress\local\schoolyear_service;

/**
 * Tests for the view of a plan's days and of the import preview.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\output\plan_view
 * @covers     \local_classprogress\output\plan_preview
 */
final class plan_view_test extends \advanced_testcase {
    /**
     * A draft plan of a new teacher with a Monday and Friday timetable, in a school year with one holiday.
     *
     * @return array [user, plan id].
     */
    private function draft(): array {
        $user = $this->getDataGenerator()->create_and_enrol($this->getDataGenerator()->create_course(), 'editingteacher');
        $yearid = schoolyear_service::create($user->id, (object) [
            'name' => 'Curs 2026-2027',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
            'holidays' => "2026-10-12 Festa <Nacional>\n",
        ]);
        $id = plan_service::create($user->id, (object) [
            'schoolyearid' => $yearid,
            'modulecode' => 'MP 0000',
            'moduletitle' => 'Typed <title>',
            'timetable' => [0 => 2, 4 => 2],
        ]);
        return [$user, $id];
    }

    /**
     * Renders a renderable with the core renderer.
     *
     * @param \renderable $renderable What to render.
     * @return string HTML.
     */
    private static function render(\renderable $renderable): string {
        global $PAGE;

        return $PAGE->get_renderer('core')->render($renderable);
    }

    /**
     * The plan text is shown as Markdown cleaned by Moodle: scripts, event handlers and javascript: links from the plan
     * do nothing, and headings are shown as typed, escaped.
     *
     * @group REQ-LCK-003
     * @group REQ-DAT-004
     */
    public function test_plan_text_is_shown_as_cleaned_markdown(): void {
        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, "# MP 0372 · Gestió\n## RA1 <C++> & co\n### 2026-09-14 [2h] <i>Intro</i>\n"
            . "**Prova** <script>alert(1)</script>\n<img src=\"x\" onerror=\"alert(2)\">\n[link](javascript:alert(3))\n");

        $html = self::render(plan_view::for_plan($user->id, plan_service::get($user->id, $id)));

        $this->assertStringContainsString('<strong>Prova</strong>', $html);
        $this->assertStringContainsString('RA1 &lt;C++&gt; &amp; co', $html);
        $this->assertStringContainsString('&lt;i&gt;Intro&lt;/i&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<i>Intro', $html);
    }

    /**
     * The days are listed in the order of the text, grouped by their RA or block, with their dates and hours; the
     * status says whether the plan is a draft or approved, and when.
     *
     * @group REQ-IMP-003
     * @group REQ-LCK-002
     */
    public function test_days_are_grouped_by_block_with_dates_hours_and_status(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        plan_service::import($user->id, $id, "### 2026-09-15\nzero\n## RA1\n### 2026-09-14 [1,5h]\na\n### 2026-09-18\nb\n"
            . "## RA2\n### 2026-09-21\nc\n");
        $output = $PAGE->get_renderer('core');

        $draft = plan_view::for_plan($user->id, plan_service::get($user->id, $id))->export_for_template($output);
        plan_service::approve($user->id, $id, plan_service::fingerprint(plan_service::check_stored($user->id, $id)), true);
        $plan = plan_service::get($user->id, $id);
        $approved = plan_view::for_plan($user->id, $plan)->export_for_template($output);

        $this->assertFalse($draft->approved);
        $this->assertSame(get_string('statusdraft', 'local_classprogress'), $draft->status);
        $this->assertTrue($approved->approved);
        $this->assertSame(get_string('statusapproved', 'local_classprogress', userdate($plan->timeapproved)), $approved->status);
        $this->assertSame([get_string('noblock', 'local_classprogress'), 'RA1', 'RA2'], array_column($approved->blocks, 'name'));
        $this->assertSame(['2026-09-14', '2026-09-18'], array_column($approved->blocks[1]['days'], 'date'));
        $this->assertSame(['1.5', '2'], array_column($approved->blocks[1]['days'], 'hours'));
        $this->assertSame('', $approved->blocks[0]['days'][0]['hours']);
        $this->assertTrue($approved->hasitems);
    }

    /**
     * The preview shows the module the import will store (and that it replaces the typed one), the blocks, the number
     * of days and hours, and every error and warning with its line and its message, escaped.
     *
     * @group REQ-IMP-002
     * @group REQ-PLN-005
     */
    public function test_preview_shows_module_blocks_days_hours_and_every_issue(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$user, $id] = $this->draft();
        $text = "# MP 0372 · Gestió\n## RA1\n### 2026-09-14 [1,5h]\na\n### 2026-10-12\nb\n## RA2\n### 2026-02-30 <b>x</b>\n"
            . "### 2026-10-16\nc\n";
        $plan = plan_service::get($user->id, $id);
        $preview = new plan_preview($plan, plan_service::check($user->id, $id, $text));

        $context = $preview->export_for_template($PAGE->get_renderer('core'));
        $html = self::render($preview);

        $this->assertSame('MP 0372 · Gestió', $context->module);
        $this->assertSame(get_string('previewreplaces', 'local_classprogress', 'MP 0000 · Typed <title>'), $context->replaces);
        $this->assertSame(
            [['name' => 'RA1', 'days' => 2, 'hours' => '3.5'], ['name' => 'RA2', 'days' => 1, 'hours' => '2']],
            $context->blocks
        );
        $this->assertSame(3, $context->days);
        $this->assertSame('5.5', $context->hours);
        $line = function (int $line, string $code, array $params): string {
            $message = get_string('issue_' . $code, 'local_classprogress', (object) $params);
            return get_string('issueline', 'local_classprogress', (object) ['line' => $line, 'message' => $message]);
        };
        $this->assertSame([$line(8, 'bad_date', ['text' => '2026-02-30 <b>x</b>'])], array_column($context->errors, 'text'));
        $holiday = $line(5, 'on_holiday', ['date' => '2026-10-12', 'holiday' => 'Festa <Nacional>']);
        $this->assertSame([$holiday], array_column($context->warnings, 'text'));
        $this->assertTrue($context->haserrors);
        $this->assertStringContainsString('2026-02-30 &lt;b&gt;x&lt;/b&gt;', $html);
        // On the approval page the text is already stored: approval keeps the plan's module as it now is.
        $stored = (new plan_preview($plan, plan_service::check($user->id, $id, $text), true))->export_for_template(
            $PAGE->get_renderer('core')
        );
        $this->assertSame(['MP 0000 · Typed <title>', ''], [$stored->module, $stored->replaces]);
        $this->assertStringContainsString('Festa &lt;Nacional&gt;', $html);
        $this->assertStringNotContainsString('<b>x', $html);
    }
}
