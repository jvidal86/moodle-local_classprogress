# UX review: session log and entry form (mod_classprogress 0.9.0)

> **Outcome (2026-10-21).** The `UIX` requirements of M6 were agreed from this review and the teacher's answers. Where they differ, the requirements win:
> - **The card (UX-1)** shows the session's plan with a "Log this session" button, or "Log a cancellation" for a later session, chosen by the teacher. It does not embed the form.
> - **The lists (UX-2):** upcoming and logged sessions each list the next or newest five, and fold the rest. They are not folded whole.
> - **Questions 1 to 4** are settled by REQ-UIX-001 and 002: the card shows the first session of today with no entry, or else the next session. Missed sessions stay in the pending list just below it.
> - **Question 5:** the late badge stays, as REQ-LOG-004 says.
>
> The screenshots named below were kept outside the repository.

Reviewed 2026-10-21 on http://localhost:8000 (Moodle 4.5.15, Boost), activity `view.php?id=2` (plan 0372: 60 sessions, 10 logged, 0 pending, 50 upcoming; today is Wednesday 21 October and the next class is Friday 23 October). Only GET requests and client-side select changes were used; no form was submitted.

Captures are in this folder. Files starting with `c-` were taken after `networkidle` plus about 2 s, with the course-index drawer and the developer performance footer hidden client side (CSS classes removed, no user preference saved). Files without the prefix are the raw first pass, kept as evidence of the drawer artifact. `probe.json` holds the DOM measurements.

**The teacher's answers (2026-10-21) are the brief this revision is built on:**
- They log on the classroom PC at the end of class, on a laptop later the same day, and on their phone.
- The first view should be today's session, or the next one if none is due today, with its plan and the log controls right there. Everything else goes below or is collapsed.
- No one-click logging: they always write what was done themselves. The form's fields stay; only its layout and flow may change.
- Their pain points: the page is too long, there are too many clicks, and the plans of future lessons can't be read on the log page, not even in a collapsible form.

## 0. The two things to verify

- **Phone sideways scroll: real.** At 390 px the document is 706 px wide (`scrollWidth` 706). The cause is the six-column **Logged sessions** table, which is 698 px wide: Date, RA or block, Title, Status, Written on and actions. The Written-on cell is `text-nowrap` and holds the late badge (193 px). The actions cell is also `text-nowrap`, with "Edit entry · History" (144 px). Nothing wraps the table in a responsive container (`templates/log_view.mustache:76`, `:94`, `:95-98`). Because the whole page scrolls sideways, the breadcrumb, headings and forms move with it. The Pending and Upcoming tables have four columns and almost fit. Evidence: `c-phone-log.png`, `crop-c-phone-log-upcoming.png`, `probe.json` → `phone.log_overflow`.
- **Unplanned form labels: present. The earlier capture was wrong.** In the DOM, "Date of the session" is the visible label of the date group. Day, Month and Year are the standard `date_selector` sub-labels, hidden with `sr-only`, as in core. "What was done" is a visible `<label for="id_content">`. The earlier capture lost the labels because the fixed course-index drawer was painted over the label column in a full-page screenshot; the same thing hid "Status" and "Reason" in `desktop-edit-not_done.png`. The clean capture is `c-desktop-unplanned.png`.
- **Catalan: not testable live.** Neither site has the Catalan language pack: there is no `moodledata/lang` on 8000 or 8001. With `?lang=ca` the page stays English (`html lang="en"`), so `desktop-log-ca.png` and `phone-log-ca.png` are English. The strings were therefore checked in the file. The Catalan labels in the cells that already overflow are longer: "Edita el registre · Historial" is 29 characters against 20 for "Edit entry · History" (`lang/ca/classprogress.php:71,169`), and "Amb 35 dies de retard" is 21 against 15 (`:205`). Both sit in `text-nowrap` cells, so in Catalan the phone overflow gets worse.
- **Moodle 5.3 (8001): no live comparison.** That site has no Class progress activity (cm ids 1 to 7 all error), so nothing could be compared. The templates are the same on both versions.

