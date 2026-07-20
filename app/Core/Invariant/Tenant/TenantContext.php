<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Tenant;

use Prontoo\Core\Tenant\TenantRegistry;

final class TenantContext
{
    private function __construct() {}

    public static function resolve(): array
    {
        if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"])) {
            return ["bypass" => true, "reason" => "guard_disabled", "clinic_id" => 0];
        }
        if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"])) {
            return ["bypass" => true, "reason" => "system_operation", "clinic_id" => 0];
        }
        $expected = max(
            0,
            (int) ($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] ?? 0),
        );
        if ($expected > 0) {
            return [
                "bypass" => false,
                "reason" => "declared_clinic_context",
                "clinic_id" => $expected,
            ];
        }
        if (self::globalOrAdminContext()) {
            return ["bypass" => true, "reason" => "global_operation", "clinic_id" => 0];
        }
        $clinicId = TenantRegistry::sessionClinicId();
        return [
            "bypass" => $clinicId <= 0,
            "reason" => $clinicId > 0 ? "session_clinic_context" : "no_clinic_context",
            "clinic_id" => $clinicId,
        ];
    }

    private static function globalOrAdminContext(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if ((string) ($_SESSION["scope"] ?? "") === "global") {
            return true;
        }
        $route = function_exists("route") ? (string) \route() : "";
        return str_starts_with($route, "admin_") && self::sessionUserIsGlobalAdmin();
    }

    private static function sessionUserIsGlobalAdmin(): bool
    {
        $uid = (int) ($_SESSION["uid"] ?? 0);
        if ($uid <= 0 || !function_exists("user_is_global_admin")) {
            return false;
        }
        try {
            return (bool) \user_is_global_admin($uid);
        } catch (\Throwable $error) {
            error_log(
                "[Prontoo invariant tenant context] falha ao validar Desenvolvedor: " .
                    $error->getMessage(),
            );
            return false;
        }
    }
}
