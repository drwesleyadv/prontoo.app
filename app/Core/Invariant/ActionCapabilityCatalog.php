<?php
declare(strict_types=1);

namespace Prontoo\Core\Invariant;

final class ActionCapabilityCatalog
{
    public const DEFAULT_ACTION = "__default__";

    private function __construct() {}

    public static function actionToken(array $post): string
    {
        $action = Canonical::token((string) ($post["act"] ?? ""), "");
        return $action !== "" ? $action : self::DEFAULT_ACTION;
    }

    public static function resolve(string $route, array $post): ?array
    {
        $route = Canonical::token($route, "login");
        $action = self::actionToken($post);
        $definition = self::catalog()[$route][$action] ?? null;
        if (!is_array($definition)) {
            return null;
        }
        $definition["route"] = $route;
        $definition["action"] = $action;
        $definition["required"] = self::conditionalRequired($definition, $post);
        $definition["effects"] = array_values(array_unique(array_map("strval", $definition["effects"] ?? [])));
        $definition["delegated"] = array_values(array_unique(array_map("strval", $definition["delegated"] ?? [])));
        $definition["contract_hash"] = Canonical::hash("action_capability", [
            "route" => $route,
            "action" => $action,
            "scope" => (string) ($definition["scope"] ?? "clinic"),
            "required" => $definition["required"],
            "effects" => $definition["effects"],
            "delegated" => $definition["delegated"],
            "policy" => (string) ($definition["policy"] ?? "matrix"),
        ]);
        return $definition;
    }

