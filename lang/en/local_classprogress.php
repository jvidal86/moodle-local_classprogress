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
 * English strings for the class progress plugin.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addevaluation'] = 'Add an evaluation period';
$string['addschoolyear'] = 'New school year';
$string['cachedef_showlink'] = 'Whether the Class progress link is shown to the current user';
$string['classprogress:use'] = 'Use Class progress';
$string['daterange'] = '{$a->start} to {$a->end}';
$string['deleteschoolyear'] = 'Delete school year';
$string['deleteschoolyearconfirm'] = 'Delete the school year {$a} with its holidays and evaluation periods? Approved plan activities keep their own copy; draft plan activities that use it will ask for another school year. This cannot be undone.';
$string['editschoolyear'] = 'Edit school year';
$string['enddate'] = 'Last day';
$string['error_baddate'] = 'Enable the date and pick an existing day.';
$string['error_changedelsewhere'] = 'This was changed elsewhere; reload to see the latest version';
$string['error_daterange'] = 'Enter a date from 2000-01-01 to 2099-12-31.';
$string['error_endbeforestart'] = 'The last day is before the first day.';
$string['error_evaluationoutsideyear'] = 'The evaluation period must lie within the school year.';
$string['error_evaluationsoverlap'] = 'Evaluation periods cannot share a day.';
$string['error_holidaylines'] = 'Some holiday lines cannot be read. Correct or remove them; nothing has been saved.';
$string['error_holidaytexttoolong'] = 'The holiday text is too long: keep it under 32 KB.';
$string['error_name'] = 'Enter a name of up to 255 characters.';
$string['error_notfound'] = 'That item does not exist, or you do not have permission to see it.';
$string['error_toomanyevaluations'] = 'A school year can have up to 20 evaluation periods.';
$string['error_yeartoolong'] = 'A school year can be at most 400 days long.';
$string['evaluation'] = 'Evaluation period {no}';
$string['evaluationname'] = 'Name';
$string['evaluations'] = 'Evaluation periods';
$string['evaluationsintro'] = 'Optional. The evaluation periods (1a, 2a, 3a avaluació) group the summary of the export. Each one lies within the school year, and two periods cannot share a day. Leave a row empty, its dates not enabled, to remove it.';
$string['holidayerror_bad_end_date'] = 'The end date of the period cannot be read.';
$string['holidayerror_end_before_start'] = 'The period ends before it starts.';
$string['holidayerror_label_too_long'] = 'The label is longer than 255 characters.';
$string['holidayerror_no_such_date'] = 'That date does not exist.';
$string['holidayerror_not_a_date'] = 'The line does not start with a date written yyyy-mm-dd or dd/mm/yyyy.';
$string['holidayerror_out_of_range'] = 'The date is not between 2000-01-01 and 2099-12-31.';
$string['holidayerror_too_long'] = 'The period is longer than 400 days.';
$string['holidayline'] = 'Line {$a->line} ({$a->text}): {$a->reason}';
$string['holidays'] = 'Holidays and non-teaching days';
$string['holidays_help'] = 'One per line: a date or a period, then an optional label. Dates are written 2026-12-08 or 08/12/2026; a period is two dates joined by "to", "a", "-", "–", "—", ".." or "→", as in "2026-12-22 to 2027-01-07 Christmas". Blank lines and lines starting with # are ignored. The holidays apply to every plan activity of this school year until it is approved; an approved plan keeps its own copy.';
$string['noplanactivities'] = 'You cannot open any plan activity yet. To make a plan, add a Class progress activity to one of your courses and choose one of your school years for it.';
$string['noschoolyears'] = 'You have no school years yet. A school year holds the dates, holidays and evaluation periods shared by all your plan activities of that year.';
$string['pendingsessions'] = 'Pending sessions';
$string['planactivities'] = 'Plan activities';
$string['planactivity'] = 'Plan activity';
$string['pluginname'] = 'Class progress';
$string['privacy:metadata:local_classprogress_evaluation'] = 'An evaluation period of a school year, used to group the export summary.';
$string['privacy:metadata:local_classprogress_evaluation:enddate'] = 'The last day of the evaluation period.';
$string['privacy:metadata:local_classprogress_evaluation:name'] = 'The name of the evaluation period.';
$string['privacy:metadata:local_classprogress_evaluation:schoolyearid'] = 'The school year the evaluation period belongs to.';
$string['privacy:metadata:local_classprogress_evaluation:startdate'] = 'The first day of the evaluation period.';
$string['privacy:metadata:local_classprogress_evaluation:timecreated'] = 'When the evaluation period was created.';
$string['privacy:metadata:local_classprogress_evaluation:timemodified'] = 'When the evaluation period was last changed.';
$string['privacy:metadata:local_classprogress_holiday'] = 'A holiday (a single day or a period) of a school year, owned by the owner of the school year.';
$string['privacy:metadata:local_classprogress_holiday:enddate'] = 'The last day of the holiday.';
$string['privacy:metadata:local_classprogress_holiday:label'] = 'The name the teacher gave the holiday.';
$string['privacy:metadata:local_classprogress_holiday:schoolyearid'] = 'The school year the holiday belongs to.';
$string['privacy:metadata:local_classprogress_holiday:startdate'] = 'The first day of the holiday.';
$string['privacy:metadata:local_classprogress_holiday:timecreated'] = 'When the holiday was created.';
$string['privacy:metadata:local_classprogress_holiday:timemodified'] = 'When the holiday was last changed.';
$string['privacy:metadata:local_classprogress_schoolyear'] = 'A teacher\'s school year, whose dates hold the holidays and evaluation periods shared by all of that teacher\'s plan activities in the year.';
$string['privacy:metadata:local_classprogress_schoolyear:enddate'] = 'The last day of the school year.';
$string['privacy:metadata:local_classprogress_schoolyear:name'] = 'The name of the school year.';
$string['privacy:metadata:local_classprogress_schoolyear:startdate'] = 'The first day of the school year.';
$string['privacy:metadata:local_classprogress_schoolyear:timecreated'] = 'When the school year was created.';
$string['privacy:metadata:local_classprogress_schoolyear:timemodified'] = 'When the school year was last changed.';
$string['privacy:metadata:local_classprogress_schoolyear:userid'] = 'The teacher who owns the school year.';
$string['privacy:path:schoolyear'] = 'School year {$a}';
$string['schoolyearcounts'] = 'Holidays: {$a->holidays}. Evaluation periods: {$a->evaluations}.';
$string['schoolyeardeleted'] = 'School year deleted.';
$string['schoolyearname'] = 'Name';
$string['schoolyearsaved'] = 'School year saved.';
$string['startdate'] = 'First day';
$string['status'] = 'Status';
