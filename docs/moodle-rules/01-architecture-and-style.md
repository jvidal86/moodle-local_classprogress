# Moodle rules 01: architecture, code style and structure

Rules for `local_classprogress`, extracted from code that was read, not from memory. Legend for **Source** (all line numbers checked with `grep -n`):

- **AIM** = `mebis-lp/moodle-local_ai_manager` @main (b722314). Note: it is a 5.0+ plugin (`requires = 2025041400`), so every core API it uses was re-checked in 4.5.
- **C45** = `moodle/moodle` @MOODLE_405_STABLE (215f443), `mod/h5pactivity`. **C53** = @MOODLE_503_STABLE (344c92d), `public/mod/h5pactivity`.
- **MCS** = `moodlehq/moodle-cs` @main (ce5ef30), the `moodle` phpcs standard. **MPCI** = `moodlehq/moodle-plugin-ci` @main (ed7d50e).
- **DOCS** = moodledev.io pages fetched on 2026-10-18 (no line numbers).

Marks: ⚠ = legacy, version-specific, or sources disagree.

## A. Layout, version.php, autoloading

**1. Rule:** Keep `lib.php` out unless a legacy callback needs it; the only files `moodle-plugin-ci validate` requires are `version.php` and `lang/en/local_classprogress.php` (with `pluginname`).
**Why:** Hooks replace the navigation callback, so the plan's `lib.php` would be empty.
**Do:** `getRequiredFiles()` returns `['version.php', 'lang/en/<component>.php']`; strings must contain `pluginname`; if `db/upgrade.php` exists it must define `xmldb_<component>_upgrade`; `install.xml` tables must start with the component name.
**Source:** MPCI src/PluginValidate/Requirements/GenericRequirements.php:29-57,69-72. AIM keeps `lib.php` only for `_status_checks` and a web-service callback (lib.php:30,47).

**2. Rule:** Put all logic in namespaced classes under `classes/`; level 1 of the namespace is the component, and class/file names match (`local_classprogress\local\plan_service` is `classes/local/plan_service.php`).
**Why:** Autoloading needs no `require`; pages stay thin.
**Do:** `namespace local_ai_manager\local;` in `classes/local/access_manager.php`, `namespace local_ai_manager\form;` in `classes/form/`, `...\output;` in `classes/output/`, `...\event;`, `...\privacy;`.
**Source:** AIM classes/local/access_manager.php:17; classes/form/confirm_ai_usage_form.php:17; classes/output/tenantnavbar.php:17; DOCS codingstyle ("Level 1 must be the full component name").

**3. Rule:** Set `version.php` with component, version, requires, supported, release, maturity.
**Why:** `supported` and `release` are what admins and the plugins directory read; the plan only listed three fields.
**Do:**
```php
$plugin->version  = 2026102305;      // YYYYMMDDXX
$plugin->requires = 2025041400;
$plugin->supported = [500, 502];     // branches, inclusive
$plugin->release = '5.1.1';
$plugin->component = 'local_ai_manager';
$plugin->maturity = MATURITY_STABLE;
```
**Source:** AIM version.php:27-32. C45 version.php:27-29. DOCS commonfiles/version.php ("supported" since 3.9, `incompatible` optional).
⚠ For us: `requires = 2024100700` (4.5.0) and `supported = [405, 503]`. Do not "fix" the trailing digits: 4.5.15 is `2024100715` (C45 version.php:32), and 5.3 is `2026100500` (C53 public/version.php:32). Core bumps its own `requires` per branch (C53 h5pactivity version.php:29 is `2026100200`); we must not, or 4.5 would refuse the plugin.

**4. Rule:** Bump `$plugin->version` every time you edit `db/hooks.php`, `db/caches.php`, `db/access.php`, `db/events.php` or `db/install.xml`/`upgrade.php`.
**Why:** Hook registrations and the others are cached and only reloaded on a version change or a purge.
**Do:** After editing `db/hooks.php`, raise version and run the upgrade (or purge caches in dev).
**Source:** DOCS hooks ("Registrations are cached, so the component's version number must be incremented").

