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

use local_classprogress\local\holiday_error;
use local_classprogress\local\schoolyear_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create or edit a school year: name, dates, holiday text and evaluation periods.
 *
 * Validation is the service's, so the form refuses exactly what the service refuses. Holiday text with a line that
 * cannot be read is shown again with every bad line, its number and the reason, and nothing is saved. Text is taken as
 * typed (PARAM_RAW), so a "<" in a name or a holiday line is kept; it is escaped wherever it is shown. The hidden
 * timemodified is the version the form was opened on, which the service checks.
 *
 * Custom data: evaluationcount, the number of stored evaluation periods (rows shown: at least 3, at most
 * schoolyear_service::MAX_EVALUATIONS, whatever the request asks for); userid, the acting user, so that new dates of a
 * stored school year are checked against its plans.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schoolyear_form extends \moodleform {
    /** @var int Evaluation period rows shown for a school year that has fewer. */
    private const MIN_EVALUATION_ROWS = 3;

    /** @var int Evaluation period rows the form builds, read from the request by repeat_elements(). */
    private int $evaluationrows = 0;

    /**
     * Defines the fields.
     */
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $date = ['size' => 10, 'maxlength' => 10, 'placeholder' => get_string('dateformat', 'local_classprogress')];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'timemodified');
        $mform->setType('timemodified', PARAM_INT);

        $mform->addElement('text', 'name', get_string('schoolyearname', 'local_classprogress'), ['size' => 40]);
        $mform->setType('name', PARAM_RAW_TRIMMED);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule(
            'name',
            get_string('maximumchars', '', schoolyear_service::MAX_NAME),
            'maxlength',
            schoolyear_service::MAX_NAME,
            'client'
        );

        $mform->addElement('text', 'startdate', get_string('startdate', 'local_classprogress'), $date);
        $mform->setType('startdate', PARAM_TEXT);
        $mform->addRule('startdate', null, 'required', null, 'client');

        $mform->addElement('text', 'enddate', get_string('enddate', 'local_classprogress'), $date);
        $mform->setType('enddate', PARAM_TEXT);
        $mform->addRule('enddate', null, 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'holidays',
            get_string('holidays', 'local_classprogress'),
            ['rows' => 12, 'cols' => 70, 'class' => 'text-monospace font-monospace', 'spellcheck' => 'false']
        );
        $mform->setType('holidays', PARAM_RAW);
        $mform->addHelpButton('holidays', 'holidays', 'local_classprogress');

        $mform->addElement('header', 'evaluationsheader', get_string('evaluations', 'local_classprogress'));
        $mform->addElement('static', 'evaluationsintro', '', get_string('evaluationsintro', 'local_classprogress'));
        $row = [
            $mform->createElement('text', 'evalname', get_string('evaluationname', 'local_classprogress'), ['size' => 20]),
            $mform->createElement('text', 'evalstartdate', get_string('startdate', 'local_classprogress'), $date),
            $mform->createElement('text', 'evalenddate', get_string('enddate', 'local_classprogress'), $date),
        ];
        $group = $mform->createElement('group', 'evaluation', get_string('evaluation', 'local_classprogress'), $row, ' ', false);
        $rows = max(self::MIN_EVALUATION_ROWS, (int) ($this->_customdata['evaluationcount'] ?? 0));
        $this->repeat_elements([$group], $rows, [
            'evalname' => ['type' => PARAM_RAW_TRIMMED],
            'evalstartdate' => ['type' => PARAM_TEXT],
            'evalenddate' => ['type' => PARAM_TEXT],
        ], 'evaluationrows', 'addevaluation', 1, get_string('addevaluation', 'local_classprogress'), true);

        $this->add_action_buttons();
    }

    /**
     * Reads a request parameter, keeping the evaluation rows within the most periods a school year can have.
     *
     * repeat_elements() reads the number of rows (evaluationrows) and the "add" button (addevaluation) here, so a
     * crafted request cannot make the form build hundreds of rows.
     *
     * @param string $name Parameter name.
     * @param mixed $default Value when the request does not have it.
     * @param string $type PARAM_* type.
     * @return mixed The value.
     */
    #[\Override]
    public function optional_param($name, $default, $type) {
        $value = parent::optional_param($name, $default, $type);
        if ($name === 'evaluationrows') {
            $this->evaluationrows = min((int) $value, schoolyear_service::MAX_EVALUATIONS);
            return $this->evaluationrows;
        }
        if ($name === 'addevaluation' && $this->evaluationrows >= schoolyear_service::MAX_EVALUATIONS) {
            return '';
        }
        return $value;
    }

    /**
     * Checks the data with the service and shows its errors next to the fields.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Element name => error message.
     */
    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $yeardata = self::to_service_data((object) $data);
        $userid = (int) ($this->_customdata['userid'] ?? 0);
        foreach (schoolyear_service::validate($yeardata, $userid, (int) ($data['id'] ?? 0)) as $field => $code) {
            if ($field === 'holidays' && $code === 'error_holidaylines') {
                $errors['holidays'] = self::holiday_errors(schoolyear_service::check_holidays($yeardata->holidays)->errors);
            } else if (str_starts_with($field, 'evaluations-')) {
                $errors['evaluation[' . substr($field, strlen('evaluations-')) . ']'] = get_string($code, 'local_classprogress');
            } else if ($field === 'evaluations') {
                $errors['evaluation[0]'] = get_string($code, 'local_classprogress');
            } else {
                $errors[$field] = get_string($code, 'local_classprogress');
            }
        }
        return $errors;
    }

    /**
     * Turns submitted form data into school year data for the service; empty evaluation rows are left out.
     *
     * @param \stdClass $formdata Data from get_data() or validation().
     * @return \stdClass School year data, with evaluations keyed by form row and the version the form was opened on.
     */
    public static function to_service_data(\stdClass $formdata): \stdClass {
        $evaluations = [];
        foreach ($formdata->evalname ?? [] as $row => $name) {
            $start = trim($formdata->evalstartdate[$row] ?? '');
            $end = trim($formdata->evalenddate[$row] ?? '');
            if (trim($name) !== '' || $start !== '' || $end !== '') {
                $evaluations[$row] = (object) ['name' => $name, 'startdate' => $start, 'enddate' => $end];
            }
        }
        return (object) [
            'name' => $formdata->name ?? '',
            'startdate' => trim($formdata->startdate ?? ''),
            'enddate' => trim($formdata->enddate ?? ''),
            'holidays' => $formdata->holidays ?? '',
            'evaluations' => $evaluations,
            'timemodified' => (int) ($formdata->timemodified ?? 0),
        ];
    }

    /**
     * Turns a stored school year into form data.
     *
     * @param \stdClass $year School year row.
     * @param \local_classprogress\local\holiday_entry[] $holidays Its holidays.
     * @param \stdClass[] $evaluations Its evaluation periods.
     * @return \stdClass Data for set_data().
     */
    public static function from_record(\stdClass $year, array $holidays, array $evaluations): \stdClass {
        $data = (object) [
            'id' => $year->id,
            'timemodified' => $year->timemodified,
            'name' => $year->name,
            'startdate' => $year->startdate,
            'enddate' => $year->enddate,
            'holidays' => schoolyear_service::holidays_to_text($holidays),
        ];
        foreach (array_values($evaluations) as $row => $evaluation) {
            $data->evalname[$row] = $evaluation->name;
            $data->evalstartdate[$row] = $evaluation->startdate;
            $data->evalenddate[$row] = $evaluation->enddate;
        }
        return $data;
    }

    /**
     * The error shown under the holiday text: every bad line with its number, its text and the reason.
     *
     * @param holiday_error[] $errors Bad lines.
     * @return string HTML.
     */
    private static function holiday_errors(array $errors): string {
        $items = [];
        foreach ($errors as $error) {
            $text = \core_text::strlen($error->text) > 80 ? \core_text::substr($error->text, 0, 80) . '…' : $error->text;
            $items[] = get_string('holidayline', 'local_classprogress', (object) [
                'line' => $error->line,
                'text' => s($text),
                'reason' => get_string('holidayerror_' . $error->code, 'local_classprogress'),
            ]);
        }
        return get_string('error_holidaylines', 'local_classprogress') . \html_writer::alist($items);
    }
}
