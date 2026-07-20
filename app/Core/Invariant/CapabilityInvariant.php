<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

final class CapabilityInvariant
{
    private const PUBLIC_POST_ROUTES = [
        "login",
        "login_autotest",
        "signup",
        "logout",
        "switch",
        "profile",
    ];

    private const ROUTE_MODULES = [
        "painel" => "painel",
        "operations" => "operations",
        "maestro" => "maestro",
        "leads" => "leads",
        "lead_lookup" => "leads",
        "lead_patient_lookup" => "leads",
        "appointments" => "appointments",
        "patients" => "patients",
        "patient" => "patients",
        "patient_lookup" => "patients",
        "patient_suggest" => "patients",
        "documents" => "documents",
        "document_view" => "documents",
        "document_print" => "documents",
        "document_pdf" => "documents",
        "tasks" => "tasks",
        "notices" => "notices",
        "users" => "users",
        "user" => "users",
        "permissions" => "permissions",
        "audit" => "audit",
        "settings" => "settings",
        "onboarding" => "settings",
        "financial" => "financial",
        "creditors" => "financial",
        "counterparty_lookup" => "financial",
        "counterparty_suggest" => "financial",
        "procedures" => "procedures",
    ];

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
        if (in_array($route, self::PUBLIC_POST_ROUTES, true)) {
            return Decision::skip("public_post_route", ["route" => $route]);
        }
        if (!$ctx) {
            return Decision::deny(
                "none",
                null,
                "view",
                "identidade_ausente",
                ["route" => $route],
            );
        }

        $scope = (string) ($ctx["scope"] ?? "");
        if ($scope === "global") {
            if (!str_starts_with($route, "admin_")) {
                return Decision::deny(
                    "global",
                    null,
                    "view",
                    "escopo_global_incompativel",
                    ["route" => $route],
                );
            }
            return Decision::allow(
                "global",
                null,
                "admin",
                "global_admin_subset_satisfied",
                ["route" => $route, "required" => ["admin:*"], "granted" => ["admin:*"]],
            );
        }

        $clinicId = (int) ($ctx["clinic_id"] ?? 0);
        $userId = (int) ($ctx["user"]["id"] ?? 0);
        if ($scope !== "clinic" || $clinicId <= 0 || $userId <= 0) {
            return Decision::deny(
                $scope ?: "none",
                null,
                "view",
                "escopo_clinica_invalido",
                ["route" => $route, "clinic_id" => $clinicId, "user_id" => $userId],
            );
        }

        $module = self::ROUTE_MODULES[$route] ?? null;
        if ($module === null) {
            return Decision::skip("route_without_domain_mutation_contract", ["route" => $route]);
        }

        $action = Canonical::token((string) ($post["act"] ?? ""), "");
        if (self::isCashierFinancialAction($route, $action, $ctx)) {
            return Decision::allow(
                "clinic",
                $module,
                "edit",
                "cashier_operational_capability",
                self::baseEvidence($route, $action, $ctx) + [
                    "required" => ["financial:edit"],
                    "granted" => ["financial:edit"],
                ],
            );
        }
        if (self::isReadonlyNoticeSupportAction($route, $action, $ctx)) {
            return Decision::allow(
                "clinic",
                $module,
                "view",
                "readonly_support_capability",
                self::baseEvidence($route, $action, $ctx) + [
                    "required" => ["notices:view"],
                    "granted" => ["notices:view"],
                ],
            );
        }

