<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class ContextInvariantRegistry
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.ContextInvariantRegistry::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Context/ContextInvariantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function assertWrite(
        string $table,
        string $operation,
        string $sql,
        array $params,
        ?array $insert,
        int $clinicId,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.ContextInvariantRegistry::assertWrite
         * Responsabilidade: Implementa a responsabilidade “assert write” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/ContextInvariantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::guard`.
         * Dependências chamadas: `AppointmentContextInvariant::supports`, `AppointmentContextInvariant::assertWrite`, `PeopleContextInvariant::supports`, `PeopleContextInvariant::assertWrite`, `TaskContextInvariant::supports`, `TaskContextInvariant::assertWrite`, `DocumentContextInvariant::supports`, `DocumentContextInvariant::assertWrite`, `FinancialContextInvariant::supports`, `FinancialContextInvariant::assertWrite`, `MaestroContextInvariant::supports`, `MaestroContextInvariant::assertWrite` e mais 2.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (AppointmentContextInvariant::supports($table)) {
            return AppointmentContextInvariant::assertWrite(
                $table,
                $operation,
                $sql,
                $params,
                $insert,
                $clinicId,
            );
        }
        if (PeopleContextInvariant::supports($table)) {
            return PeopleContextInvariant::assertWrite($table);
        }
        if (TaskContextInvariant::supports($table)) {
            return TaskContextInvariant::assertWrite(
                $table,
                $operation,
                $sql,
                $params,
                $insert,
            );
        }
        if (DocumentContextInvariant::supports($table)) {
            return DocumentContextInvariant::assertWrite($table);
        }
        if (FinancialContextInvariant::supports($table)) {
            return FinancialContextInvariant::assertWrite($table);
        }
        if (MaestroContextInvariant::supports($table)) {
            return MaestroContextInvariant::assertWrite($clinicId);
        }
        if (AccessContextInvariant::supports($table)) {
            return AccessContextInvariant::assertWrite($table);
        }
        return [
            "context" => "generic",
            "checked" => true,
            "entity_table" => $table,
        ];
    }

    public static function logicSelfTest(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.ContextInvariantRegistry::logicSelfTest
         * Responsabilidade: Executa verificações regressivas embutidas para confirmar que os contratos lógicos deste componente permanecem válidos.
         * Local arquitetural: app/Core/Invariant/Context/ContextInvariantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::logicSelfTest`.
         * Dependências chamadas: `AppointmentContextInvariant::supports`, `PeopleContextInvariant::supports`, `TaskContextInvariant::supports`, `DocumentContextInvariant::supports`, `FinancialContextInvariant::supports`, `MaestroContextInvariant::supports`, `AccessContextInvariant::supports`, `array_keys`, `array_filter`, `count`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
         */
        $cases = [
            "appointments" => AppointmentContextInvariant::supports("pi_appointments"),
            "people" => PeopleContextInvariant::supports("pi_patients"),
            "tasks" => TaskContextInvariant::supports("pi_tasks"),
            "documents" => DocumentContextInvariant::supports("pi_documents"),
            "financial" => FinancialContextInvariant::supports("pi_financial_revenues"),
            "maestro" => MaestroContextInvariant::supports("pi_maestro_rules"),
            "access" => AccessContextInvariant::supports("pi_permissions"),
        ];
        $failed = array_keys(array_filter($cases, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de núcleo de invariantes e decisões canônicas. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn(bool $ok): bool => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
        ];
    }
}
