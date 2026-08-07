<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Tenant;

final class SessionTenantContext
{
    private function __construct()
    {
    }

    public static function clinicId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE || (string) ($_SESSION['scope'] ?? '') !== 'clinic') {
            return 0;
        }
        return max(0, (int) ($_SESSION['clinic_id'] ?? 0));
    }

    public static function roleCode(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        return preg_replace('/[^a-z0-9_\-]/i', '', (string) ($_SESSION['role_code'] ?? '')) ?: '';
    }

    public static function mutationContext(): array
    {
        $uid = (int) ($_SESSION['uid'] ?? 0);
        $route = function_exists('route') ? (string) \route() : '';
        $isGlobalAdmin = false;
        if ($uid > 0 && function_exists('user_is_global_admin')) {
            try {
                $isGlobalAdmin = (bool) \user_is_global_admin($uid);
            } catch (\Throwable $error) {
                error_log('[Prontoo mutation context] ' . $error->getMessage());
            }
        }
        return [
            'scope' => (string) ($_SESSION['scope'] ?? ''),
            'clinic_id' => self::clinicId(),
            'user_id' => $uid,
            'role' => self::roleCode(),
            'route' => $route,
            'action' => (string) ($_POST['act'] ?? ''),
            'is_global_admin' => $isGlobalAdmin,
            'disabled' => !empty($GLOBALS['PRONTOO_SCOPE_GUARD_DISABLED']),
            'system' => !empty($GLOBALS['PRONTOO_SCOPE_GUARD_SYSTEM']),
            'expected_clinic_id' => max(0, (int) ($GLOBALS['PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID'] ?? 0)),
        ];
    }
}