## 1. First impressions

A teacher who opens the activity sees the course header, then "Session log" with an introduction about lateness, and four actions on one line. Below come three stacked tables: **Pending**, **Logged** (oldest first, every row with an orange "Late by N days" badge after a backfill) and **Upcoming**, which has all 50 future sessions, each with its own "Log" button. Today, with nothing pending, the page leads with "Nothing pending" and the next class (23 October) is 1,343 px down on desktop and 2,388 px down on a phone. The page is 4,142 px tall on desktop and 9,142 px on a phone, and it grows by one logged row per class until June. No row shows its plan: a session's planned content appears only on its own form page or under "Plan and calendar". On a class day, the session to log does come first under **Pending**.

Logging that session means opening a separate form. It shows the planned day twice (heading and card) and a **Status** select already set to *Done*. Then come the text box, Save, and back to the log: every session is one round trip. The teacher's own Word sheet (`~/CFGS/seguiment-programacio`) has all the rows on one page and one click per status box.

**Counts, starting from the course page** (a click is a press or tap; a page load is a server round trip). The teacher always writes what was done, so every row includes clicking into the text box. "Target" is the count after UX-1, UX-5 and UX-6.

| Task | Clicks now | Loads now | Clicks target | Loads target |
|---|---|---|---|---|
| Today's session, done | 4: activity, Log, text box, Save | 3 | 3: activity, text box, Save | 2 |
| Today's session, not done | 8: activity, Log, Status ×2, text box, Reason ×2, Save (+1 per optional note or measure) | 3 | 6: activity, radio, text box, Reason ×2, Save | 2 |
| Today's session, partly done | 8, or 9 with "Continue in the next session" | 3 | 6 or 7 | 2 |
| Next session cancelled in advance | 7 to 8 after scrolling past every logged row. If Status is left at the preselected *Done*, add an error round trip (+1 load) | 3 or 4 | 6: activity, open "Cancelled?", text box (optional), Reason ×2, Save | 2 |
| Backfill of N missed days | 3N + 1 | 2N + 1 | 2N + 2 | N + 2 |
| Read the plan of a session 3 weeks ahead | Plan and calendar, then find the day, or that session's form (2+, plus scrolling) | 2 | expand the Upcoming section, then the row (2) | 1 |

## 2. Findings, ranked by the teacher's pain points

### UX-1 · The first view isn't today's session, and logging always takes another page
- **Evidence:** `c-desktop-log-fold.png`, `c-phone-log-fold.png`. `view.php:56-82` puts a heading, the introduction and four actions above three tables. The form exists only on `entry.php`. `probe.json`: `upcomingTop` is 1,343 px on desktop and 2,388 px on a phone.
- **Problem:** The teacher's first view should be today's session, or the next one, with its plan and the log controls. Today it is a list, and logging costs a click and a page load before anything can be typed.
- **Proposal:** Add a **focus card**, `data-region="log-today"`, as the first thing under the page heading.
  - **Which session it shows:**
    - The session dated today (from `plan_log->today`), when it has no entry. The card holds the planned day, as `entry_context` renders it, the left-over from the previous session, and the **same `entry_form`** with all its fields.
    - Today's session when it is already logged: the card shows it compactly ("Logged · Done · first line of what was done · Edit entry"), followed by the next session's plan. See Q1.
    - The next session, when none is dated today. The card shows its plan, opened. Under REQ-LOG-009 only a cancellation can be logged for it, so the form there has the status fixed to *Not done*, inside a closed `<details>` labelled "Cancelled in advance? Log it".
  - **How it saves:** The form's action is `entry.php?id=…&itemid=…`, so saving and the stale-edit check (REQ-LOG-005, REQ-DAT-003) stay in one place. After a save, `entry.php` already redirects to the log (`entry.php:125`). After a validation error, the teacher lands on the full form page with the errors shown, which is acceptable.
  - **The pending table:** It keeps listing every pending session, today's included, with a "Today" badge. The card repeats one row; in exchange, the "not logged" count still equals the pending rows (REQ-EXP-009), and the order stays oldest first (REQ-LOG-001).
  - **The actions bar** ("Add an unplanned session", Plan and calendar, Export, History of deleted entries) moves below the card, inside the same `data-region="log-actions"` wrapper.
