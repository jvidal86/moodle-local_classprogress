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
 * Tests for Markdown as plain text, for the cells of the export.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_classprogress\local\plain_text
 */
final class plain_text_test extends \basic_testcase {
    /**
     * Markdown and the plain text it gives.
     *
     * @return array
     */
    public static function markdown_provider(): array {
        return [
            'plain text' => ["Normes de l'aula", "Normes de l'aula"],
            'bullets' => ["- Un\n* Dos\n+ Tres", "• Un\n• Dos\n• Tres"],
            'nested bullets keep their indent' => ["- Un\n  - Un i mig", "• Un\n  • Un i mig"],
            'numbered items keep their number' => ["1. Primer\n2) Segon\n10. Desè", "1. Primer\n2) Segon\n10. Desè"],
            'headings' => ["# Títol\n### Apartat ###\nText", "Títol\nApartat\nText"],
            'emphasis' => ['**negreta**, __també__, *cursiva*, _també_ i ~~ratllat~~',
                'negreta, també, cursiva, també i ratllat'],
            'nested emphasis' => ['***les dues***', 'les dues'],
            'underscores inside words stay' => ['- pg_hba.conf, botiga_app i pg_stat_activity',
                '• pg_hba.conf, botiga_app i pg_stat_activity'],
            'lone asterisks stay' => ['5 * 3 i *.sql', '5 * 3 i *.sql'],
            // Backticks are written \x60.
            'code span' => ["Executa \x60SELECT *\x60 i \x60a_b_c **d**\x60", 'Executa SELECT * i a_b_c **d**'],
            'code span with backticks inside' => ["Escriu \x60\x60 \x60x\x60 \x60\x60 aquí", "Escriu \x60x\x60 aquí"],
            'fenced code keeps its lines' => ["Abans\n\x60\x60\x60sql\nSELECT **a** FROM t;\n- no és llista\n\x60\x60\x60\nDesprés",
                "Abans\nSELECT **a** FROM t;\n- no és llista\nDesprés"],
            'tilde fence' => ["~~~\n# no és títol\n~~~", '# no és títol'],
            'link' => ['Mira [la guia](https://example.com/guia_1.html)', 'Mira la guia (https://example.com/guia_1.html)'],
            'link with a title' => ['[Moodle](https://moodle.org "Lloc")', 'Moodle (https://moodle.org)'],
            'image' => ['![Esquema](img/esquema.png)', 'Esquema (img/esquema.png)'],
            'autolink' => ['<https://example.com/a_b>', 'https://example.com/a_b'],
            'html tags' => ['<b>Anna</b> i <span class="x">Pau</span>', 'Anna i Pau'],
            'html line break' => ['Un<br>Dos<br />Tres', "Un\nDos\nTres"],
            'html comment' => ['Un <!-- nota --> dos', 'Un  dos'],
            'text that looks like a tag stays' => ['Pont <- Immaculada i Programació <C++> i 3 < 4 > 2',
                'Pont <- Immaculada i Programació <C++> i 3 < 4 > 2'],
            'entities' => ['&amp; &lt;b&gt; &eacute; &#39; &quot;', '& <b> é \' "'],
            'escapes' => ['\\*no\\* \\_no\\_ \\# \\\\', '*no* _no_ # \\'],
            'blockquote' => ["> Citat\n> > Dins", "Citat\nDins"],
            'thematic break' => ["Un\n---\nDos\n* * *", "Un\n\nDos"],
            'line endings' => ["Un\r\nDos\rTres", "Un\nDos\nTres"],
            'hard breaks' => ["Un  \nDos\\\nTres", "Un\nDos\nTres"],
            'blank lines stay' => ["Un\n\nDos", "Un\n\nDos"],
            'formula-like text stays' => ["=HYPERLINK(\"http://x\",\"y\")\n+1\n-1\n@x",
                "=HYPERLINK(\"http://x\",\"y\")\n+1\n-1\n@x"],
            'a bullet starting with a minus' => ['- -1 punt', '• -1 punt'],
            'empty' => ['', ''],
        ];
    }

    /**
     * Markdown becomes plain text: list items start with "• ", numbered items keep their number, heading, emphasis
     * and code markers go, a link keeps its text and its address in brackets, HTML tags go and entities are decoded,
     * and line breaks stay.
     *
     * @group REQ-EXP-007
     * @dataProvider markdown_provider
     * @param string $markdown The Markdown.
     * @param string $expected The plain text.
     */
    public function test_markdown_becomes_plain_text(string $markdown, string $expected): void {
        $this->assertSame($expected, plain_text::from_markdown($markdown));
    }

    /**
     * Emphasis that is never closed costs time in proportion to the text, not to its square: a plan day at the size
     * limit (200 KB) made of unclosed markers converts at once and loses nothing (R5-2).
     *
     * @group REQ-EXP-007
     */
    public function test_unclosed_emphasis_converts_in_linear_time(): void {
        foreach (['**a ', '__a ', '~~a ', '*a ', '_a '] as $piece) {
            $text = str_repeat($piece, 50000);
            $started = hrtime(true);
            $plain = plain_text::from_markdown($text);
            $this->assertLessThan(1.0, (hrtime(true) - $started) / 1e9, $piece);
            $this->assertSame(rtrim($text), $plain, $piece);
        }
    }

    /**
     * Emphasis up to 300 characters long loses its markers; a longer one keeps them, which is what keeps the conversion
     * linear (R5-2). Nothing of the text is lost either way.
     *
     * @group REQ-EXP-007
     */
    public function test_emphasis_longer_than_300_characters_keeps_its_markers(): void {
        $short = str_repeat('x', 300);
        $long = str_repeat('x', 301);
        $this->assertSame($short, plain_text::from_markdown("**{$short}**"));
        $this->assertSame("**{$long}**", plain_text::from_markdown("**{$long}**"));
    }

    /**
     * The three real plans.
     *
     * @return array
     */
    public static function real_plan_provider(): array {
        $data = [];
        foreach (['0372', '0374', '0377'] as $code) {
            $data['plan ' . $code] = ['plans/' . $code . '.md'];
        }
        return $data;
    }

    /**
     * In the days of the real plans, whose only markup is "- " list items, nothing but the markup changes: every
     * item starts with "• " and every other character stays.
     *
     * @group REQ-EXP-007
     * @dataProvider real_plan_provider
     * @param string $file Plan file, relative to tests/fixtures.
     */
    public function test_real_plans_lose_nothing_but_the_markup(string $file): void {
        $days = (new plan_parser())->parse(file_get_contents(__DIR__ . '/../fixtures/' . $file))->days;

        $this->assertGreaterThan(20, count($days));
        foreach ($days as $day) {
            $text = plain_text::from_markdown($day->content);
            $this->assertSame(preg_replace('/^(\s*)- /m', '$1• ', $day->content), $text, $day->date);
            $this->assertStringNotContainsString("\n- ", "\n" . $text, $day->date);
        }
    }
}
