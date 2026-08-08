<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AuthOnboarding;

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

final class AuthOnboardingRuntimeOperations07
{
    private function __construct()
    {
    }

    public static function page_switch(): void
    
    {
    
        need_login();
        redirect("profile");
    
    }

    public static function page_onboarding(): void
    
    {
    
        $c = need_login();
        if (!is_responsible_doctor($c)) {
            redirect("appointments");
        }
        $cid = (int) $c["clinic_id"];
        if (function_exists("ensure_clinic_trial_active")) {
            ensure_clinic_trial_active($cid, true);
        }
        $cl = one(
            "SELECT id,display_name,legal_name,legal_document,phone,responsible_profession,clinic_icon,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,onboarding_done FROM pi_clinics WHERE id=?",
            [$cid],
        );
        if (!$cl) {
            redirect("appointments");
        }
        seed_clinic_roles($cid);
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $rolesEnabled = array_fill_keys(array_keys(PRONTOO_ROLES), 0);
            foreach ((array) ($_POST["roles"] ?? []) as $r) {
                if (isset(PRONTOO_ROLES[$r])) {
                    $rolesEnabled[$r] = 1;
                }
            }
            $rolesEnabled["medico"] = 1;
            $rolesEnabled["gerente"] = 1;
            db_begin_transaction();
            try {
                $uf = strtoupper(mb_trim((string) ($_POST["address_state"] ?? "")));
                $city = mb_trim((string) ($_POST["address_city"] ?? ""));
                $cityIbge = (int) ($_POST["address_city_ibge"] ?? 0);
                if (!isset(br_states()[$uf]) || $city === "" || $cityIbge <= 0) {
                    throw new RuntimeException(
                        "Escolha uma cidade da lista do IBGE.",
                    );
                }
                $tz = timezone_from_location($uf, $city);
                $profession = normalize_profession(
                    (string) ($_POST["responsible_profession"] ?? ""),
                );
                $clinicIcon = normalize_clinic_icon(
                    (string) ($_POST["clinic_icon"] ?? ($cl["clinic_icon"] ?? "")),
                );
                $accentColor = normalize_accent_color(
                    (string) ($_POST["accent_color"] ??
                        ($cl["accent_color"] ?? "")),
                );
                $trialStart = time();
                $trialEnd = function_exists("subscription_trial_end_from_start")
                    ? subscription_trial_end_from_start(
                        $trialStart,
                        default_trial_days(),
                    )
                    : $trialStart + max(1, default_trial_days()) * 86400;
                q(
                    "UPDATE pi_clinics SET display_name=?, phone=?, responsible_profession=?, clinic_icon=?, accent_color=?, address_line=?, address_state=?, address_city=?, address_city_ibge=?, timezone=?, onboarding_done=1, onboarding_completed_at=NOW(), subscription_status='trial', trial_started_at=COALESCE(NULLIF(trial_started_at,0),?), trial_ends_at=IF(trial_ends_at IS NULL OR trial_ends_at=0 OR trial_ends_at<NOW(),?,trial_ends_at), paid_until=NULL, updated_at=NOW() WHERE id=?",
                    [
                        mb_trim((string) $_POST["display_name"]),
                        phone_br((string) $_POST["phone"]),
                        $profession,
                        $clinicIcon,
                        $accentColor,
                        mb_trim((string) ($_POST["address_line"] ?? "")),
                        $uf,
                        $city,
                        $cityIbge,
                        $tz,
                        $trialStart,
                        $trialEnd,
                        $cid,
                    ],
                );
                $i = 1;
                foreach (PRONTOO_ROLES as $role => $default) {
                    $label =
                        $role === "medico"
                            ? $profession
                            : (trim(
                                (string) ($_POST["role_label"][$role] ?? $default),
                            ) ?:
                            $default);
                    $ico = default_role_icon($role);
                    q(
                        "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label), icon_name=IF(icon_name='' OR (icon_name='support_agent' AND role_code<>'recepcionista'),VALUES(icon_name),icon_name), enabled=VALUES(enabled), sort_order=VALUES(sort_order)",
                        [$cid, $role, $label, $ico, $rolesEnabled[$role], $i++],
                    );
                }
                $defaults = default_permissions();
                foreach (PRONTOO_ROLES as $role => $label) {
                    foreach (actions() as $key => $a) {
                        $allow = 0;
                        if ($rolesEnabled[$role]) {
                            $allow = in_array($key, $defaults[$role] ?? [], true)
                                ? 1
                                : 0;
                        }
                        if ($role === "gerente" && $rolesEnabled[$role]) {
                            $allow = 1;
                        }
                        q(
                            "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)",
                            [$cid, $role, $key, $allow],
                        );
                    }
                }
                $newUid = save_team_member($cid, $_POST);
                if ($newUid) {
                    audit("usuario_salvo", "usuario", $newUid, [
                        "clinic_id" => $cid,
                        "origem" => "wizard",
                    ]);
                }
                audit("onboarding_concluido", "consultorio", $cid, [
                    "clinic_id" => $cid,
                    "clinic_icon" => $clinicIcon,
                    "accent_color" => $accentColor,
                    "audit_body" =>
                        "Onboarding concluído com identidade visual inicial. Permissões permaneceram com padrão do sistema para ajuste posterior.",
                ]);
                db_commit();
                flash(
                    "Configuração inicial concluída. Você pode ajustar equipe, permissões e identidade visual depois em Meu Consultório.",
                );
                redirect("appointments");
            } catch (Throwable $e) {
                if (pdo()->inTransaction()) {
                    db_rollback();
                }
                error_log(
                    "[Prontoo onboarding] " .
                        get_class($e) .
                        " | " .
                        $e->getMessage() .
                        " | " .
                        $e->getFile() .
                        ":" .
                        $e->getLine(),
                );
                $msg = $e instanceof RuntimeException ? trim($e->getMessage()) : "";
                flash(
                    $msg !== ""
                        ? $msg
                        : "Não foi possível salvar a configuração inicial. Revise os campos e tente novamente.",
                    "bad",
                );
                redirect("onboarding");
            }
        }
        $roles = clinic_roles($cid, false);
        $enabled = q(
            "SELECT role_code,enabled FROM pi_clinic_roles WHERE clinic_id=?",
            [$cid],
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $roleCards = "";
        foreach (PRONTOO_ROLES as $role => $default) {
            $locked = in_array($role, ["medico", "gerente"], true);
            $checked = $locked || (int) ($enabled[$role] ?? 0) ? "checked" : "";
            $disabled = $locked ? "disabled" : "";
            $hidden = $locked
                ? '<input type="hidden" name="roles[]" value="' . e($role) . '">'
                : "";
            $desc = match ($role) {
                "recepcionista"
                    => "Atende pacientes, organiza contatos, confirma chegada e acompanha a agenda.",
                "assistente"
                    => "Prepara informações, triagem, tarefas e apoio antes ou depois do atendimento.",
                "medico"
                    => "Realiza o atendimento, registra o prontuário e emite documentos clínicos.",
                "gerente"
                    => "Organiza equipe, procedimentos e configurações do consultório.",
                default => "",
            };
            $roleCards .=
                '<article class="wiz-role"><label class="check"><input type="checkbox" name="roles[]" value="' .
                e($role) .
                '" ' .
                $checked .
                " " .
                $disabled .
                ">" .
                $hidden .
                " Usar este departamento</label>" .
                form_row(
                    "Nome visível",
                    input(
                        "role_label[" . $role . "]",
                        "text",
                        $roles[$role] ?? $default,
                        "required",
                    ),
                ) .
                "<p>" .
                e($desc) .
                "</p></article>";
        }
        $visual = clinic_visual_from_values(
            $cl["clinic_icon"] ?? null,
            $cl["accent_color"] ?? null,
            $cl["responsible_profession"] ?? null,
        );
        $visualStep =
            '<div class="clinic-visual-preview"><span class="brand-mark">' .
            icon($visual["icon"]) .
            "</span><div><strong>" .
            e($cl["display_name"] ?? "Consultório") .
            "</strong><small>Seus ambientes de trabalho terão as cores e ícones escolhidos.</small></div></div><h3>Escolha um ícone</h3>" .
            clinic_icon_picker($visual["icon"]) .
            "<h3>Escolha uma cor</h3>" .
            clinic_color_picker($visual["brand"]);
        $steps =
            '<div class="wizard-progress" aria-label="Etapas da configuração"><span class="is-active">1</span><span>2</span><span>3</span><span>4</span></div>';
        $form =
            '<section class="wizard onboarding-wizard" data-onboarding-wizard><div class="wizard-head"><span class="eyebrow">Seu consultório, suas regras</span><h1>Vamos deixar seu consultório com a sua cara</h1><p>Informe os dados abaixo. Se precisar, ajustes podem ser feitos mais tarde.</p></div><form method="post" class="compact onboarding-form clinic-settings-form">' .
            csrf_field() .
            $steps .
            '<section class="wizard-step is-active" data-wizard-step="0"><div class="wizard-step-title"><span>' .
            icon("home_health") .
            '</span><div><h2>Fale sobre o Consultório</h2><p>Confirme o nome, telefone, profissão e cidade de atuação.</p></div></div><div class="two">' .
            form_row(
                "Nome do consultório",
                input("display_name", "text", $cl["display_name"], "required"),
            ) .
            form_row(
                "Telefone principal",
                input("phone", "text", $cl["phone"] ?? ""),
            ) .
            "</div>" .
            profession_select_fields($cl) .
            clinic_location_fields($cl) .
            '</section><section class="wizard-step" data-wizard-step="1" hidden><div class="wizard-step-title"><span>' .
            icon("badge") .
            '</span><div><h2>Seus departamentos</h2><p>Os departamentos permitem que você distribua as tarefas por cargos.</p></div></div><div class="wizard-roles">' .
            $roleCards .
            '</div></section><section class="wizard-step" data-wizard-step="2" hidden><div class="wizard-step-title"><span>' .
            icon("person_add") .
            "</span><div><h2>Primeiro membro da equipe</h2><p>Você foi configurado como Administrativo e <span data-onboarding-profession>" .
            e(
                normalize_profession(
                    (string) ($cl["responsible_profession"] ?? "Profissional"),
                ),
            ) .
            '</span> do Consultório. Cadastre outros membros da equipe agora ou clique em Avançar para fazer isto mais tarde.</p></div></div><div class="two">' .
            form_row("Nome completo", input("team_name", "text")) .
            form_row(
                "CPF",
                input("team_cpf", "text", "", 'inputmode="numeric" maxlength="14"'),
            ) .
            '</div><div class="two">' .
            form_row("Nascimento", input("team_birth", "date")) .
            form_row("E-mail", input("team_email", "email")) .
            "</div>" .
            form_row(
                "Cargos",
                role_checkbox_group("team_roles", manageable_team_roles($cid), [
                    "recepcionista",
                ]),
            ) .
            '<div class="two">' .
            form_row(
                "Senha inicial",
                input(
                    "team_password",
                    "password",
                    "",
                    'minlength="8" maxlength="128" autocomplete="new-password" data-password-strength',
                ),
            ) .
            '</div></section><section class="wizard-step" data-wizard-step="3" hidden><div class="wizard-step-title"><span>' .
            icon("palette") .
            "</span><div><h2>Aparência</h2><p>Escolha o ícone e a cor que melhor representam o seu consultório.</p></div></div>" .
            $visualStep .
            '</section><div class="wizard-nav"><button type="button" class="ghost" data-wizard-prev hidden>Voltar</button><button type="button" class="primary" data-wizard-next>Avançar</button><button type="submit" class="primary" data-wizard-submit hidden>Concluir configuração</button></div></form></section>';
        page("Seu consultório, suas regras", $form);
    
    }

