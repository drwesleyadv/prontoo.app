<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Authorization;

use Prontoo\Application\Authorization\CapabilityProvider;
use Prontoo\Domain\Authorization\ActionContract;

final class RuntimeCapabilityProvider implements CapabilityProvider
{
    
    private array $credentialCache = [];

    private array $permissionCache = [];

    public function __construct(
        private ?\Closure $credentialResolver = null,
        private ?\Closure $permissionResolver = null,
    ) {

    }

    public function grants(
        ActionContract $contract,
        string $capability,
        array $context,
    ): bool {

        $state = $this->credentialState($context);
        if (!$state['active']) {
            return false;
        }

        if ($capability === 'session:self') {
            return true;
        }
        if ($capability === 'admin:*') {
            return (string) ($context['scope'] ?? '') === 'global' &&
                $state['global_admin'];
        }

        [$module, $operation] = $this->splitCapability($capability);
        if ($module === '' || $operation === '') {
            return false;
        }
        $clinicId = (int) ($context['clinic_id'] ?? 0);
        $roles = $state['roles'];
        if ($clinicId <= 0 || $roles === []) {
            return false;
        }

        if ($contract->policy === 'manager_only') {
            return in_array('gerente', $roles, true);
        }
        if ($contract->policy === 'financial_operational' && $module === 'financial' && $operation === 'edit') {
            if (in_array('gerente', $roles, true)) {
                return true;
            }
            return in_array('recepcionista', $roles, true) &&
                $this->cashierActionAllowed($contract);
        }
        if ($contract->policy === 'notice_support' && $module === 'notices' && $operation === 'view') {
            if (!empty(($context['billing'] ?? [])['read_only'])) {
                return true;
            }
        }

        if (in_array('gerente', $roles, true)) {
            return true;
        }
        foreach ($roles as $role) {
            $matrix = $this->permissionMatrix($role, $clinicId);
            if (!empty($matrix[$module][$operation])) {
                return true;
            }
        }
        return $operation === 'view' && isset(($context['allowed'] ?? [])[$module]);
    }

    private function credentialState(array $context): array
    {

        $userId = (int) ($context['user']['id'] ?? ($context['user_id'] ?? 0));
        $scope = (string) ($context['scope'] ?? '');
        $clinicId = (int) ($context['clinic_id'] ?? 0);
        $key = $userId . '|' . $scope . '|' . $clinicId;
        if (isset($this->credentialCache[$key])) {
            return $this->credentialCache[$key];
        }
        if ($userId <= 0 || !in_array($scope, ['clinic', 'global'], true)) {
            return $this->credentialCache[$key] = [
                'active' => false,
                'global_admin' => false,
                'roles' => [],
            ];
        }

        if ($this->credentialResolver instanceof \Closure) {
            $resolved = ($this->credentialResolver)($context);
            return $this->credentialCache[$key] = $this->normalizeCredentialState($resolved);
        }

        try {
            if ($scope === 'global') {
                if (!function_exists('one')) {
                    throw new \RuntimeException('Leitura de credencial global indisponível.');
                }
                $row = \one(
                    'SELECT id,is_global_admin FROM pi_users WHERE id=? AND active=1 LIMIT 1',
                    [$userId],
                );
                $contextGlobal = (int) ($context['user']['is_global_admin'] ?? 0) === 1;
                return $this->credentialCache[$key] = [
                    'active' => is_array($row),
                    'global_admin' => is_array($row) &&
                        (int) ($row['is_global_admin'] ?? 0) === 1 &&
                        $contextGlobal,
                    'roles' => [],
                ];
            }

            if ($clinicId <= 0 || !function_exists('q')) {
                throw new \RuntimeException('Leitura de vínculo clínico indisponível.');
            }
            $rows = \q(
                'SELECT DISTINCT ur.role_code FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 JOIN pi_clinics c ON c.id=ur.clinic_id AND c.active=1 WHERE ur.user_id=? AND ur.clinic_id=? AND ur.active=1',
                [$userId, $clinicId],
            )->fetchAll();
            $roles = [];
            foreach ((array) $rows as $row) {
                $role = trim((string) ($row['role_code'] ?? ''));
                if ($role !== '') {
                    $roles[$role] = true;
                }
            }
            return $this->credentialCache[$key] = [
                'active' => $roles !== [],
                'global_admin' => false,
                'roles' => array_keys($roles),
            ];
        } catch (\Throwable $error) {
            error_log('[Prontoo layered capability] falha ao revalidar credencial: ' . $error->getMessage());
            return $this->credentialCache[$key] = [
                'active' => false,
                'global_admin' => false,
                'roles' => [],
            ];
        }
    }

