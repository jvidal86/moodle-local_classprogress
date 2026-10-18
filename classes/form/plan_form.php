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
 * Validation is the service's, so the form refuses exactly what the service refuses. The module code and title are
 * taken as typed (PARAM_RAW_TRIMMED), so a "<" is kept; they are escaped wherever they are shown. The hidden
 * timemodified is the version the form was opened on, which the service checks.
 *
 * Custom data: userid (the acting user), years (school year id => name, safe to print), courses (course id => name,
 * safe to print; see course_options()).
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

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'timemodified');
        $mform->setType('timemodified', PARAM_INT);

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
        $mform->setType('modulecode', PARAM_RAW_TRIMMED);
        $mform->addRule(
            'modulecode',
            get_string('maximumchars', '', plan_service::MAX_CODE),
            'maxlength',
            plan_service::MAX_CODE,
            'client'
        );

        $mform->addElement('text', 'moduletitle', get_string('moduletitle', 'local_classprogress'), ['size' => 50]);
        $mform->setType('moduletitle', PARAM_RAW_TRIMMED);
        $mform->addRule('moduletitle', null, 'required', null, 'client');
        $mform->addRule(
            'moduletitle',
            get_string('maximumchars', '', plan_service::MAX_TITLE),
            'maxlength',
            plan_service::MAX_TITLE,
            'client'
        );

        $optional = date_field::options(true);
        $mform->addElement('date_selector', 'startdate', get_string('firstclassday', 'local_classprogress'), $optional);
        $mform->addHelpButton('startdate', 'firstclassday', 'local_classprogress');
        $mform->addElement('date_selector', 'enddate', get_string('lastclassday', 'local_classprogress'), $optional);

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
        $plandata = self::to_service_data((object) $data);
        foreach (plan_service::validate($this->_customdata['userid'], $plandata, (int) ($data['id'] ?? 0)) as $field => $code) {
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
     * @param \stdClass $formdata Data from get_data() or validation(), with dates as date_selector timestamps.
     * @return \stdClass Plan data, with the version the form was opened on.
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
            'startdate' => date_field::to_day((int) ($formdata->startdate ?? 0)),
            'enddate' => date_field::to_day((int) ($formdata->enddate ?? 0)),
            'timetable' => $timetable,
            'timemodified' => (int) ($formdata->timemodified ?? 0),
        ];
    }

    /**
     * The courses a plan may be linked to, for the course menu: those where the user teaches, plus the plan's current
     * course when the user can no longer link it, so that saving the plan unchanged keeps its link.
     *
     * @param int $userid The acting user.
     * @param \stdClass|null $plan The plan being edited; null for a new one.
     * @return string[] Course id => name, safe to print.
     */
    public static function course_options(int $userid, ?\stdClass $plan): array {
        global $DB;

        $options = [];
        $name = fn(\stdClass $course): string =>
            format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);
        foreach (plan_service::linkable_courses($userid) as $course) {
            $options[$course->id] = $name($course);
        }
        $current = (int) ($plan->courseid ?? 0);
        if ($current !== 0 && !isset($options[$current])) {
            $course = $DB->get_record('course', ['id' => $current], 'id, fullname');
            $options[$current] = get_string('courselinkedbefore', 'local_classprogress', $course ? $name($course) : '#' . $current);
        }
        return $options;
    }

    /**
     * Turns a stored plan into form data.
     *
     * @param \stdClass $plan Plan row.
     * @param float[] $timetable Its timetable, weekday => hours.
     * @return \stdClass Data for set_data(), with dates as date_selector timestamps.
     */
    public static function from_record(\stdClass $plan, array $timetable): \stdClass {
        $data = (object) [
            'id' => $plan->id,
            'timemodified' => $plan->timemodified,
            'schoolyearid' => $plan->schoolyearid,
            'courseid' => $plan->courseid ?? 0,
            'modulecode' => $plan->modulecode,
            'moduletitle' => $plan->moduletitle,
            'startdate' => date_field::to_timestamp($plan->startdate ?? null),
            'enddate' => date_field::to_timestamp($plan->enddate ?? null),
        ];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $data->{'hours' . $weekday} = $timetable[$weekday] ?? 0;
        }
        return $data;
    }
}
