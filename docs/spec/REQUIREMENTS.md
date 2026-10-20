# Requirements

Format and cycle: see `README.md`. The requirements of M1 to M3 were accepted (status `agreed`) on 2026-10-18; the plan parser requirements (`PLN`) are already backed by running tests (`python3 tools/check_plan.py --selftest`). `LOG`, `HIS` and `MOD` were agreed in M3; `EXP` was agreed on 2026-10-19 for M4 and `REL` on 2026-10-20 for M5.

---

## PLN · Plan format (the importer's grammar; executable in `tools/check_plan.py`)

### REQ-PLN-001 Module heading
Phase: M2 · Status: agreed
The plan has at most one `#` heading: the module. Its code is the text before the first separator (` · `, ` — `, ` – `, ` - ` or `: `); without a separator the first word is the code only if it looks like one (letters then digits, `MP03`); otherwise there is no code. A plan without a `#` heading is allowed and gives the warning `no_module_heading`. In every heading, a trailing run of `#` is removed only when a space comes before it: `## Bloc 1 ##` reads `Bloc 1`, while `# Programació en C#` keeps its `#` (changed 2026-10-18).
Examples: `test-data/plans/0372.md` (`MP 0372 · Gestió de bases de dades`), `test-data/cases/formats.md` (no code), `test-data/cases/no-module-heading.md`, `test-data/cases/hash-in-title.md`.

### REQ-PLN-002 One module per plan
Phase: M2 · Status: agreed
A second `#` heading is the error `multiple_modules`; the plan cannot be approved.
Examples: `test-data/cases/two-modules.md`.

### REQ-PLN-003 RA or block heading
Phase: M2 · Status: agreed
A `##` heading names the RA or block that applies to the `###` days below it. It is optional: a day with none above it gets the warning `no_block`.
Examples: `test-data/cases/no-block.md`, `test-data/plans/0377.md`.

### REQ-PLN-004 Day heading and date formats
Phase: M2 · Status: agreed
A `###` heading starts with a date, written `2026-09-16` or `16/09/2026`, optionally preceded by a weekday (`dl dt dm dc dj dv ds dg`, `mon`…`sun`, `lun`…`dom`), followed by optional hours and an optional title.
Examples: `test-data/cases/formats.md`, `test-data/plans/0374.md` (`dl 14/09/2026 [2h] …`).

### REQ-PLN-005 Unreadable date is an error
Phase: M2 · Status: agreed
A `###` heading without a readable or existing date (`2026-02-30`, `sometime in October`) is the error `bad_date` and the day is not part of the plan; other days are still read.
Examples: `test-data/cases/bad-date.md`.

### REQ-PLN-006 Hours
Phase: M2 · Status: agreed
Hours are written `[2h]`, `[1.5h]` or `[1,5 h]`. Without them the hours come from the weekday's timetable; if the timetable has no hours for that weekday the warning `missing_hours` is given.
Examples: `test-data/cases/formats.md`, `test-data/cases/missing-hours.md`.

### REQ-PLN-007 Day body
Phase: M2 · Status: agreed
Everything under a `###` until the next heading of level 1 to 3 is the day's plan, kept as Markdown. A day with no text gets the warning `empty_day`.
Examples: `test-data/cases/out-of-order-empty.md`.

### REQ-PLN-008 Headings inside code and deeper headings
Phase: M2 · Status: agreed
Headings inside code fences and headings of level 4 or more are part of the body, never structure. Fences follow standard Markdown (changed 2026-10-18): a fence opens with a line that starts, after at most three spaces, with three or more backticks or tildes, and a backtick fence's info text may not contain a backtick (so a line like ```` ```ls``` ```` is inline code, not a fence). It closes only with a line of the same character, at least as long, followed by nothing but spaces (`~~~` does not close a ```` ``` ```` fence). A fence still open at the end of the text gives the warning `unclosed_fence` at the line that opened it, because it may have swallowed later days.
Examples: `test-data/cases/code-fence.md`, `test-data/cases/fences.md`, `test-data/cases/unclosed-fence.md`.

### REQ-PLN-009 Text before the first heading
Phase: M2 · Status: agreed
Text before the first heading is ignored, so notes can be kept there.
Examples: all files in `test-data/plans/`.

