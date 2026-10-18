# Class progress tracking: Moodle plugin plan

Status: planning (reviewed 2026-10-18), no plugin code yet. Work proceeds in phases M0 to M5, each followed by a review round (section 6). Prototype: `prototype/index.html`.

## 1. What we are building

A teacher-facing Moodle plugin for the inspection requirement: **keep track of what is planned and what is done.**

1. Set up the school year: dates, holidays (single dates and periods, shared by all your courses) and, per plan, the weekly timetable.
2. Import the year plan as **Markdown: one section per day** (paste text or upload a `.md` file). Check the preview, then **approve and lock it**. It never changes afterwards.
3. Log what actually happened against each planned session, including days after the fact. Late entries are flagged automatically and every change is kept in a history.
4. Export plan vs done to XLSX for the inspector. There is no official template, so we design the layout.

One entry point in Moodle's top navigation, across all of the teacher's courses. Private to the teacher.

Out of scope for v1: XLSX plan import (possible later, feeding the same parsed structure), moving or cancelling sessions (strikes, incidents), attachments on entries, pre-filling from Moodle activity data, reviewer access, `mod_attendance` import.

## 2. Facts verified against the Moodle source (Oct 2026)

| Topic | Result | Where checked |
|---|---|---|
| Support window | 4.5 bug fixes ended 6 Oct 2025, security until 4 Oct 2027. 5.3 is the new LTS (5 Oct 2026). | moodledev.io/general/releases |
| PHP | 4.5: 8.1–8.3. 5.1: ≥ 8.2. 5.3: ≥ 8.3. Write for **PHP 8.1**. | docs.moodle.org PHP page, `composer.json` per branch |
| Top-bar link | Hook `\core\hook\navigation\primary_extend` exists in 4.5, 5.1, 5.3. Callback calls `$hook->get_primaryview()->add_node(navigation_node::create($text, $url))`. Registered in `db/hooks.php`. Same pattern used by `local_ai_manager`. | `lib/classes/hook/navigation/primary_extend.php`, `primary.php`, `local_ai_manager` |
| Capability gotcha | A capability checked in system context fails for teachers, whose roles are assigned in courses. `has_capability_in_any_context()` does **not exist**. Available: `get_user_capability_course()` (used for "any course where the user has it", and short-circuited for admins), `has_any_capability()`. The capability's `contextlevel` does not change `has_capability()` behaviour. | `lib/accesslib.php` (4.5) |
| Table names | Limit is 53 characters without prefix, so names like `local_classprogress_entry_hist` are fine. | `lib/xmldb/xmldb_table.php` |
| Translations | Moodle loads **only `lang/en`** from a plugin folder. Other languages come from `$CFG->dataroot/lang/<lang>/` or `<lang>_local/`. | `lib/classes/string_manager_standard.php` |
| Excel writing | `MoodleExcelWorkbook` exists (not deprecated) in 4.5, 5.1, 5.3. `write_string()` forces text type on every 4.5.x (releases from about 4.5.8 also prefix `'` to text starting with `=`, `+`, `-` or `@`). `write()` auto-detects `=`, `@` and URLs, so it must **not** be used for teacher text. | `lib/excellib.class.php` |
| Excel reading (only if XLSX plan import is added later) | PhpSpreadsheet is bundled but changes major version: 1.29 (4.5), 4.1 (5.1), 5.2 (5.3). Use only `IOFactory`, read-only mode, `toArray()`. Not needed for v1, since plans are imported as Markdown. | `lib/thirdpartylibs.xml` per branch |
| 5.x layout | Code moves under `public/`. `public/config.php` is a loader for `../config.php`, so `require_once(__DIR__ . '/../../config.php')` works in both layouts. | `MOODLE_501_STABLE` |
| Privacy | Storing user-authored data requires `metadata\provider`, `request\plugin\provider` and `core_userlist_provider`. `null_provider` is not allowed. | moodledev.io privacy docs |
| Security rules | `require_login`, `require_capability`, `required_param`/`optional_param` with PARAM types, moodleforms, `confirm_sesskey` on actions, `s()`/`format_text()` on output, placeholders in SQL, confirmation before destroying data, log requests as events. | moodledev.io security policy |
| Dev tools | `moodle-docker` supports PHP 8.1–8.4. Docker 29, PHP 8.3.6, Composer, Node 24, git present locally. | README, local check |
| `moodle-docker` and 5.x | The `moodle-php-apache` image picks `/var/www/html/public` as the document root when that directory exists, so no custom image is needed. Init and run commands use `public/admin/tool/...` paths. Read from the image source, not run. | `moodlehq/moodle-php-apache` |
| Test tooling | 4.5 ships PHPUnit 9.6, 5.3 ships 11.5. `moodle-plugin-ci` supports the `public/` layout. | `composer.lock` per branch, `moodle-plugin-ci` source |
| Rule details | 100 rules with sources from `mod_attendance`, `mod_h5pactivity` and `local_ai_manager`. | `docs/moodle-rules/`, summary in `docs/MOODLE_STANDARDS.md` |

