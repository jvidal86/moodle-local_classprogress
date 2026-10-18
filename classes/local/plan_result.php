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

namespace local_classprogress\local;

/**
 * What the plan parser read from a plan: module, blocks, days and issues.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_result {
    /**
     * Creates the result.
     *
     * @param plan_module|null $module The first # heading, or null when there is none.
     * @param string[] $blocks Text of every ## heading, in order.
     * @param plan_day[] $days Every readable day, in order.
     * @param plan_issue[] $issues Every issue, ordered by line.
     */
    public function __construct(
        /** @var plan_module|null The first # heading, or null when there is none. */
        public readonly ?plan_module $module,
        /** @var string[] Text of every ## heading, in order. */
        public readonly array $blocks,
        /** @var plan_day[] Every readable day, in order. */
        public readonly array $days,
        /** @var plan_issue[] Every issue, ordered by line. */
        public readonly array $issues,
    ) {
    }

    /**
     * Sums the plan up with the keys and meaning of summarize() in tools/check_plan.py.
     *
     * Hours add up the days' hours, a day without hours counting 0. Errors and warnings are sorted lists of codes
     * that keep duplicates.
     *
     * @return array Keys module (?string), code (?string), blocks (int), days (int), hours (float), first and last
     *     (?string, yyyy-mm-dd), errors and warnings (string[]).
     */
    public function summary(): array {
        $hours = 0.0;
        $dates = [];
        foreach ($this->days as $day) {
            $hours += $day->hours ?? 0.0;
            $dates[] = $day->date;
        }
        sort($dates, SORT_STRING);
        return [
            'module' => $this->module?->title,
            'code' => $this->module?->code,
            'blocks' => count($this->blocks),
            'days' => count($this->days),
            'hours' => $hours,
            'first' => $dates[0] ?? null,
            'last' => $dates ? $dates[count($dates) - 1] : null,
            'errors' => $this->codes(plan_issue::ERROR),
            'warnings' => $this->codes(plan_issue::WARNING),
        ];
    }

    /**
     * Lists the codes of the issues of one level, sorted, duplicates kept.
     *
     * @param string $level plan_issue::ERROR or plan_issue::WARNING.
     * @return string[]
     */
    private function codes(string $level): array {
        $codes = [];
        foreach ($this->issues as $issue) {
            if ($issue->level === $level) {
                $codes[] = $issue->code;
            }
        }
        sort($codes, SORT_STRING);
        return $codes;
    }
}
