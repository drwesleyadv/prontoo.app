<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Authorization;

use Prontoo\Application\Authorization\CapabilityProvider;
use Prontoo\Domain\Authorization\ActionContract;

final class RuntimeCapabilityProvider implements CapabilityProvider
{
    public function grants(
        ActionContract $contract,
        string $capability,
        array $context,
    ): bool {
        if ($capability === 'session:self') {
            return (int) ($context['user']['id'] ?? ($context['user_id'] ?? 0)) > 0;
        }
        if ($capability === 'admin:*') {
            return (string) ($context['scope'] ?? '') === 'global' &&
                (int) ($context['user']['id'] ?? ($context['user_id'] ?? 0)) > 0;
        }

        [$module, $operation] = $this->splitCapability($capability);
        if ($module === '' || $operation === '') {
            return false;
        }
        $role = (string) ($context['role'] ?? '');
        $clinicId = (int) ($context['clinic_id'] ?? 0);
        if ($clinicId <= 0 || $role === '') {
            return false;
        }

        if ($contract->policy === 'manager_only') {
            return $role === 'gerente';
        }
        if ($contract->policy === 'financial_operational' && $module === 'financial' && $operation === 'edit') {
            if ($role === 'gerente') {
                return true;
            }
            return $role === 'recepcionista' && $this->cashierActionAllowed($contract);
        }
        if ($contract->policy === 'notice_support' && $module === 'notices' && $operation === 'view') {
            if (!empty(($context['billing'] ?? [])['read_only'])) {
                return true;
            }
        }

        if ($role === 'gerente') {
            return true;
        }
        if (function_exists('permission_rules_for_role')) {
            try {
                $matrix = \permission_rules_for_role($role, $clinicId);
                return !empty($matrix[$module][$operation]);
            } catch (\Throwable $error) {
                error_log('[Prontoo layered capability] falha ao resolver capacidade: ' . $error->getMessage());
                return false;
            }
        }
        return $operation === 'view' && isset(($context['allowed'] ?? [])[$module]);
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
}