### REQ-PLN-010 Several days on one date
Phase: M2 · Status: agreed
Two `###` days with the same date are allowed, with no warning, and keep their order.
Examples: `test-data/cases/same-date-twice.md`.

### REQ-PLN-011 Order of dates
Phase: M2 · Status: agreed
A day dated before the previous day gives the warning `out_of_order`.
Examples: `test-data/cases/out-of-order-empty.md`.

### REQ-PLN-012 Holiday, weekday and class-day checks
Phase: M2 · Status: agreed
A day on a holiday gives `on_holiday`; on a weekday that has no class in the timetable gives `no_class_weekday`; with a weekday prefix that does not match the date gives `weekday_mismatch`.
Examples: `test-data/cases/holiday-and-weekday.md`.

### REQ-PLN-013 School year range
Phase: M2 · Status: agreed
A day outside the school year gives the warning `outside_year`.
Examples: `test-data/cases/outside-year.md`.

### REQ-PLN-014 Real plans are clean
Phase: M2 · Status: agreed
The three real plans in `test-data/plans/` (0372, 0374, 0377) are read with the expected module, block count, day count, hours, first and last date, and no errors or warnings. The PHP importer must give the same results as `tools/check_plan.py` on all of them.
Examples: `test-data/expected.json`.

### REQ-PLN-015 Text encoding and characters
Phase: M2 · Status: agreed
Added 2026-10-18. The plan must be valid UTF-8; otherwise it is refused as a whole with the error `not_utf8`. A leading byte-order mark is ignored. Lines end with LF, CRLF or CR. Dates and hours are written with the digits 0 to 9 only. A word before the date is accepted only if it is one of the weekday forms in REQ-PLN-004, in any letter case (`DL`, `Mié`, `SÁB`); any other word, including look-alike letters such as `ſat` or `FRİ`, makes the heading the error `bad_date`. Unusual input never stops the parser with a crash. Whether the control characters U+001C to U+001F and U+180E count as white space is left unspecified (the two parsers differ; they do not occur in real plans; R1-15).
Examples: `test-data/cases/bom-and-line-endings.md`, `test-data/cases/odd-weekdays.md`, `test-data/cases/unicode-digits.md`.

### REQ-PLN-016 Speed on hostile input
Phase: M2 · Status: agreed
Added 2026-10-18. Reading a plan takes time proportional to its length: lines with thousands of spaces or `#` characters are read in well under a second by both the reference parser and the plugin, so a pasted plan at the size limit cannot tie up the server.
Examples: `test-data/cases/long-lines.md`.

### REQ-PLN-017 Plan wrapped by an AI assistant
Phase: M2 · Status: agreed
Added 2026-10-18 (R2, decided by the teacher). AI assistants often return the plan inside one Markdown code block, sometimes with a sentence before or after it. When the text has no heading of level 1 to 3 outside code fences, and exactly one fenced block contains such headings, the content of that block is read as the plan (line numbers still refer to the pasted text) and the warning `unwrapped_fence` says the wrapper was removed. Any other text is read as before.

---

## HOL · Holidays (M1)

### REQ-HOL-001 Holiday lines
Phase: M1 · Status: agreed
One holiday per line: a date or a period (`a to b`, also `–`, `—`, `-`, `..`, `→`, `a`), then an optional label. Dates are `yyyy-mm-dd` or `dd/mm/yyyy`. Blank lines and lines starting with `#` are ignored.
Examples: `test-data/holidays-centre-2026-27.txt`.

### REQ-HOL-002 Bad holiday lines
Phase: M1 · Status: agreed
A line that is not a date, has a non-existent date, or ends before it starts is reported with its line number and the reason, is never a holiday, and does not stop the other lines. A period whose end is mistyped is also reported, not read as a one-day holiday (changed 2026-10-18): when the first date is followed by a range word or dash (`to`, `a`, `–`, `—`, `-`, `..`, `→`) and then a digit, the text after it must be a valid date, otherwise the line is the error `bad_end_date`. Example: `2026-12-22 to 2027-1-07 Nadal`. A dash followed by text, as in `2026-10-12 - Festa`, is still a one-day holiday with that label. Changed 2026-10-18 (R1): a holiday text with any bad line is refused as a whole and nothing is saved; every bad line is listed with its number and reason, and the teacher's text stays in the form. A date outside 2000-01-01 to 2099-12-31 is the error `out_of_range`, and a period longer than 400 days is the error `too_long`.

