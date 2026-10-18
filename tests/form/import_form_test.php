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
        $this->assertNull((new approve_form($url, ['warnings' => 2, 'seen' => $seen]))->get_data());

        approve_form::mock_submit(['id' => 1, 'seen' => $seen, 'warningsread' => 1]);
        $data = (new approve_form($url, ['warnings' => 2, 'seen' => $seen]))->get_data();
        $this->assertSame([$seen, 1], [$data->seen, (int) $data->warningsread]);

        approve_form::mock_submit(['id' => 1, 'seen' => $seen]);
        $this->assertNotNull((new approve_form($url, ['warnings' => 0, 'seen' => $seen]))->get_data());
    }
}