**5. Rule:** `db/` and `lang/` files must not contain `require_once`; in classes use the autoloader, and `require_once` only for core libraries that are not autoloaded (`formslib.php`, `tablelib.php`, `excellib.class.php`).
**Why:** DOCS state the prohibition for `db/` and lang files; AIM's form and table classes do `require_once($CFG->libdir . '/formslib.php')` (classes/form/confirm_ai_usage_form.php:24, classes/table/rights_config_table.php:35). Such a file then needs the `MOODLE_INTERNAL` guard (rule 39); this applies to the planned `exporter.php`.
**Do:** ⚠ AIM breaks it once: `require_once($CFG->dirroot . '/local/ai_manager/db/upgradelib.php');` inside `xmldb_local_ai_manager_upgrade()`. Do not copy; put upgrade helpers in a class under `classes/` (but upgrade steps must not depend on code that changes later: keep them self-contained).
**Source:** DOCS commonfiles ("must never contain `require_once`"); AIM db/upgrade.php:35.

**6. Rule:** Use `upgrade.txt` only on 4.4 and older; from 4.5 use `UPGRADING.md`. A local plugin needs neither.
**Why:** Core replaced it (MDL-81125); avoids a dead file.
**Source:** C45 mod/h5pactivity/upgrade.txt:1-3 ("replaced by UPGRADING.md"); C53 public/mod/h5pactivity/UPGRADING.md.

## B. Pages (entry points)

**7. Rule:** Every page script is: `require_once(__DIR__ . '/../../config.php');` then `require_login();` then access check, then params, then `$PAGE` setup, then `$OUTPUT->header()`.
**Why:** phpcs warns when `config.php` is included without a login check afterwards; the same relative path works in both layouts.
**Do:**
```php
require_once(dirname(__FILE__) . '/../../config.php');
require_login();
global $CFG, $DB, $OUTPUT, $PAGE, $USER;
```
**Source:** AIM rights_config.php:33-34, confirm_ai_usage.php:28-31. MCS Sniffs/Files/RequireLoginSniff.php:32-33,66-70 (accepted: `require_login`, `require_course_login`, `require_admin`, `admin_externalpage_setup`). Core uses `require(__DIR__.'/../../config.php');` (C45 view.php:30, C53 view.php:30).
⚠ 5.1+: plugin files live in `public/local/classprogress/`, so `../../config.php` resolves to `public/config.php`, a tiny loader that requires `../config.php` (C53 public/config.php:25-31). Never hard-code `public/`, and use `$CFG->dirroot` for file paths (DOCS restructure: `dirroot` keeps behaviour, `$CFG->root` is new, read-only).

**8. Rule:** After login, set `$PAGE->set_url()`, `set_context()`, `set_title()`, `set_heading()` (and layout) before any output.
**Why:** Moodle throws or misrenders without url/context.
**Do:**
```php
$PAGE->set_url($url);
$PAGE->set_context($tenant->get_context());   // we: context_system::instance()
$PAGE->set_pagelayout('admin');
$PAGE->set_title($strtitle);
$PAGE->navbar->add($strtitle);
$PAGE->set_secondary_navigation(false);
```
**Source:** AIM confirm_ai_usage.php:42-49; C45 view.php:61-68 (module version with `set_heading`).
⚠ `set_pagelayout('admin')` and `limitcontentwidth` (AIM confirm_ai_usage.php:33) are AIM choices, not requirements. For a teacher page use `standard` or `report` and decide when building M1 (needs checking on both versions).

**9. Rule:** Read params with `required_param`/`optional_param` and a PARAM type; destructive links also need `require_sesskey()` and `sesskey()` in the URL.
**Why:** Security baseline; core and AIM do exactly this.
**Do:** `$enabletenant = optional_param('enabletenant', 'not_set', PARAM_ALPHANUM);` ... `require_sesskey();` before the write; link built with `['sesskey' => sesskey()]`.
**Source:** AIM tenant_config.php:40,48-51; edit_instance.php:49,109; C45 report.php:31-38.

**10. Rule:** Redirect after a successful POST (`redirect($PAGE->url, $message)`), never render on POST success.
**Why:** Prevents double submit on reload; shows a flash notification.
**Source:** AIM confirm_ai_usage.php:69; rights_config.php:80.

**11. Rule:** Log page views of sensitive screens with an event (`->trigger()`), built from `objectid`, `context`, `other`.
**Why:** Second audit trail (plan, section 3).
**Do:** `report_viewed::create(['objectid' => ..., 'context' => $context, 'other' => $other]); $event->trigger();`
**Source:** C45 report.php:77-84; C53 report.php:95-102 (shifted by the 5.x navigation code).

## C. Services, DI, hooks

