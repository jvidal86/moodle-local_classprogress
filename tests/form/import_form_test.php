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

namespace local_classprogress\form;

use local_classprogress\local\plan_service;
use local_classprogress\local\schoolyear_service;

/**
 * Tests for the import and approval forms: what a posted form gives the page.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\form\import_form
 * @covers     \local_classprogress\form\import_save_form
 * @covers     \local_classprogress\form\approve_form
 */
final class import_form_test extends \advanced_testcase {
    /**
     * Posts the import form.
     *
     * @param array $post Posted fields.
     * @return import_form The submitted form.
     */
    private static function submit(array $post): import_form {
        import_form::mock_submit($post);
        return new import_form(new \moodle_url('/local/classprogress/import.php', ['id' => 1]), ['maxbytes' => 1024]);
    }

    /**
     * Pasted text is given as typed, with <, > and &, as pasted text.
     *
     * @group REQ-IMP-001
     * @group REQ-DAT-004
     */
    public function test_pasted_text_is_given_as_typed(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $text = "# MP03 · Programació <C++> & co\n### 2026-09-14\n<b>x</b>\n";

        $form = self::submit(['sourcetext' => $text, 'planfile' => file_get_unused_draft_itemid()]);

        $this->assertNotNull($form->get_data());
        $this->assertSame([$text, 'paste', ''], $form->plan_text());
    }

    /**
     * An uploaded file is read, not stored, and wins over the text box; its bytes are given unchanged, so a file that
     * is not UTF-8 reaches the check that refuses it.
     *
     * @group REQ-IMP-001
     * @group REQ-PLN-015
     */
    public function test_an_uploaded_file_is_read_and_wins_over_the_text_box(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $draftid = file_get_unused_draft_itemid();
        $content = "# MP03 · Programaci\xf3\n### 2026-09-14\nx\n";
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => 'pla 0372.md',
        ], $content);

        $form = self::submit(['sourcetext' => 'ignored', 'planfile' => $draftid]);

        $this->assertNotNull($form->get_data());
        $this->assertSame([$content, 'upload', 'pla 0372.md'], $form->plan_text());
    }

    /**
     * The save step carries the previewed text back unchanged.
     *
     * @group REQ-IMP-002
     */
    public function test_the_save_step_carries_the_previewed_text(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $text = "# MP03 · <b>P</b> & co\n### 2026-09-14\nx\n";

        import_save_form::mock_submit(['sourcetext' => $text, 'source' => 'upload', 'filename' => 'pla.md']);
        $form = new import_save_form(new \moodle_url('/local/classprogress/import.php', ['id' => 1]));
        $data = $form->get_data();

        $this->assertSame([$text, 'upload', 'pla.md'], [$data->sourcetext, $data->source, $data->filename]);
    }

    /**
     * Approval with warnings needs "I have read the warnings"; without warnings it needs nothing more.
     *
     * @group REQ-IMP-004
     */
    public function test_approval_with_warnings_needs_them_confirmed(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $url = new \moodle_url('/local/classprogress/plan.php', ['id' => 1, 'action' => 'approve']);
        $seen = sha1('seen');

        approve_form::mock_submit(['id' => 1, 'seen' => $seen]);
        $this->assertNull((new approve_form($url, ['warnings' => 2]))->get_data());

        approve_form::mock_submit(['id' => 1, 'seen' => $seen, 'warningsread' => 1]);
        $data = (new approve_form($url, ['warnings' => 2]))->get_data();
        $this->assertSame([$seen, 1], [$data->seen, (int) $data->warningsread]);

        approve_form::mock_submit(['id' => 1, 'seen' => $seen]);
        $this->assertNotNull((new approve_form($url, ['warnings' => 0]))->get_data());
    }

    /**
     * An approval posted without the fingerprint of what the teacher saw gives an empty one, never that of the text as
     * stored now, so the service refuses it as changed elsewhere and the plan stays a draft (R2-1).
     *
     * @group REQ-IMP-004
     * @group REQ-DAT-003
     */
    public function test_an_approval_posted_without_the_fingerprint_is_refused(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_and_enrol($this->getDataGenerator()->create_course(), 'editingteacher');
        $this->setUser($user);
        $yearid = schoolyear_service::create($user->id, (object) ['name' => 'Curs', 'startdate' => '2026-09-14',
            'enddate' => '2027-06-21']);
        $id = plan_service::create($user->id, (object) ['schoolyearid' => $yearid, 'moduletitle' => 'P',
            'timetable' => [0 => 2]]);
        plan_service::import($user->id, $id, "# MP03 · P\n## RA1\n### 2026-09-14\nx\n");
        $url = new \moodle_url('/local/classprogress/plan.php', ['id' => $id, 'action' => 'approve']);

        $current = plan_service::fingerprint(plan_service::check_stored($user->id, $id));

        // A hand-made post: id and action, no "seen". Custom data as the page once passed it must not fill the field.
        approve_form::mock_submit(['id' => $id, 'action' => 'approve']);
        $data = (new approve_form($url, ['warnings' => 0, 'seen' => $current]))->get_data();

        $this->assertSame('', $data->seen);
        try {
            plan_service::approve($user->id, $id, $data->seen, false);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_changedelsewhere', $e->errorcode);
        }
        $this->assertSame(plan_service::STATUS_DRAFT, plan_service::get($user->id, $id)->status);
    }
}
