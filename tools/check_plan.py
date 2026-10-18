#!/usr/bin/env python3
"""Reference parser for the plan import format. The PHP importer must give the same results.

Format (see PLAN.md section 4):
    # <module code> · <module title>              level 1: the module (one per plan)
    ## <RA or block>                              level 2: applies to the days below it
    ### <date> [<hours>h] <title>                 level 3: one day; everything below is its plan (Markdown)

Date: 2026-09-16 or 16/09/2026 (digits 0-9 only), optionally preceded by a weekday (dl dt dm dc dj dv ds dg,
mon..sun, lun..dom, any letter case). Any other word before the date makes the heading a bad_date.
A trailing run of # is removed from a heading only when a space comes before it (REQ-PLN-001).
Headings inside code fences, and headings of level 4 or more, are part of the body. Fences follow CommonMark:
up to 3 spaces, then 3+ backticks or tildes; a backtick fence's info text has no backtick; it closes with the same
character, at least as long (REQ-PLN-008). Text before the first heading is ignored. Several days may share a date.
Input: a leading BOM is ignored; lines end with LF, CRLF or CR (REQ-PLN-015). Every step is linear in the input
(REQ-PLN-016). A plan wrapped in a code block, as AI assistants often answer, is unwrapped: when no heading of level
1 to 3 lies outside code fences and exactly one fenced block holds such headings, that block's content is read as the
plan, with line numbers of the whole text (REQ-PLN-017).

Issues
    errors (block approval):   bad_date, multiple_modules
    warnings (must be confirmed): no_module_heading, no_block, empty_day, out_of_order, on_holiday,
                                  weekday_mismatch, no_class_weekday, outside_year, missing_hours, unclosed_fence,
                                  unwrapped_fence

Usage
    python3 tools/check_plan.py test-data/plans/0372.md --holidays test-data/holidays-centre-2026-27.txt \
        --timetable 0:2,4:2 --year 2026-09-14:2027-06-21
    python3 tools/check_plan.py --selftest        # checks every plan and edge case in test-data/
"""
import argparse
import datetime as dt
import json
import re
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

WEEKDAYS = {
    "dl": 0, "dt": 1, "dm": 1, "dc": 2, "dj": 3, "dv": 4, "ds": 5, "dg": 6,
    "mon": 0, "tue": 1, "wed": 2, "thu": 3, "fri": 4, "sat": 5, "sun": 6,
    "lun": 0, "mar": 1, "mié": 2, "mie": 2, "jue": 3, "vie": 4, "sáb": 5, "sab": 5, "dom": 6,
}
DATE_RE = r"([0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{1,2}/[0-9]{1,2}/[0-9]{4})"
# Linear patterns only (REQ-PLN-016): the closing #s of a heading are removed in code, not by backtracking.
HEADING = re.compile(r"^(#{1,6})[ \t]+(.*)$")
OPEN_FENCE = re.compile(r"^ {0,3}(`{3,}|~{3,})(.*)$")
DAY = re.compile(
    r"^(?:([^\W\d_]+)\.?\s+)?" + DATE_RE +
    r"(?:\s*\[\s*([0-9]+(?:[.,][0-9]+)?)\s*h\s*\])?\s*(.*)$", re.I)
# Same matches as r"\s+[·—–-]\s+|:\s+" and r"^[A-Za-z]{0,4}[0-9]+[\w.-]*$", written so they cannot backtrack
# quadratically (REQ-PLN-016): the lookbehind only lets a whitespace run start a separator at its first character,
# and \w already includes the digits that "[0-9]+" would have to share with it.
SEPARATOR = re.compile(r"(?<!\s)\s+[·—–-]\s+|:\s+")
CODE_WORD = re.compile(r"^[A-Za-z]{0,4}[0-9][\w.-]*$")
RANGE_WORD_THEN_DIGIT = re.compile(r"^(?:to|a|–|—|-|\.\.|→)\s*[0-9]", re.I)
FIRST_DAY, LAST_DAY, MAX_DAYS = dt.date(2000, 1, 1), dt.date(2099, 12, 31), 400  # REQ-CAL-004: dates a teacher may enter
LINE_BREAK = re.compile(r"\r\n|\r|\n")


