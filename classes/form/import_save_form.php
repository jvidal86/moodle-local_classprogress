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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Second step of an import: carries the previewed text, unchanged, to be saved as the draft, or back to be changed.
 *
 * The page shows it only after a preview without errors. The text is read again when it is saved, so a tampered field
 * can only change what is checked, never skip the check.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_save_form extends \moodleform {
    /**
     * Defines the fields.
     */
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'sourcetext');
        $mform->setType('sourcetext', PARAM_RAW);
        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_ALPHA);
        $mform->addElement('hidden', 'filename');
        $mform->setType('filename', PARAM_FILE);

        $mform->addGroup([
            $mform->createElement('submit', 'savedraft', get_string('savedraft', 'local_classprogress')),
            $mform->createElement('cancel', 'changetext', get_string('changetext', 'local_classprogress')),
        ], 'buttonar', '', ' ', false);
    }
}
