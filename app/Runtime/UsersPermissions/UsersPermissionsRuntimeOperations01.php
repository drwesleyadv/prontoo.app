<?php
declare(strict_types=1);

namespace Prontoo\Runtime\UsersPermissions;

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

final class UsersPermissionsRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function require_user_in_clinic(int $cid, int $uid, array $roles = []): void
    
    {
    
        if ($uid <= 0) {
            throw new ProntooHttpError(
                403,
                "Colaborador não pertence ao consultório ativo.",
            );
        }
        if (!clinic_user_exists($cid, $uid, $roles)) {
            record_scope_violation(
                "user_outside_clinic",
                "clinic_user_exists",
                "Usuário " . $uid . " não vinculado ao consultório ativo.",
            );
            throw new ProntooHttpError(
                403,
                "Colaborador não pertence ao consultório ativo.",
            );
        }
    
    }

    public static function save_team_member(int $cid, array $data): ?int
    
    {
    
        $name = mb_trim((string) ($data["team_name"] ?? ""));
        $cpf = only_digits((string) ($data["team_cpf"] ?? ""));
        $birth = mb_trim((string) ($data["team_birth"] ?? ""));
        $email = mb_trim((string) ($data["team_email"] ?? ($data["email"] ?? "")));
        $pass = (string) ($data["team_password"] ?? "");
        $hasAny =
            $name !== "" ||
            $cpf !== "" ||
            $birth !== "" ||
            $email !== "" ||
            trim($pass) !== "";
        if (!$hasAny) {
            return null;
        }
        if ($name === "") {
            throw new RuntimeException(
                "Informe o nome completo do colaborador ou deixe a etapa de equipe em branco.",
            );
        }
        if ($cpf === "") {
            throw new RuntimeException(
                "Informe o CPF do colaborador ou deixe a etapa de equipe em branco.",
            );
        }
        if (!valid_cpf($cpf)) {
            throw new RuntimeException("Este CPF não existe.");
        }
        if ($birth === "" || !valid_birth_date($birth)) {
            throw new RuntimeException(
                "Informe uma data de nascimento válida para o colaborador.",
            );
        }
        $roles = selected_team_roles($data, $cid);
        $pid = upsert_person($name, $cpf, $birth);
        lock_person_user_identity($pid);
        if (function_exists("person_common_profile_update")) {
            person_common_profile_update(
                $pid,
                array_merge(person_common_profile_from_array($data, ""), [
                    "legal_type" => "cpf",
                    "legal_document" => $cpf,
                    "phone" => phone_br((string) ($data["phone"] ?? "")) ?: null,
                    "email" => $email !== "" ? $email : null,
                ]),
            );
        }
        $u = one(
            "SELECT id,name,email,password_hash,active FROM pi_users WHERE person_id=? LIMIT 1",
            [$pid],
        );
        if (!$u) {
            if (!password_ok($pass)) {
                throw new RuntimeException(
                    "Informe uma senha inicial para o colaborador com 8 a 128 caracteres que não seja uma senha comum.",
                );
            }
            $email = $email !== "" ? $email : null;
            q(
                "INSERT INTO pi_users (person_id,name,email,password_hash,active,created_at) VALUES (?,?,?,?,1,NOW())",
                [$pid, $name, $email, password_hash_secure($pass)],
            );
            $uid = db_last_insert_id();
            counter_inc("users_total");
            clinic_metric_inc($cid, "team_writes");
        } else {
            $uid = (int) $u["id"];
            $alreadyLinked = (bool) one(
                "SELECT id FROM pi_user_roles WHERE user_id=? AND clinic_id=? LIMIT 1",
                [$uid, $cid],
            );
            if (
                !$alreadyLinked &&
                ($pass === "" ||
                    !password_verify($pass, (string) ($u["password_hash"] ?? "")))
            ) {
                throw new RuntimeException(
                    "Este CPF já possui acesso. Confirme a senha atual do usuário para vinculá-lo com segurança a outro consultório.",
                );
            }
            if (!(int) $u["active"]) {
                q("UPDATE pi_users SET active=1,updated_at=NOW() WHERE id=?", [
                    $uid,
                ]);
            }
            q(
                "UPDATE pi_users SET name=?,email=COALESCE(NULLIF(?,''),email),updated_at=NOW() WHERE id=?",
                [$name, $email, $uid],
            );
        }
        sync_user_roles_for_clinic($cid, $uid, $roles);
        clinic_metric_inc($cid, "role_links");
        return $uid;
    
    }

    public static function clinic_user_exists(int $cid, int $uid, array $roles = []): bool
    
    {
    
        if ($cid <= 0 || $uid <= 0) {
            return false;
        }
        $params = [$uid, $cid];
        $sql =
            "SELECT ur.id FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.user_id=? AND ur.clinic_id=? AND ur.active=1";
        if ($roles) {
            $roles = array_values(array_unique(array_filter($roles)));
            $ph = implode(",", array_fill(0, count($roles), "?"));
            $sql .= " AND ur.role_code IN ($ph)";
            $params = array_merge($params, $roles);
        }
        $sql .= " LIMIT 1";
        return (bool) one($sql, $params);
    
    }

    public static function person_has_other_clinic_links(int $personId, int $cid): bool
    
    {
    
        if ($personId <= 0 || $cid <= 0) {
            return false;
        }
        try {
            $n = (int) val(
                "SELECT COUNT(*) FROM (SELECT clinic_id FROM pi_patients WHERE person_id=? AND clinic_id<>? UNION SELECT clinic_id FROM pi_leads WHERE person_id=? AND clinic_id<>? UNION SELECT ur.clinic_id FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.active=1 WHERE u.person_id=? AND ur.clinic_id<>?) x",
                [$personId, $cid, $personId, $cid, $personId, $cid],
            );
            return $n > 0;
        } catch (Throwable $e) {
            error_log("[Prontoo person scope] " . $e->getMessage());
            return true;
        }
    
    }

    public static function user_name_by_id(?int $uid): string
    
    {
    
        if (!$uid) {
            return "O sistema";
        }
        $c = ctx();
        if ((int) ($c["user"]["id"] ?? 0) === $uid) {
            return first_name((string) ($c["user"]["name"] ?? ""));
        }
        $cid = ($c["scope"] ?? "") === "clinic" ? (int) $c["clinic_id"] : null;
        $name = audit_user_name_lookup($uid, $cid);
        return $name !== "" ? first_name($name) : "O colaborador";
    
    }

    public static function clinic_active_role_count(
        int $cid,
        string $role,
        ?int $excludingUserId = null,
    ): int 
    {
    
        if ($cid <= 0 || $role === "") {
            return 0;
        }
        $params = [$cid, $role];
        $sql =
            "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.clinic_id=? AND ur.role_code=? AND ur.active=1";
        if ($excludingUserId !== null && $excludingUserId > 0) {
            $sql .= " AND ur.user_id<>?";
            $params[] = $excludingUserId;
        }
        try {
            return (int) val($sql, $params);
        } catch (Throwable $e) {
            error_log("[Prontoo role min count] " . $e->getMessage());
            return 0;
        }
    
    }

    public static function clinic_assert_minimum_roles_after_change(
        int $cid,
        int $targetUserId,
        array $targetRoles,
    ): void 
    {
    
        if ($cid <= 0) {
            throw new RuntimeException(clinic_minimum_roles_violation_message());
        }
        $targetRoles = array_values(
            array_unique(array_map("strval", $targetRoles)),
        );
        $hasManager =
            clinic_active_role_count($cid, "gerente", $targetUserId) +
            (in_array("gerente", $targetRoles, true) ? 1 : 0);
        $hasProfessional =
            clinic_active_role_count($cid, "medico", $targetUserId) +
            (in_array("medico", $targetRoles, true) ? 1 : 0);
        if ($hasManager < 1 || $hasProfessional < 1) {
            throw new RuntimeException(clinic_minimum_roles_violation_message());
        }
    
    }

    public static function clinic_assert_can_deactivate_user_roles(int $cid, int $uid): void
    
    {
    
        clinic_assert_minimum_roles_after_change($cid, $uid, []);
    
    }

    public static function clinic_auto_assign_missing_managers(int $limit = 200): int
    
    {
    
        if (!function_exists("has_cfg") || !has_cfg()) {
            return 0;
        }
        $limit = max(1, min(1000, $limit));
        $fixed = 0;
        $scopeHad = array_key_exists("PRONTOO_SCOPE_GUARD_SYSTEM", $GLOBALS);
        $scopePrev = $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] ?? null;
        $roHad = array_key_exists("PRONTOO_READONLY_GUARD_DISABLED", $GLOBALS);
        $roPrev = $GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"] ?? null;
        $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] = true;
        $GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"] = true;
        try {
            $clinics = q(
                "SELECT c.id,c.owner_user_id,c.manager_user_id FROM pi_clinics c WHERE c.active=1 AND NOT EXISTS (SELECT 1 FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.clinic_id=c.id AND ur.role_code='gerente' AND ur.active=1) ORDER BY c.id ASC LIMIT {$limit}",
            )->fetchAll();
            foreach ($clinics as $cl) {
                $cid = (int) ($cl["id"] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                $candidate = one(
                    "SELECT ur.user_id FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.clinic_id=? AND ur.active=1 ORDER BY COALESCE(ur.created_at,0) ASC, ur.id ASC LIMIT 1",
                    [$cid],
                );
                $uid = (int) ($candidate["user_id"] ?? 0);
                if ($uid <= 0) {
                    foreach (
                        [
                            (int) ($cl["manager_user_id"] ?? 0),
                            (int) ($cl["owner_user_id"] ?? 0),
                        ]
                        as $fallback
                    ) {
                        if (
                            $fallback > 0 &&
                            (int) val(
                                "SELECT COUNT(*) FROM pi_users WHERE id=? AND active=1",
                                [$fallback],
                            ) > 0
                        ) {
                            $uid = $fallback;
                            break;
                        }
                    }
                }
                if ($uid <= 0) {
                    continue;
                }
                q(
                    "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active,created_at) VALUES (?,?, 'gerente',0,1,NOW()) ON DUPLICATE KEY UPDATE active=1",
                    [$uid, $cid],
                );
                q(
                    "UPDATE pi_clinics SET manager_user_id=?, updated_at=NOW() WHERE id=? AND (manager_user_id IS NULL OR manager_user_id=0 OR NOT EXISTS (SELECT 1 FROM pi_users u WHERE u.id=pi_clinics.manager_user_id AND u.active=1))",
                    [$uid, $cid],
                );
                try {
                    propagate_user_role_permissions(
                        $cid,
                        $uid,
                        "auto_manager_guard",
                    );
                } catch (Throwable $e) {
                    error_log(
                        "[Prontoo auto manager propagate] " . $e->getMessage(),
                    );
                }
                $fixed++;
            }
        } catch (Throwable $e) {
            error_log("[Prontoo auto manager] " . $e->getMessage());
        } finally {
            if ($scopeHad) {
                $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] = $scopePrev;
            } else {
                unset($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"]);
            }
            if ($roHad) {
                $GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"] = $roPrev;
            } else {
                unset($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"]);
            }
        }
        return $fixed;
    
    }

    public static function active_clinic_roles_for_user(int $uid): array
    
    {
    
        if ($uid <= 0) {
            return [];
        }
        $loader = function () use ($uid): array {
    
            single_active_role_cleanup_for_user($uid, null);
            if (function_exists("clinic_enable_roles_from_active_user_links")) {
                clinic_enable_roles_from_active_user_links($uid);
            }
            return q(
                "SELECT ur.id,ur.user_id,ur.clinic_id,ur.role_code,ur.is_owner,ur.active,c.display_name,c.timezone,c.address_state,c.address_city,c.active clinic_active FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id WHERE ur.user_id=? AND ur.active=1 AND c.active=1 ORDER BY c.display_name ASC, ur.is_owner DESC, FIELD(ur.role_code,'gerente','medico','assistente','recepcionista'), ur.id ASC LIMIT 80",
                [$uid],
            )->fetchAll();
        };
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "permissions",
                server_json_cache_safe_key("active_roles", [$uid]),
                server_json_cache_ttl("permissions"),
                $loader,
                ["user:" . $uid, "table:pi_user_roles"],
            );
        }
        return $loader();
    
    }

    public static function user_is_global_admin(int $uid): bool
    
    {
    
        try {
            return (int) val(
                "SELECT is_global_admin FROM pi_users WHERE id=? AND active=1",
                [$uid],
            ) === 1;
        } catch (Throwable $e) {
            error_log("[Prontoo user_is_global_admin] " . $e->getMessage());
            return false;
        }
    
    }

    public static function manageable_team_roles(?int $clinicId = null): array
    
    {
    
        return [
            "recepcionista" => role_label_for("recepcionista", $clinicId),
            "assistente" => role_label_for("assistente", $clinicId),
            "medico" => role_label_for("medico", $clinicId),
            "gerente" => role_label_for("gerente", $clinicId),
        ];
    
    }

    public static function can_manage_team_role(string $role): bool
    
    {
    
        return isset(manageable_team_roles()[$role]);
    
    }

    public static function selected_team_roles(array $data, ?int $cid = null): array
    
    {
    
        $raw =
            $data["team_roles"] ??
            ($data["role_codes"] ?? ($data["team_role"] ?? []));
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $manageable = manageable_team_roles($cid);
        $roles = [];
        foreach ($raw as $role) {
            $role = (string) $role;
            if (isset($manageable[$role]) && !in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }
        $order = array_keys($manageable);
        usort(
            $roles,
             fn($a, $b) => array_search($a, $order, true) <=>
                array_search($b, $order, true),
        );
        if (count($roles) < 1 || count($roles) > count($manageable)) {
            throw new RuntimeException(
                "Escolha de 1 a " .
                    count($manageable) .
                    " cargos para o colaborador.",
            );
        }
        return $roles;
    
    }

    public static function clinic_restore_default_permissions_for_role(
        int $cid,
        string $role,
    ): void 
    {
    
        if ($cid <= 0 || $role === "" || !isset(PRONTOO_ROLES[$role])) {
            return;
        }
        $defaults = default_permissions();
        foreach (actions() as $key => $a) {
            $allow =
                in_array($key, $defaults[$role] ?? [], true) || $role === "gerente"
                    ? 1
                    : 0;
            q(
                "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)",
                [$cid, $role, $key, $allow],
            );
        }
        if (
            function_exists("permission_module_defs") &&
            function_exists("permission_operations") &&
            function_exists("permission_operation_configurable") &&
            function_exists("permission_default_ops_for_role")
        ) {
            foreach (permission_module_defs() as $module => $def) {
                foreach (permission_operations() as $op => $label) {
                    $supported = permission_operation_configurable(
                        $role,
                        (string) $module,
                        (string) $op,
                    );
                    $defaultsOps = permission_default_ops_for_role(
                        $role,
                        (string) $module,
                    );
                    $allowed = $supported && !empty($defaultsOps[$op]);
                    if ($role === "gerente" && $supported) {
                        $allowed = true;
                    }
                    q(
                        "INSERT INTO pi_permission_rules (clinic_id,role_code,action_key,operation_key,allowed) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)",
                        [
                            $cid,
                            $role,
                            (string) $module,
                            (string) $op,
                            $allowed ? 1 : 0,
                        ],
                    );
                }
            }
        }
    
    }

    public static function clinic_enable_roles_for_assignment(
        int $cid,
        array $roles,
        string $reason = "assignment",
    ): void 
    {
    
        if ($cid <= 0) {
            return;
        }
        $valid = array_keys(PRONTOO_ROLES);
        $roles = array_values(
            array_intersect(
                $valid,
                array_values(array_unique(array_map("strval", $roles))),
            ),
        );
        if (!$roles) {
            return;
        }
        try {
            seed_clinic_roles($cid);
        } catch (Throwable $e) {
            error_log("[Prontoo role enable seed] " . $e->getMessage());
        }
        $order = [
            "recepcionista" => 1,
            "assistente" => 2,
            "medico" => 3,
            "gerente" => 4,
        ];
        foreach ($roles as $role) {
            $wasEnabled = 0;
            try {
                $wasEnabled =
                    (int) (val(
                        "SELECT enabled FROM pi_clinic_roles WHERE clinic_id=? AND role_code=? LIMIT 1",
                        [$cid, $role],
                    ) ?? 0);
            } catch (Throwable $e) {
                error_log("[Prontoo role enable read] " . $e->getMessage());
            }
            $label = role_label_for($role, $cid);
            $ico = function_exists("default_role_icon")
                ? default_role_icon($role)
                : "workspaces";
            $sort = (int) ($order[$role] ?? 99);
            q(
                "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,1,?) ON DUPLICATE KEY UPDATE enabled=1,label=COALESCE(NULLIF(label,''),VALUES(label)),icon_name=IF(icon_name='' OR icon_name='support_agent',VALUES(icon_name),icon_name),sort_order=IF(sort_order IS NULL OR sort_order=0,VALUES(sort_order),sort_order)",
                [$cid, $role, $label, $ico, $sort],
            );
            if ($wasEnabled < 1) {
                clinic_restore_default_permissions_for_role($cid, $role);
                try {
                    audit("cargo_habilitado_por_colaborador", "cargo", $role, [
                        "clinic_id" => $cid,
                        "motivo" => $reason,
                        "audit_body" =>
                            "Cargo habilitado automaticamente porque foi atribuído a um colaborador ativo.",
                    ]);
                } catch (Throwable $e) {
                    error_log("[Prontoo role enable audit] " . $e->getMessage());
                }
            }
        }
    
    }
}