### REQ-HOL-003 Shared by the school year
Phase: M1 · Status: agreed
Holidays belong to a school year of one teacher, global to that teacher across all their courses. A plan activity created with that school year uses its holidays and evaluation periods until the plan is approved; at approval the plan keeps its own copy (REQ-LCK-004). Changed 2026-10-18 for the course-activity design.

## CAL · Calendar (M1)

### REQ-CAL-001 Sessions
Phase: M1 · Status: agreed
The sessions of a plan are the dates from the first to the last class day on weekdays whose timetable hours are above zero, minus holidays.

### REQ-CAL-002 Totals and invalid ranges
Phase: M1 · Status: agreed
The calendar gives the number of sessions, total hours and sessions per month. Missing or reversed dates give an empty result and a message, not an error.

### REQ-CAL-003 Sessions lost to holidays
Phase: M1 · Status: agreed
Each holiday reports how many class sessions it removed.

### REQ-CAL-004 Date limits
Phase: M1 · Status: agreed
Added 2026-10-18 (R1). Every date a teacher enters (school year, holidays, evaluation periods, plan) lies between 2000-01-01 and 2099-12-31; a school year and a holiday period are at most 400 days long; plan dates, when given, lie inside their school year. Anything else is refused with a message. As a safety net the calendar itself returns an empty result with a code for a date outside that window or a range longer than 400 days, and dates are compared as dates, never as text, so a typo such as 9999 or 9027 can never make a page loop or run out of memory.

## ACC · Access (M1)

### REQ-ACC-001 Who sees the link
Phase: M1 · Status: agreed
The "Class progress" link in the top bar appears only for users who hold the capability in at least one course. It does not appear for students, guests or users without the capability. Decided in M1: site administrators qualify; a teacher whose enrolment is suspended still qualifies (the role assignment stays and the data is their own); guests and the not-logged-in user never qualify; a role granted during a session shows the link from the next login, while the access check itself is immediate.

### REQ-ACC-002 Every page is protected
Phase: M1 · Status: agreed
Every page requires login and the capability, and refuses guests and users without it with Moodle's permission error.

### REQ-ACC-003 Live check, cached link
Phase: M1 · Status: agreed
The capability is checked live on every page and service call. A cached value may only decide whether the link is shown, so a removed role stops working at once.

### REQ-ACC-004 Capability definition
Phase: M1 · Status: agreed
The capability is `write` type with the personal-data risk flag, and is allowed by default only for the `editingteacher`, `teacher` and `manager` archetypes, never for `user`.

## OWN · Ownership (M1)

### REQ-OWN-001 Private to the owner
Phase: M1 · Status: agreed
Changed 2026-10-18 for the course-activity design (decided by the teacher). A teacher's school years, holidays and evaluation periods are private to that teacher: no other teacher, even with the same capability or as an administrator through the plugin's pages, can read, edit, delete or export them. A plan activity with its days, entries and history is shared by the teachers of its course, that is the users holding the activity's capabilities there (REQ-MOD-002), and by nobody else: a teacher of another course or a student of the course cannot read, change or export it.

### REQ-OWN-002 No existence leak
Phase: M1 · Status: agreed
Asking for a record that does not exist and for one owned by someone else gives the same error.

### REQ-OWN-003 Lists of ids
Phase: M1 · Status: agreed
A request with several ids is refused as a whole if any of them is not the user's; nothing is silently skipped.

## DAT · Database (M1)

### REQ-DAT-001 Install, upgrade, uninstall
Phase: M1 · Status: agreed
Installing creates every table of the data model; the upgrade function exists and succeeds; uninstalling leaves no `local_classprogress_*` table behind.

### REQ-DAT-002 One entry per plan item
Phase: M1 · Status: agreed
A plan item has at most one entry, while entries without a plan item (unplanned sessions) are not limited. The same on PostgreSQL and MariaDB.

### REQ-DAT-003 Changed elsewhere
Phase: M1 · Status: agreed
Added 2026-10-18 (R1). Saving a school year or a plan that was changed (in another tab or session) after its form was opened is refused with "This was changed elsewhere; reload to see the latest version", and nothing is saved.

