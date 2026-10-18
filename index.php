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
 * Landing page: the teacher's school years and plans, with the number of sessions of each plan.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
\local_classprogress\local\access::require_user();

$context = \context_user::instance($USER->id);
$PAGE->set_url(new \core\url('/local/classprogress/index.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_classprogress'));
$PAGE->set_heading(get_string('pluginname', 'local_classprogress'));

$page = \local_classprogress\output\index_page::for_user((int) $USER->id, $context);

echo $OUTPUT->header();
echo $OUTPUT->render($page);
echo $OUTPUT->footer();
