#!/usr/bin/env python3
"""Differential check: the PHP plan parser (classes/local/plan_parser.php) against the reference, tools/check_plan.py.

Both parsers read the same inputs and their results are compared: the summary (the keys of summarize()) and the
detail (module; blocks; every day's date, hours, title, block, line and content; every issue's level, code and line).

    python3 tools/compare_parsers.py                      # fixtures, edge probes and 1000 random plans
    python3 tools/compare_parsers.py --count 5000 --seed 7
    python3 tools/compare_parsers.py --only 412           # just random plan 412 of the seed, printed in full
    python3 tools/compare_parsers.py --save /tmp/diffs    # also write every differing random plan to a file
    python3 tools/compare_parsers.py --php "docker run --rm -i -v $PWD:$PWD:ro --entrypoint php moodlehq/moodle-php-apache:8.1"

Sections
    fixtures           tests/fixtures/plans/*.md and cases/*.md through the CLI's file mode, inputs as in the selftest
    edge probes        fixed inputs that each stress one rule; the parsers must agree
    random             seeded random plans through the CLI's JSON-lines mode: headings of levels 1 to 7 with and
                       without closing hashes, valid and invalid dates in both formats and in other digits, weekday
                       words in any case and look-alike letters, hours, code fences of both kinds (inline, indented,
                       longer closers, unclosed), empty days, LF/CRLF/CR and other line-like characters, a leading BOM,
                       long lines and runs of spaces, Catalan and other Unicode text; must agree
Exit 1 when any input gives different results. Bytes that are not UTF-8 (error not_utf8) cannot reach the reference,
which reads text, so they are tested in tests/local/plan_parser_test.php only.
"""
import argparse
import datetime as dt
import json
import math
import random
import shlex
import subprocess
import sys
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import check_plan  # noqa: E402

FIXTURES = ROOT / "tests" / "fixtures"
CLI = ROOT / "tools" / "plan_parser_cli.php"
DEFAULT_TIMETABLE = {0: 2.0, 4: 2.0}
PHP = ["php"]  # set from --php


# ── running both parsers ────────────────────────────────────────────────────────────────────────────────────────
def hours(value):
    """Hours as both sides can compare them: a float, "INF" (JSON cannot hold infinity) or None."""
    if value is None or value == "INF":
        return value
    return "INF" if math.isinf(value) else float(value)


def python_result(text, holidays, timetable, year):
    """The reference's result, shaped like the CLI's --full output (issues without parameters)."""
    pyholidays = None if holidays is None else {dt.date.fromisoformat(k): v for k, v in holidays.items()}
    pyyear = None if year is None else tuple(dt.date.fromisoformat(d) for d in year)
    try:
        result = check_plan.parse(text, pyholidays, timetable, pyyear)
        summary = check_plan.summarize(result)
    except Exception as e:  # The reference can fail on odd input; that is a difference too.
        return {"exception": f"{type(e).__name__}: {e}"}
    for key in ("first", "last"):
        summary[key] = summary[key].isoformat() if summary[key] else None
    summary["hours"] = hours(summary["hours"])
    module = result["module"]
    return {
        "summary": summary,
        "module": None if module is None else {k: module[k] for k in ("code", "title", "line")},
        "blocks": result["blocks"],
        "days": [{"date": d["date"].isoformat(), "hours": hours(d["hours"]), "title": d["title"], "block": d["block"],
                  "line": d["line"], "content": d["content"]} for d in result["days"]],
        "issues": [{k: i[k] for k in ("level", "code", "line")} for i in result["issues"]],
    }


def php_shape(result):
    if "exception" in result:
        return result
    result["summary"]["hours"] = hours(result["summary"]["hours"])
    for day in result["days"]:
        day["hours"] = hours(day["hours"])
    result["issues"] = [{k: i[k] for k in ("level", "code", "line")} for i in result["issues"]]
    return result


