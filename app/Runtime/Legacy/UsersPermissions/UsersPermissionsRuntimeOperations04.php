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

final class UsersPermissionsRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function page_users(): void
    
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
}