def split_lines(text):
    """Lines end with LF, CRLF or CR (REQ-PLN-015); a leading byte-order mark is ignored."""
    if text.startswith("\ufeff"):
        text = text[1:]
    return LINE_BREAK.split(text)


def opens_fence(line):
    """(character, length) of the code fence a line opens, or None (REQ-PLN-008)."""
    f = OPEN_FENCE.match(line)
    if f and not (f.group(1)[0] == "`" and "`" in f.group(2)):
        return f.group(1)[0], len(f.group(1))
    return None


def closes_fence(line, fence):
    """Whether a line closes an open fence: its own character, at least as long, nothing but spaces after."""
    return re.match(r"^ {0,3}" + re.escape(fence[0]) + "{" + str(fence[1]) + r",}[ \t]*$", line) is not None


def is_structure(line):
    m = HEADING.match(line)
    return m is not None and len(m.group(1)) <= 3


def unwrap(lines):
    """REQ-PLN-017: (lines to read, number of lines before them, line of the wrapping fence or None)."""
    blocks = []           # [index of the opening line, index of the closing line or len(lines), holds headings]
    fence = None
    outside = False
    for i, line in enumerate(lines):
        if fence is None:
            fence = opens_fence(line)
            if fence:
                blocks.append([i, len(lines), False])
            elif is_structure(line):
                outside = True
        elif closes_fence(line, fence):
            blocks[-1][1] = i
            fence = None
        elif is_structure(line):
            blocks[-1][2] = True
    wrapped = [b for b in blocks if b[2]]
    if outside or len(wrapped) != 1:
        return lines, 0, None
    start, end, _ = wrapped[0]
    return lines[start + 1:end], start + 1, start + 1


def heading_title(rest):
    """Remove a closing run of # only when a space comes before it (REQ-PLN-001), without regex backtracking."""
    t = rest.rstrip(" \t")
    j = len(t)
    while j > 0 and t[j - 1] == "#":
        j -= 1
    if j < len(t) and (j == 0 or t[j - 1] in " \t"):
        t = t[:j]
    return t.strip()


def to_date(text):
    try:
        if "/" in text:
            d, m, y = text.split("/")
            return dt.date(int(y), int(m), int(d))
        return dt.date.fromisoformat(text)
    except ValueError:
        return None


def parse_holidays(text):
    """Holiday lines: a date or 'date to date', then an optional label. '#' lines are ignored."""
    days = {}
    pattern = re.compile(r"^" + DATE_RE + r"\s*(?:(?:to|a|–|—|-|\.\.|→)\s*" + DATE_RE + r")?\s*(.*)$", re.I)
    for raw in split_lines(text):
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        m = pattern.match(line)
        if not m:
            continue
        a = to_date(m.group(1))
        b = to_date(m.group(2)) if m.group(2) else a
        if not a or not b or b < a:
            continue
        if not (FIRST_DAY <= a and b <= LAST_DAY) or (b - a).days + 1 > MAX_DAYS:
            continue  # out_of_range or too_long (REQ-HOL-002, REQ-CAL-004)
        label = m.group(3).strip()
        if not m.group(2) and RANGE_WORD_THEN_DIGIT.match(label):
            continue  # bad_end_date (REQ-HOL-002): a mistyped period end is not a one-day holiday
        d = a
        while d <= b:
            days.setdefault(d, label)
            d += dt.timedelta(days=1)
    return days


def split_module(text):
    m = SEPARATOR.search(text)
    if m:
        return text[:m.start()].strip(), text[m.end():].strip()
    first, _, rest = text.partition(" ")
    if rest and CODE_WORD.match(first):
        return first, rest.strip()
    return None, text.strip()