    public static function catalog(): array
    {
        static $catalog = null;
        if (is_array($catalog)) {
            return $catalog;
        }

        $catalog = [];
        $add = static function (
            string $route,
            string|array $actions,
            string $scope,
            array $required = [],
            array $effects = [],
            array $delegated = [],
            string $policy = "matrix",
            ?string $primary = null,
        ) use (&$catalog): void {
            foreach ((array) $actions as $action) {
                $action = $action !== "" ? $action : self::DEFAULT_ACTION;
                if (isset($catalog[$route][$action])) {
                    throw new \LogicException("Contrato duplicado: {$route}:{$action}");
                }
                $catalog[$route][$action] = [
                    "scope" => $scope,
                    "required" => array_values(array_unique(array_map("strval", $required))),
                    "effects" => array_values(array_unique(array_map("strval", $effects))),
                    "delegated" => array_values(array_unique(array_map("strval", $delegated))),
                    "policy" => $policy,
                    "primary" => $primary ?? ($required[0] ?? ($scope === "global" ? "admin:*" : "session:self")),
                ];
            }
        };

        // Public authentication contracts. No authenticated route receives an implicit bypass.
        $add("login", self::DEFAULT_ACTION, "public", [], ["session:authenticate"]);
        $add("signup", self::DEFAULT_ACTION, "public", [], ["clinic:create", "session:authenticate"]);
        $add("login_autotest", self::DEFAULT_ACTION, "public", [], ["session:autotest"]);
        $add("mobile_web_access", self::DEFAULT_ACTION, "public", [], ["session:mobile_probe"]);

        // Authenticated self-service contracts.
        $add("logout", self::DEFAULT_ACTION, "authenticated", ["session:self"], ["session:logout"]);
        $add("switch", [self::DEFAULT_ACTION, "choose_admin"], "authenticated", ["session:self"], ["session:environment"]);
        $add("profile", ["profile_update_user", "profile_change_password", "profile_switch_environment"], "authenticated", ["session:self"], ["identity:self", "session:environment"]);

        // Dismissal is handled centrally before the route handler, but remains an exact action contract.
        foreach (["painel", "operations", "leads", "patients", "appointments", "procedures", "documents", "tasks", "maestro", "audit", "financial", "notices", "users", "settings", "permissions"] as $route) {
            $add($route, "onboarding_tip_dismiss", "clinic", ["session:self"], ["onboarding_tip:edit"]);
        }

        // Clinic onboarding and settings.
        $add("onboarding", self::DEFAULT_ACTION, "clinic", ["settings:edit"], ["clinic:edit", "permissions:seed"]);
        $add("settings", ["profile", "sectors", "visual"], "clinic", ["settings:edit"], ["clinic:edit"]);
        $add("settings", "subscription_claim", "clinic", ["settings:edit"], ["subscription:claim"], [], "subscription_claim");

        // Patients and clinical record.
        $add("patients", self::DEFAULT_ACTION, "clinic", ["patients:add"], ["patients:add"]);
        $add("patient", ["save_legal_guardian", "update_patient_contact", "update_patient", "create_patient_tab", "update_care"], "clinic", ["patients:edit"], ["patients:edit"]);
        $add("patient", ["delete_legal_guardian", "delete_patient", "delete_care"], "clinic", ["patients:delete"], ["patients:delete"]);
        $add("patient", "issue_patient_document", "clinic", ["patients:view", "documents:add"], ["documents:add"]);
        $add("patient", "patient_revenue_receive", "clinic", ["patients:view", "financial:edit"], ["financial:receipt"], [], "financial_operational", "financial:edit");
        $add("patient", "finish_active_appointment", "clinic", ["patients:view", "appointments:edit"], ["appointments:transition"], ["tasks:edit"]);
        $add("patient", [self::DEFAULT_ACTION, "record"], "clinic", ["patients:edit"], ["care:add"], ["appointments:transition", "tasks:edit"]);

        // Leads.
        $add("leads", [self::DEFAULT_ACTION, "save"], "clinic", ["leads:add"], ["leads:add"]);
        $add("leads", "update", "clinic", ["leads:edit"], ["leads:edit"]);
        $add("leads", "convert", "clinic", ["leads:edit", "patients:add"], ["leads:convert", "patients:add"]);
        $add("leads", "archive_lead", "clinic", ["leads:edit"], ["leads:archive"]);

        // Appointments, agenda notes and blocks. Workflow side effects remain delegated.
        $add("appointments", [self::DEFAULT_ACTION, "create"], "clinic", ["appointments:add"], ["appointments:add"], ["financial:sync", "tasks:add"]);
        $add("appointments", ["agenda_note", "block"], "clinic", ["appointments:add"], ["agenda:add"]);
        $add("appointments", ["agenda_note_delete", "delete_appointment", "delete_block", "unblock", "cancel"], "clinic", ["appointments:delete"], ["agenda:delete"], ["financial:sync", "tasks:edit"]);
        $add("appointments", ["edit", "update_block", "update_appointment", "confirm", "arrived", "no_show", "start_prepare", "finish_prepare", "start_consultation", "finish_consultation", "finish_checkout"], "clinic", ["appointments:edit"], ["appointments:transition"], ["tasks:edit", "financial:sync"]);

        // Documents.
        $add("documents", "save_template", "clinic", [], ["documents:template"], [], "matrix", "documents:edit");
        $add("documents", ["approve_template", "reject_template", "save_document", "confirm_document"], "clinic", ["documents:edit"], ["documents:edit"]);
        $add("documents", "create_document", "clinic", ["documents:add"], ["documents:add"]);

        // Procedures.
        $add("procedures", "save", "clinic", [], ["procedures:write"], [], "matrix", "procedures:edit");
        $add("procedures", "toggle", "clinic", ["procedures:edit"], ["procedures:edit"]);

        // Tasks and notices.
        $add("tasks", [self::DEFAULT_ACTION, "create"], "clinic", ["tasks:add"], ["tasks:add"]);
        $add("tasks", ["start", "release", "done", "comment", "comment_edit", "comment_delete"], "clinic", ["tasks:edit"], ["tasks:edit"]);
        $add("notices", [self::DEFAULT_ACTION, "create"], "clinic", ["notices:add"], ["notices:add"]);
        $add("notices", ["support_message", "ack", "hide", "unhide"], "clinic", ["notices:view"], ["notices:self_state"], [], "notice_support");

        // Collaborators and permission matrix.
        $add("users", [self::DEFAULT_ACTION, "save"], "clinic", ["users:add"], ["users:add"]);
        $add("users", "deactivate", "clinic", ["users:delete"], ["users:deactivate"]);
        $add("user", [self::DEFAULT_ACTION, "update"], "clinic", ["users:edit"], ["users:edit"]);
        $add("user", "deactivate", "clinic", ["users:delete"], ["users:deactivate"]);
        $add("permissions", self::DEFAULT_ACTION, "clinic", ["permissions:edit"], ["permissions:edit"]);

        // Maestro.
        $add("maestro", [self::DEFAULT_ACTION, "save_rule"], "clinic", ["maestro:add"], ["maestro:add"], [], "manager_only");
        $add("maestro", "toggle_rule", "clinic", ["maestro:edit"], ["maestro:edit"], [], "manager_only");
        $add("maestro", "delete_rule", "clinic", ["maestro:delete"], ["maestro:delete"], [], "manager_only");

        // Financial cashier actions are exact and do not grant general financial administration.
        $add("financial", ["cash_open", "cash_keep_closed", "cash_receipt", "cash_payment", "cash_close"], "clinic", ["financial:edit"], ["financial:cashier"], [], "financial_operational");

        // Financial management actions use the exact tokens processed by financial_admin_page().
        $financialAdd = ["drawer_create", "bank_account"];
        $financialEdit = [
            "drawer_rename",
            "drawer_assign",
            "drawer_unassign",
            "drawer_deactivate",
            "drawer_schedule_unlock",
            "goal",
            "review_close",
            "review_opening",
            "daily_consolidate",
            "admin_receive",
            "admin_payment",
            "admin_transfer",
            "safe_payment",
            "safe_receipt",
            "deposit_bank",
        ];
        $add("financial", $financialAdd, "clinic", ["financial:add"], ["financial:add"]);
        $add("financial", $financialEdit, "clinic", ["financial:edit"], ["financial:edit"]);
        $add("creditors", [self::DEFAULT_ACTION, "creditor_save", "creditor_deactivate"], "clinic", ["financial:edit"], ["financial:counterparty"]);

        // Explicit global administration contracts use only tokens processed by their handlers.
        $globalActions = [
            "admin_painel" => ["goal", "confirm_subscription_payment", "reject_subscription_payment"],
            "admin_clinics" => ["activate_subscription", "billing", "deactivate_subscription", "default_billing", "toggle"],
            "admin_global_notices" => ["create", "toggle"],
            "admin_alerts" => ["create", "read"],
            "admin_maintenance" => ["save_settings", "regenerate_footer_seq_alphabet", "save_maintenance"],
            "admin_deleted" => ["restore_patient", "restore_care"],
        ];
        foreach ($globalActions as $route => $actions) {
            $add($route, $actions, "global", ["admin:*"], ["admin:write"], [], "global_admin");
        }

        return $catalog;
    }