### REQ-DAT-004 Text kept as typed
Phase: M1 · Status: agreed
Added 2026-10-18 (R1). Names, labels and pasted holiday text are stored exactly as typed, including characters such as `<`, `>` and `&` (for example `Pont <- Immaculada` and `Programació <C++> i Java`), are validated by the plugin's own rules, and are always escaped when displayed, so nothing a teacher types runs as HTML.

### REQ-DAT-005 Upgrade creates missing tables
Phase: M1 · Status: agreed
Added 2026-10-18 (R1). Upgrading from any earlier version of the plugin, including the M0 skeleton (2026101800) that had no tables, leaves every table of the data model in place.

### REQ-DAT-006 No plan without its school year
Phase: M1 · Status: agreed
Deleting a school year and creating a plan activity with it at the same moment never leaves a plan pointing at a missing school year. A draft plan activity whose school year is gone (deleted by its teacher, erased, or not present after a restore) shows that clearly and asks for a school year before import or approval; an approved plan never needs one (REQ-LCK-004). Changed 2026-10-18.

## PRV · Privacy (M1)

### REQ-PRV-001 Metadata
Phase: M1 · Status: agreed
The privacy provider describes every table and field the plugin stores.

### REQ-PRV-002 Export
Phase: M1 · Status: agreed
A privacy export for a user contains their school years, holidays and evaluation periods, and, for each plan activity they contributed to, the entries and history rows they authored, in that activity's module context. Changed 2026-10-18.

### REQ-PRV-003 Erase
Phase: M1 · Status: agreed
Erasing a user deletes their school years, holidays and evaluation periods with everything that is only theirs. In plan activities, which are a course's shared record, their authorship is anonymised (their user id in entries and history replaced by the deleted-user placeholder) and the content stays, so the course's record for the inspection remains complete; the activity's data is fully deleted only with the activity or the course. Other users' data is untouched. Agreed by the teacher 2026-10-18.

### REQ-PRV-004 Account deletion
Phase: M1 · Status: agreed
Deleting a user account does the same as REQ-PRV-003 without waiting for a privacy request. A draft plan activity whose school year disappears this way stays usable: it asks for another school year before it can be imported or approved (REQ-DAT-006). Changed 2026-10-18.

## AUD · Audit events (M1)

### REQ-AUD-001 Audit events
Phase: M1 · Status: agreed
Importing and approving a plan and creating, editing or deleting an entry each record a Moodle event in the plan activity's module context, with the acting user, carrying only ids and codes, never plan or entry text. Changed 2026-10-18 (module context instead of the owner's user context).

---

## LOG · Logging what was done (M3) · agreed 2026-10-18

### REQ-LOG-001 Pending and upcoming sessions
Phase: M3 · Status: agreed
For an approved plan, every plan item dated today or earlier that has no entry is **pending** (listed oldest first); items dated after today are **upcoming**. Items of a draft plan are neither. "Today" is the teacher's own date in their time zone.

### REQ-LOG-002 Entry status
Phase: M3 · Status: agreed
An entry for a plan item has the status `done`, `partial` or `not_done`; an entry for an unplanned session has the status `added`. What was done is required, except for `not_done`, where it may be empty.

### REQ-LOG-003 Deviation reason and measure
Phase: M3 · Status: agreed
`partial` and `not_done` require a reason, one of: strike (vaga), incident, trip (excursió), school event (activitat de centre), teacher absent (absència), other; plus an optional note and an optional corrective measure (the "mesura" of the current Word sheets). `done` and `added` keep neither reason nor note.

### REQ-LOG-004 Written-on date and late flag
Phase: M3 · Status: agreed
The written-on date is the teacher's own date (their time zone) when the entry is first saved, and never changes afterwards; later edits record a separate last-edited time. An entry whose written-on date is after its session date is **late by N days**, shown wherever the entry appears.

### REQ-LOG-005 One entry per session, edit and delete
Phase: M3 · Status: agreed
A plan item has at most one entry. Saving again updates it. An entry can be deleted after a confirmation step. Saving an entry that was changed elsewhere after the form opened is refused, as in REQ-DAT-003.

### REQ-LOG-006 Carry-over
Phase: M3 · Status: agreed
A `partial` entry may be marked "continue in the next session"; the editor of the next plan item of the same plan then shows what was left over.

