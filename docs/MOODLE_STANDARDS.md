# Moodle standards for `local_classprogress`

One-page summary of the rules we follow, extracted on 2026-10-18 from real, well-maintained plugin code. The three detail files hold every rule with its reason, a code snippet and a `file:line` source:

| File | Covers | Rules |
|---|---|---|
| [`moodle-rules/01-architecture-and-style.md`](moodle-rules/01-architecture-and-style.md) | Layout, namespaces, pages, hooks, DI, templates, forms, XMLDB, strings, settings, caches, code style | 41 |
| [`moodle-rules/02-security-and-privacy.md`](moodle-rules/02-security-and-privacy.md) | Capabilities, ownership, input, CSRF, output escaping, SQL, transactions, Excel safety, events, Privacy API | 34 |
| [`moodle-rules/03-testing-ci-and-release.md`](moodle-rules/03-testing-ci-and-release.md) | PHPUnit, Behat, `moodle-plugin-ci`, GitHub Actions, `moodle-docker`, docs, versioning, Marketplace release | 30 |

**Sources read:** `mod_h5pactivity` from Moodle core (4.5 and 5.3 branches), `mod_attendance` (23,000+ installs), `local_ai_manager` (modern local plugin with hooks and DI), plus `block_rss_client`, core calendar, `moodle-plugin-ci`, `moodle-cs`, `moodle-docker`, `moodle-php-apache` and moodledev.io. None of them is a "private, per-teacher, cross-course" plugin, so a few rules (ownership tests, optimistic locking, size limits) are our own design and are marked as such in the detail files.

**Spot-checked by hand (not just taken from the agents):** PHPUnit 9.6.34 on 4.5 and 11.5.56 on 5.3; `get_user_capability_course($capability, $userid, $doanything, $fieldsexceptid, $orderby, $limit)`; `write_string()` at v4.5.0 has no formula escaping while 4.5.15 has; the `moodle-php-apache` image picks `public/` as document root automatically.

---

## 1. The ten rules that matter most

1. **Every page starts the same way:** `require_once(__DIR__ . '/../../config.php'); require_login();` then the access check, then PARAM-typed params, then `$PAGE` url, context and title. The same relative path works on 4.5 and on 5.x (`public/config.php` is a loader).
2. **A capability is not ownership.** Admins pass every capability check. Put the owner in the WHERE of every query, and test "user B cannot touch user A" with a same-capability teacher and an admin.
3. **Capability:** `captype` write, `RISK_PERSONAL`, archetypes `editingteacher`, `teacher`, `manager`; never `user` (students would get in). Gate with `get_user_capability_course($cap, null, true, '', '', 1)`. Cache only the decision to show the link, never the enforcement.
4. **Writes go through a `moodleform`** (sesskey and `setType` included) or `confirm && confirm_sesskey()` after a confirmation page. Redirect after a successful POST.
5. **Output:** `s()` for plain text, `format_string()` for titles, `format_text($t, FORMAT_MARKDOWN, ['context' => $ctx])` for Markdown (always cleaned). Never `noclean`. `{{ }}` in templates; `{{{ }}}` only for URLs or text already through `format_text()`.
6. **SQL:** `$DB` methods with placeholders, `get_in_or_equal()` for lists. Multi-table writes in one `start_delegated_transaction()`; write the history row in the same transaction; trigger events after commit with ids only.
7. **Excel:** `write_string()` for every teacher-written cell, never `write()` (it turns `=…` into formulas). Text type is guaranteed on all 4.5.x; releases from about 4.5.8 also prefix `'`, so convert Markdown bullets to "•" and compare visible text on both versions.
8. **Privacy:** implement all three interfaces (`null_provider` is not allowed), in the teacher's user context, covering history too. Add a `user_deleted` observer. Test with `provider_testcase`.
9. **Structure:** logic in namespaced classes under `classes/`, pages only wire things together, hook registered in `db/hooks.php` with a cheap, install-safe callback. No root `renderer.php`; use `classes/output/` templatables plus Mustache. No custom JavaScript in v1.
10. **CI from day one:** `moodle-plugin-ci` with `phpcs --max-warnings 0`, `phpdoc`, `validate`, `savepoints`, `mustache`, `phpunit --fail-on-warning`, Behat; matrix PHP 8.1 and 8.3 on 4.5, PHP 8.3 and 8.4 on 5.3, PostgreSQL 17 and MariaDB 11.