    public static function person_autosuggest_datalist(
        int $cid,
        string $id = "prontoo_person_suggestions",
    ): string 
    {
    
        $rows = q(
            "SELECT DISTINCT p.id,p.full_name,p.cpf,p.birth_date FROM pi_persons p JOIN (SELECT person_id FROM pi_patients WHERE clinic_id=? AND person_id IS NOT NULL UNION SELECT person_id FROM pi_leads WHERE clinic_id=? AND person_id IS NOT NULL) x ON x.person_id=p.id WHERE p.full_name<>'' ORDER BY p.full_name ASC LIMIT 500",
            [$cid, $cid],
        )->fetchAll();
        $h = '<datalist id="' . e($id) . '">';
        foreach ($rows as $r) {
            $label = trim(
                (!empty($r["cpf"])
                    ? "CPF " . mask((string) $r["cpf"])
                    : "CPF não informado") .
                    " · " .
                    (!empty($r["birth_date"])
                        ? "Nascimento " . date_br((string) $r["birth_date"])
                        : "nascimento não informado"),
                " ·",
            );
            $h .=
                '<option value="' .
                e((string) $r["full_name"]) .
                '" label="' .
                e($label) .
                '" data-cpf="' .
                e(mask((string) ($r["cpf"] ?? ""))) .
                '" data-birth="' .
                e(db_birth_date_input($r["birth_date"] ?? "")) .
                '"></option>';
        }
        return $h . "</datalist>";
    
    }