**12. Rule:** Business logic lives in plain classes taking dependencies in the constructor (`private readonly`), fetched in pages with `\core\di::get()`; or, for static helpers, use a `create_from_*` factory.
**Why:** Testable without globals; the DI container autowires constructor type hints.
**Do:**
```php
public function __construct(
    private readonly tenant_factory $tenantfactory
) {}
// page: $accessmanager = \core\di::get(\local_ai_manager\local\access_manager::class);
```
**Source:** AIM classes/local/access_manager.php:39-43; ai_info.php:43. C45 classes/local/manager.php:47,111 (`manager::create_from_coursemodule`, used in view.php:39). Hook manager through DI: AIM access_manager.php:115.
⚠ Our `access::require_user()` as a static class is fine, but a DI-constructed class is easier to mock; both patterns are used by the plugins read.

**13. Rule:** Register the top-bar link in `db/hooks.php` against `\core\hook\navigation\primary_extend`, callback as `Class::method` string (works on 4.3+) or `[Class::class, 'method']` (4.4+ only).
**Why:** The hooks API replaced `lib.php` navigation callbacks; our floor is 4.5, so both notations work.
**Do:**
```php
$callbacks = [
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => \local_ai_manager\local\hook_callbacks::class . '::extend_primary_navigation',
    ],
];
```
**Source:** AIM db/hooks.php:30-31. Hook class: C45 lib/classes/hook/navigation/primary_extend.php:31,49 and C53 public/lib/classes/hook/navigation/primary_extend.php:31,49 (identical).

**14. Rule:** Callback = `public static function name(primary_extend $hook): void` in a class under `classes/local/` (or `classes/hook_callbacks.php`); add nodes with `$hook->get_primaryview()->add_node(navigation_node::create($text, $url))`.
**Why:** This is the only supported signature; the plan's `add()` was wrong (already fixed in the plan log).
**Do:**
```php
$node = navigation_node::create(get_string('aiadministrationlink', 'local_ai_manager'),
    new moodle_url('/local/ai_manager/tenant_config.php'));
...
$hook->get_primaryview()->add_node($node);
```
**Source:** AIM classes/local/hook_callbacks.php:39,44,55. ⚠ AIM uses `moodle_url`; since 4.5 `\core\url` is the real class and `moodle_url` is an alias (C45 lib/classes/url.php:42,908; C53 public/lib/classes/url.php:955). Either works on both; prefer `\core\url` in new code.

**15. Rule:** Keep hook callbacks cheap and install-safe: check a setting or `during_initial_install()` first, wrap DB-dependent logic, return early if the user does not qualify.
**Why:** Hooks fire during install and upgrade, before tables exist; the primary navigation is built on every page.
**Do:** `if (empty(get_config('local_ai_manager', 'addnavigationentry'))) { return; }` as first statement.
**Source:** AIM hook_callbacks.php:40; DOCS hooks ("Hooks can fire during installation or upgrade"). Relevant to the plan: the `get_user_capability_course()` call must be cached (session cache) and must not run when `during_initial_install()`.

**16. Rule:** Observe `\core\event\user_deleted` in `db/events.php` to wipe or anonymise per-user rows.
**Why:** Teacher-authored data must not outlive the account silently (privacy deletion also covered in the privacy provider).
**Do:** `['eventname' => '\core\event\user_deleted', 'callback' => '\local_ai_manager\local\observers::user_deleted']`
**Source:** AIM db/events.php:28-33; classes/local/observers.php:37. ⚠ Decide in the privacy slice whether history rows are deleted or kept (the plan says history survives entry deletion).

## D. Output: templates, renderables, renderer

**17. Rule:** Build output from `renderable, templatable` classes in `classes/output/` plus Mustache files in `templates/`; render with `$OUTPUT->render($obj)` or `$OUTPUT->render_from_template('local_x/name', $ctx)`. A `renderer.php` is not needed.
**Why:** `renderer_base::render()` finds `render_<classname>` first, then `named_templatable`, then guesses the template `component/classname`.
**Do:**
```php
class tenantnavbar implements renderable, \templatable {
    #[\Override]
    public function export_for_template(renderer_base $output): stdClass { ... }
}
// page:  echo $OUTPUT->render(new tenantnavbar('rights_config.php'));
```
**Source:** AIM classes/output/tenantnavbar.php:32,51-52; rights_config.php:85; C45 lib/classes/output/renderer_base.php:218-260 (C53 :238). Neither AIM nor h5pactivity ships a renderer: C45 mod/h5pactivity/classes/output/reportlink.php:40,65.
⚠ DOCS say "plugins should always use a more specific plugin renderer" and place it at `classes/output/renderer.php` (never root `renderer.php`). If we add one: `local_classprogress\output\renderer extends \plugin_renderer_base`, fetched via `$PAGE->get_renderer('local_classprogress')`. Renderer classes may not use `global $OUTPUT/$PAGE` (MCS Sniffs/PHP/ForbiddenGlobalUseSniff.php:41-45).

