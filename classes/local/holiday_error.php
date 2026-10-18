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
 * One rejected holiday line. The reason is a code (a holiday_parser constant); the message comes from a lang string.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class holiday_error {
    /**
     * Constructor.
     *
     * @param int $line Line number in the holiday text, starting at 1.
     * @param string $code Reason: a holiday_parser constant, or schoolyear_service::LABEL_TOO_LONG.
     * @param string $text The line as written, trimmed.
     */
    public function __construct(
        /** @var int Line number in the holiday text, starting at 1. */
        public readonly int $line,
        /** @var string Reason: a holiday_parser constant, or schoolyear_service::LABEL_TOO_LONG. */
        public readonly string $code,
        /** @var string The line as written, trimmed. */
        public readonly string $text,
    ) {
    }
}
