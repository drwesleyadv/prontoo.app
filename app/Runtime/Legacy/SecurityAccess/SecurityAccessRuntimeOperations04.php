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

final class SecurityAccessRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function ctx(): array
    
    {
    
        static $c = null;
        if ($c !== null) {
            return $c;
        }
        $uid = (int) ($_SESSION["uid"] ?? 0);
        if ($uid <= 0) {
            return $c = [];
        }
    
        security_session_generation_enforce($uid);
    
        $scopeHint = (string) ($_SESSION["scope"] ?? "global");
        $clinicHint = (int) ($_SESSION["clinic_id"] ?? 0);
        $ucHint = (int) ($_SESSION["uc_id"] ?? 0);
        $roleHint = (string) ($_SESSION["role_code"] ?? "");
        if (
            $scopeHint === "global" &&
            !security_global_scope_verified($uid)
        ) {
            secure_session_destroy();
            if (!headers_sent()) {
                header(
                    "Location: " . href("login", ["relogin" => "1"]),
                );
            }
            exit();
        }
        if (
            function_exists("server_json_cache_context_key") &&
            function_exists("server_json_cache_get")
        ) {
            $cacheKey = server_json_cache_context_key(
                $uid,
                $scopeHint,
                $clinicHint,
                $ucHint,
                $roleHint,
            );
            $cached = server_json_cache_get(
                "context",
                $cacheKey,
                server_json_cache_ttl("context"),
            );
            if (
                is_array($cached) &&
                !empty($cached["scope"]) &&
                isset($cached["user"]["id"]) &&
                (int) $cached["user"]["id"] === $uid
            ) {
                server_json_cache_apply_context_session($cached);
                return $c = $cached;
            }
        }
    
        $user = one(
            "SELECT id,person_id,name,email,password_hash,is_global_admin,active,failed_login_count,locked_until,last_login_at,created_at,updated_at FROM pi_users WHERE id=? AND active=1",
            [$uid],
        );
        if (!$user) {
            return $c = [];
        }
        $person =
            one("SELECT cpf,birth_date FROM pi_persons WHERE id=?", [
                (int) $user["person_id"],
            ]) ?:
            [];
        $user += [
            "cpf" => $person["cpf"] ?? "",
            "birth_date" => $person["birth_date"] ?? null,
        ];
    
        $storeContext = function (array $ctx) use (
            $uid,
            $scopeHint,
            $clinicHint,
            $ucHint,
            $roleHint,
        ): array {
    
            $ctx = function_exists("server_json_cache_sanitize_context")
                ? server_json_cache_sanitize_context($ctx)
                : $ctx;
            if (
                function_exists("server_json_cache_set") &&
                function_exists("server_json_cache_context_key")
            ) {
                $key = server_json_cache_context_key(
                    $uid,
                    $scopeHint,
                    $clinicHint,
                    $ucHint,
                    $roleHint,
                );
                server_json_cache_set(
                    "context",
                    $key,
                    $ctx,
                    server_json_cache_ttl("context"),
                    ["user:" . $uid, "context"],
                );
            }
            return $ctx;
        };
    
        if (
            (int) $user["is_global_admin"] === 1 &&
            ($_SESSION["scope"] ?? "global") === "global" &&
            security_global_scope_verified($uid)
        ) {
            $_SESSION["scope"] = "global";
            unset(
                $_SESSION["clinic_id"],
                $_SESSION["role_code"],
                $_SESSION["uc_id"],
            );
            $tz = app_global_admin_timezone((int) $user["id"]);
            $ctx = [
                "scope" => "global",
                "user" => $user,
                "role" => "administrador",
                "effective_roles" => ["administrador"],
                "clinic_id" => null,
                "clinic" => "Painel do Desenvolvedor",
                "timezone" => $tz,
                "roles" => [],
                "allowed" => array_flip(array_keys(PRONTOO_ADMIN_ACTIONS)),
            ];
            app_apply_request_timezone($tz);
            return $c = $storeContext($ctx);
        }
    
        if (function_exists("single_active_role_cleanup_for_user")) {
            single_active_role_cleanup_for_user($uid, null);
        }
        if (function_exists("clinic_enable_roles_from_active_user_links")) {
            clinic_enable_roles_from_active_user_links($uid);
        }
        $links = q(
            "SELECT id,user_id,clinic_id,role_code,is_owner,active,created_at FROM pi_user_roles WHERE user_id=? AND active=1 ORDER BY clinic_id ASC, is_owner DESC, FIELD(role_code,'gerente','medico','assistente','recepcionista'), id ASC LIMIT 80",
            [$uid],
        )->fetchAll();
        if (!$links) {
            if (
                (int) $user["is_global_admin"] === 1 &&
                security_global_scope_verified($uid)
            ) {
                $_SESSION["scope"] = "global";
                unset(
                    $_SESSION["clinic_id"],
                    $_SESSION["role_code"],
                    $_SESSION["uc_id"],
                );
                $tz = app_global_admin_timezone((int) $user["id"]);
                $ctx = [
                    "scope" => "global",
                    "user" => $user,
                    "role" => "administrador",
                    "effective_roles" => ["administrador"],
                    "clinic_id" => null,
                    "clinic" => "Painel do Desenvolvedor",
                    "timezone" => $tz,
                    "roles" => [],
                    "allowed" => array_flip(array_keys(PRONTOO_ADMIN_ACTIONS)),
                ];
                app_apply_request_timezone($tz);
                return $c = $storeContext($ctx);
            }
            return $c = [];
        }
        $clinicIds = int_ids($links, "clinic_id");
        $clinics = fetch_map(
            "pi_clinics",
            $clinicIds,
            "id,display_name,timezone,address_state,address_city,responsible_profession,clinic_icon,accent_color,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents,active",
        );
        $roles = [];
        foreach ($links as $ln) {
            $cl = $clinics[(int) $ln["clinic_id"]] ?? null;
            if (!$cl || (int) ($cl["active"] ?? 0) !== 1) {
                continue;
            }
            $ln["clinic_name"] = $cl["display_name"];
            $ln["clinic_timezone"] = $cl["timezone"];
            $ln["address_state"] = $cl["address_state"];
            $ln["address_city"] = $cl["address_city"];
            $ln["responsible_profession"] =
                $cl["responsible_profession"] ?? "Médico(a)";
            $ln["clinic_icon"] = $cl["clinic_icon"] ?? "medical_services";
            $ln["accent_color"] = $cl["accent_color"] ?? "#334155";
            $ln["clinic_created_at"] = $cl["created_at"];
            $ln["trial_started_at"] = $cl["trial_started_at"];
            $ln["trial_ends_at"] = $cl["trial_ends_at"];
            $ln["subscription_status"] = $cl["subscription_status"];
            $ln["paid_until"] = $cl["paid_until"];
            $ln["monthly_price_cents"] = $cl["monthly_price_cents"];
            $roles[] = $ln;
        }
        if (!$roles) {
            return $c = [];
        }
        $wantClinic = (int) ($_SESSION["clinic_id"] ?? 0);
        if ($wantClinic <= 0 && (int) ($_SESSION["uc_id"] ?? 0) > 0) {
            foreach ($roles as $r) {
                if ((int) $r["id"] === (int) $_SESSION["uc_id"]) {
                    $wantClinic = (int) $r["clinic_id"];
                    break;
                }
            }
        }
        if ($wantClinic <= 0) {
            $wantClinic = (int) $roles[0]["clinic_id"];
        }
        $clinicRoles = array_values(
            array_filter(
                $roles,
                static  fn($r) => (int) $r["clinic_id"] === $wantClinic,
            ),
        );
        if (!$clinicRoles) {
            $wantClinic = (int) $roles[0]["clinic_id"];
            $clinicRoles = array_values(
                array_filter(
                    $roles,
                    static  fn($r) => (int) $r["clinic_id"] === $wantClinic,
                ),
            );
        }
        $chosen = $clinicRoles[0];
        foreach ($clinicRoles as $r) {
            if (
                (int) ($_SESSION["uc_id"] ?? 0) > 0 &&
                (int) $r["id"] === (int) $_SESSION["uc_id"]
            ) {
                $chosen = $r;
                break;
            }
            if ((int) $r["is_owner"] === 1) {
                $chosen = $r;
            }
        }
        $primary = (string) $chosen["role_code"];
        $effectiveRoles = [$primary];
        $_SESSION["uc_id"] = (int) $chosen["id"];
        $_SESSION["scope"] = "clinic";
        $_SESSION["clinic_id"] = $wantClinic;
        $_SESSION["role_code"] = $primary;
        $_SESSION["effective_roles"] = $effectiveRoles;
        app_apply_request_timezone(
            valid_timezone((string) ($chosen["clinic_timezone"] ?? "America/Cuiaba")),
        );
        if (function_exists("maestro_runtime_upgrade")) {
            maestro_runtime_upgrade();
        }
        $allowed = effective_allowed_modules_for_roles(
            $wantClinic,
            $effectiveRoles,
        );
        $billing = billing_state([
            "id" => $wantClinic,
            "clinic_id" => $wantClinic,
            "timezone" => $chosen["clinic_timezone"] ?? "America/Cuiaba",
            "created_at" => $chosen["clinic_created_at"] ?? null,
            "trial_started_at" => $chosen["trial_started_at"] ?? null,
            "trial_ends_at" => $chosen["trial_ends_at"] ?? null,
            "subscription_status" => $chosen["subscription_status"] ?? "trial",
            "paid_until" => $chosen["paid_until"] ?? null,
            "monthly_price_cents" =>
                $chosen["monthly_price_cents"] ?? default_monthly_price_cents(),
        ]);
        $ctx = [
            "scope" => "clinic",
            "user" => $user,
            "role" => $primary,
            "effective_roles" => $effectiveRoles,
            "clinic_id" => $wantClinic,
            "clinic" => $chosen["clinic_name"],
            "timezone" => valid_timezone(
                (string) ($chosen["clinic_timezone"] ?? "America/Cuiaba"),
            ),
            "responsible_profession" =>
                (string) ($chosen["responsible_profession"] ?? "Médico(a)"),
            "clinic_icon" =>
                (string) ($chosen["clinic_icon"] ?? "medical_services"),
            "accent_color" => (string) ($chosen["accent_color"] ?? "#334155"),
            "roles" => $roles,
            "uc_id" => (int) $chosen["id"],
            "clinic_roles" => [$chosen],
            "allowed" => array_flip($allowed),
            "billing" => $billing,
        ];
        app_apply_request_timezone((string) $ctx["timezone"]);
        return $c = $storeContext($ctx);
    
    }

    public static function need_login(): array
    
    {
    
        $c = ctx();
        if (!$c) {
            redirect("login");
        }
        return $c;
    
    }

    public static function can(string $action): bool
    
    {
    
        $c = ctx();
        if (!$c) {
            return false;
        }
        if ($c["scope"] === "global") {
            return str_starts_with($action, "admin_");
        }
        if (($c["scope"] ?? "") === "clinic" && $action === "creditors") {
            return function_exists("has_effective_role") &&
                has_effective_role($c, "gerente") &&
                isset(($c["allowed"] ?? [])["patients"]);
        }
        $normallyAllowed = isset(($c["allowed"] ?? [])[$action]);
        if (!empty(($c["billing"] ?? [])["read_only"])) {
            if ($action === "settings") {
                $isManager = function_exists("has_effective_role")
                    ? has_effective_role($c, "gerente")
                    : (string) ($c["role"] ?? "") === "gerente";
                return $normallyAllowed && $isManager;
            }
        }
        return $normallyAllowed;
    
    }

    public static function secure_relogin_after_forbidden_action(string $action): void
    
    {
    
        $uid = $_SESSION["uid"] ?? null;
        try {
            audit("acesso_negado_sessao_encerrada", "rota", $action, [
                "janela" => $action,
                "audit_body" =>
                    "Sessão encerrada com segurança após divergência entre a rota solicitada e as permissões do cargo ativo.",
            ]);
        } catch (Throwable $e) {
            error_log("[Prontoo access guard audit] " . $e->getMessage());
        }
        try {
            if ((int) $uid > 0) {
                user_auth_generation_rotate((int) $uid);
                security_retire_persistent_devices_for_user((int) $uid);
            }
        } catch (Throwable $e) {
            error_log("[Prontoo access guard revocation] " . $e->getMessage());
        }
        secure_session_destroy();
        if (!headers_sent()) {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Location: " . href("login", ["relogin" => "1"]));
        }
        exit();
    
    }

    public static function require_can(string $action): array
    
    {
    
        $c = need_login();
        if (!can($action)) {
            secure_relogin_after_forbidden_action($action);
        }
        return $c;
    
    }
}
