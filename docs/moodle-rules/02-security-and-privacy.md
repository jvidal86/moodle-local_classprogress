# Security, access control, privacy and events: rules for `local_classprogress`

Scope: per-teacher private data, cross-course, Moodle 4.5 minimum (PHP 8.1), tested on 5.3. Every rule below was checked against code or docs read in this pass. Line numbers were verified with `grep -n` on shallow clones taken 2026-10-18.

Source labels: **ATT** = danmarsden/moodle-mod_attendance @ default branch (786991c; its `requires` is 5.3, the MOODLE_405_STABLE branch was not read). **AI** = mebis-lp/moodle-local_ai_manager @ main (b722314). **C45** / **C53** = moodle/moodle @ MOODLE_405_STABLE (4.5.15) / MOODLE_503_STABLE; 5.x paths gain a `public/` prefix, written `[public/]`. **DOC** = moodledev.io pages fetched (security policy, access API, privacy API 5.0); the events API page returned 404 for 4.5 and 5.0, so event rules come from `lib/classes/event/base.php` instead. ⚠ marks legacy, version-specific, disputed or anti-pattern items.

Honest limits: none of ATT, AI or h5pactivity solves "a teacher-only, per-user, cross-course plugin". ATT and h5pactivity are course-bound (module context); AI uses system context plus its own role table. Rules 2, 5, 25 and 30 are therefore reasoned from core code, not copied from a plugin.

---

## A. Capabilities and page access

### 1. Declare one `write` capability with risk flags
- **Rule**: In `db/access.php` set `captype`, `contextlevel`, `riskbitmask` and CAP_ALLOW-only archetypes. Use `write` for anything that creates or edits data.
- **Why**: Guests and not-logged-in users are refused every `write` capability and every capability with RISK_XSS/CONFIG/DATALOSS, whatever the role settings (stops a guest session reaching teacher pages).
- **Do**: `'mod/attendance:takeattendances' => ['riskbitmask' => RISK_DATALOSS, 'captype' => 'write', 'contextlevel' => CONTEXT_MODULE, 'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]]`. Use RISK_PERSONAL for capabilities exposing personal data, RISK_XSS only where `noclean` is allowed.
- **Source**: ATT db/access.php:50-52,61-71; C45 lib/accesslib.php:478-484 (guest rule); DOC access API ("recommended to use only CAP_ALLOW" in archetypes; AI db/access.php:29-40 also uses CAP_PREVENT, which is allowed but redundant).

### 2. Teachers hold roles in courses: never gate them with a bare system-context check
- **Rule**: Do not use `has_capability($cap, context_system::instance())` to admit teachers. Use `get_user_capability_course($cap, null, true, '', '', 1)` (non-false result means "teacher somewhere"), or use the `user` archetype if all authenticated users may enter.
- **Why**: `editingteacher` is assigned in course (or category) contexts, so a system-context check is false for them (broken link, 403 for the real users).
- **Do**: `$courses = get_user_capability_course('local/classprogress:use', null, true, '', '', 1); $allowed = !empty($courses);` Core calls it for "any course where the user has the capability" (`moodle/course:view`, enrolled-user search) and its docblock notes it "may return an array of all courses" if the user has the capability everywhere, hence `$limit = 1`.
- **Source**: C45 lib/accesslib.php:4365-4366,4379 (doc + signature), lib/enrollib.php:790, lib/classes/user.php:418; accesslib.php:4284 (site admins short-circuit when `$doanything`). Core's "own data" idiom is the opposite: `'user' => CAP_ALLOW` at system level (`moodle/user:manageownfiles`, `moodle/calendar:manageownentries`), lib/db/access.php:619-628,1303-1313; AI db/access.php:33 does the same for `local/ai_manager:use`.
- ⚠ Do **not** also give the capability to archetype `user`: then every authenticated user has it in system context, `get_user_capability_course` would return all courses, and students get in. MDL-59897 (old tracker, third-party summary) reports the function being slow on large sites; the 4.5 docblock says "reasonably efficient".
- ⚠ The function reads role assignments, not enrolment status; a teacher with a suspended enrolment still qualifies. Decide whether that is acceptable and write a test either way.

