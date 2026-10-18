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

namespace local_classprogress\output;

use local_classprogress\local\plan_issue;
use local_classprogress\local\plan_result;
use local_classprogress\local\plan_service;

/**
 * What a plan text gives, before it is saved or approved: the module it stores, its blocks, days and hours, and every
 * error and warning with its line.
 *
 * Messages come from the lang strings issue_<code>, with the issue's values; they and the names are exported as typed,
 * for the template to escape. For a text about to be imported, the module is the one the import stores; for the stored
 * text (approval), the plan's own, which approval keeps.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_preview implements \renderable, \templatable {
    /** @var int Longest quoted heading in a message, in characters. */
    private const MAX_QUOTE = 80;

    /**
     * Constructor.
     *
     * @param \stdClass $plan The plan row the text is for.
     * @param plan_result $result What the text gives, from plan_service::check() or check_stored().
     * @param bool $stored Whether the result is of the plan's stored text (check_stored()).
     */
    public function __construct(
        /** @var \stdClass The plan row the text is for. */
        private readonly \stdClass $plan,
        /** @var plan_result What the text gives. */
        private readonly plan_result $result,
        /** @var bool Whether the result is of the plan's stored text. */
        private readonly bool $stored = false,
    ) {
    }

    /**
     * Exports the preview for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return \stdClass Template context.
     */
    #[\Override]
    public function export_for_template(\renderer_base $output): \stdClass {
        $typed = index_page::plan_name($this->plan);
        $module = $typed;
        if (!$this->stored) {
            [$code, $title] = plan_service::imported_module($this->plan, $this->result);
            $module = index_page::plan_name((object) ['modulecode' => $code, 'moduletitle' => $title]);
        }
        $blocks = [];
        $hours = 0.0;
        foreach ($this->result->days as $day) {
            $name = $day->block ?? get_string('noblock', 'local_classprogress');
            $blocks[$name] ??= ['name' => $name, 'days' => 0, 'hours' => 0.0];
            $blocks[$name]['days']++;
            $blocks[$name]['hours'] += $day->hours ?? 0.0;
            $hours += $day->hours ?? 0.0;
        }
        foreach ($blocks as $name => $block) {
            $blocks[$name]['hours'] = format_float($block['hours'], 2, true, true);
        }
        $errors = $this->issues(plan_issue::ERROR);
        $warnings = $this->issues(plan_issue::WARNING);
        return (object) [
            'module' => $module,
            'replaces' => $module !== $typed ? get_string('previewreplaces', 'local_classprogress', $typed) : '',
            'hasblocks' => !empty($blocks),
            'blocks' => array_values($blocks),
            'days' => count($this->result->days),
            'hours' => format_float($hours, 2, true, true),
            'haserrors' => !empty($errors),
            'errors' => $errors,
            'haswarnings' => !empty($warnings),
            'warnings' => $warnings,
        ];
    }

    /**
     * The issues of one level, each as "Line N: message".
     *
     * @param string $level plan_issue::ERROR or plan_issue::WARNING.
     * @return array Each with line and text.
     */
    private function issues(string $level): array {
        $issues = [];
        foreach ($this->result->issues as $issue) {
            if ($issue->level !== $level) {
                continue;
            }
            $params = $issue->params;
            if (isset($params['text']) && \core_text::strlen($params['text']) > self::MAX_QUOTE) {
                $params['text'] = \core_text::substr($params['text'], 0, self::MAX_QUOTE) . '…';
            }
            $message = get_string('issue_' . $issue->code, 'local_classprogress', (object) $params);
            $issues[] = [
                'line' => $issue->line,
                'text' => get_string('issueline', 'local_classprogress', (object) ['line' => $issue->line, 'message' => $message]),
            ];
        }
        return $issues;
    }
}