### REQ-LOG-007 Unplanned sessions
Phase: M3 · Status: agreed
A teacher can add an entry that belongs to no plan item, for a date inside the plan's school year, with the status `added`.

### REQ-LOG-008 Only approved plans are logged
Phase: M3 · Status: agreed
Entries can be created only for approved plans; a draft plan shows no log.

### REQ-LOG-009 Sessions after today
Phase: M3 · Status: agreed
Added 2026-10-18 (decided by the teacher). An entry for a plan item dated after today may only have the status `not_done`, with its reason: a cancellation known in advance, such as an announced strike or trip. `done` and `partial` are refused until that day has come. An unplanned session (`added`) cannot be dated after today. "Today" is the teacher's own date in their time zone, as in REQ-LOG-001.

## HIS · History (M3) · agreed 2026-10-18

### REQ-HIS-001 Every change is recorded
Phase: M3 · Status: agreed
Every creation, update and deletion of an entry writes a history row with a full snapshot of the entry, the acting user and, for "log in as", the real user, in the same transaction as the change.

### REQ-HIS-002 History is kept and shown
Phase: M3 · Status: agreed
History rows are never edited and survive the deletion of their entry. The history of an entry, and of deleted entries of a plan, can be viewed newest first.

---

## MOD · Course activity (M3) · agreed 2026-10-18

### REQ-MOD-001 Added from the activity chooser
Phase: M3 · Status: agreed
"Class progress" appears in a course's "Add an activity or resource" list for users who may add it (editing teachers and managers). Its settings are the activity name, a school year (one of the adding teacher's school years), the weekly timetable and optional first and last class days chosen with date pickers. Each activity is one plan; the module code and title come from the imported text.

### REQ-MOD-002 Teachers only
Phase: M3 · Status: agreed
The activity is visible on the course page and can be opened only by the course's teachers and managers (view and edit capabilities for `teacher`, `editingteacher` and `manager`). Students and other users neither see it on the course page nor can open its pages by URL.

### REQ-MOD-003 Opening the activity
Phase: M3 · Status: agreed
Opening a draft activity leads to its import and preview (M2 behaviour); opening an approved one leads to its daily log (REQ-LOG-*). The plan's days are visible from both.

### REQ-MOD-004 Shared by the course's teachers
Phase: M3 · Status: agreed
Every teacher of the course holding the edit capability can import, approve and log sessions in the activity; history rows and events record who acted (and the real user for "log in as").

### REQ-MOD-005 Top-menu overview
Phase: M3 · Status: agreed
The top-menu "Class progress" page keeps the teacher's school years (holidays, evaluation periods) and lists every plan activity the teacher can open, across courses, with its course, status and number of pending sessions, linking to each.

### REQ-MOD-006 Backup and restore
Phase: M3 · Status: agreed
A course backup always contains the activity's plan, timetable, days, imports and copied holidays and evaluation periods; entries and history only when the backup includes user data. A restored approved plan is complete on its own; a restored draft asks for a school year (REQ-DAT-006).

### REQ-MOD-007 Deletion and reset
Phase: M3 · Status: agreed
Deleting the activity (or the course) deletes all its data. A course reset that removes user data deletes the activity's entries and history and keeps the plan.

---

## IMP · Import (M2)

### REQ-IMP-001 Input and limits
Phase: M2 · Status: agreed
A plan is pasted as text or read from an uploaded `.md` file (the file is not stored). Text above the size limit (default 200 KB) or with more than the day limit (default 400) is refused with a message. Recorded in M2: a plan is also refused with `no_days` (no day heading at all), `too_many_days`, `out_of_range` (a date outside the REQ-CAL-004 window), `module_too_long`, `block_too_long` or `title_too_long` (over 255 characters, the database limit) and `too_many_hours` (a day over 24 hours).

### REQ-IMP-002 Preview
Phase: M2 · Status: agreed
Before anything is saved the teacher sees the module, blocks, number of days and hours, and every error and warning with its line number.

### REQ-IMP-003 Draft plan
Phase: M2 · Status: agreed
Importing saves a draft plan. A draft can be replaced or deleted; an approved plan cannot. Recorded in M2: a `#` module heading replaces the code and the title typed for the draft, each only when the heading gives it; re-importing a draft replaces its text and days, keeps the plan's dates, and adds a row to the import log.

