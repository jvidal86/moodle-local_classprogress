#!/usr/bin/env python3
"""Build the importer test data from the teacher's CFGS repository.

Reads, from ~/CFGS (override with --cfgs):
  seguiment-programacio/calendari-centre-2026-27.yaml   shared school calendar
  seguiment-programacio/<modul>.yaml                    module name and timetable
  ASIX/<modul>/guia-professor-amb-dates-26-27.md        one row per class day
  ASIX/0372_*/planificacio-curs.md, ASIX/0374_*/planificacio-curs.md, ASIX/0377_*/calendari-0377.md
                                                        block and RA names
  seguiment-programacio/seguiment-0372-26-27.docx       the six block A days taught before the replan

Writes, to test-data/ (override with --out):
  holidays-centre-2026-27.txt   holiday lines in the plugin's format (single dates and periods)
  plans/<modul>.md              plans in the importer format: # module, ## RA or block, ### day
  expected.json                 what the importer must find in each plan

Run: python3 tools/build_test_data.py
The generated files are committed, so the tests do not need the CFGS repository.
"""
import argparse
import datetime as dt
import json
import re
from pathlib import Path

import yaml

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
WEEKDAY_CA = ["dl", "dm", "dc", "dj", "dv", "ds", "dg"]

MODULES = {
    "0372": {"dir": "ASIX/0372_gestio_bases_dades", "yaml": "0372.yaml", "dates": "iso"},
    "0374": {"dir": "ASIX/0374_administracio_sistemes_operatius", "yaml": "0374.yaml", "dates": "ca"},
    "0377": {"dir": "ASIX/0377_administracio_sgbd", "yaml": "0377.yaml", "dates": "iso"},
}


def d(x):
    return x if isinstance(x, dt.date) else dt.date.fromisoformat(str(x))


# ── holidays ────────────────────────────────────────────────────────────────────────────────────────────────
def build_holidays(cal):
    rows = []
    for day, label in cal["festius"].items():
        rows.append((d(day), d(day), label))
    for start, end, label in cal["vacances"]:
        rows.append((d(start), d(end), label))
    rows.sort()
    out = [
        f"# Holidays and non-teaching days · {cal['centre']} · {cal['curs']}",
        "# Source: seguiment-programacio/calendari-centre-2026-27.yaml (same dates as the school's official calendar).",
        "# One entry per line: a single date or a period, then an optional label. Lines starting with # are ignored.",
        "",
    ]
    for a, b, label in rows:
        out.append(f"{a.isoformat()} {label}" if a == b else f"{a.isoformat()} to {b.isoformat()} {label}")
    out += ["", "# School notices: classes are held, but not as usual (the importer treats these as information only)."]
    for day, label in sorted(cal["avisos"].items()):
        out.append(f"# {d(day).isoformat()} {label}")
    return "\n".join(out) + "\n"


# ── guide tables ────────────────────────────────────────────────────────────────────────────────────────────
ROW = re.compile(r"^\| \*\*d[a-z] (\d\d)/(\d\d)/(\d{4})\*\* \| \*\*(.+?)\*\* \| (.*) \| ([^|]*) \|\s*$")


def guide_rows(path):
    rows = []
    for line in path.read_text(encoding="utf-8").splitlines():
        m = ROW.match(line)
        if m:
            day = dt.date(int(m.group(3)), int(m.group(2)), int(m.group(1)))
            rows.append({"date": day, "session": m.group(4).strip(), "what": m.group(5).strip(), "page": m.group(6).strip()})
    return rows


def clean(s):
    return re.sub(r"[*`]", "", s).strip()


def fmt_date(day, style):
    if style == "ca":
        return f"{WEEKDAY_CA[day.weekday()]} {day:%d/%m/%Y}"
    return day.isoformat()


# ── block names ─────────────────────────────────────────────────────────────────────────────────────────────
def blocks_0372(text):
    meta = {}
    for m in re.finditer(r"^\| ([A-G])\. (.+?) \| (RA[^|]+?|tots) \|", text, re.M):
        meta[m.group(1)] = f"Bloc {m.group(1)} · {m.group(2).strip()} ({m.group(3).strip()})"
    return meta


def blocks_0374(text):
    meta = {}
    for m in re.finditer(r"^\| \*\*(\d)\*\* \| (.+?) \| (RA[^|]+?) \| \d+ \|", text, re.M):
        meta[m.group(1)] = f"Bloc {m.group(1)} · {m.group(2).strip()} ({m.group(3).strip()})"
    return meta


def blocks_0377(text):
    meta = {}
    for m in re.finditer(r"^\| \*\*(B\d) · (.+?)\*\* \| .*? \| (RA[^|]+?) \|", text, re.M):
        meta[m.group(1)] = f"{m.group(1)} · {m.group(2).strip()} ({m.group(3).strip()})"
    return meta


# ── block A of 0372 (taught before the replan, only in the Word sheet) ─────────────────────────────────────
def block_a_days(docx_path):
    try:
        import docx
    except ImportError:
        return []
    days = []
    for t in docx.Document(str(docx_path)).tables:
        for r in t.rows:
            cells = [c.text.strip() for c in r.cells]
            m = re.match(r"d[a-z] (\d\d)/(\d\d)/(\d{4})$", cells[0])
            if m and cells[1].startswith("Bloc A"):
                days.append(dt.date(int(m.group(3)), int(m.group(2)), int(m.group(1))))
    return sorted(days)