    private function normalizeCredentialState(mixed $resolved): array
    {

        $resolved = is_array($resolved) ? $resolved : [];
        $roles = [];
        foreach ((array) ($resolved['roles'] ?? []) as $role) {
            $role = trim((string) $role);
            if ($role !== '') {
                $roles[$role] = true;
            }
        }
        return [
            'active' => !empty($resolved['active']),
            'global_admin' => !empty($resolved['global_admin']),
            'roles' => array_keys($roles),
        ];
    }

    private function permissionMatrix(string $role, int $clinicId): array
    {

        $key = $clinicId . '|' . $role;
        if (isset($this->permissionCache[$key])) {
            return $this->permissionCache[$key];
        }
        if ($this->permissionResolver instanceof \Closure) {
            $matrix = ($this->permissionResolver)($role, $clinicId);
            return $this->permissionCache[$key] = is_array($matrix) ? $matrix : [];
        }
        if (!function_exists('permission_rules_for_role')) {
            return $this->permissionCache[$key] = [];
        }
        try {
            $matrix = \permission_rules_for_role($role, $clinicId);
            return $this->permissionCache[$key] = is_array($matrix) ? $matrix : [];
        } catch (\Throwable $error) {
            error_log('[Prontoo layered capability] falha ao resolver capacidade: ' . $error->getMessage());
            return $this->permissionCache[$key] = [];
        }
    }

    private function cashierActionAllowed(ActionContract $contract): bool
    {

        if ($contract->route === 'patient') {
            return $contract->action === 'patient_revenue_receive';
        }
        return $contract->route === 'financial' && in_array(
            $contract->action,
            ['cash_open', 'cash_keep_closed', 'cash_receipt', 'cash_payment', 'cash_close'],
            true,
        );
    }

    private function splitCapability(string $capability): array
    {

        $parts = explode(':', $capability, 2);
        return [trim((string) ($parts[0] ?? '')), trim((string) ($parts[1] ?? ''))];
    }