def php_batch(requests):
    """Runs the PHP parser once over many requests, through the CLI's JSON-lines mode."""
    lines = "".join(json.dumps(r) + "\n" for r in requests)
    out = subprocess.run(PHP + [str(CLI), "--jsonl"], input=lines.encode("utf-8"), capture_output=True, check=True)
    # Split on "\n" only: the output may hold U+0085, which str.splitlines() would also split on.
    results = [json.loads(line) for line in out.stdout.decode("utf-8").split("\n") if line]
    if len(results) != len(requests):
        sys.exit(f"PHP returned {len(results)} results for {len(requests)} requests:\n{out.stderr.decode()}")
    return [php_shape(r) for r in results]


def php_file(path, holidays_file, timetable, year):
    args = PHP + [str(CLI), str(path), "--full", f"--holidays={holidays_file}",
            "--timetable=" + ("none" if timetable is None else ",".join(f"{k}:{v}" for k, v in timetable.items()))]
    if year:
        args.append(f"--year={year[0]}:{year[1]}")
    out = subprocess.run(args, capture_output=True, check=True)
    return php_shape(json.loads(out.stdout.decode("utf-8")))


def differences(py, php):
    """Names of the parts that differ, such as 'summary.hours' or 'days[3]'."""
    if "exception" in py or "exception" in php:
        return [] if py == php else ["exception"]
    found = [f"summary.{k}" for k in py["summary"] if py["summary"][k] != php["summary"].get(k)]
    found += [k for k in ("module", "blocks") if py[k] != php[k]]
    for key in ("days", "issues"):
        if len(py[key]) != len(php[key]):
            found.append(f"{key} (count {len(py[key])} vs {len(php[key])})")
        else:
            found += [f"{key}[{n}]" for n, (a, b) in enumerate(zip(py[key], php[key])) if a != b][:3]
    return found


def short(value, limit=160):
    text = repr(value)
    return text if len(text) <= limit else text[:limit] + f"... ({len(text)} chars)"


def report(name, text, py, php, diff):
    print(f"  DIFF {name}: {', '.join(diff)}")
    print(f"       input {short(text, 300)}")
    for part in diff[:4]:
        key = part.split(" ")[0]
        if key == "exception":
            print(f"       python {short(py.get('exception', py.get('summary')))}")
            print(f"       php    {short(php.get('exception', php.get('summary')))}")
            continue
        if key.startswith("summary."):
            a, b = py["summary"][key[8:]], php["summary"].get(key[8:])
        elif "[" in key:
            k, n = key[:-1].split("[")
            a, b = py[k][int(n)], php[k][int(n)]
        else:
            a, b = py[key], php[key]
        print(f"       {key}: python {short(a)}")
        print(f"       {' ' * len(key)}  php    {short(b)}")


# ── random plans ────────────────────────────────────────────────────────────────────────────────────────────────
CATALAN = ("Presentació del mòdul i avaluació inicial Instal·lació de l'IDE Pràctica guiada Normes de l'aula "
           "Exercicis d'àlgebra relacional Gestió de bases de dades Còpia de seguretat Lliurament del projecte "
           "Revisió de l'examen façana Sessió teòrica Administració de sistemes operatius Identitats el servei "
           "de directori L'entorn de treball Neix el domini Organitzar LDAP per dins Polítiques de grup").split()
OTHER = ["niño", "año", "naïve", "Ελληνικά", "日本語", "中文", "русский", "عربى", "עברית", "🎓", "👩\u200d🏫", "é", "ﬁ",
         "Ⅻ", "½", "²", "٢", "MP٠٣", "\u200b", "\xad", "\u2060", "\x00", "\x1f", "\u180e", "\ufeff", "C#", "F#",
         "x86-64", "1.5", "2h", "[2h]", "[1,5 h]", "·", "—", "–", "-", ":", "::", "#", "##", "###", "**bold**", "_em_",
         "`code`", "[link](https://example.org/a_b)", "<b>html</b>", "&amp;", "SELECT * FROM t;", "$x = 1;", "\\",
         '"quoted"', "'single'", "RA1", "RA2:", "Bloc", "2026-09-14", "14/09/2026", "dl", "Wed", "mié"]
