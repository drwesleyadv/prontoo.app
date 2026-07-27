<?php
declare(strict_types=1);

namespace Prontoo\Core\Integrity;

use Prontoo\Runtime\LayeredKernel;

/**
 * Compatibility boundary for the pre-layered HTTP adapter.
 * Authorization and proof persistence live outside Core.
 */
final class ActionProof
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.ActionProof::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Request/ActionProof.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function enforce(
        string $route,
        string $method,
        array $post,
        array $context,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.ActionProof::enforce
         * Responsabilidade: Implementa a responsabilidade “enforce” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Request/ActionProof.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `enforce_action_integrity`.
         * Dependências chamadas: `LayeredKernel::enforceAction`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        LayeredKernel::enforceAction($route, $method, $post, $context);
    }

    public static function logicSelfTest(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.ActionProof::logicSelfTest
         * Responsabilidade: Executa verificações regressivas embutidas para confirmar que os contratos lógicos deste componente permanecem válidos.
         * Local arquitetural: app/Core/Invariant/Request/ActionProof.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `defined`, `dirname`, `LayeredKernel::logicSelfTest`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $root = defined('PRONTOO_ROOT') ? PRONTOO_ROOT : dirname(__DIR__, 4);
        return LayeredKernel::logicSelfTest($root);
    }
}