Not yet verified (check when first needed):
- `moodle-docker` actually running a 5.3 checkout and mounting the plugin at `public/local/classprogress` (M0).
- Whether PHPUnit 11.5 on 5.3 accepts docblock annotations under `failOnDeprecation` (first CI run, M0).
- Whether teachers see the link and can open the pages with the course-level capability check, including a teacher with a suspended enrolment (M1).
- That the capability appears in the role editor with `CONTEXT_COURSE` (M1).
- That a unique index on the nullable `planitemid` behaves the same on MariaDB and PostgreSQL (M1).

## 3. Architecture

**Plugin:** `local_classprogress`. A `local_` plugin fits: it is site-specific, cross-course functionality.

```
local/classprogress/
  version.php   settings.php (limits)   README.md   CHANGES.md
  db/   install.xml  access.php  hooks.php  events.php  caches.php  upgrade.php
  classes/
    local/hook_callbacks.php      # primary_extend: add link if user qualifies
    local/access.php              # require_user(), ownership checks
    local/calendar.php            # dates x timetable - holidays -> sessions
    local/holiday_parser.php      # "2026-12-23 to 2027-01-06 Nadal", dd/mm/yyyy
    local/plan_parser.php         # Markdown sections -> plan items + warnings
    local/plan_service.php        # import (parse + save draft), approve/lock
    local/entry_service.php       # save/delete entry, late flag, history
    local/exporter.php            # plan vs done -> MoodleExcelWorkbook (needs require_once of excellib)
    local/observers.php           # user_deleted: remove the teacher's rows
    form/  output/  event/  privacy/
  templates/  lang/en/local_classprogress.php
  index.php  setup.php  plan.php  import.php  log.php  export.php
  tests/  (phpunit + behat)
```

**Data model** (tables prefixed `local_classprogress_`; calendar days as `char(10)` ISO dates so they have no timezone; audit times as Unix timestamps):

| Table | Holds |
|---|---|
| `schoolyear` | owner, name, start/end |
| `holiday` | schoolyear, start, end, label. Shared by all plans in that year. |
| `evaluation` | schoolyear, name, start, end (1a, 2a, 3a avaluació). Used to group the export summary, as your Word sheets do. |
| `plan` | owner, schoolyear, module code and module title (from the `#` heading), optional courseid, start/end, status (`draft`/`approved`), source Markdown text, time approved. One plan per module. |
| `slot` | plan, weekday, hours (weekly timetable; optional, gives default hours and day checks) |
| `planitem` | plan, sort order, **session date**, block (the RA or block heading above the day), title, content (Markdown), planned hours |
| `entry` | planitem (null for unplanned), plan, session date, status, text + format, reason, reason note, **measure** (corrective action, as in the current "Desviació · motiu · mesura" column), carry flag, written-on date, times created/modified. One entry per planitem (unique **index**, not key; `planitemid` is null for unplanned entries, so uniqueness is tested on MariaDB and PostgreSQL). |
| `entry_hist` | entry id, plan, user, action (`create`/`update`/`delete`), JSON snapshot of every field, time. Survives deleting the entry. |
| `import` | plan, source (pasted/uploaded name), content hash, day count, time. The plan's Markdown source is stored on the plan itself, so no file storage is needed. |

