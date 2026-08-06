<?php
declare(strict_types=1);
function require_user_in_clinic(int $cid, int $uid, array $roles = []): void
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
function save_team_member(int $cid, array $data): ?int
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
function clinic_user_exists(int $cid, int $uid, array $roles = []): bool
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
function person_has_other_clinic_links(int $personId, int $cid): bool
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
function user_name_by_id(?int $uid): string
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
function single_active_role_cleanup_for_user(int $uid, ?int $cid = null): void
{

    return;
}
function clinic_minimum_roles_violation_message(): string
{

    return "Esta alteração não é possível: todo consultório precisa manter pelo menos um Profissional e um Administrativo ativos.";
}
function clinic_active_role_count(
    int $cid,
    string $role,
    ?int $excludingUserId = null,
): int {

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
function clinic_assert_minimum_roles_after_change(
    int $cid,
    int $targetUserId,
    array $targetRoles,
): void {

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
function clinic_assert_can_deactivate_user_roles(int $cid, int $uid): void
{

    clinic_assert_minimum_roles_after_change($cid, $uid, []);
}
function clinic_auto_assign_missing_managers(int $limit = 200): int
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
function active_clinic_roles_for_user(int $uid): array
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
function user_is_global_admin(int $uid): bool
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
function manageable_team_roles(?int $clinicId = null): array
{

    return [
        "recepcionista" => role_label_for("recepcionista", $clinicId),
        "assistente" => role_label_for("assistente", $clinicId),
        "medico" => role_label_for("medico", $clinicId),
        "gerente" => role_label_for("gerente", $clinicId),
    ];
}
function can_manage_team_role(string $role): bool
{

    return isset(manageable_team_roles()[$role]);
}
function selected_team_roles(array $data, ?int $cid = null): array
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
function clinic_restore_default_permissions_for_role(
    int $cid,
    string $role,
): void {

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
function clinic_enable_roles_for_assignment(
    int $cid,
    array $roles,
    string $reason = "assignment",
): void {

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
function clinic_enable_roles_from_active_user_links(int $uid): void
{

    if ($uid <= 0) {
        return;
    }
    try {
        $rows = q(
            "SELECT DISTINCT ur.clinic_id,ur.role_code FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id AND c.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE ur.user_id=? AND ur.active=1 AND (cr.role_code IS NULL OR COALESCE(cr.enabled,0)=0)",
            [$uid],
        )->fetchAll();
        $byClinic = [];
        foreach ($rows as $r) {
            $cid = (int) ($r["clinic_id"] ?? 0);
            $role = (string) ($r["role_code"] ?? "");
            if ($cid > 0 && isset(PRONTOO_ROLES[$role])) {
                $byClinic[$cid][] = $role;
            }
        }
        foreach ($byClinic as $cid => $roles) {
            clinic_enable_roles_for_assignment(
                (int) $cid,
                $roles,
                "active_user_link_repair",
            );
        }
    } catch (Throwable $e) {
        error_log("[Prontoo role enable repair] " . $e->getMessage());
    }
}
function sync_user_roles_for_clinic(
    int $cid,
    int $uid,
    array $roles,
    bool $propagate = true,
): array {

    if ($cid <= 0 || $uid <= 0) {
        throw new RuntimeException("Colaborador ou consultório não informado.");
    }
    $manageable = manageable_team_roles($cid);
    $roles = array_values(
        array_intersect(
            array_keys($manageable),
            array_values(array_unique(array_map("strval", $roles))),
        ),
    );
    if (count($roles) < 1 || count($roles) > count($manageable)) {
        throw new RuntimeException(
            "Escolha de 1 a " .
                count($manageable) .
                " cargos para o colaborador.",
        );
    }
    clinic_assert_minimum_roles_after_change($cid, $uid, $roles);
    $db = pdo();
    $own = !$db->inTransaction();
    if ($own) {
        db_begin_transaction();
    }
    try {
        clinic_enable_roles_for_assignment($cid, $roles, "roles_sync");
        $ownerFlag = (int) (val(
            "SELECT COALESCE(MAX(is_owner),0) FROM pi_user_roles WHERE user_id=? AND clinic_id=?",
            [$uid, $cid],
        ) ?: 0);
        $ph = implode(",", array_fill(0, count($roles), "?"));
        q(
            "UPDATE pi_user_roles SET active=0 WHERE user_id=? AND clinic_id=? AND active=1 AND role_code NOT IN ($ph)",
            array_merge([$uid, $cid], $roles),
        );
        foreach ($roles as $role) {
            q(
                "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active,created_at) VALUES (?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE active=1,is_owner=GREATEST(is_owner,VALUES(is_owner))",
                [$uid, $cid, $role, $ownerFlag],
            );
        }
        $actualRoles = active_role_codes_for_user_in_clinic($cid, $uid);
        $expectedRoles = $roles;
        sort($actualRoles);
        sort($expectedRoles);
        if ($actualRoles !== $expectedRoles) {
            throw new RuntimeException(
                "Não foi possível aplicar todos os cargos selecionados ao colaborador.",
            );
        }
        if ($own && $db->inTransaction()) {
            db_commit();
        }
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) {
            db_rollback();
        }
        throw $e;
    }
    if ($propagate) {
        propagate_user_role_permissions($cid, $uid, "roles_sync");
    }
    return active_role_codes_for_user_in_clinic($cid, $uid);
}
function active_role_codes_for_user_in_clinic(int $cid, int $uid): array
{

    if ($cid <= 0 || $uid <= 0) {
        return [];
    }
    $rows = q(
        "SELECT role_code FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND active=1 ORDER BY FIELD(role_code,'recepcionista','assistente','medico','gerente'), id ASC",
        [$uid, $cid],
    )->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_unique(array_map("strval", $rows ?: [])));
}
function preferred_active_role_link_for_user(
    int $cid,
    int $uid,
    ?int $preferRoleId = null,
): ?array {

    if ($cid <= 0 || $uid <= 0) {
        return null;
    }
    if ($preferRoleId && $preferRoleId > 0) {
        $r = one(
            "SELECT id,clinic_id,role_code FROM pi_user_roles WHERE id=? AND user_id=? AND clinic_id=? AND active=1 LIMIT 1",
            [$preferRoleId, $uid, $cid],
        );
        if ($r) {
            return $r;
        }
    }
    return one(
        "SELECT id,clinic_id,role_code FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND active=1 ORDER BY is_owner DESC, FIELD(role_code,'gerente','medico','assistente','recepcionista'), id ASC LIMIT 1",
        [$uid, $cid],
    ) ?:
        null;
}
function propagate_user_role_permissions(
    int $cid,
    int $uid,
    string $reason = "roles_updated",
): void {

    if ($cid <= 0 || $uid <= 0) {
        return;
    }
    try {
        seed_permissions($cid);
    } catch (Throwable $e) {
        error_log("[Prontoo role propagation seed] " . $e->getMessage());
    }
    $replacement = preferred_active_role_link_for_user(
        $cid,
        $uid,
        (int) ($_SESSION["uid"] ?? 0) === $uid &&
        (int) ($_SESSION["clinic_id"] ?? 0) === $cid
            ? (int) ($_SESSION["uc_id"] ?? 0)
            : null,
    );
    try {
        $newGeneration = user_auth_generation_rotate($uid);
        security_retire_persistent_devices_for_user($uid);
        if ((int) ($_SESSION["uid"] ?? 0) === $uid) {
            $_SESSION["user_auth_generation"] = $newGeneration;
        }
    } catch (Throwable $e) {
        error_log("[Prontoo role propagation sessions] " . $e->getMessage());
    }
    if (
        (int) ($_SESSION["uid"] ?? 0) === $uid &&
        (int) ($_SESSION["clinic_id"] ?? 0) === $cid
    ) {
        if ($replacement) {
            $_SESSION["scope"] = "clinic";
            $_SESSION["uc_id"] = (int) $replacement["id"];
            $_SESSION["clinic_id"] = $cid;
            $_SESSION["role_code"] = (string) $replacement["role_code"];
            $_SESSION["effective_roles"] = [
                (string) $replacement["role_code"],
            ];
        } else {
            unset(
                $_SESSION["uc_id"],
                $_SESSION["clinic_id"],
                $_SESSION["role_code"],
            );
            unset($_SESSION["effective_roles"]);
        }
    }
    if (function_exists("server_json_cache_clear_categories")) {
        server_json_cache_clear_categories([
            "context",
            "cmdbar",
            "permissions",
            "clinic",
            "work_hours",
            "lookup",
            "auxiliary",
        ]);
    }
    try {
        audit("permissoes_atualizadas", "usuario", $uid, [
            "clinic_id" => $cid,
            "motivo" => $reason,
            "audit_body" =>
                "Permissões efetivas propagadas imediatamente após alteração dos cargos ativos do colaborador.",
        ]);
    } catch (Throwable $e) {
        error_log("[Prontoo role propagation audit] " . $e->getMessage());
    }
}
function role_labels_from_codes(array $codes, ?int $cid = null): array
{

    $labels = [];
    foreach ($codes as $code) {
        $labels[] = role_label_for((string) $code, $cid);
    }
    return $labels;
}
function role_badges_html(array $codes, ?int $cid = null): string
{

    if (!$codes) {
        return '<span class="muted">Sem cargo ativo</span>';
    }
    $h = '<span class="role-badge-list">';
    foreach ($codes as $code) {
        $h .=
            '<span class="role-badge">' .
            e(role_label_for((string) $code, $cid)) .
            "</span>";
    }
    return $h . "</span>";
}
function role_checkbox_group(
    string $name,
    array $options,
    array $selected,
): string {

    $field = str_ends_with($name, "[]") ? $name : $name . "[]";
    $h =
        '<div class="role-check-grid" role="group" aria-label="Cargos do colaborador">';
    foreach ($options as $code => $label) {
        $checked = in_array((string) $code, $selected, true) ? " checked" : "";
        $h .=
            '<label class="role-check"><input type="checkbox" name="' .
            e($field) .
            '" value="' .
            e((string) $code) .
            '"' .
            $checked .
            "><span>" .
            e($label) .
            "</span></label>";
    }
    return $h .
        "</div><small>Escolha de 1 a 4 cargos. O colaborador poderá entrar em apenas um Ambiente por vez.</small>";
}
function team_options(int $cid): array
{

    $links = q(
        "SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND active=1 ORDER BY id ASC LIMIT 200",
        [$cid],
    )->fetchAll();
    $users = fetch_map(
        "pi_users",
        int_ids($links, "user_id"),
        "id,name,active",
    );
    $o = [];
    foreach ($links as $r) {
        $u = $users[(int) $r["user_id"]] ?? [];
        if ($u && (int) $u["active"] === 1) {
            $o[(int) $u["id"]] = $u["name"];
        }
    }
    asort($o, SORT_NATURAL | SORT_FLAG_CASE);
    return $o;
}
function clinic_role_user_ids(int $cid, array $roles): array
{

    $roles = array_values(array_filter(array_unique($roles)));
    if (!$roles) {
        return [];
    }
    $ph = implode(",", array_fill(0, count($roles), "?"));
    $rows = q(
        "SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND active=1 AND role_code IN ($ph) ORDER BY id DESC LIMIT 800",
        array_merge([$cid], $roles),
    )->fetchAll();
    $ids = [];
    foreach ($rows as $r) {
        $ids[] = (int) $r["user_id"];
    }
    return array_values(array_unique($ids));
}
function permission_operations(): array
{

    return [
        "view" => "Visualizar",
        "add" => "Adicionar",
        "edit" => "Alterar",
        "delete" => "Excluir",
    ];
}
function permission_module_defs(): array
{

    $acts = actions();
    $spec = [
        "painel" => ["ops" => ["view"], "note" => "Painel é somente leitura."],
        "leads" => [
            "ops" => ["view", "add", "edit"],
            "note" =>
                "Interessados podem ser cadastrados e convertidos; não há exclusão manual nesta tela.",
        ],
        "appointments" => [
            "ops" => ["view", "add", "edit", "delete"],
            "note" =>
                "Agenda permite criar, alterar e cancelar consultas ou bloqueios.",
        ],
        "operations" => [
            "ops" => ["view"],
            "note" =>
                "Fluxo reúne atalhos para Interessados, Pacientes, Agenda, Tarefas e Atividades.",
        ],
        "financial" => [
            "ops" => ["view", "add", "edit"],
            "note" =>
                "Financeiro gerencia receitas, despesas, contas, credores e relatórios.",
        ],
        "procedures" => [
            "ops" => ["view", "add", "edit"],
            "note" =>
                "Procedimentos são cadastrados pelo Administrativo e alimentam os motivos agendáveis.",
        ],
        "patients" => [
            "ops" => ["view", "add", "edit", "delete"],
            "note" =>
                "Pacientes possuem cadastro, atualização e exclusão lógica quando a regra do cargo permitir.",
        ],
        "tasks" => [
            "ops" => ["view", "add", "edit"],
            "note" =>
                "Tarefas podem ser criadas e movimentadas; não há exclusão manual da tarefa.",
        ],
        "documents" => [
            "ops" => ["view", "add", "edit"],
            "note" =>
                "Documentos permitem cadastrar/alterar modelos e emitir documentos; não há exclusão manual.",
        ],
        "notices" => [
            "ops" => ["view", "add"],
            "note" =>
                "Avisos podem ser lidas e criadas; confirmar ou ocultar leitura não é operação administrativa de alteração/exclusão.",
        ],
        "users" => [
            "ops" => ["view", "add", "edit", "delete"],
            "note" =>
                "Colaboradores podem ser cadastrados, alterados e desativados.",
        ],
        "permissions" => [
            "ops" => ["view", "edit"],
            "note" =>
                "Permissões são visualizadas e alteradas por cargo; não há inclusão/exclusão manual de módulos.",
        ],
        "audit" => [
            "ops" => ["view"],
            "note" =>
                "Atividades mostra o histórico contextual por eixo: criar, modificar, consultar e excluir.",
        ],
        "settings" => [
            "ops" => ["view", "edit"],
            "note" =>
                "Consultório permite consultar e atualizar dados cadastrais.",
        ],
    ];
    $defs = [];
    foreach ($spec as $key => $cfg) {
        if (isset($acts[$key])) {
            $defs[$key] = [
                "label" => $acts[$key]["label"],
                "icon" => $acts[$key]["icon"],
                "ops" => $cfg["ops"],
                "note" => $cfg["note"],
            ];
        }
    }
    return $defs;
}
function permission_operation_supported(string $module, string $op): bool
{

    $defs = permission_module_defs();
    return isset($defs[$module]) &&
        in_array($op, $defs[$module]["ops"] ?? [], true);
}
function permission_module_available_for_role(
    string $role,
    string $module,
): bool {

    $acts = actions();
    return isset($acts[$module]) &&
        in_array($role, $acts[$module]["roles"] ?? [], true);
}
function permission_operation_configurable(
    string $role,
    string $module,
    string $op,
): bool {

    return permission_module_available_for_role($role, $module) &&
        permission_operation_supported($module, $op);
}
function permission_default_ops_for_role(string $role, string $module): array
{

    $isManager = $role === "gerente";
    return match ($module) {
        "painel" => ["view" => true],
        "leads" => ["view" => true, "add" => true, "edit" => true],
        "appointments" => [
            "view" => true,
            "add" => true,
            "edit" => true,
            "delete" => in_array(
                $role,
                ["recepcionista", "medico", "gerente"],
                true,
            ),
        ],
        "operations" => ["view" => $isManager],
        "financial" => [
            "view" => $isManager,
            "add" => $isManager,
            "edit" => $isManager,
        ],
        "procedures" => [
            "view" => $isManager,
            "add" => $isManager,
            "edit" => $isManager,
        ],
        "patients" => [
            "view" => true,
            "add" => true,
            "edit" => true,
            "delete" => in_array($role, ["medico", "gerente"], true),
        ],
        "tasks" => ["view" => true, "add" => true, "edit" => true],
        "documents" => ["view" => true, "add" => true, "edit" => true],
        "notices" => ["view" => true, "add" => true],
        "users" => [
            "view" => $isManager,
            "add" => $isManager,
            "edit" => $isManager,
            "delete" => $isManager,
        ],
        "permissions" => ["view" => $isManager, "edit" => $isManager],
        "audit" => ["view" => true],
        "settings" => [
            "view" => in_array($role, ["medico", "gerente"], true),
            "edit" => in_array($role, ["medico", "gerente"], true),
        ],
        default => ["view" => false],
    };
}
function permission_default_for(
    string $role,
    string $module,
    string $op,
    int $cid,
): bool {

    foreach (collaborator_permission_matrix_base($role, $cid) as $row) {
        if (($row["key"] ?? "") === $module) {
            return !empty($row[$op]);
        }
    }
    return false;
}
function permission_rules_for_role(string $role, int $cid): array
{

    static $requestCache = [];
    $requestKey = $cid . "|" . $role;
    if (array_key_exists($requestKey, $requestCache)) {
        return $requestCache[$requestKey];
    }
    $loader = function () use ($role, $cid): array {

        $stored = [];
        try {
            foreach (
                q(
                    "SELECT action_key,operation_key,allowed FROM pi_permission_rules WHERE clinic_id=? AND role_code=?",
                    [$cid, $role],
                )->fetchAll()
                as $row
            ) {
                $stored[(string) $row["action_key"]][(string) $row["operation_key"]] =
                    (int) $row["allowed"] === 1;
            }
        } catch (Throwable $e) {
            error_log("[Prontoo permission rules] " . $e->getMessage());
        }
        $baseDefaults = [];
        foreach (collaborator_permission_matrix_base($role, $cid) as $baseRow) {
            $baseKey = (string) ($baseRow["key"] ?? "");
            if ($baseKey !== "") {
                $baseDefaults[$baseKey] = $baseRow;
            }
        }
        $matrix = [];
        foreach (permission_module_defs() as $module => $definition) {
            foreach (permission_operations() as $operation => $label) {
                if (!permission_operation_configurable($role, $module, $operation)) {
                    $matrix[$module][$operation] = false;
                    continue;
                }
                $matrix[$module][$operation] = array_key_exists(
                    $operation,
                    $stored[$module] ?? [],
                )
                    ? (bool) $stored[$module][$operation]
                    : !empty($baseDefaults[$module][$operation]);
            }
        }
        if ($role === "gerente") {
            foreach ($matrix as $module => $operations) {
                foreach ($operations as $operation => $value) {
                    if (permission_operation_configurable($role, $module, $operation)) {
                        $matrix[$module][$operation] = true;
                    }
                }
            }
        }
        return $matrix;
    };
    if (function_exists("server_json_cache_remember") && $cid > 0) {
        $matrix = server_json_cache_remember(
            "permissions",
            server_json_cache_safe_key("role_rules", [$cid, $role]),
            server_json_cache_ttl("permissions"),
            $loader,
            ["clinic:" . $cid, "role:" . $role],
        );
    } else {
        $matrix = $loader();
    }
    return $requestCache[$requestKey] = is_array($matrix) ? $matrix : [];
}
function persist_permission_rules(int $cid, string $role, array $posted): void
{

    $mods = permission_module_defs();
    $ops = permission_operations();
    foreach ($mods as $module => $m) {
        $row = $posted[$module] ?? [];
        $any = false;
        foreach (["add", "edit", "delete"] as $op) {
            if (
                permission_operation_configurable($role, $module, $op) &&
                isset($row[$op])
            ) {
                $any = true;
            }
        }
        $view =
            permission_operation_configurable($role, $module, "view") &&
            (isset($row["view"]) || $any);
        foreach ($ops as $op => $label) {
            $supported = permission_operation_configurable($role, $module, $op);
            $allowed =
                $supported && ($op === "view" ? $view : isset($row[$op]));
            if ($role === "gerente" && $supported) {
                $allowed = true;
            }
            q(
                "INSERT INTO pi_permission_rules (clinic_id,role_code,action_key,operation_key,allowed) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)",
                [$cid, $role, $module, $op, $allowed ? 1 : 0],
            );
        }
        q(
            "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)",
            [$cid, $role, $module, $view ? 1 : 0],
        );
    }
    unset(
        $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"][
            $cid . "|" . $role
        ],
    );
}
function collaborator_permission_matrix_base(string $role, int $cid): array
{

    $requestKey = $cid . "|" . $role;
    if (
        !isset($GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"]) ||
        !is_array($GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"])
    ) {
        $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"] = [];
    }
    if (
        array_key_exists(
            $requestKey,
            $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"],
        )
    ) {
        return $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"][$requestKey];
    }
    $allowed = [];
    foreach (
        q(
            "SELECT action_key,allowed FROM pi_permissions WHERE clinic_id=? AND role_code=?",
            [$cid, $role],
        )->fetchAll()
        as $r
    ) {
        $allowed[(string) $r["action_key"]] = (int) $r["allowed"] === 1;
    }
    $defs = permission_module_defs();
    $actions = actions();
    $out = [];
    foreach ($defs as $key => $m) {
        $roleCanSee = in_array($role, $actions[$key]["roles"] ?? [], true);
        $can =
            (!empty($allowed[$key]) && $roleCanSee) ||
            ($role === "gerente" && $roleCanSee);
        $defaults = permission_default_ops_for_role($role, $key);
        $row = ["key" => $key, "label" => $m["label"], "icon" => $m["icon"]];
        foreach (permission_operations() as $op => $label) {
            $row[$op] =
                $can &&
                permission_operation_supported($key, $op) &&
                !empty($defaults[$op]);
        }
        $out[] = $row;
    }
    $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"][$requestKey] = $out;
    return $out;
}
function collaborator_permission_matrix(string $role, int $cid): array
{

    $rules = permission_rules_for_role($role, $cid);
    $defs = permission_module_defs();
    $out = [];
    foreach ($defs as $key => $m) {
        $row = ["key" => $key, "label" => $m["label"], "icon" => $m["icon"]];
        foreach (permission_operations() as $op => $label) {
            $row[$op] =
                permission_operation_supported($key, $op) &&
                !empty($rules[$key][$op]);
        }
        $out[] = $row;
    }
    return $out;
}
function collaborator_permission_table(string $role, int $cid): string
{

    return collaborator_permission_summary([$role], $cid);
}
function collaborator_permission_summary(array $roles, int $cid): string
{

    $roles = array_values(
        array_unique(array_filter(array_map("strval", $roles))),
    );
    if (!$roles) {
        return '<div class="empty">Nenhum cargo ativo para calcular permissões.</div>';
    }
    $ops = permission_operations();
    $defs = permission_module_defs();
    $sources = [];
    foreach ($roles as $role) {
        foreach (collaborator_permission_matrix($role, $cid) as $row) {
            foreach ($ops as $op => $label) {
                if (!empty($row[$op])) {
                    $sources[$row["key"]][$op][] = $role;
                }
            }
        }
    }
    $h =
        '<div class="collab-permission-summary compact-permission-summary"><div class="permission-source-note compact"><strong>Permissões efetivas</strong><span>' .
        e(implode(", ", role_labels_from_codes($roles, $cid))) .
        '</span></div><div class="permission-module-grid compact">';
    foreach ($defs as $module => $meta) {
        $chips = "";
        foreach ($ops as $op => $label) {
            if (!permission_operation_supported($module, $op)) {
                continue;
            }
            $grants = $sources[$module][$op] ?? [];
            if (!$grants) {
                continue;
            }
            $chips .=
                '<span class="permission-chip yes" title="Liberado por ' .
                e(implode(", ", role_labels_from_codes($grants, $cid))) .
                '">' .
                e($label) .
                "</span>";
        }
        if ($chips === "") {
            $chips = '<span class="permission-chip no">Sem acesso</span>';
        }
        $h .=
            '<article class="permission-module-card compact"><header>' .
            icon($meta["icon"]) .
            "<strong>" .
            e($meta["label"]) .
            '</strong></header><div class="permission-chip-list">' .
            $chips .
            "</div></article>";
    }
    return $h . "</div></div>";
}
function page_users(): void
{

    $c = require_can("users");
    $cid = (int) $c["clinic_id"];
    $manageable = manageable_team_roles($cid);
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "save");
        try {
            if ($act === "save") {
                $db = pdo();
                $ownTransaction = !$db->inTransaction();
                if ($ownTransaction) {
                    db_begin_transaction();
                }
                try {
                    $roles = selected_team_roles($_POST, $cid);
                    $uid = save_team_member($cid, $_POST);
                    if ($uid && in_array("medico", $roles, true)) {
                        save_user_work_hours($cid, $uid, $_POST);
                    }
                    audit("usuario_salvo", "usuario", $uid, [
                        "perfil" => implode(",", $roles),
                        "clinic_id" => $cid,
                    ]);
                    if ($ownTransaction && $db->inTransaction()) {
                        db_commit();
                    }
                } catch (Throwable $e) {
                    if ($ownTransaction && $db->inTransaction()) {
                        db_rollback();
                    }
                    throw $e;
                }
                flash("Colaborador vinculado ao consultório.");
                redirect("users");
            }
            if ($act === "deactivate") {
                $uid = (int) ($_POST["user_id"] ?? 0);
                if ($uid === (int) $c["user"]["id"]) {
                    flash(
                        "Você não pode desativar seu próprio vínculo.",
                        "bad",
                    );
                    redirect("users");
                }
                $exists = (int) val(
                    "SELECT COUNT(*) FROM pi_user_roles WHERE user_id=? AND clinic_id=?",
                    [$uid, $cid],
                );
                if (!$exists) {
                    flash(
                        "Colaborador não encontrado neste consultório.",
                        "bad",
                    );
                    redirect("users");
                }
                clinic_assert_can_deactivate_user_roles($cid, $uid);
                q(
                    "UPDATE pi_user_roles SET active=0 WHERE user_id=? AND clinic_id=?",
                    [$uid, $cid],
                );
                propagate_user_role_permissions(
                    $cid,
                    $uid,
                    "deactivate_from_list",
                );
                if (
                    (int) val(
                        "SELECT COUNT(*) FROM pi_user_roles WHERE user_id=? AND active=1",
                        [$uid],
                    ) === 0
                ) {
                    q(
                        "UPDATE pi_users SET active=0, updated_at=NOW() WHERE id=?",
                        [$uid],
                    );
                }
                audit("usuario_desativado", "usuario", $uid, [
                    "clinic_id" => $cid,
                    "audit_body" =>
                        "Colaborador desativado pela listagem; permissões e dispositivos foram sincronizados.",
                ]);
                flash("Colaborador desativado neste consultório.");
                redirect("users");
            }
        } catch (Throwable $e) {
            error_log("[Prontoo users] " . $e->getMessage());
            flash(
                $e->getMessage() === "Este CPF não existe."
                    ? "Este CPF não existe."
                    : $e->getMessage(),
                "bad",
            );
            redirect("users");
        }
    }
    $search = mb_trim((string) ($_GET["q"] ?? ""));
    if (function_exists("mb_substr")) {
        $search = mb_substr($search, 0, 90);
    } else {
        $search = substr($search, 0, 90);
    }
    $filter = (string) ($_GET["f"] ?? "ativos");
    $filterOptions = [
        "ativos" => "Ativos",
        "todos" => "Todos",
        "profissionais" => "Profissionais",
        "sem_email" => "Sem e-mail",
        "inativos" => "Inativos",
    ];
    if (!isset($filterOptions[$filter])) {
        $filter = "ativos";
    }
    $isNew = isset($_GET["new"]) && (string) $_GET["new"] !== "0";
    $newCollaboratorForm =
        '<form method="post" class="compact collaborator-add-form collaborator-new-form collaborator-screen-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="save"><section class="collaborator-new-hero patient-hero"><span class="collaborator-new-icon" aria-hidden="true">' .
        icon("person_add") .
        '</span><span><strong>Novo colaborador</strong><small>Cadastre identificação, contato, cargos e acesso inicial no mesmo padrão de Novo Paciente.</small></span></section><section class="collaborator-form-section collaborator-form-section--identity"><header><span>' .
        icon("badge") .
        '</span><div><strong>Identificação</strong><small>Dados essenciais para localizar ou criar o cadastro.</small></div></header><div class="two collaborator-form-grid">' .
        form_row(
            "Nome completo",
            input(
                "team_name",
                "text",
                "",
                'required autocomplete="name" list="prontoo_person_suggestions" data-person-autosuggest',
            ),
        ) .
        form_row(
            "CPF",
            input(
                "team_cpf",
                "text",
                "",
                'required inputmode="numeric" maxlength="14" data-cpf-mask',
            ),
        ) .
        '</div><div class="two collaborator-form-grid">' .
        form_row("Nascimento", input("team_birth", "date", "", "required")) .
        form_row(
            "E-mail de acesso",
            input("email", "email", "", 'autocomplete="email"'),
        ) .
        '</div></section><section class="collaborator-form-section collaborator-form-section--contact"><header><span>' .
        icon("contact_mail") .
        "</span><div><strong>Contato e endereço</strong><small>Informações complementares do colaborador.</small></div></header>" .
        (function_exists("person_common_profile_fields_html")
            ? person_common_profile_fields_html($cid, [], "", false, true)
            : "") .
        '</section><section class="collaborator-form-section collaborator-form-section--roles"><header><span>' .
        icon("admin_panel_settings") .
        "</span><div><strong>Cargos e expediente</strong><small>Defina o papel no consultório e, se aplicável, o horário de atendimento.</small></div></header>" .
        form_row("Cargos", role_checkbox_group("team_roles", $manageable, [])) .
        work_hours_form_html($cid, 0) .
        '</section><section class="collaborator-form-section collaborator-form-section--access"><header><span>' .
        icon("key") .
        "</span><div><strong>Acesso inicial</strong><small>Senha necessária apenas quando o CPF ainda não possui usuário.</small></div></header>" .
        form_row(
            "Senha inicial",
            input(
                "team_password",
                "password",
                "",
                'placeholder="Obrigatória apenas se o CPF ainda não tiver usuário" data-password-strength',
            ) .
                "<small>Se o CPF já tiver acesso, o sistema reaproveita o usuário.</small>",
        ) .
        "</section>" .
        person_autosuggest_datalist($cid) .
        '<div class="form-actions collaborator-new-actions"><a class="ghost" href="' .
        href("users") .
        '">' .
        icon("close") .
        '<span>Cancelar</span></a><button class="primary" type="submit">' .
        icon("person_add") .
        "<span>Salvar colaborador</span></button></div></form>";
    if ($isNew) {
        page(
            "Novo Colaborador",
            page_head("Novo Colaborador", "") .
                card(
                    $newCollaboratorForm,
                    "patient-new-screen-card collaborator-new-screen-card",
                ),
        );
        return;
    }
    $statsTotal = (int) safe_val(
        "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id WHERE ur.clinic_id=?",
        [$cid],
        0,
    );
    $statsActive = (int) safe_val(
        "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1",
        [$cid],
        0,
    );
    $statsProfessionals = (int) safe_val(
        "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1 AND ur.role_code='medico'",
        [$cid],
        0,
    );
    $statsNoEmail = (int) safe_val(
        "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1 AND COALESCE(u.email,'')=''",
        [$cid],
        0,
    );
    $inactive = max(0, $statsTotal - $statsActive);
    $statHtml =
        '<section class="collaborator-directory-overview patient-directory-overview kpis kpi-info-strip" aria-label="Resumo de colaboradores"><div class="patient-kpi-card kpi-card ' .
        ($statsActive > 0 ? "is-total" : "is-muted") .
        '">' .
        icon("groups") .
        "<p><b>" .
        number_format($statsActive, 0, ",", ".") .
        '</b><span>Ativos</span></p></div><div class="patient-kpi-card kpi-card ' .
        ($statsProfessionals > 0 ? "is-ok" : "is-muted") .
        '">' .
        icon("stethoscope") .
        "<p><b>" .
        number_format($statsProfessionals, 0, ",", ".") .
        '</b><span>Profissionais</span></p></div><div class="patient-kpi-card kpi-card ' .
        ($statsNoEmail > 0 ? "is-warn warn" : "is-ok") .
        '">' .
        icon("alternate_email") .
        "<p><b>" .
        number_format($statsNoEmail, 0, ",", ".") .
        '</b><span>Sem e-mail</span></p></div><div class="patient-kpi-card kpi-card ' .
        ($inactive > 0 ? "is-bad warn" : "is-ok") .
        '">' .
        icon("person_off") .
        "<p><b>" .
        number_format($inactive, 0, ",", ".") .
        "</b><span>Inativos</span></p></div></section>";
    $where = "ur.clinic_id=?";
    $params = [$cid];
    if ($filter === "ativos") {
        $where .= " AND ur.active=1 AND u.active=1";
    } elseif ($filter === "profissionais") {
        $where .= " AND ur.active=1 AND u.active=1 AND ur.role_code='medico'";
    } elseif ($filter === "sem_email") {
        $where .= " AND ur.active=1 AND u.active=1 AND COALESCE(u.email,'')=''";
    } elseif ($filter === "inativos") {
        $where .= " AND (ur.active=0 OR u.active=0)";
    }
    if ($search !== "") {
        $like = "%" . $search . "%";
        $digits = function_exists("only_digits")
            ? only_digits($search)
            : preg_replace("/\D+/", "", $search);
        $where .=
            " AND (u.name LIKE ? OR u.email LIKE ? OR p.full_name LIKE ? OR p.cpf LIKE ? OR p.phone LIKE ? OR ur.role_code LIKE ?)";
        array_push(
            $params,
            $like,
            $like,
            $like,
            $digits !== "" ? "%" . $digits . "%" : $like,
            $digits !== "" ? "%" . $digits . "%" : $like,
            $like,
        );
    }
    $links = q(
        "SELECT u.id user_id,u.name,u.email,u.active user_active,u.last_login_at,p.cpf,p.birth_date,p.phone,GROUP_CONCAT(CASE WHEN ur.active=1 THEN ur.role_code END ORDER BY FIELD(ur.role_code,'recepcionista','assistente','medico','gerente') SEPARATOR ',') active_roles,MAX(ur.active) any_role_active FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id LEFT JOIN pi_persons p ON p.id=u.person_id WHERE $where GROUP BY u.id,u.name,u.email,u.active,u.last_login_at,p.cpf,p.birth_date,p.phone ORDER BY any_role_active DESC,u.name ASC LIMIT 220",
        $params,
    )->fetchAll();
    $rows = "";
    foreach ($links as $r) {
        $roles = array_values(
            array_filter(explode(",", (string) ($r["active_roles"] ?? ""))),
        );
        $status =
            (int) $r["any_role_active"] && (int) $r["user_active"]
                ? "Ativo"
                : "Inativo";
        $statusClass = $status === "Ativo" ? "ok" : "bad";
        $email = mb_trim((string) ($r["email"] ?? ""));
        $emailLabel = $email !== "" ? $email : "Sem e-mail cadastrado";
        $cpf = mask((string) ($r["cpf"] ?? ""));
        if (trim($cpf) === "") {
            $cpf = "CPF não informado";
        }
        $phone = function_exists("phone_br")
            ? phone_br((string) ($r["phone"] ?? ""))
            : mb_trim((string) ($r["phone"] ?? ""));
        if (trim($phone) === "") {
            $phone = "Telefone não informado";
        }
        $last = mb_trim((string) ($r["last_login_at"] ?? ""));
        $lastLabel = $last !== "" ? dt_br($last) : "Sem acesso recente";
        $name = (string) ($r["name"] ?? "Colaborador #" . $r["user_id"]);
        $url = href("user", ["id" => (int) $r["user_id"]]);
        $searchData =
            $name .
            " " .
            $emailLabel .
            " " .
            $cpf .
            " " .
            $phone .
            " " .
            implode(" ", $roles) .
            " " .
            $status;
        $rows .=
            '<article class="collaborator-card-row ds-person-row ds-collaborator-row ds-collaborator-row-v31 patient-card-row patient-status-' .
            e($statusClass) .
            '" data-ds-row-kind="surface" data-collaborator-search="' .
            e($searchData) .
            '">' .
            '<span class="collaborator-avatar ds-person-avatar" aria-hidden="true">' .
            icon("badge") .
            '</span><a class="collaborator-main ds-person-main patient-card-main" href="' .
            $url .
            '"><span class="collaborator-titleline ds-person-title patient-card-title"><strong>' .
            e($name) .
            '</strong><span class="ds-status-pill ' .
            e($statusClass) .
            '">' .
            e($status) .
            '</span></span><span class="collaborator-meta ds-person-meta patient-card-meta"><span>' .
            icon("alternate_email") .
            "<span>" .
            e($emailLabel) .
            "</span></span><span>" .
            icon("badge") .
            "<span>" .
            e($cpf) .
            "</span></span><span>" .
            icon("call") .
            "<span>" .
            e($phone) .
            "</span></span><span>" .
            icon("login") .
            "<span>" .
            e($lastLabel) .
            '</span></span></span><span class="collaborator-roles ds-person-roles">' .
            role_badges_html($roles, $cid) .
            '</span></a><div class="patient-card-actions ds-person-actions collaborator-actions"><a class="primary small" href="' .
            $url .
            '">' .
            icon("folder_open") .
            "<span>Abrir</span></a></div></article>";
    }
    $shown = count($links);
    $list =
        $rows !== ""
            ? '<div class="collaborator-directory-list patient-directory-list ds-person-list ds-collaborator-list">' .
                $rows .
                "</div>"
            : '<div class="empty patient-directory-empty collaborator-directory-empty">' .
                icon("manage_search") .
                "<strong>" .
                ($search !== ""
                    ? "Nenhum colaborador encontrado."
                    : "Nenhum colaborador neste filtro.") .
                "</strong><span>" .
                ($search !== ""
                    ? "Revise a busca ou limpe o campo para ver a equipe completa."
                    : "Escolha outro filtro ou cadastre um novo colaborador.") .
                "</span></div>";
    $clear =
        $search !== "" || $filter !== "ativos"
            ? '<a class="ghost small" href="' .
                href("users", ["f" => "ativos"]) .
                '">' .
                icon("close") .
                "<span>Limpar</span></a>"
            : "";
    $searchBar =
        '<section class="collaborator-directory-search ds-search-block"><form method="get" class="patient-search-bar collaborator-search-bar" role="search"><input type="hidden" name="r" value="users"><input type="hidden" name="f" value="' .
        e($filter) .
        '"><label class="search-field"><input name="q" type="search" value="' .
        e($search) .
        '" placeholder="Nome, CPF, e-mail, telefone ou cargo" autocomplete="off" aria-label="Buscar colaborador por nome, CPF, e-mail, telefone ou cargo"></label><button class="primary small ds-search-button" type="submit">' .
        icon("search") .
        "<span>Busca rápida</span></button>" .
        $clear .
        "</form></section>";
    $filterIcons = [
        "ativos" => "groups",
        "todos" => "view_list",
        "profissionais" => "stethoscope",
        "sem_email" => "alternate_email",
        "inativos" => "person_off",
    ];
    $chips = "";
    foreach ($filterOptions as $key => $label) {
        $chipParams = ["f" => $key];
        if ($search !== "") {
            $chipParams["q"] = $search;
        }
        $chips .=
            '<a class="patient-filter-chip ' .
            ($filter === $key ? "active is-active" : "") .
            '" href="' .
            href("users", $chipParams) .
            '">' .
            icon($filterIcons[$key] ?? "filter_list") .
            "<span>" .
            e($label) .
            "</span></a>";
    }
    if ($search !== "") {
        $chips =
            '<span class="patient-filter-chip active is-active patient-filter-found" aria-current="page">' .
            icon("manage_search") .
            "<span>Encontrados</span><small>" .
            number_format($shown, 0, ",", ".") .
            "</small></span>" .
            $chips;
    }
    $filterList =
        '<nav class="patient-filter-chips ds-filter-list-chips collaborator-list-toolbar" aria-label="Filtros de colaboradores">' .
        $chips .
        '</nav><div data-collaborator-results="users">' .
        $list .
        "</div>";
    $newParams = ["new" => 1];
    if ($search !== "") {
        $newParams["q"] = $search;
    } elseif ($filter !== "ativos") {
        $newParams["f"] = $filter;
    }
    $headAction =
        '<a class="primary small cmdlike" href="' .
        href("users", $newParams) .
        '">' .
        action_summary_label("Novo Colaborador", "person_add") .
        "</a>";
    page(
        "Colaboradores",
        page_head(
            "Colaboradores",
            "Lista do consultório para localizar, conferir e abrir colaboradores com rapidez.",
            $headAction,
        ) .
            $statHtml .
            card(
                $searchBar,
                "patient-search-card collaborator-search-card ds-search-card",
            ) .
            card(
                $filterList,
                "patient-list-card patient-directory-card collaborator-directory-card ds-filter-list-block",
            ),
    );
}
function page_user(): void
{

    $c = require_can("users");
    $cid = (int) $c["clinic_id"];
    $uid = (int) ($_GET["id"] ?? 0);
    $person = one(
        "SELECT u.id user_id,u.name,u.email,u.active user_active,u.last_login_at,u.person_id,p.full_name,p.cpf,p.birth_date,p.phone,p.email person_email,p.address_zip,p.address,p.address_number,p.address_neighborhood,p.address_complement,p.address_state,p.address_city,p.address_city_ibge FROM pi_users u LEFT JOIN pi_persons p ON p.id=u.person_id WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=?) LIMIT 1",
        [$uid, $cid],
    );
    if (!$person) {
        http_response_code(404);
        page(
            "Colaborador",
            '<div class="empty">Colaborador não encontrado neste consultório.</div>',
        );
        return;
    }
    $manageable = manageable_team_roles($cid);
    $roleRows = q(
        "SELECT id,role_code,active FROM pi_user_roles WHERE user_id=? AND clinic_id=? ORDER BY FIELD(role_code,'recepcionista','assistente','medico','gerente')",
        [$uid, $cid],
    )->fetchAll();
    $activeRoles = [];
    foreach ($roleRows as $rr) {
        if ((int) $rr["active"]) {
            $activeRoles[] = (string) $rr["role_code"];
        }
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "update");
        try {
            if ($act === "update") {
                $name = mb_trim((string) ($_POST["name"] ?? ""));
                $email = mb_trim((string) ($_POST["email"] ?? ""));
                $roles = selected_team_roles($_POST, $cid);
                if ($name === "") {
                    throw new RuntimeException("Revise nome e cargos.");
                }
                db_begin_transaction();
                try {
                    q(
                        "UPDATE pi_persons SET full_name=?, updated_at=NOW() WHERE id=?",
                        [$name, (int) $person["person_id"]],
                    );
                    if (function_exists("person_common_profile_update")) {
                        person_common_profile_update(
                            (int) $person["person_id"],
                            array_merge(
                                person_common_profile_from_array($_POST, ""),
                                [
                                    "legal_type" => "cpf",
                                    "legal_document" => only_digits(
                                        (string) ($person["cpf"] ?? ""),
                                    ),
                                    "email" => $email !== "" ? $email : null,
                                ],
                            ),
                        );
                    }
                    person_signature_refresh_verified(
                        (int) $person["person_id"],
                    );
                    q(
                        "UPDATE pi_users SET name=?, email=NULLIF(?,''), active=1, updated_at=NOW() WHERE id=?",
                        [$name, $email, $uid],
                    );
                    $activeRoles = sync_user_roles_for_clinic(
                        $cid,
                        $uid,
                        $roles,
                        false,
                    );
                    if (in_array("medico", $roles, true)) {
                        save_user_work_hours($cid, $uid, $_POST);
                    }
                    audit("usuario_salvo", "usuario", $uid, [
                        "perfil" => implode(",", $activeRoles),
                        "clinic_id" => $cid,
                        "audit_body" =>
                            "Cadastro e cargos do colaborador atualizados em uma única transação.",
                    ]);
                    db_commit();
                    propagate_user_role_permissions($cid, $uid, "roles_sync");
                } catch (Throwable $error) {
                    if (pdo()->inTransaction()) {
                        db_rollback();
                    }
                    throw $error;
                }
                flash("Colaborador atualizado e permissões aplicadas.");
                redirect("user", ["id" => $uid]);
            }
            if ($act === "deactivate") {
                if ($uid === (int) $c["user"]["id"]) {
                    flash(
                        "Você não pode desativar seu próprio vínculo.",
                        "bad",
                    );
                    redirect("user", ["id" => $uid]);
                }
                clinic_assert_can_deactivate_user_roles($cid, $uid);
                q(
                    "UPDATE pi_user_roles SET active=0 WHERE user_id=? AND clinic_id=?",
                    [$uid, $cid],
                );
                propagate_user_role_permissions($cid, $uid, "deactivate");
                if (
                    (int) val(
                        "SELECT COUNT(*) FROM pi_user_roles WHERE user_id=? AND active=1",
                        [$uid],
                    ) === 0
                ) {
                    q(
                        "UPDATE pi_users SET active=0, updated_at=NOW() WHERE id=?",
                        [$uid],
                    );
                }
                audit("usuario_desativado", "usuario", $uid, [
                    "clinic_id" => $cid,
                    "audit_body" =>
                        "Colaborador desativado e sessões/dispositivos do consultório revogados.",
                ]);
                flash("Colaborador desativado neste consultório.");
                redirect("users");
            }
        } catch (Throwable $e) {
            error_log("[Prontoo user detail] " . $e->getMessage());
            flash(
                app_public_error_message(
                    $e,
                    "Não foi possível concluir a alteração do colaborador.",
                ),
                "bad",
            );
            redirect("user", ["id" => $uid]);
        }
    }
    $back =
        '<a class="ghost small" href="' .
        href("users") .
        '">' .
        icon("arrow_back") .
        '<span>Voltar para Colaboradores</span></a><a class="ghost small" href="' .
        href("permissions") .
        '">' .
        icon("shield_person") .
        "<span>Editar permissões</span></a>";
    $status =
        (int) $person["user_active"] && $activeRoles ? "Ativo" : "Inativo";
    $deactivate = "";
    if ($activeRoles && $uid !== (int) $c["user"]["id"]) {
        $deactivate =
            '<form method="post" class="inline" onsubmit="return confirm(&quot;Desativar o vínculo deste colaborador neste consultório?&quot;)">' .
            csrf_field() .
            '<input type="hidden" name="act" value="deactivate"><button class="danger small" type="submit">' .
            icon("person_remove") .
            "<span>Desativar colaborador</span></button></form>";
    }
    $form =
        '<form method="post" class="compact collaborator-edit-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="update"><div class="two">' .
        form_row(
            "Nome completo",
            input("name", "text", $person["name"] ?? "", "required"),
        ) .
        form_row(
            "CPF",
            '<input type="text" value="' .
                e(mask($person["cpf"] ?? "")) .
                '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">',
        ) .
        '</div><div class="two">' .
        form_row(
            "Nascimento",
            '<input type="date" value="' .
                e(app_date_input_from_storage($person["birth_date"] ?? "")) .
                '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">',
        ) .
        form_row(
            "E-mail de acesso",
            input("email", "email", $person["email"] ?? ""),
        ) .
        "</div>" .
        (function_exists("person_common_profile_fields_html")
            ? person_common_profile_fields_html($cid, $person, "", false, true)
            : "") .
        '<div class="two">' .
        form_row(
            "Cargos",
            role_checkbox_group("role_codes", $manageable, $activeRoles),
        ) .
        form_row(
            "Status",
            '<input type="text" value="' . e($status) . '" readonly>',
        ) .
        "</div>" .
        work_hours_form_html($cid, $uid) .
        '<div class="form-actions">' .
        $deactivate .
        '<button type="submit" class="primary">' .
        icon("save") .
        "<span>Salvar colaborador</span></button></div></form>";
    $perm =
        '<h2>Permissões do colaborador</h2><p class="muted-copy">As permissões exibidas consideram os cargos ativos. Na sessão, o colaborador frequenta apenas um Ambiente por vez.</p>' .
        collaborator_permission_summary($activeRoles, $cid);
    page(
        "Colaborador",
        page_head("Colaborador", "", $back) .
            card(
                "<h2>" . e($person["name"] ?? "Colaborador") . "</h2>" . $form,
                "collaborator-profile-card",
            ) .
            card($perm, "collaborator-permissions-card"),
    );
}
function page_permissions(): void
{

    $c = require_can("permissions");
    $cid = (int) $c["clinic_id"];
    $roles = manageable_team_roles($cid);
    $modules = permission_module_defs();
    $ops = permission_operations();
    $selected = (string) ($_GET["role"] ?? "recepcionista");
    if (!isset($roles[$selected])) {
        $selected = "recepcionista";
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $role = (string) ($_POST["role_code"] ?? $selected);
        if (!isset($roles[$role])) {
            $role = "recepcionista";
        }
        try {
            persist_permission_rules($cid, $role, $_POST["allow"] ?? []);
            foreach (
                q(
                    "SELECT DISTINCT user_id FROM pi_user_roles WHERE clinic_id=? AND role_code=? AND active=1",
                    [$cid, $role],
                )->fetchAll(PDO::FETCH_COLUMN)
                as $affectedUid
            ) {
                propagate_user_role_permissions(
                    $cid,
                    (int) $affectedUid,
                    "permission_matrix_updated",
                );
            }
            audit("permissoes_atualizadas", "permissoes", $cid, [
                "perfil" => $role,
                "audit_body" =>
                    "Permissões do cargo " .
                    role_label_for($role, $cid) .
                    " atualizadas e propagadas para colaboradores ativos.",
            ]);
            flash(
                "Permissões atualizadas para " .
                    role_label_for($role, $cid) .
                    ".",
            );
            redirect("permissions", ["role" => $role]);
        } catch (Throwable $e) {
            error_log("[Prontoo permissions] " . $e->getMessage());
            flash("Não foi possível salvar as permissões.", "bad");
            redirect("permissions", ["role" => $role]);
        }
    }
    $rules = permission_rules_for_role($selected, $cid);
    $roleBlocks = "";
    foreach ($roles as $role => $label) {
        $count = 0;
        $enabled = 0;
        foreach ($modules as $module => $m) {
            if (!permission_module_available_for_role($role, $module)) {
                continue;
            }
            $count++;
            foreach ($ops as $op => $opLabel) {
                if (
                    permission_operation_supported($module, $op) &&
                    !empty(
                        ($role === $selected
                            ? $rules
                            : permission_rules_for_role($role, $cid))[$module][
                            $op
                        ]
                    )
                ) {
                    $enabled++;
                }
            }
        }
        $roleBlocks .=
            '<a class="permission-role-block ' .
            ($role === $selected ? "is-active" : "") .
            '" href="' .
            href("permissions", ["role" => $role]) .
            '"><span class="permission-role-icon">' .
            icon(role_icon($role, $cid)) .
            '</span><span class="permission-role-copy"><strong>' .
            e($label) .
            "</strong><small>" .
            e((string) $count) .
            " módulos · " .
            e((string) $enabled) .
            " liberações</small></span></a>";
    }
    $cards = "";
    foreach ($modules as $module => $m) {
        if (!permission_module_available_for_role($selected, $module)) {
            continue;
        }
        $toggles = "";
        $activeOps = 0;
        foreach ($ops as $op => $label) {
            if (!permission_operation_supported($module, $op)) {
                continue;
            }
            $checked = !empty($rules[$module][$op]);
            if ($checked) {
                $activeOps++;
            }
            $disabled = $selected === "gerente" ? " disabled" : "";
            $hidden =
                $selected === "gerente" && $checked
                    ? '<input type="hidden" name="allow[' .
                        e($module) .
                        "][" .
                        e($op) .
                        ']" value="1">'
                    : "";
            $toggles .=
                '<label class="permission-toggle permission-toggle-compact ' .
                ($checked ? "on" : "off") .
                '">' .
                $hidden .
                '<input type="checkbox" name="allow[' .
                e($module) .
                "][" .
                e($op) .
                ']" value="1" ' .
                ($checked ? "checked" : "") .
                $disabled .
                '><span class="permission-toggle-knob"></span><b>' .
                e($label) .
                "</b></label>";
        }
        if ($toggles === "") {
            $toggles =
                '<span class="permission-chip no">Sem operação configurável</span>';
        }
        $cards .=
            '<article class="permission-edit-card permission-module-card"><header><span class="permission-edit-title">' .
            icon($m["icon"]) .
            "<strong>" .
            e($m["label"]) .
            "</strong></span><small>" .
            e((string) $activeOps) .
            "/" .
            e((string) count($m["ops"] ?? [])) .
            ' ações</small></header><div class="permission-toggle-list permission-toggle-list-compact">' .
            $toggles .
            "</div>";
        if (!empty($m["note"])) {
            $cards .=
                '<p class="permission-module-note">' . e($m["note"]) . "</p>";
        }
        $cards .= "</article>";
    }
    $html =
        '<form method="post" class="permissions-compact-form permissions-ds-form">' .
        csrf_field() .
        '<input type="hidden" name="role_code" value="' .
        e($selected) .
        '"><div class="permissions-ds-shell"><aside class="permissions-role-rail"><div class="permissions-rail-head"><span>' .
        icon("badge") .
        "</span><div><strong>Cargos</strong><small>Escolha o perfil</small></div></div>" .
        $roleBlocks .
        '</aside><section class="permissions-editor-pane"><div class="permission-compact-toolbar permissions-editor-toolbar"><div><strong>' .
        e($roles[$selected]) .
        '</strong><span>Módulos e ações reais deste cargo, organizados em blocos compactos lado a lado.</span></div><span class="task-chip info">' .
        e((string) count($modules)) .
        ' módulos</span></div><div class="permission-edit-grid permissions-module-grid">' .
        $cards .
        '</div><div class="form-actions permission-save-actions permissions-sticky-actions"><a class="ghost" href="' .
        href("users") .
        '">' .
        icon("groups") .
        '<span>Colaboradores</span></a><button class="primary" type="submit">' .
        icon("save") .
        "<span>Salvar permissões</span></button></div></section></div></form>";
    page(
        "Permissões",
        page_head("Permissões", "") .
            card(
                $html,
                "permissions-role-card compact-permissions-card permissions-ds-card",
            ),
    );
}
