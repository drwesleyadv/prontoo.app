<?php
declare(strict_types=1);

namespace Prontoo\Runtime;

use Prontoo\Application\Authorization\AuthorizationService;
use Prontoo\Core\Architecture\ArchitectureVerifier;
use Prontoo\Core\Invariant\InvariantKernel;
use Prontoo\Infrastructure\Audit\PdoActionProofStore;
use Prontoo\Infrastructure\Authorization\RuntimeCapabilityProvider;
use Prontoo\Presentation\Http\ActionMiddleware;
use Prontoo\Runtime\Modules\RuntimeModuleCatalog;
use Prontoo\Runtime\Routing\RouteCatalog;

final class LayeredKernel
{
    private static ?ActionMiddleware $actions = null;

    private function __construct() {

    }

    public static function enforceAction(
        string $route,
        string $method,
        array $post,
        array $context,
    ): void {

        self::actionMiddleware()->enforce($route, $method, $post, $context);
    }

    public static function actionMiddleware(): ActionMiddleware
    {

        return self::$actions ??= new ActionMiddleware(
            new AuthorizationService(new RuntimeCapabilityProvider()),
            new PdoActionProofStore(),
        );
    }

    public static function logicSelfTest(?string $root = null): array
    {

        $authorization = AuthorizationService::logicSelfTest();
        $credentials = RuntimeCapabilityProvider::logicSelfTest();
        $middleware = ActionMiddleware::logicSelfTest();
        $mutations = InvariantKernel::logicSelfTest();
        $routes = class_exists(RouteCatalog::class) ? RouteCatalog::all() : [];
        $moduleCatalog = RuntimeModuleCatalog::logicSelfTest($routes);
        $architecture = $root !== null && $root !== ''
            ? ArchitectureVerifier::report(
                $root,
                false,
                RuntimeModuleCatalog::fullModules(),
                $routes,
            )
            : ['ok' => true, 'skipped' => true];
        return [
            'ok' => !empty($authorization['ok']) &&
                !empty($credentials['ok']) &&
                !empty($middleware['ok']) &&
                !empty($mutations['ok']) &&
                !empty($moduleCatalog['ok']) &&
                !empty($architecture['ok']),
            'authorization' => $authorization,
            'credentials' => $credentials,
            'middleware' => $middleware,
            'mutations' => $mutations,
            'module_catalog' => $moduleCatalog,
            'architecture' => $architecture,
        ];
    }
}