### 3. Every page: `require_login()`, then `require_capability()`, then data
- **Rule**: Call `require_login()` first (no course: site level), then `require_capability()` with an explicit context. Use `has_capability()` only to branch the UI, never as the only gate on a write.
- **Why**: `require_login()` alone lets the guest user and any student through; missing capability checks are the classic broken-access-control bug.
- **Do**: `require_login($course, true, $cm); $context = context_module::instance($cm->id); require_capability('mod/attendance:manageattendances', $context);` For us: `require_login(); \local_classprogress\local\access::require_user();` where that method wraps rule 2 and throws `required_capability_exception`/`moodle_exception`.
- **Source**: ATT sessions.php:49-52, export.php:37-40; AI manage_models.php:31-33; DOC security policy ("Before allowing the user to see anything or do anything, call has_capability or require_capability").

### 4. A passing capability check never means "may see this row"
- **Rule**: Treat capability and ownership as two separate checks. Site admins pass `has_capability()` and `get_user_capability_course()` for everything (`$doanything = true` default).
- **Why**: Without an owner filter an admin, or a teacher guessing ids, can read other teachers' plans.
- **Do**: see rules 6 to 8; include an admin user in the "user B cannot read user A" PHPUnit tests.
- **Source**: C45 lib/accesslib.php:432 (signature, `$doanything = true`), 4284.

### 5. Do not cache the access decision you enforce
- **Rule**: Cache (session cache, as PLAN proposes) only the decision that shows the navigation link. Re-run the check on each page and service entry.
- **Why**: A cached "teacher" flag keeps working after the role is removed (stale authorisation).
- **Do**: hook callback uses the cached value; `access::require_user()` does not. No inspected plugin caches enforcement: AI hook_callbacks.php and external functions call the live check each time.
- **Source**: AI classes/local/hook_callbacks.php:39-60 (nav, live check), classes/external/get_user_quota.php:54-56 (enforcement, live). Reasoned recommendation, not a documented rule.

---

## B. Ownership (IDOR)

### 6. Put the owner in the lookup condition, never fetch by id alone
- **Rule**: Load rows with `['id' => $id, 'userid' => $USER->id]` plus `MUST_EXIST`, or join to the parent that carries the owner.
- **Why**: Insecure direct object reference: changing `?entryid=` to another teacher's id.
- **Do**: `$DB->get_record('attendance_sessions', ['id' => $sessionid, 'attendanceid' => $this->id], '*', MUST_EXIST);` (parent id in the condition). Child of child (entry -> planitem -> plan -> owner) must check the whole chain in one query.
- **Source**: ATT classes/structure.php:1147-1156; C45 mod/h5pactivity/classes/local/manager.php:523-531 (`get_attempt` filters by `h5pactivityid`).

### 7. Bulk id lists: compare counts
- **Rule**: When a request carries several ids, query them with the owner condition and refuse if the count differs from the unique ids sent.
- **Why**: One foreign id in a "delete selected" list must not be silently skipped or, worse, deleted.
- **Do**: `[$sql,$params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED); $params['attendanceid'] = $this->id; ... if (count($sessions) != count(array_unique($ids))) throw ...`
- **Source**: ATT classes/structure.php:1179-1196 (read), 1384-1389 and 1415-1432 (`filter_own_session_ids` before delete).
- ⚠ The "No such session" check there is a raw-string `moodle_exception` (see rule 28).

### 8. Never accept a user id for "whose data"; derive it from `$USER`
- **Rule**: Pages and services take the acting user from `$USER->id`; a `userid` parameter, if it exists at all, must equal `$USER->id` or be refused.
- **Why**: Horizontal privilege escalation by editing the `userid` query parameter.
- **Do**: h5pactivity returns null (page throws `permissiondenied`) when the requested user differs from the current user and the viewer lacks the review capability: `if ($userid && $user->id != $userid) { return null; }`.
- **Source**: C45 mod/h5pactivity/classes/local/manager.php:494-498; report.php:50-53 (`throw new \moodle_exception('permissiondenied')`).
- ⚠ Reviewer access is out of scope in v1; when added it needs its own capability, not a relaxed owner check.

---

## C. Input, CSRF, destructive actions

### 9. Typed parameters only, no superglobals
- **Rule**: `required_param`/`optional_param` with the narrowest PARAM type; arrays via `*_param_array`. Never touch `$_GET/$_POST/$_REQUEST`.
- **Why**: Injection and type confusion; `PARAM_INT` makes ids safe before they reach SQL.
- **Do**: `$id = required_param('id', PARAM_INT); $confirm = optional_param('confirm', null, PARAM_INT); $ids = optional_param('ids', '', PARAM_ALPHANUMEXT);`
- **Source**: ATT sessions.php:31-34,121,145-149,182-183; DOC security policy.
- ⚠ ISO dates (`char(10)`) have no PARAM type: take `PARAM_TEXT`/`PARAM_RAW` and validate with a strict regex plus `DateTimeImmutable::createFromFormat` in the service (own recommendation).