## 2. Traps that differ between 4.5 and 5.x

| Topic | 4.5 | 5.3 |
|---|---|---|
| PHP | 8.1–8.3 (write for 8.1) | ≥ 8.3 |
| Code location | `local/classprogress` | `public/local/classprogress`; init and run commands use `public/admin/tool/...` |
| PHPUnit | 9.6: docblock annotations, no attributes | 11.5: attributes preferred, annotations deprecated |
| Excel formula prefix | none before about 4.5.8 | `'` prefix on text starting with `= + - @` |
| URL class | `\core\url` exists; `moodle_url` is an alias | `\core\url` preferred |
| Plugin upgrade notes | `upgrade.txt` replaced by `UPGRADING.md` in core | same; a local plugin needs neither |
| Core `requires` | core bumps its own per branch; **we never copy that**, `requires = 2024100700` | |

Our rule: no attributes in tests, no 5.x-only page API (`set_show_navigation_footer()` etc.), `supported = [405, 503]`.

## 3. Style and structure checklist (use on every pull request)

- [ ] GPL header and `@package local_classprogress` on every PHP file; PHPDoc on every class, method, constant; typed parameters and return types; `else if`; `#[\Override]` instead of `@inheritdoc`.
- [ ] `defined('MOODLE_INTERNAL') || die();` only where the sniff wants it: files with side effects or several artifacts (`db/access.php`, `hooks.php`, `caches.php`, `events.php`, `settings.php`, class files with `require_once`). Not in single-class files, not in `db/upgrade.php`, not in `lang/` or Behat files.
- [ ] `version.php`: `component`, bumped `version`, `requires`, `supported`, semver `release`, `maturity`. Any change to `db/hooks.php`, `caches.php`, `access.php`, `events.php` bumps the version.
- [ ] New table or field: `install.xml` plus an `upgrade.php` step with a `field_exists`/`table_exists` guard and a savepoint equal to the new version.
- [ ] Strings only in `lang/en`, sorted by `strcmp` byte order, with `_desc`, `cachedef_` and `privacy:metadata:` keys. No concatenated fragments.
- [ ] Unique constraints as unique indexes (not keys) unless an FK target; key names like `planid-sessiondate`.
- [ ] Templates have the `@template` docblock with an example context; `export_for_template()` returns only scalars, arrays and `stdClass`.
- [ ] No `unserialize`, `print_r`, `error_log`, `extract`, `sizeof`; errors are `moodle_exception` with a lang string id.
- [ ] In `@param` lines use `float[]`, `int[]`, `holiday_entry[]` or `array`, never generics with a space such as `array<int, float>`: Moodle's PHPDoc checker splits the type at the space and reports "incomplete parameters list" (found when merging the calendar, 2026-10-18). Run `moodle-plugin-ci phpdoc` before merging; phpcs alone does not catch it.
- [ ] Settings in `settings.php` inside `if ($hassiteconfig)` and `if ($ADMIN->fulltree)`, named `local_classprogress/<key>`, with the right PARAM type.

## 4. Security checklist

**Every page**
- [ ] `require_login()` then `access::require_user()`; explicit page context.
- [ ] Params via `required_param`/`optional_param` with the narrowest PARAM type; no superglobals; no `userid` parameter for "whose data".
- [ ] Every id loaded with the owner in the condition; id lists count-compared.
- [ ] Writes through a form, or `confirm && confirm_sesskey()`; destructive actions have a confirmation page and write history first.
- [ ] Redirect targets built with `moodle_url`/`\core\url`.
- [ ] Export: access check, owner scope, `clean_filename()`, `write_string()`.

