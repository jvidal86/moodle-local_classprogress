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
 * Import the text of a draft plan: paste or upload it, read the preview, then save it as the draft's content.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_classprogress\form\import_form;
use local_classprogress\form\import_save_form;
use local_classprogress\local\plan_issue;
use local_classprogress\local\plan_service;
use local_classprogress\output\index_page;
use local_classprogress\output\plan_preview;

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
\local_classprogress\local\access::require_user();

$id = required_param('id', PARAM_INT);

$context = \context_user::instance($USER->id);
$indexurl = new \core\url('/local/classprogress/index.php');
$planurl = new \core\url('/local/classprogress/plan.php', ['id' => $id]);
$url = new \core\url('/local/classprogress/import.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_primary_active_tab('local_classprogress');
$PAGE->set_heading(get_string('pluginname', 'local_classprogress'));

$plan = plan_service::get($USER->id, $id);
if ($plan->status !== plan_service::STATUS_DRAFT) {
    throw new \moodle_exception('error_planlocked', 'local_classprogress', $planurl);
}
$name = index_page::plan_name($plan);
$title = get_string('importplan', 'local_classprogress');
$PAGE->set_title($title);
$PAGE->navbar->add(get_string('pluginname', 'local_classprogress'), $indexurl);
$PAGE->navbar->add(s($name), $planurl);
$PAGE->navbar->add($title);

$inputform = new import_form($url, ['maxbytes' => plan_service::max_size()]);
$saveform = new import_save_form($url);
// The text to preview, with where it came from; null to show the empty form.
$text = null;
$source = plan_service::SOURCE_PASTE;
$filename = '';
$message = null;
if ($saveform->is_cancelled()) {
    // Back to the text box, with the previewed text in it.
    $inputform->set_data(['sourcetext' => $saveform->get_submitted_data()->sourcetext ?? '']);
} else if ($data = $saveform->get_data()) {
    try {
        plan_service::import($USER->id, $id, $data->sourcetext, $data->source, $data->filename);
        redirect($planurl, get_string('plandraftsaved', 'local_classprogress'), null, notification::NOTIFY_SUCCESS);
    } catch (\moodle_exception $e) {
        if ($e->errorcode !== 'error_planhaserrors' && $e->errorcode !== 'error_plantoolarge') {
            throw $e;
        }
        // Something changed since the preview (the limits): show the text again with what it gives now.
        $inputform->set_data(['sourcetext' => $data->sourcetext]);
        [$text, $source, $filename] = [$data->sourcetext, $data->source, $data->filename];
    }
} else if ($inputform->get_data()) {
    [$text, $source, $filename] = $inputform->plan_text();
}

$result = null;
if ($text !== null) {
    try {
        $result = plan_service::check($USER->id, $id, $text);
    } catch (\moodle_exception $e) {
        if ($e->errorcode !== 'error_plantoolarge') {
            throw $e;
        }
        $message = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title . ': ' . s($name));
if ($message !== null) {
    echo $OUTPUT->notification($message, notification::NOTIFY_ERROR);
}
if ($result !== null) {
    echo $OUTPUT->heading(get_string('preview', 'local_classprogress'), 3);
    echo $OUTPUT->render(new plan_preview($plan, $result));
    if (plan_service::count_issues($result, plan_issue::ERROR) === 0) {
        $saveform->set_data(['sourcetext' => $text, 'source' => $source, 'filename' => $filename]);
        $saveform->display();
        echo $OUTPUT->footer();
        exit;
    }
}
echo html_writer::tag('p', get_string('importplanintro', 'local_classprogress'));
$inputform->display();
echo $OUTPUT->heading(get_string('aiinstructionsheading', 'local_classprogress'), 3);
echo html_writer::tag('p', get_string('aiinstructionsintro', 'local_classprogress'));
echo html_writer::tag('pre', s(get_string('aiinstructions', 'local_classprogress')), [
    'class' => 'border p-3 bg-light',
    'data-region' => 'ai-instructions',
    'style' => 'white-space: pre-wrap',
]);
echo $OUTPUT->footer();
