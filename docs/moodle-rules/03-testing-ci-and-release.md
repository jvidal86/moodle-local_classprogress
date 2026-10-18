# Moodle rules 03: testing, CI, docs and release (`local_classprogress`)

Scope: PHPUnit, Behat, moodle-plugin-ci, GitHub Actions, moodle-docker, README/CHANGES, `version.php`, Marketplace release. Every rule cites code or docs actually read (Oct 2026).
Source shorthand (all shallow clones; short hash = HEAD read):
`att` = danmarsden/moodle-mod_attendance@786991c (main, requires 5.3). `aim` = mebis-lp/moodle-local_ai_manager@b722314 (main, supports 5.0-5.2). `c405` = moodle/moodle MOODLE_405_STABLE@215f443. `c503` = moodle/moodle MOODLE_503_STABLE@344c92d (paths under `public/`). `pci` = moodlehq/moodle-plugin-ci@ed7d50e. `mdock` = moodlehq/moodle-docker@f4c2324. `apache` = moodlehq/moodle-php-apache@86db4fb. `rel` = moodlehq/moodle-plugin-release@7c56bd9. `bycs` = bycs-lp/moodle-ci-workflows@dbc54d0. Doc URLs are on moodledev.io.
Legend: ⚠ = legacy, version-specific (4.5 vs 5.x) or plugins disagree.

## A. PHPUnit

1. **Rule:** Write tests as `final class foo_test extends \advanced_testcase` in `tests/**/foo_test.php`, class name equal to file name, namespace equal to the code under test, `: void` on every test.
   **Why:** Core docs make the suffix and the name match mandatory; the test suite is discovered by file name.
   **Do:** `namespace mod_h5pactivity\local; final class manager_test extends \advanced_testcase {`
   **Source:** c405 `mod/h5pactivity/tests/local/manager_test.php:31`; moodledev.io/docs/5.1/guides/testing ("MUST have a filename ending in `_test.php`", class name "exactly match the filename").

2. **Rule:** Call `$this->resetAfterTest()` inside each test, immediately before the first data creation; never in a shared `setUp()`.
   **Why:** The 5.1 testing guide bans it in `setUp`; it hides which tests dirty the DB.
   **Do:** `$this->resetAfterTest(); $this->setAdminUser(); $course = $this->getDataGenerator()->create_course();`
   **Source:** c503 `public/mod/h5pactivity/tests/event/report_viewed_test.php:54`. ⚠ Disagreement: `aim tests/local/access_manager_test.php:34` and c405 `admin/tool/monitor/tests/privacy/provider_test.php:49` call it in `setUp()`. Follow the docs.

3. **Rule:** ⚠ Use docblock annotations (`@covers`, `@dataProvider`, `@group`), not PHP attributes, because the plugin must run on PHPUnit 9.6 (4.5) and 11.5 (5.3).
   **Why:** 4.5 ships PHPUnit 9.6.34 (no attribute support, so a `#[DataProvider]` test would receive no arguments); 5.3 ships 11.5.56 where annotations are hard-deprecated. moodledev says plugins testing on 4.5 and 5.0+ "cannot use Attributes for testing".
   **Do:** `* @covers \local_classprogress\local\plan_parser` on the class; `* @dataProvider parse_cases` on the test; `public static function parse_cases(): array`.
   **Source:** c405 `composer.lock:1356`; c503 `composer.lock:5575`; c405 `mod/h5pactivity/tests/local/manager_test.php:26,68` (annotations) vs c503 `.../manager_test.php:30,75` (attributes); moodledev.io/general/development/tools/phpunit/upgrading-11. `aim` (supports 5.x only) mixes both: `tests/local/tenant_test.php:29,34` attributes, `tests/manager_test.php:51-52` docblocks, which suggests docblocks still run on PHPUnit 11.
   ⚠ **Unresolved:** c503 `phpunit.xml.dist:9-10` sets `failOnDeprecation="true"`, and `pci src/Command/PHPUnitCommand.php:156-167` falls back to the core `local_classprogress_testsuite` when the plugin has no `phpunit.xml`. Whether PHPUnit 11.5 doc-comment metadata notices fail that run is not settled by anything I read. The first green `phpunit --fail-on-warning` on `MOODLE_503_STABLE` is the test (M0 exit criterion). If it fails: keep annotations and add `<phpunit failOnDeprecation="false">` via a plugin `phpunit.xml`, or branch the plugin per Moodle series (moodledev upgrading-11 recommends a branch per series). Do not mix annotation + attribute on one method without testing.
   **Resolved in M0 (2026-10-18, run on both stacks):** annotations pass on 5.3; PHPUnit 11.5 reports them as *runner* deprecations, which neither core's `failOnDeprecation` nor `--fail-on-warning` turns into a failure. A plugin `phpunit.xml` with `failOnDeprecation="false"` would not change that, and `moodle-plugin-ci` would then use it instead of core's configuration, so do not add one. Annotation plus the matching attribute on the same item was tested: no notices on 11.5, still passes on 9.6.

