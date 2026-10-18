#!/usr/bin/env python3
"""Reference parser for the plan import format. The PHP importer must give the same results.

Format (see PLAN.md section 4):
    # <module code> · <module title>              level 1: the module (one per plan)
    ## <RA or block>                              level 2: applies to the days below it
    ### <date> [<hours>h] <title>                 level 3: one day; everything below is its plan (Markdown)

Date: 2026-09-16 or 16/09/2026, optionally preceded by a weekday (dl dt dm dc dj dv ds dg, mon..sun, lun..dom).
Headings inside code fences, and headings of level 4 or more, are part of the body. Text before the first
heading is ignored. Several days may share a date.

Issues
    errors (block approval):   bad_date, multiple_modules
    warnings (must be confirmed): no_module_heading, no_block, empty_day, out_of_order, on_holiday,
                                  weekday_mismatch, no_class_weekday, outside_year, missing_hours

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
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

WEEKDAYS = {
    "dl": 0, "dt": 1, "dm": 1, "dc": 2, "dj": 3, "dv": 4, "ds": 5, "dg": 6,
    "mon": 0, "tue": 1, "wed": 2, "thu": 3, "fri": 4, "sat": 5, "sun": 6,
    "lun": 0, "mar": 1, "mié": 2, "mie": 2, "jue": 3, "vie": 4, "sáb": 5, "sab": 5, "dom": 6,
}
DATE_RE = r"(\d{4}-\d{2}-\d{2}|\d{1,2}/\d{1,2}/\d{4})"
HEADING = re.compile(r"^(#{1,6})[ \t]+(.*?)[ \t]*#*[ \t]*$")
FENCE = re.compile(r"^\s*(```|~~~)")
DAY = re.compile(
    r"^(?:(" + "|".join(sorted(WEEKDAYS, key=len, reverse=True)) + r")\.?\s+)?" + DATE_RE +
    r"(?:\s*\[\s*(\d+(?:[.,]\d+)?)\s*h\s*\])?\s*(.*)$", re.I)
SEPARATOR = re.compile(r"\s+[·—–-]\s+|:\s+")
CODE_WORD = re.compile(r"^[A-Za-z]{0,4}\d+[\w.-]*$")


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
    for raw in text.splitlines():
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
        label = m.group(3).strip()
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
    in_fence = False
    prev_date = None

    def issue(level, code, line, msg):
        issues.append({"level": level, "code": code, "line": line, "message": msg})

    def close_day():
        if current is not None:
            current["content"] = "\n".join(current["body"]).strip()

    for no, line in enumerate(text.splitlines(), 1):
        if FENCE.match(line):
            in_fence = not in_fence
        m = None if in_fence else HEADING.match(line)
        level = len(m.group(1)) if m else 0
        if level in (1, 2, 3):
            close_day()
            current = None
            title = m.group(2).strip()
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
                if dm.group(1) and WEEKDAYS[dm.group(1).lower()] != date.weekday():
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
        text = (base / "cases" / name).read_text(encoding="utf-8")
        tt = default_tt if "timetable" not in want else parse_timetable(want["timetable"])
        got = summarize(parse(text, holidays, tt, year))
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