def parse(text, holidays=None, timetable=None, year=None):
    """Return {module, blocks, days, issues}. timetable: {weekday: hours} or None; year: (start, end) or None."""
    issues = []
    days, blocks = [], []
    modules = []
    block = None
    current = None
    fence = None          # (character, length, line) of the open code fence
    prev_date = None

    def issue(level, code, line, msg):
        issues.append({"level": level, "code": code, "line": line, "message": msg})

    def close_day():
        if current is not None:
            current["content"] = "\n".join(current["body"]).strip()

    lines, before, wrapper = unwrap(split_lines(text))
    for no, line in enumerate(lines, before + 1):
        m = None
        if fence is None:
            f = opens_fence(line)
            if f:
                fence = (f[0], f[1], no)
            else:
                m = HEADING.match(line)
        elif closes_fence(line, fence):
            fence = None
        level = len(m.group(1)) if m else 0
        if level in (1, 2, 3):
            close_day()
            current = None
            title = heading_title(m.group(2))
            if level == 1:
                code, name = split_module(title)
                modules.append({"code": code, "title": name, "line": no})
            elif level == 2:
                block = title
                blocks.append(title)
            else:
                dm = DAY.match(title)
                date = to_date(dm.group(2)) if dm else None
                if not dm or not date:
                    issue("error", "bad_date", no, f"Cannot read a date in: {title}")
                    continue
                hours = float(dm.group(3).replace(",", ".")) if dm.group(3) else None
                current = {"date": date, "hours": hours, "title": dm.group(4).strip(), "block": block, "line": no, "body": []}
                days.append(current)
                weekday = WEEKDAYS.get(dm.group(1).lower()) if dm.group(1) else None
                if dm.group(1) and weekday is None:
                    issue("error", "bad_date", no, f"Not a weekday before the date in: {title}")
                    days.pop()
                    current = None
                    continue
                if dm.group(1) and weekday != date.weekday():
                    issue("warning", "weekday_mismatch", no, f"{dm.group(1)} is not the weekday of {date}")
                if prev_date and date < prev_date:
                    issue("warning", "out_of_order", no, f"{date} comes after {prev_date}")
                prev_date = date
                if holidays and date in holidays:
                    issue("warning", "on_holiday", no, f"{date} is a holiday: {holidays[date]}")
                if timetable is not None and date.weekday() not in timetable:
                    issue("warning", "no_class_weekday", no, f"{date} falls on a weekday with no class")
                if year and not (year[0] <= date <= year[1]):
                    issue("warning", "outside_year", no, f"{date} is outside the school year")
                if hours is None:
                    if timetable is not None and date.weekday() in timetable:
                        current["hours"] = float(timetable[date.weekday()])
                    else:
                        issue("warning", "missing_hours", no, f"No hours for {date}")
                if block is None:
                    issue("warning", "no_block", no, f"{date} has no RA or block heading above it")
        elif current is not None:
            current["body"].append(line)
    close_day()
    if wrapper is not None:
        issue("warning", "unwrapped_fence", wrapper, "The plan was inside a code block; the block was read as the plan")
    if fence is not None:
        issue("warning", "unclosed_fence", fence[2], "A code fence is never closed; it may hide later days")

    for d in days:
        if not d["content"]:
            issue("warning", "empty_day", d["line"], f"{d['date']} has no plan text")
    if len(modules) > 1:
        issue("error", "multiple_modules", modules[1]["line"], "More than one # module heading; import one module at a time")
    if not modules:
        issue("warning", "no_module_heading", 1, "No # module heading")
    return {"module": modules[0] if modules else None, "blocks": blocks, "days": days, "issues": sorted(issues, key=lambda i: i["line"])}