4. **Rule:** Data providers are `public static`, return non-empty arrays keyed by case name, only define data (no DB, no generators), and positional inner arrays whose count equals the test parameters.
   **Why:** Non-static providers were removed in PHPUnit 11 (static is also fine on 9.6); providers run before the DB is reset; empty providers and mismatched named keys fail or warn.
   **Do:** `public static function is_tracking_enabled_data(): array { return ['Logged student, tracking enabled' => [true, 'student', 1, true, true], ...];}`
   **Source:** c405 `mod/h5pactivity/tests/local/manager_test.php:103-106`; moodledev.io/general/development/tools/phpunit/upgrading-11; testing guide ("Data providers must not instantiate or create data").

5. **Rule:** Put reusable data creation in `tests/generator/lib.php`, class `local_classprogress_generator extends component_generator_base`, and use it from PHPUnit and Behat.
   **Why:** One creation path for plan/entry rows keeps tests short and consistent; Behat reuses it.
   **Do:** `$gen = $this->getDataGenerator()->get_plugin_generator('local_classprogress'); $plan = $gen->create_plan(['userid' => $u->id]);`
   **Source:** `aim tests/generator/lib.php:25,78` (`create_model(array $record = []): stdClass`, defaults merged with `array_merge`); `aim tests/table/userstats_table_test.php:47`; testing guide ("must extend `\component_generator_base`").

6. **Rule:** Test services (not pages): call the service class / external function directly as a given user, and assert both the allowed and denied path.
   **Why:** Pages are thin; the logic and the access rules live in `classes/local/*_service.php`.
   **Do:** `$this->setUser($teacher); $this->expectException(\required_capability_exception::class); remove_session::execute($id);`
   **Source:** `att tests/external/remove_session_test.php:85`; external-function return check `:54` (`external_api::clean_returnvalue(...)`, `core_external\external_api` since 4.2).

7. **Rule:** Test capabilities with course-level roles (`create_and_enrol($course, 'editingteacher')`), plus a positive/negative pair per rule.
   **Why:** PLAN.md section 3 grants access from course-assigned roles via `get_user_capability_course()`; a system-role test would pass for the wrong reason.
   **Do:** `$t = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher'); $s = ...create_and_enrol($course, 'student');`
   **Source:** `aim tests/external/get_prompts_test.php:40` (teacher denied for admin's data, `:52` allowed for a student's).

8. **Rule:** Ownership/IDOR test per service method: user A creates a plan/entry, then `setUser(B)` (also B with the same capability and course) and assert read, edit, delete and export throw or return nothing.
   **Why:** Private-per-teacher data; capability alone must never be enough.
   **Do:** Descriptive: loop over `[get, save_entry, delete_entry, export]` as B with A's ids; `expectException` each; assert A's rows are unchanged in `$DB`. Also assert a missing id and another user's id give the same exception (no existence leak).
   **Source:** pattern combined from `aim get_prompts_test.php:37-66` (pairing) and `att remove_session_test.php:75-90`. ⚠ No real plugin I read has a cross-user ownership test; this one is our own design.