### REQ-IMP-004 Approval rules
Phase: M2 · Status: agreed
Approval is impossible while the plan has errors. Warnings must be shown and explicitly confirmed. Approval asks for confirmation. Recorded in M2: approval is refused if the plan changed since the teacher's preview (a fingerprint of what was shown); approval only changes the status, the days are written by the import.

### REQ-IMP-005 All or nothing
Phase: M2 · Status: agreed
Import and approval each run in one transaction: a failure leaves no half-saved plan.

## LCK · Lock (M2)

### REQ-LCK-001 Approved plan is read-only
Phase: M2 · Status: agreed
An approved plan rejects any change to its items, timetable and source text through every path: form, service call and a tampered request. In the course-activity design (decision 12) the activity's name and description can still be changed through its settings, its course is fixed by where it lives, and its school year, dates and timetable stay locked; it can only be removed by deleting the activity, which deletes its record (REQ-MOD-007). (Earlier wording, same day: "its name and course link may still be changed, and it cannot be deleted".)

### REQ-LCK-002 Approval is final
Phase: M2 · Status: agreed
Any teacher of the course holding the activity's edit capability may approve (decided 2026-10-18); approval records the time and the acting user and fires the `plan_approved` event. There is no way to un-approve.

### REQ-LCK-003 Safe display
Phase: M2 · Status: agreed
Plan text is displayed through Moodle's Markdown formatting with cleaning on: script, event handlers, `javascript:` links and other unsafe HTML from the plan are removed and never run; harmless formatting that survives the cleaning may still show (wording corrected in R2).

### REQ-LCK-004 Dates do not move
Phase: M2 · Status: agreed
At approval the plan keeps its own copy of the holidays and evaluation periods it was checked against (decided 2026-10-18), so it depends on no school year afterwards. Editing or deleting holidays, timetables or the school year later never changes the dates or hours of an approved plan.

---

## EXP · Export (M4) · agreed 2026-10-19

The layout follows the teacher's own Word tracking sheets (`~/CFGS/seguiment-programacio`, columns "Data · Previst · Realitzat · Estat · Desviació · motiu · mesura", grouped by evaluation period) and the summary of their `genera_seguiment.py --resum`, with two changes the teacher decided on 2026-10-19: coverage counts every session due, not only the logged ones, and each row names who logged it. Pure parts (the five states, the figures, Markdown to text) live in the local plugin's library; the page and the file in the activity.

### REQ-EXP-001 Export page and access
Phase: M4 · Status: agreed
An approved plan activity offers **Export** on its log page to every user who may view it (REQ-MOD-002, REQ-MOD-004). The export page shows the summary tables of REQ-EXP-006 and a button that downloads the XLSX file; the page and the file are computed in the same way from the same data, with the same "today". A draft plan has no export (`error_plannotapproved`); a user who may not open the activity gets `error_notfound` (REQ-OWN-002).

