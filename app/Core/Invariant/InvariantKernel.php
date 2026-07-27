<?php
declare(strict_types=1);

namespace Prontoo\Core\Invariant;

use Prontoo\Core\Invariant\Mutation\MutationInvariant;
use Prontoo\Core\Invariant\Mutation\MutationLedger;

final class InvariantKernel
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.InvariantKernel::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/InvariantKernel.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function policyVersion(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.InvariantKernel::policyVersion
         * Responsabilidade: Implementa a responsabilidade “policy version” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/InvariantKernel.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.InvariantKernel::logicSelfTest`, `Core.Database.SqlScopeGuard::logicSelfTest`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return Canonical::POLICY_VERSION;
    }

    public static function guardMutation(string $sql, array $params = []): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.InvariantKernel::guardMutation
         * Responsabilidade: Implementa a responsabilidade “guard mutation” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/InvariantKernel.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`.
         * Dependências chamadas: `MutationInvariant::guard`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        MutationInvariant::guard($sql, $params);
    }

    public static function proofSnapshot(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.InvariantKernel::proofSnapshot
         * Responsabilidade: Implementa a responsabilidade “proof snapshot” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/InvariantKernel.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `MutationLedger::snapshot`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return MutationLedger::snapshot();
    }

    public static function logicSelfTest(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.InvariantKernel::logicSelfTest
         * Responsabilidade: Executa verificações regressivas embutidas para confirmar que os contratos lógicos deste componente permanecem válidos.
         * Local arquitetural: app/Core/Invariant/InvariantKernel.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::logicSelfTest`, `Runtime.LayeredKernel::logicSelfTest`.
         * Dependências chamadas: `MutationInvariant::logicSelfTest`, `self::policyVersion`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $mutations = MutationInvariant::logicSelfTest();
        return [
            'ok' => !empty($mutations['ok']),
            'policy' => self::policyVersion(),
            'mutations' => $mutations,
        ];
    }
}
