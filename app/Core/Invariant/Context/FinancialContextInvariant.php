<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class FinancialContextInvariant
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.FinancialContextInvariant::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Context/FinancialContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function supports(string $table): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.FinancialContextInvariant::supports
         * Responsabilidade: Implementa a responsabilidade “supports” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/FinancialContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.ContextInvariantRegistry::assertWrite`, `Core.Invariant.Context.ContextInvariantRegistry::logicSelfTest`.
         * Dependências chamadas: `str_starts_with`, `in_array`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return str_starts_with($table, "pi_financial_") ||
            in_array(
                $table,
                ["pi_cash_sessions", "pi_cash_closing_reviews", "pi_subscription_payments"],
                true,
            );
    }

    public static function assertWrite(string $table): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.FinancialContextInvariant::assertWrite
         * Responsabilidade: Implementa a responsabilidade “assert write” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/FinancialContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.ContextInvariantRegistry::assertWrite`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return [
            "context" => "financial",
            "checked" => true,
            "entity_table" => $table,
        ];
    }
}
