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

final class UsersPermissionsRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function clinic_enable_roles_from_active_user_links(int $uid): void
    
    {
    
        if ($uid <= 0) {
            return;
        }
        try {
            $rows = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions02.clinic_enable_roles_from_active_user_links.01', [$uid], [])->fetchAll();
            $byClinic = [];
            foreach ($rows as $r) {
                $cid = (int) ($r["clinic_id"] ?? 0);
                $role = (string) ($r["role_code"] ?? "");
                if ($cid > 0 && isset(PRONTOO_ROLES[$role])) {
                    $byClinic[$cid][] = $role;
                }
            }
            foreach ($byClinic as $cid => $roles) {
                \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_enable_roles_for_assignment(
                    (int) $cid,
                    $roles,
                    "active_user_link_repair",
                );
            }
        } catch (Throwable $e) {
            error_log("[Prontoo role enable repair] " . $e->getMessage());
        }
    
    }

    public static function sync_user_roles_for_clinic(
        int $cid,
        int $uid,
        array $roles,
        bool $propagate = true,
    ): array 
    {
    
        if ($cid <= 0 || $uid <= 0) {
            throw new RuntimeException("Colaborador ou consultório não informado.");
        }
        $manageable = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::manageable_team_roles($cid);
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
        \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_assert_minimum_roles_after_change($cid, $uid, $roles);
        \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->atomic(function () use ($cid, $uid, $roles): void {
            \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_enable_roles_for_assignment($cid, $roles, "roles_sync");
            $ownerFlag = (int) (\Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.permissions02.sync_user_roles_for_clinic.01', [$uid, $cid], []) ?: 0);
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result(
                'identity.permissions02.sync_user_roles_for_clinic.02',
                array_merge([$uid, $cid], $roles),
                ['role_count' => count($roles)],
            );
            foreach ($roles as $role) {
                \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions02.sync_user_roles_for_clinic.03', [$uid, $cid, $role, $ownerFlag], []);
            }
            $actualRoles = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::active_role_codes_for_user_in_clinic($cid, $uid);
            $expectedRoles = $roles;
            sort($actualRoles);
            sort($expectedRoles);
            if ($actualRoles !== $expectedRoles) {
                throw new RuntimeException(
                    "Não foi possível aplicar todos os cargos selecionados ao colaborador.",
                );
            }
        });
        if ($propagate) {
            \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::propagate_user_role_permissions($cid, $uid, "roles_sync");
        }
        return \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::active_role_codes_for_user_in_clinic($cid, $uid);
    
    }

    public static function active_role_codes_for_user_in_clinic(int $cid, int $uid): array
    
    {
    
        if ($cid <= 0 || $uid <= 0) {
            return [];
        }
        $rows = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions02.active_role_codes_for_user_in_clinic.01', [$uid, $cid], [])->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_unique(array_map("strval", $rows ?: [])));
    
    }

    public static function preferred_active_role_link_for_user(
        int $cid,
        int $uid,
        ?int $preferRoleId = null,
    ): ?array 
    {
    
        if ($cid <= 0 || $uid <= 0) {
            return null;
        }
        if ($preferRoleId && $preferRoleId > 0) {
            $r = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.permissions02.preferred_active_role_link_for_user.01', [$preferRoleId, $uid, $cid], []);
            if ($r) {
                return $r;
            }
        }
        return \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.permissions02.preferred_active_role_link_for_user.02', [$uid, $cid], []) ?:
            null;
    
    }

    public static function propagate_user_role_permissions(
        int $cid,
        int $uid,
        string $reason = "roles_updated",
    ): void 
    {
    
        if ($cid <= 0 || $uid <= 0) {
            return;
        }
        try {
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::seed_permissions($cid);
        } catch (Throwable $e) {
            error_log("[Prontoo role propagation seed] " . $e->getMessage());
        }
        $replacement = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::preferred_active_role_link_for_user(
            $cid,
            $uid,
            (int) ($_SESSION["uid"] ?? 0) === $uid &&
            (int) ($_SESSION["clinic_id"] ?? 0) === $cid
                ? (int) ($_SESSION["uc_id"] ?? 0)
                : null,
        );
        try {
            $newGeneration = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($uid);
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_retire_persistent_devices_for_user($uid);
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
        if (is_callable([\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::class, 'server_json_cache_clear_categories'])) {
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories([
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
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("permissoes_atualizadas", "usuario", $uid, [
                "clinic_id" => $cid,
                "motivo" => $reason,
                "audit_body" =>
                    "Permissões efetivas propagadas imediatamente após alteração dos cargos ativos do colaborador.",
            ]);
        } catch (Throwable $e) {
            error_log("[Prontoo role propagation audit] " . $e->getMessage());
        }
    
    }

    public static function role_labels_from_codes(array $codes, ?int $cid = null): array
    
    {
    
        $labels = [];
        foreach ($codes as $code) {
            $labels[] = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for((string) $code, $cid);
        }
        return $labels;
    
    }

    public static function role_badges_html(array $codes, ?int $cid = null): string
    
    {
    
        if (!$codes) {
            return '<span class="muted">Sem cargo ativo</span>';
        }
        $h = '<span class="role-badge-list">';
        foreach ($codes as $code) {
            $h .=
                '<span class="role-badge">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for((string) $code, $cid)) .
                "</span>";
        }
        return $h . "</span>";
    
    }

    public static function team_options(int $cid): array
    
    {
    
        $links = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions02.team_options.01', [$cid], [])->fetchAll();
        $users = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "users_status",
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($links, "user_id"),
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

    public static function clinic_role_user_ids(int $cid, array $roles): array
    
    {
    
        $roles = array_values(array_filter(array_unique($roles)));
        if (!$roles) {
            return [];
        }
        $rows = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result(
            'identity.permissions02.clinic_role_user_ids.01',
            array_merge([$cid], $roles),
            ['role_count' => count($roles)],
        )->fetchAll();
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int) $r["user_id"];
        }
        return array_values(array_unique($ids));
    
    }

    public static function permission_module_defs(): array
    
    {
    
        $acts = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::actions();
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

    public static function permission_operation_supported(string $module, string $op): bool
    
    {
    
        $defs = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_defs();
        return isset($defs[$module]) &&
            in_array($op, $defs[$module]["ops"] ?? [], true);
    
    }

    public static function permission_module_available_for_role(
        string $role,
        string $module,
    ): bool 
    {
    
        $acts = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::actions();
        return isset($acts[$module]) &&
            in_array($role, $acts[$module]["roles"] ?? [], true);
    
    }

    public static function permission_operation_configurable(
        string $role,
        string $module,
        string $op,
    ): bool 
    {
    
        return \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_available_for_role($role, $module) &&
            \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_supported($module, $op);
    
    }

    public static function permission_default_for(
        string $role,
        string $module,
        string $op,
        int $cid,
    ): bool 
    {
    
        foreach (\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations03::collaborator_permission_matrix_base($role, $cid) as $row) {
            if (($row["key"] ?? "") === $module) {
                return !empty($row[$op]);
            }
        }
        return false;
    
    }

    public static function permission_rules_for_role(string $role, int $cid): array
    
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
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions02.permission_rules_for_role.01', [$cid, $role], [])->fetchAll()
                    as $row
                ) {
                    $stored[(string) $row["action_key"]][(string) $row["operation_key"]] =
                        (int) $row["allowed"] === 1;
                }
            } catch (Throwable $e) {
                error_log("[Prontoo permission rules] " . $e->getMessage());
            }
            $baseDefaults = [];
            foreach (\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations03::collaborator_permission_matrix_base($role, $cid) as $baseRow) {
                $baseKey = (string) ($baseRow["key"] ?? "");
                if ($baseKey !== "") {
                    $baseDefaults[$baseKey] = $baseRow;
                }
            }
            $matrix = [];
            foreach (\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_defs() as $module => $definition) {
                foreach (\Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::permission_operations() as $operation => $label) {
                    if (!\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_configurable($role, $module, $operation)) {
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
                        if (\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_configurable($role, $module, $operation)) {
                            $matrix[$module][$operation] = true;
                        }
                    }
                }
            }
            return $matrix;
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember']) && $cid > 0) {
            $matrix = \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "permissions",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("role_rules", [$cid, $role]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("permissions"),
                $loader,
                ["clinic:" . $cid, "role:" . $role],
            );
        } else {
            $matrix = $loader();
        }
        return $requestCache[$requestKey] = is_array($matrix) ? $matrix : [];
    
    }

    public static function persist_permission_rules(int $cid, string $role, array $posted): void
    
    {
    
        $mods = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_defs();
        $ops = \Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::permission_operations();
        foreach ($mods as $module => $m) {
            $row = $posted[$module] ?? [];
            $any = false;
            foreach (["add", "edit", "delete"] as $op) {
                if (
                    \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_configurable($role, $module, $op) &&
                    isset($row[$op])
                ) {
                    $any = true;
                }
            }
            $view =
                \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_configurable($role, $module, "view") &&
                (isset($row["view"]) || $any);
            foreach ($ops as $op => $label) {
                $supported = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_configurable($role, $module, $op);
                $allowed =
                    $supported && ($op === "view" ? $view : isset($row[$op]));
                if ($role === "gerente" && $supported) {
                    $allowed = true;
                }
                \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions02.persist_permission_rules.01', [$cid, $role, $module, $op, $allowed ? 1 : 0], []);
            }
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions02.persist_permission_rules.02', [$cid, $role, $module, $view ? 1 : 0], []);
        }
        unset(
            $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"][
                $cid . "|" . $role
            ],
        );
    
    }
}