9. **Rule:** Test events with `redirectEvents()` and assert class, context, `objectid`, `other`.
   **Why:** The plan lists five audit events; sink captures them without the logstore.
   **Do:** `$sink = $this->redirectEvents(); $event->trigger(); $events = $sink->get_events(); $this->assertCount(1, $events);`
   **Source:** c503 `public/mod/h5pactivity/tests/event/report_viewed_test.php:96-100`; file layout `tests/event/<name>_test.php`.

10. **Rule:** Test the privacy provider by extending `\core_privacy\tests\provider_testcase` and covering all seven methods (`get_metadata`, `get_contexts_for_userid`, `get_users_in_context`, `export_user_data`, `delete_data_for_all_users_in_context`, `delete_data_for_user`, `delete_data_for_users`).
    **Why:** Required interfaces (`metadata\provider`, `request\plugin\provider`, `core_userlist_provider`) are all exercised; user-context plugin is exactly our case.
    **Do:** `$writer = writer::with_context($usercontext); $this->assertFalse($writer->has_any_data()); provider::export_user_data($approved); $this->assertTrue($writer->has_any_data());`
    **Source:** c405 `admin/tool/monitor/tests/privacy/provider_test.php:42,88,97,138,187,222,289,346` (user context, own tables, plugin generator); c503 `public/mod/h5pactivity/tests/privacy/provider_test.php:45,229` (module-context example). Assert also that user B's rows survive A's deletion.

11. **Rule:** Test the primary-navigation callback by testing the predicate it calls (`access::qualifies($userid)`), and cover link visibility in Behat.
    **Why:** `redirectHook()` intercepts hooks your code dispatches; it does not run your `primary_extend` callback. No plugin I read tests its own callback (`aim` only registers it, `db/hooks.php:30`).
    **Do:** `create_and_enrol(..., 'editingteacher')` vs a plain user, assert `qualifies()`; Behat: teacher sees the link in the primary nav, student does not.
    **Source:** `redirectHook` exists in c405 `lib/phpunit/classes/advanced_testcase.php:500` and c503 `public/lib/phpunit/classes/advanced_testcase.php:460`; `primary_extend` is dispatched from `\core\navigation\views\primary::initialise()` (c405 `lib/classes/navigation/views/primary.php:90`). ⚠ Constructing `new primary_extend($primaryview)` directly needs a `primary` view (needs `$PAGE`); not verified as practical.

12. **Rule:** Keep non-PHPUnit fixture helpers in `tests/fixtures/` and load them with `require_once(__DIR__ . '/../fixtures/x.php')`; fixture data files (`.md`, `.json`) are read with `file_get_contents(__DIR__ . '/fixtures/...')`.
    **Why:** Tests cannot reach outside the plugin directory in CI or in the Marketplace zip.
    **Source:** `aim tests/local/utils_test.php:34`; c503 uses `$CFG->dirroot . '/h5p/tests/fixtures/...'` for binary fixtures (`.../generator/lib.php:57`).

13. **Rule:** After adding or renaming tests/generators, re-run PHPUnit init (`util.php --upgrade` is faster for plugin-only changes); run one plugin with `vendor/bin/phpunit --testsuite local_classprogress_testsuite`.
    **Why:** The component suite is generated at init; `moodle-plugin-ci` also guesses `<component>_testsuite`.
    **Source:** moodledev.io/general/development/tools/phpunit; `pci src/Command/PHPUnitCommand.php:167`.

## B. Behat

14. **Rule:** Put features in `tests/behat/*.feature`, start each file with `@local @local_classprogress`, add `@javascript` only per scenario that needs JS.
    **Why:** `moodle-plugin-ci validate` fails if any feature lacks both tags; non-JS scenarios are much faster.
    **Do:** `@local @local_classprogress` (line 1), then `Scenario:`; `@javascript` above only the scenario that needs it.
    **Source:** `pci src/PluginValidate/Requirements/GenericRequirements.php:74-77` and `AbstractRequirements.php:51-58`; `att tests/behat/preferences.feature:1,26` (`@mod @mod_attendance ...`, `@javascript` per scenario); moodledev.io/general/development/tools/behat/writing.

