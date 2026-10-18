#!/usr/bin/env python3
"""Check that every requirement is proved by a test, and that no test cites an unknown requirement.

Requirements: docs/spec/REQUIREMENTS.md, headings like '### REQ-PLN-004 Title' followed by 'Phase: M2 · Status: draft'.
Tests cite requirements by id. Scanned places: tests/ (PHPUnit docblock '@group REQ-X-001', Behat tag '@REQ-X-001'),
test-data/ (the 'req' lists in the expected-results JSON files) and any *.feature file.

    python3 tools/check_traceability.py                 # coverage matrix, exit 1 only for unknown or duplicate ids
    python3 tools/check_traceability.py --strict M2     # also exit 1 if any requirement of phase M0..M2 has no test
"""
import argparse
import re
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ID = re.compile(r"REQ-[A-Z]{2,5}-\d{3}")
HEAD = re.compile(r"^### (REQ-[A-Z]{2,5}-\d{3}) (.+)$")
META = re.compile(r"^Phase: (M\d+) · Status: (\w+)")
SCAN_DIRS = ["tests", "test-data"]
SCAN_SUFFIXES = {".php", ".feature", ".json", ".py", ".md", ".yml", ".yaml", ".txt"}


def read_requirements(path):
    reqs, dup = {}, []
    lines = path.read_text(encoding="utf-8").splitlines()
    for i, line in enumerate(lines):
        m = HEAD.match(line)
        if not m:
            continue
        rid, title = m.group(1), m.group(2)
        meta = META.match(lines[i + 1]) if i + 1 < len(lines) else None
        if rid in reqs:
            dup.append(rid)
        reqs[rid] = {"title": title, "phase": meta.group(1) if meta else "?", "status": meta.group(2) if meta else "?"}
    return reqs, dup


def scan_citations():
    cites = defaultdict(set)
    files = []
    for d in SCAN_DIRS:
        base = ROOT / d
        if base.exists():
            files += [p for p in base.rglob("*") if p.is_file() and p.suffix in SCAN_SUFFIXES]
    files += [p for p in ROOT.rglob("*.feature") if p not in files and ".git" not in p.parts]
    for p in files:
        try:
            text = p.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        for rid in ID.findall(text):
            cites[rid].add(str(p.relative_to(ROOT)))
    return cites


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--strict", metavar="PHASE", help="fail if a requirement of this phase or an earlier one has no test, e.g. M2")
    args = ap.parse_args()

    reqs, dup = read_requirements(ROOT / "docs" / "spec" / "REQUIREMENTS.md")
    cites = scan_citations()
    unknown = sorted(set(cites) - set(reqs))

    by_phase = defaultdict(list)
    for rid, r in reqs.items():
        by_phase[r["phase"]].append(rid)

    covered = uncovered = 0
    for phase in sorted(by_phase):
        print(f"\n{phase}")
        for rid in sorted(by_phase[phase]):
            r, files = reqs[rid], sorted(cites.get(rid, []))
            mark = "ok  " if files else "NONE"
            covered += bool(files)
            uncovered += not files
            print(f"  {mark} {rid} [{r['status']}] {r['title']}" + (f"  <- {len(files)} file(s)" if files else ""))

    print(f"\n{len(reqs)} requirements: {covered} with tests, {uncovered} without")
    failed = False
    if dup:
        print("DUPLICATE ids:", ", ".join(dup)); failed = True
    if unknown:
        print("UNKNOWN ids cited by tests:", ", ".join(unknown)); failed = True
    if args.strict:
        limit = int(args.strict.lstrip("M"))
        missing = [rid for rid, r in reqs.items() if r["phase"].startswith("M") and int(r["phase"][1:]) <= limit and rid not in cites]
        if missing:
            print(f"--strict {args.strict}: no test for", ", ".join(sorted(missing))); failed = True
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()
