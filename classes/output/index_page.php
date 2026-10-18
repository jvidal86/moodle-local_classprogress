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
use local_classprogress\local\schoolyear_service;

/**
 * The landing page: the teacher's school years, and every plan activity the teacher can open, across courses, with its
 * course, status and number of pending sessions (REQ-MOD-005).
 *
 * The activities come from the course activity mod_classprogress (\mod_classprogress\local\overview), read only when
 * that plugin is installed, so this plugin does not depend on it. School year names are exported as typed, for the
 * template to escape; activity and course names come already formatted by the activity plugin.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class index_page implements \renderable, \templatable {
    /** @var string The class of the activity plugin that lists a user's plan activities. */
    public const OVERVIEW = '\mod_classprogress\local\overview';

    /**
     * Constructor.
     *
     * @param \stdClass[] $years School year rows keyed by id, in display order, each with holidaycount and evaluationcount.
     * @param array[] $activities Plan activities as \mod_classprogress\local\overview::for_user() gives them.
     */
    public function __construct(
        /** @var \stdClass[] School year rows, in display order, with holidaycount and evaluationcount. */
        private readonly array $years,
        /** @var array[] Plan activities: name, course, status, pending and url. */
        private readonly array $activities,
    ) {
    }

    /**
     * Reads everything the page shows for one user.
     *
     * @param int $userid The acting user.
     * @return self The page.
     */
    public static function for_user(int $userid): self {
        $years = schoolyear_service::list($userid);
        foreach ($years as $year) {
            $year->holidaycount = count(schoolyear_service::holidays($userid, $year->id));
            $year->evaluationcount = count(schoolyear_service::evaluations($userid, $year->id));
        }
        return new self($years, self::activities($userid));
    }

    /**
     * The plan activities the user can open, when the activity plugin is installed.
     *
     * @param int $userid The acting user.
     * @return array[] Plan activities; none without the activity plugin.
     */
    public static function activities(int $userid): array {
        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_classprogress');
        if (!$plugin || !$plugin->is_installed_and_upgraded() || !class_exists(self::OVERVIEW)) {
            return [];
        }
        $overview = self::OVERVIEW;
        return $overview::for_user($userid);
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
            $years[] = [
                'name' => $year->name,
                'dates' => get_string('daterange', 'local_classprogress', (object) [
                    'start' => $year->startdate,
                    'end' => $year->enddate,
                ]),
                'counts' => get_string('schoolyearcounts', 'local_classprogress', (object) [
                    'holidays' => $year->holidaycount,
                    'evaluations' => $year->evaluationcount,
                ]),
                'editurl' => $yearurl->out(false),
                'deleteurl' => (new url($yearurl, ['action' => 'delete']))->out(false),
            ];
        }
        $activities = [];
        foreach ($this->activities as $activity) {
            $activities[] = [
                'name' => $activity['name'],
                'course' => $activity['course'],
                'status' => $activity['status'],
                'pending' => $activity['pending'] === null ? '' : (string) $activity['pending'],
                'url' => $activity['url'],
            ];
        }
        return (object) [
            'addyearurl' => (new url('/local/classprogress/schoolyear.php'))->out(false),
            'hasyears' => !empty($years),
            'years' => $years,
            'hasactivities' => !empty($activities),
            'activities' => $activities,
        ];
    }
}
