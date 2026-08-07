<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

final class SqlLexicalScanner
{
    private function __construct()
    {
    }

    public static function topLevelKeywordPosition(string $sql, string $keyword, int $start = 0): ?int
    {

        return self::topLevelPhrasePosition($sql, $keyword, $start);
    }

    public static function topLevelPhrasePosition(string $sql, string $phrase, int $start = 0): ?int
    {

        $phrase = strtolower(mb_trim(preg_replace('/\s+/', ' ', $phrase) ?? $phrase));
        if ($phrase === "") {
            return null;
        }
        $length = strlen($sql);
        $phraseLength = strlen($phrase);
        $depth = 0;
        $quote = null;
        for ($i = max(0, $start); $i <= $length - $phraseLength; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $length && $sql[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $sql[$i - 1] !== "\\") {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "(") {
                $depth++;
                continue;
            }
            if ($ch === ")") {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            $candidate = strtolower(preg_replace('/\s+/', ' ', substr($sql, $i, $phraseLength)) ?? "");
            if ($candidate !== $phrase) {
                continue;
            }
            $before = $i > 0 ? $sql[$i - 1] : " ";
            $after = $i + $phraseLength < $length ? $sql[$i + $phraseLength] : " ";
            if (preg_match('/[a-z0-9_]/i', $before) || preg_match('/[a-z0-9_]/i', $after)) {
                continue;
            }
            return $i;
        }
        return null;
    }

    public static function splitTopLevelWithOffsets(string $expression, string $delimiter = ","): array
    {

        $parts = [];
        $start = 0;
        $length = strlen($expression);
        $depth = 0;
        $quote = null;
        for ($i = 0; $i < $length; $i++) {
            $ch = $expression[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $length && $expression[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $expression[$i - 1] !== "\\") {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "(") {
                $depth++;
                continue;
            }
            if ($ch === ")") {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth === 0 && $ch === $delimiter) {
                $parts[] = [substr($expression, $start, $i - $start), $start];
                $start = $i + 1;
            }
        }
        $parts[] = [substr($expression, $start), $start];
        return $parts;
    }

    public static function splitBooleanTopLevel(string $expression, string $operator): array
    {

        $operator = strtolower(trim($operator));
        $parts = [];
        $start = 0;
        $length = strlen($expression);
        $wordLength = strlen($operator);
        $depth = 0;
        $quote = null;
        for ($i = 0; $i <= $length - $wordLength; $i++) {
            $ch = $expression[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $length && $expression[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $expression[$i - 1] !== "\\") {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "(") {
                $depth++;
                continue;
            }
            if ($ch === ")") {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth !== 0 || strncasecmp(substr($expression, $i, $wordLength), $operator, $wordLength) !== 0) {
                continue;
            }
            $before = $i > 0 ? $expression[$i - 1] : " ";
            $after = $i + $wordLength < $length ? $expression[$i + $wordLength] : " ";
            if (preg_match('/[a-z0-9_]/i', $before) || preg_match('/[a-z0-9_]/i', $after)) {
                continue;
            }
            $parts[] = [substr($expression, $start, $i - $start), $start];
            $i += $wordLength - 1;
            $start = $i + 1;
        }
        if ($parts !== []) {
            $parts[] = [substr($expression, $start), $start];
            return $parts;
        }
        return [[$expression, 0]];
    }

    public static function trimExpression(string $expression, int $baseOffset): array
    {

        $left = strlen($expression) - strlen(ltrim($expression));
        return [trim($expression), $baseOffset + $left];
    }

    public static function outerParenthesesWrap(string $expression): bool
    {

        $expression = trim($expression);
        $length = strlen($expression);
        if ($length < 2 || $expression[0] !== "(" || $expression[$length - 1] !== ")") {
            return false;
        }
        $depth = 0;
        $quote = null;
        for ($i = 0; $i < $length; $i++) {
            $ch = $expression[$i];
            if ($quote !== null) {
                if ($ch === $quote && ($i === 0 || $expression[$i - 1] !== "\\")) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "(") {
                $depth++;
            } elseif ($ch === ")") {
                $depth--;
                if ($depth === 0 && $i < $length - 1) {
                    return false;
                }
            }
        }
        return $depth === 0;
    }

    public static function placeholderIndexBefore(string $sql, int $offset): int
    {

        $count = 0;
        $quote = null;
        $limit = min(strlen($sql), max(0, $offset));
        for ($i = 0; $i < $limit; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $limit && $sql[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $sql[$i - 1] !== "\\") {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "?") {
                $count++;
            }
        }
        return $count;
    }

    public static function tokenValue(
        string $sql,
        string $token,
        int $absoluteOffset,
        array $params,
        ?bool &$known = null,
    ): mixed {

        $token = trim($token);
        $known = true;
        if ($token === "?") {
            $index = self::placeholderIndexBefore($sql, $absoluteOffset);
            if (!array_key_exists($index, array_values($params))) {
                $known = false;
                return null;
            }
            return array_values($params)[$index];
        }
        if (preg_match('/^[-+]?\d+$/', $token)) {
            return (int) $token;
        }
        if (preg_match('/^[-+]?\d+\.\d+$/', $token)) {
            return (float) $token;
        }
        if (strcasecmp($token, "null") === 0) {
            return null;
        }
        if (preg_match('/^\'(.*)\'$/s', $token, $match)) {
            return str_replace("''", "'", (string) $match[1]);
        }
        if (preg_match('/^\"(.*)\"$/s', $token, $match)) {
            return stripcslashes((string) $match[1]);
        }
        $known = false;
        return null;
    }

    public static function valueTuples(string $sql, int $start, int $end): array
    {

        $rows = [];
        $depth = 0;
        $quote = null;
        $tupleStart = null;
        for ($i = $start; $i < $end; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $end && $sql[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $sql[$i - 1] !== "\\") {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "(") {
                if ($depth === 0) {
                    $tupleStart = $i + 1;
                }
                $depth++;
                continue;
            }
            if ($ch === ")") {
                $depth--;
                if ($depth < 0) {
                    return [];
                }
                if ($depth === 0 && $tupleStart !== null) {
                    $inner = substr($sql, $tupleStart, $i - $tupleStart);
                    $tokens = [];
                    foreach (self::splitTopLevelWithOffsets($inner) as [$token, $offset]) {
                        $left = strlen($token) - strlen(ltrim($token));
                        $tokens[] = [
                            "token" => trim($token),
                            "offset" => $tupleStart + $offset + $left,
                        ];
                    }
                    $rows[] = $tokens;
                    $tupleStart = null;
                }
            }
        }
        return $depth === 0 ? $rows : [];
    }

    public static function canonicalComparable(mixed $value): string
    {

        if ($value === null) {
            return "null";
        }
        if (is_bool($value)) {
            return $value ? "bool:1" : "bool:0";
        }
        if (is_int($value) || is_float($value)) {
            return "number:" . (string) $value;
        }
        return "string:" . (string) $value;
    }

    public static function identifier(string $raw): string
    {

        $raw = strtolower(trim($raw));
        $raw = trim($raw, "` \t\n\r\0\x0B");
        return preg_match('/^[a-z0-9_]+$/', $raw) ? $raw : "";
    }

    public static function isDirectValueToken(string $token): bool
    {

        $token = trim($token);
        return $token === "?" ||
            preg_match('/^[-+]?\d+(?:\.\d+)?$/', $token) === 1 ||
            strcasecmp($token, "null") === 0 ||
            preg_match('/^\'(?:[^\']|\'\')*\'$/s', $token) === 1 ||
            preg_match('/^"(?:[^"]|\\")*"$/s', $token) === 1;
    }
}
