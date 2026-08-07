<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\UsersPermissions;

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

final class UsersPermissionsRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function page_user(): void
    
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

    public static function page_permissions(): void
    
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
}
