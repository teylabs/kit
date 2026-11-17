<?php

namespace Tey\Kit\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The server's twin of MiddleTruncate (resources/js/components/Kit/Data/middleTruncate.ts), counting characters instead of pixels: each grapheme is one,
 * and so is the ellipsis. For a label a model builds itself (`Str::middleTruncate("{$code} · {$title}", 40)`): the start and the end stay, the middle is
 * cut. The same anchors as the component: a file name keeps its extension, a path or URL its last segment, a URL its scheme and host and drops its query
 * or fragment first. tests/MiddleTruncateCheck.php holds it to the TypeScript, case by case.
 */
final class MiddleTruncate
{
    public const ELLIPSIS = '…';

    /** What is shown: the head, an ellipsis for a cut middle, the tail, and an ellipsis for a cut query or fragment. Fits `$maxChars` when it can. */
    public static function cut(string $value, int $maxChars, string $kind = 'auto', int $minTail = 0): string
    {
        $parts = self::parts($value, $maxChars, $kind, $minTail);

        return $parts['head'].($parts['middle'] !== '' ? self::ELLIPSIS : '').$parts['tail'].($parts['end'] !== '' ? self::ELLIPSIS : '');
    }

    /**
     * `head` + `middle` + `tail` + `end` is always the whole value, as the TypeScript's middleTruncate returns it.
     *
     * @return array{head: string, middle: string, tail: string, end: string, truncated: bool}
     */
    public static function parts(string $value, int $maxChars, string $kind = 'auto', int $minTail = 0): array
    {
        $kind = self::kind($value, $kind);
        $minTail = max(0, $minTail);
        $measure = fn (string $text): int => count(self::graphemes($text));
        $query = $kind === 'url' ? self::search($value, '/[?#]/') : -1;
        if ($query > 0 && $query < strlen($value) - 1 && $measure($value) > $maxChars) {
            $body = self::split(substr($value, 0, $query + 1), $maxChars, fn (string $text): int => $measure($text) + 1, $kind, $minTail);

            return ['head' => $body['head'], 'middle' => $body['middle'], 'tail' => $body['tail'], 'end' => substr($value, $query + 1), 'truncated' => true];
        }
        $body = self::split($value, $maxChars, $measure, $kind, $minTail);

        return ['head' => $body['head'], 'middle' => $body['middle'], 'tail' => $body['tail'], 'end' => '', 'truncated' => $body['truncated']];
    }

    /** `Str::middleTruncate($value, $maxChars, kind:, minTail:)`, registered on boot unless the host has a macro of that name. */
    public static function macro(): void
    {
        if (! Str::hasMacro('middleTruncate')) {
            Str::macro('middleTruncate', fn (string $value, int $maxChars, string $kind = 'auto', int $minTail = 0): string => MiddleTruncate::cut($value, $maxChars, $kind, $minTail));
        }
    }

    /** `auto` reads a scheme (`https://`) or a leading `www.` as a URL, a value with a `/` or `\` as a path, and anything else as a file name. */
    public static function kind(string $value, string $kind = 'auto'): string
    {
        if (! in_array($kind, ['auto', 'file', 'path', 'url'], true)) {
            throw new InvalidArgumentException("MiddleTruncate kind [{$kind}] is not auto, file, path or url.");
        }
        if ($kind !== 'auto') {
            return $kind;
        }
        if (preg_match('~^[a-z][a-z\d+.-]*://~i', $value) || preg_match('/^www\./i', $value)) {
            return 'url';
        }

        return preg_match('~[/\\\\]~', $value) ? 'path' : 'file';
    }

    /** Where a file name's extension starts (its dot, a byte offset), or -1. `.tar.gz` is one extension; a leading dot (`.env`) is a name. */
    public static function extensionStart(string $name): int
    {
        if (! preg_match('/(?:\.tar)?\.[\p{L}\p{N}]{1,16}$/u', $name, $match, PREG_OFFSET_CAPTURE)) {
            return -1;
        }
        $index = $match[0][1];

        return $index > 0 && $name[$index - 1] !== '/' && $name[$index - 1] !== '\\' ? $index : -1;
    }

    /** @return list<string> the value's user-perceived characters: an emoji sequence, a flag or a letter with its accents is one. */
    public static function graphemes(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (function_exists('grapheme_str_split')) {
            $parts = grapheme_str_split($text);
            if ($parts !== false) {
                return $parts;
            }
        }
        preg_match_all('/\X/u', $text, $matches);

        return $matches[0];
    }

