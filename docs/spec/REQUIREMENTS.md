# Requirements (phases M1 and M2)

Format and cycle: see `README.md`. All requirements below were accepted (status `agreed`) on 2026-10-18; the plan parser requirements (`PLN`) are already backed by running tests (`python3 tools/check_plan.py --selftest`). `LOG`, `HIS`, `EXP` and `REL` are written at the start of their phases.

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
Holidays belong to a school year and apply to every plan of that year of the same teacher.

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
A teacher cannot read, edit, delete or export another teacher's school years, plans, entries or history, even with the same capability, and even as an administrator. In M1 this is proved for school years and plans; entries and history are proved again in M3 and export in M4, each with the same two kinds of intruder.

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
Added 2026-10-18 (R1). Deleting a school year and creating a plan in it at the same moment never leaves a plan without its school year; if it ever happened, the landing page would still load and show such a plan apart.

## PRV · Privacy (M1)

### REQ-PRV-001 Metadata
Phase: M1 · Status: agreed
The privacy provider describes every table and field the plugin stores.

### REQ-PRV-002 Export
Phase: M1 · Status: agreed
A privacy export contains the user's school years, holidays, plans, items, entries and history.

### REQ-PRV-003 Erase
Phase: M1 · Status: agreed
Erasing a user deletes all of their data, history included, and leaves other users' data untouched. A history row belongs to the owner of its plan: when someone else acted on it (an administrator "logged in as" the teacher), that person's numeric id stays in the owner's history when that person is erased, as in Moodle's own logs (clarified 2026-10-18).

### REQ-PRV-004 Account deletion
Phase: M1 · Status: agreed
Deleting a user account removes their rows without waiting for a privacy request.

## AUD · Audit events (M1)

### REQ-AUD-001 Audit events
Phase: M1 · Status: agreed
Added 2026-10-18. Importing and approving a plan and creating, editing or deleting an entry each record a Moodle event in the owner's user context, carrying only ids and codes, never plan or entry text. The event classes exist and are tested in M1; their triggering is checked again in M2 (import, approval) and M3 (entries).

---

## IMP · Import (M2)

### REQ-IMP-001 Input and limits
Phase: M2 · Status: agreed
A plan is pasted as text or read from an uploaded `.md` file (the file is not stored). Text above the size limit (default 200 KB) or with more than the day limit (default 400) is refused with a message.

### REQ-IMP-002 Preview
Phase: M2 · Status: agreed
Before anything is saved the teacher sees the module, blocks, number of days and hours, and every error and warning with its line number.

### REQ-IMP-003 Draft plan
Phase: M2 · Status: agreed
Importing saves a draft plan. A draft can be replaced or deleted; an approved plan cannot.

### REQ-IMP-004 Approval rules
Phase: M2 · Status: agreed
Approval is impossible while the plan has errors. Warnings must be shown and explicitly confirmed. Approval asks for confirmation.

### REQ-IMP-005 All or nothing
Phase: M2 · Status: agreed
Import and approval each run in one transaction: a failure leaves no half-saved plan.

## LCK · Lock (M2)

### REQ-LCK-001 Approved plan is read-only
Phase: M2 · Status: agreed
An approved plan rejects any change to its items, timetable and source text through every path: form, service call and a tampered request.

### REQ-LCK-002 Approval is final
Phase: M2 · Status: agreed
Approval records the time and fires the `plan_approved` event. There is no way to un-approve.

### REQ-LCK-003 Safe display
Phase: M2 · Status: agreed
Plan text is displayed through Moodle's Markdown formatting with cleaning on: script, event handlers and raw HTML from the plan do nothing.

### REQ-LCK-004 Dates do not move
Phase: M2 · Status: agreed
Editing holidays or a timetable afterwards never changes the dates or hours of an approved plan.
