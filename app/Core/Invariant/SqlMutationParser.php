<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

final class SqlMutationParser
{
    private function __construct()
    {
    }

    public static function operation(string $sql): ?string
    {

        if (!preg_match('/^\s*(UPDATE|DELETE|INSERT|REPLACE)\b/i', $sql, $match)) {
            return null;
        }
        return strtoupper((string) $match[1]);
    }

    public static function targetTable(string $sql): string
    {

        $patterns = [
            '/^\s*(?:INSERT|REPLACE)\s+(?:(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE)\s+)*INTO\s+(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?/i',
            '/^\s*UPDATE\s+(?:(?:LOW_PRIORITY|IGNORE)\s+)*(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?/i',
            '/^\s*DELETE\s+(?:(?:LOW_PRIORITY|QUICK|IGNORE)\s+)*FROM\s+(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql, $match)) {
                return strtolower((string) $match[1]);
            }
        }
        return "";
    }

    public static function parseInsert(string $sql): ?array
    {

        if (!preg_match(
            '/^\s*(?:insert|replace)\s+(?:(?:low_priority|delayed|high_priority|ignore)\s+)*into\s+(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?\s*\(([^)]*)\)\s*values\b/is',
            $sql,
            $match,
            PREG_OFFSET_CAPTURE,
        )) {
            return null;
        }
        $columns = [];
        foreach (SqlExpression::splitTopLevelWithOffsets((string) $match[2][0]) as [$raw]) {
            $column = SqlExpression::identifier((string) $raw);
            if ($column === "") {
                return null;
            }
            $columns[] = $column;
        }
        $valuesStart = (int) $match[0][1] + strlen((string) $match[0][0]);
        $duplicatePosition = SqlExpression::topLevelPhrasePosition($sql, "on duplicate key update", $valuesStart);
        $valuesEnd = $duplicatePosition ?? strlen($sql);
        $rows = SqlLexicalScanner::valueTuples($sql, $valuesStart, $valuesEnd);
        if ($rows === []) {
            return null;
        }
        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                return null;
            }
        }
        return [
            "table" => strtolower((string) $match[1][0]),
            "columns" => $columns,
            "rows" => $rows,
            "duplicate_position" => $duplicatePosition,
        ];
    }

    public static function assignments(string $sql): array
    {

        $operation = self::operation($sql);
        $setPosition = $operation === "UPDATE"
            ? SqlExpression::topLevelKeywordPosition($sql, "set")
            : SqlExpression::topLevelPhrasePosition($sql, "on duplicate key update");
        if ($setPosition === null) {
            return [];
        }
        $setLength = $operation === "UPDATE" ? 3 : strlen("on duplicate key update");
        $start = $setPosition + $setLength;
        $wherePosition = $operation === "UPDATE"
            ? SqlExpression::topLevelKeywordPosition($sql, "where", $start)
            : null;
        $end = $wherePosition ?? strlen($sql);
        $segment = substr($sql, $start, $end - $start);
        $assignments = [];
        foreach (SqlExpression::splitTopLevelWithOffsets($segment) as [$raw, $offset]) {
            if (!preg_match('/^\s*(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?\s*=\s*(.+?)\s*$/is', $raw, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $column = strtolower((string) $match[1][0]);
            $expression = mb_trim((string) $match[2][0]);
            $expressionOffset = $start + $offset + (int) $match[2][1];
            $assignments[$column] = [
                "token" => $expression,
                "offset" => $expressionOffset,
                "known_direct" => SqlExpression::isDirectValueToken($expression),
            ];
        }
        return $assignments;
    }

    public static function whereExpression(string $sql): ?array
    {

        $where = SqlExpression::topLevelKeywordPosition($sql, "where");
        if ($where === null) {
            return null;
        }
        $start = $where + 5;
        return [substr($sql, $start), $start];
    }

    public static function insertColumnValues(
        string $sql,
        array $params,
        array $parsed,
        string $column,
        ?bool &$complete = null,
    ): array {

        $complete = true;
        $index = array_search(strtolower($column), array_map('strtolower', $parsed["columns"] ?? []), true);
        if ($index === false) {
            return [];
        }
        $values = [];
        foreach ($parsed["rows"] ?? [] as $row) {
            if (!isset($row[$index])) {
                $complete = false;
                continue;
            }
            $known = false;
            $values[] = SqlExpression::tokenValue(
                $sql,
                (string) $row[$index]["token"],
                (int) $row[$index]["offset"],
                $params,
                $known,
            );
            if (!$known) {
                $complete = false;
            }
        }
        return $values;
    }

}