    public static function save_person_by_document(
        string $name,
        string $doc,
        ?string $birth = null,
    ): int 
    {
    
        $name = trim($name);
        $doc = only_digits($doc);
        if ($name === "") {
            throw new RuntimeException("Nome da pessoa não informado.");
        }
        if (strlen($doc) === 11) {
            return save_person_flexible($name, $doc, $birth);
        }
        if (strlen($doc) === 14) {
            if (!valid_cnpj($doc)) {
                throw new RuntimeException("Informe CNPJ válido.");
            }
            $id = val("SELECT id FROM pi_persons WHERE legal_document=? LIMIT 1", [
                $doc,
            ]);
            if ($id) {
                q(
                    "UPDATE pi_persons SET full_name=COALESCE(NULLIF(full_name,''),?), updated_at=NOW() WHERE id=?",
                    [$name, $id],
                );
                return (int) $id;
            }
            q(
                "INSERT INTO pi_persons (full_name,cpf,birth_date,legal_document,created_at) VALUES (?,NULL,NULL,?,NOW())",
                [$name, $doc],
            );
            return db_last_insert_id();
        }
        throw new RuntimeException("Informe CPF ou CNPJ válido.");
    
    }

    public static function login_telemetry_wave_data(): array
    
    {
        try {
            $requests = function_exists("telemetry_route_requests_series_20d")
      ? telemetry_route_requests_series_20d()
      : [];
            $records = function_exists(
      "telemetry_sequence_records_series_20d",
            )
      ? telemetry_sequence_records_series_20d()
      : [];
            return [
      "requests" => login_telemetry_wave_values($requests),
      "records" => login_telemetry_wave_values($records),
            ];
        } catch (Throwable $error) {
            error_log(
      "[Prontoo login telemetry wave] " . $error->getMessage(),
            );
            return ["requests" => [], "records" => []];
        }
    
    }

