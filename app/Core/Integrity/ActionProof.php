<?php
declare(strict_types=1);
namespace Prontoo\Core\Integrity;
final class ActionProof
{
    private const POLICY_VERSION = "action-proof-v1";
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
        "counterparty_lookup" => "financial",
        "counterparty_suggest" => "financial",
        "procedures" => "procedures",
    ];
    private function __construct() {}
    public static function enforce(
        string $route,
        string $method,
        array $post,
        array $ctx,
    ): void {
        $route = self::clean($route, "login");
        $method = strtoupper($method);
        if ($method !== "POST") {
            return;
        }
        if (in_array($route, self::PUBLIC_POST_ROUTES, true)) {
            return;
        }
        if (!$ctx) {
            self::deny(
                "identidade_ausente",
                $route,
                null,
                "view",
                "Sessão autenticada ausente.",
            );
        }
        $scope = (string) ($ctx["scope"] ?? "");
        if ($scope === "global") {
            if (!str_starts_with($route, "admin_")) {
                self::deny(
                    "escopo_global_incompativel",
                    $route,
                    null,
                    "view",
                    "Desenvolvedor tentou ação fora do escopo global.",
                );
            }
            self::writeProof(
                $route,
                null,
                "admin",
                $ctx,
                "global",
                true,
                "aprovada",
            );
            return;
        }
        if (
            $scope !== "clinic" ||
            (int) ($ctx["clinic_id"] ?? 0) <= 0 ||
            (int) ($ctx["user"]["id"] ?? 0) <= 0
        ) {
            self::deny(
                "escopo_clinica_invalido",
                $route,
                null,
                "view",
                "Escopo de consultório não demonstrável.",
            );
        }
        $module = self::moduleForRoute($route);
        if ($module === null) {
            return;
        }
        $action = (string) ($post["act"] ?? "");
        if (self::isCashierFinancialAction($route, $action, $ctx)) {
            self::writeProof(
                $route,
                $module,
                "edit",
                $ctx,
                "clinic",
                true,
                "caixa_operacional_aprovado",
            );
            return;
        }
        if (self::isReadonlyNoticeSupportAction($route, $action, $ctx)) {
            self::writeProof(
                $route,
                $module,
                "view",
                $ctx,
                "clinic",
                true,
                "somente_leitura_suporte_aprovado",
            );
            return;
        }
        $operation = self::operationFor($route, $action, $post, $module);
        if (!self::allowed($ctx, $module, $operation)) {
            self::deny(
                "permissao_operacional_negada",
                $route,
                $module,
                $operation,
                "Operação incompatível com o papel atual.",
                $ctx,
            );
        }
        self::writeProof(
            $route,
            $module,
            $operation,
            $ctx,
            "clinic",
            true,
            "aprovada",
        );
    }
    private static function moduleForRoute(string $route): ?string
    {
        return self::ROUTE_MODULES[$route] ?? null;
    }
    private static function operationFor(
        string $route,
        string $action,
        array $post,
        string $module,
    ): string {
        $a = self::clean($action, "");
        $id = max(
            0,
            (int) ($post["id"] ??
                ($post["patient_id"] ??
                    ($post["task_id"] ??
                        ($post["document_id"] ??
                            ($post["template_id"] ??
                                ($post["notice_id"] ?? 0)))))),
        );
        $deleteLike = [
            "delete",
            "remove",
            "deactivate",
            "cancel",
            "discard",
            "archive",
            "restore",
        ];
        foreach ($deleteLike as $needle) {
            if (
                $a === $needle ||
                str_contains($a, $needle . "_") ||
                str_contains($a, "_" . $needle)
            ) {
                return self::supported($module, "delete") ? "delete" : "edit";
            }
        }
        $addLike = [
            "add",
            "new",
            "create",
            "store",
            "register",
            "emit",
            "issue",
        ];
        foreach ($addLike as $needle) {
            if (
                $a === $needle ||
                str_starts_with($a, $needle . "_") ||
                str_contains($a, "_" . $needle)
            ) {
                return self::supported($module, "add") ? "add" : "edit";
            }
        }
        if (
            $id <= 0 &&
            in_array(
                $module,
                [
                    "leads",
                    "appointments",
                    "patients",
                    "documents",
                    "tasks",
                    "notices",
                    "users",
                    "procedures",
                    "financial",
                ],
                true,
            )
        ) {
            return self::supported($module, "add") ? "add" : "edit";
        }
        return self::supported($module, "edit") ? "edit" : "view";
    }
    private static function isCashierFinancialAction(
        string $route,
        string $action,
        array $ctx,
    ): bool {
        $a = self::clean($action, "");
        if ($route !== "financial") {
            return false;
        }
        if ((string) ($ctx["scope"] ?? "") !== "clinic") {
            return false;
        }
        $role = (string) ($ctx["role"] ?? "");
        if ($role !== "recepcionista") {
            return false;
        }
        if (
            (int) ($ctx["clinic_id"] ?? 0) <= 0 ||
            (int) ($ctx["user"]["id"] ?? 0) <= 0
        ) {
            return false;
        }
        return in_array(
            $a,
            [
                "cash_open",
                "cash_keep_closed",
                "cash_receipt",
                "cash_payment",
                "cash_close",
            ],
            true,
        );
    }
    private static function isReadonlyNoticeSupportAction(
        string $route,
        string $action,
        array $ctx,
    ): bool {
        if ($route !== "notices") {
            return false;
        }
        if ((string) ($ctx["scope"] ?? "") !== "clinic") {
            return false;
        }
        if (empty(($ctx["billing"] ?? [])["read_only"])) {
            return false;
        }
        $a = self::clean($action, "");
        return in_array($a, ["support_message", "ack", "hide", "unhide"], true);
    }
    private static function supported(string $module, string $operation): bool
    {
        if (function_exists("permission_operation_supported")) {
            try {
                return (bool) \permission_operation_supported(
                    $module,
                    $operation,
                );
            } catch (\Throwable) {
                return false;
            }
        }
        return false;
    }
    private static function allowed(
        array $ctx,
        string $module,
        string $operation,
    ): bool {
        $cid = (int) ($ctx["clinic_id"] ?? 0);
        $role = (string) ($ctx["role"] ?? "");
        if ($cid <= 0 || $role === "") {
            return false;
        }
        if ($role === "gerente") {
            return true;
        }
        if (function_exists("permission_rules_for_role")) {
            try {
                $matrix = \permission_rules_for_role($role, $cid);
                if (
                    array_key_exists($module, $matrix) &&
                    is_array($matrix[$module]) &&
                    array_key_exists($operation, $matrix[$module])
                ) {
                    return (bool) $matrix[$module][$operation];
                }
            } catch (\Throwable $e) {
                error_log(
                    "[Prontoo action proof] falha ao ler permissões granulares do cargo ativo: " .
                        $e->getMessage(),
                );
            }
            return false;
        }
        return $operation === "view" &&
            isset(($ctx["allowed"] ?? [])[$module]);
    }
    private static function proofContext(
        string $route,
        ?string $module,
        string $operation,
        array $ctx,
        string $scope,
    ): array {
        return [
            "audit_body" => "Prova operacional aprovada.",
            "integrity_policy" => self::POLICY_VERSION,
            "route" => $route,
            "module" => $module,
            "operation" => $operation,
            "scope" => $scope,
            "role" => (string) ($ctx["role"] ?? ""),
            "active_role" => (string) ($ctx["role"] ?? ""),
            "clinic_id" => $ctx["clinic_id"] ?? null,
            "proof_hash" => hash(
                "sha256",
                implode("|", [
                    self::POLICY_VERSION,
                    $route,
                    (string) $module,
                    $operation,
                    $scope,
                    (string) ($ctx["clinic_id"] ?? ""),
                    (string) ($ctx["user"]["id"] ?? ""),
                ]),
            ),
        ];
    }
    private static function deny(
        string $reason,
        string $route,
        ?string $module,
        string $operation,
        string $detail,
        array $ctx = [],
    ): void {
        self::writeProof(
            $route,
            $module,
            $operation,
            $ctx,
            (string) ($ctx["scope"] ?? ""),
            false,
            $reason . ": " . $detail,
        );
        throw new \ProntooHttpError(
            403,
            "Esta ação não está disponível para sua credencial atual.",
        );
    }
    private static function writeProof(
        string $route,
        ?string $module,
        string $operation,
        array $ctx,
        string $scope,
        bool $allowed,
        string $reason,
    ): void {
        if (!function_exists("pdo")) {
            return;
        }
        try {
            $payload = self::proofContext(
                $route,
                $module,
                $operation,
                $ctx,
                $scope,
            );
            $payload["allowed"] = $allowed;
            $payload["reason"] = $reason;
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            if ($json === false) {
                $json = "{}";
            }
            $proofHash = hash("sha256", self::POLICY_VERSION . "|" . $json);
            $sql =
                "INSERT INTO pi_action_proofs (clinic_id,user_id,route,module_key,operation_key,scope,role_code,allowed,reason,proof_hash,policy_version,context_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
            if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                $sql = \Prontoo\Core\Integrity\PiIntegrity::rewriteSqlForRuntime(
                    $sql,
                );
            }
            $stmt = \pdo()->prepare($sql);
            $stmt->execute([
                $ctx["clinic_id"] ?? null,
                $ctx["user"]["id"] ?? ($_SESSION["uid"] ?? null),
                $route,
                $module,
                $operation,
                $scope,
                (string) ($ctx["role"] ?? ($_SESSION["role_code"] ?? "")),
                $allowed ? 1 : 0,
                mb_substr($reason, 0, 180, "UTF-8"),
                $proofHash,
                self::POLICY_VERSION,
                $json,
            ]);
        } catch (\Throwable $e) {
            error_log(
                "[Prontoo action proof] não foi possível registrar prova: " .
                    $e->getMessage(),
            );
        }
    }
    private static function clean(string $value, string $fallback): string
    {
        return preg_replace("/[^a-z0-9_\-]/i", "", $value) ?: $fallback;
    }
}
