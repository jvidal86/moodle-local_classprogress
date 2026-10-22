# Changes

## 0.9.1 (unreleased)

A fix found in review R6, for `mod_classprogress` 0.10.0, which needs this version.

- The Markdown converter behind the export and the log page keeps the private-use characters U+E000 and U+E001 as
  typed. Before, one of them in a plan or an entry made the conversion loop forever or fail, which took the activity's
  log page down from 0.10.0 on.

## 0.9.0 (2026-10-21)

First release, beta, for Moodle 4.5 to 5.3. Needs `mod_classprogress` 0.9.0 to plan and log classes.

- School years private to each teacher, with their first and last day, holidays and non-teaching days (single days and
  periods, typed one per line, read and checked as a whole) and evaluation periods.
- The "Class progress" page in the top menu, for the teachers and managers of at least one course: their school years
  and an overview of every plan activity they can open, across courses, with its status and the sessions still to log.
- The plan library used by the activity: the Markdown plan reader with its errors and warnings, the class calendar
  (timetable, holidays, evaluation periods), the rules for log entries and late entries, and the export figures.
- Privacy provider: export and erasure of a teacher's school years; deleting an account deletes them too.
- English, Catalan and Spanish.
