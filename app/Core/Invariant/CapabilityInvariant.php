<?php
declare(strict_types=1);

namespace Prontoo\Core\Invariant;

final class CapabilityInvariant
{
    private function __construct() {}

    public static function evaluate(
        string $route,
        string $method,
        array $post,
        array $ctx,
    ): Decision {
        $route = Canonical::token($route, "login");
        $method = strtoupper(trim($method));
        if ($method !== "POST") {
            return Decision::skip("request_read_only", ["route" => $route]);
        }

        $contract = ActionCapabilityCatalog::resolve($route, $post);
        $action = ActionCapabilityCatalog::actionToken($post);
        if ($contract === null) {
            return Decision::deny(
                (string) ($ctx["scope"] ?? "none"),
                null,
                "execute",
                "action_contract_missing",
                [
                    "policy" => Canonical::POLICY_VERSION,
                    "route" => $route,
                    "action" => $action,
                ],
            );
        }

        $scope = (string) ($contract["scope"] ?? "clinic");
        $scopeDecision = self::assertScope($scope, $ctx, $route, $action, $contract);
        if ($scopeDecision instanceof Decision) {
            return $scopeDecision;
        }
        if ($scope === "public") {
            return Decision::skip(
                "exact_public_action_contract",
                self::evidence($route, $action, $ctx, $contract, [], []),
            );
        }

        $required = array_values(array_unique(array_map("strval", $contract["required"] ?? [])));
        $granted = [];
        foreach ($required as $capability) {
            if (self::granted($capability, $ctx, $contract)) {
                $granted[] = $capability;
            }
        }
        $missing = array_values(array_diff($required, $granted));
        $evidence = self::evidence($route, $action, $ctx, $contract, $granted, $missing);

        if ($missing !== []) {
            return Decision::deny(
                $scope,
                self::primaryModule($contract),
                self::primaryOperation($contract),
                "action_capability_subset_not_satisfied",
                $evidence,
            );
        }

        return Decision::allow(
            $scope,
            self::primaryModule($contract),
            self::primaryOperation($contract),
            "exact_action_capability_subset_satisfied",
            $evidence,
        );
    }

    private static function assertScope(
        string $requiredScope,
        array $ctx,
        string $route,
        string $action,
        array $contract,
    ): ?Decision {
        if ($requiredScope === "public") {
            return null;
        }
        $userId = (int) ($ctx["user"]["id"] ?? ($ctx["user_id"] ?? 0));
        if (!$ctx || $userId <= 0) {
            return Decision::deny(
                "none",
                self::primaryModule($contract),
                self::primaryOperation($contract),
                "identity_missing_for_action",
                self::evidence($route, $action, $ctx, $contract, [], (array) ($contract["required"] ?? [])),
            );
        }
        $actual = (string) ($ctx["scope"] ?? "");
        if ($requiredScope === "authenticated") {
            return in_array($actual, ["clinic", "global"], true)
                ? null
                : Decision::deny(
                    $actual ?: "none",
                    self::primaryModule($contract),
                    self::primaryOperation($contract),
                    "authenticated_scope_required",
                    self::evidence($route, $action, $ctx, $contract, [], (array) ($contract["required"] ?? [])),
                );
        }
        if ($requiredScope === "global") {
            return $actual === "global"
                ? null
                : Decision::deny(
                    $actual ?: "none",
                    self::primaryModule($contract),
                    self::primaryOperation($contract),
                    "global_scope_required_for_action",
                    self::evidence($route, $action, $ctx, $contract, [], (array) ($contract["required"] ?? [])),
                );
        }
        $clinicId = (int) ($ctx["clinic_id"] ?? 0);
        return $actual === "clinic" && $clinicId > 0
            ? null
            : Decision::deny(
                $actual ?: "none",
                self::primaryModule($contract),
                self::primaryOperation($contract),
                "clinic_scope_required_for_action",
                self::evidence($route, $action, $ctx, $contract, [], (array) ($contract["required"] ?? [])),
            );
    }

