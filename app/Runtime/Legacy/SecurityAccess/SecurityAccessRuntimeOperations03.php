<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\SecurityAccess;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class SecurityAccessRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function device_session_revoke_current(): void
    
    {
    
        $uid = (int) ($_SESSION["uid"] ?? 0);
        security_retire_persistent_devices_for_user($uid);
    
    }

    public static function scope_violation_evidence_payload(string $sql, string $detail): string
    
    {
    
        $operation = preg_match('/^\s*(UPDATE|DELETE|INSERT|REPLACE)\b/i', $sql, $m)
            ? strtoupper((string) $m[1])
            : "ACCESS";
        $table = "";
        $tablePattern = match ($operation) {
            "UPDATE" => '/^\s*UPDATE\s+`?([a-z0-9_]+)`?/i',
            "INSERT", "REPLACE" => '/^\s*(?:INSERT|REPLACE)\s+(?:(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE)\s+)*INTO\s+`?([a-z0-9_]+)`?/i',
            "DELETE" => '/^\s*DELETE\s+FROM\s+`?([a-z0-9_]+)`?/i',
            default => '',
        };
        if ($tablePattern !== "" && preg_match($tablePattern, $sql, $tableMatch)) {
            $table = preg_replace('/[^a-z0-9_]/i', "", (string) ($tableMatch[1] ?? "")) ?: "";
        }
        $action = preg_replace(
            '/[^a-z0-9_.:\-]/i',
            "_",
            mb_trim((string) ($_POST["act"] ?? "")),
        ) ?:
            "";
        $method = preg_replace(
            '/[^A-Z]/',
            "",
            strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "")),
        ) ?:
            "";
        $payload = [
            "v" => 2,
            "reason" => scope_violation_safe_reason($detail),
            "method" => mb_substr($method, 0, 8),
            "action" => mb_substr($action, 0, 48),
            "operation" => mb_substr($operation, 0, 10),
            "table" => mb_substr($table, 0, 64),
            "sql_shape" => scope_violation_sql_shape($sql),
        ];
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return scope_violation_safe_reason($detail);
        }
        if (strlen($encoded) > 580) {
            $payload["sql_shape"] = mb_substr((string) $payload["sql_shape"], 0, 100);
            $payload["reason"] = mb_substr((string) $payload["reason"], 0, 100);
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return is_string($encoded) ? $encoded : scope_violation_safe_reason($detail);
    
    }

    public static function record_scope_violation(
        string $key,
        string $sql,
        string $detail = "",
    ): void 
    {
    
        $cid = scope_guard_active_clinic_id();
        if ($cid <= 0) {
            return;
        }
        $fingerprint = sql_fingerprint($sql);
        $requestRoute = route();
        $dedupKey = hash(
            "sha256",
            $cid . "|" . $key . "|" . $fingerprint . "|" . $requestRoute . "|" . (string) ($_POST["act"] ?? ""),
        );
        static $recorded = [];
        if (isset($recorded[$dedupKey])) {
            return;
        }
        $recorded[$dedupKey] = true;
        try {
            $ins =
                "INSERT INTO pi_scope_violations (clinic_id,user_id,role_code,route,violation_key,sql_fingerprint,details,created_at) VALUES (?,?,?,?,?,?,?,NOW())";
            $params = [
                $cid,
                $_SESSION["uid"] ?? null,
                session_clinic_role_code(),
                $requestRoute,
                $key,
                $fingerprint,
                scope_violation_evidence_payload($sql, $detail),
            ];
            if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                [
                    $ins,
                    $params,
                ] = \Prontoo\Infrastructure\Integrity\PiIntegrity::prepareRuntimeQuery(
                    $ins,
                    $params,
                );
            }
            $st = pdo()->prepare($ins);
            $st->execute($params);
        } catch (Throwable $e) {
            error_log(
                "[Prontoo scope violation] " .
                    $key .
                    " | " .
                    $fingerprint .
                    " | " .
                    $e->getMessage(),
            );
        }
    
    }

    public static function sql_write_scope_guard(string $sql, array $params = []): void
    
    {
    
        if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"])) {
            return;
        }
        $kind = preg_match("/^\s*(UPDATE|DELETE|INSERT|REPLACE)\s+/i", $sql, $m)
            ? strtoupper($m[1])
            : "";
        if ($kind === "") {
            return;
        }
        $cacheable = empty($params) && !preg_match("/\?/", $sql);
        $key = $cacheable
            ? hash(
                "sha256",
                $kind .
                    "|" .
                    preg_replace("/\s+/", " ", trim($sql)) .
                    "|" .
                    (string) ($_SESSION["scope"] ?? "") .
                    "|" .
                    (string) ($_SESSION["clinic_id"] ?? "") .
                    "|" .
                    scope_guard_expected_clinic_id() .
                    "|" .
                    (string) ($_POST["act"] ?? "") .
                    "|" .
                    route(),
            )
            : "";
        static $ok = [];
        if ($key !== "" && isset($ok[$key])) {
            return;
        }
        \Prontoo\Core\Database\SqlScopeGuard::guard(
            $sql,
            $params,
            \Prontoo\Runtime\Tenant\SessionTenantContext::mutationContext(),
        );
        if ($key !== "" && count($ok) < 256) {
            $ok[$key] = 1;
        }
    
    }

    public static function require_same_clinic_entity(
        int $cid,
        string $table,
        int $id,
        string $cols = "id",
    ): array 
    {
    
        $table = allowed_db_table($table);
        if (!tenant_table_is_scoped($table)) {
            throw new RuntimeException("Tabela sem escopo de consultório.");
        }
        $cols = safe_db_columns($cols);
        if ($cid <= 0 || $id <= 0) {
            throw new ProntooHttpError(
                403,
                "Registro não pertence ao consultório ativo.",
            );
        }
        $row = one("SELECT $cols FROM $table WHERE id=? AND clinic_id=? LIMIT 1", [
            $id,
            $cid,
        ]);
        if (!$row) {
            record_scope_violation(
                "entity_outside_clinic",
                "SELECT $cols FROM $table WHERE id=? AND clinic_id=?",
                "Registro " .
                    $table .
                    "#" .
                    $id .
                    " não encontrado no consultório ativo.",
            );
            throw new ProntooHttpError(
                403,
                "Registro não pertence ao consultório ativo.",
            );
        }
        return $row;
    
    }

    public static function role_actions(string $role): array
    
    {
    
        $all = actions();
        if (isset($all["patients"])) {
            $all["patients"]["label"] = "Pessoas";
            $all["patients"]["icon"] = prontoo_icon_for("people");
        }
        if ($role === "recepcionista" && isset($all["financial"])) {
            $all["financial"]["label"] = "Caixa";
            $all["financial"]["icon"] = function_exists("reception_cash_state_icon")
                ? reception_cash_state_icon()
                : "lock_clock";
        }
        $orders = [
            "recepcionista" => [
                "appointments",
                "patients",
                "documents",
                "tasks",
                "financial",
                "audit",
            ],
            "assistente" => [
                "appointments",
                "patients",
                "documents",
                "tasks",
                "audit",
            ],
            "medico" => ["appointments", "patients", "documents", "tasks", "audit"],
            "gerente" => [
                "appointments",
                "patients",
                "documents",
                "tasks",
                "financial",
                "settings",
                "maestro",
                "audit",
            ],
        ];
        $order = $orders[$role] ?? array_keys($all);
        if (!in_array("audit", $order, true)) {
            $order[] = "audit";
        }
        $out = [];
        foreach ($order as $key) {
            if (
                isset($all[$key]) &&
                $key !== "notices" &&
                ($key === "audit" || in_array($role, $all[$key]["roles"], true))
            ) {
                $out[$key] = $all[$key];
            }
        }
        return $out;
    
    }

    public static function role_actions_effective(array $roles): array
    
    {
    
        $roles = array_values(
            array_unique(array_filter(array_map("strval", $roles))),
        );
        if (!$roles) {
            return [];
        }
        $order = [
            "appointments",
            "painel",
            "leads",
            "patients",
            "documents",
            "tasks",
            "financial",
            "settings",
            "procedures",
            "users",
            "permissions",
            "operations",
            "maestro",
            "audit",
        ];
        $out = [];
        foreach ($roles as $role) {
            foreach (role_actions($role) as $key => $a) {
                if ($key === "notices") {
                    continue;
                }
                if ($key === "financial" && $role === "recepcionista") {
                    $a["label"] = "Caixa";
                    $a["icon"] = function_exists("reception_cash_state_icon")
                        ? reception_cash_state_icon()
                        : prontoo_icon_for("cash", "lock_clock");
                }
                $out[$key] = $a;
            }
        }
        $sorted = [];
        foreach ($order as $key) {
            if (isset($out[$key])) {
                $sorted[$key] = $out[$key];
            }
        }
        foreach ($out as $key => $a) {
            if (!isset($sorted[$key])) {
                $sorted[$key] = $a;
            }
        }
        return $sorted;
    
    }

    public static function effective_allowed_modules_for_roles(int $cid, array $roles): array
    
    {
    
        $roles = array_values(
            array_unique(array_filter(array_map("strval", $roles))),
        );
        if ($cid <= 0 || !$roles) {
            return [];
        }
        if (in_array("gerente", $roles, true)) {
            return array_keys(actions());
        }
        $ph = implode(",", array_fill(0, count($roles), "?"));
        try {
            $allowed = q(
                "SELECT DISTINCT action_key FROM pi_permissions WHERE clinic_id=? AND role_code IN ($ph) AND allowed=1",
                array_merge([$cid], $roles),
            )->fetchAll(PDO::FETCH_COLUMN);
            return array_values(array_unique(array_map("strval", $allowed ?: [])));
        } catch (Throwable $e) {
            error_log("[Prontoo effective permissions] " . $e->getMessage());
            return [];
        }
    
    }

    public static function seed_permissions(int $clinicId): void
    
    {
    
        with_read_only_guard_disabled(function () use ($clinicId): void {
    
            foreach (default_permissions() as $role => $keys) {
                foreach (actions() as $key => $a) {
                    q(
                        "INSERT INTO pi_permissions (clinic_id, role_code, action_key, allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=allowed",
                        [
                            $clinicId,
                            $role,
                            $key,
                            in_array($key, $keys, true) ? 1 : 0,
                        ],
                    );
                }
            }
        });
    
    }

    public static function secret_key(): string
    
    {
        
        static $secret = null;
        if (is_string($secret) && $secret !== "") {
            return $secret;
        }
        $secret = (string) (val(
            "SELECT meta_value FROM pi_meta WHERE meta_key='app_secret'",
        ) ??
            (cfg()["secret"] ?? "prontoo"));
        return $secret;
    
    }

    public static function billing_state(array $clinic): array
    
    {
    
        $started = $clinic["trial_started_at"] ?: $clinic["created_at"] ?? now();
        $startedTs = app_storage_timestamp($started);
        if ($startedTs <= 0) {
            $startedTs = time();
        }
        $trialEnd =
            $clinic["trial_ends_at"] ?:
            (string) ($startedTs + default_trial_days() * 86400);
        $paidUntil = $clinic["paid_until"] ?? null;
        $status = (string) ($clinic["subscription_status"] ?? "trial");
        $price =
            (int) ($clinic["monthly_price_cents"] ??
                default_monthly_price_cents()) ?:
            default_monthly_price_cents();
        $trialEndTs = app_storage_timestamp($trialEnd, true);
        $paidUntilTs = $paidUntil
            ? app_date_only_end_timestamp(
                $paidUntil,
                (int) ($clinic["id"] ?? ($clinic["clinic_id"] ?? 0)),
                $clinic,
            )
            : 0;
        $trialActive = $trialEndTs >= time();
        $paidActive = $paidUntilTs >= time();
        $exempt = $status === "exempt";
        $activeWithoutDue =
            $status === "active" && mb_trim((string) $paidUntil) === "";
        $readOnly =
            $status === "read_only" ||
            !($trialActive || $paidActive || $exempt || $activeWithoutDue);
        if ($readOnly) {
            $label = "Somente leitura";
        } elseif ($exempt) {
            $label = "Isento";
        } else {
            $label = "Ativo";
        }
        $days = $trialActive
            ? max(0, (int) ceil(($trialEndTs - time()) / 86400))
            : 0;
        return [
            "status" => $status,
            "label" => $label,
            "trial_started_at" => $started,
            "trial_ends_at" => $trialEnd,
            "paid_until" => $paidUntil,
            "price_cents" => $price,
            "trial_active" => $trialActive,
            "paid_active" => $paidActive,
            "exempt" => $exempt,
            "read_only" => $readOnly,
            "trial_days_left" => $days,
        ];
    
    }

    public static function billing_notice(array $c): string
    
    {
    
        if (($c["scope"] ?? "") !== "clinic") {
            return "";
        }
        $b = $c["billing"] ?? [];
        if (!$b || empty($b["read_only"])) {
            return "";
        }
        $isAdmin = function_exists("has_effective_role")
            ? has_effective_role($c, "gerente")
            : (string) ($c["role"] ?? "") === "gerente";
        $clinicId = (int) ($c["clinic_id"] ?? 0);
        $adminLabel = function_exists("role_label_for")
            ? role_label_for("gerente", $clinicId)
            : PRONTOO_ROLES["gerente"] ?? "Administrador";
        $adminLabel = mb_trim((string) $adminLabel) ?: "Administrador";
        if (!$isAdmin) {
            return '<section class="billing-banner billing-readonly-banner ds-readonly-banner billing-user-readonly-banner" role="status" aria-label="Modo somente leitura"><span class="billing-readonly-icon">' .
                icon("lock") .
                '</span><span class="billing-readonly-copy"><span class="eyebrow">Modo somente leitura</span><b>Alterações bloqueadas</b><small>Aguarde até que ' .
                e($adminLabel) .
                " desabilite este modo.</small></span></section>";
        }
        $price = isset($b["price_cents"]) ? money_br((int) $b["price_cents"]) : "";
        $link =
            '<a class="primary small billing-link" href="' .
            e(href("settings", ["tab" => "assinatura"])) .
            '">' .
            icon("credit_card") .
            "<span>Regularizar</span></a>";
        $support =
            '<a class="ghost small billing-support-link" href="' .
            e(href("notices")) .
            '">' .
            icon("support_agent") .
            "<span>Suporte</span></a>";
        $detail =
            $price !== ""
                ? "Pagamento pendente: " .
                    $price .
                    ". Alterações temporariamente bloqueadas até a regularização."
                : "Alterações temporariamente bloqueadas até a regularização.";
        return '<section class="billing-banner billing-readonly-banner ds-readonly-banner billing-admin-readonly-banner" role="status" aria-label="Assinatura pendente"><span class="billing-readonly-icon">' .
            icon("credit_card") .
            '</span><span class="billing-readonly-copy"><span class="eyebrow">Assinatura</span><b>Pagamento pendente</b><small>' .
            e($detail) .
            '</small></span><nav class="billing-readonly-actions" aria-label="Ações da assinatura">' .
            $link .
            $support .
            "</nav></section>";
    
    }

    public static function enforce_read_only(array $c, string $route): void
    
    {
    
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
            return;
        }
        if (($c["scope"] ?? "") !== "clinic") {
            return;
        }
        if (read_only_post_allowed($route)) {
            return;
        }
        $b = $c["billing"] ?? [];
        if (empty($b["read_only"])) {
            return;
        }
        audit(
            "somente_leitura_bloqueio",
            "assinatura",
            (string) ($c["clinic_id"] ?? ""),
            ["rota" => $route, "acao" => (string) ($_POST["act"] ?? "")],
        );
        flash("Assinatura pendente: regularize para alterar dados.", "bad");
        redirect($route);
    
    }
}
