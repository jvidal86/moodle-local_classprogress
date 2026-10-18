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

namespace local_classprogress\event;

/**
 * Base of the plugin's events: a change to a teacher's own data, logged in that teacher's user context.
 *
 * The object and every value in other are ids, never plan or entry text, because the log store keeps them outside
 * the teacher's privacy export. validate_data() enforces it: other must hold exactly the keys of other_ids(), each an
 * id. The entry history stays the authoritative record; events are a second trail.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \core\event\base {
    /** @var string Create, read, update or delete: c, r, u or d. */
    protected const CRUD = '';

    /** @var string Table of the event's object. */
    protected const OBJECTTABLE = '';

    /**
     * The ids that other must hold.
     *
     * @return array Key of other => [table the id points to, whether it may be null].
     */
    abstract protected static function other_ids(): array;

    /**
     * Sets the event's crud, education level and object table.
     */
    protected function init(): void {
        $this->data['crud'] = static::CRUD;
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = static::OBJECTTABLE;
    }

    /**
     * Checks that the event has an object, is in a user context, and that other holds exactly its ids.
     *
     * @throws \coding_exception When any of that is not so.
     */
    protected function validate_data(): void {
        parent::validate_data();

        if (empty($this->objectid)) {
            throw new \coding_exception('The objectid must be set.');
        }
        if ((int) $this->contextlevel !== CONTEXT_USER) {
            throw new \coding_exception('The context must be the user context of the owner.');
        }

        $other = is_array($this->other) ? $this->other : [];
        $ids = static::other_ids();
        foreach ($ids as $key => [, $nullable]) {
            if (!array_key_exists($key, $other)) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
            $value = $other[$key];
            $isid = is_int($value) || (is_string($value) && ctype_digit($value));
            if (!$isid && !($nullable && $value === null)) {
                throw new \coding_exception("The '{$key}' value in other must be an id.");
            }
        }
        $unknown = array_diff_key($other, $ids);
        if ($unknown) {
            throw new \coding_exception('Other may only hold ids; unknown keys: ' . implode(', ', array_keys($unknown)) . '.');
        }
    }

    /**
     * The object is private to its owner and is never restored into a course.
     *
     * @return array The table of the object, not mapped.
     */
    public static function get_objectid_mapping(): array {
        return ['db' => static::OBJECTTABLE, 'restore' => self::NOT_MAPPED];
    }

    /**
     * The ids in other point to private rows that are never restored into a course.
     *
     * @return array Key of other => its table, not mapped.
     */
    public static function get_other_mapping(): array {
        $mapping = [];
        foreach (static::other_ids() as $key => [$table]) {
            $mapping[$key] = ['db' => $table, 'restore' => self::NOT_MAPPED];
        }
        return $mapping;
    }
}