15. **Rule:** Create data with `Given the following "..." exist:` steps and a Behat generator, enrol the teacher with a course role, and start with `I am on the "X" "local_classprogress > Page" page logged in as "teacher1"`.
    **Why:** The plain "I log in as" lands on the Dashboard, which is "very slow"; generators insert straight into the DB.
    **Do:** `Given the following "course enrolments" exist: | user | course | role | / | teacher1 | C1 | editingteacher |`
    **Source:** c503 `public/mod/h5pactivity/tests/behat/sending_attempt.feature:19,30`; `att tests/behat/preferences.feature:28`.

16. **Rule:** Custom steps go in `tests/behat/behat_local_classprogress.php` (class `behat_local_classprogress extends behat_base`); named pages via `resolve_page_url()` (no instance) or `resolve_page_instance_url()`; entity generators in `tests/generator/behat_local_classprogress_generator.php` extending `behat_generator_base` with `get_creatable_entities()`.
    **Why:** Lets features say `the following "local_classprogress > plans" exist:` and `"local_classprogress > Plan"` pages.
    **Do:** `'plans' => ['singular' => 'plan', 'datagenerator' => 'plan', 'required' => ['user'], 'switchids' => ['user' => 'userid']]`
    **Source:** c503 `public/mod/h5pactivity/tests/generator/behat_mod_h5pactivity_generator.php:27-33`; `att tests/behat/behat_mod_attendance.php:27,39,52`. The `require_once(__DIR__ . '/../../../../lib/behat/behat_base.php')` path (att `:27`, four levels up) is the same depth for `local/classprogress/tests/behat` in both layouts, since `public/lib/behat/behat_base.php` exists in 5.3. That file must not contain a `MOODLE_INTERNAL` guard (att `:25` comment: Behat may load it before `config.php`). I did not find a local plugin with `resolve_page_url`; method names are from moodledev.

## C. moodle-plugin-ci

17. **Rule:** Run the full check list in this order: `phplint`, `phpmd` (non-blocking), `phpcs --max-warnings 0`, `phpdoc --max-warnings 0`, `validate`, `savepoints`, `mustache`, `grunt --max-lint-warnings 0`, `phpunit --fail-on-warning`, `behat --profile chrome --scss-deprecations`.
    **Why:** This is the canonical step list the Marketplace pre-checks mirror (phplint, phpcs, js, css, phpdoc, savepoint, thirdparty, grunt, mustache).
    **Source:** `pci gha.dist.yml:72-112`; moodledev.io/general/community/plugincontribution/codeprechecks. ⚠ `pci docs/GHAFileExplained.md` still shows an older template (PHP 7.4-8.1, `ubuntu-22.04`, no `--fail-on-warning`); trust `gha.dist.yml`. `att .github/workflows/ci.yml` omits the `mustache` step.

18. **Rule:** Keep the `mustache` step (we ship templates) and keep `grunt` in the workflow even though v1 has no custom JS.
    **Why:** `mustache` lints our templates; `grunt` is a cheap no-op without `amd/` and starts checking automatically if JS is added.
    **Source:** `pci gha.dist.yml:97-103`.

19. **Rule:** `validate` needs `version.php` and `lang/en/local_classprogress.php` (with `pluginname`); `db/install.xml` table names must contain the full component name; a missing `db/upgrade.php` is only a warning ("Skipping validation of missing or optional file").
    **Why:** A fresh plugin with no upgrade file passes; once `upgrade.php` exists it must define `xmldb_local_classprogress_upgrade`.
    **Source:** `pci src/PluginValidate/Requirements/GenericRequirements.php:29-72`; `pci src/PluginValidate/PluginValidate.php:156`.

20. **Rule:** Ignore only generated/third-party paths, through `thirdpartylibs.xml` or `IGNORE_PATHS`/`IGNORE_NAMES` (per-command `PHPUNIT_IGNORE_PATHS`, `CODECHECKER_IGNORE_*`, `MUSTACHE_IGNORE_NAMES`); a per-command variable fully replaces the global one.
    **Source:** `pci docs/IgnoringFiles.md:6-15,50-86`. `thirdpartylibs.xml` is needed only if we bundle a library; format `att thirdpartylibs.xml:1-10`.

