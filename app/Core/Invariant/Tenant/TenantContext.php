<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Tenant;

final class TenantContext
{
    private function __construct()
    {
    }

    public static function resolve(array $runtime): array
    {
        if (!empty($runtime['disabled'])) {
            return ['bypass' => true, 'reason' => 'guard_disabled', 'clinic_id' => 0];
        }
        if (!empty($runtime['system'])) {
            return ['bypass' => true, 'reason' => 'system_operation', 'clinic_id' => 0];
        }
        $expected = max(0, (int) ($runtime['expected_clinic_id'] ?? 0));
        if ($expected > 0) {
            return ['bypass' => false, 'reason' => 'declared_clinic_context', 'clinic_id' => $expected];
        }
        $scope = (string) ($runtime['scope'] ?? '');
        $route = (string) ($runtime['route'] ?? '');
        if ($scope === 'global' || (str_starts_with($route, 'admin_') && !empty($runtime['is_global_admin']))) {
            return ['bypass' => true, 'reason' => 'global_operation', 'clinic_id' => 0];
        }
        $clinicId = $scope === 'clinic' ? max(0, (int) ($runtime['clinic_id'] ?? 0)) : 0;
        return [
            'bypass' => $clinicId <= 0,
            'reason' => $clinicId > 0 ? 'session_clinic_context' : 'no_clinic_context',
            'clinic_id' => $clinicId,
        ];
    }
}