### REQ-EXP-002 The file
Phase: M4 · Status: agreed
One XLSX workbook with two sheets in this order, **Plan vs done** and **Summary**. Each starts with a header block: module code and title, course full name, activity name, school year dates, plan dates, planned sessions and hours, exported by (the user's full name) and exported on (their date and time). The download name is `clean_filename()` of `<module code>-<course short name>-<export date yyyy-mm-dd>.xlsx`. Dates are written as `yyyy-mm-dd` text, so no time zone can shift them.

### REQ-EXP-003 Plan vs done rows
Phase: M4 · Status: agreed
One row per planned day and one per unplanned session, ordered by date; within a date, planned days in plan order and then unplanned sessions. Columns: date, evaluation period, block, planned (day title, then its content), planned hours, status, what was done, reason, reason note, corrective measure, continues in the next session (yes or empty), written on, days late, logged by (the full name of the user who first saved the entry, from its history; "Deleted user" when that user was erased, REQ-PRV-003). The status is the entry's (done, partly done, not done, added) or, for a planned day without an entry, **not logged** (dated today or earlier) or **upcoming** (dated after today). Days late is 0 for an entry written on its day and empty for a day without an entry.

### REQ-EXP-004 Five states of a planned day
Phase: M4 · Status: agreed
Every planned day is in exactly one state: **done**, **partly done** or **not done** (it has an entry with that status, whatever its date; a cancellation logged in advance, REQ-LOG-009, is not done), **not logged** (no entry, dated today or earlier) or **upcoming** (no entry, dated after today). For any set of planned days, planned = done + partly done + not done + not logged + upcoming. Unplanned sessions are counted apart (**added**) and never in planned. "Today" is the exporting user's date in their time zone (REQ-LOG-001).

### REQ-EXP-005 Summary figures
Phase: M4 · Status: agreed
For a set of days the summary gives: planned, done, partly done, not done, not logged, upcoming, added; **coverage** = (done + ½ × partly done) ÷ (done + partly done + not done + not logged), that is every planned day except the upcoming ones, as a whole percentage, empty when that divisor is 0 (decided by the teacher on 2026-10-19: a session not yet logged counts as not covered until it is logged; a partly done session counts as half, as in `genera_seguiment.py --resum`; a cancellation logged in advance counts from the moment it is logged); planned hours (a day without hours, warning `missing_hours`, counts as 0); **hours done** = hours of done days + ½ × hours of partly done days; **hours not done** = hours of not done days + ½ × hours of partly done days; **written late** (entries, planned or unplanned, with days late above 0) and the **most days late**. Unplanned sessions have no hours and are not in coverage.

### REQ-EXP-006 Overall, per block, per evaluation period
Phase: M4 · Status: agreed
The Summary sheet and the export page hold three tables with the columns of REQ-EXP-005: **overall** (one row); **per block**, one row per RA or block in order of first appearance in the Plan vs done rows (date order), then "No block" when some days have no block (REQ-PLN-003) and "Unplanned sessions" when there are any; **per evaluation period**, one row per period copied at approval (REQ-LCK-004) in date order, a day or unplanned session belonging to the period whose first and last days include it, then "Outside evaluation periods" when some days fall outside every period; a period with no days shows zeros, while "No block", "Unplanned sessions" and "Outside evaluation periods" are listed only when they have days. In each table the rows add up exactly to the overall row in every count and hours column (clarified 2026-10-19: hours are stored with two decimals, so written with three they are exact).

### REQ-EXP-007 Markdown as plain text
Phase: M4 · Status: agreed
The planned content and the entry texts (what was done, reason note, corrective measure) are written as plain text: list items start with "• " (numbered items keep their number), heading, emphasis and code markers are removed, a link keeps its text followed by its address in brackets, HTML tags are removed and entities decoded, and line breaks stay as line breaks inside the cell. Nothing of the text is lost but the markup. Proved on the three real plans of `test-data/plans/`. An emphasis longer than 300 characters keeps its markers, so that the conversion takes time in proportion to the text even when markers are never closed (clarified 2026-10-20, R5-2).

### REQ-EXP-008 No formulas, no links
Phase: M4 · Status: agreed
Every text cell is written as text (`write_string`), on 4.5 and on 5.3, and no cell of the file holds a formula: totals and percentages are written as numbers. Text beginning with `=`, `+`, `-` or `@` stays text; Moodle 4.5 and 5.3 put an apostrophe before it, which shows in the cell (checked in `lib/classes/dataformat.php` of both versions), while list items begin with "•" and never trigger it. Addresses stay plain text, never hyperlink cells.

### REQ-EXP-009 Same numbers everywhere
Phase: M4 · Status: agreed
The figures on the export page equal those in the file, and the overall "not logged" figure equals the number of pending sessions of the log page (REQ-LOG-001) and of the top-menu overview (REQ-MOD-005) at the same moment.

### REQ-EXP-010 Large plans
Phase: M4 · Status: agreed
A plan of 400 days, each with an entry, exports with a number of database queries that does not grow with the number of days, and with memory raised the way core exports do (`raise_memory_limit(MEMORY_EXTRA)`).

---

## REL · Release (M5) · agreed 2026-10-20

What a school needs to install, run, translate and remove both plugins, and what the teacher needs to trust them in front of the inspection. Decided by the teacher on 2026-10-20: the translations ship inside each plugin, and each plugin's release is published as a GitHub release of its repository with its zip attached (built by a workflow on a `v*` tag, with a manual dry run that builds the zip without releasing), not in the Moodle plugins directory. Each requirement has an automatic test; the checks that only a person can make (installing the real zip on fresh sites, upgrading the lab with its data, uninstalling, reading the translations and the README, opening the file in LibreOffice) are listed for review round R5 in `PLAN.md` section 6.3.

### REQ-REL-001 Release versions
Phase: M5 · Status: agreed
Both plugins are released as `0.9.0` with maturity `MATURITY_BETA` (decided by the teacher on 2026-10-20: `1.0.0` stable follows a few weeks of real classes), a `version` above every earlier one (2026101900, installed on the lab), `requires` 2024100700 (4.5.0) and `supported` [405, 503]; the activity depends on the local plugin's release version. The release changes no table, so a site with 0.1.0 data upgrades without losing any of it.

### REQ-REL-002 The release zip
Phase: M5 · Status: agreed
Each plugin's release is a zip built with `git archive --prefix=classprogress/` of its release tag: one root folder `classprogress` holding the plugin, with `README.md`, `CHANGES.md`, `version.php`, `lang/en`, `lang/ca`, `lang/es` and the tests, and none of the development material (`PLAN.md`, `docs/`, `prototype/`, `test-data/`, `tools/`, `.github/`, `.gitattributes`, `.gitignore`, `.moodle-plugin-ci.yml`). Moodle's own package validator (`\core\update\validator`, the one behind "Install plugins") accepts it on 4.5 and 5.3.

### REQ-REL-003 Catalan and Spanish
Phase: M5 · Status: agreed
Every English string of both plugins has a Catalan and a Spanish translation in `lang/ca/` and `lang/es/` inside the plugin: the same keys and no others, the same placeholders (`{$a}`, `{$a->name}`), and no string left in English unless it is listed as the same in both languages (such as "XLSX"). A user whose language is Catalan or Spanish sees both plugins, the export page and the exported file (sheet names, headers, statuses) in that language once the site has that language pack, with no file copied by hand (Moodle 4.5 and 5.3 read a plugin's own `lang/<lang>/` folder: `lib/classes/string_manager_standard.php`, lines 187 to 190 on both). The export oracle `tools/check_export.py` keeps checking the English file.

### REQ-REL-004 README and CHANGES
Phase: M5 · Status: agreed
Each plugin's zip holds a `README.md` with these sections: what it does; requirements (Moodle 4.5 to 5.3, PHP 8.1 or later, the other plugin); install (from the zip or from git, the local plugin first, the folders on 4.5 and on 5.x); languages; settings and limits; uninstall (the activity first, then the local plugin; uninstalling deletes all their data); privacy (what is stored and for how long, who can read it, that site administrators can read the database and nothing is encrypted, that erasing a user anonymises their part of a plan activity); known limits (an approved plan cannot be changed, while an editing teacher can still delete the activity and its record with it). `CHANGES.md` lists releases newest first, and its first entry is the `release` of `version.php`.

### REQ-REL-005 From start to finish
Phase: M5 · Status: agreed
One Behat journey (J9, in the activity's repository), on 4.5 and 5.3: a teacher creates a school year with holidays and evaluation periods, adds a Class progress activity to a course, imports a real plan from `test-data/plans/`, approves it, logs sessions (done, partly done, not done with a reason, an unplanned session, one written late), sees the pending count in the top-menu overview, and opens the export page, whose totals match the log; a second teacher of the course logs a session too; a student of the course sees neither the link nor the activity.

### REQ-REL-006 Privacy request end to end
Phase: M5 · Status: agreed
Through the site's data privacy tool (`tool_dataprivacy`), on both versions: a data export request for a teacher, once approved and processed by its task, gives a file that holds their school years, holidays and evaluation periods and, under each plan activity, the entries and history rows they authored (REQ-PRV-002); a data deletion request, once approved and processed, leaves the site as REQ-PRV-003 says, and other teachers' data unchanged.

### REQ-REL-007 Accessibility
Phase: M5 · Status: agreed
Every page of both plugins (the top-menu page, the school year pages, and the activity's settings, import and preview, plan, log, entry, history and export pages) passes Moodle's automated accessibility check (`the page should meet accessibility standards`, axe) on 4.5 and 5.3, with the forms in both their empty and their error state. Every form field has a label, and every action is a link or a button that the keyboard reaches; the plugins add no JavaScript of their own.