21. **Rule:** `moodle-plugin-ci` resolves the 5.1+ `public/` layout itself; install with `--plugin ./plugin` and set `MOODLE_BRANCH=MOODLE_503_STABLE`, nothing else.
    **Why:** Detection is `file_exists(<moodle>/public/version.php)`, and the plugin dir is computed from core's plugin types for branch >= 500.
    **Source:** `pci src/Bridge/Moodle.php:47-50,120`; `pci docs/CHANGELOG.md:50` ("support for public directory structure in 5.1", 4.5.8).

## D. GitHub Actions workflow

22. **Rule:** Matrix by `include:` (not cross product); PHP 8.1 and 8.3 on 4.5, PHP 8.3 and 8.4 on 5.3; databases pgsql and mariadb; service images `postgres:17`, `mariadb:11`.
    **Why:** 4.5 supports PHP 8.1-8.3, 5.3 supports 8.3-8.4 (docs.moodle.org/503/en/PHP); 5.3 requires Postgres 17, MariaDB 11.4, PHP >= 8.3, and those images also satisfy 4.5 (needs Postgres 13, MariaDB 10.6.7).
    **Source:** c503 `public/admin/environment.xml:5316-5329` (5.3 block: Postgres 17 at `:5325`, `<PHP version="8.3.0">` at `:5329`); c405 `admin/environment.xml:4514-4527`; `pci docs/CHANGELOG.md:15-16`. Base file `pci gha.dist.yml:12-38`, `att ci.yml:34-37`.

    Proposed `.github/workflows/ci.yml` (adapted from `pci gha.dist.yml`, only the matrix and `permissions` differ; steps as rule 17):
    ```yaml
    name: Moodle Plugin CI
    on: [push, pull_request]
    permissions: { contents: read }
    jobs:
      test:
        runs-on: ubuntu-24.04
        services: # copy postgres:17 + mariadb:11 blocks from gha.dist.yml:12-30
        strategy:
          fail-fast: false
          matrix:
            include:
              - { php: '8.1', moodle-branch: 'MOODLE_405_STABLE', database: pgsql }
              - { php: '8.3', moodle-branch: 'MOODLE_405_STABLE', database: mariadb }
              - { php: '8.3', moodle-branch: 'MOODLE_503_STABLE', database: pgsql }
              - { php: '8.4', moodle-branch: 'MOODLE_503_STABLE', database: mariadb }
        # steps: checkout (path: plugin), setup-php, "composer create-project ... moodle-plugin-ci ci ^4",
        # install --plugin ./plugin --db-host=127.0.0.1, then rule-17 steps, Behat faildump upload
    ```
    Add `ini-values: max_input_vars=5000, opcache.enable_cli=1, opcache.jit=disable` (gha.dist.yml:51; `att` drops `opcache.jit=disable`). Optional: `bycs` derives branches from `$plugin->supported` and `aim .github/workflows/ci.yml:7` just calls it (`bycs .github/workflows/moodle-plugin-ci.yml:32`); not needed for two fixed branches.

23. **Rule:** Release job triggered by tag, using the Moodle HQ reusable workflow with a `MOODLE_MARKETPLACE_TOKEN` secret.
    **Why:** It builds the zip with `git archive`, reads `$plugin->component` from `version.php`, takes release notes from the GitHub Release body, else `CHANGES.md`/`CHANGELOG.md`/`UPGRADING.md` in the root.
    **Do:** `on: push: tags: ['v*']` then `uses: moodlehq/moodle-plugin-release/.github/workflows/moodle-release.yml@main` with `secrets: MOODLE_MARKETPLACE_TOKEN`.
    **Source:** `rel README.md:6-30,38-46`; `rel .github/workflows/moodle-release.yml:64,74`; `aim .github/workflows/moodle-release.yml` (wraps it via `bycs`).

