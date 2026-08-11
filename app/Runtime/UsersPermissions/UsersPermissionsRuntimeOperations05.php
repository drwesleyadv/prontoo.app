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

final class UsersPermissionsRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function page_user(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("users");
        $cid = (int) $c["clinic_id"];
        $uid = (int) ($_GET["id"] ?? 0);
        $person = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.permissions05.page_user.01', [$uid, $cid], []);
        if (!$person) {
            http_response_code(404);
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Colaborador",
                '<div class="empty">Colaborador não encontrado neste consultório.</div>',
            );
            return;
        }
        $manageable = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::manageable_team_roles($cid);
        $roleRows = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions05.page_user.02', [$uid, $cid], [])->fetchAll();
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
                    $roles = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::selected_team_roles($_POST, $cid);
                    if ($name === "") {
                        throw new RuntimeException("Revise nome e cargos.");
                    }
                    $commonProfile = [];
                    if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'person_common_profile_update'])) {
                        $commonProfile = array_merge(
                            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_from_array($_POST, ""),
                            [
                                "legal_type" => "cpf",
                                "legal_document" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits(
                                    (string) ($person["cpf"] ?? ""),
                                ),
                                "email" => $email !== "" ? $email : null,
                            ],
                        );
                    }
                    $activeRoles = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::userPermission()
                        ->updateTeamMember(
                            $cid,
                            $uid,
                            (int) $person["person_id"],
                            $name,
                            $email,
                            $roles,
                            $_POST,
                            $commonProfile,
                            static function (int $personId, array $profile): void {
                                if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'person_common_profile_update'])) {
                                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_update(
                                        $personId,
                                        $profile,
                                    );
                                }
                            },
                            static fn(int $personId): mixed =>
                                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_refresh_verified(
                                    $personId,
                                ),
                            static fn(int $clinicId, int $userId, array $selected, bool $propagate): array =>
                                \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::sync_user_roles_for_clinic(
                                    $clinicId,
                                    $userId,
                                    $selected,
                                    $propagate,
                                ),
                            static fn(int $clinicId, int $userId, array $payload): mixed =>
                                \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::save_user_work_hours(
                                    $clinicId,
                                    $userId,
                                    $payload,
                                ),
                            static fn(int $clinicId, int $userId, string $reason): mixed =>
                                \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::propagate_user_role_permissions(
                                    $clinicId,
                                    $userId,
                                    $reason,
                                ),
                            static fn(...$arguments): bool =>
                                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                                    ...$arguments,
                                ),
                        );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Colaborador atualizado e permissões aplicadas.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("user", ["id" => $uid]);
                }
                if ($act === "deactivate") {
                    if ($uid === (int) $c["user"]["id"]) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "Você não pode desativar seu próprio vínculo.",
                            "bad",
                        );
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("user", ["id" => $uid]);
                    }
                    \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_assert_can_deactivate_user_roles($cid, $uid);
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions05.page_user.05', [$uid, $cid], []);
                    \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::propagate_user_role_permissions($cid, $uid, "deactivate");
                    if (
                        (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.permissions05.page_user.06', [$uid], []) === 0
                    ) {
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions05.page_user.07', [$uid], []);
                    }
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("usuario_desativado", "usuario", $uid, [
                        "clinic_id" => $cid,
                        "audit_body" =>
                            "Colaborador desativado e sessões/dispositivos do consultório revogados.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Colaborador desativado neste consultório.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("users");
                }
            } catch (Throwable $e) {
                error_log("[Prontoo user detail] " . $e->getMessage());
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                        $e,
                        "Não foi possível concluir a alteração do colaborador.",
                    ),
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("user", ["id" => $uid]);
            }
        }
        $back =
            '<a class="ghost small" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("users") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            '<span>Voltar para Colaboradores</span></a><a class="ghost small" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("permissions") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("shield_person") .
            "<span>Editar permissões</span></a>";
        $status =
            (int) $person["user_active"] && $activeRoles ? "Ativo" : "Inativo";
        $deactivate = "";
        if ($activeRoles && $uid !== (int) $c["user"]["id"]) {
            $deactivate =
                '<form method="post" class="inline" onsubmit="return confirm(&quot;Desativar o vínculo deste colaborador neste consultório?&quot;)">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="deactivate"><button class="danger small" type="submit">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("person_remove") .
                "<span>Desativar colaborador</span></button></form>";
        }
        $form =
            '<form method="post" class="compact collaborator-edit-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="update"><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome completo",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("name", "text", $person["name"] ?? "", "required"),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF",
                '<input type="text" value="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($person["cpf"] ?? "")) .
                    '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">',
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nascimento",
                '<input type="date" value="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($person["birth_date"] ?? "")) .
                    '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">',
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "E-mail de acesso",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("email", "email", $person["email"] ?? ""),
            ) .
            "</div>" .
            (is_callable([\Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::class, 'person_common_profile_fields_html'])
                ? \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::person_common_profile_fields_html($cid, $person, "", false, true)
                : "") .
            '<div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Cargos",
                \Prontoo\Presentation\UsersPermissions\UsersPermissionsPresentationOperations01::role_checkbox_group("role_codes", $manageable, $activeRoles),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Status",
                '<input type="text" value="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status) . '" readonly>',
            ) .
            "</div>" .
            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::work_hours_form_html($cid, $uid) .
            '<div class="form-actions">' .
            $deactivate .
            '<button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
            "<span>Salvar colaborador</span></button></div></form>";
        $perm =
            '<h2>Permissões do colaborador</h2><p class="muted-copy">As permissões exibidas consideram os cargos ativos. Na sessão, o colaborador frequenta apenas um Ambiente por vez.</p>' .
            \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations03::collaborator_permission_summary($activeRoles, $cid);
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Colaborador",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Colaborador", "", $back) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    "<h2>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($person["name"] ?? "Colaborador") . "</h2>" . $form,
                    "collaborator-profile-card",
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($perm, "collaborator-permissions-card"),
        );
    
    }

    public static function page_permissions(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("permissions");
        $cid = (int) $c["clinic_id"];
        $roles = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::manageable_team_roles($cid);
        $modules = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_defs();
        $ops = \Prontoo\Domain\UsersPermissions\UsersPermissionsDomainOperations01::permission_operations();
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
                \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::persist_permission_rules($cid, $role, $_POST["allow"] ?? []);
                foreach (
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.permissions05.page_permissions.01', [$cid, $role], [])->fetchAll(PDO::FETCH_COLUMN)
                    as $affectedUid
                ) {
                    \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::propagate_user_role_permissions(
                        $cid,
                        (int) $affectedUid,
                        "permission_matrix_updated",
                    );
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("permissoes_atualizadas", "permissoes", $cid, [
                    "perfil" => $role,
                    "audit_body" =>
                        "Permissões do cargo " .
                        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, $cid) .
                        " atualizadas e propagadas para colaboradores ativos.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Permissões atualizadas para " .
                        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, $cid) .
                        ".",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("permissions", ["role" => $role]);
            } catch (Throwable $e) {
                error_log("[Prontoo permissions] " . $e->getMessage());
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Não foi possível salvar as permissões.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("permissions", ["role" => $role]);
            }
        }
        $rules = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_rules_for_role($selected, $cid);
        $roleBlocks = "";
        foreach ($roles as $role => $label) {
            $count = 0;
            $enabled = 0;
            foreach ($modules as $module => $m) {
                if (!\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_available_for_role($role, $module)) {
                    continue;
                }
                $count++;
                foreach ($ops as $op => $opLabel) {
                    if (
                        \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_supported($module, $op) &&
                        !empty(
                            ($role === $selected
                                ? $rules
                                : \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_rules_for_role($role, $cid))[$module][
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
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("permissions", ["role" => $role]) .
                '"><span class="permission-role-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_icon($role, $cid)) .
                '</span><span class="permission-role-copy"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $count) .
                " módulos · " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $enabled) .
                " liberações</small></span></a>";
        }
        $cards = "";
        foreach ($modules as $module => $m) {
            if (!\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_available_for_role($selected, $module)) {
                continue;
            }
            $toggles = "";
            $activeOps = 0;
            foreach ($ops as $op => $label) {
                if (!\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_supported($module, $op)) {
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
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($module) .
                            "][" .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($op) .
                            ']" value="1">'
                        : "";
                $toggles .=
                    '<label class="permission-toggle permission-toggle-compact ' .
                    ($checked ? "on" : "off") .
                    '">' .
                    $hidden .
                    '<input type="checkbox" name="allow[' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($module) .
                    "][" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($op) .
                    ']" value="1" ' .
                    ($checked ? "checked" : "") .
                    $disabled .
                    '><span class="permission-toggle-knob"></span><b>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                    "</b></label>";
            }
            if ($toggles === "") {
                $toggles =
                    '<span class="permission-chip no">Sem operação configurável</span>';
            }
            $cards .=
                '<article class="permission-edit-card permission-module-card"><header><span class="permission-edit-title">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($m["icon"]) .
                "<strong>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($m["label"]) .
                "</strong></span><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $activeOps) .
                "/" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) count($m["ops"] ?? [])) .
                ' ações</small></header><div class="permission-toggle-list permission-toggle-list-compact">' .
                $toggles .
                "</div>";
            if (!empty($m["note"])) {
                $cards .=
                    '<p class="permission-module-note">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($m["note"]) . "</p>";
            }
            $cards .= "</article>";
        }
        $html =
            '<form method="post" class="permissions-compact-form permissions-ds-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="role_code" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($selected) .
            '"><div class="permissions-ds-shell"><aside class="permissions-role-rail"><div class="permissions-rail-head"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
            "</span><div><strong>Cargos</strong><small>Escolha o perfil</small></div></div>" .
            $roleBlocks .
            '</aside><section class="permissions-editor-pane"><div class="permission-compact-toolbar permissions-editor-toolbar"><div><strong>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($roles[$selected]) .
            '</strong><span>Módulos e ações reais deste cargo, organizados em blocos compactos lado a lado.</span></div><span class="task-chip info">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) count($modules)) .
            ' módulos</span></div><div class="permission-edit-grid permissions-module-grid">' .
            $cards .
            '</div><div class="form-actions permission-save-actions permissions-sticky-actions"><a class="ghost" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("users") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("groups") .
            '<span>Colaboradores</span></a><button class="primary" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
            "<span>Salvar permissões</span></button></div></section></div></form>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Permissões",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Permissões", "") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    $html,
                    "permissions-role-card compact-permissions-card permissions-ds-card",
                ),
        );
    
    }
}
