<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

use Prontoo\Core\Invariant\Workflow\AppointmentWorkflow;

final class AppointmentContextInvariant
{
    private const TABLES = [
        "pi_appointments",
        "pi_blocks",
        "pi_agenda_notes",
        "pi_user_work_hours",
        "pi_procedures",
    ];

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.AppointmentContextInvariant::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Context/AppointmentContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function supports(string $table): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.AppointmentContextInvariant::supports
         * Responsabilidade: Implementa a responsabilidade “supports” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/AppointmentContextInvariant.php (núcleo de invariantes e decisões canônicas).
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
        int $clinicId,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.AppointmentContextInvariant::assertWrite
         * Responsabilidade: Implementa a responsabilidade “assert write” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/AppointmentContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.ContextInvariantRegistry::assertWrite`.
         * Dependências chamadas: `AppointmentWorkflow::assertWrite`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $workflow = AppointmentWorkflow::assertWrite(
            $table,
            $operation,
            $sql,
            $params,
            $insert,
            $clinicId,
        );
        return [
            "context" => "appointments",
            "checked" => true,
            "workflow" => $workflow,
        ];
    }
}
