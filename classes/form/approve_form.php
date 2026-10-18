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
 * Confirmation of an approval: the fingerprint of what the teacher was shown and, when there are warnings, the
 * statement that they were read.
 *
 * The page sets seen, plan_service::fingerprint() of what it shows, with set_data(); a post without it gives an empty
 * fingerprint, which approval refuses.
 *
 * Custom data: warnings, the number of warnings shown.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class approve_form extends \moodleform {
    /**
     * Defines the fields.
     */
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'approve');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'seen', '');
        $mform->setType('seen', PARAM_ALPHANUM);

        if (!empty($this->_customdata['warnings'])) {
            $mform->addElement('checkbox', 'warningsread', '', get_string('readwarnings', 'local_classprogress'));
        }
        $this->add_action_buttons(true, get_string('approveplan', 'local_classprogress'));
    }

    /**
     * Refuses an approval with warnings that the teacher did not confirm.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Element name => error message.
     */
    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($this->_customdata['warnings']) && empty($data['warningsread'])) {
            $errors['warningsread'] = get_string('error_confirmwarnings', 'local_classprogress');
        }
        return $errors;
    }
}
