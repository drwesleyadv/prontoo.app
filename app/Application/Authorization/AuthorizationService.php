<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization;

use Prontoo\Core\Invariant\Canonical;
use Prontoo\Core\Invariant\Decision;
use Prontoo\Domain\Authorization\ActionContract;

final readonly class AuthorizationService
{
    public function __construct(private CapabilityProvider $capabilities) {

    }

    public function evaluate(
        string $route,
        string $method,
        array $post,
        array $context,
    ): Decision {

        $route = Canonical::token($route, 'login');
        $method = strtoupper(trim($method));
        if ($method !== 'POST') {
            return Decision::skip('request_read_only', ['route' => $route]);
        }

        $contract = ActionCatalog::resolve($route, $post);
        $action = ActionCatalog::actionToken($post);
        if (!$contract instanceof ActionContract) {
            return Decision::deny(
                (string) ($context['scope'] ?? 'none'),
                null,
                'execute',
                'action_contract_missing',
                [
                    'policy' => Canonical::POLICY_VERSION,
                    'route' => $route,
                    'action' => $action,
                ],
            );
        }

        $scopeDecision = $this->assertScope($contract, $context);
        if ($scopeDecision instanceof Decision) {
            return $scopeDecision;
        }
        if ($contract->scope === 'public') {
            return Decision::skip(
                'exact_public_action_contract',
                $this->evidence($contract, $context, [], []),
            );
        }

        $granted = [];
        foreach ($contract->required as $capability) {
            if ($this->capabilities->grants($contract, $capability, $context)) {
                $granted[] = $capability;
            }
        }
        $missing = array_values(array_diff($contract->required, $granted));
        $evidence = $this->evidence($contract, $context, $granted, $missing);

        if ($missing !== []) {
            return Decision::deny(
                $contract->scope,
                self::primaryModule($contract),
                self::primaryOperation($contract),
                'action_capability_subset_not_satisfied',
                $evidence,
            );
        }

        return Decision::allow(
            $contract->scope,
            self::primaryModule($contract),
            self::primaryOperation($contract),
            'exact_action_capability_subset_satisfied',
            $evidence,
        );
    }

    private function assertScope(ActionContract $contract, array $context): ?Decision
    {

        if ($contract->scope === 'public') {
            return null;
        }
        $userId = (int) ($context['user']['id'] ?? ($context['user_id'] ?? 0));
        if ($userId <= 0) {
            return Decision::deny(
                'none',
                self::primaryModule($contract),
                self::primaryOperation($contract),
                'identity_missing_for_action',
                $this->evidence($contract, $context, [], $contract->required),
            );
        }
        $actual = (string) ($context['scope'] ?? '');
        if ($contract->scope === 'authenticated') {
            return in_array($actual, ['clinic', 'global'], true)
                ? null
                : Decision::deny(
                    $actual ?: 'none',
                    self::primaryModule($contract),
                    self::primaryOperation($contract),
                    'authenticated_scope_required',
                    $this->evidence($contract, $context, [], $contract->required),
                );
        }
        if ($contract->scope === 'global') {
            return $actual === 'global'
                ? null
                : Decision::deny(
                    $actual ?: 'none',
                    self::primaryModule($contract),
                    self::primaryOperation($contract),
                    'global_scope_required_for_action',
                    $this->evidence($contract, $context, [], $contract->required),
                );
        }
        $clinicId = (int) ($context['clinic_id'] ?? 0);
        return $actual === 'clinic' && $clinicId > 0
            ? null
            : Decision::deny(
                $actual ?: 'none',
                self::primaryModule($contract),
                self::primaryOperation($contract),
                'clinic_scope_required_for_action',
                $this->evidence($contract, $context, [], $contract->required),
            );
    }

    private function evidence(
        ActionContract $contract,
        array $context,
        array $granted,
        array $missing,
    ): array {

        return [
            'policy' => Canonical::POLICY_VERSION,
            'contract_hash' => $contract->hash(),
            'route' => $contract->route,
            'action' => $contract->action,
            'scope' => $contract->scope,
            'clinic_id' => (int) ($context['clinic_id'] ?? 0),
            'user_id' => (int) ($context['user']['id'] ?? ($context['user_id'] ?? 0)),
            'role' => (string) ($context['role'] ?? ''),
            'required' => $contract->required,
            'granted' => array_values($granted),
            'missing' => array_values($missing),
            'effects' => $contract->effects,
            'delegated' => $contract->delegated,
            'source' => $contract->source,
        ];
    }

    private static function primaryModule(ActionContract $contract): ?string
    {

        [$module] = self::splitCapability($contract->primary);
        return $module !== '' ? $module : null;
    }

    private static function primaryOperation(ActionContract $contract): string
    {

        [, $operation] = self::splitCapability($contract->primary);
        return $operation !== '' ? $operation : 'execute';
    }

    private static function splitCapability(string $capability): array
    {

        $parts = explode(':', $capability, 2);
        return [
            Canonical::token((string) ($parts[0] ?? ''), ''),
            Canonical::token((string) ($parts[1] ?? ''), ''),
        ];
    }

    public static function logicSelfTest(): array
    {

        $provider = new class implements CapabilityProvider {
            public function grants(ActionContract $contract, string $capability, array $context): bool
            {

                return in_array($capability, (array) ($context['grants'] ?? []), true) ||
                    ($capability === 'session:self' && (int) ($context['user']['id'] ?? 0) > 0) ||
                    ($capability === 'admin:*' && ($context['scope'] ?? '') === 'global');
            }
        };
        $service = new self($provider);
        $manager = [
            'scope' => 'clinic',
            'clinic_id' => 17,
            'user' => ['id' => 3],
            'role' => 'gerente',
            'grants' => ['patients:view', 'documents:add', 'appointments:add'],
        ];
        $global = ['scope' => 'global', 'user' => ['id' => 1], 'grants' => ['admin:*']];
        $cases = [];
        $cases['read_skips'] = $service->evaluate('appointments', 'GET', [], [])->skipped;
        $cases['public_exact_allowed'] = $service->evaluate('login', 'POST', [], [])->allowed;
        $cases['unknown_action_denied'] = !$service->evaluate('patient', 'POST', ['act' => 'unknown'], $manager)->allowed;
        $cases['composed_action_allowed'] = $service->evaluate('patient', 'POST', ['act' => 'issue_patient_document'], $manager)->allowed;
        $cases['composed_action_denied_when_partial'] = !$service->evaluate('patient', 'POST', ['act' => 'patient_revenue_receive'], $manager)->allowed;
        $cases['global_exact_allowed'] = $service->evaluate('admin_maintenance', 'POST', ['act' => 'save_settings'], $global)->allowed;
        $cases['global_unknown_denied'] = !$service->evaluate('admin_maintenance', 'POST', ['act' => 'unknown'], $global)->allowed;
        $cases['clinic_action_rejects_global'] = !$service->evaluate('appointments', 'POST', ['act' => 'create'], $global)->allowed;
        $catalog = ActionCatalog::logicSelfTest();
        $cases['catalog'] = !empty($catalog['ok']);
        $failed = array_keys(array_filter($cases, static  fn(bool $ok): bool => !$ok));
        return [
            'ok' => $failed === [],
            'passed' => count($cases) - count($failed),
            'total' => count($cases),
            'failed' => $failed,
            'catalog' => $catalog,
        ];
    }
}
