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

use core\url;
use local_classprogress\local\calendar_result;
use local_classprogress\local\plan_service;
use local_classprogress\local\schoolyear_service;

/**
 * The landing page: the teacher's school years, each with its plans, their dates and their number of sessions.
 *
 * Names are exported as typed, for the template to escape. A plan whose school year is missing, which should never
 * happen, is listed apart so that it can still be opened or deleted.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class index_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass[] $years School year rows keyed by id, in display order, each with holidaycount and evaluationcount.
     * @param \stdClass[] $plans Plan rows.
     * @param calendar_result[] $calendars Plan id => calendar.
     * @param string[] $courses Course id => full name, for the courses the plans are linked to.
     */
    public function __construct(
        /** @var \stdClass[] School year rows, in display order, with holidaycount and evaluationcount. */
        private readonly array $years,
        /** @var \stdClass[] Plan rows. */
        private readonly array $plans,
        /** @var calendar_result[] Plan id => calendar. */
        private readonly array $calendars,
        /** @var string[] Course id => full name. */
        private readonly array $courses,
    ) {
    }

    /**
     * Reads everything the page shows for one user.
     *
     * @param int $userid The acting user.
     * @return self The page.
     */
    public static function for_user(int $userid): self {
        global $DB;

        $years = schoolyear_service::list($userid);
        foreach ($years as $year) {
            $year->holidaycount = count(schoolyear_service::holidays($userid, $year->id));
            $year->evaluationcount = count(schoolyear_service::evaluations($userid, $year->id));
        }
        $plans = plan_service::list($userid);
        $courseids = array_unique(array_filter(array_column($plans, 'courseid')));
        $courses = $courseids ? $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname') : [];
        return new self(
            $years,
            $plans,
            plan_service::calendars($userid, array_keys($plans)),
            array_map(fn(\stdClass $course): string => $course->fullname, $courses)
        );
    }

    /**
     * The name of a plan: its module code and title.
     *
     * @param \stdClass $plan Plan row.
     * @return string The name as typed, not escaped: print it with s() or {{ }}.
     */
    public static function plan_name(\stdClass $plan): string {
        if ($plan->modulecode === '') {
            return $plan->moduletitle;
        }
        return get_string('planname', 'local_classprogress', (object) ['code' => $plan->modulecode, 'title' => $plan->moduletitle]);
    }

    /**
     * Exports the page for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return \stdClass Template context.
     */
    #[\Override]
    public function export_for_template(\renderer_base $output): \stdClass {
        $years = [];
        foreach ($this->years as $year) {
            $yearurl = new url('/local/classprogress/schoolyear.php', ['id' => $year->id]);
            $plans = array_filter($this->plans, fn(\stdClass $plan): bool => (int) $plan->schoolyearid === (int) $year->id);
            $years[] = [
                'name' => $year->name,
                'dates' => self::dates($year->startdate, $year->enddate),
                'counts' => get_string('schoolyearcounts', 'local_classprogress', (object) [
                    'holidays' => $year->holidaycount,
                    'evaluations' => $year->evaluationcount,
                ]),
                'editurl' => $yearurl->out(false),
                'deleteurl' => (new url($yearurl, ['action' => 'delete']))->out(false),
                'addplanurl' => (new url('/local/classprogress/plan.php', ['schoolyearid' => $year->id]))->out(false),
                'hasplans' => !empty($plans),
                'plans' => array_values(array_map(fn(\stdClass $plan): array => $this->export_plan($plan), $plans)),
            ];
        }
        $orphans = array_filter($this->plans, fn(\stdClass $plan): bool => !isset($this->years[$plan->schoolyearid]));
        return (object) [
            'addyearurl' => (new url('/local/classprogress/schoolyear.php'))->out(false),
            'hasyears' => !empty($years),
            'years' => $years,
            'hasorphans' => !empty($orphans),
            'orphans' => array_values(array_map(fn(\stdClass $plan): array => $this->export_plan($plan), $orphans)),
        ];
    }

    /**
     * Exports one plan row.
     *
     * @param \stdClass $plan Plan row.
     * @return array Template context for the row.
     */
    private function export_plan(\stdClass $plan): array {
        $calendar = $this->calendars[$plan->id];
        $course = $this->courses[$plan->courseid ?? 0] ?? null;
        $courseid = (int) ($plan->courseid ?? 0);
        return [
            'name' => self::plan_name($plan),
            'course' => $course === null ? '' : format_string($course, true, ['context' => \context_course::instance($courseid)]),
            'dates' => $plan->startdate && $plan->enddate ? self::dates($plan->startdate, $plan->enddate) : '',
            'sessions' => $calendar->code === null ? count($calendar->sessions) : '',
            'hours' => $calendar->code === null ? format_float($calendar->totalhours, 2, true, true) : '',
            'url' => (new url('/local/classprogress/plan.php', ['id' => $plan->id]))->out(false),
            'deleteurl' => (new url('/local/classprogress/plan.php', ['id' => $plan->id, 'action' => 'delete']))->out(false),
        ];
    }

    /**
     * Writes a range of days.
     *
     * @param string $start First day, yyyy-mm-dd.
     * @param string $end Last day, yyyy-mm-dd.
     * @return string Such as "2026-09-14 to 2027-06-21".
     */
    private static function dates(string $start, string $end): string {
        return get_string('daterange', 'local_classprogress', (object) ['start' => $start, 'end' => $end]);
    }
}
