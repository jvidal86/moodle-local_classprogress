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

$string['cachedef_showlink'] = 'Whether the Class progress link is shown to the current user';
$string['classprogress:use'] = 'Keep a record of what was planned and what was done in class';
$string['evententrycreated'] = 'Entry created';
$string['evententrydeleted'] = 'Entry deleted';
$string['evententryupdated'] = 'Entry updated';
$string['eventplanapproved'] = 'Plan approved';
$string['eventplanimported'] = 'Plan imported';
$string['pluginname'] = 'Class progress';
$string['privacy:metadata:local_classprogress_entry'] = 'What actually happened in a session, written by the teacher. The text may name students.';
$string['privacy:metadata:local_classprogress_entry:carry'] = 'Whether unfinished content is carried over to a later session.';
$string['privacy:metadata:local_classprogress_entry:content'] = 'What was done in the session, written by the teacher; it may name students.';
$string['privacy:metadata:local_classprogress_entry:contentformat'] = 'The text format of what was done.';
$string['privacy:metadata:local_classprogress_entry:measure'] = 'The corrective action for a deviation; it may name students.';
$string['privacy:metadata:local_classprogress_entry:planid'] = 'The plan the entry belongs to.';
$string['privacy:metadata:local_classprogress_entry:planitemid'] = 'The planned day the entry is for; empty for an unplanned session.';
$string['privacy:metadata:local_classprogress_entry:reason'] = 'The reason given for a deviation from the plan.';
$string['privacy:metadata:local_classprogress_entry:reasonnote'] = 'Free text about the reason; it may name students.';
$string['privacy:metadata:local_classprogress_entry:sessiondate'] = 'The day the session took place.';
$string['privacy:metadata:local_classprogress_entry:status'] = 'Whether the planned content was done, partly done or not done.';
$string['privacy:metadata:local_classprogress_entry:timecreated'] = 'When the entry was created.';
$string['privacy:metadata:local_classprogress_entry:timemodified'] = 'When the entry was last changed.';
$string['privacy:metadata:local_classprogress_entry:writtenon'] = 'The day the entry was written, which shows whether it was written late.';
$string['privacy:metadata:local_classprogress_entry_hist'] = 'The history of every change to an entry, kept after the entry is deleted. Its copies of the entry hold text that may name students.';
$string['privacy:metadata:local_classprogress_entry_hist:action'] = 'Whether the entry was created, updated or deleted.';
$string['privacy:metadata:local_classprogress_entry_hist:entryid'] = 'The entry that was changed.';
$string['privacy:metadata:local_classprogress_entry_hist:planid'] = 'The plan of the entry.';
$string['privacy:metadata:local_classprogress_entry_hist:realuserid'] = 'The real user, when the change was made by someone logged in as the user.';
$string['privacy:metadata:local_classprogress_entry_hist:snapshot'] = 'A copy of every field of the entry at the time of the change, including text that may name students.';
$string['privacy:metadata:local_classprogress_entry_hist:timecreated'] = 'When the change was made.';
$string['privacy:metadata:local_classprogress_entry_hist:userid'] = 'The user who made the change.';
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
$string['privacy:metadata:local_classprogress_import'] = 'The log of plan imports. The plan text itself is kept on the plan.';
$string['privacy:metadata:local_classprogress_import:contenthash'] = 'A fingerprint (SHA-1) of the imported text.';
$string['privacy:metadata:local_classprogress_import:daycount'] = 'The number of days read from the text.';
$string['privacy:metadata:local_classprogress_import:filename'] = 'The name of the uploaded file.';
$string['privacy:metadata:local_classprogress_import:planid'] = 'The plan the text was imported into.';
$string['privacy:metadata:local_classprogress_import:source'] = 'Whether the text was pasted or uploaded.';
$string['privacy:metadata:local_classprogress_import:timecreated'] = 'When the plan was imported.';
$string['privacy:metadata:local_classprogress_plan'] = 'A teacher\'s year plan for one module, imported from Markdown and read-only once approved.';
$string['privacy:metadata:local_classprogress_plan:courseid'] = 'The course the teacher linked the plan to, if any.';
$string['privacy:metadata:local_classprogress_plan:enddate'] = 'The last class day of the plan.';
$string['privacy:metadata:local_classprogress_plan:modulecode'] = 'The module code from the plan\'s main heading.';
$string['privacy:metadata:local_classprogress_plan:moduletitle'] = 'The module title from the plan\'s main heading.';
$string['privacy:metadata:local_classprogress_plan:schoolyearid'] = 'The school year of the plan.';
$string['privacy:metadata:local_classprogress_plan:sourcetext'] = 'The Markdown text of the plan, as pasted or uploaded by the teacher.';
$string['privacy:metadata:local_classprogress_plan:startdate'] = 'The first class day of the plan.';
$string['privacy:metadata:local_classprogress_plan:status'] = 'Whether the plan is a draft or approved.';
$string['privacy:metadata:local_classprogress_plan:timeapproved'] = 'When the teacher approved and locked the plan.';
$string['privacy:metadata:local_classprogress_plan:timecreated'] = 'When the plan was created.';
$string['privacy:metadata:local_classprogress_plan:timemodified'] = 'When the plan was last changed.';
$string['privacy:metadata:local_classprogress_plan:userid'] = 'The teacher who owns the plan.';
$string['privacy:metadata:local_classprogress_planitem'] = 'A planned day of a plan, read from the plan text.';
$string['privacy:metadata:local_classprogress_planitem:block'] = 'The learning outcome or block heading above the day.';
$string['privacy:metadata:local_classprogress_planitem:content'] = 'What the teacher planned for the day, in Markdown.';
$string['privacy:metadata:local_classprogress_planitem:hours'] = 'The planned hours of the session.';
$string['privacy:metadata:local_classprogress_planitem:planid'] = 'The plan the day belongs to.';
$string['privacy:metadata:local_classprogress_planitem:sessiondate'] = 'The planned date of the session.';
$string['privacy:metadata:local_classprogress_planitem:sortorder'] = 'The position of the day in the plan text.';
$string['privacy:metadata:local_classprogress_planitem:timecreated'] = 'When the day was imported.';
$string['privacy:metadata:local_classprogress_planitem:title'] = 'The short title of the day.';
$string['privacy:metadata:local_classprogress_schoolyear'] = 'A teacher\'s school year, whose dates hold the holidays and evaluation periods shared by all of that teacher\'s plans in the year.';
$string['privacy:metadata:local_classprogress_schoolyear:enddate'] = 'The last day of the school year.';
$string['privacy:metadata:local_classprogress_schoolyear:name'] = 'The name of the school year.';
$string['privacy:metadata:local_classprogress_schoolyear:startdate'] = 'The first day of the school year.';
$string['privacy:metadata:local_classprogress_schoolyear:timecreated'] = 'When the school year was created.';
$string['privacy:metadata:local_classprogress_schoolyear:timemodified'] = 'When the school year was last changed.';
$string['privacy:metadata:local_classprogress_schoolyear:userid'] = 'The teacher who owns the school year.';
$string['privacy:metadata:local_classprogress_slot'] = 'The weekly timetable of a plan: the hours of class on each weekday.';
$string['privacy:metadata:local_classprogress_slot:hours'] = 'The hours of class on that day of the week.';
$string['privacy:metadata:local_classprogress_slot:planid'] = 'The plan the timetable belongs to.';
$string['privacy:metadata:local_classprogress_slot:timecreated'] = 'When the timetable day was created.';
$string['privacy:metadata:local_classprogress_slot:timemodified'] = 'When the timetable day was last changed.';
$string['privacy:metadata:local_classprogress_slot:weekday'] = 'The day of the week.';
$string['privacy:path:plan'] = 'Plan {$a}';
$string['privacy:path:schoolyear'] = 'School year {$a}';
