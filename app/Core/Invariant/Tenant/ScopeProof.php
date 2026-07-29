<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Tenant;

use Prontoo\Core\Invariant\SqlExpression;

final class ScopeProof
{
    public const ACTIVE = "active";
    public const MISMATCH = "mismatch";
    public const UNPROVED = "unproved";

    private function __construct() {
        








    }

    public static function whereStatus(
        string $sql,
        string $scopeColumn,
        int $clinicId,
        array $params,
    ): string {
        








        $where = SqlExpression::whereExpression($sql);
        if ($where === null) {
            return self::UNPROVED;
        }
        return self::booleanStatus(
            $sql,
            (string) $where[0],
            (int) $where[1],
            strtolower($scopeColumn),
            $clinicId,
            array_values($params),
        );
    }

    public static function insertStatus(
        string $sql,
        array $parsed,
        string $scopeColumn,
        int $clinicId,
        array $params,
    ): string {
        








        $complete = false;
        $values = SqlExpression::insertColumnValues(
            $sql,
            $params,
            $parsed,
            $scopeColumn,
            $complete,
        );
        if ($values === []) {
            return self::UNPROVED;
        }
        $mismatch = false;
        foreach ($values as $value) {
            if ((int) $value !== $clinicId) {
                $mismatch = true;
            }
        }
        if ($mismatch) {
            return self::MISMATCH;
        }
        if (!$complete) {
            return self::UNPROVED;
        }
        return self::ACTIVE;
    }

    public static function insertColumnPresent(string $sql, string $scopeColumn): bool
    {
        








        return preg_match(
            '/^\s*(?:insert|replace)\s+(?:(?:low_priority|delayed|high_priority|ignore)\s+)*into\s+(?:`?[a-z0-9_]+`?\s*\.\s*)?`?[a-z0-9_]+`?\s*\(([^)]*)\)/is',
            $sql,
            $match,
        ) === 1 &&
            in_array(
                strtolower($scopeColumn),
                array_map(
                    static  fn(string $column): string => SqlExpression::identifier($column),
                    explode(",", (string) $match[1]),
                ),
                true,
            );
    }

    public static function updateChangesScope(string $sql, string $scopeColumn): bool
    {
        








        return array_key_exists(strtolower($scopeColumn), SqlExpression::assignments($sql));
    }

    public static function duplicatePreservesScope(string $sql, string $scopeColumn): bool
    {
        








        $assignments = SqlExpression::assignments($sql);
        $key = strtolower($scopeColumn);
        if (!isset($assignments[$key])) {
            return true;
        }
        $expression = strtolower(
            preg_replace('/\s+|`/', '', (string) $assignments[$key]["token"]) ?? "",
        );
        return in_array(
            $expression,
            ["values(" . $key . ")", $key],
            true,
        );
    }

    private static function booleanStatus(
        string $sql,
        string $expression,
        int $baseOffset,
        string $scopeColumn,
        int $clinicId,
        array $params,
    ): string {
        








        [$expression, $baseOffset] = SqlExpression::trimExpression(
            $expression,
            $baseOffset,
        );
        while (SqlExpression::outerParenthesesWrap($expression)) {
            $expression = substr($expression, 1, -1);
            $baseOffset++;
            [$expression, $baseOffset] = SqlExpression::trimExpression(
                $expression,
                $baseOffset,
            );
        }

        $orParts = SqlExpression::splitBooleanTopLevel($expression, "or");
        if (count($orParts) > 1) {
            $statuses = [];
            foreach ($orParts as [$part, $offset]) {
                $statuses[] = self::booleanStatus(
                    $sql,
                    $part,
                    $baseOffset + $offset,
                    $scopeColumn,
                    $clinicId,
                    $params,
                );
            }
            if (count(array_filter($statuses, static  fn(string $status): bool => $status === self::ACTIVE)) === count($statuses)) {
                return self::ACTIVE;
            }
            if (count(array_filter($statuses, static  fn(string $status): bool => $status === self::MISMATCH)) === count($statuses)) {
                return self::MISMATCH;
            }
            return self::UNPROVED;
        }

        $andParts = SqlExpression::splitBooleanTopLevel($expression, "and");
        if (count($andParts) > 1) {
            $hasMismatch = false;
            foreach ($andParts as [$part, $offset]) {
                $status = self::booleanStatus(
                    $sql,
                    $part,
                    $baseOffset + $offset,
                    $scopeColumn,
                    $clinicId,
                    $params,
                );
                if ($status === self::ACTIVE) {
                    return self::ACTIVE;
                }
                $hasMismatch = $hasMismatch || $status === self::MISMATCH;
            }
            return $hasMismatch ? self::MISMATCH : self::UNPROVED;
        }

        return self::atomicStatus(
            $sql,
            $expression,
            $baseOffset,
            $scopeColumn,
            $clinicId,
            $params,
        );
    }

