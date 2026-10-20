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
 * Markdown as plain text, for the cells of the export (REQ-EXP-007).
 *
 * Only the markup goes: list items start with "• " (numbered items keep their number), heading, quote, emphasis and
 * code markers are removed, a link keeps its text followed by its address in round brackets, HTML tags are removed
 * and entities decoded, and line breaks stay. Code keeps its text as typed. A "<" that does not open a tag, such as
 * in "<C++>" or "<-", is text, and so are underscores inside words (pg_hba_file). Pure logic: no Moodle API.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plain_text {
    /** @var string Opens a piece of text set aside from the inline rules; private-use characters never typed. */
    private const OPEN = "\u{E000}";

    /** @var string Closes a piece of text set aside. */
    private const CLOSE = "\u{E001}";

    /** @var string A letter, digit or underscore: what an underscore of emphasis must not touch. */
    private const WORD = '[\p{L}\p{N}_]';

    /**
     * @var int Longest emphasis whose markers go, in characters. The bound keeps each unclosed marker's search short, so
     *     a line full of them costs time in proportion to its length rather than to its square (R5-2).
     */
    private const MAX_EMPHASIS = 300;

    /**
     * Converts Markdown to plain text.
     *
     * @param string $markdown The Markdown, as typed.
     * @return string The plain text, lines separated by "\n".
     */
    public static function from_markdown(string $markdown): string {
        $kept = [];
        $text = self::blocks(str_replace(["\r\n", "\r"], "\n", $markdown), $kept);
        $text = self::inline($text, $kept);
        $restore = fn(array $m): string => $kept[(int) $m[1]];
        // Text set aside may hold more text set aside (a link's address inside a link's text, never deeper than that).
        while (str_contains($text, self::OPEN)) {
            $text = preg_replace_callback('/' . self::OPEN . '(\d+)' . self::CLOSE . '/u', $restore, $text);
        }
        $lines = array_map('rtrim', explode("\n", $text));
        return trim(implode("\n", $lines), "\n");
    }

    /**
     * Removes the block markers of each line: fences, headings, quotes, thematic breaks and bullets.
     *
     * @param string $text The Markdown, lines separated by "\n".
     * @param string[] $kept Text set aside, by number; the lines of code are added to it.
     * @return string The text.
     */
    private static function blocks(string $text, array &$kept): string {
        $lines = [];
        $fence = null;
        foreach (explode("\n", $text) as $line) {
            // A fence is three or more backticks or tildes; it closes with the same character, at least as many times.
            if ($fence === null && preg_match('/^ {0,3}(\x60{3,}|~{3,})/', $line, $m)) {
                $fence = $m[1];
                continue;
            }
            $closes = $fence !== null && preg_match('/^ {0,3}(\x60{3,}|~{3,}) *$/', $line, $m);
            if ($closes && $m[1][0] === $fence[0] && strlen($m[1]) >= strlen($fence)) {
                $fence = null;
                continue;
            }
            if ($fence !== null) {
                $lines[] = self::keep($line, $kept);
                continue;
            }
            $line = preg_replace('/^ {0,3}(> ?)+/', '', $line);
            if (preg_match('/^ {0,3}([-*_])( *\1){2,} *$/', $line) || preg_match('/^ {0,3}=+ *$/', $line)) {
                $lines[] = '';
            } else if (preg_match('/^ {0,3}#{1,6}(?: +(.*?))?(?: +#+)? *$/', $line, $m)) {
                $lines[] = $m[1] ?? '';
            } else {
                $lines[] = preg_replace('/^(\s*)[-*+](?:[ \t]+|$)/', '$1• ', $line);
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Removes the inline markup: code spans, escapes, links, HTML, emphasis and entities.
     *
     * @param string $text The text without block markers.
     * @param string[] $kept Text set aside, by number.
     * @return string The text, with the text set aside still marked.
     */
    private static function inline(string $text, array &$kept): string {
        // Code spans: a run of backticks up to the same run; one space inside each end is padding.
        $code = function (array $m) use (&$kept): string {
            $code = $m[2];
            if (strlen($code) > 2 && $code[0] === ' ' && substr($code, -1) === ' ' && trim($code) !== '') {
                $code = substr($code, 1, -1);
            }
            return self::keep($code, $kept);
        };
        $text = preg_replace_callback('/(?<!\x60)(\x60+)(?!\x60)(.+?)(?<!\x60)\1(?!\x60)/su', $code, $text);
        // Hard line breaks: a backslash at the end of a line.
        $text = preg_replace('/\\\\\n/', "\n", $text);
        // Escaped punctuation is text.
        $keep = function (array $m) use (&$kept): string {
            return self::keep($m[1], $kept);
        };
        $text = preg_replace_callback('/\\\\([!-\/:-@\[-\x60{-~])/', $keep, $text);
        // Autolinks, then links and images: their text, then their address in round brackets.
        $text = preg_replace_callback('/<([a-zA-Z][a-zA-Z0-9+.-]{1,31}:[^\s<>]*)>/', $keep, $text);
        $link = function (array $m) use (&$kept): string {
            return $m[1] . ' (' . self::keep($m[2], $kept) . ')';
        };
        $text = preg_replace_callback('/!?\[([^\]\n]*)\]\(\s*<?([^\s)>]*)>?(?:\s+"[^"\n]*")?\s*\)/u', $link, $text);
        // HTML: a line break tag is a line break; other tags and comments go.
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<!--.*?-->/s', '', $text);
        $text = preg_replace('/<\/?[A-Za-z][A-Za-z0-9-]*(?:\s[^<>]*)?\/?>/', '', $text);
        // Emphasis: strong before plain; an underscore must not touch a letter outside it; at most MAX_EMPHASIS long.
        $word = self::WORD;
        $in = '(.{1,' . self::MAX_EMPHASIS . '}?)';
        $text = preg_replace('/\*\*(?=\S)' . $in . '(?<=\S)\*\*/u', '$1', $text);
        $text = preg_replace("/(?<!{$word})__(?=\\S){$in}(?<=\\S)__(?!{$word})/u", '$1', $text);
        $text = preg_replace('/\*(?=[^\s*])' . $in . '(?<=[^\s*])\*/u', '$1', $text);
        $text = preg_replace("/(?<!{$word})_(?=[^\\s_]){$in}(?<=[^\\s_])_(?!{$word})/u", '$1', $text);
        $text = preg_replace('/~~(?=\S)' . $in . '(?<=\S)~~/u', '$1', $text);
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sets a piece of text aside from the inline rules.
     *
     * @param string $text The text, kept as it is.
     * @param string[] $kept Text set aside, by number.
     * @return string The mark that stands for it.
     */
    private static function keep(string $text, array &$kept): string {
        $kept[] = $text;
        return self::OPEN . (count($kept) - 1) . self::CLOSE;
    }
}
