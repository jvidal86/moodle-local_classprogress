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
 * Deletes everything a user owns in the plugin, for the privacy provider and the account deletion observer.
 *
 * A user owns their school years, with the holidays and evaluation periods in them. The plan activities that used
 * them keep their own data (mod_classprogress): an approved plan its copy, a draft asks for another school year.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class data_eraser {
    /** @var string[] Tables that hang off a school year, without the local_classprogress_ prefix. */
    private const SCHOOLYEAR_CHILDREN = ['holiday', 'evaluation'];

    /**
     * Deletes all rows owned by a user, in one transaction.
     *
     * @param int $userid The owner.
     */
    public static function delete_user_data(int $userid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $params = ['userid' => $userid];
            $years = 'SELECT id FROM {local_classprogress_schoolyear} WHERE userid = :userid';
            foreach (self::SCHOOLYEAR_CHILDREN as $table) {
                $DB->delete_records_select('local_classprogress_' . $table, "schoolyearid IN ({$years})", $params);
            }
            $DB->delete_records('local_classprogress_schoolyear', $params);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }
}