SPACES = [" "] * 12 + ["  ", "\t", "\xa0", "\u3000", "\u2003", "\u202f", " \t ", "\x1f"]
BREAKS = ["\n", "\r\n", "\r", "\x0b", "\x0c", "\x1c", "\x1d", "\x1e", "\x85", "\u2028", "\u2029"]
BAD_DATES = ["2026-02-30", "2026-13-01", "2026-00-10", "00/01/2026", "32/01/2026", "29/02/2027", "0000-01-01",
             "2026-9-14", "14.09.2026", "2026/09/14", "sometime", "", "31/04/2027", "2026-09-31", "29/02/2100",
             "2026-W38-1", "20260914", "2026-09-14T10:00", "1/1/1", "014/09/2026", "dl", "#"]
ODD_DATES = ["9999-12-31", "0001-01-01", "29/02/2028", "29/02/2000", "2026-09-145", "14/09/20261", "1/2/2027"]
HOURS = ["[2h]"] * 6 + ["[1,5 h]", "[1.5h]", "[ 2 H ]", "[2]", "[2 hores]", "[h]", "[0h]", "[,5h]", "[1.h]",
                        "[10,25h]", "[2h", "2h]", "[ 2\xa0h ]", "[007h]", "[2 h]", "[2hh]", "[1.5.5h]", "[1,25H]",
                        "[\t3\th\t]", "[" + "9" * 30 + "h]", "[" + "9" * 400 + "h]"]
NOT_WEEKDAYS = ["Monday", "dll", "lu", "mi", "dimecres", "Mo", "x", "dl:", "MON,", "Setmana", "Sessió", "S", "S1",
                "dl1", "\u017fat", "fr\u0131", "FR\u0130", "M\u043en", "\uff2don", "\uff24\uff2c", "d\u217c",
                "Sun\u00b2", "dl\u00bd", "\u2167", "mie\u0301", "MIE\u0301", "\u212aon", "dl_", "d-l", "\u00e1",
                "S\u00c1B\u00c1"]
FENCES = ["```", "```python", "  ```", "   ```", "    ```", "\t```", "\xa0```", "~~~", "~~~ text", "~~~ `x`", "````",
          "`````", "``````", "~~~~", "~~~~~", "```inline```", "```ls -la```", "``` sql `x`", "```   ", "``` x", "``",
          "~~", " ~~~", "\t~~~", "~~~\t", "` ``", "```\xa0"]
UNICODE_DATES = ["\u0661\u0664/\u0661\u0660/\u0662\u0660\u0662\u0666",
                 "\uff12\uff10\uff12\uff16-\uff11\uff10-\uff10\uff15", "2026-1\u0660-05", "\u0967\u096a/09/2026",
                 "14/09/202\u0666", "\U0001d7ee\U0001d7ec/09/2026"]
UNICODE_HOURS = ["[\u0662h]", "[\uff13h]", "[1,\u0665h]", "[\u0967.5 h]", "[2\u00b2h]"]
CODES = ["MP03", "MP 0372", "M3", "0372", "MP03b", "X1.2-a", "ABCDE1", "MP٠٣", "C1_2", "UF1", "MP", "Mòdul3", "3D",
         "A1é", "ab12_c.d-e"]
SEPARATORS = [" · ", " · ", " — ", " – ", " - ", ": ", ":", " ·", "· ", "  ·  ", "\t·\t", "\xa0·\xa0", " : ",
              ":\t", " -- ", " —", " ·\xa0"]
WEEKDAY_NAMES = list(check_plan.WEEKDAYS)


def words(rng, low=1, high=8):
    n = rng.randint(low, high)
    out = [rng.choice(CATALAN) if rng.random() < 0.75 else rng.choice(OTHER) for _ in range(n)]
    gaps = [rng.choice(SPACES) for _ in range(n - 1)]
    if gaps and rng.random() < 0.05:  # a long run of spaces, kept short enough for the reference's backtracking
        gaps[rng.randrange(len(gaps))] = " " * rng.randint(20, 150)
    return "".join(w + (gaps[i] if i < len(gaps) else "") for i, w in enumerate(out))


