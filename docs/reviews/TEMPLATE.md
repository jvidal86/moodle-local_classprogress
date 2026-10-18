# Review round R<n> · phase M<n> <name>

Date: YYYY-MM-DD · Commit / tag: `phase-M<n>-done` · Reviewers: (agents and lenses used)

## Self-checks

| Check | Result |
|---|---|
| CI matrix (4.5/8.1/pgsql, 4.5/8.3/mariadb, 5.3/8.3/pgsql, 5.3/8.4/mariadb) | |
| `python3 tools/check_plan.py --selftest` | |
| phpcs, phpdoc, validate, savepoints, mustache (zero warnings) | |
| Developer debug mode: notices seen | |
| Fresh install / upgrade / uninstall | |

## Findings

| ID | Severity (P1/P2/P3) | Lens | Where (file:line) | What and how to reproduce | Status (open / fixed / deferred) | Regression test |
|---|---|---|---|---|---|---|
| | | | | | | |

## Mutation checks (rules broken on purpose; a test must fail)

| Rule | Mutation | Test that failed |
|---|---|---|
| | | |

## Phase-specific checks (section 6.3 of PLAN.md)

- [ ] ...

## Documents updated

- [ ] `PLAN.md` status · [ ] `docs/DEV_ENVIRONMENT.md` · [ ] `test-data/expected.json` (if behaviour changed) · [ ] `CHANGES.md`

## Gate

- Open P1: 0 · Open P2: 0 · Deferred P3 (with reason): ...
- Sign-off (required for R2 and R5): ...
- Decision: **closed** / not closed. Next phase may start: yes / no
