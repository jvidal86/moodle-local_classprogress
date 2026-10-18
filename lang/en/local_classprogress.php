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
$string['addplan'] = 'New plan';
$string['addschoolyear'] = 'New school year';
$string['aiinstructions'] = 'Write my year plan as Markdown, in exactly the format below, so that I can import it into Moodle. Output only the plan, with nothing after it: anything after the last day would be read as part of that day.

# <module code> · <module title>
(once, as the first heading)

## <RA or block> · <title>
(one heading for each learning outcome or block)

### <date> [<hours>h] <optional short title>
<what we do that day, as a Markdown list; bold and links are allowed>

Rules:
- One ### heading for each class day, in date order. Two days on the same date are allowed.
- Write dates as 2026-09-16 or 16/09/2026. A weekday may come before the date (dl, dt, dc, dj, dv, Mon, Tue, lun, mar, ...).
- Write hours as [2h] or [1,5h]. Leave them out to use the hours of my weekly timetable.
- Everything under a ### heading, up to the next heading, is that day\'s plan.
- Do not add any other # heading. Do not use #, ## or ### headings inside a day.';
$string['aiinstructionsheading'] = 'Instructions for my AI assistant';
$string['aiinstructionsintro'] = 'To have an AI assistant write or convert your plan, copy these instructions into the chat together with your own plan, then paste its answer here.';
$string['allsessions'] = 'All sessions';
$string['approveplan'] = 'Approve plan';
$string['approveplanintro'] = 'Approval is final. Once the plan is approved, its days, dates, hours, text and timetable can no longer be changed, and the plan cannot be deleted. Check the days below before you approve it.';
$string['approveplannoerrors'] = 'The plan text has errors, so the plan cannot be approved. Import a corrected text first.';
$string['block'] = 'RA or block';
$string['cachedef_showlink'] = 'Whether the Class progress link is shown to the current user';
$string['calendar'] = 'Calendar';
$string['calendar_invalid_date'] = 'The first or the last class day is not a valid date.';
$string['calendar_last_before_first'] = 'The last class day is before the first, so there are no sessions.';
$string['calendar_missing_dates'] = 'Enter the first and the last class day to see the calendar.';
$string['calendar_out_of_range'] = 'The first or the last class day is not between 2000-01-01 and 2099-12-31, so there is no calendar.';
$string['calendar_too_long'] = 'The class days span more than 400 days, so there is no calendar.';
$string['calendarsummary'] = '{$a->sessions}, {$a->hours}';
$string['changetext'] = 'Change the text';
$string['classdays'] = 'Class days';
$string['classprogress:use'] = 'Use Class progress';
$string['course'] = 'Course';
$string['course_help'] = 'Optionally, the Moodle course this plan belongs to. Only courses where you teach are listed.';
$string['courselinkedbefore'] = '{$a} (linked before; you no longer teach in it)';
$string['date'] = 'Date';
$string['dateformat'] = 'yyyy-mm-dd';
$string['daterange'] = '{$a->start} to {$a->end}';
$string['dates'] = 'Dates';
$string['days'] = 'Days';
$string['daytitle'] = 'Title';
$string['deleteplan'] = 'Delete plan';
$string['deleteplanconfirm'] = 'Delete the plan {$a} with its timetable, its days and its imports? This cannot be undone.';
$string['deleteschoolyear'] = 'Delete school year';
$string['deleteschoolyearconfirm'] = 'Delete the school year {$a} with its holidays and evaluation periods? This cannot be undone.';
$string['deleteschoolyearhasplans'] = 'The school year {$a} has plans. Delete its plans before you delete the school year.';
$string['editplan'] = 'Edit plan';
$string['editschoolyear'] = 'Edit school year';
$string['enddate'] = 'Last day';
$string['error_baddate'] = 'Enter an existing date written yyyy-mm-dd, such as 2026-09-14.';
$string['error_changedelsewhere'] = 'This was changed elsewhere; reload to see the latest version';
$string['error_confirmwarnings'] = 'Confirm that you have read the warnings.';
$string['error_course'] = 'Choose a course where you teach.';
$string['error_dateoutsideyear'] = 'The date must lie within the school year.';
$string['error_daterange'] = 'Enter a date from 2000-01-01 to 2099-12-31.';
$string['error_endbeforestart'] = 'The last day is before the first day.';
$string['error_evaluationoutsideyear'] = 'The evaluation period must lie within the school year.';
$string['error_evaluationsoverlap'] = 'Evaluation periods cannot share a day.';
$string['error_holidaylines'] = 'Some holiday lines cannot be read. Correct or remove them; nothing has been saved.';
$string['error_holidaytexttoolong'] = 'The holiday text is too long: keep it under 32 KB.';
$string['error_hours'] = 'Enter a number of hours from 0 to 24.';
$string['error_modulecode'] = 'The module code can have up to 100 characters.';
$string['error_moduletitle'] = 'Enter the module title, up to 255 characters.';
$string['error_name'] = 'Enter a name of up to 255 characters.';
$string['error_notfound'] = 'That item does not exist, or you do not have permission to see it.';
$string['error_notimported'] = 'Import the plan text before you approve the plan.';
$string['error_planhaserrors'] = 'The plan text has errors. Correct them; nothing has been saved.';
$string['error_planlocked'] = 'This plan is approved and can no longer be changed.';
$string['error_plantoolarge'] = 'The plan text is larger than {$a} KB, the most a plan can have.';
$string['error_schoolyearhasplans'] = 'This school year has plans. Delete its plans first.';
$string['error_toomanyevaluations'] = 'A school year can have up to 20 evaluation periods.';
$string['error_weekday'] = 'The timetable has a day that is not a weekday.';
$string['error_yearexcludesplans'] = 'Plans of this school year have class days outside these dates. The school year must include them.';
$string['error_yeartoolong'] = 'A school year can be at most 400 days long.';
$string['evaluation'] = 'Evaluation period {no}';
$string['evaluationname'] = 'Name';
$string['evaluations'] = 'Evaluation periods';
$string['evaluationsintro'] = 'Optional. The evaluation periods (1a, 2a, 3a avaluació) group the summary of the export. Each one lies within the school year, and two periods cannot share a day. Leave a row empty to remove it.';
$string['evententrycreated'] = 'Entry created';
$string['evententrydeleted'] = 'Entry deleted';
$string['evententryupdated'] = 'Entry updated';
$string['eventplanapproved'] = 'Plan approved';
$string['eventplanimported'] = 'Plan imported';
$string['firstclassday'] = 'First class day';
$string['firstclassday_help'] = 'The first and the last day of class of this module, written yyyy-mm-dd. The calendar counts the sessions between them on the weekdays of the timetable, minus the holidays of the school year. Leave them empty if you do not know them yet.';
$string['holiday'] = 'Holiday';
$string['holidayerror_bad_end_date'] = 'The end date of the period cannot be read.';
$string['holidayerror_end_before_start'] = 'The period ends before it starts.';
$string['holidayerror_label_too_long'] = 'The label is longer than 255 characters.';
$string['holidayerror_no_such_date'] = 'That date does not exist.';
$string['holidayerror_not_a_date'] = 'The line does not start with a date written yyyy-mm-dd or dd/mm/yyyy.';
$string['holidayerror_out_of_range'] = 'The date is not between 2000-01-01 and 2099-12-31.';
$string['holidayerror_too_long'] = 'The period is longer than 400 days.';
$string['holidayline'] = 'Line {$a->line} ({$a->text}): {$a->reason}';
$string['holidays'] = 'Holidays and non-teaching days';
$string['holidays_help'] = 'One per line: a date or a period, then an optional label. Dates are written 2026-12-08 or 08/12/2026; a period is two dates joined by "to", "a", "-", "–", "—", ".." or "→", as in "2026-12-22 to 2027-01-07 Christmas". Blank lines and lines starting with # are ignored. The holidays apply to every plan of this school year.';
$string['hours'] = 'Hours';
$string['importplan'] = 'Import plan text';
$string['importplanintro'] = 'Paste the text of the plan below, or upload it as a Markdown (.md) or text file, then press Preview. Nothing is saved until you press Save draft after the preview.';
$string['issue_bad_date'] = 'The day heading "{$a->text}" has no date that can be read. Write the date as 2026-09-16 or 16/09/2026, optionally after a weekday such as dl or Mon.';
$string['issue_block_too_long'] = 'The RA or block heading above this day is longer than {$a->max} characters.';
$string['issue_empty_day'] = 'The day {$a->date} has no plan text.';
$string['issue_missing_hours'] = 'The day {$a->date} has no hours, and the timetable has none for that weekday.';
$string['issue_module_too_long'] = 'The module heading is too long: the code can have up to 100 characters and the title up to 255.';
$string['issue_multiple_modules'] = 'A second # module heading. A plan holds one module: remove this heading or import the module separately.';
$string['issue_no_block'] = 'The day {$a->date} has no ## RA or block heading above it.';
$string['issue_no_class_weekday'] = 'The day {$a->date} falls on a weekday with no class in the timetable.';
$string['issue_no_days'] = 'The plan has no days. Write each class day as a ### heading that starts with its date.';
$string['issue_no_module_heading'] = 'The plan has no # module heading, so the module code and title of this plan stay as they are.';
$string['issue_not_utf8'] = 'The text is not UTF-8 text. Save the file as UTF-8 and try again.';
$string['issue_on_holiday'] = 'The day {$a->date} is a holiday: {$a->holiday}.';
$string['issue_out_of_order'] = 'The day {$a->date} comes after {$a->previous} in the text, but it is earlier.';
$string['issue_out_of_range'] = 'The date {$a->date} is not between 2000-01-01 and 2099-12-31.';
$string['issue_outside_year'] = 'The day {$a->date} is outside the school year.';
$string['issue_title_too_long'] = 'The title of the day {$a->date} is longer than {$a->max} characters.';
$string['issue_too_many_days'] = 'The plan has more than {$a->max} days, the most a plan can have. This is the first day over the limit.';
$string['issue_too_many_hours'] = 'The day {$a->date} has {$a->hours} hours; a day can have at most 24.';
$string['issue_unclosed_fence'] = 'A code block opens here and is never closed, so it may hide the days after it.';
$string['issue_unwrapped_fence'] = 'The plan was inside a code block, so it was read from that block and the text around the block was left out.';
$string['issue_weekday_mismatch'] = 'The weekday "{$a->weekday}" is not the weekday of {$a->date}.';
$string['issueline'] = 'Line {$a->line}: {$a->message}';
$string['lastclassday'] = 'Last class day';
$string['maxplandays'] = 'Most days in a plan';
$string['maxplandays_desc'] = 'A plan text with more days than this is refused.';
$string['maxplansize'] = 'Largest plan text (KB)';
$string['maxplansize_desc'] = 'A pasted or uploaded plan text larger than this, in kilobytes, is refused.';
$string['module'] = 'Module';
$string['modulecode'] = 'Module code';
$string['moduletitle'] = 'Module title';
$string['month'] = 'Month';
$string['noblock'] = '(no RA or block)';
$string['nocourse'] = 'No course';
$string['noholidays'] = 'This school year has no holidays.';
$string['noitems'] = 'This plan has no days yet. Import its text to add them.';
$string['noplans'] = 'No plans in this school year yet.';
$string['noschoolyearforplan'] = 'A plan belongs to a school year. Create a school year first.';
$string['noschoolyears'] = 'You have no school years yet. A school year holds the dates, holidays and evaluation periods shared by all your plans of that year.';
$string['plan'] = 'Plan';
$string['planapproved'] = 'Plan approved.';
$string['plandeleted'] = 'Plan deleted.';
$string['plandraftsaved'] = 'Draft saved. Check its days, then approve the plan.';
$string['planfile'] = 'Or upload a file';
$string['planfile_help'] = 'A Markdown (.md) or text (.txt) file, saved as UTF-8. It is read in place of the text box and is not kept.';
$string['planname'] = '{$a->code} · {$a->title}';
$string['plannedcontent'] = 'Planned content';
$string['plansaved'] = 'Plan saved.';
$string['plansnoschoolyear'] = 'Plans without a school year';
$string['plansnoschoolyearintro'] = 'The school year of these plans no longer exists. Open a plan to choose another school year for it, or delete it.';
$string['plantext'] = 'Plan text';
$string['pluginname'] = 'Class progress';
$string['preview'] = 'Preview of the plan text';
$string['previewclean'] = 'No errors or warnings.';
$string['previewerrors'] = 'Errors: the plan cannot be saved until they are corrected.';
$string['previewplan'] = 'Preview';
$string['previewreplaces'] = 'The module heading of the text replaces the module typed for this plan ({$a}).';
$string['previewwarnings'] = 'Warnings: read them; you will be asked to confirm them when you approve the plan.';
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
$string['readwarnings'] = 'I have read the warnings';
$string['savedraft'] = 'Save draft';
$string['schoolyear'] = 'School year';
$string['schoolyearcounts'] = 'Holidays: {$a->holidays}. Evaluation periods: {$a->evaluations}.';
$string['schoolyeardeleted'] = 'School year deleted.';
$string['schoolyearname'] = 'Name';
$string['schoolyearsaved'] = 'School year saved.';
$string['sessions'] = 'Sessions';
$string['sessionslost'] = 'Sessions lost to holidays';
$string['sessionslostcolumn'] = 'Sessions lost';
$string['sessionspermonth'] = 'Sessions per month';
$string['startdate'] = 'First day';
$string['status'] = 'Status';
$string['statusapproved'] = 'Approved on {$a}. The plan is locked: its days, dates, hours, text and timetable can no longer be changed.';
$string['statusapprovedshort'] = 'Approved';
$string['statusdraft'] = 'Draft: the plan can still be changed, imported again or deleted.';
$string['statusdraftshort'] = 'Draft';
$string['summaryhours'] = '{$a} hours of class';
$string['summaryhoursone'] = '{$a} hour of class';
$string['summarysessions'] = '{$a} sessions';
$string['summarysessionsone'] = '{$a} session';
$string['timetable'] = 'Weekly timetable';
$string['timetableintro'] = 'Hours of class of this module on each weekday; 0 means no class that day.';
$string['weekday'] = 'Weekday';
