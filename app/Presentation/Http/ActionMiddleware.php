<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Http;

use Prontoo\Application\Audit\ActionProofPort;
use Prontoo\Application\Authorization\AuthorizationService;

final readonly class ActionMiddleware
{
    public function __construct(
        private AuthorizationService $authorization,
        private ActionProofPort $proofs,
    ) {}

    public function enforce(
        string $route,
        string $method,
        array $post,
        array $context,
    ): void {
        $decision = $this->authorization->evaluate($route, $method, $post, $context);
        if ($decision->skipped) {
            return;
        }
        $this->proofs->write($route, $context, $decision);
        if (!$decision->allowed) {
            throw new \ProntooHttpError(
                403,
                'Esta ação não está disponível para sua credencial atual.',
            );
        }
    }
}