    private static function granted(string $capability, array $ctx, array $contract): bool
    {
        if ($capability === "session:self") {
            return (int) ($ctx["user"]["id"] ?? ($ctx["user_id"] ?? 0)) > 0;
        }
        if ($capability === "admin:*") {
            return (string) ($ctx["scope"] ?? "") === "global" &&
                (int) ($ctx["user"]["id"] ?? ($ctx["user_id"] ?? 0)) > 0;
        }

        [$module, $operation] = self::splitCapability($capability);
        if ($module === "" || $operation === "") {
            return false;
        }
        $role = (string) ($ctx["role"] ?? "");
        $clinicId = (int) ($ctx["clinic_id"] ?? 0);
        if ($clinicId <= 0 || $role === "") {
            return false;
        }

        $policy = (string) ($contract["policy"] ?? "matrix");
        if ($policy === "manager_only") {
            return $role === "gerente";
        }
        if ($policy === "financial_operational" && $module === "financial" && $operation === "edit") {
            if ($role === "gerente") {
                return true;
            }
            return $role === "recepcionista" && self::cashierActionAllowed((string) ($contract["route"] ?? ""), (string) ($contract["action"] ?? ""));
        }
        if ($policy === "notice_support" && $module === "notices" && $operation === "view") {
            if (!empty(($ctx["billing"] ?? [])["read_only"])) {
                return true;
            }
        }

        if ($role === "gerente") {
            return true;
        }
        if (function_exists("permission_rules_for_role")) {
            try {
                $matrix = \permission_rules_for_role($role, $clinicId);
                return !empty($matrix[$module][$operation]);
            } catch (\Throwable $error) {
                error_log("[Prontoo action capability] falha ao resolver capacidade: " . $error->getMessage());
                return false;
            }
        }
        if ($operation === "view" && isset(($ctx["allowed"] ?? [])[$module])) {
            return true;
        }
        return false;
    }

    private static function cashierActionAllowed(string $route, string $action): bool
    {
        if ($route === "patient") {
            return $action === "patient_revenue_receive";
        }
        return $route === "financial" && in_array(
            $action,
            ["cash_open", "cash_keep_closed", "cash_receipt", "cash_payment", "cash_close"],
            true,
        );
    }

    private static function splitCapability(string $capability): array
    {
        $parts = explode(":", $capability, 2);
        return [Canonical::token((string) ($parts[0] ?? ""), ""), Canonical::token((string) ($parts[1] ?? ""), "")];
    }

    private static function primaryModule(array $contract): ?string
    {
        [$module] = self::splitCapability((string) ($contract["primary"] ?? ""));
        return $module !== "" ? $module : null;
    }

    private static function primaryOperation(array $contract): string
    {
        [, $operation] = self::splitCapability((string) ($contract["primary"] ?? ""));
        return $operation !== "" ? $operation : "execute";
    }

    private static function evidence(
        string $route,
        string $action,
        array $ctx,
        array $contract,
        array $granted,
        array $missing,
    ): array {
        return [
            "policy" => Canonical::POLICY_VERSION,
            "contract_hash" => (string) ($contract["contract_hash"] ?? ""),
            "route" => $route,
            "action" => $action,
            "scope" => (string) ($contract["scope"] ?? ""),
            "clinic_id" => (int) ($ctx["clinic_id"] ?? 0),
            "user_id" => (int) ($ctx["user"]["id"] ?? ($ctx["user_id"] ?? 0)),
            "role" => (string) ($ctx["role"] ?? ""),
            "required" => array_values((array) ($contract["required"] ?? [])),
            "granted" => array_values($granted),
            "missing" => array_values($missing),
            "effects" => array_values((array) ($contract["effects"] ?? [])),
            "delegated" => array_values((array) ($contract["delegated"] ?? [])),
        ];
    }

    public static function logicSelfTest(): array
    {
        $manager = [
            "scope" => "clinic",
            "clinic_id" => 17,
            "user" => ["id" => 3],
            "role" => "gerente",
        ];
        $global = ["scope" => "global", "user" => ["id" => 1]];
        $cases = [];
        $cases["read_skips"] = self::evaluate("appointments", "GET", [], [])->skipped;
        $cases["public_exact_allowed"] = self::evaluate("login", "POST", [], [])->allowed;
        $cases["unknown_action_denied"] = !self::evaluate("patient", "POST", ["act" => "unknown"], $manager)->allowed;
        $cases["composed_action_allowed_for_manager"] = self::evaluate("patient", "POST", ["act" => "issue_patient_document"], $manager)->allowed;
        $cases["global_exact_allowed"] = self::evaluate("admin_maintenance", "POST", ["act" => "save_settings"], $global)->allowed;
        $cases["global_unknown_denied"] = !self::evaluate("admin_maintenance", "POST", ["act" => "unknown"], $global)->allowed;
        $cases["clinic_action_rejects_global"] = !self::evaluate("appointments", "POST", ["act" => "create"], $global)->allowed;
        $catalog = ActionCapabilityCatalog::logicSelfTest();
        $cases["catalog"] = !empty($catalog["ok"]);
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
            "catalog" => $catalog,
        ];
    }
}