    public static function logicSelfTest(): array
    {

        $provider = new self(
            static  fn(array $context): array => [
                'active' => !empty($context['live_active']),
                'global_admin' => !empty($context['live_global_admin']),
                'roles' => (array) ($context['live_roles'] ?? []),
            ],
            static  fn(string $role, int $clinicId): array => match ($role) {
                'medico' => ['patients' => ['edit' => true]],
                'assistente' => ['tasks' => ['edit' => true]],
                default => [],
            },
        );
        $global = new ActionContract(
            'admin_maintenance',
            'save_settings',
            'global',
            ['admin:*'],
            ['admin:write'],
            [],
            'global_admin',
            'admin:*',
            'Admin/AdminPages.php',
        );
        $patient = new ActionContract(
            'patient',
            'record',
            'clinic',
            ['patients:edit'],
            ['care:add'],
            [],
            'matrix',
            'patients:edit',
            'Domain/Patients/Patients.php',
        );
        $tasks = new ActionContract(
            'tasks',
            'done',
            'clinic',
            ['tasks:edit'],
            ['tasks:edit'],
            [],
            'matrix',
            'tasks:edit',
            'Domain/Tasks/TasksNotices.php',
        );
        $cashOpen = new ActionContract(
            'financial',
            'cash_open',
            'clinic',
            ['financial:edit'],
            ['financial:cashier'],
            [],
            'financial_operational',
            'financial:edit',
            'Domain/Financial/Financial.php',
        );
        $adminReceive = new ActionContract(
            'financial',
            'admin_receive',
            'clinic',
            ['financial:edit'],
            ['financial:edit'],
            [],
            'matrix',
            'financial:edit',
            'Domain/Financial/Financial.php',
        );
        $maestro = new ActionContract(
            'maestro',
            'save_rule',
            'clinic',
            ['maestro:add'],
            ['maestro:add'],
            [],
            'manager_only',
            'maestro:add',
            'Domain/Maestro/Maestro.php',
        );
        $cases = [];
        $cases['global_requires_live_admin'] = !$provider->grants($global, 'admin:*', [
            'scope' => 'global',
            'user' => ['id' => 1, 'is_global_admin' => 1],
            'live_active' => true,
            'live_global_admin' => false,
        ]);
        $cases['global_live_admin_allowed'] = $provider->grants($global, 'admin:*', [
            'scope' => 'global',
            'user' => ['id' => 2, 'is_global_admin' => 1],
            'live_active' => true,
            'live_global_admin' => true,
        ]);
        $cases['stale_clinic_role_denied'] = !$provider->grants($patient, 'patients:edit', [
            'scope' => 'clinic',
            'clinic_id' => 17,
            'user' => ['id' => 3],
            'role' => 'medico',
            'live_active' => false,
            'live_roles' => [],
        ]);
        $cases['secondary_role_capability_allowed'] = $provider->grants($tasks, 'tasks:edit', [
            'scope' => 'clinic',
            'clinic_id' => 18,
            'user' => ['id' => 4],
            'role' => 'medico',
            'effective_roles' => ['medico', 'assistente'],
            'live_active' => true,
            'live_roles' => ['medico', 'assistente'],
        ]);
        $cases['clinic_manager_never_grants_global_admin'] = !$provider->grants($global, 'admin:*', [
            'scope' => 'clinic',
            'clinic_id' => 19,
            'user' => ['id' => 5, 'is_global_admin' => 0],
            'live_active' => true,
            'live_global_admin' => false,
            'live_roles' => ['gerente'],
        ]);
        $cases['global_admin_never_inherits_clinic_capability'] = !$provider->grants($patient, 'patients:edit', [
            'scope' => 'global',
            'user' => ['id' => 6, 'is_global_admin' => 1],
            'live_active' => true,
            'live_global_admin' => true,
            'live_roles' => [],
        ]);
        $cases['reception_cashier_exact_action_allowed'] = $provider->grants($cashOpen, 'financial:edit', [
            'scope' => 'clinic',
            'clinic_id' => 20,
            'user' => ['id' => 7],
            'live_active' => true,
            'live_roles' => ['recepcionista'],
        ]);
        $cases['reception_admin_financial_action_denied'] = !$provider->grants($adminReceive, 'financial:edit', [
            'scope' => 'clinic',
            'clinic_id' => 20,
            'user' => ['id' => 7],
            'live_active' => true,
            'live_roles' => ['recepcionista'],
        ]);
        $cases['manager_only_policy_allows_manager'] = $provider->grants($maestro, 'maestro:add', [
            'scope' => 'clinic',
            'clinic_id' => 21,
            'user' => ['id' => 8],
            'live_active' => true,
            'live_roles' => ['gerente'],
        ]);
        $cases['manager_only_policy_denies_professional'] = !$provider->grants($maestro, 'maestro:add', [
            'scope' => 'clinic',
            'clinic_id' => 21,
            'user' => ['id' => 9],
            'live_active' => true,
            'live_roles' => ['medico'],
        ]);
        $failed = array_keys(array_filter($cases, static  fn(bool $ok): bool => !$ok));
        return [
            'ok' => $failed === [],
            'passed' => count($cases) - count($failed),
            'total' => count($cases),
            'failed' => $failed,
        ];
    }
}
