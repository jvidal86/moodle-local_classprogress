<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Prints what the PHP plan parser reads from a plan, as JSON. Used by tools/compare_parsers.py; runs without Moodle.
 *
 *     php tools/plan_parser_cli.php tests/fixtures/plans/0372.md --holidays=tests/fixtures/holidays-expanded.json \
 *         --timetable=0:2,4:2 --year=2026-09-14:2027-06-21      # prints summary()
 *     php tools/plan_parser_cli.php plan.md --full              # also module, blocks, days and issues
 *     php tools/plan_parser_cli.php --jsonl < requests.jsonl    # one request per line in, one full result per line out
 *
 * Holidays are a JSON file of yyyy-mm-dd to label, like tests/fixtures/holidays-expanded.json. Timetable and year are
 * written as for tools/check_plan.py (Monday = 0; "none" for no timetable). A JSON-lines request is
 * {"text": "...", "holidays": {"2026-10-12": "..."}, "timetable": {"0": 2.0}, "year": ["2026-09-14", "2027-06-21"]},
 * any of the last three null or left out. Infinite hours, which JSON cannot hold, are printed as "INF".
 *
 * Development tool only: tools/ is not part of the release.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_classprogress\local\plan_parser;
use local_classprogress\local\plan_result;

foreach (['plan_module', 'plan_day', 'plan_issue', 'plan_result', 'plan_parser'] as $classname) {
    require_once(__DIR__ . '/../classes/local/' . $classname . '.php');
}

/**
 * Turns a parse result into plain arrays for JSON.
 *
 * @param plan_result $result The parse result.
 * @param bool $full Whether to add module, blocks, days and issues to the summary.
 * @return array
 */
function plan_parser_cli_export(plan_result $result, bool $full): array {
    $summary = $result->summary();
    $summary['hours'] = plan_parser_cli_hours($summary['hours']);
    if (!$full) {
        return $summary;
    }
    $module = $result->module;
    return [
        'summary' => $summary,
        'module' => $module === null ? null : ['code' => $module->code, 'title' => $module->title, 'line' => $module->line],
        'blocks' => $result->blocks,
        'days' => array_map(fn($day): array => [
            'date' => $day->date,
            'hours' => plan_parser_cli_hours($day->hours),
            'title' => $day->title,
            'block' => $day->block,
            'line' => $day->line,
            'content' => $day->content,
        ], $result->days),
        'issues' => array_map(fn($issue): array => [
            'level' => $issue->level,
            'code' => $issue->code,
            'line' => $issue->line,
            'params' => (object) $issue->params,
        ], $result->issues),
    ];
}

/**
 * Hours as JSON can hold them.
 *
 * @param float|null $hours Hours.
 * @return float|string|null The hours, or "INF" for infinite hours.
 */
function plan_parser_cli_hours(?float $hours): float|string|null {
    return $hours !== null && is_infinite($hours) ? 'INF' : $hours;
}

/**
 * Reads a timetable written as for tools/check_plan.py.
 *
 * @param string|null $spec Weekday:hours pairs such as "0:2,4:2", or "none".
 * @return array<int, float>|null
 */
function plan_parser_cli_timetable(?string $spec): ?array {
    if ($spec === null || $spec === '' || $spec === 'none') {
        return null;
    }
    $timetable = [];
    foreach (explode(',', $spec) as $pair) {
        [$weekday, $hours] = explode(':', $pair);
        $timetable[(int) $weekday] = (float) $hours;
    }
    return $timetable;
}

/**
 * Encodes a value as one line of JSON.
 *
 * @param mixed $value The value.
 * @return string
 */
function plan_parser_cli_json(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR);
}

// Options may come before or after the file name, which getopt() does not allow.
$options = [];
$files = [];
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--')) {
        [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, true);
        $options[$name] = $value;
    } else {
        $files[] = $argument;
    }
}
$parser = new plan_parser();

if (isset($options['jsonl'])) {
    while (($line = fgets(STDIN)) !== false) {
        if (trim($line) === '') {
            continue;
        }
        $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $timetable = isset($request['timetable']) ? array_map('floatval', $request['timetable']) : null;
        try {
            $result = $parser->parse($request['text'], $request['holidays'] ?? null, $timetable, $request['year'] ?? null);
            echo plan_parser_cli_json(plan_parser_cli_export($result, true)), "\n";
        } catch (\Throwable $e) {
            echo plan_parser_cli_json(['exception' => get_class($e) . ': ' . $e->getMessage()]), "\n";
        }
    }
    exit(0);
}

$file = $files[0] ?? null;
if (count($files) !== 1 || !is_readable($file)) {
    fwrite(STDERR, "Usage: php tools/plan_parser_cli.php PLAN.md [--holidays=FILE.json] [--timetable=0:2,4:2|none]"
        . " [--year=START:END] [--full]\n       php tools/plan_parser_cli.php --jsonl < REQUESTS.jsonl\n");
    exit(2);
}
$holidays = isset($options['holidays'])
    ? json_decode(file_get_contents($options['holidays']), true, 512, JSON_THROW_ON_ERROR)
    : null;
$year = isset($options['year']) ? explode(':', $options['year']) : null;
$result = $parser->parse(file_get_contents($file), $holidays, plan_parser_cli_timetable($options['timetable'] ?? null), $year);
echo plan_parser_cli_json(plan_parser_cli_export($result, isset($options['full']))), "\n";
