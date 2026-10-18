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

use local_classprogress\local\calendar_result;
use local_classprogress\local\calendar_session;
use local_classprogress\local\holiday_entry;
use local_classprogress\local\plan_service;

/**
 * The calendar of a plan: sessions, hours, sessions per month and sessions lost to each holiday.
 *
 * Every figure is read from the calendar result as it is; nothing is counted again here.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_view implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param calendar_result $result The plan's calendar.
     * @param holiday_entry[] $holidays The school year's holidays, under the keys used in the result's lost sessions.
     */
    public function __construct(
        /** @var calendar_result The plan's calendar. */
        private readonly calendar_result $result,
        /** @var holiday_entry[] The school year's holidays, under the keys used in the result's lost sessions. */
        private readonly array $holidays,
    ) {
    }

    /**
     * Builds the view of one of the user's plans.
     *
     * @param int $userid The acting user.
     * @param \stdClass $plan The plan row, already read as the user's.
     * @return self The view.
     */
    public static function for_plan(int $userid, \stdClass $plan): self {
        return new self(
            plan_service::calendar($userid, (int) $plan->id),
            plan_service::holidays($userid, (int) $plan->id)
        );
    }

    /**
     * Exports the calendar for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return \stdClass Template context.
     */
    #[\Override]
    public function export_for_template(\renderer_base $output): \stdClass {
        $result = $this->result;
        if ($result->code !== null) {
            return (object) ['message' => get_string('calendar_' . $result->code, 'local_classprogress')];
        }
        $months = [];
        foreach ($result->sessionspermonth as $month => $count) {
            $months[] = ['month' => self::month_name($month), 'sessions' => $count];
        }
        $holidays = [];
        foreach ($this->holidays as $key => $holiday) {
            $range = (object) ['start' => $holiday->start, 'end' => $holiday->end];
            $dates = get_string('daterange', 'local_classprogress', $range);
            $holidays[] = [
                'dates' => $holiday->start === $holiday->end ? $holiday->start : $dates,
                'label' => $holiday->label,
                'lost' => $result->lost[$key] ?? 0,
            ];
        }
        return (object) [
            'message' => null,
            'summary' => self::summary(count($result->sessions), $result->totalhours),
            'months' => $months,
            'hasholidays' => !empty($holidays),
            'holidays' => $holidays,
            'hassessions' => !empty($result->sessions),
            'sessions' => array_map(fn(calendar_session $s): array => [
                'date' => $s->date,
                'weekday' => self::weekday_name($s->date),
                'hours' => format_float($s->hours, 2, true, true),
            ], $result->sessions),
        ];
    }

    /**
     * Writes the number of sessions and hours, in the singular for exactly one.
     *
     * @param int $sessions Number of sessions.
     * @param float $hours Total hours.
     * @return string Such as "60 sessions, 120 hours of class" or "1 session, 1.5 hours of class".
     */
    private static function summary(int $sessions, float $hours): string {
        $formatted = format_float($hours, 2, true, true);
        return get_string('calendarsummary', 'local_classprogress', (object) [
            'sessions' => get_string($sessions === 1 ? 'summarysessionsone' : 'summarysessions', 'local_classprogress', $sessions),
            'hours' => get_string($hours == 1.0 ? 'summaryhoursone' : 'summaryhours', 'local_classprogress', $formatted),
        ]);
    }

    /**
     * Names a month in the user's language.
     *
     * @param string $month The month, yyyy-mm.
     * @return string Such as "September 2026".
     */
    private static function month_name(string $month): string {
        $day = new \DateTimeImmutable($month . '-15', new \DateTimeZone('UTC'));
        return userdate($day->getTimestamp(), get_string('strftimemonthyear', 'langconfig'), 'UTC');
    }

    /**
     * Names the weekday of a date in the user's language.
     *
     * @param string $date The day, yyyy-mm-dd.
     * @return string Such as "Monday".
     */
    private static function weekday_name(string $date): string {
        $day = new \DateTimeImmutable($date . ' 12:00', new \DateTimeZone('UTC'));
        return userdate($day->getTimestamp(), '%A', 'UTC');
    }
}
