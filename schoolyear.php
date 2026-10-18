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
 * Create, edit or delete a school year: its dates, holidays and evaluation periods.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_classprogress\form\schoolyear_form;
use local_classprogress\local\schoolyear_service;

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
\local_classprogress\local\access::require_user();

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$context = \context_user::instance($USER->id);
$indexurl = new \core\url('/local/classprogress/index.php');
$url = new \core\url('/local/classprogress/schoolyear.php', $id ? ['id' => $id] : []);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_primary_active_tab('local_classprogress');
$PAGE->set_heading(get_string('pluginname', 'local_classprogress'));
$PAGE->navbar->add(get_string('pluginname', 'local_classprogress'), $indexurl);

$year = $id ? schoolyear_service::get($USER->id, $id) : null;
$name = $year ? s($year->name) : '';

if ($year && $action === 'delete') {
    $title = get_string('deleteschoolyear', 'local_classprogress');
    if (\local_classprogress\local\access::delete_confirmed()) {
        schoolyear_service::delete($USER->id, $id);
        redirect($indexurl, get_string('schoolyeardeleted', 'local_classprogress'), null, notification::NOTIFY_SUCCESS);
    }
    $PAGE->set_title($title);
    $PAGE->navbar->add($title);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    if (schoolyear_service::count_plans($USER->id, $id) > 0) {
        echo $OUTPUT->notification(
            get_string('deleteschoolyearhasplans', 'local_classprogress', $name),
            notification::NOTIFY_WARNING,
            false
        );
        echo $OUTPUT->continue_button($indexurl);
    } else {
        $confirmurl = new \core\url($url, ['action' => 'delete', 'confirm' => 1, 'sesskey' => sesskey()]);
        echo $OUTPUT->confirm(get_string('deleteschoolyearconfirm', 'local_classprogress', $name), $confirmurl, $indexurl);
    }
    echo $OUTPUT->footer();
    exit;
}

$title = get_string($year ? 'editschoolyear' : 'addschoolyear', 'local_classprogress');
$PAGE->set_title($title);
$PAGE->navbar->add($title);

$evaluations = $year ? schoolyear_service::evaluations($USER->id, $id) : [];
$form = new schoolyear_form($url, ['evaluationcount' => count($evaluations)]);
$stale = false;
if ($form->is_cancelled()) {
    redirect($indexurl);
} else if ($data = $form->get_data()) {
    $yeardata = schoolyear_form::to_service_data($data);
    try {
        if ($year) {
            schoolyear_service::update($USER->id, $id, $yeardata);
        } else {
            schoolyear_service::create($USER->id, $yeardata);
        }
    } catch (\moodle_exception $e) {
        if ($e->errorcode !== 'error_changedelsewhere') {
            throw $e;
        }
        // Nothing was saved; the form keeps what the teacher typed, and the message asks them to reload.
        $stale = true;
    }
    if (!$stale) {
        redirect($indexurl, get_string('schoolyearsaved', 'local_classprogress'), null, notification::NOTIFY_SUCCESS);
    }
}
if ($year && !$stale) {
    $form->set_data(schoolyear_form::from_record($year, schoolyear_service::holidays($USER->id, $id), $evaluations));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($year ? $name : $title);
if ($stale) {
    echo $OUTPUT->notification(get_string('error_changedelsewhere', 'local_classprogress'), notification::NOTIFY_ERROR);
}
$form->display();
echo $OUTPUT->footer();