### 10. Moodleforms for writes; `setType` on every field
- **Rule**: Build write screens with `moodleform`; call `setType()` for every element (including hidden ones); use `get_data()`.
- **Why**: The form adds and checks the sesskey (CSRF) and cleans values by type; a missing `setType` triggers a debugging notice and unsanitised data.
- **Do**: `$mform->addElement('hidden','id'); $mform->setType('id', PARAM_INT);`
- **Source**: C45 lib/formslib.php:219-221 (hidden `sesskey` added automatically), 314-319 (`confirm_sesskey()` on submission, else `invalidsesskey`), 1460 (`detectMissingSetType`); ATT classes/form/import/marksessions.php:77-83.
- Note: ATT import.php:65 calls `require_sesskey()` after `get_data()`; redundant but harmless.

### 11. Link/button actions: confirmation page, POST, sesskey checked together with `confirm`
- **Rule**: A state-changing action reached by link first shows `$OUTPUT->confirm()`; the "continue" URL carries `confirm=1&sesskey=...`; the handler runs only if `confirm && confirm_sesskey()`. Use `data_submitted() && confirm_sesskey()` for non-form posts.
- **Why**: CSRF and accidental deletion (a crafted link or prefetch must not delete anything).
- **Do**:
  ```php
  if (isset($confirm) && confirm_sesskey()) { $att->delete_sessions([$sessionid]); redirect(...); }
  $params = [..., 'confirm' => 1, 'sesskey' => sesskey()];
  echo $OUTPUT->confirm($message, $att->url_sessions($params), $att->url_manage());
  ```
- **Source**: ATT sessions.php:121-141,148,211-224, take.php:67; C45 lib/classes/output/core_renderer.php:1740-1744 (a `moodle_url` continue becomes a `single_button` with method `post`).
- Per DOC: confirm "before destroying large amounts of data". Our entry delete must also write its history row first (rule 20).

### 12. Validate text size on the server, in the service as well as the form
- **Rule**: Cap length with a form `maxlength` rule plus a `validation()` override, and re-check `strlen()` against the admin setting inside the import service before parsing.
- **Why**: A 50 MB paste exhausts memory/CPU in the parser (denial of service); the service is also reached by tests, CLI and future web services.
- **Do**: `$mform->addRule('x', get_string('maximumchars','',255), 'maxlength', 255, 'client');` (QuickForm still validates server-side: the `'client'` flag only adds JavaScript, see C45 lib/pear/HTML/QuickForm.php:1475 vs 1721.)
- **Source**: ATT classes/form/addwarning.php:77, C45 mod/h5pactivity/mod_form.php:62. No inspected plugin enforces an admin-configurable byte or item limit; PLAN's 200 KB / 400 days limits are our own design.

### 13. Uploads: restrict type, read the content, do not store it
- **Rule**: `filepicker` with `accepted_types` (and `maxbytes`); read with `$form->get_file_content()` inside the same request; do not keep the file.
- **Why**: Avoids file storage, pluginfile exposure and retention problems.
- **Do**: `'accepted_types' => ['.h5p']` / `'accepted_types' => 'csv'`; `$text = $form->get_file_content('importfile');`
- **Source**: C45 mod/h5pactivity/mod_form.php:68; ATT classes/form/import/marksessions.php:56, import.php:88. ⚠ `accepted_types` is a UI hint; re-validate content (UTF-8, size) in the service.

### 14. Files, if ever added (v1: none)
- **Rule**: A `local_classprogress_pluginfile()` must `require_login`, whitelist the filearea, check the record, look the file up inside the exact context path, and force download.
- **Why**: Otherwise any logged-in user can fetch another user's file by guessing path ids.
- **Do**: `require_login($course,false,$cm); if (!in_array($filearea,['session'])) return false; ... $fs->get_file_by_hash(sha1("/$context->id/mod_attendance/$filearea/$sessid/$relativepath")); send_stored_file($file,0,0,true);`
- **Source**: ATT lib.php:450-481. For user context also require `$context->instanceid == $USER->id` (own addition). DOC: uploaded files must not be opened in the browser from the same server.

---

## D. Output escaping