def heading(rng, level, text):
    sep = rng.choice([" "] * 12 + ["  ", "\t", "", "\xa0", " \t"])
    close = rng.choice([""] * 14 + [" #", " ##", "#", "##", " # ", "\t#\t", " ###   ", " #" + " " * rng.randint(1, 60),
                                    " C#", " C##", " # #", " #\xa0", "#\t", " \\#", " " + "#" * rng.randint(1, 300)])
    indent = " " if rng.random() < 0.03 else ""
    return indent + "#" * level + sep + text + close


def random_case(rng, name):
    return "".join(c.upper() if rng.random() < 0.3 else c for c in name)


def day_text(rng, cursor):
    """Text of a ### heading, and the next date cursor."""
    r = rng.random()
    date = None
    if r < 0.8:
        cursor += dt.timedelta(days=rng.choice([0, 1, 1, 2, 3, 3, 4, 7, 7, 14, -3, -30]))
        date = cursor
        fmt = rng.random()
        if fmt < 0.45:
            text = date.isoformat()
        elif fmt < 0.85:
            text = f"{date.day:02d}/{date.month:02d}/{date.year}"
        else:
            text = f"{date.day}/{date.month}/{date.year}"
    elif r < 0.9:
        text = rng.choice(BAD_DATES)
    elif r < 0.95:
        text = rng.choice(UNICODE_DATES)
    else:
        text = rng.choice(ODD_DATES)
    if rng.random() < 0.35:
        p = rng.random()
        if date is not None and p < 0.6:
            name = rng.choice([n for n, w in check_plan.WEEKDAYS.items() if w == date.weekday()])
        elif p < 0.85:
            name = rng.choice(WEEKDAY_NAMES)
        else:
            name = rng.choice(NOT_WEEKDAYS)
        dot = "." if rng.random() < 0.15 else ""
        gap = rng.choice(SPACES + ["", "\u200b"])
        text = random_case(rng, name) + dot + gap + text
    if rng.random() < 0.75:
        text += rng.choice(SPACES + [""]) + rng.choice(HOURS if rng.random() < 0.95 else UNICODE_HOURS)
    if rng.random() < 0.6:
        text += rng.choice(SPACES + [""]) + words(rng, 1, 10)
    if rng.random() < 0.02:
        text += " " + words(rng, 300, 600)  # a very long title
    return text, cursor


def module_text(rng):
    code, title = rng.choice(CODES), words(rng, 1, 6)
    r = rng.random()
    if r < 0.5:
        return code + rng.choice(SEPARATORS) + title
    if r < 0.65:
        return code + rng.choice([" ", " ", "\t", "\xa0"]) + title
    if r < 0.75:
        return title
    if r < 0.8:
        return code
    if r < 0.85:
        return rng.choice([": ", "· ", " · "]) + title
    if r < 0.9:
        return code + " " * rng.randint(50, 300) + "· " + title
    if r < 0.95:
        return "1" * rng.randint(100, 1500) + rng.choice(["!", "", "é"]) + " " + title
    return words(rng, 1, 3) + rng.choice(SEPARATORS) + words(rng, 1, 3) + rng.choice(SEPARATORS) + title


def body_line(rng):
    r = rng.random()
    if r < 0.4:
        return rng.choice(["", "- ", "* ", "1. ", "> ", "  - "]) + words(rng, 1, 12)
    if r < 0.5:
        return rng.choice(["", "", "   ", "\t", "\xa0", " \x1f "])
    if r < 0.6:
        return heading(rng, rng.randint(4, 7), words(rng, 1, 5))
    if r < 0.68:
        return rng.choice(["#hashtag", "#", "##", "###", "#!", "####### seven", " ## indented", "\t# tabbed",
                           "#\xa0nbsp", "#\ttab heading"])
    if r < 0.73:
        return rng.choice(["x" * rng.randint(1000, 20000), words(rng, 200, 800), "a" + " " * rng.randint(100, 5000) + "b",
                           "- " + "é" * rng.randint(1000, 5000)])
    if r < 0.78:
        return rng.choice(["2026-10-05", "### not a heading in a list?", "  ### indented", "[2h]", "---", "***", "| a | b |"])
    return words(rng, 1, 15)


