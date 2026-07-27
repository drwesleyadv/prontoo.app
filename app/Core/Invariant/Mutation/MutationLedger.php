<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Mutation;

use Prontoo\Core\Invariant\Canonical;

final class MutationLedger
{
    private static ?string $chain = null;
    private static array $proofs = [];

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Mutation.MutationLedger::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Mutation/MutationLedger.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function record(array $evidence): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Mutation.MutationLedger::record
         * Responsabilidade: Valida e executa a mutação “record”, preservando as invariantes do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Mutation/MutationLedger.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::guard`.
         * Dependências chamadas: `Canonical::hash`, `function_exists`, `hash`, `count`.
         * Estado externo lido: `$_SESSION`, `$GLOBALS`.
         * Efeitos colaterais: lê ou altera a sessão.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (self::$chain === null) {
            self::$chain = Canonical::hash("mutation_genesis", [
                "route" => function_exists("route") ? \route() : "runtime",
                "clinic_id" => (int) ($_SESSION["clinic_id"] ?? 0),
                "user_id" => (int) ($_SESSION["uid"] ?? 0),
            ]);
        }
        $proof = Canonical::hash("mutation", $evidence);
        self::$chain = hash("sha256", self::$chain . "|" . $proof);
        self::$proofs[] = [
            "proof" => $proof,
            "table" => (string) ($evidence["table"] ?? ""),
            "operation" => (string) ($evidence["operation"] ?? ""),
        ];
        $GLOBALS["PRONTOO_INVARIANT_PROOF_HASH"] = self::$chain;
        $GLOBALS["PRONTOO_INVARIANT_PROOF_COUNT"] = count(self::$proofs);
        return $proof;
    }

    public static function snapshot(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Mutation.MutationLedger::snapshot
         * Responsabilidade: Implementa a responsabilidade “snapshot” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Mutation/MutationLedger.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.InvariantKernel::proofSnapshot`.
         * Dependências chamadas: `count`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return [
            "policy" => Canonical::POLICY_VERSION,
            "chain" => self::$chain,
            "count" => count(self::$proofs),
            "proofs" => self::$proofs,
        ];
    }

    public static function reset(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Mutation.MutationLedger::reset
         * Responsabilidade: Implementa a responsabilidade “reset” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Mutation/MutationLedger.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        self::$chain = null;
        self::$proofs = [];
        unset(
            $GLOBALS["PRONTOO_INVARIANT_PROOF_HASH"],
            $GLOBALS["PRONTOO_INVARIANT_PROOF_COUNT"],
        );
    }
}
