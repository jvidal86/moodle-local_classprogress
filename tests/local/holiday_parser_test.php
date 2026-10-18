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
 * Tests for the holiday text parser.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class holiday_parser_test extends \basic_testcase {
    /**
     * A line with one ISO date and a label is a one-day holiday.
     *
     * @covers \local_classprogress\local\holiday_parser
     * @group REQ-HOL-001
     */
    public function test_single_iso_date_with_label_is_one_day_holiday(): void {
        $result = holiday_parser::parse("2026-10-12 Festa Nacional d'Espanya");

        $this->assertSame([], $result->errors);
        $this->assertCount(1, $result->entries);
        $entry = $result->entries[0];
        $this->assertSame(1, $entry->line);
        $this->assertSame('2026-10-12', $entry->start);
        $this->assertSame('2026-10-12', $entry->end);
        $this->assertSame("Festa Nacional d'Espanya", $entry->label);
    }
}