### 15. Plain text: `s()`; short titles: `format_string()`; rich text: `format_text()`
- **Rule**: Escape on output, in the renderer/template layer, according to the kind of string. Module code/title and RA headings are short strings.
- **Why**: Stored XSS from teacher-typed text shown to the same teacher, to an admin, or in a shared screen.
- **Do**: `html_writer::span(s($text), 'remarkcontent')`; `format_string($field->name, true, ['context' => $modcontext])`.
- **Source**: ATT classes/output/user_sessions_cells_html.php:105, classes/form/export.php:140; AI classes/table/model_management_table.php:168; DOC security policy.

### 16. Markdown: `format_text($text, FORMAT_MARKDOWN)` with the default cleaning
- **Rule**: Render stored Markdown through `format_text()` with `FORMAT_MARKDOWN` and no `trusted`/`noclean`/`clean=false` option. Pass an explicit `context`.
- **Why**: Markdown allows raw inline HTML; the cleaner (HTMLPurifier) strips scripts and event handlers after conversion.
- **Do**: `'prompt' => format_text($record->prompttext, FORMAT_MARKDOWN)`. In core, Markdown output is cleaned unless `$clean === false`, and `FORMAT_MARKDOWN` forces `$trusted = false`, so even a trusttext site cannot make it unclean.
- **Source**: C45 lib/classes/formatting.php:151,182-186,250-262 (same note at C53 public/lib/classes/formatting.php:151); AI classes/ai_manager_utils.php:185-188; AI classes/base_purpose.php:225-227 (converts Markdown itself, then `format_text(..., FORMAT_HTML)` to sanitise: more code than needed for us).
- ⚠ `format_text` also runs site filters (MathJax, multilang...). Consider `'filter' => false` on the plan view (own recommendation; `filter` is a valid `format_text()` option, C45 lib/weblib.php:575-588).
- ⚠ Plugins disagree on `noclean`: ATT classes/form/addsession.php:153 and updatesession.php:50 put `'noclean' => true` in **editor options** (so the editor keeps unfiltered HTML for the file API). Do not copy it; `noclean` on display needs a RISK_XSS capability (DOC).

---

## E. SQL, transactions, data integrity

### 17. Placeholders everywhere; `get_in_or_equal` for lists
- **Rule**: Only `$DB` methods with `?` or `:named` placeholders; table names via `{table}`; variable-length lists via `get_in_or_equal()`. Never interpolate request data into SQL.
- **Why**: SQL injection.
- **Do**: `[$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'usid0'); $DB->delete_records_select('attendance_log', "sessionid $sql", $params);` Give each `get_in_or_equal` call its own prefix when two are in one query.
- **Source**: ATT classes/structure.php:957,1182,1398-1400; AI classes/privacy/provider.php:220-222; DOC security policy.

### 18. Multi-table writes in one delegated transaction
- **Rule**: `start_delegated_transaction()`, do all writes, `allow_commit()`, and on `catch` call `$transaction->rollback($e)`.
- **Why**: A failed import/approval otherwise leaves a half-saved plan (items without header, approved flag without lock).
- **Do**:
  ```php
  $transaction = $DB->start_delegated_transaction();
  ... inserts/deletes ...
  $transaction->allow_commit();
  } catch (\Exception $e) { if (isset($transaction) && !$transaction->is_disposed()) { $transaction->rollback($e); } }
  ```