def random_plan(seed, index):
    """One random plan and its inputs; the same seed and index always give the same plan."""
    rng = random.Random(f"{seed}:{index}")
    lines = [body_line(rng) for _ in range(rng.randint(0, 3))]
    if rng.random() < 0.85:
        lines.append(heading(rng, 1, module_text(rng)))
    cursor = dt.date(2026, 9, 14) + dt.timedelta(days=rng.randint(-30, 200))
    kinds = ["day"] * 30 + ["body"] * 40 + ["blank"] * 12 + ["block"] * 6 + ["fence"] * 5 + ["module"] * 2
    for _ in range(rng.randint(3, 60)):
        kind = rng.choice(kinds)
        if kind == "day":
            text, cursor = day_text(rng, cursor)
            lines.append(heading(rng, 3, text))
        elif kind == "body":
            lines.append(body_line(rng))
        elif kind == "blank":
            lines.append("")
        elif kind == "block":
            lines.append(heading(rng, 2, rng.choice(["RA1 · ", "RA2: ", "Bloc 3 · ", "", "UF1 — "]) + words(rng, 0, 6)))
        elif kind == "fence":
            if rng.random() < 0.2:
                lines.append(" " * rng.randint(0, 4) + rng.choice("`~") * rng.randint(2, 400) + rng.choice(["", " ", "x"]))
            else:
                lines.append(rng.choice(FENCES) + rng.choice(["", "", "sql", " bash"]))
        else:
            lines.append(heading(rng, 1, module_text(rng)))
    scheme = rng.random()
    if scheme < 0.45:
        breaks = ["\n"] * len(lines)
    elif scheme < 0.7:
        breaks = ["\r\n"] * len(lines)
    elif scheme < 0.8:
        breaks = ["\r"] * len(lines)
    else:  # mixed line ends, and characters that end lines for str.splitlines() but not in a plan
        breaks = [rng.choice(BREAKS[:3] * 4 + BREAKS) for _ in lines]
    text = "".join(line + br for line, br in zip(lines, breaks))
    if rng.random() < 0.2:
        text = text.rstrip("\n\r")
    if rng.random() < 0.05:
        text = "\ufeff" + text
    timetable = rng.choice([DEFAULT_TIMETABLE] * 3 + [None, {}, {1: 1.0}, {0: 2.0, 3: 2.0}, {0: 1.5, 2: 2.25, 5: 3.0},
                                                       {i: 1.0 for i in range(7)}])
    holidays = rng.choice(["centre", "centre", None, "empty"])
    year = rng.choice([("2026-09-14", "2027-06-21"), ("2026-09-14", "2027-06-21"), None, ("2026-10-01", "2026-12-31")])
    return text, holidays, timetable, year


# ── fixed inputs ────────────────────────────────────────────────────────────────────────────────────────────────
def plan(*lines):
    return "\n".join(lines) + "\n"


