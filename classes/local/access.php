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
 * Who may use the plugin.
 *
 * A user qualifies when they hold the capability in at least one course, since teachers hold their roles
 * in courses, not in the system context. Pages call require_user() right after require_login(), and it
 * always checks live; only the top-bar link uses the cached decision of show_link().
 *
 * The capability never proves ownership: site administrators qualify too, so every query of the
 * plugin's records is scoped by owner.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string The capability that admits a user to the plugin. */
    public const CAPABILITY = 'local/classprogress:use';

    /**
     * Whether the user holds the capability in at least one course, checked live.
     *
     * Guests and the visitor who is not logged in never qualify. A teacher whose enrolment is suspended
     * still does, because the role assignment stays; removing the role takes access away at once.
     *
     * @param int $userid The user to check.
     * @return bool True when the user may use the plugin.
     */
    public static function qualifies(int $userid): bool {
        global $USER;

        // User id 0 would be read as "the current user" by get_user_capability_course().
        if ($userid <= 0 || isguestuser($userid)) {
            return false;
        }
        // The code is newer than the installation: the capability does not exist yet.
        if (!get_capability_info(self::CAPABILITY)) {
            return false;
        }
        if ($userid == $USER->id) {
            // The session keeps a copy of the user's role assignments. has_capability() reloads it when a role
            // was given or removed since; get_user_capability_course() does not, so do it here.
            \context_system::instance()->reload_if_dirty();
        }
        return !empty(get_user_capability_course(self::CAPABILITY, $userid, true, '', '', 1));
    }

    /**
     * Refuses the current user unless they qualify; pages call it right after require_login().
     *
     * @throws \required_capability_exception When the current user does not qualify.
     */
    public static function require_user(): void {
        global $USER;

        if (!self::qualifies((int) $USER->id)) {
            throw new \required_capability_exception(\context_system::instance(), self::CAPABILITY, 'nopermissions', '');
        }
    }

    /**
     * Whether the top-bar link is shown to the current user.
     *
     * The answer is kept in the session cache, so a role given or removed later shows only after the next
     * login. Never use it to protect a page or a service: that is require_user().
     *
     * @return bool True when the link is shown.
     */
    public static function show_link(): bool {
        global $USER;

        if (!isloggedin() || isguestuser() || !get_capability_info(self::CAPABILITY)) {
            return false;
        }
        $cache = \cache::make('local_classprogress', 'showlink');
        $show = $cache->get($USER->id);
        if ($show === false) {
            $show = self::qualifies((int) $USER->id) ? 1 : 0;
            $cache->set($USER->id, $show);
        }
        return !empty($show);
    }
}
