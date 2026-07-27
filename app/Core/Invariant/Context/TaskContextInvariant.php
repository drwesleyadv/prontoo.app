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

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.TaskContextInvariant::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Context/TaskContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function supports(string $table): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.TaskContextInvariant::supports
         * Responsabilidade: Implementa a responsabilidade “supports” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/TaskContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.ContextInvariantRegistry::assertWrite`, `Core.Invariant.Context.ContextInvariantRegistry::logicSelfTest`.
         * Dependências chamadas: `in_array`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return in_array($table, self::TABLES, true);
    }

    public static function assertWrite(
        string $table,
        string $operation,
        string $sql,
        array $params,
        ?array $insert,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.TaskContextInvariant::assertWrite
         * Responsabilidade: Implementa a responsabilidade “assert write” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/TaskContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.ContextInvariantRegistry::assertWrite`.
         * Dependências chamadas: `self::assertEnumColumn`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
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
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.TaskContextInvariant::assertEnumColumn
         * Responsabilidade: Implementa a responsabilidade “assert enum column” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/TaskContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.TaskContextInvariant::assertWrite`.
         * Dependências chamadas: `in_array`, `is_array`, `SqlExpression::insertColumnValues`, `SqlExpression::assignments`, `self::deny`, `SqlExpression::tokenValue`, `array_values`, `array_unique`, `array_map`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
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
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.TaskContextInvariant::deny
         * Responsabilidade: Implementa a responsabilidade “deny” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/TaskContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.TaskContextInvariant::assertEnumColumn`.
         * Dependências chamadas: `function_exists`.
         * Classes ou serviços instanciados: `.ProntooHttpError`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
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