    public static function login_telemetry_wave_html(): string
    
    {
        $data = login_telemetry_wave_data();
        $requests = $data["requests"];
        $records = $data["records"];
        $maximum = max(
            1.0,
            $requests ? max($requests) : 0.0,
            $records ? max($records) : 0.0,
        );
        $requestsPath = login_telemetry_wave_path(
            $requests,
            $maximum,
        );
        $recordsPath = login_telemetry_wave_path(
            $records,
            $maximum,
        );
        return '<div class="login-telemetry-wave" data-login-telemetry-wave data-refresh-url="' .
            e(href("login_telemetry_wave")) .
            '" data-refresh-ms="900000" aria-hidden="true"><svg viewBox="0 0 1000 250" preserveAspectRatio="none" focusable="false" role="presentation"><path class="login-telemetry-wave-path is-requests" data-wave-series="requests" d="' .
            e($requestsPath) .
            '"/><path class="login-telemetry-wave-path is-records" data-wave-series="records" d="' .
            e($recordsPath) .
            '"/></svg></div>';
    
    }

    public static function page_login_telemetry_wave(): void
    
    {
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET") {
            http_response_code(405);
            header("Allow: GET");
            header("Content-Type: application/json; charset=utf-8");
            echo '{"error":"method_not_allowed"}';
            return;
        }
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: public, max-age=60, stale-while-revalidate=300");
        header("X-Content-Type-Options: nosniff");
        echo json_encode(
            login_telemetry_wave_data(),
            JSON_UNESCAPED_UNICODE |
      JSON_UNESCAPED_SLASHES |
      JSON_PRESERVE_ZERO_FRACTION,
        );
    
    }
}
