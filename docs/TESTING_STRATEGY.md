# Testing and specification strategy

How `local_classprogress` is built: **spec first, test first, end-to-end where it earns its cost.** Written 2026-10-18. Commands for the Moodle containers are *planned* until M0 has run them (see `docs/DEV_ENVIRONMENT.md`).

SDD here means spec-driven development: the requirements in `docs/spec/REQUIREMENTS.md` are written and accepted before the code, and every requirement is proved by a test that cites its id.

## 1. Is it helpful for a Moodle plugin? An honest assessment

| Practice | Helps? | Where it fits in this plugin | Where it does not, and the cost |
|---|---|---|---|
| **TDD** (test first, red-green-refactor) | **Strongly** for logic with clear inputs and outputs. | Calendar, holiday parser, plan parser, access rules, ownership, entry and plan services (late flag, history, lock), exporter figures. We already have the Python reference parser and 14 passing parser cases, so the PHP parser starts from examples, not guesses. | Page scripts, forms, Mustache templates and the hook callback are glue and are awkward to drive test-first; they are covered by the services under them and by Behat. Moodle's PHPUnit needs an initialised test site, so a run takes seconds, not milliseconds, unless the test needs no database. |
| **SDD** (spec first, traceable) | **Yes**, because the rules come from an inspector and are specific (what is frozen, what is a late entry, what must never be shared). | The requirements for M1 and M2 are already written (42 so far). Examples are executable: fixture files, expected-result JSON, Gherkin. | Paperwork that rots. Counter-measures: one behaviour per requirement, examples over prose, a script that fails when a requirement has no test or a test cites an unknown requirement, and writing each phase's spec only when that phase starts. |
| **End-to-end tests** | **Selectively.** | Moodle's own e2e layer is **Behat**: real page flows against a throw-away test site with generators. We use it for the teacher journeys (see section 5). | Slow and the most fragile layer. Behat scenarios without `@javascript` run without a real browser and are much faster; our v1 has no custom JavaScript, so nearly all scenarios should be non-JS. Downloads are hard to assert, so XLSX contents are checked in PHPUnit. |
| **A second e2e tool (Playwright, Cypress)** | **Not in v1.** | Moodle core uses Behat (PHP/Mink) and Jest for JavaScript; its 5.3 and `main` branches contain neither Playwright nor Cypress (checked in `composer.json` and `package.json`). | A second stack would duplicate Behat, lose Moodle's generators and database reset, and add a Node toolchain. It could come later for screenshots in the user guide. |

## 2. The test layers

| Layer | Tool | Speed | What it proves | Share of tests |
|---|---|---|---|---|
| Reference and parity | `python3 tools/check_plan.py --selftest`; PHP provider over the same files | milliseconds | The PHP importer agrees with the executable spec on 3 real plans and 11 edge cases. | small, fixed |
| Unit, no database | PHPUnit `basic_testcase` (exists on 4.5 and 5.3 for tests that touch no database or globals) | fast | `calendar`, `holiday_parser`, `plan_parser`, summary arithmetic | many |
| Integration, with database | PHPUnit `advanced_testcase`, `provider_testcase` | seconds | Services, access, ownership (including an admin), transactions, history, events, privacy, exporter output read back from the generated file | many |
| End to end | Behat, tags `@local @local_classprogress` | tens of seconds per scenario | A teacher can do the whole job through the pages; others cannot | few (about 8 for v1) |
| Differential fuzz | PHP parser vs Python parser on random plans | seconds | No input where the two disagree (review round R2) | one test, many inputs |
| Static | `phpcs`, `phpdoc`, `validate`, `savepoints`, `mustache` | seconds | Style and structure rules in `docs/MOODLE_STANDARDS.md` | every commit |

Rule of thumb: push every rule down to the cheapest layer that can prove it; Behat only checks that the layers are wired together.

## 3. The TDD loop for this project

1. Pick one behaviour from a requirement (for example `REQ-CAL-001`: sessions are weekdays with hours, minus holidays).
2. **Red:** write the smallest test that expresses it, tagged `@group REQ-CAL-001`. Run it and see it fail *for the right reason* (the behaviour is missing, not a typo).
3. **Green:** write the least code that passes. No extra behaviour.
4. **Refactor** with the tests green; re-run the whole file.
5. Commit when green. One behaviour per cycle, one commit per cycle.

Fastest loop, **verified**, on the host without Moodle (for tests extending `\basic_testcase`; PHPUnit 9.6.34, the version Moodle 4.5 ships; about 2 ms per test file):

```bash
(cd tools/host-phpunit && composer install)      # once per checkout or worktree
tools/host-phpunit/run.sh                        # all database-free tests
tools/host-phpunit/run.sh tests/local/calendar_test.php
tools/host-phpunit/run.sh --group REQ-CAL-001
```

The same files then run unchanged inside Moodle. Code tested this way must not use any Moodle function or global.

Planned commands (inside the stack, from `docs/DEV_ENVIRONMENT.md`; 5.x paths start with `public/`):