## E. Local runs with moodle-docker

24. **Rule:** Use moodle-docker per Moodle checkout; for 5.x it works without changes.
    **Why:** The `moodlehq/moodle-php-apache` image sets Apache's DocumentRoot to `/var/www/html/public` automatically when that directory exists (unless `APACHE_DOCUMENT_ROOT` is set). `mdock/base.yml:3-7` mounts `MOODLE_DOCKER_WWWROOT` at `/var/www/html` and sets no `APACHE_DOCUMENT_ROOT`.
    **Do:**
    ```bash
    export MOODLE_DOCKER_WWWROOT=~/moodle503 MOODLE_DOCKER_DB=pgsql MOODLE_DOCKER_PHP_VERSION=8.3
    cp config.docker-template.php $MOODLE_DOCKER_WWWROOT/config.php   # config.php stays at the repo root in 5.x
    bin/moodle-docker-compose up -d && bin/moodle-docker-wait-for-db
    ```
    **Source:** `apache root/system-docker-entrypoint.d/wwwroot.sh:8-18` (detection) and `Dockerfile:49,58-64` (entrypoint, sed on vhosts); `mdock README.md:20-35,209-232` (`MOODLE_DOCKER_PHP_VERSION` default 8.3, allowed 8.4-8.1; `MOODLE_DOCKER_DB_VERSION` defaults pgsql 17, mariadb 11.4; `MOODLE_DOCKER_BROWSER` default firefox, set `chrome` to match CI's `--profile chrome`).
    ⚠ The README never mentions `public/`. Its commands (`mdock README.md:73,77,100,105`: `php admin/tool/phpunit/cli/init.php`) are 4.x paths; in 5.x use `public/admin/tool/phpunit/cli/init.php`, `public/admin/tool/behat/cli/init.php` (moodledev PHPUnit page uses `public/` paths; `pci` uses `<public>/admin/tool/...`, `TestSuiteInstaller.php:46,107`). Image behaviour verified from its source, not run.

25. **Rule:** Mount the plugin repo into the Moodle tree with a `local.yml` volume, not a symlink: `/var/www/html/local/classprogress` (4.5) or `/var/www/html/public/local/classprogress` (5.x).
    **Why:** Docker bind mounts do not follow host symlinks; Moodle installs `local_` plugins under `public/local` from 5.1 (`pci Moodle.php:120` uses core's plugin type dirs for branch >= 500).
    **Do:** `services: { webserver: { volumes: ["/home/me/classprogress:/var/www/html/public/local/classprogress"] } }`
    **Source:** `mdock README.md:247-266` (local.yml mechanism). The exact mount is derived, not tested: verify in M0 (this also settles PLAN.md section 2 "public/local").

26. **Rule:** Run tests inside the webserver container: `bin/moodle-docker-compose exec webserver php [public/]admin/tool/phpunit/cli/init.php`, then `vendor/bin/phpunit --testsuite local_classprogress_testsuite` (`vendor/` stays at the repo root); Behat: `exec -u www-data webserver php [public/]admin/tool/behat/cli/run.php --tags=@local_classprogress`.
    **Source:** `mdock README.md:69-80,96-106`; behat/phpunit configs are in `mdock config.docker-template.php:68-83`.

## F. Docs, versioning, release

27. **Rule:** `version.php`: `component`, `version` (YYYYMMDDXX, increases every release), `requires`, `supported`, `release`, `maturity`.
    **Why:** `supported = [405, 503]` also documents the tested range and `bycs` derives the CI branches from it.
    **Do:**
    ```php
    $plugin->component = 'local_classprogress';
    $plugin->version   = 2026101800;   $plugin->requires  = 2024100700; // Moodle 4.5.0
    $plugin->supported = [405, 503];   $plugin->release   = '0.1.0';
    $plugin->maturity  = MATURITY_ALPHA;
    ```
    **Source:** `aim version.php:27-32` (`release` is semver, `maturity` STABLE, `supported = [500, 502]`); `att version.php:26-32`. ⚠ `att` sets `release` to the version number (2026090300) and `supported = [503, 503]`; `aim` uses semver strings. Prefer semver. moodledev.io/docs/5.1/apis/commonfiles/version.php (format, `MATURITY_*`). PLAN.md omits `supported`.

28. **Rule:** Ship `README.md` (what it does, requirements, install incl. translations to `moodledata/lang/<lang>_local/`, privacy statement, limits) and `CHANGES.md` (newest first; the release workflow uses it as release notes).
    **Why:** The Marketplace zip needs `version.php` and `README.md|txt`; admins read the privacy statement.
    **Source:** docs.moodle.org/dev/Plugin_validation (zip, `README.md`); `rel README.md:38-46` (CHANGES.md fallback); moodledev.io/general/community/plugincontribution/checklist (README lists post-install steps and DB limits; describe processed personal data). `att README.md` has headings ABOUT/PURPOSE/DOCUMENTATION only; `aim README.md` a feature list. Neither ships CHANGES; ⚠ docs say it pre-fills the release notes (docs.moodle.org/dev/Plugin_files, marked outdated).

29. **Rule:** Optional `.gitattributes` with `export-ignore` for dev-only paths (`.github`, `docs/`, `test-data` if not needed by tests).
    **Why:** The release zip is `git archive HEAD`, which honours `export-ignore`.
    **Source:** `rel .github/workflows/moodle-release.yml:74`. ⚠ Neither `att` nor `aim` has a `.gitattributes`; this is our choice, not a convention. Keep `tests/` and fixtures in the zip.

30. **Rule:** Marketplace requirements: public source repo and public bug tracker (GitHub Issues OK), GPL v3+ header on every PHP file, English-only `lang/en`, strings as plain data (no concatenation), screenshots, privacy API implemented, `thirdpartylibs.xml` for bundled libraries (GPL-compatible), unique frankenstyle name.
    **Why:** Automatic checks run on every version; manual review only on the first release (clean install, no duplicate of core, English docs).
    **Source:** moodledev.io/general/community/plugincontribution/checklist; docs.moodle.org/dev/Plugin_validation; codeprechecks page ("tests typically do not check functionality, security or correctness"). Paid-plugin review (2-4 weeks) is from the Marketplace FAQ search result, not read in full; ⚠ the checklist page is flagged "legacy".

## G. Proposed `tests/` tree (write in this order)

```
tests/
  calendar_test.php             # holidays x timetable -> sessions; weekday numbering; holiday ranges; dd/mm/yyyy vs ISO
  holiday_parser_test.php       # 'date to date label' lines, bad ranges skipped (port of parse_holidays)
  local/plan_parser_test.php    # parity with Python: provider per fixtures/plans/*.md and fixtures/cases/*.md
  local/plan_service_test.php   # import draft, approve+lock, locked plan rejects item/slot/source edits, transaction rollback
  local/entry_service_test.php  # create/update/delete, late flag via writtenon, history row on every save and delete, stale timemodified refused
  local/access_test.php         # qualifies(): editingteacher in a course vs plain user vs student; require_user throws
  local/ownership_test.php      # user B cannot read/edit/delete/export A's plans and entries (rule 8)
  local/exporter_test.php       # two sheets; '=HYPERLINK("http://x","y")' stays text (write_string); per-RA coverage numbers
  event/*_test.php              # plan_imported, plan_approved, entry_created/updated/deleted via redirectEvents
  privacy/provider_test.php     # provider_testcase: metadata, contexts, userlist, export, three delete paths; B untouched
  hook_callbacks_test.php       # rule 11: predicate only
  generator/lib.php             # local_classprogress_generator: create_schoolyear/holiday/plan/planitem/entry
  generator/behat_local_classprogress_generator.php
  fixtures/plans/{0372,0374,0377}.md   fixtures/cases/*.md   fixtures/expected.json   fixtures/cases-expected.json   fixtures/holidays-centre-2026-27.txt
  behat/behat_local_classprogress.php
  behat/teacher_flow.feature    # @local @local_classprogress: teacher sees link, imports pasted Markdown, approves, logs an entry, exports; student has no link
```

**Reusing the Python reference (parity):**
- Copy (do not symlink or reference `../test-data`) `test-data/{plans,cases,expected.json,holidays-centre-2026-27.txt}` into `tests/fixtures/` with a small sync script; rename `cases/expected.json` to `cases-expected.json`. Add a CI-free check that the copy equals the source (`diff -r`) before release.
- One `public static` provider per JSON file, reading the JSON and yielding `'0372.md' => [$path, $expected]` (reading files is "define data" and allowed). Test: parse with timetable/year from `expected.json` (`school_year`, per-plan `timetable`; cases default `{0:2,4:2}`, `"timetable":"none"` means null, year 2026-09-14..2027-06-21) and compare only keys present in the expected entry.
- Mirror `summarize()` in `tools/check_plan.py:163-176`: `errors` and `warnings` are sorted lists of codes (duplicates kept: `["bad_date","bad_date"]`), `module`/`code` may be `null`, `hours` is a float (use `assertEqualsWithDelta`), `first`/`last` are ISO strings. Parameters of the test must match positional inner arrays.
- Holidays: parse the same `holidays-centre-2026-27.txt` through the PHP `holiday_parser` and assert day-by-day equality with the Python parser on a few sampled dates.

## H. Pre-release checklist
- [ ] `version` bumped (YYYYMMDDXX), `release` semver, `requires` 2024100700, `supported [405, 503]`, `maturity` set
- [ ] `CHANGES.md` entry for this tag; README current (install, translations, privacy statement, limits)
- [ ] CI green on all four matrix jobs including `phpunit --fail-on-warning` on 5.3 (rule 3) and Behat
- [ ] `phpcs`, `phpdoc`, `validate`, `savepoints`, `mustache` with zero warnings
- [ ] Every PHP file has GPL header and `defined('MOODLE_INTERNAL') || die();` where required; `lang/en` alphabetical, no concatenation
- [ ] Privacy provider tests pass; fixtures synced from `test-data/`
- [ ] Fresh install and upgrade tested in moodle-docker on 4.5 (PHP 8.1) and 5.3 (PHP 8.3)
- [ ] Screenshots, public repo and issue tracker URL ready; tag `vX.Y.Z`; `MOODLE_MARKETPLACE_TOKEN` secret set

## I. Contradicts or refines PLAN.md
1. **moodle-docker and 5.x (section 2 "not verified", section 7 risk "custom Apache image"):** not needed. The php-apache image auto-selects `public/` (`wwwroot.sh:12-15`). Remaining work: use `public/` paths for init/run commands and verify the plugin mount path in M0. Image behaviour read from source, not executed.
2. **PHPUnit differs by series:** 4.5 = PHPUnit 9.6.34, 5.3 = 11.5.56 with `failOnDeprecation`. Plan has no rule for annotations vs attributes, static providers, or `resetAfterTest` placement (rules 2-4).
3. **`version.php` (section 3):** add `$plugin->supported = [405, 503]`, semver `release`; `requires` 2024100700 is right.
4. **CI list (section 3) is incomplete:** add `phpmd`, `validate`, `--max-warnings 0`, `--fail-on-warning`, Behat `--profile chrome`; matrix must use `include:` with DB images `postgres:17`/`mariadb:11`; PHP 8.4 is in scope for 5.3. M0 "5.3 on PHP 8.3" is valid but CI should also cover 8.4.
5. **Behat tags:** `validate` rejects features without `@local @local_classprogress` (M5 item).
6. **`validate` and a new plugin:** missing `db/upgrade.php` is only a warning, so the first release can ship without it; `savepoints` applies once it exists.
7. **Release (M5):** the tag-driven Marketplace workflow exists (`moodlehq/moodle-plugin-release`); it needs `CHANGES.md` or a GitHub Release body and `git archive` content decides the zip (`.gitattributes` optional).
8. **Hook test claim:** plan says nothing about testing the `primary_extend` callback; tests should target the predicate (rule 11), since no real plugin tests the callback directly.