# ── plan writers ────────────────────────────────────────────────────────────────────────────────────────────
class Plan:
    def __init__(self, module_line, intro):
        self.lines = list(intro) + ["", f"# {module_line}"]
        self.block = None
        self.days = 0
        self.dates = []
        self.blocks = []

    def to_block(self, heading):
        if heading != self.block:
            self.block = heading
            self.blocks.append(heading)
            self.lines += ["", f"## {heading}"]

    def day(self, date_text, hours, title, bullets):
        head = f"### {date_text} [{hours:g}h]" + (f" {title}" if title else "")
        self.lines += ["", head] + [f"- {b}" for b in bullets]
        self.days += 1

    def text(self):
        return "\n".join(self.lines) + "\n"


def build_module(code, cfgs, cal):
    spec = MODULES[code]
    cfg = yaml.safe_load((cfgs / "seguiment-programacio" / spec["yaml"]).read_text(encoding="utf-8"))
    base = cfgs / spec["dir"]
    rows = guide_rows(base / "guia-professor-amb-dates-26-27.md")
    hours = cfg["hores_sessio"]
    style = spec["dates"]
    intro = [
        f"Test plan for the importer, generated from the dated guide of MP {code} (tools/build_test_data.py).",
        "Everything before the first heading is ignored by the importer, so notes like this are allowed.",
    ]
    plan = Plan(cfg["modul"], intro)
    days = []          # (date, block heading, title, bullets)

    if code == "0372":
        meta = blocks_0372((base / "planificacio-curs.md").read_text(encoding="utf-8"))
        for day in block_a_days(cfgs / "seguiment-programacio" / "seguiment-0372-26-27.docx"):
            days.append((day, meta["A"], "", ["Bloc A · RA1: previst en 3 sessions; en van ser 6 i l'examen del RA1 es va fer el 2/10/2026"]))
        for r in rows:
            letter = r["session"][0]
            title = f"{r['session']} · {clean(r['what'])}"
            days.append((r["date"], meta[letter], title, [f"Guia: {clean(r['page'])}"] if r["page"] else []))
    elif code == "0374":
        meta = blocks_0374((base / "planificacio-curs.md").read_text(encoding="utf-8"))
        current = "1"
        for r in rows:
            m = re.match(r"(\d)\.\d", clean(r["what"]))
            if m:
                current = m.group(1)
            what = clean(r["what"])
            notes = [seg.strip() for seg in what.split(" · ")]
            title = f"{r['session']} · {notes[0]}"
            bullets = notes[1:] + ([f"Guia: {clean(r['page'])}"] if r["page"] else [])
            days.append((r["date"], meta[current], title, bullets))
    else:
        meta = blocks_0377((base / "calendari-0377.md").read_text(encoding="utf-8"))
        for r in rows:
            what = clean(r["what"])
            main, _, delivery = what.partition(" — ")
            fields = [x.strip() for x in main.split(" · ")]
            code_block = fields[0]
            heading = meta.get(code_block) or f"{code_block} · {fields[1]}"
            bullets = fields[2:] if code_block.startswith("B") else fields[1:]
            if delivery.strip():
                bullets.append(f"Lliurament o prova: {delivery.strip()}")
            if r["page"]:
                bullets.append(f"Guia: {clean(r['page'])}")
            days.append((r["date"], heading, r["session"], bullets))

    days.sort(key=lambda x: x[0])
    for day, heading, title, bullets in days:
        plan.to_block(heading)
        plan.day(fmt_date(day, style), hours, title, bullets)
        plan.dates.append(day)
    return cfg, plan


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--cfgs", default=str(Path.home() / "CFGS"))
    ap.add_argument("--out", default=str(ROOT / "test-data"))
    args = ap.parse_args()
    cfgs, out = Path(args.cfgs), Path(args.out)
    (out / "plans").mkdir(parents=True, exist_ok=True)

    cal = yaml.safe_load((cfgs / "seguiment-programacio" / "calendari-centre-2026-27.yaml").read_text(encoding="utf-8"))
    holidays_text = build_holidays(cal)
    (out / "holidays-centre-2026-27.txt").write_text(holidays_text, encoding="utf-8")
    # The same holidays expanded day by day by the reference parser: the PHP holiday parser must produce exactly this.
    from check_plan import parse_holidays
    expanded = {day.isoformat(): label for day, label in sorted(parse_holidays(holidays_text).items())}
    (out / "holidays-expanded.json").write_text(json.dumps(expanded, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    expected = {
        "school_year": {"name": cal["curs"], "start": d(cal["inici"]).isoformat(), "end": d(cal["final"]).isoformat(),
                        "evaluations": [{"name": n, "start": d(a).isoformat(), "end": d(b).isoformat()} for a, b, n in cal["avaluacions"]]},
        "plans": {},
    }
    for code in MODULES:
        cfg, plan = build_module(code, cfgs, cal)
        (out / "plans" / f"{code}.md").write_text(plan.text(), encoding="utf-8")
        expected["plans"][code] = {
            "file": f"plans/{code}.md",
            "code": cfg["modul"].split(" · ", 1)[0],
            "module": cfg["modul"].split(" · ", 1)[1],
            "timetable": {str(w): cfg["hores_sessio"] for w in cfg["dies"]},
            "blocks": len(plan.blocks),
            "days": plan.days,
            "first": plan.dates[0].isoformat(),
            "last": plan.dates[-1].isoformat(),
            "hours": plan.days * cfg["hores_sessio"],
            "req": ["REQ-PLN-001", "REQ-PLN-003", "REQ-PLN-004", "REQ-PLN-009", "REQ-PLN-014"],
            "errors": [],
            "warnings": [],
        }
        print(f"{code}: {plan.days} days, {len(plan.blocks)} blocks, {plan.dates[0]} to {plan.dates[-1]}")
    (out / "expected.json").write_text(json.dumps(expected, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