    private static function search(string $value, string $pattern): int
    {
        return preg_match($pattern, $value, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : -1;
    }

    private static function isSeparator(string $char): bool
    {
        return $char === '/' || $char === '\\';
    }

    /** The start of the last path segment's separator in `$value[$from, $to)`, ignoring trailing separators, or -1. */
    private static function lastSeparator(string $value, int $from, int $to): int
    {
        $end = $to;
        while ($end > $from + 1 && self::isSeparator($value[$end - 1])) {
            $end--;
        }
        for ($i = $end - 1; $i >= $from; $i--) {
            if (self::isSeparator($value[$i])) {
                return $i;
            }
        }

        return -1;
    }

    /** The byte offset `$units` UTF-16 code units past `$from` (the TypeScript counts code units), stopping at `$to`. */
    private static function units(string $value, int $from, int $units, int $to): int
    {
        $offset = $from;
        while ($units > 0 && $offset < $to && preg_match('/./su', $value, $match, 0, $offset)) {
            $units -= strlen($match[0]) === 4 ? 2 : 1;
            $offset += strlen($match[0]);
        }

        return min($offset, $to);
    }

    /** @return array{head: int, lead: int, tail: int, floor: int} byte offsets, as anchors() in the TypeScript. */
    private static function anchors(string $value, string $kind): array
    {
        $length = strlen($value);
        if ($kind === 'url') {
            $scheme = preg_match('~^[a-z][a-z\d+.-]*://~i', $value, $match) ? strlen($match[0]) : 0;
            $rest = substr($value, $scheme);
            $hostEnd = self::search($rest, '~[/?#]~');
            $pathEnd = self::search($rest, '/[?#]/');
            $host = $hostEnd === -1 ? $length : $scheme + $hostEnd;
            $query = $pathEnd === -1 ? $length : $scheme + $pathEnd;
            $separator = $host < $query ? self::lastSeparator($value, $host, $query) : -1;
            $tail = $separator > $host || ($separator === $host && $query - $host > 1) ? $separator : $query;
            $dot = $tail < $query ? self::extensionStart(substr($value, $tail + 1, $query - $tail - 1)) : -1;

            return ['head' => self::units($value, $scheme, 8, $host), 'lead' => 1, 'tail' => $tail, 'floor' => $dot === -1 ? $length : $tail + 1 + $dot];
        }
        if ($kind === 'path') {
            $separator = self::lastSeparator($value, 0, $length);
            if ($separator > 0) {
                // A drive's root (`C:\`, `C:/`) or a UNC share (`\\server\share\`) is one unit of the head, never cut.
                $root = max(1, preg_match('~^(?:[a-z]:[/\\\\]?|\\\\\\\\[^/\\\\]+\\\\[^/\\\\]+\\\\?)~i', $value, $match) ? strlen($match[0]) : 0);
                $dot = self::extensionStart(substr($value, $separator + 1));

                return [
                    'head' => $root,
                    'lead' => $root,
                    'tail' => $separator,
                    'floor' => self::isSeparator($value[$length - 1]) || $dot === -1 ? $length : $separator + 1 + $dot,
                ];
            }
        }
        $dot = self::extensionStart($value);

        return ['head' => 1, 'lead' => 1, 'tail' => $dot === -1 ? $length : $dot, 'floor' => $dot === -1 ? $length : $dot];
    }

    /**
     * @param  callable(string): int  $measure
     * @return array{head: string, middle: string, tail: string, truncated: bool}
     */
    private static function split(string $value, int $width, callable $measure, string $kind, int $minTail): array
    {
        $parts = self::graphemes($value);
        $count = count($parts);
        if ($count < 3 || $measure($value) <= $width) {
            return ['head' => $value, 'middle' => '', 'tail' => '', 'truncated' => false];
        }
        $starts = [];
        $offset = 0;
        foreach ($parts as $part) {
            $starts[] = $offset;
            $offset += strlen($part);
        }
        $before = fn (int $at): int => count(array_filter($starts, fn (int $start): bool => $start < $at));
        $from = fn (int $at): int => $count - $before($at);
        $anchor = self::anchors($value, $kind);
        $lead = max(1, min($before($anchor['lead']), $count - 2));
        $floor = min($count - 1 - $lead, max(1, $from($anchor['floor']), $minTail));
        $tail = min($count - 1 - $lead, max($floor, $from($anchor['tail'])));
        $head = max($lead, min($before($anchor['head']), $count - 1 - $tail));
        $text = fn (int $h, int $t): string => implode('', array_slice($parts, 0, $h)).self::ELLIPSIS.implode('', array_slice($parts, $count - $t));
        $fits = fn (int $h, int $t): bool => $measure($text($h, $t)) <= $width;
        $largest = function (int $steps, callable $split) use ($fits): array {
            $low = 0;
            $high = $steps;
            while ($low < $high) {
                $mid = intdiv($low + $high + 1, 2);
                if ($fits(...$split($mid))) {
                    $low = $mid;
                } else {
                    $high = $mid - 1;
                }
            }

            return $split($low);
        };
        if ($fits($head, $tail)) {
            $spare = $count - 1 - $head - $tail;
            [$h, $t] = $largest($spare, fn (int $step): array => [$head + (int) ceil($step * 2 / 3), $tail + $step - (int) ceil($step * 2 / 3)]);
        } else {
            // Too narrow for the anchors: give up the head beyond its lead and the tail beyond its floor together, in proportion.
            $steps = max($head - $lead, $tail - $floor);
            $per = $steps ?: 1;
            [$h, $t] = $largest($steps, fn (int $step): array => [
                $lead + (int) floor($step * ($head - $lead) / $per + 0.5),
                $floor + (int) floor($step * ($tail - $floor) / $per + 0.5),
            ]);
        }
        // Whatever room is left: to the tail while it is short of its anchor, else to the head first.
        for ($grew = true; $grew && $h + $t < $count - 1;) {
            $tailFirst = $t < $tail;
            if ($tailFirst && $fits($h, $t + 1)) {
                $t++;
            } elseif ($fits($h + 1, $t)) {
                $h++;
            } elseif (! $tailFirst && $fits($h, $t + 1)) {
                $t++;
            } else {
                $grew = false;
            }
        }

        return [
            'head' => implode('', array_slice($parts, 0, $h)),
            'middle' => implode('', array_slice($parts, $h, $count - $t - $h)),
            'tail' => implode('', array_slice($parts, $count - $t)),
            'truncated' => true,
        ];
    }
}
