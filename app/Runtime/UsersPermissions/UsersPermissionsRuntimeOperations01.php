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
        if (!\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($cid, $uid, $roles)) {
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::record_scope_violation(
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
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($data["team_cpf"] ?? ""));
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
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            throw new RuntimeException("Este CPF não existe.");
        }
        if ($birth === "" || !\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth)) {
            throw new RuntimeException(
                "Informe uma data de nascimento válida para o colaborador.",
            );
        }
        $roles = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::selected_team_roles($data, $cid);
        $pid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::upsert_person($name, $cpf, $birth);
        \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::lock_person_user_identity($pid);
        if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'person_common_profile_update'])) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_update(
                $pid,
                array_merge(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_from_array($data, ""), [
                    "legal_type" => "cpf",
                    "legal_document" => $cpf,
                    "phone" => \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($data["phone"] ?? "")) ?: null,
                    "email" => $email !== "" ? $email : null,
                ]),
            );
        }
        $u = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.permissions01.save_team_member.01', [$pid], []);
        if (!$u) {
            if (!\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_ok($pass)) {
                throw new RuntimeException(
                    "Informe uma senha inicial para o colaborador com 8 a 128 caracteres que não seja uma senha comum.",
                );
            }
            $email = $email !== "" ? $email : null;
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.save_team_member.02', [$pid, $name, $email, \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_hash_secure($pass)], []);
            $uid = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->lastInsertId();
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("users_total");
            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "team_writes");
        } else {
            $uid = (int) $u["id"];
            $alreadyLinked = (bool) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.permissions01.save_team_member.03', [$uid, $cid], []);
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
                \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.save_team_member.04', [
                    $uid,
                ], []);
            }
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.save_team_member.05', [$name, $email, $uid], []);
        }
        \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::sync_user_roles_for_clinic($cid, $uid, $roles);
        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "role_links");
        return $uid;
    
    }

    public static function clinic_user_exists(int $cid, int $uid, array $roles = []): bool
    
    {
    
        if ($cid <= 0 || $uid <= 0) {
            return false;
        }
        $roles = array_values(array_unique(array_filter(array_map('strval', $roles))));
        $params = array_merge([$uid, $cid], $roles);
        return (bool) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row(
            'identity.permissions01.clinic_user_exists.01',
            $params,
            ['role_count' => count($roles)],
        );
    
    }

    public static function person_has_other_clinic_links(int $personId, int $cid): bool
    
    {
    
        if ($personId <= 0 || $cid <= 0) {
            return false;
        }
        try {
            $n = (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.permissions01.person_has_other_clinic_links.01', [$personId, $cid, $personId, $cid, $personId, $cid], []);
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
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
        if ((int) ($c["user"]["id"] ?? 0) === $uid) {
            return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($c["user"]["name"] ?? ""));
        }
        $cid = ($c["scope"] ?? "") === "clinic" ? (int) $c["clinic_id"] : null;
        $name = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_user_name_lookup($uid, $cid);
        return $name !== "" ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($name) : "O colaborador";
    
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
        if ($excludingUserId !== null && $excludingUserId > 0) {
            $params[] = $excludingUserId;
        }
        try {
            return (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar(
                'identity.permissions01.clinic_active_role_count.01',
                $params,
                ['exclude_user' => $excludingUserId !== null && $excludingUserId > 0],
            );
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
            throw new RuntimeException(\Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::clinic_minimum_roles_violation_message());
        }
        $targetRoles = array_values(
            array_unique(array_map("strval", $targetRoles)),
        );
        $hasManager =
            \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_active_role_count($cid, "gerente", $targetUserId) +
            (in_array("gerente", $targetRoles, true) ? 1 : 0);
        $hasProfessional =
            \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_active_role_count($cid, "medico", $targetUserId) +
            (in_array("medico", $targetRoles, true) ? 1 : 0);
        if ($hasManager < 1 || $hasProfessional < 1) {
            throw new RuntimeException(\Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::clinic_minimum_roles_violation_message());
        }
    
    }

    public static function clinic_assert_can_deactivate_user_roles(int $cid, int $uid): void
    
    {
    
        \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_assert_minimum_roles_after_change($cid, $uid, []);
    
    }

    public static function clinic_auto_assign_missing_managers(int $limit = 200): int
    
    {
    
        if (!is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'has_cfg']) || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
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
            $clinics = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.clinic_auto_assign_missing_managers.01', [], ['limit' => $limit])->fetchAll();
            foreach ($clinics as $cl) {
                $cid = (int) ($cl["id"] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                $candidate = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.permissions01.clinic_auto_assign_missing_managers.02', [$cid], []);
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
                            (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.permissions01.clinic_auto_assign_missing_managers.03', [$fallback], []) > 0
                        ) {
                            $uid = $fallback;
                            break;
                        }
                    }
                }
                if ($uid <= 0) {
                    continue;
                }
                \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.clinic_auto_assign_missing_managers.04', [$uid, $cid], []);
                \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.clinic_auto_assign_missing_managers.05', [$uid, $cid], []);
                try {
                    \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::propagate_user_role_permissions(
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
    
            \Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::single_active_role_cleanup_for_user($uid, null);
            if (is_callable([\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::class, 'clinic_enable_roles_from_active_user_links'])) {
                \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::clinic_enable_roles_from_active_user_links($uid);
            }
            return \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.active_clinic_roles_for_user.01', [$uid], [])->fetchAll();
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "permissions",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("active_roles", [$uid]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("permissions"),
                $loader,
                ["user:" . $uid, "table:pi_user_roles"],
            );
        }
        return $loader();
    
    }

    public static function user_is_global_admin(int $uid): bool
    
    {
    
        try {
            return (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.permissions01.user_is_global_admin.01', [$uid], []) === 1;
        } catch (Throwable $e) {
            error_log("[Prontoo user_is_global_admin] " . $e->getMessage());
            return false;
        }
    
    }

    public static function manageable_team_roles(?int $clinicId = null): array
    
    {
    
        return [
            "recepcionista" => \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("recepcionista", $clinicId),
            "assistente" => \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("assistente", $clinicId),
            "medico" => \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("medico", $clinicId),
            "gerente" => \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("gerente", $clinicId),
        ];
    
    }

    public static function can_manage_team_role(string $role): bool
    
    {
    
        return isset(\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::manageable_team_roles()[$role]);
    
    }

    public static function selected_team_roles(array $data, ?int $cid = null): array
    
    {
    
        $raw =
            $data["team_roles"] ??
            ($data["role_codes"] ?? ($data["team_role"] ?? []));
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $manageable = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::manageable_team_roles($cid);
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
        $defaults = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::default_permissions();
        foreach (\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::actions() as $key => $a) {
            $allow =
                in_array($key, $defaults[$role] ?? [], true) || $role === "gerente"
                    ? 1
                    : 0;
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.clinic_restore_default_permissions_for_role.01', [$cid, $role, $key, $allow], []);
        }
        if (
            is_callable([\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::class, 'permission_module_defs']) &&
            is_callable([\Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::class, 'permission_operations']) &&
            is_callable([\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::class, 'permission_operation_configurable']) &&
            is_callable([\Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::class, 'permission_default_ops_for_role'])
        ) {
            foreach (\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_defs() as $module => $def) {
                foreach (\Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::permission_operations() as $op => $label) {
                    $supported = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_configurable(
                        $role,
                        (string) $module,
                        (string) $op,
                    );
                    $defaultsOps = \Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::permission_default_ops_for_role(
                        $role,
                        (string) $module,
                    );
                    $allowed = $supported && !empty($defaultsOps[$op]);
                    if ($role === "gerente" && $supported) {
                        $allowed = true;
                    }
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.clinic_restore_default_permissions_for_role.02', [
                            $cid,
                            $role,
                            (string) $module,
                            (string) $op,
                            $allowed ? 1 : 0,
                        ], []);
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
            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::seed_clinic_roles($cid);
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
                    (int) (\Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.permissions01.clinic_enable_roles_for_assignment.01', [$cid, $role], []) ?? 0);
            } catch (Throwable $e) {
                error_log("[Prontoo role enable read] " . $e->getMessage());
            }
            $label = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, $cid);
            $ico = is_callable([\Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::class, 'default_role_icon'])
                ? \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($role)
                : "workspaces";
            $sort = (int) ($order[$role] ?? 99);
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions01.clinic_enable_roles_for_assignment.02', [$cid, $role, $label, $ico, $sort], []);
            if ($wasEnabled < 1) {
                \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_restore_default_permissions_for_role($cid, $role);
                try {
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("cargo_habilitado_por_colaborador", "cargo", $role, [
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
