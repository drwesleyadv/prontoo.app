<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Http;

use Prontoo\Application\Audit\ActionProofPort;
use Prontoo\Application\Authorization\AuthorizationService;
use Prontoo\Application\Authorization\CapabilityProvider;
use Prontoo\Core\Invariant\Decision;
use Prontoo\Domain\Authorization\ActionContract;

final readonly class ActionMiddleware
{
    public function __construct(
        private AuthorizationService $authorization,
        private ActionProofPort $proofs,
    ) {
        








    }

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

        $proofWritten = $this->proofs->write($route, $context, $decision);
        if (!$decision->allowed) {
            throw new \ProntooHttpError(
                403,
                'Esta ação não está disponível para sua credencial atual.',
            );
        }
        if (!$proofWritten) {
            throw new \ProntooHttpError(
                503,
                'Não foi possível registrar a prova de autorização. Nenhuma alteração foi executada.',
            );
        }

        $GLOBALS['PRONTOO_ACTION_AUTHORIZATION_SEAL'] = [
            'route' => (string) ($decision->evidence['route'] ?? $route),
            'action' => (string) ($decision->evidence['action'] ?? ''),
            'user_id' => (int) ($decision->evidence['user_id'] ?? 0),
            'clinic_id' => (int) ($decision->evidence['clinic_id'] ?? 0),
            'contract_hash' => (string) ($decision->evidence['contract_hash'] ?? ''),
            'decision_proof_hash' => $decision->proofHash,
            'ledger_id' => (int) ($GLOBALS['PRONTOO_ACTION_LEDGER_ID'] ?? 0),
            'request_id' => (string) ($GLOBALS['PRONTOO_ACTION_LEDGER_REQUEST_ID'] ?? ''),
        ];
    }

    public static function logicSelfTest(): array
    {
        









        $provider = new class implements CapabilityProvider {
            public function grants(ActionContract $contract, string $capability, array $context): bool
            {
                








                return in_array($capability, (array) ($context['grants'] ?? []), true);
            }
        };
        $proofs = new class implements ActionProofPort {
            public bool $result = true;
            public int $writes = 0;

            public function write(string $route, array $context, Decision $decision): bool
            {
                








                $this->writes++;
                return $this->result;
            }
        };
        $middleware = new self(new AuthorizationService($provider), $proofs);
        $context = [
            'scope' => 'clinic',
            'clinic_id' => 17,
            'user' => ['id' => 3],
            'role' => 'medico',
            'grants' => ['patients:edit'],
        ];
        $cases = [];

        $middleware->enforce('patient', 'POST', ['act' => 'record'], $context);
        $cases['allowed_with_proof'] = $proofs->writes === 1;

        $proofs->result = false;
        $failClosed = false;
        try {
            $middleware->enforce('patient', 'POST', ['act' => 'record'], $context);
        } catch (\ProntooHttpError $error) {
            $failClosed = $error->status === 503;
        }
        $cases['allowed_without_proof_is_blocked'] = $failClosed;

        $deniedStillDenied = false;
        try {
            $middleware->enforce('patient', 'POST', ['act' => 'delete_patient'], $context);
        } catch (\ProntooHttpError $error) {
            $deniedStillDenied = $error->status === 403;
        }
        $cases['denied_without_proof_remains_denied'] = $deniedStillDenied;

        $before = $proofs->writes;
        $middleware->enforce('patient', 'GET', [], $context);
        $cases['read_skips_proof'] = $proofs->writes === $before;

        $failed = array_keys(array_filter($cases, static  fn(bool $ok): bool => !$ok));
        return [
            'ok' => $failed === [],
            'passed' => count($cases) - count($failed),
            'total' => count($cases),
            'failed' => $failed,
        ];
    }
}