    private static function atomicStatus(
        string $sql,
        string $expression,
        int $baseOffset,
        string $scopeColumn,
        int $clinicId,
        array $params,
    ): string {
        








        $column = preg_quote($scopeColumn, "/");
        $columnExpression = "(?<![a-z0-9_])(?:`?[a-z0-9_]+`?\\s*\\.\\s*)?`?" . $column . "`?(?![a-z0-9_])";
        $patterns = [
            "/" . $columnExpression . "\\s*=\\s*(\\?|[-+]?\\d+\\b|'[^']*'|\"[^\"]*\")/i",
            "/(\\?|[-+]?\\d+\\b|'[^']*'|\"[^\"]*\")\\s*=\\s*" . $columnExpression . "/i",
        ];
        $mismatch = false;
        foreach ($patterns as $pattern) {
            if (!preg_match_all(
                $pattern,
                $expression,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
            )) {
                continue;
            }
            foreach ($matches as $match) {
                $known = false;
                $value = SqlExpression::tokenValue(
                    $sql,
                    (string) $match[1][0],
                    $baseOffset + (int) $match[1][1],
                    $params,
                    $known,
                );
                if (!$known) {
                    continue;
                }
                if ((int) $value === $clinicId) {
                    return self::ACTIVE;
                }
                $mismatch = true;
            }
        }
        return $mismatch ? self::MISMATCH : self::UNPROVED;
    }

    public static function logicSelfTest(): array
    {
        








        $clinicId = 17;
        $cases = [];
        $cases["nested_or_inside_scoped_and"] = self::whereStatus(
            "UPDATE pi_tasks SET status=? WHERE clinic_id=? AND (assigned_to IS NULL OR assigned_to=?)",
            "clinic_id",
            $clinicId,
            ["ok", 17, 4],
        ) === self::ACTIVE;
        $cases["unscoped_or_branch_denied"] = self::whereStatus(
            "UPDATE pi_tasks SET status=? WHERE clinic_id=? OR id=?",
            "clinic_id",
            $clinicId,
            ["ok", 17, 4],
        ) === self::UNPROVED;
        $cases["all_or_branches_scoped"] = self::whereStatus(
            "DELETE FROM pi_tasks WHERE (clinic_id=? AND id=?) OR (clinic_id=? AND id=?)",
            "clinic_id",
            $clinicId,
            [17, 1, 17, 2],
        ) === self::ACTIVE;
        $parsed = SqlExpression::parseInsert(
            "INSERT INTO pi_tasks (clinic_id,title) VALUES (?,?),(?,?)",
        );
        $cases["all_insert_rows_scoped"] = is_array($parsed) && self::insertStatus(
            "INSERT INTO pi_tasks (clinic_id,title) VALUES (?,?),(?,?)",
            $parsed,
            "clinic_id",
            $clinicId,
            [17, "a", 17, "b"],
        ) === self::ACTIVE;
        $cases["mismatched_insert_denied"] = is_array($parsed) && self::insertStatus(
            "INSERT INTO pi_tasks (clinic_id,title) VALUES (?,?),(?,?)",
            $parsed,
            "clinic_id",
            $clinicId,
            [17, "a", 99, "b"],
        ) === self::MISMATCH;
        $cases["scope_update_detected"] = self::updateChangesScope(
            "UPDATE pi_tasks SET clinic_id=? WHERE id=? AND clinic_id=?",
            "clinic_id",
        );
        $failed = array_keys(array_filter($cases, static  fn(bool $ok): bool => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
        ];
    }
}