**Every service method**
- [ ] Takes `$userid` explicitly and scopes by owner first.
- [ ] Re-validates sizes, dates (strict pattern plus `DateTimeImmutable`), enums, status transitions.
- [ ] Refuses edits to an approved plan.
- [ ] One delegated transaction; history in the same transaction; event after commit with ids only.

**Threats and where they are handled**

| Threat | Handled by |
|---|---|
| Student or guest reaches the pages | `captype` write, no `user` archetype, live capability check on every page |
| Teacher B reads or edits teacher A's data | Owner in every query; tests with a same-capability teacher and an admin |
| CSRF | Form sesskey; `confirm_sesskey()` on link actions |
| Stored XSS in Markdown | `format_text(FORMAT_MARKDOWN)` cleaning; `s()` elsewhere; no `noclean` |
| SQL injection | Placeholders, `get_in_or_equal()` |
| Half-saved import or approval | One delegated transaction |
| Formula injection in XLSX | `write_string()` only; cross-version export test |
| Oversized pasted plan | Form and service size and day limits |
| Plan text in logs | Events carry ids and codes only |
| Data left after account deletion | Privacy provider in user context, `user_deleted` observer, provider tests |

## 5. Testing checklist

- [ ] Test written before the code, tagged `@group REQ-…` with the requirement it proves (Behat: `@REQ-…`); `python3 tools/check_traceability.py --strict M<n>` passes. Method: `docs/TESTING_STRATEGY.md`.
- [ ] `final class x_test extends \advanced_testcase` (or `\basic_testcase` when no database is touched), `@covers`, `: void`, `resetAfterTest()` inside each test, `public static` data providers, no attributes.
- [ ] Plugin generator in `tests/generator/lib.php`; Behat generator and `behat_local_classprogress.php`.
- [ ] First tests to write: calendar and holiday parser; plan parser against `test-data/` (one provider per expected-results JSON); services (import, approve, entry save and delete, history, late flag, stale save); access and ownership; exporter; events with `redirectEvents()`; privacy provider; one Behat flow tagged `@local @local_classprogress` (teacher sees the link, imports, approves, logs, exports; student has no link).
- [ ] The hook callback is tested through the predicate it calls (`access::qualifies()`), because no plugin tests the callback directly.
- [ ] Fixtures: copy `test-data/` into `tests/fixtures/` with a sync script; check the copy equals the source before release.

## 6. Release checklist

- [ ] `README.md` (what it does, requirements, install, translations into `moodledata/lang/<lang>_local/`, privacy statement, limits) and `CHANGES.md` (newest first).
- [ ] CI green on all four matrix jobs.
- [ ] Fresh install and upgrade tried in `moodle-docker` on 4.5 (PHP 8.1) and 5.3 (PHP 8.3).
- [ ] Public repository and issue tracker, screenshots, GPL v3+ headers, privacy API implemented; tag `vX.Y.Z` with the Marketplace release workflow and secret.

## 7. Open points the plugins did not settle

Each is a named check in `PLAN.md` sections 2 and 6:
1. ~~Does PHPUnit 11.5 on 5.3 accept docblock annotations?~~ Resolved in M0: yes; one runner-deprecation notice per annotated item, which does not fail `--fail-on-warning`.
2. ~~Does `moodle-docker` mount the plugin at `public/local/classprogress` on 5.3?~~ Resolved in M0: yes.
3. Does the capability appear in the role editor with `CONTEXT_COURSE`, and does a teacher with a suspended enrolment still qualify? (M1)
4. Is a unique index on a nullable `planitemid` equivalent on MariaDB and PostgreSQL? (M1)
5. Which page layout (`standard` or `report`) works best on both versions? (M1)
6. Does the `'` formula prefix show a literal apostrophe before text cells starting with `-`? (M4, both versions)
