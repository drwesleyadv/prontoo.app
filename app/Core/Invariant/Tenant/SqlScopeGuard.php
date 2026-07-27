<?php
declare(strict_types=1);
namespace Prontoo\Core\Database;

use Prontoo\Core\Invariant\InvariantKernel;

final class SqlScopeGuard
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Tenant/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function guard(string $sql, array $params = []): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::guard
         * Responsabilidade: Avalia ou impõe a regra “guard”, falhando de forma controlada quando a pré-condição não é satisfeita.
         * Local arquitetural: app/Core/Invariant/Tenant/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `sql_write_scope_guard`.
         * Dependências chamadas: `InvariantKernel::guardMutation`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        InvariantKernel::guardMutation($sql, $params);
    }

    public static function logicSelfTest(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::logicSelfTest
         * Responsabilidade: Executa verificações regressivas embutidas para confirmar que os contratos lógicos deste componente permanecem válidos.
         * Local arquitetural: app/Core/Invariant/Tenant/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `platform_backend_selftest`, `page_admin_security`.
         * Dependências chamadas: `InvariantKernel::logicSelfTest`, `InvariantKernel::policyVersion`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $kernel = InvariantKernel::logicSelfTest();
        $scope = (array) ($kernel["mutations"]["scope"] ?? []);
        return $scope + [
            "policy" => $kernel["policy"] ?? InvariantKernel::policyVersion(),
            "kernel" => $kernel,
        ];
    }
}