**18. Rule:** `export_for_template()` returns only scalars, arrays and `stdClass`; do queries and access checks before constructing the renderable.
**Why:** Mustache cannot call methods; keeps templates themeable and cacheable.
**Source:** DOCS output ("should only return simple types"); AIM tenantnavbar.php:52-82.

**19. Rule:** Every template starts with the GPL comment, then a doc comment with `@template component/name`, description, required variables, and an `Example context (json)`.
**Why:** `moodle-plugin-ci mustache` lints this.
**Do:**
```
{{!
    @template local_ai_manager/infobox
    Template for displaying the local_ai_manager infobox.
    Example context (json):
    { "aiinfourl": "https://mymoodle.com/..." }
}}
```
**Source:** AIM templates/infobox.mustache:17-26; C45 templates/reportlink.mustache:17-32; DOCS templates ("Classes required for JS", "Data attributes required for JS", "Context variables required").

**20. Rule:** In templates use `{{#str}} key, local_x {{/str}}` for text; `{{var}}` escapes, `{{{var}}}` only for URLs or HTML already produced by `format_text()`.
**Why:** Triple braces are an XSS risk (plan sec. 3 says no `noclean`).
**Source:** AIM templates/infobox.mustache:28 (`{{#str}}`); C45 templates/reportlink.mustache:34 (`href="{{{url}}}"`, `{{message}}`); DOCS templates.

**21. Rule:** Escape with `s()`/`format_string()` for short plain text and `format_text($text, FORMAT_MARKDOWN, ['context' => $ctx])` for long text; set the context option.
**Source:** DOCS output ("format_string ... does not offer any of its options or protections"; "Set `options->context`").

## E. Forms

**22. Rule:** Forms are classes in `classes/form/` extending `\moodleform`, with `require_once($CFG->libdir . '/formslib.php')` after a `defined('MOODLE_INTERNAL') || die;` guard, `definition()` and `validation($data, $files): array`.
**Why:** The `require_once` is a side effect, so the guard is required in this file (see rule 29).
**Do:**
```php
defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once($CFG->libdir . '/formslib.php');
class confirm_ai_usage_form extends \moodleform {
    #[\Override]
    public function definition() { $mform = &$this->_form; ... }
```
**Source:** AIM classes/form/confirm_ai_usage_form.php:21-24,34-36,48-49. Custom data: `new confirm_ai_usage_form(null, ['showtermsofuse' => ...])` (confirm_ai_usage.php:60-61).

**23. Rule:** Page processing order: `is_cancelled()` then `get_data()` then (else) `set_data()` and `display()`; `get_data()` already verifies sesskey.
**Do:**
```php
if ($form->is_cancelled()) { redirect('/my'); }
else if ($data = $form->get_data()) { ...; redirect($PAGE->url, get_string('...', 'local_x')); }
echo $OUTPUT->header(); $form->set_data([...]); $form->display();
```
**Source:** AIM confirm_ai_usage.php:63-70,83-84; DOCS form. Use `else if` (not `elseif`): DOCS codingstyle.

**24. Rule:** Use `#[\Override]` on overridden methods instead of `@inheritdoc`.
**Why:** Coding style says so. ⚠ Core 4.5 (PHP 8.1 supported) already uses it (C45 lib/classes/system_clock.php:27,35). Per general PHP knowledge (not from the sources read) the engine enforces `#[\Override]` only on 8.3+ and ignores it on 8.1/8.2, so run PHPUnit on 8.3 to catch mistakes. Older core code (h5pactivity, 2020) does not use it.
**Source:** AIM classes/form/confirm_ai_usage_form.php:35,48; DOCS codingstyle.

## F. Database: install.xml, upgrade.php

**25. Rule:** In `install.xml`: lowercase names, table prefix = component (`local_classprogress_entry`), `id` int(10) sequence, timestamps int(10) `NOTNULL="true" DEFAULT="0"`, booleans int(1) with default, text for long strings, char length explicit.
**Why:** Matches core and the checker (MPCI requires the component prefix).
**Do:**
```xml
<FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
<FIELD NAME="deleted" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
<FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
```
**Source:** AIM db/install.xml:9,65,23; C45 db/install.xml:9,17-18,12 (timestamps without default: both occur). DOCS xmldb: names lowercase, max 53 chars for tables and 63 for fields (28/30 before 4.3). ⚠ AIM makes most columns `NOTNULL="false"` (install.xml:10-25); core activity tables prefer NOT NULL where a value always exists. Use NOT NULL with defaults for our `char(10)` ISO dates only if never empty.

