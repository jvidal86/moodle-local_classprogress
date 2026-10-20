# Class progress (local_classprogress)

A Moodle plugin for teachers who must show the education inspection what was planned and what was done in each class
session. It works together with the course activity
[`mod_classprogress`](https://github.com/jvidal86/moodle-mod_classprogress), where each plan and its daily log live;
this plugin keeps what belongs to the teacher rather than to one course.

- **School years:** each teacher's own school years, each with its first and last day, its holidays and non-teaching
  days (one per line, a date or a period with an optional label, such as `2026-12-22 to 2027-01-07 Christmas`) and its
  evaluation periods. A school year is private to the teacher who created it and serves all of their plan activities,
  in every course.
- **The "Class progress" page in the top menu,** shown to every user who is a teacher, editing teacher or manager in
  at least one course: their school years and an overview of every plan activity they can open, across courses, with
  its course, its status and the number of sessions still waiting to be logged.
- **The plan library** the activity uses: the reader of Markdown plans, the class calendar (timetable, holidays,
  evaluation periods), the rules for log entries and the figures of the export.

## Requirements

- Moodle 4.5 to 5.3.
- PHP 8.1 or later, and at least the version your Moodle needs (Moodle 5.x needs a newer PHP of its own). Tested with
  PHP 8.1 and 8.3 on 4.5, and PHP 8.3 and 8.4 on 5.3.
- The activity [`mod_classprogress`](https://github.com/jvidal86/moodle-mod_classprogress), of the same release, to
  create plans and log sessions. This plugin installs without it, but then the page shows only school years.

## Install

Install this plugin first, then the activity: Moodle refuses the activity while this plugin is missing.

From the release zip, `local_classprogress-<release>.zip` on the
[releases page](https://github.com/jvidal86/moodle-local_classprogress/releases). Its only root folder is
`classprogress`. Either:

- In Moodle: Site administration > Plugins > Install plugins, upload the zip and follow the steps. This needs a web
  server that may write to Moodle's `local` folder.
- Or by hand: unzip it into Moodle's `local` folder, so that the plugin is in `local/classprogress` on Moodle 4.5 or
  `public/local/classprogress` on Moodle 5.x.

From git, in the Moodle folder (on 5.x use `public/local/classprogress`):

```bash
git clone https://github.com/jvidal86/moodle-local_classprogress.git local/classprogress
git -C local/classprogress checkout v0.9.0
```

A git checkout also holds the development material (plan, specification, tools, test data), which the zip leaves out;
on a production site prefer the zip.

Then run the upgrade: visit Site administration > Notifications, or run `php admin/cli/upgrade.php` from the Moodle
folder (the `admin/cli` folder stays at the top on 5.x too). Install the activity the same way, into `mod/classprogress`
(`public/mod/classprogress` on 5.x).

## Languages

English, Catalan and Spanish ship inside the plugin (`lang/en`, `lang/ca`, `lang/es`); there is nothing to copy by
hand. For users to choose Catalan or Spanish, the site needs that language pack installed (Site administration >
Language > Language packs); each user then sees the plugin in their own language. The activity's export file follows
the language of the user who exports it.

## Settings and limits

This plugin has no settings. Who may use it is decided by the capability `local/classprogress:use`, given by default
to the teacher, editing teacher and manager roles in a course; a user who holds it in at least one course sees the
page.

Fixed limits, checked whenever a school year is saved:

- Every date lies between 2000-01-01 and 2099-12-31.
- A school year, and a holiday period, is at most 400 days long.
- The name of a school year or of an evaluation period, and a holiday label, is at most 255 characters long.
- The holiday text of a school year is at most 32 KB. A text with a line that cannot be read is refused as a whole,
  with the line and the reason, so no bad line is stored and the text stays in the form to be corrected.
- A school year has at most 20 evaluation periods.

The activity's own limits (the largest plan text, the most days in a plan) are settings of the activity.

## Uninstall

Uninstall the activity first, then this plugin (Site administration > Plugins > Plugins overview): Moodle refuses to
uninstall this plugin while the activity, which depends on it, is installed. Then delete the `local/classprogress`
folder (`public/local/classprogress` on 5.x), or Moodle installs the plugin again at the next upgrade.

Uninstalling deletes all of the plugins' data, and it cannot be undone: this plugin's school years with their holidays
and evaluation periods, and the activity's plans, entries and history in every course. If the record must be kept,
take a backup of each course with its plan activities first (with user data, so that it holds the entries and their
history), and keep each plan's exported file.

## Privacy

What this plugin stores: each teacher's school years (name, first and last day), their holidays (dates and label)
and their evaluation periods (name and dates), with the time each was created and last changed. Nothing about students.

- **Where, and who can read it:** a school year belongs to the teacher who created it, in their user context, and only
  that teacher sees or changes it through the plugin's pages; a site administrator does not open another teacher's
  school year there either. The other teachers of a course see a school year's holidays (never its name) in the draft
  plans of that course that use it. When a plan is approved, the activity takes its own copy
  of the school year's dates, holidays and evaluation periods, which then belongs to the activity, shared by the
  course's teachers and managers (see the activity's README).
- **How long:** until the teacher deletes the school year, their account is deleted, or the plugin is uninstalled.
  Deleting a school year leaves the activities that used it: an approved plan keeps its copy, and a draft asks for
  another school year.
- **Not encrypted:** site administrators, and anyone who can read the database or its backups, can read the data.
- **A privacy export** (Site administration > Users > Privacy and policies > Data requests) gives the teacher's school
  years with their holidays and evaluation periods.
- **An erasure** (a deletion request, or deleting the account) deletes the teacher's school years with their holidays
  and evaluation periods. What the teacher wrote in plan activities is the activity's: its content stays as the
  course's record, and the teacher's authorship there is anonymised.

## Known limits

- An approved plan cannot be changed, and neither can the copy of the school year it keeps: changing a school year's
  holidays later changes only the plans that are still drafts. Corrections and moving sessions are planned for a later
  version.
- The top-menu link is decided once per session: a teacher who is given, or loses, a teacher role sees the link appear
  or go after their next login. The page itself checks at once.
- A school year belongs to one teacher and cannot be shared: a plan activity keeps the school year it was given, and
  another editing teacher of the course can only move a draft into one of their own school years.
- The plugins add no JavaScript of their own: dates are chosen with Moodle's own date selectors, and nothing is copied
  to the clipboard for you.

## Development

The project's plan, specification and development notes live in this repository and stay out of the release zip:
`PLAN.md` (what is built and why, phases and review rounds), `docs/spec/REQUIREMENTS.md` (the requirements every test
cites), `docs/DEV_ENVIRONMENT.md` (test stacks, daily workflow, the release) and `docs/TESTING_STRATEGY.md`. Changes
are listed in `CHANGES.md`.

## License

GNU GPL v3 or later.
