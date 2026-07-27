<?php
declare(strict_types=1);

namespace Prontoo\Runtime;

use Prontoo\Application\Authorization\AuthorizationService;
use Prontoo\Core\Architecture\ArchitectureVerifier;
use Prontoo\Core\Invariant\InvariantKernel;
use Prontoo\Infrastructure\Audit\PdoActionProofStore;
use Prontoo\Infrastructure\Authorization\RuntimeCapabilityProvider;
use Prontoo\Presentation\Http\ActionMiddleware;

final class LayeredKernel
{
    private static ?ActionMiddleware $actions = null;

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Runtime.LayeredKernel::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Runtime/LayeredKernel.php (composição geral do runtime).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function enforceAction(
        string $route,
        string $method,
        array $post,
        array $context,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Runtime.LayeredKernel::enforceAction
         * Responsabilidade: Implementa a responsabilidade “enforce action” dentro do módulo de composição geral do runtime.
         * Local arquitetural: app/Runtime/LayeredKernel.php (composição geral do runtime).
         * Chamadores detectados: `Core.Integrity.ActionProof::enforce`.
         * Dependências chamadas: `self::actionMiddleware`, `->enforce`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        self::actionMiddleware()->enforce($route, $method, $post, $context);
    }

    public static function actionMiddleware(): ActionMiddleware
    {
        /*
         * GUIA DE MANUTENÇÃO — Runtime.LayeredKernel::actionMiddleware
         * Responsabilidade: Implementa a responsabilidade “action middleware” dentro do módulo de composição geral do runtime.
         * Local arquitetural: app/Runtime/LayeredKernel.php (composição geral do runtime).
         * Chamadores detectados: `Runtime.LayeredKernel::enforceAction`.
         * Dependências chamadas: `ActionMiddleware`, `AuthorizationService`, `RuntimeCapabilityProvider`, `PdoActionProofStore`.
         * Classes ou serviços instanciados: `ActionMiddleware`, `AuthorizationService`, `RuntimeCapabilityProvider`, `PdoActionProofStore`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return self::$actions ??= new ActionMiddleware(
            new AuthorizationService(new RuntimeCapabilityProvider()),
            new PdoActionProofStore(),
        );
    }

    public static function logicSelfTest(?string $root = null): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Runtime.LayeredKernel::logicSelfTest
         * Responsabilidade: Executa verificações regressivas embutidas para confirmar que os contratos lógicos deste componente permanecem válidos.
         * Local arquitetural: app/Runtime/LayeredKernel.php (composição geral do runtime).
         * Chamadores detectados: `Core.Integrity.ActionProof::logicSelfTest`.
         * Dependências chamadas: `AuthorizationService::logicSelfTest`, `RuntimeCapabilityProvider::logicSelfTest`, `ActionMiddleware::logicSelfTest`, `InvariantKernel::logicSelfTest`, `ArchitectureVerifier::report`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $authorization = AuthorizationService::logicSelfTest();
        $credentials = RuntimeCapabilityProvider::logicSelfTest();
        $middleware = ActionMiddleware::logicSelfTest();
        $mutations = InvariantKernel::logicSelfTest();
        $architecture = $root !== null && $root !== ''
            ? ArchitectureVerifier::report($root, false)
            : ['ok' => true, 'skipped' => true];
        return [
            'ok' => !empty($authorization['ok']) &&
                !empty($credentials['ok']) &&
                !empty($middleware['ok']) &&
                !empty($mutations['ok']) &&
                !empty($architecture['ok']),
            'authorization' => $authorization,
            'credentials' => $credentials,
            'middleware' => $middleware,
            'mutations' => $mutations,
            'architecture' => $architecture,
        ];
    }
}