**26. Rule:** Declare `primary` key, a `foreign` key for every `userid`/`planid` column (the DB auto-creates the index), and extra indexes only for real query paths; unique constraints that are not an FK target are indexes.
**Why:** DOCS: "Declare any fields as UNIQUE KEY only if they are going to be used as target for one FK."
**Do:**
```xml
<KEY NAME="primary" TYPE="primary" FIELDS="id"/>
<KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
<INDEX NAME="configkey_tenant" UNIQUE="true" FIELDS="configkey, tenant"/>
```
**Source:** AIM db/install.xml:70-71,42; DOCS tools/xmldb. ⚠ Naming disagreement: core uses prefixes `fk_course`, `uq_activityuserattempt` and a unique KEY (C45 db/install.xml:24,44-45); AIM uses bare field names; DOCS say names are the fields joined by hyphens (core index: `h5pactivityid-timecreated`, C45 db/install.xml:49). Choose DOCS style (`userid`, `planid-sessiondate`), unique constraint as `<INDEX UNIQUE="true">`. Our "one entry per planitem" is `planitemid` unique index; but planitemid is null for unplanned entries, and NULLs in a unique index behave differently across DBs, so test on MariaDB, PostgreSQL.

**27. Rule:** Every schema change goes through `db/upgrade.php` with a guard (`field_exists`/`table_exists`) and its own `upgrade_plugin_savepoint(true, <version>, 'local', 'classprogress')` that equals the new `version.php` value, ending with `return true;`.
**Why:** `moodle-plugin-ci savepoints` fails otherwise; re-runs must be safe.
**Do:**
```php
if ($oldversion < 2024080101) {
    $table = new xmldb_table('local_ai_manager_instance');
    $field = new xmldb_field('customfield5', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'customfield4');
    if (!$dbman->field_exists($table, $field)) { $dbman->add_field($table, $field); }
    upgrade_plugin_savepoint(true, 2024080101, 'local', 'ai_manager');
}
```
**Source:** AIM db/upgrade.php:33-46,427; MPCI src/Command/SavePointsCommand.php:42-62. Core style also keeps `// Automatically generated Moodle v5.2.0 release upgrade line.` markers (C53 db/upgrade.php:60,75,78); mod uses `upgrade_mod_savepoint` (C45 db/upgrade.php:67). Use `$dbman = $DB->get_manager();` inside the function (AIM:36). A first release needs no steps; keep `xmldb_local_classprogress_upgrade()` returning true from the start (MPCI requires the function when the file exists).

## G. Language strings

**28. Rule:** Strings live only in `lang/en/local_classprogress.php`, keys lowercase letters/digits, sorted by `strcmp` byte order; `pluginname` is mandatory.
**Why:** moodle-cs enforces order with `strcmp`/`SORT_STRING`, not natural or locale order: `ai_manager:use` sorts before `aiadministrationlink`, uppercase before lowercase, `_` before letters.
**Do:** `$string['ai_manager:viewprompts'] = 'View prompts of users';` then `$string['aiadministrationlink'] = '...';`
**Source:** MCS Sniffs/Files/LangFilesOrderingSniff.php:19,186,235; AIM lang/en/local_ai_manager.php:26-42,220. ⚠ Core h5pactivity is not strictly sorted (C45 lang/en/h5pactivity.php:35-37, `answer_unknown` before `answer_text`): do not imitate it. DOCS codingstyle does not mention ordering; the sniff does.

**29. Rule:** Required naming: capability name strings `local_classprogress:use` keyed as `classprogress:use`; settings `<key>` and `<key>_desc`; `_help` only for help icons; `cachedef_<area>` for every cache; `privacy:metadata:...` for privacy; `eventname` strings via `get_name()`.
**Why:** Missing capability, cache or privacy strings produce debug errors.
**Do:** `$string['cachedef_googleauth'] = 'Cache for ...';` `$string['privacy:metadata:local_ai_manager_userinfo:locked'] = '...';`
**Source:** AIM lang:30-41 (`ai_manager:manage`), 67, 223-229; C45 lang:59,68,114-121; DOCS admin ("`_desc` suffix is the current convention"). ⚠ Disagreements: AIM uses `…desc` without underscore (lang:28) while DOCS use `_desc`: use `_desc`. Privacy keys: AIM `privacy:metadata:<table>:<field>`, core `privacy:metadata:<field>` (shared): use AIM's, since we have 8 tables.

