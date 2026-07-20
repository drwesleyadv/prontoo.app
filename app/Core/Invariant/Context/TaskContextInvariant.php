<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

use Prontoo\Core\Invariant\SqlExpression;

final class TaskContextInvariant
{
    private const TABLES = [
        "pi_tasks",
        "pi_task_details",
        "pi_task_events",
        "pi_task_comments",
        "pi_notices",
    ];

    private function __construct() {}

    public static function supports(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }

    public static function assertWrite(
        string $table,
        string $operation,
        string $sql,
        array $params,
        ?array $insert,
    ): array {
        $checks = [];
        if ($table === "pi_tasks") {
            $checks["target_scope"] = self::assertEnumColumn(
                $operation,
                $sql,
                $params,
                $insert,
                "target_scope",
                ["clinic", "role", "user", "all"],
            );
        } elseif ($table === "pi_notices") {
            $checks["target_scope"] = self::assertEnumColumn(
                $operation,
                $sql,
                $params,
                $insert,
                "target_scope",
                ["all", "role", "user"],
            );
        }
        return [
            "context" => "tasks_notices",
            "checked" => true,
            "checks" => $checks,
        ];
    }

    private static function assertEnumColumn(
        string $operation,
        string $sql,
        array $params,
        ?array $insert,
        string $column,
        array $allowed,
    ): array {
        $values = [];
        $complete = true;
        if (in_array($operation, ["INSERT", "REPLACE"], true)) {
            if (!is_array($insert) || !in_array($column, $insert["columns"] ?? [], true)) {
                return ["applied" => false];
            }
            $values = SqlExpression::insertColumnValues(
                $sql,
                $params,
                $insert,
                $column,
                $complete,
            );
        } elseif ($operation === "UPDATE") {
            $assignments = SqlExpression::assignments($sql);
            if (!isset($assignments[$column])) {
                return ["applied" => false];
            }
            $assignment = $assignments[$column];
            if (empty($assignment["known_direct"])) {
                self::deny($column . "_expression_unproved", $sql);
            }
            $known = false;
            $values = [SqlExpression::tokenValue(
                $sql,
                (string) $assignment["token"],
                (int) $assignment["offset"],
                $params,
                $known,
            )];
            $complete = $known;
        } else {
            return ["applied" => false];
        }
        if (!$complete) {
            self::deny($column . "_value_unproved", $sql);
        }
        foreach ($values as $value) {
            if ($value === null || $value === "") {
                continue;
            }
            if (!in_array((string) $value, $allowed, true)) {
                self::deny($column . "_value_invalid", $sql);
            }
        }
        return [
            "applied" => true,
            "values" => array_values(array_unique(array_map("strval", $values))),
        ];
    }

    private static function deny(string $key, string $sql): never
    {
        if (function_exists("record_scope_violation")) {
            \record_scope_violation(
                "task_context_" . $key,
                $sql,
                "Valor estrutural de Tarefas/Avisos recusado pelo contrato especializado.",
            );
        }
        throw new \ProntooHttpError(
            409,
            "A configuração de destino da tarefa ou do aviso não é válida para este contexto.",
        );
    }
}
