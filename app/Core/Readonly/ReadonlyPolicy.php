<?php
declare(strict_types=1);
namespace Prontoo\Core\Readonly;
use Prontoo\Core\Support\Check;
use Prontoo\Core\Tenant\TenantRegistry;
final class ReadonlyPolicy
{
    private const PASSIVE_ROUTES = [
        "login",
        "login_autotest",
        "logout",
        "switch",
        "profile",
        "onboarding",
    ];
    private const PASSIVE_WRITE_TABLES = [
        "pi_audit",
        "pi_user_devices",
        "pi_login_locks",
        "pi_clinic_roles",
        "pi_permissions",
        "pi_platform_counters",
        "pi_users",
        "pi_persons",
    ];
    private const SUBSCRIPTION_WRITE_TABLES = [
        "pi_subscription_payments",
        "pi_clinics",
        "pi_audit",
        "pi_notices",
        "pi_notice_reads",
        "pi_platform_counters",
    ];
    private function __construct() {}
    public static function postAllowed(string $route, string $action = ""): bool
    {
        $route = self::cleanRoute($route);
        $action = self::cleanAction($action);
        if (in_array($route, self::PASSIVE_ROUTES, true)) {
            return true;
        }
        if (
            $route === "notices" &&
            in_array(
                $action,
                ["support_message", "ack", "hide", "unhide"],
                true,
            )
        ) {
            return true;
        }
        return $route === "settings" && $action === "subscription_claim";
    }
    public static function allowedWriteTables(
        string $route,
        string $action = "",
        bool $isClinicScope = true,
    ): array {
        if (!$isClinicScope) {
            return ["*"];
        }
        $route = self::cleanRoute($route);
        $action = self::cleanAction($action);
        if ($route === "onboarding") {
            return [
                "pi_clinics",
                "pi_clinic_roles",
                "pi_permissions",
                "pi_permission_rules",
                "pi_persons",
                "pi_users",
                "pi_user_roles",
                "pi_user_work_hours",
                "pi_audit",
                "pi_platform_counters",
            ];
        }
        if (in_array($route, self::PASSIVE_ROUTES, true)) {
            return self::PASSIVE_WRITE_TABLES;
        }
        if ($route === "notices" && $action === "support_message") {
            return ["pi_admin_alerts", "pi_audit", "pi_platform_counters"];
        }
        if (
            $route === "notices" &&
            in_array($action, ["ack", "hide", "unhide"], true)
        ) {
            return ["pi_notice_reads", "pi_audit", "pi_platform_counters"];
        }
        if ($route === "settings" && $action === "subscription_claim") {
            return self::SUBSCRIPTION_WRITE_TABLES;
        }
        return [];
    }
    public static function sqlAllowed(
        string $sql,
        string $route,
        string $action = "",
        bool $isClinicScope = true,
    ): bool {
        $allowed = self::allowedWriteTables($route, $action, $isClinicScope);
        if (in_array("*", $allowed, true)) {
            return true;
        }
        if ($allowed === []) {
            return false;
        }
        $norm = Check::normalizedSql($sql);
        foreach (TenantRegistry::scopedTables() as $table => $scopeCol) {
            if (Check::tableHit($norm, $table)) {
                return in_array($table, $allowed, true);
            }
        }
        return false;
    }
    private static function cleanRoute(string $route): string
    {
        return preg_replace("/[^a-z0-9_\-]/i", "", $route) ?: "login";
    }
    private static function cleanAction(string $action): string
    {
        return preg_replace("/[^a-z0-9_\-]/i", "", $action) ?: "";
    }
}