**30. Rule:** Placeholders: `{$a}` for one value, `{$a->name}` for an object/array; strings are whole sentences, no fragments, no leading/trailing spaces, no concatenation.
**Source:** C45 lang/en/h5pactivity.php:48 (`'Attempt #{$a}'`), :53; DOCS codingstyle ("Language strings ... never be assembled from fragments").

**31. Rule:** Lang files need no `MOODLE_INTERNAL` guard (sniff exempts `/lang/`), though core adds it.
**Source:** MCS Sniffs/Files/MoodleInternalSniff.php:59-60; AIM lang file has none (lang:1-26); C45 lang/en/h5pactivity.php:26 has one. Long lines allowed in lang files (MCS LineLengthSniff.php:40).

## H. Settings, caches, capabilities, events, privacy

**32. Rule:** `settings.php`: wrap in `if ($hassiteconfig)`, add an `admin_settingpage` to `localplugins`, add settings only inside `if ($ADMIN->fulltree)`, name them `local_classprogress/<key>`, never query the DB at include time.
**Do:**
```php
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ai_manager', get_string('basicsettings', 'local_ai_manager'));
    $ADMIN->add('local_ai_manager_settings', $settings);   // simple plugins: $ADMIN->add('localplugins', $settings)
    if ($ADMIN->fulltree) { $settings->add(new admin_setting_configcheckbox('local_ai_manager/addnavigationentry', ...)); }
}
```
**Source:** AIM settings.php:32-44,72-78 (settingpage at :38-41); DOCS admin ("The slash is important here"; `$paramtype` is the fifth argument of `admin_setting_configtext`, so use `PARAM_INT` for our size and day limits). `defined('MOODLE_INTERNAL') || die;` goes before (AIM settings.php:28). `admin_setting_check` exists in 4.5 (C45 lib/adminlib.php:11723). Read with `get_config('local_classprogress', 'maxplansize')`.

**33. Rule:** `db/caches.php` defines `$definitions` with `mode`, plus `simplekeys`/`simpledata` when keys and values are plain; session caches for per-user results.
**Why:** The plan's "session cache" for the capability check needs a definition and `cachedef_` string.
**Do:** `'googleauth' => ['mode' => cache_store::MODE_APPLICATION, 'simplekeys' => true, 'simpledata' => true, 'canuselocalstore' => false]`; for us `cache_store::MODE_SESSION`; fetch with `cache::make('local_classprogress', 'area')`.
**Source:** AIM db/caches.php:28-35; DOCS muc ("`ttl` ... strongly recommended that you don't make use of this"; session data "may not actually be stored in the session"). ⚠ A cached "qualifies as teacher" flag goes stale when a role is removed: key it by a short revision or use a short `ttl` consciously (DOCS discourage ttl).

**34. Rule:** `db/access.php` lists capabilities as `plugin/name:action` with `captype`, `contextlevel`, `archetypes`; use `clonepermissionsfrom` when mirroring a core capability.
**Source:** AIM db/access.php:28-40; C45 db/access.php:28-69 (`'clonepermissionsfrom' => 'moodle/course:manageactivities'` at :49). AIM sets `CAP_PREVENT` for non-roles explicitly (access.php:41-52): not necessary. The course-level check problem is outside this slice.

**35. Rule:** Events extend `\core\event\base` in `classes/event/`, set `crud`, `edulevel`, `objecttable` in `init()`, implement `get_name()` (lang string) and `get_description()`, expose a `create_from_*` factory or document `other`; add `validate_data()` for required `other` keys.
**Do:** `$this->data['crud'] = 'r'; $this->data['edulevel'] = self::LEVEL_OTHER; $this->data['objecttable'] = '...';`
**Source:** AIM classes/event/get_ai_response_succeeded.php:29-37,44,53,63-73; C45 classes/event/report_viewed.php:51-55,72-78. Our write events use crud `c`/`u`/`d` and `LEVEL_TEACHING`.

**36. Rule:** Privacy provider implements `metadata\provider`, `core_userlist_provider`, `plugin\provider`; the plugin stores in a context (we choose user or system) and every table with `userid` is declared.
**Source:** AIM classes/privacy/provider.php:44-47; C45 classes/privacy/provider.php:37-40. Depth belongs to another slice. ⚠ AIM stores everything in the system context (provider.php:98-100); h5pactivity uses module context.

