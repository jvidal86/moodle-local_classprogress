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
 * Form to create or edit a draft plan shell: school year, optional course, module, class days and weekly timetable.
 *
 * Validation is the service's, so the form refuses exactly what the service refuses.
 *
 * Custom data: userid (the acting user), years (school year id => name), courses (course id => name).
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_form extends \moodleform {
    /** @var string[] Weekday lang strings in the calendar component, Monday = 0 to Sunday = 6. */
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Defines the fields.
     */
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $date = ['size' => 10, 'maxlength' => 10, 'placeholder' => get_string('dateformat', 'local_classprogress')];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement(
            'select',
            'schoolyearid',
            get_string('schoolyear', 'local_classprogress'),
            $this->_customdata['years']
        );
        $mform->setType('schoolyearid', PARAM_INT);
        $mform->addRule('schoolyearid', null, 'required', null, 'client');

        $courses = [0 => get_string('nocourse', 'local_classprogress')] + $this->_customdata['courses'];
        $mform->addElement('select', 'courseid', get_string('course'), $courses);
        $mform->setType('courseid', PARAM_INT);
        $mform->addHelpButton('courseid', 'course', 'local_classprogress');

        $mform->addElement('text', 'modulecode', get_string('modulecode', 'local_classprogress'), ['size' => 12]);
        $mform->setType('modulecode', PARAM_TEXT);
        $mform->addRule(
            'modulecode',
            get_string('maximumchars', '', plan_service::MAX_CODE),
            'maxlength',
            plan_service::MAX_CODE,
            'client'
        );

        $mform->addElement('text', 'moduletitle', get_string('moduletitle', 'local_classprogress'), ['size' => 50]);
        $mform->setType('moduletitle', PARAM_TEXT);
        $mform->addRule('moduletitle', null, 'required', null, 'client');
        $mform->addRule(
            'moduletitle',
            get_string('maximumchars', '', plan_service::MAX_TITLE),
            'maxlength',
            plan_service::MAX_TITLE,
            'client'
        );

        $mform->addElement('text', 'startdate', get_string('firstclassday', 'local_classprogress'), $date);
        $mform->setType('startdate', PARAM_TEXT);
        $mform->addHelpButton('startdate', 'firstclassday', 'local_classprogress');

        $mform->addElement('text', 'enddate', get_string('lastclassday', 'local_classprogress'), $date);
        $mform->setType('enddate', PARAM_TEXT);

        $mform->addElement('header', 'timetableheader', get_string('timetable', 'local_classprogress'));
        $mform->setExpanded('timetableheader');
        $mform->addElement('static', 'timetableintro', '', get_string('timetableintro', 'local_classprogress'));
        foreach (self::WEEKDAYS as $weekday => $name) {
            $mform->addElement('float', 'hours' . $weekday, get_string($name, 'calendar'), ['size' => 4]);
            // The float element reads the number in the user's language (1,5 or 1.5) and gives a float.
            $mform->setType('hours' . $weekday, PARAM_RAW_TRIMMED);
            $mform->setDefault('hours' . $weekday, 0);
        }

        $this->add_action_buttons();
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
        foreach (plan_service::validate($this->_customdata['userid'], self::to_service_data((object) $data)) as $field => $code) {
            if (str_starts_with($field, 'timetable-')) {
                $field = 'hours' . substr($field, strlen('timetable-'));
            } else if ($field === 'timetable') {
                $field = 'hours0';
            }
            $errors[$field] ??= get_string($code, 'local_classprogress');
        }
        return $errors;
    }

    /**
     * Turns submitted form data into plan data for the service.
     *
     * @param \stdClass $formdata Data from get_data() or validation().
     * @return \stdClass Plan data.
     */
    public static function to_service_data(\stdClass $formdata): \stdClass {
        $timetable = [];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $timetable[$weekday] = (float) ($formdata->{'hours' . $weekday} ?? 0);
        }
        return (object) [
            'schoolyearid' => (int) ($formdata->schoolyearid ?? 0),
            'courseid' => (int) ($formdata->courseid ?? 0),
            'modulecode' => $formdata->modulecode ?? '',
            'moduletitle' => $formdata->moduletitle ?? '',
            'startdate' => trim($formdata->startdate ?? ''),
            'enddate' => trim($formdata->enddate ?? ''),
            'timetable' => $timetable,
        ];
    }

    /**
     * Turns a stored plan into form data.
     *
     * @param \stdClass $plan Plan row.
     * @param float[] $timetable Its timetable, weekday => hours.
     * @return \stdClass Data for set_data().
     */
    public static function from_record(\stdClass $plan, array $timetable): \stdClass {
        $data = (object) [
            'id' => $plan->id,
            'schoolyearid' => $plan->schoolyearid,
            'courseid' => $plan->courseid ?? 0,
            'modulecode' => $plan->modulecode,
            'moduletitle' => $plan->moduletitle,
            'startdate' => $plan->startdate ?? '',
            'enddate' => $plan->enddate ?? '',
        ];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $data->{'hours' . $weekday} = $timetable[$weekday] ?? 0;
        }
        return $data;
    }
}
