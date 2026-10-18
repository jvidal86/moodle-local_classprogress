# Specification (spec-driven development)

`REQUIREMENTS.md` is the source of truth for what the plugin must do. `PLAN.md` explains why and how; when the two disagree, the requirement wins and the plan is corrected.

## How a requirement is written

```
### REQ-PLN-004 Day heading date formats
Phase: M2 · Status: agreed
A day heading starts with a date in ISO form or dd/mm/yyyy ... (one behaviour, testable, no "and also")
Examples: test-data/cases/formats.md
```

- **ID:** `REQ-<AREA>-<nnn>`. Areas: `PLN` plan format, `HOL` holidays, `CAL` calendar, `ACC` access, `OWN` ownership, `DAT` database, `PRV` privacy, `IMP` import, `LCK` approval and lock, `LOG` entries, `HIS` history, `EXP` export, `REL` release, `AUD` audit events. IDs are never reused.
- **One behaviour per requirement,** worded so a test can pass or fail on it. Prefer an example (a fixture file, a Gherkin scenario) over a paragraph.
- **Status:** `draft` (being discussed), `agreed` (you read and accepted it), `implemented` (code and tests exist), `dropped` (kept for history, with the reason).
- **Phase:** the phase that builds it (`M0` to `M5`).

## The cycle for every phase

1. **Specify.** Write or update the requirements of the phase, with examples and a draft of the Behat scenarios. You read them: this is the sign-off artifact before any code.
2. **Tests first.** Write tests that fail, each tagged with the ids it proves.
3. **Implement** until green, then refactor (see `docs/TESTING_STRATEGY.md`).
4. **Trace.** `python3 tools/check_traceability.py --strict M<n>` must pass: every requirement up to the current phase is cited by at least one test, and no test cites an unknown requirement.
5. **Review round** (`PLAN.md` section 6).

A bug found later is handled in this order: change or add the requirement (if the spec was wrong or silent), then add the failing test, then fix the code.

## How a test cites a requirement

| Test kind | Where the id goes |
|---|---|
| PHPUnit | docblock `@group REQ-PLN-004` on the test method or class (PHPUnit 9.6 and 11.5 both read it as a group; run one with `--group REQ-PLN-004`) |
| Behat | tag on the feature or scenario: `@REQ-PLN-004` |
| Fixture-based (parser parity) | `"req": ["REQ-PLN-004"]` in `test-data/expected.json` and `test-data/cases/expected.json` |

Phases not yet specified (`LOG`, `HIS`, `EXP`, `REL`) are written at the start of their phase, not now, so they are not frozen before the earlier phases have taught us something.
