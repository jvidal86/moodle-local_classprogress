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

/**
 * Create, edit or delete a draft plan shell and its weekly timetable, and see its calendar.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_classprogress\form\plan_form;
use local_classprogress\local\plan_service;
use local_classprogress\local\schoolyear_service;
use local_classprogress\output\calendar_view;
use local_classprogress\output\index_page;

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
\local_classprogress\local\access::require_user();

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$schoolyearid = optional_param('schoolyearid', 0, PARAM_INT);

$context = \context_user::instance($USER->id);
$indexurl = new \core\url('/local/classprogress/index.php');
$url = new \core\url('/local/classprogress/plan.php', $id ? ['id' => $id] : []);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_primary_active_tab('local_classprogress');
$PAGE->set_heading(get_string('pluginname', 'local_classprogress'));
$PAGE->navbar->add(get_string('pluginname', 'local_classprogress'), $indexurl);

$plan = $id ? plan_service::get($USER->id, $id) : null;
$name = $plan ? s(index_page::plan_name($plan)) : '';

if ($plan && $action === 'delete') {
    $title = get_string('deleteplan', 'local_classprogress');
    if (\local_classprogress\local\access::delete_confirmed()) {
        plan_service::delete($USER->id, $id);
        redirect($indexurl, get_string('plandeleted', 'local_classprogress'), null, notification::NOTIFY_SUCCESS);
    }
    $PAGE->set_title($title);
    $PAGE->navbar->add($title);
    $confirmurl = new \core\url($url, ['action' => 'delete', 'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    echo $OUTPUT->confirm(get_string('deleteplanconfirm', 'local_classprogress', $name), $confirmurl, $indexurl);
    echo $OUTPUT->footer();
    exit;
}

$title = $plan ? $name : get_string('addplan', 'local_classprogress');
$PAGE->set_title($title);
$PAGE->navbar->add($title);

$years = [];
foreach (schoolyear_service::list($USER->id) as $year) {
    $years[$year->id] = s($year->name);
}
if (!$years) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    echo $OUTPUT->notification(
        get_string('noschoolyearforplan', 'local_classprogress'),
        notification::NOTIFY_INFO,
        false
    );
    echo $OUTPUT->single_button(
        new \core\url('/local/classprogress/schoolyear.php'),
        get_string('addschoolyear', 'local_classprogress'),
        'get'
    );
    echo $OUTPUT->footer();
    exit;
}
$courses = plan_form::course_options((int) $USER->id, $plan);

$form = new plan_form($url, ['userid' => (int) $USER->id, 'years' => $years, 'courses' => $courses]);
$stale = false;
if ($form->is_cancelled()) {
    redirect($plan ? $url : $indexurl);
} else if ($data = $form->get_data()) {
    $plandata = plan_form::to_service_data($data);
    try {
        if ($plan) {
            plan_service::update($USER->id, $id, $plandata);
        } else {
            $id = plan_service::create($USER->id, $plandata);
        }
    } catch (\moodle_exception $e) {
        if ($e->errorcode !== 'error_changedelsewhere') {
            throw $e;
        }
        // Nothing was saved; the form keeps what the teacher typed, and the message asks them to reload.
        $stale = true;
    }
    if (!$stale) {
        redirect(
            new \core\url('/local/classprogress/plan.php', ['id' => $id]),
            get_string('plansaved', 'local_classprogress'),
            null,
            notification::NOTIFY_SUCCESS
        );
    }
}
if ($plan && !$stale) {
    $form->set_data(plan_form::from_record($plan, plan_service::timetable($USER->id, $id)));
} else if (!$plan && isset($years[$schoolyearid])) {
    $year = schoolyear_service::get($USER->id, $schoolyearid);
    $form->set_data(['schoolyearid' => $schoolyearid, 'startdate' => $year->startdate, 'enddate' => $year->enddate]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
if ($stale) {
    echo $OUTPUT->notification(get_string('error_changedelsewhere', 'local_classprogress'), notification::NOTIFY_ERROR);
}
if ($plan) {
    echo $OUTPUT->heading(get_string('calendar', 'local_classprogress'), 3);
    echo $OUTPUT->render(calendar_view::for_plan($USER->id, $plan));
    echo $OUTPUT->heading(get_string('editplan', 'local_classprogress'), 3);
}
$form->display();
echo $OUTPUT->footer();
