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

    private function __construct() {}

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
        $mutations = InvariantKernel::logicSelfTest();
        $architecture = $root !== null && $root !== ''
            ? ArchitectureVerifier::report($root, false)
            : ['ok' => true, 'skipped' => true];
        return [
            'ok' => !empty($authorization['ok']) &&
                !empty($mutations['ok']) &&
                !empty($architecture['ok']),
            'authorization' => $authorization,
            'mutations' => $mutations,
            'architecture' => $architecture,
        ];
    }
}
