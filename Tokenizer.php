<?php

namespace Markpruett571\PhpYake\PhpSyntok;

class Tokenizer
{
    const HYPHENS = "\x{00AD}\x{058A}\x{05BE}\x{0F0C}\x{1400}\x{1806}\x{2010}\x{2011}\x{2012}\x{2e17}\x{30A0}-";
    const APOSTROPHES = "'\x{00B4}\x{02B9}\x{02BC}\x{2019}\x{2032}";
    const APOSTROPHE_T = '/[' . self::APOSTROPHES . ']t/u';

    public function __construct(public bool $emitHyphenOrUnderscoreSep = false, public bool $replaceNotContraction = true)
    {

    }

    public function split(string $text): array
    {
        return [$this->tokenize($text)];
    }

    public function tokenize(string $text, int $base_offset = 0) {
        if ($base_offset > 0) {
            $text = str_repeat(" ", $base_offset) . $text;
        }

        $offset = $base_offset;
        $this->spaces($text, $matches);

        foreach ($matches[0] as $index => $mo) {
            $moStart = $mo[1];
            $moEnd = $mo[1] + strlen($mo[0]);

            $start = $this->findStart($moStart, $moEnd, $text);

            if ($start == $moEnd) {
                yield new Token(substr($text, $offset, $moStart), $mo[0], $moStart);
            } else {
                $end = $this->findEnd($start, $moEnd, $text);

                if ($start > $moStart) {
                    $result = $this->splitNonwordPrefix($mo, $offset, $start, $text);

                    foreach ($result['tokens'] as $token) {
                        yield $token;
                    }

                    $offset = $result['offset'] - 1;
                }

                if ($start != $end) {
                    if ($index === 0) {
                        $word = substr($text, $start, $end - $offset);
                    } else {
                        $word = substr($text, $start, $end - $offset - 1);
                    }

                    foreach ($this->splitWord(substr($text, $offset, $start - $offset), $word, $start) as $token) {
                        yield $token;
                    }
                }

                $tail = substr($text, $end, $moEnd - $end);

                if (substr($tail, 0, 3) == "...") {
                    yield new Token("", "...", $end);
                    $end += 3;
                    $tail = substr($tail, 3);
                }

                for ($idx = 0; $idx < strlen($tail); $idx++) {
                    yield new Token("", $tail[$idx], $idx + $end);
                }
            }

            $offset = $moEnd;
        }

        if ($offset < strlen($text)) {
            yield new Token(substr($text, $offset), "", strlen($text));
        }
    }

    public static function findStart(int $start, int $end, string $text): int {
        for ($c = $start; $c < $end; $c++) {
            if (ctype_alnum($text[$c])) {
                break;
            }
            $start += 1;
        }
        return $start;
    }

    public static function findEnd(int $start, int $end, string $text): int {
        for ($c = $end - 1; $c >= $start; $c--) {
            if (ctype_alnum($text[$c])) {
                break;
            }
            $end -= 1;
        }
        return $end;
    }

    public static function splitNonwordPrefix($mo, int $offset, int $start, string $text) {
        $tokens = [];

        for ($i = 0; $i < strlen(substr($text, $mo[1], $offset - $start)); $i++) {
            $c = $text[$mo[1] + $i];
            if ($i == 0) {
                $tokens[] = new Token(substr($text, $offset, $mo[1]), $c, $mo[1]);
                $offset = $start;
            } else {
                $tokens[] = new Token("", $c, $mo[1] + $i);
            }
        }

        return ['tokens' => $tokens, 'offset' => $offset];
    }

    public function splitWord(string $prefix, string $word, int $offset) {
        $remainder = 0;

        $this->separation($word, $matches);

        foreach ($matches[0] as $mo) {
            $result = $this->produceSeparatorSplitToken($remainder, $word, $mo, $prefix, $offset);
            $prefix = $result['prefix'];

            foreach ($result['tokens'] as $token) {
                yield $token;
            }

            $remainder = $mo[1] + strlen($mo[0]);
        }

        if ($remainder == 0) {
            yield new Token($prefix, $word, $offset);
        } elseif ($remainder < strlen($word)) {
            yield new Token($prefix, substr($word, $remainder), $offset + $remainder);
        }
    }

    public function produceSeparatorSplitToken(int $remainder, string $word, array $mo, string $prefix, int $offset) {
        $tokens = [];

        if ($mo[1] > $remainder) {
            if (preg_match(self::APOSTROPHE_T, $mo[0]) && $word[$mo[1] - 1] == 'n') {
                if ($remainder < $mo[1] - 1) {
                    $tokens[] = new Token($prefix, substr($word, $remainder, $mo[1] - 1), $offset + $remainder);
                    $prefix = "";
                }

                $tokens[] = new Token($prefix, $this->replaceNotContraction ? "not" : 'n' . $mo[0], $offset + $mo[1] - 1);
                return ['tokens' => $tokens, 'prefix' => ""];
            }

            $tokens[] = new Token($prefix, substr($word, $remainder, $mo[1] - $remainder), $offset + $remainder);
            $prefix = "";
        }

        $separator = $mo[0];

        if ($separator && $this->canEmit($separator)) {
            $tokens[] = new Token($prefix, $separator, $offset + $mo[1]);
            return ['tokens' => $tokens, 'prefix' => ""];
        } else {
            return ['tokens' => $tokens, 'prefix' => $prefix . $separator];
        }
    }

    public function canEmit(string $separator) {
        return $this->emitHyphenOrUnderscoreSep || !str_contains($this->hyphensAndUnderscore(), $separator);
    }

    public static function hyphensAndUnderscore() {
        return self::HYPHENS . "_";
    }

    public static function hyphenNewline() {
        return '/(?<=\p{L})[' . self::HYPHENS . '][ \t\u00a0\r]*\n[ \t\u00a0]*(?=\\p{L})/u';
    }

    public static function separation($input, &$matches) {
        return preg_match_all(
            '/(?<=\p{Ll})[.!?]?(?=\p{Lu})|' .  // lowercase-uppercase transitions
            '[' . "'\x{00B4}\x{02B9}\x{02BC}\x{2019}\x{2032}" . ']\p{L}+|' .  // apostrophes and their tail
            '[\p{Ps}\p{Pe}]|' .   // parenthesis and open/close punctuation
            '\.\.\.|' .  // inner ellipsis
            '(?<=\p{L})[,;_' . "\x{00AD}\x{058A}\x{05BE}\x{0F0C}\x{1400}\x{1806}\x{2010}\x{2011}\x{2012}\x{2e17}\x{30A0}-" . '](?=[\p{L}\p{Nd}])|' .  // dash-not-digits transition prefix
            '(?<=[\p{L}\p{Nd}])[,;_' . "\x{00AD}\x{058A}\x{05BE}\x{0F0C}\x{1400}\x{1806}\x{2010}\x{2011}\x{2012}\x{2e17}\x{30A0}-" . '](?=\p{L})/u', $input  // dash-not-digits transition postfix
        , $matches, PREG_OFFSET_CAPTURE);
    }

    public static function spaces($input, &$matches) {
        return preg_match_all('/[^\s\x{200b}]+/u', $input, $matches, PREG_OFFSET_CAPTURE);
    }

    public static function joinHyphenatedWordsAcrossLinebreaks(string $text): string {
        return preg_replace(self::hyphenNewline(), "", $text);
    }

    public static function to_text(array $tokens): string {
        return implode("", array_map("strval", $tokens));
    }
}