## I. Code style and checks (what CI enforces)

**37. Rule:** Moodle phpcs (`moodle-plugin-ci phpcs --max-warnings 0`): PSR-12 base, 4 spaces, braces always, `else if`, short arrays, trailing commas in multiline arrays, line length 132 (error at 180), forbidden `error_log`, `print_r`, `extract`, `unserialize`, `sizeof`.
**Why:** Warnings fail the job when `--max-warnings 0`.
**Source:** MCS moodle/ruleset.xml:20,61,73; Sniffs/Files/LineLengthSniff.php:34-35; Sniffs/PHP/ForbiddenFunctionsSniff.php:55-65; MPCI gha.dist.yml:83. History snapshots: use `json_encode`/`json_decode`, never `unserialize`. ⚠ AIM indents multiline arrays by 8 (hooks.php:29-37); core by 4 (C45 db/access.php:28-40). Both pass; pick 4.

**38. Rule:** PHPDoc: GPL header block (copied from any file), then a docblock with `@package`, `@copyright`, `@license`, one docblock per class, method, and constant; `: void` and typed params everywhere (new code).
**Source:** AIM classes/local/access_manager.php:22-30,45-59; MCS Sniffs/Commenting/MissingDocblockSniff.php:109,118; DOCS codingstyle ("Type hints and return types are required for all new code"). The first line `// This file is part of Moodle - http://moodle.org/` plus the GPL block is checked (MCS Sniffs/Files/BoilerplateCommentSniff.php).

**39. Rule:** `defined('MOODLE_INTERNAL') || die();` decision table: pages that include `config.php`: none; class/interface/trait file with one artifact and no side effects: none (adding it gives warning `MoodleInternalNotNeeded`); any file with side effects or several artifacts (`db/access.php`, `hooks.php`, `caches.php`, `events.php`, `settings.php`, `lib.php`, a class that runs `require_once`): required (error `MoodleInternalGlobalState` or warning `...MultipleArtifacts`); `db/upgrade.php` (one function, no side effects) must NOT have it (warning `MoodleInternalNotNeeded` fails `--max-warnings 0`; neither AIM upgrade.php:17-35 nor C45 db/upgrade.php:17-47 has it); `lang/` and `tests/behat`: exempt.
**Why:** The plan said "non-class files"; the sniff is more precise.
**Source:** MCS Sniffs/Files/MoodleInternalSniff.php:55-60,88-96,111-139. AIM: absent in class files (hook_callbacks.php, access_manager.php), present in the form file (confirm_ai_usage_form.php:21) and in `db/access.php`, `hooks.php`, `caches.php`, `events.php`. ⚠ Core class files such as C45 classes/event/report_viewed.php:27 still carry it (older code).

**40. Rule:** Tests: `final class x_test extends \advanced_testcase` in `tests/` mirroring `classes/` paths, `@covers` on each test, `: void`; Behat features tagged `@local @local_classprogress`.
**Source:** AIM tests/hook/purpose_usage_test.php:27-33; MCS Sniffs/PHPUnit/{TestClassesFinal,TestCaseCovers,TestReturnType}Sniff.php; MPCI GenericRequirements.php:74-77.

**41. Rule:** CI runs: phplint, phpmd (non-blocking), phpcs, phpdoc, validate, savepoints, mustache, grunt, phpunit, behat. Matrix PHP 8.1-8.3 on MOODLE_405_STABLE.
**Source:** MPCI gha.dist.yml:35-36,74-112. ⚠ moodle-plugin-ci resolves the 5.1+ `public/` layout itself (`getPublicDirectory()`: `file_exists($dir.'/public/version.php')`), MPCI src/Bridge/Moodle.php:43-51; confirm in M0 that your pinned `^4` supports 5.3. AIM delegates to a shared workflow (`.github/workflows/ci.yml:7`), so its matrix is not visible.

## J. 4.5 versus 5.x differences that touch plugin code

- ⚠ Location: `public/local/classprogress/` on 5.1+, `local/classprogress/` on 4.5; same relative `../../config.php` (rule 7).
- ⚠ `index.php` of activity plugins became a redirect to the course overview (C53 index.php:28-29 uses `activityoverviewbase::redirect_to_overview_page`; C45 index.php:28-86 lists instances). Irrelevant to a local plugin; do not copy.
- ⚠ 5.x core adds `\core\url` as the preferred class (C53 report.php:80 uses `new \core\url(...)`), already available in 4.5 (C45 lib/classes/url.php:42), so `\core\url` is safe on both.
- ⚠ 5.x page API gained `set_show_navigation_footer()`, `set_supplementary_content()` (C53 report.php:70-87): do not use on 4.5.
- DB: core raised some `char` limits in 5.x (activity `name` 255 to 1333, C53 db/install.xml:11 vs C45:11) for multilang: our `char(255)` is fine; keep long text in `text`.

