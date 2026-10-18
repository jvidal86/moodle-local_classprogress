#!/usr/bin/env python3
"""Check that every requirement is proved by a test, and that no test cites an unknown requirement.

Requirements: docs/spec/REQUIREMENTS.md, headings like '### REQ-PLN-004 Title' followed by 'Phase: M2 · Status: draft'.
A requirement counts as tested only through real test metadata (tightened after review round R1, finding R1-18):
  - PHP files under tests/: a docblock tag '@group REQ-X-001'
  - Behat *.feature files: a tag '@REQ-X-001' on a tag line
  - JSON files under tests/ and test-data/: ids inside a "req" list
Mentions anywhere else (comments, prose, Markdown) do not count.

    python3 tools/check_traceability.py                 # coverage matrix, exit 1 only for unknown or duplicate ids
    python3 tools/check_traceability.py --strict M2     # also exit 1 if any requirement of phase M0..M2 has no test
    python3 tools/check_traceability.py --extra-root ~/PROJECTS/moodle-mod_classprogress --strict M3
                                                        # also count the tests of the course activity's repository
"""
import argparse
import json
import re
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ID = re.compile(r"REQ-[A-Z]{2,5}-\d{3}")
HEAD = re.compile(r"^### (REQ-[A-Z]{2,5}-\d{3}) (.+)$")
META = re.compile(r"^Phase: (M\d+) · Status: (\w+)")
SCAN_DIRS = ["tests", "test-data"]


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


def scan_citations(root=ROOT, cites=None):
    cites = defaultdict(set) if cites is None else cites
    label = "" if root == ROOT else root.name + ":"

    def add(rid, path):
        cites[rid].add(label + str(path.relative_to(root)))

    def walk_json(node, path):
        if isinstance(node, dict):
            for key, value in node.items():
                if key == "req" and isinstance(value, list):
                    for rid in value:
                        if isinstance(rid, str) and ID.fullmatch(rid):
                            add(rid, path)
                else:
                    walk_json(value, path)
        elif isinstance(node, list):
            for value in node:
                walk_json(value, path)

    group = re.compile(r"@group\s+(" + ID.pattern + r")\b")
    for d in SCAN_DIRS:
        base = root / d
        if not base.exists():
            continue
        for p in base.rglob("*"):
            if not p.is_file():
                continue
            if p.suffix == ".php" and d == "tests":
                for rid in group.findall(p.read_text(encoding="utf-8", errors="replace")):
                    add(rid, p)
            elif p.suffix == ".json":
                try:
                    walk_json(json.loads(p.read_text(encoding="utf-8")), p)
                except (ValueError, UnicodeDecodeError):
                    pass
    for p in root.rglob("*.feature"):
        if ".git" in p.parts or "worktrees" in p.parts:
            continue
        for line in p.read_text(encoding="utf-8", errors="replace").splitlines():
            if line.strip().startswith("@"):
                for token in line.split():
                    if token.startswith("@") and ID.fullmatch(token[1:]):
                        add(token[1:], p)
    return cites


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--strict", metavar="PHASE", help="fail if a requirement of this phase or an earlier one has no test, e.g. M2")
    ap.add_argument("--extra-root", metavar="DIR", action="append", default=[],
                    help="another repository whose tests count too, such as the course activity's; may be repeated")
    args = ap.parse_args()

    reqs, dup = read_requirements(ROOT / "docs" / "spec" / "REQUIREMENTS.md")
    cites = scan_citations()
    for extra in args.extra_root:
        path = Path(extra).expanduser().resolve()
        if not path.is_dir():
            print(f"--extra-root {extra}: not a directory"); sys.exit(1)
        scan_citations(path, cites)
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