    private static function conditionalRequired(array $definition, array $post): array
    {
        $required = array_values(array_unique(array_map("strval", $definition["required"] ?? [])));
        $route = (string) ($definition["route"] ?? "");
        $action = (string) ($definition["action"] ?? "");

        if ($route === "documents" && $action === "save_template") {
            $required[] = (int) ($post["id"] ?? 0) > 0 ? "documents:edit" : "documents:add";
        }
        if ($route === "procedures" && $action === "save") {
            $required[] = (int) ($post["id"] ?? 0) > 0 ? "procedures:edit" : "procedures:add";
        }

        return array_values(array_unique($required));
    }

    public static function logicSelfTest(): array
    {
        $cases = [];
        $catalog = self::catalog();
        $cases["catalog_nonempty"] = $catalog !== [];
        $cases["public_login"] = (self::resolve("login", [])["scope"] ?? "") === "public";
        $cases["unknown_denied_by_absence"] = self::resolve("patient", ["act" => "not_real"]) === null;
        $cases["patient_document_composed"] = (self::resolve("patient", ["act" => "issue_patient_document"])["required"] ?? []) === ["patients:view", "documents:add"];
        $cases["patient_financial_composed"] = (self::resolve("patient", ["act" => "patient_revenue_receive"])["required"] ?? []) === ["patients:view", "financial:edit"];
        $cases["lead_conversion_composed"] = (self::resolve("leads", ["act" => "convert"])["required"] ?? []) === ["leads:edit", "patients:add"];
        $cases["template_create_conditional"] = (self::resolve("documents", ["act" => "save_template", "id" => 0])["required"] ?? []) === ["documents:add"];
        $cases["template_edit_conditional"] = (self::resolve("documents", ["act" => "save_template", "id" => 5])["required"] ?? []) === ["documents:edit"];
        $cases["no_duplicate_contracts"] = true;
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
        ];
    }
}
