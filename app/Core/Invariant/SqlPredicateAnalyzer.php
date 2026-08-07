<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

final class SqlPredicateAnalyzer
{
    private function __construct()
    {
    }

    public static function whereEqualityValues(
        string $sql,
        string $column,
        array $params,
        ?bool &$complete = null,
    ): array {

        $complete = true;
        $where = SqlExpression::whereExpression($sql);
        if ($where === null) {
            $complete = false;
            return [];
        }
        [$expression, $baseOffset] = $where;
        $columnPattern = '(?<![a-z0-9_])(?:`?[a-z0-9_]+`?\s*\.\s*)?`?' . preg_quote(strtolower($column), '/') . '`?(?![a-z0-9_])';
        $values = [];
        if (preg_match_all('/' . $columnPattern . '\s*=\s*(\?|[-+]?\d+|\'[^\']*\'|"[^"]*")/i', $expression, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $token = (string) $match[1][0];
                $known = false;
                $value = SqlExpression::tokenValue(
                    $sql,
                    $token,
                    $baseOffset + (int) $match[1][1],
                    $params,
                    $known,
                );
                if (!$known) {
                    $complete = false;
                    continue;
                }
                $values[] = $value;
            }
        }
        if (preg_match_all('/' . $columnPattern . '\s+in\s*\(([^)]*)\)/i', $expression, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $list = (string) $match[1][0];
                $listOffset = $baseOffset + (int) $match[1][1];
                foreach (SqlExpression::splitTopLevelWithOffsets($list) as [$token, $offset]) {
                    $left = strlen($token) - strlen(ltrim($token));
                    $known = false;
                    $value = SqlExpression::tokenValue(
                        $sql,
                        trim($token),
                        $listOffset + $offset + $left,
                        $params,
                        $known,
                    );
                    if (!$known) {
                        $complete = false;
                        continue;
                    }
                    $values[] = $value;
                }
            }
        }
        return array_values(array_unique($values, SORT_REGULAR));
    }

    public static function whereAllowedValues(
        string $sql,
        string $column,
        array $params,
        ?bool &$complete = null,
    ): array {

        $complete = true;
        $where = SqlExpression::whereExpression($sql);
        if ($where === null) {
            $complete = false;
            return [];
        }
        [$expression, $baseOffset] = $where;
        $constraint = self::columnValueConstraint(
            $sql,
            $expression,
            $baseOffset,
            strtolower($column),
            array_values($params),
        );
        if ($constraint === null) {
            $complete = false;
            return [];
        }
        return array_values(array_unique($constraint, SORT_REGULAR));
    }

    public static function columnValueConstraint(
        string $sql,
        string $expression,
        int $baseOffset,
        string $column,
        array $params,
    ): ?array {

        [$expression, $baseOffset] = SqlExpression::trimExpression($expression, $baseOffset);
        while (SqlExpression::outerParenthesesWrap($expression)) {
            $expression = substr($expression, 1, -1);
            $baseOffset++;
            [$expression, $baseOffset] = SqlExpression::trimExpression($expression, $baseOffset);
        }

        $orParts = SqlExpression::splitBooleanTopLevel($expression, "or");
        if (count($orParts) > 1) {
            $values = [];
            foreach ($orParts as [$part, $offset]) {
                $branch = self::columnValueConstraint(
                    $sql,
                    $part,
                    $baseOffset + $offset,
                    $column,
                    $params,
                );
                if ($branch === null) {
                    return null;
                }
                $values = array_merge($values, $branch);
            }
            return array_values(array_unique($values, SORT_REGULAR));
        }

        $andParts = SqlExpression::splitBooleanTopLevel($expression, "and");
        if (count($andParts) > 1) {
            $constraints = [];
            foreach ($andParts as [$part, $offset]) {
                $branch = self::columnValueConstraint(
                    $sql,
                    $part,
                    $baseOffset + $offset,
                    $column,
                    $params,
                );
                if ($branch !== null) {
                    $constraints[] = $branch;
                }
            }
            if ($constraints === []) {
                return null;
            }
            $values = array_shift($constraints);
            foreach ($constraints as $constraint) {
                $values = array_values(array_uintersect(
                    $values,
                    $constraint,
                    static  fn(mixed $a, mixed $b): int => strcmp(
                        SqlLexicalScanner::canonicalComparable($a),
                        SqlLexicalScanner::canonicalComparable($b),
                    ),
                ));
            }
            return $values;
        }

        return self::atomicColumnConstraint(
            $sql,
            $expression,
            $baseOffset,
            $column,
            $params,
        );
    }

    public static function atomicColumnConstraint(
        string $sql,
        string $expression,
        int $baseOffset,
        string $column,
        array $params,
    ): ?array {

        $columnPattern = '(?:`?[a-z0-9_]+`?\\s*\\.\\s*)?`?' . preg_quote($column, '/') . '`?';
        $valuePattern = '(\\?|[-+]?\\d+|\\\'[^\\\']*\\\'|"[^"]*")';
        $patterns = [
            '/^\\s*' . $columnPattern . '\\s*=\\s*' . $valuePattern . '\\s*$/i',
            '/^\\s*' . $valuePattern . '\\s*=\\s*' . $columnPattern . '\\s*$/i',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $expression, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $token = (string) ($match[1][0] ?? "");
            $known = false;
            $value = SqlExpression::tokenValue(
                $sql,
                $token,
                $baseOffset + (int) ($match[1][1] ?? 0),
                $params,
                $known,
            );
            return $known ? [$value] : null;
        }

        if (preg_match(
            '/^\\s*' . $columnPattern . '\\s+in\\s*\\((.*)\\)\\s*$/is',
            $expression,
            $match,
            PREG_OFFSET_CAPTURE,
        )) {
            $list = (string) ($match[1][0] ?? "");
            $listOffset = $baseOffset + (int) ($match[1][1] ?? 0);
            $values = [];
            foreach (SqlExpression::splitTopLevelWithOffsets($list) as [$token, $offset]) {
                $left = strlen($token) - strlen(ltrim($token));
                $known = false;
                $value = SqlExpression::tokenValue(
                    $sql,
                    trim($token),
                    $listOffset + $offset + $left,
                    $params,
                    $known,
                );
                if (!$known) {
                    return null;
                }
                $values[] = $value;
            }
            return $values;
        }

        return null;
    }

}