- **Source**: AI tools/telli/classes/local/cleanup_consumption_data.php:129-150 (the only transaction in the three plugins; ATT has none, including `delete_sessions`, structure.php:1384-1407 ⚠). `rollback()` rethrows the exception ("This does not return"): C45 lib/dml/moodle_database.php:2774,2793, so code after it in the catch is dead (AI's `output_error` line is unreachable).
- Trigger events after `allow_commit()` (own recommendation, so no event is logged for a rolled-back write).

### 19. Optimistic locking for edits is your own design
- **Rule**: Carry `timemodified` in the form and compare in the service before update, as PLAN says.
- **Why**: Two tabs overwrite each other.
- **Source**: none of ATT, AI or h5pactivity implements it (grep for `timemodified` comparison found none); keep it, but it is unsourced.

### 20. Write history inside the same transaction as the change
- **Rule**: History row and entry change commit together; deletes write the snapshot first.
- **Why**: A crash between them breaks the inspector audit trail.
- **Source**: reasoned from rule 18; ATT/AI keep no per-row history.

---

## F. Excel export

### 21. Write every teacher-authored cell with `write_string()`; never `write()`
- **Rule**: Use `write_string()` for text, `write_number()`/`write_date()` for numbers/dates. Never `write()` on user text.
- **Why**: `write()` sniffs the value: a leading `=` or `@` becomes a real formula, `http://`/`mailto:` becomes a hyperlink (formula injection, e.g. `=HYPERLINK(...)`).
- **Do**: `write()` branches at C45 lib/excellib.class.php:311-333; ATT calls `$myxls->write($i, $j++, $cell)` on remarks and descriptions (locallib.php:767): ⚠ anti-pattern, do not copy.
- **Source**: C45 lib/excellib.class.php:200-206,311-333; C53 public/lib/excellib.class.php:205-212,326-351.

### 22. `write_string()` forces text type on every 4.5.x; the `'` prefix is newer and has a cost
- **Rule**: Rely on `write_string()` for the "stays text" guarantee; treat the formula-escaping prefix as a version-dependent extra. Do not raise the version floor for it.
- **Why**: A string cell with explicit type and text format is not evaluated by Excel, so `=HYPERLINK(...)` stays inert even without the prefix.
- **Do**: v4.5.0 `write_string()` already does `setFormatCode(NumberFormat::FORMAT_TEXT)` + `setCellValueExplicitByColumnAndRow(..., DataType::TYPE_STRING)` and has no `escape_spreadsheet_formula` (grep count 0 in v4.5.0 `lib/excellib.class.php` and `lib/classes/dataformat.php`). 4.5.15 and 5.3 add `\core\dataformat::escape_spreadsheet_formula($str)` first: it prefixes `'` when the left-trimmed text starts with `=`, `+`, `-` or `@` (not for the single `'-'`). Third-party CVE pages (CVE-2025-67851 / MSA-25-0055 / MDL-72744, titled for CSV) place that addition in 4.5.8, 5.0.4, 5.1.1; Moodle's own advisory was not read.
- **Source**: C45@v4.5.0 lib/excellib.class.php:200-207; C45 (4.5.15) lib/excellib.class.php:203, lib/classes/dataformat.php:181-206; C53 public/lib/excellib.class.php:210, public/lib/classes/dataformat.php:181.
- ⚠ On releases with the prefix, any text starting with `-` (Markdown bullets, "-5") gets a leading `'` that is probably visible in the cell; the cell content therefore differs between 4.5.0-4.5.7 and later. PLAN's bullets-to-"•" conversion avoids most cases. M4 test: open the generated file, assert cell type is string and compare visible text on both CI versions. Optional: `$plugin->requires = 2024100708` (4.5.8) only if you want identical output across versions.

### 23. Export is read-only, owner-scoped, and sets a clean filename
- **Rule**: `require_login` + capability + owner-scoped query before any header is sent; `clean_filename()` for the download name; raise limits for big sets.
- **Why**: Exports are the easiest place to leak every row; header-injection through a module name in the filename.
- **Do**: `$filename = clean_filename($course->shortname . '_' . ... . userdate(time(), '%Y%m%d-%H%M')); \core_php_time_limit::raise(); raise_memory_limit(MEMORY_HUGE);`
- **Source**: ATT export.php:37-40,55-57,83; locallib.php:733-741 (`new MoodleExcelWorkbook("-")`, `send()`, `close()`).

---

## G. Events

### 24. One class per create/update/delete, extending `\core\event\base`
- **Rule**: In `classes/event/`, set `crud` (`c|r|u|d`), `edulevel`, `objecttable` in `init()`; implement `get_name()` (lang string), `get_description()` (non-localised, with user and object ids), `get_url()`, `get_objectid_mapping()` and `validate_data()` for required `other` keys.
- **Why**: Missing `crud`/`edulevel` throws a coding exception; no mapping logs a developer warning when courses are restored.
- **Do**:
  ```php
  protected function init() {
      $this->data['crud'] = 'd';
      $this->data['edulevel'] = self::LEVEL_TEACHING;
      $this->data['objecttable'] = 'attendance_sessions';
  }
  ```
  For private data we have no restore mapping: return `\core\event\base::NOT_MAPPED`.
- **Source**: ATT classes/event/session_deleted.php:40-98; C45 lib/classes/event/base.php:306,557-561 (`NOT_MAPPED` at 81),718-742 (init and `other` validation); AI classes/event/get_ai_response_succeeded.php:33-37.

### 25. Context and trigger
- **Rule**: Create with `Event::create(['objectid' => ..., 'context' => ..., 'other' => [...]])` and call `trigger()` after the write; for deletes add `add_record_snapshot()`.
- **Why**: Logs, report builder and the privacy tool resolve the event through its context.
- **Do**: `$event = session_deleted::create(['objectid' => $this->id, 'context' => $this->context, 'other' => ['info' => ...]]); $event->add_record_snapshot('course_modules', $this->cm); $event->trigger();`
- **Source**: ATT classes/structure.php:1401-1406; AI classes/event/get_ai_response_succeeded.php:63-73 (system context). For user-owned rows use `\context_user::instance($USER->id)` (own recommendation; no inspected plugin does it for events).
- `userid` and `realuserid` (set when an admin is "logged in as") are filled by core, base.php:700; store the same real user in `entry_hist`.

### 26. Keep content out of events and logs
- **Rule**: `other` holds ids, counts and codes only; never plan or entry text, never Markdown.
- **Why**: `other` is JSON-encoded into the log store (base.php:742), readable by admins and kept by the log retention setting, outside the teacher's privacy export unless a log store provider covers it.
- **Do**: AI logs only `'code'` in the event and keeps prompts in its own table with a retention task and anonymising wiper.
- **Source**: AI classes/event/get_ai_response_succeeded.php:67-69; classes/local/data_wiper.php:83-115.
- Events are a second trail, not the legal history: log retention is an admin setting (not verified here), so `entry_hist` stays authoritative.

---

## H. Privacy API

### 27. Implement all three interfaces; `null_provider` is not allowed
- **Rule**: `metadata\provider` + `request\plugin\provider` + `request\core_userlist_provider`.
- **Why**: Any table with user data makes the plugin non-compliant (GDPR export/erase requests fail).
- **Do**: `class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\core_userlist_provider, \core_privacy\local\request\plugin\provider`
- **Source**: C45 mod/h5pactivity/classes/privacy/provider.php:37-41; AI classes/privacy/provider.php:44-47; DOC privacy API (null provider only if no tables, no preferences, no external links; `user_preference_provider` if you store preferences).

### 28. Context for user-owned data: the user context (PLAN is right)
- **Rule**: Expose per-teacher rows in `context_user` of the owner.
- **Why**: The data belongs to the person, not to a course or activity; course deletion or expiry must not touch it and a user data request must find it.
- **Do**: block_rss_client (per-user table, no course) lists the context with `JOIN {context} ctx ON ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel` where `contextlevel = CONTEXT_USER`; calendar does the same for `eventtype = 'user'`.
- **Source**: C45 blocks/rss_client/classes/privacy/provider.php:72-87; calendar/classes/privacy/provider.php:97-112,187-198. Counter-examples: ATT and h5pactivity use the module context because the data belongs to the activity (ATT provider.php:136-150); AI uses the system context for per-user rows (AI provider.php:91-104). DOC: pick "what context you would use when checking role capabilities", so rule 2 and this rule are consistent only because our check is not context-bound.
- ⚠ `delete_data_for_all_users_in_context()` is called with a user context: delete that user's rows (`$context instanceof \context_user` -> `instanceid`), not "everything" (rss_client provider.php:139-143).

### 29. Implement the full method set with context-type guards
- **Rule**: `get_contexts_for_userid`, `get_users_in_context`, `export_user_data`, `delete_data_for_all_users_in_context`, `delete_data_for_user`, `delete_data_for_users`; ignore contexts of the wrong type.
- **Why**: Moodle calls these with contexts you did not return; unguarded code deletes or exports the wrong data.
- **Do**: `if (!$context instanceof \context_user) { return; } $userlist->add_from_sql('userid', "SELECT userid FROM {block_rss_client} WHERE userid = ?", [$context->instanceid]);`
- **Source**: C45 blocks/rss_client/classes/privacy/provider.php:94-104,113-130,139-165.

### 30. Export everything the user owns, including history; delete everything
- **Rule**: Export `plan`, `planitem`, `entry` and `entry_hist` under `[get_string('pluginname', ...)]` with `writer::with_context($context)->export_data()`; delete all of them, including history and the stored Markdown.
- **Why**: Right of access and erasure. DOC: data is "completely deleted, or overwritten if a structure needs to be maintained"; ours is single-owner, so delete (no shared stats to preserve).
- **Do**: `\core_privacy\local\request\writer::with_context($contextlist->current())->export_data([get_string('pluginname','block_rss_client')], (object)['feeds' => $rssdata]);` use `transform::yesno()`/`transform::datetime()` for readable values.
- **Source**: C45 blocks/rss_client/classes/privacy/provider.php:113-130. Where shared data must stay AI anonymises instead (data_wiper.php:105-115); ATT reassigns `takenby` to hard-coded user id `2` (ATT classes/privacy/provider.php:411-418) ⚠ do not copy.

### 31. Describe every table and field in `get_metadata`, including free text that may hold third-party data
- **Rule**: `add_database_table()` per table with a `privacy:metadata:*` string per field; mention that entry text may name students.
- **Why**: Metadata is the legal data inventory; the JSON snapshot column is a field too.
- **Source**: AI classes/privacy/provider.php:49-88 (every column listed per table); lang strings alphabetically in lang/en/local_ai_manager.php (`privacy:metadata:*`, e.g. lines 223-226).

### 32. Purge on account deletion
- **Rule**: Register a `\core\event\user_deleted` observer that deletes the user's rows.
- **Why**: Privacy providers run on requests/expiry, not on every `delete_user()`; orphan rows keep personal data.
- **Do**: `db/events.php`: `['eventname' => '\core\event\user_deleted', 'callback' => '\local_ai_manager\local\observers::user_deleted']`; callback uses `$event->objectid`.
- **Source**: AI db/events.php:28-33, classes/local/observers.php:37-42. ⚠ ATT and h5pactivity do not do this (course-bound data); privacy API coverage alone is the minimum.

### 33. Test the provider with `provider_testcase`
- **Rule**: `final class provider_test extends \core_privacy\tests\provider_testcase`; cover contexts, users in context, export, delete-for-all, delete-for-user, delete-for-users, and "other user's data survives".
- **Why**: DOC warns `advanced_testcase` does not reset the content writer between tests. Neither ATT nor AI has privacy tests (checked: no `provider_testcase` in either).
- **Do**: model on the small user-context test `export_context_data_for_user($user->id, $context, 'block_rss_client')` then `writer::with_context($context)->get_data([...])`.
- **Source**: C45 blocks/rss_client/tests/privacy/provider_test.php:37,50,65,90,120,177,199; mod/h5pactivity/tests/privacy/provider_test.php:45,180,229.

---

## I. Errors

### 34. `moodle_exception` with a lang string id, never user text or raw messages
- **Rule**: `throw new \moodle_exception('stringid', 'local_classprogress', $returnurl, $a)`; define the string in `lang/en`. For permissions use the core id `permissiondenied` or `required_capability_exception`.
- **Why**: Raw strings cannot be translated and tend to leak internals; user text in `$a` is echoed on the error page.
- **Do**: `throw new \moodle_exception('error_nocontextiftenant', 'local_ai_manager');` and `throw new \moodle_exception('permissiondenied');`
- **Source**: AI view_prompts.php:44; C45 mod/h5pactivity/report.php:51-53. ⚠ ATT structure.php:604,1193 throw `new moodle_exception('No such session in this course')` (raw string): do not copy.
- If web services are added later: `self::validate_context($context); require_capability(...)` first in `execute()` (AI classes/external/get_user_quota.php:54-56) and `'ajax' => true` in `db/services.php` (AI db/services.php:33); not needed in v1 (no JavaScript).

---

## Security review checklist

**Every page (`index.php`, `plan.php`, `import.php`, `log.php`, `export.php`, `setup.php`)**
1. `require_once(__DIR__ . '/../../config.php'); require_login();` then `access::require_user()` (rule 2/3).
2. `$PAGE->set_url()` and an explicit page context; `$PAGE->set_context()` before output.
3. All params via `required_param`/`optional_param`, narrowest PARAM type; no superglobals (rule 9).
4. Every id loaded with the owner in the condition (rule 6); no `userid` param.
5. Writes through a moodleform (`get_data()`), or `confirm && confirm_sesskey()` for link actions (rules 10, 11).
6. Destructive action has a confirmation step and a history row (rules 11, 20).
7. Output: `s()` / `format_string()` / `format_text(FORMAT_MARKDOWN)` with default cleaning; no `noclean`, no `{{{ }}}` of raw text (rules 15, 16).
8. Redirect targets built with `moodle_url`, never from a raw request value.
9. Export page: capability, owner scope, `clean_filename`, `write_string` (rules 21-23).

**Every service method (`plan_service`, `entry_service`, `exporter`)**
1. Takes `$userid` explicitly; first statement scopes by owner or throws (rules 6-8).
2. Re-validates inputs (size, dates, enum values, status transitions) regardless of the form (rules 9, 12).
3. Refuses edits to approved plans.
4. Multi-write in one delegated transaction with rollback (rule 18).
5. Writes `entry_hist` in the same transaction (rule 20).
6. Triggers one event after commit, `other` without free text (rules 24-26).
7. Throws `moodle_exception` with a string id (rule 34).

## Threat to control

| Threat | Handled where |
|---|---|
| Non-teacher (student, guest) reaches pages | `access::require_user()` using `local/classprogress:use` (write, RISK_PERSONAL) and `get_user_capability_course(..., limit 1)`; guest blocked by `captype write` (rules 1-3) |
| Teacher B reads/edits/exports teacher A's rows (IDOR) | Owner in every query; count-compare on id lists; PHPUnit "B cannot access A", including an admin user (rules 4, 6-8) |
| Admin sees data via capability bypass | Ownership, not capability, protects rows; admin still hits owner filter (rule 4). Admin DB access accepted risk (PLAN risks table) |
| CSRF on save/delete/approve/import | moodleform sesskey; `confirm_sesskey()` on link actions; confirm page via POST (rules 10, 11) |
| Stored XSS via plan or entry Markdown | `format_text(FORMAT_MARKDOWN)` cleaned; `s()`/`format_string()` for short text; no `noclean` (rules 15, 16) |
| SQL injection | `$DB` placeholders, `get_in_or_equal` (rule 17) |
| Half-saved import/approval | One delegated transaction (rule 18) |
| Lost update from two tabs | `timemodified` check in service (rule 19, own design) |
| Formula injection / hyperlinks in XLSX | `write_string` only (text type on all 4.5.x); cross-version export test (rules 21, 22) |
| Oversized paste / upload exhausts server | Form + service size and day limits, `accepted_types`, no stored file (rules 12, 13) |
| Stale authorisation after role removal | Live check on every page; cache only the link (rule 5) |
| Plan text leaks via logs | Events carry ids only (rule 26) |
| Data left after user/account deletion or request | Privacy provider in user context + `user_deleted` observer + provider tests (rules 28-33) |
| Tampering with history | Rows never updated, written in same transaction; deleting an entry keeps `entry_hist`; events as second trail (rules 20, 26) |

## Contradicts or refines PLAN.md

1. **Capability approach (refine).** `get_user_capability_course()` is a real, documented core function and works for course-level roles, but none of ATT, AI or h5pactivity uses it for gating. Refinements: pass `limit = 1`; do **not** give the capability to archetype `user` (it would admit students and make the function return all courses); do not rely on the session cache for enforcement, only for the link (rule 5); declare `captype write` and `RISK_PERSONAL` (PLAN names neither); note that suspended enrolments still qualify. The "admin setting with allowed roles" fallback has no precedent in the three plugins; AI's own role table is the nearest. PLAN says the capability is "at system level": the `contextlevel` value in `db/access.php` does not change `has_capability` behaviour (no reference to the capability's `contextlevel` anywhere in accesslib.php:432-600), so choose `CONTEXT_COURSE` if you want it listed where teachers' roles are defined (⚠ UI effect not tested).
2. **Privacy in the user context (confirmed).** block_rss_client and core calendar do exactly this. Missing in PLAN: delete history and the stored Markdown too; per-field metadata for the JSON snapshot; a `user_deleted` observer (AI); tests via `provider_testcase` (only core plugins have them as exemplars).
3. **Excel (mostly confirmed).** PLAN's "`write_string()` forces text type" is true on all 4.5.x (checked at v4.5.0). Only the extra `'` formula prefix arrived later (reportedly 4.5.8+, 5.0.4+, 5.1.1+); so the claim "escapes formulas" holds on newer releases only, and the prefix can show as a literal apostrophe before cells starting with `-`. No version bump needed; add the cross-version export test (rule 22).
4. **Missing from PLAN.** Admin bypass in ownership tests (rule 4); `moodle_exception` string-id rule and no raw messages (rule 34); events triggered after commit and without free text (rules 18, 26); file/pluginfile rules if attachments are added later (rule 14); `realuserid` ("logged in as") in `entry_hist` (rule 25); explicit page context.
5. **Over-engineered or unsupported.** The two-tab `timemodified` check and admin-configurable size limits have no precedent in the three plugins; they are cheap, keep them but do not expect to copy code. The per-session capability cache and the "second audit trail" in events add moving parts; events are fine, the cache is optional.

Not verified here: Mustache escaping (`{{ }}` vs `{{{ }}}`), event buffering during transactions, log-store retention defaults, behaviour of `get_user_capability_course` for suspended enrolments.