---

## (a) Pull request review checklist

- [ ] GPL header and `@package local_classprogress` on every PHP file; `defined('MOODLE_INTERNAL')` per rule 39.
- [ ] `version.php`: version bumped, `requires`, `supported`, `release`, `maturity`.
- [ ] Page: `config.php` then `require_login()` then access check then PARAM-typed params then `$PAGE` url/context/title; no superglobals.
- [ ] Every write: moodleform or `require_sesskey()`; redirect after success.
- [ ] Logic in `classes/`; pages only wire; no `require_once` in `db/` or `lang/`; in classes only for non-autoloaded core libs, with the guard.
- [ ] New table/field: `install.xml` and `upgrade.php` step with guard and savepoint; version bumped.
- [ ] Output through templatables and Mustache (docblock with Example context); text via `s()` or `format_text()`; no `{{{ }}}` on raw user data.
- [ ] New strings in `lang/en` only, sorted by `strcmp`, `_desc`/`_help`/`cachedef_`/`privacy:` keys present.
- [ ] `db/hooks.php`, `caches.php`, `access.php` changes accompanied by a version bump.
- [ ] Tests: `final`, `@covers`, `: void`; ownership tests for the new service method.
- [ ] `phpcs --max-warnings 0`, `phpdoc`, `validate`, `savepoints`, `mustache`, `phpunit` green on 4.5/PHP 8.1 and 5.3/PHP 8.3.

## (b) Things to avoid

- Root `renderer.php`; `lib.php` navigation callbacks; `upgrade.txt`; `$CFG->dirroot` hard-coded with `/public`.
- `require_once` in `db/` or lang files (AIM does it once in upgrade.php; not a model).
- `unserialize`, `print_r`, `error_log`, `extract`, `sizeof`.
- `elseif`, missing braces, long array syntax, `@inheritdoc`.
- `lang/ca`, `lang/es` inside the plugin (not loaded).
- NULL-able everything in `install.xml` (AIM).
- `MOODLE_INTERNAL` in single-class files (warning).
- Mixed key naming (`fk_`/`uq_` vs plain) in one `install.xml`.

## (c) Contradicts or refines PLAN.md

1. Plan layout lists `renderer.php` (section 3): neither plugin has one; use `classes/output/*` templatables plus templates. If a renderer is added, it goes at `classes/output/renderer.php`, not the root.
2. `lib.php` is listed but would be empty: omit it (validate does not require it).
3. "`MOODLE_INTERNAL` in non-class files" is imprecise: see rule 39 (class files warn if the guard is present; config.php pages are exempt; side-effect files require it).
4. `version.php` lacks `release` and `supported = [405, 503]`; explain the `requires` digits so nobody edits them.
5. "Strings kept alphabetical" should read "sorted by `strcmp` byte order, enforced by phpcs".
6. Add: `UPGRADING.md` not `upgrade.txt`; `db/events.php` observer for `user_deleted`; `cachedef_<area>` lang strings; `_desc`/`_help` suffix rules; Behat tags `@local_classprogress`; `xmldb_local_classprogress_upgrade()` present from v1.
7. Savepoints: plan says "savepoints in `upgrade.php`"; add that each equals a `version.php` bump and each step needs a `field_exists`/`table_exists` guard.
8. Unique "one entry per planitem" must be an index (not a unique KEY) under the DOCS rule, and `planitemid` is nullable (unplanned entries): verify uniqueness semantics on MariaDB and PostgreSQL in M0/M1.
9. Hook callback must be cheap and install-safe; the `get_user_capability_course()` result cache needs a `db/caches.php` entry (session mode) plus a `cachedef_` string, and a staleness policy.
10. `exporter.php` needs `require_once($CFG->libdir . '/excellib.class.php')` (not autoloaded), hence the `MOODLE_INTERNAL` guard in that one class file (rules 5, 39).
11. The plan checks CI "lint, phpcs, phpdoc, savepoints, mustache, PHPUnit, Behat": also `validate`, and `phpcs --max-warnings 0` is the upstream default; `phpcpd` is deprecated in moodle-plugin-ci (MPCI src/Command/CopyPasteDetectorCommand.php:36), so skip it.
