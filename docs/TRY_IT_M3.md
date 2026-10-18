# Try it: log what was done in each session (M3)

About 15 minutes on the local test site. Everything here is throw-away: nothing touches your lab on port 8080 or any real course.

**Site:** http://localhost:8000 (Moodle 4.5) · **Login:** `admin` / `test`.
Already set up for you: the school year `2026-2027` with the centre's holidays and three evaluation periods, the course **0372-TEST** (`MP 0372 Gestió de bases de dades (TEST)`), and in it the activity **Class progress MP 0372**, with `test-data/plans/0372.md` imported (60 sessions, Monday 2 h and Friday 2 h, 2026-09-14 to 2027-05-24) and approved.
The test sites were rebuilt for the two-plugin design, so the plan you created earlier is gone. If you prefer your own plan, add a second activity to the course with **Add an activity or resource → Class progress**, import it and approve it.

## 1. Open the activity
1. Go to the course 0372-TEST (http://localhost:8000/course/view.php?id=2) and click **Class progress MP 0372**, or use **Class progress** in the top bar, which lists your plan activities.
2. The page is the **Session log**: **Pending sessions** (planned days up to today with nothing logged, oldest first), **Logged sessions** and **Upcoming sessions**. **Plan and calendar** shows the approved plan, read-only.

## 2. Log sessions
1. Log the most recent pending session (Friday 16 October): **Log**; the page shows what was **Planned for this session**; choose a status (**Done**, **Partly done**, **Not done**), write **What was done**, save. It is logged after its day, so it shows **Late by 2 days** if you do it today (18 October).
2. Backfill an older one, such as 14 September: it shows how many days late it was written.
3. **Partly done** or **Not done** asks for a **Reason**, with an optional note and **Corrective measure**; tick **Continue in the next session** and the next session shows what was **Left over from the previous session**.
4. A session under **Upcoming sessions** can only be logged as **Not done** (a cancellation known in advance).
5. **Add an unplanned session** (an extra class, a recovery): pick its date in the date selector; it cannot be after today.

## 3. Change and check
1. **Edit entry** on a logged session and change its text: the entry shows **Last edited**.
2. **History** shows every version, newest first, with who wrote it. **Delete entry** (on the entry's page) asks for confirmation; the history of the deleted entry stays under **History of deleted entries** on the log page.
3. Optional: create a second teacher, enrol them in the course as a teacher and log in as them: they see and edit the same log (shared by the course's teachers). A student enrolled in the course does not see the activity at all.

## What to report back
- Anything that looks wrong, is confusing or reads badly.
- Whether the fields (status, reason, measure, carry-over) are what the inspection would want to see per session. The XLSX export comes in M4 and will be built from exactly these fields, so this is the moment to ask for a field.
