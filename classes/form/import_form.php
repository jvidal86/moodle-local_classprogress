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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to give the text of a plan: pasted into a text box, or read from an uploaded Markdown or text file.
 *
 * The text is taken as typed (PARAM_RAW), so Markdown keeps every "<" and "&". An uploaded file is read from the
 * user's draft area and not stored by the plugin; it wins over the text box. Nothing is saved from this form: the page
 * shows the preview and asks again before saving.
 *
 * Custom data: maxbytes, the largest file (plan_service::max_size()).
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /**
     * Defines the fields.
     */
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'textarea',
            'sourcetext',
            get_string('plantext', 'local_classprogress'),
            ['rows' => 20, 'cols' => 80, 'class' => 'text-monospace font-monospace', 'spellcheck' => 'false']
        );
        $mform->setType('sourcetext', PARAM_RAW);

        $mform->addElement('filepicker', 'planfile', get_string('planfile', 'local_classprogress'), null, [
            'maxbytes' => $this->_customdata['maxbytes'] ?? plan_service::max_size(),
            'accepted_types' => ['.md', '.txt'],
        ]);
        $mform->addHelpButton('planfile', 'planfile', 'local_classprogress');

        $mform->addElement('submit', 'preview', get_string('previewplan', 'local_classprogress'));
    }

    /**
     * The plan text of a submitted form, with where it came from.
     *
     * @return array [text, plan_service::SOURCE_UPLOAD or SOURCE_PASTE, file name (empty for pasted text)].
     */
    public function plan_text(): array {
        $content = $this->get_file_content('planfile');
        if ($content !== false) {
            $filename = clean_param((string) $this->get_new_filename('planfile'), PARAM_FILE);
            return [$content, plan_service::SOURCE_UPLOAD, $filename];
        }
        return [(string) ($this->get_data()->sourcetext ?? ''), plan_service::SOURCE_PASTE, ''];
    }
}