```bash
# one file, fast loop
moodle-docker-compose exec webserver vendor/bin/phpunit local/classprogress/tests/local/calendar_test.php
# one requirement across the plugin
moodle-docker-compose exec webserver vendor/bin/phpunit --group REQ-CAL-001
# whole plugin
moodle-docker-compose exec webserver vendor/bin/phpunit --testsuite local_classprogress_testsuite
# on the host, no Moodle: parser examples and the requirement matrix
python3 tools/check_plan.py --selftest && python3 tools/check_export.py --selftest && python3 tools/check_traceability.py
```

Rules:
- **No production code without a failing test first**, except glue that cannot be driven that way: `db/install.xml` and `upgrade.php` (checked by the install tests), lang strings, Mustache templates and page scripts (checked by Behat and the service tests under them).
- Pure logic (`calendar`, parsers, arithmetic) is written to need **no Moodle globals**, so its tests extend `basic_testcase` and stay fast.
- Test through public methods and observable results. Do not test private methods. Use the real database through `advanced_testcase`; do not mock `$DB`.
- Test names say the behaviour (`test_holiday_removes_class_session`), not the method.
- A bug always gets a failing test first; the test is tagged with the requirement it violated, or a new requirement is written if the spec was silent.
- Data providers are `public static`; fixtures come from `test-data/` copied to `tests/fixtures/` (annotations, not attributes, as in `docs/MOODLE_STANDARDS.md`).
- Time: tests that depend on "today" or the user's time zone set both explicitly; never read the real clock in a test.

## 4. The SDD cycle for every phase

1. **Specify:** write the phase's requirements with examples; draft the Behat scenarios in Gherkin. **You read and accept them** (status `agreed`). This is the sign-off artifact before any code.
2. **Tests first:** red tests tagged with the ids.
3. **Implement** (the TDD loop).
4. **Trace:** `python3 tools/check_traceability.py --strict M<n>` passes: every requirement up to the phase has a test, none cites an unknown id.
5. **Review round** (`PLAN.md` section 6). Its coherence lens now includes "does the code still do what the requirements say, and do the requirements still say what you want".

Change order when something changes: requirement, then test, then code. `PLAN.md` explains; `REQUIREMENTS.md` decides.

Executable parts of the spec today: `tools/check_plan.py` (the parser grammar), `tools/check_export.py` (the export oracle: reads an XLSX by the labels of `PLAN.md` section 5 and recomputes every Summary figure, `python3 tools/check_export.py FILE.xlsx [--today yyyy-mm-dd]`), `test-data/` (3 real plans, 11 edge cases, holidays) and `tools/check_traceability.py`.

## 5. End-to-end scenarios (Behat)

Policy: few, user-level, readable. Created with generators (`the following "course enrolments" exist`, plus the plugin's own generator), started with `I am on the "..." page logged in as "teacher1"` (the dashboard login is slow), no fixed waits, no `@javascript` unless a step truly needs it.

J1 to J3 live in this repository (`tests/behat`), J4 to J7 in `moodle-mod_classprogress` (`tests/behat/activity.feature`, `log.feature`); the activity also has two scenarios outside the J list (adding the activity, MOD-001; students, MOD-002).

| # | Scenario | Phase | Requirements |
|---|---|---|---|
| J1 | A teacher sees the "Class progress" link; a student and a guest do not | M1 | ACC-001, ACC-002 |
| J2 | A teacher creates a school year, pastes holidays (one bad line is reported) and sets a timetable; the session count is shown | M1 | HOL-001, HOL-002, CAL-001, CAL-002 |
| J3 | Another teacher cannot see or open a teacher's school year; a teacher outside the course gets the same "not found" error for a plan activity (PHPUnit) | M1, M3 | OWN-001, OWN-002 |
| J4 | Importing a plan with a bad date shows the error with its line and offers no approval (activity) | M2, M3 | PLN-005, IMP-002, IMP-004 |
| J5 | Importing a clean plan, reading the preview, another teacher of the course approving (with confirmation of warnings) and finding the plan read-only afterwards (activity) | M2, M3 | IMP-003, IMP-004, LCK-001, LCK-002, MOD-003, MOD-004 |
| J6 | Logging a session on the day, then backfilling an old one: the second shows the late flag (activity) | M3 | LOG-001 to 004, LOG-006, LOG-007 |
| J7 | Editing an entry keeps the earlier version in the history; deleting it keeps the history (activity) | M3 | LOG-005, HIS-001, HIS-002 |
| J8 | The export page offers the file and shows the same totals as the log (the file's cells are checked in PHPUnit) (activity) | M4 | EXP-001, EXP-006, EXP-009 |

At R5, J1 to J8 run as one full teacher journey on both stacks.

## 6. What not to do

- Snapshot tests of whole HTML pages, or assertions on exact CSS.
- Many Behat scenarios for rules that a service test covers faster.
- Tests that depend on order, on the real clock, or on data left by another test.
- Asserting on private state, or on the number of queries, except in the one performance check of the top-bar hook (R1).
- Weakening a test to make it pass; if a requirement is wrong, change the requirement first.
- Treating a green traceability report as proof of quality: it shows that tests exist, not that they are good. The review rounds and the mutation checks are what test the tests.