def summarize(result):
    days = result["days"]
    hours = sum(d["hours"] or 0 for d in days)
    return {
        "module": (result["module"] or {}).get("title"),
        "code": (result["module"] or {}).get("code"),
        "blocks": len(result["blocks"]),
        "days": len(days),
        "hours": hours,
        "first": min((d["date"] for d in days), default=None),
        "last": max((d["date"] for d in days), default=None),
        "errors": sorted(i["code"] for i in result["issues"] if i["level"] == "error"),
        "warnings": sorted(i["code"] for i in result["issues"] if i["level"] == "warning"),
    }


def parse_timetable(spec):
    if spec in (None, "", "none"):
        return None
    return {int(a): float(b) for a, b in (p.split(":") for p in spec.split(","))}


def parse_year(spec):
    if not spec:
        return None
    a, b = spec.split(":")
    return dt.date.fromisoformat(a), dt.date.fromisoformat(b)


# ── self test ───────────────────────────────────────────────────────────────────────────────────────────────
def selftest():
    base = ROOT / "test-data"
    holidays = parse_holidays((base / "holidays-centre-2026-27.txt").read_text(encoding="utf-8"))
    exp = json.loads((base / "expected.json").read_text(encoding="utf-8"))
    cases = json.loads((base / "cases" / "expected.json").read_text(encoding="utf-8"))
    year = (dt.date.fromisoformat(exp["school_year"]["start"]), dt.date.fromisoformat(exp["school_year"]["end"]))
    failures = 0

    def check(name, got, want):
        nonlocal failures
        bad = {k: (got.get(k), v) for k, v in want.items() if got.get(k) != v}
        print(("ok   " if not bad else "FAIL ") + name)
        for k, (g, w) in bad.items():
            failures += 1
            print(f"       {k}: got {g!r}, expected {w!r}")

    for code, want in exp["plans"].items():
        text = (base / want["file"]).read_text(encoding="utf-8")
        tt = {int(k): float(v) for k, v in want["timetable"].items()}
        got = summarize(parse(text, holidays, tt, year))
        got["first"], got["last"] = got["first"].isoformat(), got["last"].isoformat()
        check(f"plan {code}", got, {k: want[k] for k in ("code", "module", "blocks", "days", "first", "last", "hours", "errors", "warnings")})

    default_tt = {0: 2.0, 4: 2.0}
    for name, want in cases.items():
        text = (base / "cases" / name).read_bytes().decode("utf-8")  # keep CR and CRLF as written
        tt = default_tt if "timetable" not in want else parse_timetable(want["timetable"])
        started = time.perf_counter()
        got = summarize(parse(text, holidays, tt, year))
        if time.perf_counter() - started > 1.0:
            failures += 1
            print(f"FAIL case {name}: took {time.perf_counter() - started:.2f} s (REQ-PLN-016 allows 1 s)")
        check(f"case {name}", got, {k: v for k, v in want.items() if k not in ("timetable", "req")})
    print("\n" + ("all passed" if not failures else f"{failures} mismatch(es)"))
    return 1 if failures else 0


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("plan", nargs="?")
    ap.add_argument("--holidays")
    ap.add_argument("--timetable", help="weekday:hours pairs, Monday = 0, e.g. 0:2,4:2")
    ap.add_argument("--year", help="start:end, e.g. 2026-09-14:2027-06-21")
    ap.add_argument("--selftest", action="store_true")
    args = ap.parse_args()
    if args.selftest:
        sys.exit(selftest())
    if not args.plan:
        ap.error("give a plan file or --selftest")
    holidays = parse_holidays(Path(args.holidays).read_text(encoding="utf-8")) if args.holidays else None
    result = parse(Path(args.plan).read_text(encoding="utf-8"), holidays, parse_timetable(args.timetable), parse_year(args.year))
    s = summarize(result)
    print(f"{s['code']} · {s['module']}: {s['blocks']} blocks, {s['days']} days, {s['hours']:g} h, {s['first']} to {s['last']}")
    for i in result["issues"]:
        print(f"  {i['level']:7} line {i['line']:>4} {i['code']}: {i['message']}")


if __name__ == "__main__":
    main()