- **Effort:** M. The card uses data the log already loads: `entry_service::log()` reads the full item rows and today's date. It reuses `entry_context` and `entry_form`. The only new code is in `view.php` and a small template.
- **REQ:** REQ-LOG-001 (a presentation note: "today's session is also shown first"), REQ-LOG-006 (left-over in the card), REQ-LOG-009, REQ-REL-007 (the log page now holds a form, so its axe check covers it), REQ-EXP-009 (unchanged, because of the duplicated row).
- **Behat:**
  - `log.feature:35` asserts that "Presentació" appears before "Entorn" on the page. With today's "Entorn" in the card at the top, that fails. Scope the check to the pending table: one step.
  - Everything else holds. The "Log" link in today's pending row stays (`log.feature:36`). Today's rows stay in `log-pending` (`log.feature:33`, `export.feature:42`). The count step `behat_mod_classprogress.php:158` still counts `log-pending tbody tr`. The keyboard scenario `accessibility.feature:140` has no session today, so it gets no card; it would still pass with one, because the card sits outside `log-pending`.
  - The accessibility scenario 2 log page will hold the closed cancellation form for 2027-01-15. Its axe step then covers that form, which is wanted.
  - Add one scenario: log today's session from the card.

### UX-2 · The page is too long and grows all year
- **Evidence:** page height 4,142 px on desktop and 9,142 px on a phone with 10 of 60 sessions logged. 50 "Log" buttons under Upcoming (`log_view.mustache:108-131`). Logged is listed oldest first.
- **Proposal** (no plugin JavaScript; native `<details>`, which the keyboard can reach):
  - **Pending** stays open. It only holds missed sessions, and it is short.
  - **Logged sessions** goes in a closed `<details>`, newest first, summarised as "Logged sessions (10) · 9 written late" (REQ-LOG-004: each row inside keeps its badge).
  - **Upcoming sessions** goes in a closed `<details>`, summarised as "Upcoming sessions (49) · next: 2026-10-30". Its rows' buttons become **Log a cancellation** ("Registra una anul·lació"), the only thing REQ-LOG-009 allows.
  - The secondary actions (UX-1) and "History of deleted entries" go below.
- **Effort:** S.
- **REQ:** REQ-LOG-001 (only Pending is ordered), REQ-LOG-004, REQ-REL-007.
- **Behat:**
  - The non-JavaScript features read the text inside a closed `<details>`, so `log.feature` and `journey.feature` don't change.
  - `accessibility.feature` runs with JavaScript and clicks rows that would sit in the collapsed Logged section: "Edit entry" at `:107` and `:114`, "History" at `:110`. Each needs one step that opens "Logged sessions" first, so three steps are added.
  - Keep these unchanged: the `[data-region='log-actions']` wrapper around "Export" (`journey.feature:107`, `export.feature:43`), and the labels "Add an unplanned session" (`journey.feature:89`, `log.feature:58`, `accessibility.feature:104`) and "History of deleted entries" (`log.feature:85`, `accessibility.feature:119`). The wireframes shorten these labels only to fit the sketch.
  - No scenario clicks an upcoming "Log", so the relabelling is free.

