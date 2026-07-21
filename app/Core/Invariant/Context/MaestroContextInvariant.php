<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class MaestroContextInvariant
{
    private const TABLES = ["pi_maestro_rules", "pi_maestro_executions"];

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.MaestroContextInvariant::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Context/MaestroContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function supports(string $table): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.MaestroContextInvariant::supports
         * Responsabilidade: Implementa a responsabilidade “supports” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/MaestroContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.ContextInvariantRegistry::assertWrite`, `Core.Invariant.Context.ContextInvariantRegistry::logicSelfTest`.
         * Dependências chamadas: `in_array`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return in_array($table, self::TABLES, true);
    }

    public static function assertWrite(int $clinicId): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.MaestroContextInvariant::assertWrite
         * Responsabilidade: Implementa a responsabilidade “assert write” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/MaestroContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.ContextInvariantRegistry::assertWrite`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Classes ou serviços instanciados: `.ProntooHttpError`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if ($clinicId <= 0) {
            throw new \ProntooHttpError(
                500,
                "A rotina agendada não possui contexto de consultório demonstrável.",
            );
        }
        return [
            "context" => "maestro",
            "checked" => true,
            "clinic_id" => $clinicId,
        ];
    }
}
