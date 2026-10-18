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

use local_classprogress\local\plan_service;

/**
 * The status of a plan and its days, grouped by RA or block in the order of the text.
 *
 * Block and day titles are exported as typed, for the template to escape. The plan text of each day goes through
 * format_text() as Markdown with cleaning on, so script, event handlers and raw HTML from the plan do nothing.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_view implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass $plan The plan row.
     * @param \stdClass[] $items Its items, in the order of the text.
     */
    public function __construct(
        /** @var \stdClass The plan row. */
        private readonly \stdClass $plan,
        /** @var \stdClass[] Its items, in the order of the text. */
        private readonly array $items,
    ) {
    }

    /**
     * Reads a plan's items for one user.
     *
     * @param int $userid The acting user.
     * @param \stdClass $plan The plan row, one of the user's.
     * @return self The view.
     */
    public static function for_plan(int $userid, \stdClass $plan): self {
        return new self($plan, plan_service::items($userid, (int) $plan->id));
    }

    /**
     * Exports the view for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return \stdClass Template context.
     */
    #[\Override]
    public function export_for_template(\renderer_base $output): \stdClass {
        $context = \context_user::instance($this->plan->userid);
        $approved = $this->plan->status === plan_service::STATUS_APPROVED;
        $blocks = [];
        $last = null;
        foreach ($this->items as $item) {
            if ($last === null || $last !== $item->block) {
                $name = $item->block === '' ? get_string('noblock', 'local_classprogress') : $item->block;
                $blocks[] = ['name' => $name, 'days' => []];
                $last = $item->block;
            }
            $blocks[count($blocks) - 1]['days'][] = [
                'date' => $item->sessiondate,
                'title' => $item->title,
                'hours' => $item->hours === null ? '' : format_float((float) $item->hours, 2, true, true),
                'content' => format_text($item->content, FORMAT_MARKDOWN, ['context' => $context]),
            ];
        }
        return (object) [
            'approved' => $approved,
            'status' => $approved
                ? get_string('statusapproved', 'local_classprogress', userdate($this->plan->timeapproved))
                : get_string('statusdraft', 'local_classprogress'),
            'hasitems' => !empty($blocks),
            'blocks' => $blocks,
        ];
    }
}