### UX-3 · Future lessons' plans can't be read on the log page
- **Evidence:** `log_view.php:100-108`: rows export only the date, block and title. `c-desktop-log.png`: the six Bloc A rows even have an empty Title, because those day headings carry none (REQ-PLN-004).
- **Problem:** This is the teacher's own complaint: to see what is planned three weeks ahead, they have to leave for "Plan and calendar" or open each session's form.
- **Proposal:** Make the Session cell of every row (pending, logged and upcoming) a `<details>`.
  - The `<summary>` is the title or, when the title is empty, the first line of the planned content shortened to about 60 characters (`shorten_text`).
  - The body is the planned content, through `format_text(…, FORMAT_MARKDOWN)` as in `entry_context.php:74`, followed by the hours.
  - Logged rows also show, inside the same body, "What was done:" with the entry's text, so the teacher can reread an entry without opening it.
  - The rows stay `<tr>`, so the table semantics and the Behat locators are kept.
- **Effort:** S. `plan_log->items` already holds the full rows, so no new query is needed. For a 400-day plan, that is up to 400 `format_text()` calls.
- **REQ:** none (display only). REQ-LCK-003 (safe display) is met by `format_text` with cleaning, as on the entry page.
- **Behat:** none. The summaries carry the same titles, the rows the same dates. The new text in a row must not meet a `should not see … in the "X" "table_row"` assertion: `log.feature:43` checks "Late by", which is safe, and `accessibility.feature:146` checks "Presentació" in `log-pending` after logging it, also safe. Add one step: the planned content of an upcoming row is visible after expanding it.

### UX-4 · The log page scrolls sideways on a phone (706 px at 390)
The teacher logs on a phone too, so this is a real use case, not an edge case.
- **Evidence:** section 0, `c-phone-log.png`, `crop-c-phone-log-upcoming.png`. The block name is repeated in every row and wraps to six lines.
- **Proposal:** Keep the tables.
  - Hide the **RA or block** column and the **Written on** column below md (`d-none d-md-table-cell`). On small screens, show the block inside the row's expanded body (UX-3).
  - Below md, move the late badge under the date (`d-md-none`).
  - Remove `text-nowrap` from the actions cell and stack its links vertically.
  - Wrap each table in `.table-responsive` as a safety net.

  These classes exist in both Bootstrap 4 and 5. Do not insert block-header rows inside `tbody`: the count step `behat_mod_classprogress.php:158` would count them as pending sessions.
- **Effort:** S.
- **REQ:** REQ-LOG-004 (the badge stays visible on every row), REQ-REL-007.
- **Behat:** none. Behat's default window is wider than md, so all the columns stay.

### UX-5 · Status is a select preset to *Done*, even where *Done* is refused
- **Evidence:** `entry_form.php:72-78` (a select with no empty option, so it falls back to the first one). `entry.php:129-135` sets no status. `probe.json` → `item11_status: "done"` on `entry.php?id=2&itemid=11` (23 October, upcoming). `c-phone-item.png`.
- **Problem:** Choosing a status costs two clicks and hides the options. For an upcoming session the preset *Done* always fails, since REQ-LOG-009 allows only *Not done*, and the teacher learns this after a save round trip.
- **Proposal:** Same field, new widget.
  - Make the status three radio buttons in a `group` labelled "Status", one click each, all options visible and stacked on a phone. `hideIf` works with radio groups (to verify on 5.3).
  - For a session after today, show the status as fixed text, "Not done (cancellation known in advance)", with a hidden `status=not_done`.
- **Effort:** S.
- **REQ:** REQ-LOG-002, REQ-LOG-009 (now enforced up front, unchanged).
- **Behat:** each "Status" field step changes to select the radio by its label: `journey.feature:78`, `journey.feature:85`, `log.feature:46` and `accessibility.feature:98`. Four steps in three files.

### UX-6 · A backfill costs a round trip per session
- **Evidence:** `entry.php:125` always redirects to the log.
- **Proposal:** Add a second submit button, **Save and log the next pending session (N left)**, shown when another pending session follows this one. It redirects to that session's form with the "Entry saved." notice. Build it as a button group of two `submit` elements plus cancel, in place of `add_action_buttons`. "Save entry" keeps its label.
- **Effort:** S.
- **REQ:** none.
- **Behat:** none (the "Save entry" presses are unchanged). Add one scenario.