HEAD = ("# MP01 · Probe", "## RA1")
# (name, text, overrides of holidays/timetable/year)
PROBES = [
    ("empty text", "", {}),
    ("one empty line", "\n", {}),
    ("closing hash with no space", plan("# Programació en C#", "## RA1", "### 2026-10-05 [2h] C#", "- a"), {}),
    ("no-break space after ###", plan(*HEAD, "###\xa02026-10-05 [2h]", "- a"), {}),
    ("tab after ###", plan(*HEAD, "###\t2026-10-05 [2h]", "- a"), {}),
    ("seven hashes", plan(*HEAD, "### 2026-10-05 [2h]", "####### 2026-10-09 [2h]"), {}),
    ("inline ``` is not a fence", plan(*HEAD, "### 2026-10-05 [2h]", "```ls -la```", "### 2026-10-09 [2h]", "- b"), {}),
    ("~~~ does not close ```", plan(*HEAD, "### 2026-10-05 [2h]", "```", "x", "~~~", "### 2026-10-09 [2h]", "- b"), {}),
    ("shorter closer, then a longer one", plan(*HEAD, "### 2026-10-05 [2h]", "````", "```", "### x", "`````", "### 2026-10-09"),
     {}),
    ("closer with text after it", plan(*HEAD, "### 2026-10-05 [2h]", "~~~", "~~~ x", "### x", "~~~  ", "### 2026-10-09"), {}),
    ("fence indented by 3, 4 spaces and a tab", plan(*HEAD, "### 2026-10-05 [2h]", "   ```", "### x", "    ```", "\t```",
                                                      "   ```", "    ```", "### 2026-10-09 [2h]"), {}),
    ("tilde fence with backticks in its info", plan(*HEAD, "### 2026-10-05 [2h]", "~~~ `x`", "### x", "~~~"), {}),
    ("backtick fence with a backtick in its info", plan(*HEAD, "### 2026-10-05 [2h]", "``` a`b", "### 2026-10-09"), {}),
    ("400 backticks open, 399 do not close", plan(*HEAD, "### 2026-10-05 [2h]", "`" * 400, "`" * 399, "### x"), {}),
    ("unclosed fence", plan(*HEAD, "### 2026-10-05 [2h]", "```", "### 2026-10-09 [2h]", "## RA2"), {}),
    ("NUL at the end of a body", plan(*HEAD, "### 2026-10-05 [2h]", "- a\x00"), {}),
    ("unit separator around a title", plan(*HEAD, "### 2026-10-05 [2h] \x1fTitle\x1f", "- a"), {}),
    ("Mongolian vowel separator around a title", plan(*HEAD, "### 2026-10-05 [2h] \u180eTitle\u180e", "- a"), {}),
    ("zero-width space in a weekday gap", plan(*HEAD, "### dl\u200b05/10/2026 [2h]", "- a"), {}),
    ("lone CR", "# MP01 · X\r## RA1\r### 2026-10-05 [2h]\r- a\r", {}),
    ("NEL, LS, PS, VT, FF and FS inside lines",
     "# MP01 · X\x85## RA1\u2028### 2026-10-05 [2h]\u2029- a\x0b### 2026-10-09 [2h]\x0c- b\x1c### x\x1d\x1e", {}),
    ("LF CR is two breaks", "# MP01 · X\n\r## RA1\n\r### 2026-10-05 [2h]\n\r- a", {}),
    ("empty block heading", plan("# MP01 · X", "## ", "### 2026-10-05 [2h]", "- a"), {}),
    ("empty module code", plan("# : Title", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("tab between code and title", plan("# MP03\tProgramació", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("accented letter in a code word", plan("# MP03é Programació", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("Arabic-Indic digits in a code word", plan("# MP٠٣ Programació", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("code word followed by '0'", plan("# MP03 0", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("colon before the middle dot", plan("# MP03: Programació · Java", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("400-digit hours", plan(*HEAD, "### 2026-10-05 [" + "9" * 400 + "h]", "- a"), {}),
    ("300 spaces before the separator", plan("# MP01" + " " * 300 + "· X", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("1500 digits then '!'", plan("# " + "1" * 1500 + "! X", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("200 spaces before a closing hash", plan(*HEAD, "### 2026-10-05 [2h] T" + " " * 200 + "#" + " " * 200, "- a"), {}),
    ("digits glued to an ISO date", plan(*HEAD, "### 2026-09-145 [2h]", "- a"), {}),
    ("digits glued to a dd/mm/yyyy date", plan(*HEAD, "### 14/09/20261", "- a"), {}),
    ("weekday, dot, no-break space", plan(*HEAD, "### DL.\xa014/09/2026 [2h]", "- a"), {}),
    ("weekday, dot, no space", plan(*HEAD, "### dl.14/09/2026 [2h]", "- a"), {}),
    ("upper-case Spanish weekdays", plan(*HEAD, "### MIÉ 14/10/2026 [2h]", "- a", "### SÁB 17/10/2026 [2h]", "- b"), {}),
    ("holiday with an empty label", plan(*HEAD, "### 2026-10-05 [2h]", "- a"), {"holidays": {"2026-10-05": ""}}),
    ("empty timetable", plan(*HEAD, "### 2026-10-05", "- a"), {"timetable": {}}),
    ("first and last day of the year", plan(*HEAD, "### 2026-09-14 [2h]", "- a", "### 2027-06-21 [2h]", "- b"), {}),
    ("year 1 and year 9999", plan(*HEAD, "### 0001-01-01 [1h]", "- a", "### 9999-12-31 [1h]", "- b"), {}),
    ("29 February in 2000 and 2100", plan(*HEAD, "### 29/02/2000", "- a", "### 29/02/2100", "- b"), {}),
    ("text before the first heading", plan("notes", "- 2026-10-05", "# MP01 · X", "### 2026-10-05 [2h]", "- a"), {}),
    ("byte order mark after line 1", plan("notes", "\ufeff# MP01 · X", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("byte order mark", "\ufeff" + plan("# MP01 · Saved by Notepad", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("two byte order marks", "\ufeff\ufeff" + plan("# MP01 · X", "## RA1", "### 2026-10-05 [2h]", "- a"), {}),
    ("byte order mark, CR line ends", "\ufeff# MP01 · X\r## RA1\r### 2026-10-05 [2h]\r- a", {}),
    ("closing hashes", plan("# C# ##", "## x ##", "## C##", "## ##", "## a # #", "### 2026-10-05 [2h] T #\xa0", "- a"), {}),
    ("Arabic-Indic digits, dd/mm/yyyy", plan(*HEAD, "### \u0661\u0664/\u0661\u0660/\u0662\u0660\u0662\u0666 [2h]",
                                              "- a"), {}),
    ("full-width digits in hours", plan(*HEAD, "### 2026-10-14 [\uff13h]", "- a"), {"timetable": None}),
    ("long s in a weekday", plan(*HEAD, "### \u017fat 10/10/2026 [2h]", "- a"), {}),
    ("dotless i in a weekday", plan(*HEAD, "### fr\u0131 09/10/2026 [2h]", "- a"), {}),
    ("dotted capital I in a weekday", plan(*HEAD, "### FR\u0130 09/10/2026 [2h]", "- a"), {}),
    ("Kelvin sign, full-width and Cyrillic letters", plan(*HEAD, "### \u212aon 05/10/2026", "### \uff2don 05/10/2026",
                                                         "### M\u043en 05/10/2026", "- a"), {}),
    ("roman numeral and superscript in a word", plan(*HEAD, "### d\u217c 05/10/2026", "### Sun\u00b2 05/10/2026"), {}),
    ("decomposed accent in mié", plan(*HEAD, "### mie\u0301 14/10/2026 [2h]", "- a"), {}),
    ("not a weekday word", plan(*HEAD, "### Setmana 16/10/2026 [2h]", "- a", "### S\u00c1B 17/10/2026 [2h]", "- b"), {}),
]

def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--count", type=int, default=1000, help="random plans (default 1000)")
    ap.add_argument("--seed", default="2026", help="seed of the random plans (default 2026)")
    ap.add_argument("--only", type=int, help="run only this random plan and print it")
    ap.add_argument("--save", type=Path, help="write each differing random plan to this directory")
    ap.add_argument("--show", type=int, default=10, help="differing random plans to print (default 10)")
    ap.add_argument("--php", default="php", help="command that runs PHP (default php), e.g. to try another version")
    args = ap.parse_args()
    PHP[:] = shlex.split(args.php)

    holidays_file = FIXTURES / "holidays-expanded.json"
    holidays = json.loads(holidays_file.read_text(encoding="utf-8"))
    expected = json.loads((FIXTURES / "expected.json").read_text(encoding="utf-8"))
    year = (expected["school_year"]["start"], expected["school_year"]["end"])
    inputs = {"centre": holidays, "empty": {}, None: None}
    failures = 0

    if args.only is None:
        reference = check_plan.parse_holidays((FIXTURES / "holidays-centre-2026-27.txt").read_text(encoding="utf-8"))
        same = {d.isoformat(): label for d, label in reference.items()} == holidays
        print(f"holidays: {len(holidays)} days in holidays-expanded.json, "
              + ("the same as parse_holidays(holidays-centre-2026-27.txt)" if same else "DIFFERENT from parse_holidays()"))
        failures += not same

        # Fixtures, through the CLI's file mode.
        fixtures = [(FIXTURES / p["file"], {int(k): float(v) for k, v in p["timetable"].items()})
                    for p in expected["plans"].values()]
        cases = json.loads((FIXTURES / "cases-expected.json").read_text(encoding="utf-8"))
        fixtures += [(FIXTURES / "cases" / name, check_plan.parse_timetable(c["timetable"]) if "timetable" in c
                      else DEFAULT_TIMETABLE) for name, c in cases.items()]
        bad = 0
        for path, timetable in fixtures:
            text = path.read_bytes().decode("utf-8")  # keep CR and CRLF as written, as the selftest does
            py, php = python_result(text, holidays, timetable, year), php_file(path, holidays_file, timetable, year)
            diff = differences(py, php)
            if diff:
                bad += 1
                report(path.name, text, py, php, diff)
        print(f"fixtures: {len(fixtures)} plans, {bad} with differences")
        failures += bad

        # Edge probes.
        requests = []
        for _, text, over in PROBES:
            requests.append({"text": text, "holidays": over.get("holidays", holidays),
                             "timetable": over.get("timetable", DEFAULT_TIMETABLE), "year": over.get("year", year)})
        bad = 0
        for (name, text, _), request, php in zip(PROBES, requests, php_batch(requests)):
            py = python_result(text, request["holidays"], request["timetable"], request["year"])
            diff = differences(py, php)
            if diff:
                bad += 1
                report(name, text, py, php, diff)
        print(f"edge probes: {len(PROBES)} inputs, {bad} with differences")
        failures += bad

    # Random plans.
    indexes = [args.only] if args.only is not None else range(args.count)
    plans = [random_plan(args.seed, i) for i in indexes]
    requests = [{"text": t, "holidays": inputs[h], "timetable": tt, "year": y} for t, h, tt, y in plans]
    stats = Counter()
    bad = 0
    for index, (text, h, timetable, y), php in zip(indexes, plans, php_batch(requests)):
        py = python_result(text, inputs[h], timetable, y)
        if args.only is not None:
            print(f"random plan {index} (seed {args.seed}), holidays {h}, timetable {timetable}, year {y}:")
            print(text)
            print("python", json.dumps(py, ensure_ascii=False)[:2000])
            print("php   ", json.dumps(php, ensure_ascii=False)[:2000])
        if "summary" in py:
            stats["lines"] += len(text.splitlines())
            stats["days"] += len(py["days"])
            stats.update(i["code"] for i in py["issues"])
        diff = differences(py, php)
        if diff:
            bad += 1
            if bad <= args.show:
                report(f"random plan {index}", text, py, php, diff)
            if args.save:
                args.save.mkdir(parents=True, exist_ok=True)
                (args.save / f"plan-{args.seed}-{index}.md").write_bytes(text.encode("utf-8"))
    codes = ", ".join(f"{code} {n}" for code, n in sorted(stats.items()) if code not in ("lines", "days"))
    print(f"random: {len(plans)} plans (seed {args.seed}; {stats['lines']} lines, {stats['days']} days; issues: {codes}), "
          f"{bad} with differences")
    failures += bad
    if args.only is not None:
        return 1 if failures else 0

    print("\n" + ("no differences" if not failures else f"{failures} input(s) with differences"))
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
