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

use core\hook\navigation\primary_extend;

/**
 * Callbacks of the hooks registered in db/hooks.php.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Adds the "Class progress" link to the primary navigation for users who qualify.
     *
     * It runs on every page, also while Moodle is being installed, when it returns before touching roles or
     * caches. The decision comes from the session cache of access::show_link(), so a warm page runs no query.
     * Pages of the plugin can mark the link active with $PAGE->set_primary_active_tab('local_classprogress').
     *
     * @param primary_extend $hook The primary navigation hook.
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        if (during_initial_install() || !access::show_link()) {
            return;
        }
        $hook->get_primaryview()->add_node(\navigation_node::create(
            get_string('pluginname', 'local_classprogress'),
            new \core\url('/local/classprogress/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_classprogress'
        ));
    }
}
