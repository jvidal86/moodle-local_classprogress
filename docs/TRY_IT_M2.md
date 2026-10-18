# Try it: import, preview, approve and lock (M2)

> **Superseded by [TRY_IT_M3.md](TRY_IT_M3.md):** since M3 a plan lives in the course activity (Add an activity or resource → Class progress), not under a school year in the top-menu page. Kept as the record of the R2 test.

About 15 minutes on the local test site. Everything here is throw-away: nothing touches your lab on port 8080 or any real course.

**Site:** http://localhost:8000 (Moodle 4.5) · **Login:** `admin` / `test` · Site administrators can use the plugin for themselves (decided in M1).
The files below are in this repository: `test-data/holidays-centre-2026-27.txt` and `test-data/plans/0372.md`, `0374.md`, `0377.md` (your real plans, converted to the import format).

## 1. School year
1. Click **Class progress** in the top bar, then **New school year**.
2. Name `2026-2027`, first day 14 September 2026, last day 21 June 2027 (pick them in the day, month and year menus or with the calendar button).
3. In **Holidays and non-teaching days**, paste the whole content of `test-data/holidays-centre-2026-27.txt` (comment lines starting with `#` are fine).
4. Optional: **Evaluation periods** `1a avaluació` 2026-09-14 to 2026-11-27, `2a` 2026-11-30 to 2027-02-26, `3a` 2027-03-01 to 2027-06-21 (tick **Enable** next to each date first).
5. Save. Try once a deliberate typo in a holiday line, for example `2026-12-22 to 2027-1-07 Nadal`: the whole text should be refused, with that line named.

## 2. Plan
1. **New plan** in that school year. Module code and title: anything (the import will replace them). **Weekly timetable:** Monday 2, Friday 2.
2. Save. The plan page shows the calendar: it should say **60 sessions** between 2026-09-14 and 2027-05-24 if you set those plan dates (your own Word sheet's number), and how many sessions each holiday removed.

## 3. Import, preview, save
1. On the plan page, **Import plan text**. Upload `test-data/plans/0372.md`, or paste its text.
2. **Preview:** it should read `MP 0372 · Gestió de bases de dades`, 7 blocks, 60 days, **No errors or warnings**, and tell you the module heading replaces what you typed.
3. **Save draft.** The plan page now lists the days grouped by RA, with dates, hours and each day's text. Check the last day's text before approving: the preview does not show day texts, so this is where a stray remark would appear.
4. Try **Change the text** with mistakes and preview again (do not save): a date that does not exist (`### 2026-02-30 [2h]`), a day on a holiday (`### 2026-10-12 [2h]`), a Wednesday (`### 2026-10-14 [2h]`), a second `#` heading. Errors block saving; warnings only need reading.
5. The import page also shows **Instructions for my AI assistant**: copy them with one of your own plans into a chat and paste back exactly what the assistant returns, even if it wraps the plan in a ``` code block with a sentence around it: the preview should say the wrapper was removed (REQ-PLN-017).

## 4. Approve and lock
1. **Approve plan.** If the text had warnings you must tick that you read them; approval asks for confirmation and is final.
2. Check the plan is now read-only: no edit, import or delete actions; the landing page shows it as approved without Delete.
3. Change a holiday in the school year afterwards: the approved plan's dates and hours must not move.

## What to report back
- Anything that looks wrong, is confusing, or reads badly (English wording is fine to criticise; Catalan and Spanish come in M5).
- Whether the preview messages are clear enough for a colleague.
- Whether your other plans (`0374.md`, `0377.md`, or one written by an AI from the instructions) import as you expect.
Your sign-off closes review round R2 (`PLAN.md` section 6).