        $operation = self::operationFor($action, $post, $module);
        $required = [$module . ":" . $operation];
        $granted = self::capabilitySet($ctx, $module);
        $missing = array_values(array_diff($required, $granted));
        $evidence = self::baseEvidence($route, $action, $ctx) + [
            "required" => $required,
            "granted" => $granted,
            "missing" => $missing,
        ];
        if ($missing !== []) {
            return Decision::deny(
                "clinic",
                $module,
                $operation,
                "required_capability_not_in_active_set",
                $evidence,
            );
        }
        return Decision::allow(
            "clinic",
            $module,
            $operation,
            "required_capability_subset_satisfied",
            $evidence,
        );
    }

    private static function capabilitySet(array $ctx, string $module): array
    {
        $clinicId = (int) ($ctx["clinic_id"] ?? 0);
        $role = (string) ($ctx["role"] ?? "");
        if ($clinicId <= 0 || $role === "") {
            return [];
        }
        $operations = ["view", "add", "edit", "delete"];
        if ($role === "gerente") {
            return array_map(
                static fn(string $operation): string => $module . ":" . $operation,
                $operations,
            );
        }
        if (function_exists("permission_rules_for_role")) {
            try {
                $matrix = \permission_rules_for_role($role, $clinicId);
                $set = [];
                foreach ($operations as $operation) {
                    if (!empty($matrix[$module][$operation])) {
                        $set[] = $module . ":" . $operation;
                    }
                }
                return array_values(array_unique($set));
            } catch (\Throwable $error) {
                error_log(
                    "[Prontoo invariant capability] falha ao resolver conjunto de capacidades: " .
                        $error->getMessage(),
                );
                return [];
            }
        }
        if (isset(($ctx["allowed"] ?? [])[$module])) {
            return [$module . ":view"];
        }
        return [];
    }

    private static function operationFor(string $action, array $post, string $module): string
    {
        $id = max(
            0,
            (int) ($post["id"] ??
                ($post["patient_id"] ??
                    ($post["task_id"] ??
                        ($post["document_id"] ??
                            ($post["template_id"] ??
                                ($post["notice_id"] ?? 0)))))),
        );
        foreach (["delete", "remove", "deactivate", "cancel", "discard", "archive", "restore"] as $needle) {
            if (
                $action === $needle ||
                str_contains($action, $needle . "_") ||
                str_contains($action, "_" . $needle)
            ) {
                return self::supported($module, "delete") ? "delete" : "edit";
            }
        }
        foreach (["add", "new", "create", "store", "register", "emit", "issue"] as $needle) {
            if (
                $action === $needle ||
                str_starts_with($action, $needle . "_") ||
                str_contains($action, "_" . $needle)
            ) {
                return self::supported($module, "add") ? "add" : "edit";
            }
        }
        if (
            $id <= 0 &&
            in_array(
                $module,
                ["leads", "appointments", "patients", "documents", "tasks", "notices", "users", "procedures", "financial"],
                true,
            )
        ) {
            return self::supported($module, "add") ? "add" : "edit";
        }
        return self::supported($module, "edit") ? "edit" : "view";
    }

    private static function supported(string $module, string $operation): bool
    {
        if (!function_exists("permission_operation_supported")) {
            return false;
        }
        try {
            return (bool) \permission_operation_supported($module, $operation);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function isCashierFinancialAction(string $route, string $action, array $ctx): bool
    {
        return $route === "financial" &&
            (string) ($ctx["scope"] ?? "") === "clinic" &&
            (string) ($ctx["role"] ?? "") === "recepcionista" &&
            (int) ($ctx["clinic_id"] ?? 0) > 0 &&
            (int) ($ctx["user"]["id"] ?? 0) > 0 &&
            in_array(
                $action,
                ["cash_open", "cash_keep_closed", "cash_receipt", "cash_payment", "cash_close"],
                true,
            );
    }

    private static function isReadonlyNoticeSupportAction(string $route, string $action, array $ctx): bool
    {
        return $route === "notices" &&
            (string) ($ctx["scope"] ?? "") === "clinic" &&
            !empty(($ctx["billing"] ?? [])["read_only"]) &&
            in_array($action, ["support_message", "ack", "hide", "unhide"], true);
    }

    private static function baseEvidence(string $route, string $action, array $ctx): array
    {
        return [
            "policy" => Canonical::POLICY_VERSION,
            "route" => $route,
            "action" => $action,
            "clinic_id" => (int) ($ctx["clinic_id"] ?? 0),
            "user_id" => (int) ($ctx["user"]["id"] ?? 0),
            "role" => (string) ($ctx["role"] ?? ""),
        ];
    }

    public static function logicSelfTest(): array
    {
        $cases = [];
        $cases["read_skips"] = self::evaluate("appointments", "GET", [], [])->skipped;
        $cases["public_post_skips"] = self::evaluate("login", "POST", [], [])->skipped;
        $cases["missing_context_denied"] = !self::evaluate("appointments", "POST", [], [])->allowed;
        $cases["global_admin_route_allowed"] = self::evaluate(
            "admin_painel",
            "POST",
            ["act" => "save"],
            ["scope" => "global", "user" => ["id" => 1]],
        )->allowed;
        $cases["manager_specialized_route_allowed"] = self::evaluate(
            "maestro",
            "POST",
            ["act" => "run", "id" => 1],
            [
                "scope" => "clinic",
                "clinic_id" => 17,
                "user" => ["id" => 3],
                "role" => "gerente",
                "allowed" => ["maestro" => true],
            ],
        )->allowed;
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
        ];
    }
}