**Rules that need tests**
- **Dates come from the plan text** and are frozen with it. Holidays and the timetable are only used for warnings (a day on a holiday or a day without class) and default hours. Editing them later never moves an approved item, so the locked plan cannot shift under the inspector.
- Approval is blocked only by errors (an unreadable date). Warnings need the teacher to read them and confirm.
- Approved plans reject every change to items, slots and the source text.
- Late flag: the write date (in the user's timezone, stored as `writtenon`) is after the session date.
- Every save writes a history row, including deletes (an entry may be deleted after confirmation; its history stays).
- Two tabs editing one entry: the form carries the entry's `timemodified`, and a stale save is refused.
- Import and approval run inside a delegated transaction (rollback rethrows). The history row is written in the same transaction as the change; events are triggered after commit.

**Standards and security baseline (applies from M1, not at the end)**
- Every page: `require_login()`, then `local_classprogress\local\access::require_user()`, then params via `required_param`/`optional_param` with PARAM types. No superglobals.
- Writes use moodleforms (sesskey built in); any link-based action uses `confirm_sesskey()` and a confirmation step for destructive actions.
- **Ownership:** a capability never proves ownership, and site admins pass every capability check. Every service method takes the acting user from `$USER`, puts the owner in the WHERE (or joins up to the plan's owner) and compares counts for id lists. PHPUnit proves that user B (with the same capability, and an admin) cannot read, edit, delete or export user A's plans and entries, and that a missing id and a foreign id give the same exception.
- SQL: XMLDB definitions and `$DB` methods with placeholders only.
- Output: `s()` for plain text, `format_string()` for module and RA titles, `format_text($text, FORMAT_MARKDOWN, ['context' => ...])` for plan and entry text (Markdown output is always cleaned; never `noclean`). In templates `{{ }}`, and `{{{ }}}` only for URLs or text already through `format_text()`. Consider `'filter' => false` on the plan view. ISO dates have no PARAM type: read them as `PARAM_TEXT` and validate with a strict pattern and `DateTimeImmutable` in the service.
- Plan text: size limit (default 200 KB) and day limit (default 400) as admin settings. Our own parser reads it as data only. Markdown is stored as text and displayed through `format_text()` with `FORMAT_MARKDOWN` and cleaning on, so no HTML or script from the plan is trusted. An uploaded `.md` file is read into the same text box and not stored.
- Excel output: `write_string()` for every teacher-written value, never `write()`. A test exports the text `=HYPERLINK("http://x","y")` and checks it stays text. Releases from about 4.5.8 prefix `'` to text starting with `=`, `+`, `-` or `@`, which can show a literal apostrophe; our bullets-to-"•" conversion avoids most cases, and M4 compares the visible cell text on 4.5 and 5.3. The download name goes through `clean_filename()`, and the export page runs the same access and owner checks.
- Capability: `local/classprogress:use`, `captype` write (this alone blocks guests), `riskbitmask` `RISK_PERSONAL`, archetypes `editingteacher`, `teacher`, `manager` only. **Never** give it to archetype `user`: students would get in and `get_user_capability_course()` would return every course. The check is `get_user_capability_course($cap, null, true, '', '', 1)` (non-empty means teacher in at least one course). It is a real core function, but none of the three reference plugins uses it, so M1 tests it with teacher, student, suspended-enrolment and admin accounts. `contextlevel` in `access.php` does not change `has_capability()`; use `CONTEXT_COURSE` and confirm in M1 that the capability shows in the role editor. The session cache (`db/caches.php`, plus a `cachedef_` string) is used **only to decide whether to show the link**; every page and service call runs the live check, so a removed role stops working at once. Fallback if awkward: an admin setting listing allowed roles.
- Events: `plan_imported`, `plan_approved`, `entry_created`, `entry_updated`, `entry_deleted` in `classes/event/` extending `\core\event\base` (`crud`, `edulevel`, `objecttable`, `get_name()`, `get_description()`), in the teacher's user context, with only ids and codes in `other` (never plan or entry text). `entry_hist` stays the authoritative history; events are a second trail.
- Privacy provider with the three interfaces above, in the teacher's **user context** (as `block_rss_client` and core calendar do), covering every table including `entry_hist` and the stored Markdown. Every table and field is described in `get_metadata`, noting that entry text may name students. `delete_data_for_all_users_in_context` deletes only that user's rows. A `user_deleted` observer (`db/events.php`) removes the rows when an account is deleted. Tested with `provider_testcase` (neither `mod_attendance` nor `local_ai_manager` has such tests; core's `block_rss_client` does).
- Code style: Moodle coding standard (`phpcs --max-warnings 0`), PHPDoc on every class, method and constant, GPL header on every PHP file, typed parameters and return types, `else if`, `#[\Override]` instead of `@inheritdoc`, no `unserialize`, `print_r` or `error_log`. `defined('MOODLE_INTERNAL') || die();` follows the phpcs sniff, not a blanket rule: required in files with side effects or several artifacts (`db/access.php`, `hooks.php`, `caches.php`, `events.php`, `settings.php`, and class files that `require_once`, such as the form classes and `exporter.php`); **not** in single-class files or `db/upgrade.php` (that warns, and warnings fail CI); not in `lang/` or Behat files.
- Strings: `lang/en` only, sorted by `strcmp` byte order (phpcs enforces it), with `_desc`, `cachedef_` and `privacy:metadata:` keys, whole sentences, `{$a}` placeholders.
- Database: lowercase names, NOT NULL with defaults where a value always exists, key names like `userid` or `planid-sessiondate`, unique constraints as unique indexes. `db/upgrade.php` defines `xmldb_local_classprogress_upgrade()` from the first release; every step has a `field_exists`/`table_exists` guard and a savepoint equal to the new `version`. Changes to `db/hooks.php`, `caches.php`, `access.php` or `events.php` need a `version` bump (they are cached).
- Pages: `config.php`, `require_login()`, access check, PARAM-typed params, `$PAGE` url/context/title, redirect after a successful POST, confirmation page with `confirm_sesskey()` for link-based deletes. Output through `classes/output/` templatables plus Mustache (docblock with `@template` and an example context), no root `renderer.php`, no custom JavaScript in v1. Errors are `moodle_exception` with a lang string id. Hook callbacks are cheap and install-safe (return early during initial install). Use `\core\url` in new code.
- `version.php`: `component`, `version` (YYYYMMDDXX), `requires = 2024100700` (4.5.0; never raised to follow core's own bumps), `supported = [405, 503]`, `release` as semver (`0.1.0`), `maturity` ALPHA until M5.
- CI with `moodle-plugin-ci ^4`: `phplint`, `phpmd` (non-blocking), `phpcs --max-warnings 0`, `phpdoc --max-warnings 0`, `validate`, `savepoints`, `mustache`, `grunt`, `phpunit --fail-on-warning`, `behat --profile chrome`. Matrix by `include:`: PHP 8.1 and 8.3 on MOODLE_405_STABLE, PHP 8.3 and 8.4 on MOODLE_503_STABLE; PostgreSQL 17 and MariaDB 11.
- Tests: 4.5 runs PHPUnit 9.6 and 5.3 runs 11.5, so tests use docblock annotations (`@covers`, `@dataProvider`) and `public static` data providers, not attributes; `resetAfterTest()` inside each test; a plugin generator in `tests/generator/lib.php`. Whether 11.5's `failOnDeprecation` rejects annotations is unresolved: the first green run on 5.3 settles it (M0 exit criterion). Behat feature files start with `@local @local_classprogress` (`validate` fails without them). `test-data/` is copied into `tests/fixtures/` by a sync script, with one static provider per expected-results JSON file.

**Translations:** the plugin ships English only. Catalan and Spanish are kept in the repo as `translations/ca` and `translations/es` and copied into `moodledata/lang/<lang>_local/` by an install note or script. Publishing to the Moodle plugin directory would let AMOS handle translations later.

**Documentation and release:** `README.md` (what it does, requirements, install, translations, privacy statement, limits) and `CHANGES.md` (newest first; the Marketplace release workflow uses it as release notes). The rules above are detailed, with sources, in `docs/moodle-rules/` and summarised in `docs/MOODLE_STANDARDS.md`. How to set up and use the development environment (test stacks, labs, daily workflow, M0 checklist) is in `docs/DEV_ENVIRONMENT.md`.

## 4. Plan import (Markdown: module, RA or block, day)

The plan is plain Markdown that a person or an AI assistant can write and edit anywhere, then paste into the plugin. Three heading levels:

```
# MP03 · Programació

## RA1 · Reconeix l'estructura d'un programa

### 2026-09-14 [1h]
Presentació del mòdul i avaluació inicial.
- Normes de l'aula
- Prova diagnòstica

### 16/09/2026 [2h] Entorn de treball
- Instal·lació de l'IDE
- Primer programa

## RA2 · Escriu i prova codi

### 2026-10-05 [2h]
...
```

Rules:
- `#` **module**: its code and text (`MP03 · Programació`). The code is the text before the first separator (` · `, ` — `, ` – `, ` - ` or `: `), so `MP 0372 · Gestió de bases de dades` gives code `MP 0372`. Without a separator, the first word is the code only if it looks like one (`MP03`); otherwise there is no code. One module per plan.
- `##` **RA or block**: free text such as `RA1 · …` or `Bloc 2 · …`. It applies to the days below it. Optional.
- `###` **day**: the date (`2026-09-16` or `16/09/2026`, optionally preceded by a weekday such as `dl`, `dj` or `Wed`, as in your dated guides), optionally `[2h]` or `[1,5h]` for hours, and optionally a short title.
- Everything under a `###` until the next heading is that day's plan, kept as Markdown (lists, bold, links).
- Text before the first heading is ignored, so you can keep notes there. Headings inside code fences are ignored. Several `###` on the same date are allowed.

Preview before approving:
- **Errors (block approval):** a day heading whose date cannot be read; more than one `#` module in the text (v1 imports one module at a time).
- **Warnings (must be read and confirmed):** dates out of order, a day on a holiday or on a weekday with no class, a weekday prefix that does not match the date, a date outside the school year, an empty day, a day with no RA or block above it, missing module heading, missing hours (the weekday's timetable hours are used instead, if set).
- The exact rules and error/warning codes are implemented in `tools/check_plan.py`; the PHP importer must give the same results on `test-data/` (section 10).
- A "Copy instructions for my AI assistant" button gives the format above, ready to paste into a chat together with the teacher's own plan.

An XLSX importer can be added later; it would only need to produce the same list of days.

## 5. Export

No template exists, so we define it, in this order: **Plan vs done** (one row per planned session: planned date, module, RA or block, planned content, planned hours, status, actual date, what was done, deviation reason, written on, late days) and **Summary** (sessions due, done, partly done, not done, no note, hours, written late, coverage), **overall, per RA or block, and per evaluation period** (your current Word sheets already summarise per avaluació), since inspectors usually ask how far each RA has been covered. Markdown in the plan and in the entries is converted to plain text (bullets as "•") for the sheet cells. Written with `MoodleExcelWorkbook`. A CSV export can follow if useful.

## 6. Phases and review rounds

The work is split into phases **M0 to M5** (the former milestones; the ids stay, so every "M1" or "M4" elsewhere in the plan and in `docs/` still points to the right phase). **A phase does not start until the review round of the previous phase is closed.** The review rounds are named R0 to R5 after the phase they follow, and v2 gets its own phases and rounds when it is planned.

### 6.0 How each phase is built (spec first, test first)

Every phase follows the same cycle (details in `docs/TESTING_STRATEGY.md` and `docs/spec/README.md`):

1. **Specify** the phase's requirements in `docs/spec/REQUIREMENTS.md`, with examples and draft Behat scenarios. You accept them (status `agreed`) before code is written. The 42 requirements of M1 and M2 are drafted; later phases are specified when they start, so they benefit from what the earlier ones taught.
2. **Test first:** failing PHPUnit tests (Behat for page flows), each tagged with the requirement ids it proves.
3. **Implement** with the red-green-refactor loop. Pure logic (calendar, parsers, arithmetic) lives in classes that need no database, tested with `basic_testcase` so the loop stays fast.
4. **Trace:** `python3 tools/check_traceability.py --strict M<n>` passes (every requirement up to the phase has a test; no test cites an unknown id).
5. **Review round** (6.1).

Parallel pre-work: pure-logic classes that need no Moodle (parsers, calendar) can be written test-first on separate branches before their phase, with the host test loop (`tools/host-phpunit/run.sh`, PHPUnit 9.6 without Moodle). They are merged only through the gates (decision 8).

End-to-end tests are Behat, Moodle's own e2e layer: about eight teacher-journey scenarios for v1 (J1 to J8 in `docs/TESTING_STRATEGY.md`), mostly without `@javascript`, since v1 has no custom JavaScript. No second e2e tool (Playwright, Cypress) in v1: Moodle core uses neither.

### 6.1 The review round protocol

1. **Freeze.** Commit and tag the phase (`phase-M2-done`); nothing new is built during the round, only fixes.
2. **Self-checks (automatic).** The whole test suite and the CI matrix (PHP 8.1 on 4.5, PHP 8.3 on 5.3, PostgreSQL and MariaDB); `python3 tools/check_plan.py --selftest`; `python3 tools/check_traceability.py --strict M<n>`; developer debugging on (`$CFG->debug = DEBUG_DEVELOPER`) while clicking through every screen.
3. **Independent review.** Fresh agents get the repository and the checklists, **not the author's conclusions**, one per lens below, plus the phase-specific checks in 6.3. For code, the `code-review` and `security-review` skills are used. Findings go back as evidence (file, line, how to reproduce), not as opinions.
4. **Triage.** Every finding is written to `docs/reviews/R<n>-<phase>.md` (template: `docs/reviews/TEMPLATE.md`) with a severity:
   - **P1:** data loss, a leak between users, a security hole, or a wrong figure shown to the inspector.
   - **P2:** a functional bug, a failed standard (phpcs, `validate`, a rule in `docs/MOODLE_STANDARDS.md`), or a flaky test.
   - **P3:** polish, wording, small documentation gaps.
5. **Fix and prove.** Every fixed bug gets a regression test. For the rules that matter most (ownership, lock, escaping, formula safety, history), a **mutation check**: break the rule on purpose and confirm a test fails.
6. **Gate.** The round closes when there is no open P1 or P2 (P3 are fixed or listed in the backlog with a reason), CI is green on all four jobs, the traceability check passes in strict mode for the phase, and the documents are updated (`PLAN.md` status, `docs/DEV_ENVIRONMENT.md`, `test-data/expected.json` if behaviour changed). **R2 and R5 also need your sign-off.**

| Lens | Question | Typical checks |
|---|---|---|
| **Coherence** | Do the requirements, plan, code, docs, tests and prototype say the same thing? | Behaviour against `docs/spec/REQUIREMENTS.md` (and the requirements still say what you want); tests really prove the requirement they cite, not just mention it; data model in `PLAN.md` vs `db/install.xml`; capability, event and string names used vs defined; every rule in section 3 implemented or consciously deferred; lang strings used are defined (and sorted); "planned" items in `docs/DEV_ENVIRONMENT.md` resolved; `test-data/expected.json` vs what the importer does; differences between the prototype and the real screens listed. |
| **Errors** | Does everything build and pass, cleanly? | Four-job CI matrix green; `phpcs`, `phpdoc`, `validate`, `savepoints`, `mustache` with zero warnings; code lints on PHP 8.1; no notices in developer debug mode; fresh install, upgrade from the previous version, and uninstall leaving no tables behind. |
| **Bug hunting** | Can it be made to fail, leak or lie? | Negative and adversarial tests on every page and service; differential fuzzing against the Python reference parser; time zone and DST edges; PostgreSQL vs MariaDB differences; two browser sessions at once; injected failures in transactions; XSS and formula payloads; large inputs; stale caches after a role change. |

### 6.2 The phases

| Phase | Build | Done when |
|---|---|---|
| **M0 Environment and skeleton** | `git init` and `.gitattributes`; the two `moodle-docker` stacks; an empty plugin (`version.php`, `lang/en`); CI workflow; one trivial PHPUnit test; the `test-data` sync script. | The plugin installs on 4.5 (PHP 8.1) and on 5.3 (PHP 8.3); the plugin path inside the 5.3 container is confirmed (`public/local/classprogress`); `phpunit/cli/init.php` works on both; the CI skeleton is green on all four jobs; the first `phpunit --fail-on-warning` on 5.3 settles annotations vs attributes; every "planned" line in `docs/DEV_ENVIRONMENT.md` is now "verified". |
| **M1 Foundations and setup** | Requirements `HOL`, `CAL`, `ACC`, `OWN`, `DAT`, `PRV` agreed first; then, test first: all tables in `install.xml` and an `upgrade.php` that returns true; capability, access class, top-bar link and its session cache; school year, holidays, evaluations and timetable pages; PHP calendar and holiday parser; events skeleton, `user_deleted` observer and privacy provider; plugin generator. | The link appears only for qualifying teachers; every query is scoped by owner; the PHP holiday parser agrees with the Python one on `test-data/holidays-centre-2026-27.txt`; PHPUnit covers calendar, parser, access, ownership and privacy; the unique index on the nullable `planitemid` behaves the same on PostgreSQL and MariaDB. |
| **M2 Import, approve and lock** | Requirements `PLN`, `IMP`, `LCK` agreed first; then, test first: PHP `plan_parser`; paste/upload form with size and day limits; preview with errors and warnings; approval with confirmation; read-only plan view; `plan_imported` and `plan_approved` events. | The PHP parser gives the same results as `tools/check_plan.py` on all 14 checks; approval is blocked by errors and needs confirmation of warnings; a locked plan rejects every change path; plan text is rendered safely; import and approval are one transaction. **Checkpoint: you try it with your real plans.** |
| **M3 Log** | Requirements `LOG` and `HIS` written and agreed first. Entry service (create, update, delete), history, late flag, pending list, carry-over, unplanned sessions, stale-save check, events, list/editor/history pages. | Every rule under "Rules that need tests" in section 3 has a passing test; a teacher can log, backfill, edit and delete in one Behat flow; history is complete after every kind of write. |
| **M4 Export** | Requirements `EXP` written and agreed first. Exporter with the two sheets; summaries overall, per RA or block and per evaluation; safe filename. | Every number in the export equals the number shown on screen; formula and URL payloads stay text on 4.5 and 5.3; the file opens in LibreOffice. |
| **M5 Hardening and release** | Requirements `REL` written and agreed first. Full Behat flow; Catalan and Spanish strings with install notes; `README.md`, `CHANGES.md`, privacy statement; zero coding-standard warnings; release workflow dry run; maturity raised. | A zip built with `git archive` installs on both stacks and uninstalls cleanly; privacy export and deletion work end to end; all checklists in `docs/MOODLE_STANDARDS.md` ticked. |
| **v2 Adjustments** | Move or cancel a session as an overlay on the locked plan, with reason; optional reviewer access. | Planned later, with its own phases and review rounds. |

### 6.3 What each review round looks at (on top of the three lenses)

| Round | Extra focus |
|---|---|
| **R0** | Every command in `docs/DEV_ENVIRONMENT.md` was actually run and works; CI file is correct and not just green by accident (a deliberately failing test turns it red); no secrets in the repository; `export-ignore` really keeps `docs/`, `prototype/`, `tools/` and `test-data/` out of `git archive`; `version.php` fields. |
| **R1** | **Visibility matrix:** teacher, teacher in two courses, student, guest, admin, teacher with a suspended enrolment, teacher whose role is removed while logged in. Install, upgrade and uninstall on PostgreSQL and MariaDB. Cost of the top-bar hook on every page (number of queries) and its behaviour during installation. Schema against the plan's data model. IDOR attempts on every setup page, with a same-capability teacher and with an admin. Holiday parser differential test against the Python one. |
| **R2** | **Differential fuzzing:** generate random plans (CRLF, BOM, tabs, Unicode headings, code fences, very long lines, huge text) and compare the PHP parser with `tools/check_plan.py`; any difference is a bug in one of them. **Lock bypass:** direct service calls, tampered form fields, two simultaneous approvals. XSS payloads inside plan Markdown. Size and day limits. Rollback after an injected failure. Correctness of the "copy instructions for my AI assistant" text. Your sign-off on real plans. |
| **R3** | Late flag around midnight, across DST changes and when the user's time zone changes. Every write path writes history; deleting an entry keeps it. Stale save from a second browser session. Event order relative to commit. Pending list against holidays, today and future days. Two days on the same date. Entries on a locked plan. IDOR and mass-id requests. "Log in as" (`realuserid`). Deleting the user. |
| **R4** | Recompute every summary figure independently (a small script reads the XLSX and the database). Formula and URL payloads on 4.5 and 5.3. Empty plan, plan with no entries, RA with no days, evaluation boundary dates, decimal hours, a large plan (three plans of about 400 days), filename injection. |
| **R5** | Full audit with all lenses over the whole code base. Fresh install from the release zip on both stacks, upgrade, uninstall. Privacy export and delete end to end. Basic accessibility (keyboard, labels, contrast). A reader who has not seen the project follows `README.md` from scratch. Your sign-off. |

## 7. Risks

| Risk | Mitigation |
|---|---|
| Teachers writing the plan in a slightly different shape | Forgiving parser (both date formats, optional parts) and a clear preview of errors and warnings; the AI-assistant instructions button keeps plans in the format. |
| XLSX reading (only if added later) | PhpSpreadsheet differs across 1.29, 4.1, 5.2: stable calls only and a fixture file read in CI on each version. |
| Teachers cannot see the link | Course-level capability check, verified on a real teacher account in M1; role-setting fallback. |
| 5.x layout in `moodle-docker` | The image selects the `public/` root itself; confirm in M0 (use `public/` paths for init and run commands). |
| PHPUnit 9.6 (4.5) vs 11.5 (5.3) | Annotations and static providers; the first CI run on 5.3 settles `failOnDeprecation`; fallback is a plugin `phpunit.xml` or one branch per Moodle series. |
| Export text differs between 4.5.0–4.5.7 and later (`'` prefix) | Convert bullets to "•", compare visible cell text on both versions in M4. |
| 4.5 is past bug-fix support | 4.5 stays the minimum because it is your server; CI runs 5.3 from the start. |
| Scope creep | Locked plan plus a later overlay keeps v1 small. |
| Admins can read the database | README privacy statement; v1 does not encrypt. |

## 8. Decisions

Decided:
1. Moodle 4.5 minimum, also tested on 5.3 LTS.
2. The teacher approves and locks their own plan.
3. Data is private to the teacher.
4. Catalan and Spanish translations (shipped outside the plugin folder, see section 3), English as base.
5. Holidays are shared by all courses in a school year.
6. There is no inspector template; we design the export.
7. The plan is Markdown with three levels: `#` module (code and text), `##` RA or block, `###` day (date in the heading, free Markdown below with bullets and so on). One module per plan. No table, no XLSX import in v1.
8. The work is split into phases with a review round after each (coherence, errors, bug hunting), and a phase does not start until the previous round is closed (section 6). One exception, to work in parallel: pure-logic code of a later phase (parsers, calendar) may be written test-first on its own branch while an earlier phase is being finished; it is merged only after the earlier review round closes, and only for requirements already agreed.
9. Spec-driven and test-driven: each phase starts with agreed requirements (`docs/spec/REQUIREMENTS.md`), code is written test first, every requirement is proved by a test that cites its id, and Behat covers about eight end-to-end teacher journeys (`docs/TESTING_STRATEGY.md`).

The sample of real plan text that was needed is covered: `test-data/` holds three real plans from your CFGS material (section 10). What remains for M2 is your sign-off after trying the importer on your own plans (R2).

## 9. Review log (2026-10-18)

Problems found in the first version of this plan and how they were fixed:

| Problem | Fix |
|---|---|
| Plan was "locked" but planned dates were derived live from the calendar, so editing holidays would silently move an approved plan. | First fixed by snapshotting dates at approval; now solved more simply because each day's date is written in the plan text and frozen with it (section 3). |
| Planned `lang/ca` and `lang/es` inside the plugin: Moodle ignores them. | English only in the plugin; other languages via `moodledata/lang/<lang>_local/` or AMOS. |
| Planned a system-level capability check, which fails for course-level teachers; suggested helper did not exist. | Course-level check with `get_user_capability_course()`, session-cached, plus a fallback. |
| Security, privacy and events came only at the end (M5). | Moved into M1 as a baseline, with permission tests. |
| No events, no ownership rules, no upload limits, no formula-injection rule, no renderer or templates, no `version.php` details. | Added in section 3. |
| CRUD semantics were undefined (delete, concurrent edits). | Defined: deletes allowed with confirmation and kept in history; stale saves refused. |
| History table could not hold all fields or survive deletions. | Full JSON snapshot, independent of the entry. |
| `courseid`/`groupid` on plans had no use. | Kept only an optional `courseid`; group dropped. |
| Hook callback described as `add()`; real method is `add_node(navigation_node::create(...))`. | Corrected. |
| CI plan lacked PHP 8.1 and the coding-standards tool. | M0 covers PHP 8.1 on 4.5 and `moodle-plugin-ci`. |
| Worry that table names were too long. | Checked: limit is 53 characters, no change needed. |

Later change (2026-10-18): the plan import moved from XLSX to Markdown sections. That removes the file upload, the PhpSpreadsheet version differences and the column-mapping step, and makes the plan easy to write with an AI assistant. The approval snapshot is no longer needed because dates now come from the plan text.

Later change (2026-10-18): heading levels fixed as `#` module (code and text), `##` RA or block, `###` day. The export summary also reports coverage per RA or block.

## 10. Test data and real-world findings (2026-10-18)

Real material from `~/CFGS` was turned into a test dataset for the importer:

| File | What it is |
|---|---|
| `test-data/plans/0372.md`, `0374.md`, `0377.md` | Three real module plans (60, 61 and 27 class days) in the importer format, generated from your dated guides. 0372 is Mon+Fri 2 h, 0374 Mon+Thu 2 h, 0377 Tue 1 h. |
| `test-data/holidays-centre-2026-27.txt` | The Jaume Huguet school calendar as holiday lines: 14 single dates and 2 periods (Nadal, Setmana Santa). Centre notices are kept as comments. |
| `test-data/cases/*.md` + `expected.json` | 11 small hand-made files for every error and warning, and for the odd formats (weekday prefixes, `[1,5 h]`, headings inside code fences, two days on one date). |
| `test-data/expected.json` | What the importer must find in each real plan (module, blocks, days, hours, first and last date, no errors, no warnings). |
| `tools/build_test_data.py` | Regenerates the files above from `~/CFGS`. Generated files are committed, so tests do not depend on that repository. |
| `tools/check_plan.py` | Reference parser, the executable spec. `python3 tools/check_plan.py --selftest` runs all 14 checks (all pass). |

What the real data showed:
- **All three real plans are clean:** every day falls on a class weekday, outside holidays and inside the school year.
- **Module names contain a space (`MP 0372`)**, which broke the first version of the module-code rule. Fixed in section 4.
- **Dates and weekdays are written like `dl 05/10/2026`** in your guides, so the importer accepts a weekday prefix and checks it.
- **Shared holidays are confirmed:** one school calendar covers all three modules.
- **You already track plan vs done** in generated Word sheets: one row per class day with Realitzat, Fet/Parcial/No fet, and "Desviació · motiu · mesura". As of today all three sheets are empty and 9 class days of 0372 have passed with nothing marked. The README itself warns that regenerating a sheet blanks it. The plugin replaces that sheet: same columns (plus a history, a late flag and no risk of overwriting), and it adds the `measure` field and per-evaluation summary above.
- **Known gaps in the plans:** 0374 has 66 planned sessions but only 61 dates, so S62 to S66 are not in the plan file; a plan with sessions that have no date cannot be expressed in this format. Replanning (moving the whole plan later, as in 0372's block A) is the v2 adjustments feature.
- **Centre notices** ("Jornada Joves i Empresa", audits) are days with class but a different activity. They are not warnings in v1; they could become information-level hints in v2.

## 11. Changes from the standards review (2026-10-18)

Three agents read `mod_attendance`, `mod_h5pactivity` (core) and `local_ai_manager` and wrote 105 rules into `docs/moodle-rules/`. Corrections they forced on this plan (all applied above):

| Finding | Change |
|---|---|
| Neither reference plugin has a root `renderer.php`; they use `classes/output/` templatables. An empty `lib.php` is not required. | Both removed from the layout. |
| `MOODLE_INTERNAL` is enforced by a phpcs sniff: warns on single-class files and `db/upgrade.php`, required on files with side effects. | Replaced "non-class files" with the real rule. |
| `version.php` needs `release` and `supported`. | Added; `requires` stays 4.5.0. |
| Capability: nobody uses `get_user_capability_course()` for gating; admins pass every check; never grant to archetype `user`; `contextlevel` does not affect checks; cache only the link. | Capability bullet rewritten, tests with admin and suspended enrolment. |
| `write_string()` forces text on all 4.5.x; the formula prefix arrived about 4.5.8. | Export rule refined, cross-version test in M4. |
| Privacy in the user context is confirmed; history, Markdown and a `user_deleted` observer must be covered; `provider_testcase` tests. | Privacy bullet rewritten, `db/events.php` and `observers.php` added. |
| `moodle-docker` handles 5.x by itself; the plan's custom-image fallback is unnecessary. | Risk and M0 updated. |
| PHPUnit 9.6 vs 11.5: annotations, static providers; `failOnDeprecation` unresolved. | Test rules added; settled by the first CI run (M0). |
| CI list missed `phpmd`, `validate`, `--max-warnings 0`, `--fail-on-warning`, PHP 8.4, Behat tags. | CI bullet rewritten. |
| Strings are sorted by `strcmp` byte order, not "alphabetical". | Corrected. |
| Unique constraints should be indexes; the nullable `planitemid` index needs checking on both databases. | Data model and M1 check updated. |
| Events must be triggered after commit and carry no free text; the history row belongs in the same transaction. | Rules added. |
| Nobody else has the two-tab `timemodified` check or admin-configurable size limits. | Kept (cheap), but written without a reference to copy. |