### UX-7 · Which fields are required only shows up after saving; the deviation part pushes Save off a phone screen
- **Evidence:** `entry_form.php` has no `addRule` call. Required-ness lives only in `validation()` (`:116-130`, and in the local plugin's `entry_rules.php:144-157`), so there is no asterisk and no client-side check. The three text boxes have 6 rows each (`:64`). With *Not done*, Save is at y≈1,400 on a phone, against an 844 px viewport (`probe.json`, `c-phone-edit-not_done.png`).
- **Proposal:** The fields and the `hideIf` rules stay as they are.
  - Put the conditional requirement in the label: "Reason (required)" / "Motiu (obligatori)".
  - Relabel the text box "What was done (optional)" when the status is *Not done*. Because a label can't follow the status, show two labelled hints toggled by `hideIf` on `static` elements.
  - Set the main text box to 4 rows, and the note and the measure to 2. That brings Save on a phone for *Not done* to about 1,000 px.
- **Effort:** S.
- **REQ:** REQ-LOG-002, REQ-LOG-003 (unchanged), REQ-REL-007 (every field keeps a label).
- **Behat:** none. `accessibility.feature:101` asserts the error text, which stays. Relabelling "What was done" itself would break the 13 steps that name it (journey ×4, log ×6, accessibility ×3), so only a hint is added beside the label.

### UX-8 · On the entry page, History and Delete sit between the plan and the form
- **Evidence:** `templates/entry_context.mustache:67-75`, `c-desktop-edit-partial.png`. "History" is a grey `btn-secondary` placed above Status, and Delete is one tab stop before Status. The date and title appear twice (heading `entry.php:139`, and the card meta line `entry_context.mustache:56`). 18 tab stops come before Status on a phone, 21 on desktop.
- **Proposal:**
  - Show the planned card once: the heading carries the date and title, and the card shows "2 h · block" and the content.
  - Move "Written on … · Last edited … · History · Delete entry" into one muted line below the Save buttons, as links.
  - The same layout serves the focus card (UX-1).
- **Effort:** S.
- **REQ:** REQ-LOG-004 (written-on and late are still shown), REQ-LOG-005.
- **Behat:** none. "Delete entry" is clicked by its link text (`log.feature:80`, `accessibility.feature:115`).

### UX-9 · The history page doesn't name the session
- **Evidence:** `c-desktop-history.png`, `history.php:47,68`.
- **Proposal:**
  - Make the heading "History · 2026-09-14 · <title>", with the same title fallback as UX-3.
  - Replace "Continue" with "Back to the session log".
- **Effort:** S.
- **REQ:** REQ-HIS-002.
- **Behat:** `log.feature:78` presses "Continue" on this page and would need the new label. The other "Continue" presses (`log.feature:82`, `:107`, `accessibility.feature:118`) are the delete confirmation, which is unchanged.

## 3. Target layouts

The log page on desktop, on a class day, at the end of class (1366 px):
```
Class progress MP 0372
[ Class progress ]  Settings  More v
-------------------------------------------------------------------------------------------
Session log
+- Today · Fri 2026-10-23 · B·S5 · Posem en marxa el servidor ------ data-region=log-today -+
| 2 h · Bloc B · Ronda 1 · La botiga bàsica (RA2–RA4)                                         |
| • Guia: guia-professor-bloc-b.md, «Sessió 5»                                                |
| [Left over from the previous session: …]          (only when there is one)                 |
|                                                                                             |
| Status            (•) Done   ( ) Partly done   ( ) Not done                                 |
| What was done     [ 4 rows                                                               ]  |
| Reason (required) [ Choose... v ]          <- Partly done / Not done only (hideIf)          |
| Note              [ 2 rows ]                                                                |
| Corrective measure[ 2 rows ]                                                                |
|                   [ ] Continue in the next session   (Partly done only)                     |
| [Save entry]  [Save and log the next pending session (1 left)]                              |
+---------------------------------------------------------------------------------------------+
+ Unplanned session · Plan and calendar · Export                        data-region=log-actions

Pending sessions (2)
 Date        Session                                                Actions
 2026-10-19  > B·S4 · De l'E/R al relacional                          Log
 2026-10-23  > B·S5 · Posem en marxa el servidor   [Today]            Log
> Logged sessions (9) · 8 written late                          <- closed <details>, newest first
> Upcoming sessions (49) · next 2026-10-26                       <- closed <details>
    2026-10-26  > B·S6 · BotigaDB al servidor, i el seu plànol         Log a cancellation
                  2 h · Bloc B …  • Guia: …«Sessió 6»          <- one row expanded
History of deleted entries
```

The log page on a phone, on a day without a class (390 px, no sideways scroll):
```
Session log
+- Next · Mon 2026-10-26 --------+
| B·S6 · BotigaDB al servidor,   |
| i el seu plànol · 2 h          |
| • Guia: …«Sessió 6»            |
| > Cancelled in advance? Log it |
+-------------------------------+
+ Unplanned session
Plan and calendar · Export

Pending sessions
Nothing pending.
> Logged sessions (10)
> Upcoming sessions (49)
   Date        Session
   2026-10-26  > B·S6 · BotigaDB …
               Log a cancellation
   2026-10-30  > B·S7 · CREATE TABLE …
History of deleted entries
```

The entry form on desktop (`entry.php`, the same form the focus card embeds):
```
2026-10-19 · B·S4 · De l'E/R al relacional
+- Planned for this session ------------------------------------------------+
| 2 h · Bloc B · Ronda 1 · La botiga bàsica (RA2–RA4)                        |
| • Guia: guia-professor-bloc-b.md, «Sessió 4»                               |
+---------------------------------------------------------------------------+
Status             (•) Done   ( ) Partly done   ( ) Not done
What was done      [ 4 rows                                                 ]
Reason (required)  [ Choose... v ]                 (Partly / Not done only)
Note               [ 2 rows ]
Corrective measure [ 2 rows ]
                   [ ] Continue in the next session (Partly done only)
[Save entry]  [Save and log the next pending session (3 left)]  [Cancel]
Written on 2026-10-19 · Last edited 19 Oct 12:37 · History · Delete entry  (when editing)
```

The entry form on a phone (Save at about 650 px for *Done*, about 1,000 px for *Not done*):
```
2026-10-19 · B·S4 · De l'E/R
al relacional
+- Planned ---------------------+
| 2 h · Bloc B · Ronda 1         |
| • Guia: …«Sessió 4»            |
+-------------------------------+
Status
(•) Done
( ) Partly done
( ) Not done
What was done
[ 4 rows                      ]
[Save entry]
[Save and log next (3 left)]
[Cancel]
History · Delete entry
```

## 4. Questions only the teacher can answer

1. **After today's session is logged:** should the first view switch to the next session's plan, or keep showing today's entry (with "Edit") until the day ends? The proposal does both: a compact logged line, then the next plan.
2. **Missed sessions when there is no class today:** if older sessions are still pending, should the first view offer the oldest missed one to log, or the next future session's plan, as you described? Missed sessions stay listed just below in either case.
3. **Two sessions on the same day** (allowed by REQ-PLN-010): does that happen in your plans? If so, should the card stack two forms, or log one and offer the other?
4. **Cancellations in advance:** how often do you log one before the day (a strike, a trip)? If rarely, the cancellation form can stay inside a closed "Cancelled in advance?" in the card, as proposed.
5. **The late badge after a backfill:** every backfilled row turns orange. Do you want "Late by N days" on every row of the log, or only on the entry and in the export? Showing it only there would change REQ-LOG-004, which today requires it wherever the entry appears.
