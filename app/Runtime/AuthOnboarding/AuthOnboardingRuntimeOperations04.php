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

final class AuthOnboardingRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function login_lock(string $cpf): int
    
    {
    
        try {
            [$pair, $subject, $ip] = login_bucket_keys($cpf);
            $now = time();
            $r = one(
                "SELECT MAX(GREATEST(0,locked_until-?)) AS wait_seconds FROM pi_login_locks WHERE (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?)",
                [
                    $now,
                    $pair[0],
                    $pair[1],
                    $subject[0],
                    $subject[1],
                    $ip[0],
                    $ip[1],
                ],
            );
            if (!$r) {
                return 0;
            }
            return max(0, (int) ($r["wait_seconds"] ?? 0));
        } catch (Throwable $e) {
            error_log("[Prontoo login_lock] " . $e->getMessage());
            return login_session_wait();
        }
    
    }

    public static function login_fail(string $cpf): int
    
    {
        
        $seconds = 60;
        try {
            [$pair, $subject, $ip] = login_bucket_keys($cpf);
            q(
                "INSERT INTO pi_login_locks (subject_hash,ip_hash,fail_count,locked_until,updated_at)
                 VALUES
                   (?,?,1,UNIX_TIMESTAMP()+2,NOW()),
                   (?,?,1,0,NOW()),
                   (?,?,1,0,NOW())
                 ON DUPLICATE KEY UPDATE
                   locked_until=CASE
                     WHEN subject_hash=? AND ip_hash=? THEN
                       UNIX_TIMESTAMP()+CAST(
                         LEAST(900,2*POW(2,LEAST(10,GREATEST(0,fail_count))))
                         AS UNSIGNED
                       )
                     WHEN subject_hash=? AND ip_hash=? THEN
                       CASE
                         WHEN fail_count+1>=5 THEN UNIX_TIMESTAMP()+CAST(
                           LEAST(86400,60*POW(2,LEAST(10,GREATEST(0,fail_count+1-5))))
                           AS UNSIGNED
                         )
                         ELSE COALESCE(locked_until,0)
                       END
                     ELSE
                       CASE
                         WHEN fail_count+1>=20 THEN UNIX_TIMESTAMP()+CAST(
                           LEAST(3600,60*POW(2,LEAST(10,GREATEST(0,fail_count+1-20))))
                           AS UNSIGNED
                         )
                         ELSE COALESCE(locked_until,0)
                       END
                   END,
                   fail_count=LEAST(100000,fail_count+1),
                   updated_at=NOW()",
                [
                    $pair[0],
                    $pair[1],
                    $subject[0],
                    $subject[1],
                    $ip[0],
                    $ip[1],
                    $pair[0],
                    $pair[1],
                    $subject[0],
                    $subject[1],
                ],
            );
            $seconds = max(1, login_lock($cpf));
        } catch (Throwable $e) {
            error_log("[Prontoo login_fail] " . $e->getMessage());
        }
        return $seconds;
    
    }

    public static function login_clear(string $cpf): void
    
    {
    
        try {
            [$pair, $subject] = login_bucket_keys($cpf);
            q(
                "DELETE FROM pi_login_locks WHERE (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?)",
                [$pair[0], $pair[1], $subject[0], $subject[1]],
            );
        } catch (Throwable $e) {
            error_log("[Prontoo login_clear] " . $e->getMessage());
        }
    
    }

    public static function mark_login_success(int $uid): void
    
    {
        
        try {
            q(
                "UPDATE pi_users SET failed_login_count=0, locked_until=NULL, last_login_at=NOW() WHERE id=?",
                [$uid],
            );
        } catch (Throwable $e) {
            error_log("[Prontoo mark_login_success] " . $e->getMessage());
        }
    
    }

    public static function page_signup(): void
    
    {
    
        $currentCtx = ctx();
        if ($currentCtx) {
            redirect(
                ($currentCtx["scope"] ?? "") === "global"
                    ? "admin_painel"
                    : "painel",
            );
        }
        if (function_exists("clinic_signup_blocked") && clinic_signup_blocked()) {
            page(
                "Cadastro pausado",
                '<section class="auth widebox"><h1>A inauguração de consultórios foi pausada</h1><p>Atingimos nossa capacidade máxima de consultórios hoje. Tente novamente mais tarde.</p><p><a class="ghost" href="' .
                    e(href("login")) .
                    '">Tentarei mais tarde</a></p></section>',
                ["public" => true, "robots" => "noindex,nofollow"],
            );
            return;
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            if (empty($_POST["trial_accept"])) {
                flash("Confirme que deseja experimentar sem custo.", "bad");
                redirect("signup");
            }
            $pass = (string) $_POST["password"];
            $cpf = only_digits((string) $_POST["cpf"]);
            $doc = only_digits((string) $_POST["legal_document"]);
            if (
                security_rate_limit(security_client_bucket("signup"), 5, 3600) ||
                security_rate_limit(security_ip_bucket("signup"), 20, 3600) ||
                ($cpf !== "" &&
                    security_rate_limit(
                        security_value_bucket("signup_cpf", $cpf),
                        3,
                        3600,
                    )) ||
                ($doc !== "" &&
                    security_rate_limit(
                        security_value_bucket("signup_doc", $doc),
                        4,
                        3600,
                    ))
            ) {
                flash(
                    "Muitas tentativas de cadastro. Aguarde alguns instantes antes de tentar novamente.",
                    "bad",
                );
                redirect("signup");
            }
            $legalType = (string) ($_POST["legal_type"] ?? "");
            $profession = normalize_profession(
                (string) ($_POST["responsible_profession"] ?? ""),
            );
            if (!valid_cpf($cpf)) {
                flash("Este CPF não existe.", "bad");
                redirect("signup");
            }
            if (!valid_birth_date((string) $_POST["birth_date"])) {
                flash(
                    "Informe uma data de nascimento válida para o responsável pelo consultório.",
                    "bad",
                );
                redirect("signup");
            }
            if (!in_array($legalType, ["cpf", "cnpj"], true)) {
                flash(
                    "Escolha se o consultório será pessoa física ou jurídica.",
                    "bad",
                );
                redirect("signup");
            }
            if ($legalType === "cpf" && !valid_cpf($doc)) {
                flash("Este CPF não existe.", "bad");
                redirect("signup");
            }
            if ($legalType === "cnpj" && !valid_cnpj($doc)) {
                flash("Informe CNPJ válido para o consultório.", "bad");
                redirect("signup");
            }
            $uf = strtoupper(mb_trim((string) ($_POST["address_state"] ?? "")));
            $city = mb_trim((string) ($_POST["address_city"] ?? ""));
            $cityIbge = (int) ($_POST["address_city_ibge"] ?? 0);
            if (!isset(br_states()[$uf]) || $city === "" || $cityIbge <= 0) {
                flash("Escolha a cidade de atuação na lista do IBGE.", "bad");
                redirect("signup");
            }
            $timezone = timezone_from_location($uf, $city);
            $accentColor = PRONTOO_DEFAULT_ACCENT_COLOR;
            db_begin_transaction();
            try {
                $pid = upsert_person(
                    mb_trim((string) $_POST["doctor_name"]),
                    $cpf,
                    (string) $_POST["birth_date"],
                );
                lock_person_user_identity($pid);
                $existing = one(
                    "SELECT id,password_hash,active,email FROM pi_users WHERE person_id=? LIMIT 1",
                    [$pid],
                );
                if ($existing) {
                    if (!(int) $existing["active"]) {
                        throw new RuntimeException(
                            "Usuário existente está inativo.",
                        );
                    }
                    if (
                        !password_verify($pass, (string) $existing["password_hash"])
                    ) {
                        db_rollback();
                        flash(
                            "Não foi possível confirmar as credenciais informadas. Revise CPF, senha e dados do consultório.",
                            "bad",
                        );
                        redirect("signup");
                    }
                    $uid = (int) $existing["id"];
                    $email = mb_trim((string) $_POST["email"]);
                    if ($email !== "" && empty($existing["email"])) {
                        q(
                            "UPDATE pi_users SET email=?,updated_at=NOW() WHERE id=?",
                            [$email, $uid],
                        );
                    }
                } else {
                    if (!password_ok($pass)) {
                        db_rollback();
                        flash(
                            "Use senha com 8 a 128 caracteres que não seja uma senha comum.",
                            "bad",
                        );
                        redirect("signup");
                    }
                    q(
                        "INSERT INTO pi_users (person_id,name,email,password_hash,active,created_at) VALUES (?,?,?,?,1,NOW())",
                        [
                            $pid,
                            mb_trim((string) $_POST["doctor_name"]),
                            mb_trim((string) $_POST["email"]) ?: null,
                            password_hash_secure($pass),
                        ],
                    );
                    $uid = db_last_insert_id();
                    counter_inc("users_total");
                }
                $trialStart = time();
                $trialEnd = function_exists("subscription_trial_end_from_start")
                    ? subscription_trial_end_from_start(
                        $trialStart,
                        default_trial_days(),
                    )
                    : $trialStart + max(1, default_trial_days()) * 86400;
                q(
                    "INSERT INTO pi_clinics (legal_type,legal_name,legal_document,display_name,phone,responsible_profession,owner_user_id,manager_user_id,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,onboarding_done,onboarding_completed_at,trial_started_at,trial_ends_at,subscription_status,monthly_price_cents,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,'trial',?,?)",
                    [
                        $legalType,
                        mb_trim((string) $_POST["legal_name"]),
                        $doc,
                        mb_trim((string) $_POST["display_name"]),
                        phone_br((string) $_POST["phone"]),
                        $profession,
                        $uid,
                        $uid,
                        $accentColor,
                        mb_trim((string) ($_POST["address_line"] ?? "")),
                        $uf,
                        $city,
                        $cityIbge,
                        $timezone,
                        $trialStart,
                        $trialStart,
                        $trialEnd,
                        default_monthly_price_cents(),
                        $trialStart,
                    ],
                );
                $cid = db_last_insert_id();
                if (function_exists("ensure_clinic_trial_active")) {
                    ensure_clinic_trial_active($cid, false);
                }
                counter_inc("clinics_total");
                q(
                    "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active) VALUES (?,?,?,1,1) ON DUPLICATE KEY UPDATE is_owner=1, active=1",
                    [$uid, $cid, "gerente"],
                );
                q(
                    "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active) VALUES (?,?,?,1,1) ON DUPLICATE KEY UPDATE is_owner=1, active=1",
                    [$uid, $cid, "medico"],
                );
                $managerRoleId = (int) (val(
                    "SELECT id FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND role_code='gerente' AND active=1 LIMIT 1",
                    [$uid, $cid],
                ) ?: 0);
                if ($managerRoleId <= 0) {
                    throw new RuntimeException(
                        "Não foi possível definir o ambiente Administrativo inicial.",
                    );
                }
                $ownerRoles = q(
                    "SELECT role_code FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND active=1 AND role_code IN ('gerente','medico')",
                    [$uid, $cid],
                )->fetchAll(PDO::FETCH_COLUMN);
                $ownerRoles = array_values(
                    array_unique(array_map("strval", $ownerRoles ?: [])),
                );
                sort($ownerRoles);
                if ($ownerRoles !== ["gerente", "medico"]) {
                    throw new RuntimeException(
                        "Não foi possível registrar os ambientes Administrativo e Profissional do responsável.",
                    );
                }
                seed_permissions($cid);
                seed_clinic_roles($cid);
                q(
                    "UPDATE pi_clinic_roles SET label=? WHERE clinic_id=? AND role_code='medico'",
                    [$profession, $cid],
                );
                audit("consultorio_criado", "consultorio", $cid, [
                    "clinic_id" => $cid,
                    "nome" => $_POST["display_name"],
                    "owner_user_id" => $uid,
                    "cidade" => $city,
                    "uf" => $uf,
                    "timezone" => $timezone,
                    "accent_color" => $accentColor,
                    "onboarding_done" => 1,
                ]);
                db_commit();
                login_last_credential_remember(
                    $uid,
                    "clinic",
                    $managerRoleId,
                );
                flash(
                    "Seu consultório foi inaugurado e configurado. Insira CPF e Senha para entrar como Administrativo.",
                );
                redirect("login");
            } catch (Throwable $e) {
                if (pdo()->inTransaction()) {
                    db_rollback();
                }
                error_log("[Prontoo signup] " . $e->getMessage());
                $reason =
                    "Revise os dados informados, a senha atual do CPF ou o documento do consultório.";
                flash("Não foi possível concluir o cadastro. " . $reason, "bad");
                redirect("signup");
            }
        }
        $signupPrice = money_br(default_monthly_price_cents());
        $signupTrialDays = default_trial_days();
        $signupTrialLabel = trial_period_label($signupTrialDays);
        $signupTrialCopy =
            $signupTrialDays > 0
                ? "Use por " .
                    $signupTrialLabel .
                    " sem compromisso. Se gostar, renove por mais 30 dias por " .
                    $signupPrice .
                    "."
                : "Os primeiros 30 dias são uma cortesia. Aproveite!";
        $signupConsent =
            $signupTrialDays > 0
                ? "Quero experimentar por " .
                    $signupTrialLabel .
                    ", sem nenhum compromisso."
                : "Quero começar sem fidelidade e sem compromisso.";
        $responsibleFields =
            '<div class="signup-ds-grid two">' .
            form_row(
                "CPF",
                input(
                    "cpf",
                    "text",
                    "",
                    'required inputmode="numeric" maxlength="14" autocomplete="username" placeholder="000.000.000-00" data-person-cpf-lookup="' .
                        e(href("person_lookup")) .
                        '" data-person-lookup-context="signup" data-person-name-target="doctor_name" data-person-birth-target="birth_date"',
                ),
            ) .
            form_row(
                "Nome completo",
                input(
                    "doctor_name",
                    "text",
                    "",
                    'required autocomplete="name" placeholder="Nome do responsável"',
                ),
            ) .
            '</div><div class="signup-ds-grid two">' .
            form_row("Nascimento", input("birth_date", "date", "", "required")) .
            form_row(
                "E-mail",
                input(
                    "email",
                    "email",
                    "",
                    'required autocomplete="email" placeholder="email@exemplo.com"',
                ),
            ) .
            "</div>" .
            profession_select_fields() .
            form_row(
                "Senha",
                '<div class="password-field">' .
                    input(
                        "password",
                        "password",
                        "",
                        'required minlength="8" maxlength="128" autocomplete="new-password" placeholder="Senha nova ou senha atual se o CPF já existir" data-password-strength data-password-toggle',
                    ) .
                    '<button type="button" class="password-toggle" data-password-toggle-button aria-label="Mostrar senha">' .
                    icon("visibility") .
                    "</button></div>",
            );
        $clinicFields =
            '<div class="signup-ds-grid two">' .
            select_label(
                "Tipo de pessoa",
                "legal_type",
                ["cpf" => "Pessoa física", "cnpj" => "Pessoa jurídica"],
                null,
                "required",
            ) .
            form_row(
                "CPF/CNPJ do consultório",
                input(
                    "legal_document",
                    "text",
                    "",
                    'required inputmode="numeric" placeholder="Documento do consultório" data-doc-mask data-document-validate',
                ),
            ) .
            '</div><div class="signup-ds-grid two">' .
            form_row(
                "Nome/Razão social",
                input(
                    "legal_name",
                    "text",
                    "",
                    'required placeholder="Nome jurídico do consultório"',
                ),
            ) .
            form_row(
                "Nome fantasia",
                input(
                    "display_name",
                    "text",
                    "",
                    'required placeholder="Nome exibido no sistema"',
                ),
            ) .
            "</div>" .
            form_row(
                "Telefone principal",
                input(
                    "phone",
                    "text",
                    "",
                    'autocomplete="tel" inputmode="tel" placeholder="(00) 00000-0000"',
                ),
            ) .
            clinic_location_fields();
        $form =
            '<section class="auth widebox signup-card signup-steps-card signup-ds-shell signup-screen-flow"><header class="signup-ds-hero"><div class="signup-ds-hero-main"><span class="eyebrow signup-opening-label">' .
            icon("home_health") .
            '<span>Novo consultório</span></span><h1>Criar consultório</h1><p>Configure o acesso inicial, identifique o responsável e registre o consultório em um fluxo simples e seguro. Cada etapa aparece em uma tela própria.</p></div><div class="auth-brandmark signup-brandmark" data-app-favicon-brandmark><img class="auth-brandmark-favicon app-brandmark-img" src="/public/assets/app-icon-' .
            e(PRONTOO_ASSET_REV) .
            '.png" alt="" aria-hidden="true"></div></header><ol class="signup-ds-stepper" aria-label="Etapas para criar consultório"><li class="is-active" data-signup-indicator="0"><b>1</b><span><strong>Experimente</strong><small>Sem compromisso</small></span></li><li data-signup-indicator="1"><b>2</b><span><strong>Responsável</strong><small>CPF e acesso</small></span></li><li data-signup-indicator="2"><b>3</b><span><strong>Consultório</strong><small>Dados principais</small></span></li></ol><form method="post" class="compact signup-form signup-wizard signup-ds-form" data-signup-steps>' .
            csrf_field() .
            '<section class="signup-step signup-ds-step signup-screen-panel is-active" data-signup-step="0"><div class="signup-screen-kicker"><span>Etapa 1 de 3</span><strong>Experimente sem compromisso</strong></div><div class="signup-ds-layout"><article class="signup-ds-offer-card"><span class="signup-ds-icon">' .
            icon("verified") .
            '</span><div><span class="eyebrow">Teste inicial</span><h2>Comece com calma</h2><p>' .
            e($signupTrialCopy) .
            '</p></div></article><article class="signup-ds-price-card"><span class="eyebrow">Após o período inicial</span><strong>' .
            e($signupPrice) .
            '<small>/mês</small></strong><p>Plano mensal, sem fidelidade, com cancelamento livre.</p></article></div><div class="signup-ds-feature-grid"><span>' .
            icon("event_available") .
            "<b>Agenda</b><small>Consultas e bloqueios organizados.</small></span><span>" .
            icon("patient_list") .
            "<b>Pacientes</b><small>Dados clínicos acessíveis por perfil.</small></span><span>" .
            icon("payments") .
            '<b>Financeiro</b><small>Entradas, baixas e acompanhamento.</small></span></div><label class="checkline signup-consent signup-ds-consent"><input type="checkbox" name="trial_accept" value="1" required data-signup-trial-accept data-required-message="Confirme que deseja experimentar sem custo."><span>' .
            e($signupConsent) .
            '</span></label><div class="signup-actions signup-step-actions signup-ds-actions"><a class="ghost" href="' .
            href("login") .
            '">' .
            icon("arrow_back") .
            '<span>Voltar para entrada</span></a><button type="button" class="primary" data-signup-next>' .
            icon("arrow_forward") .
            '<span>Continuar</span></button></div></section><section class="signup-step signup-ds-step signup-screen-panel" data-signup-step="1" hidden><div class="signup-screen-kicker"><span>Etapa 2 de 3</span><strong>Responsável e acesso</strong></div><fieldset class="signup-ds-fieldset"><legend><span class="signup-ds-icon small">' .
            icon("account_circle") .
            '</span><span><b>Responsável pelo consultório</b><small>Use CPF, dados pessoais e senha de acesso.</small></span></legend><p class="field-help">Se este CPF já existir, informe a senha atual para vincular o novo consultório ao mesmo acesso.</p>' .
            $responsibleFields .
            '</fieldset><div class="signup-actions signup-step-actions signup-ds-actions"><button type="button" class="ghost" data-signup-prev>' .
            icon("arrow_back") .
            '<span>Voltar</span></button><button type="button" class="primary" data-signup-next>' .
            icon("arrow_forward") .
            '<span>Dados do consultório</span></button></div></section><section class="signup-step signup-ds-step signup-screen-panel" data-signup-step="2" hidden><div class="signup-screen-kicker"><span>Etapa 3 de 3</span><strong>Identificação do consultório</strong></div><fieldset class="signup-ds-fieldset"><legend><span class="signup-ds-icon small">' .
            icon("domain_add") .
            '</span><span><b>Identificação do consultório</b><small>Dados definitivos para abrir o ambiente inicial.</small></span></legend><p class="field-help">A cidade de atuação define automaticamente o fuso horário. Identidade visual, departamentos, equipe e permissões serão orientados dentro do consultório.</p>' .
            $clinicFields .
            '</fieldset><div class="signup-actions signup-step-actions signup-ds-actions"><button type="button" class="ghost" data-signup-prev>' .
            icon("arrow_back") .
            '<span>Voltar</span></button><button type="submit" class="primary">' .
            icon("check_circle") .
            "<span>Criar consultório</span></button></div></section></form></section>";
        page("Criar consultório", $form, ["public" => true]);
    
    }
}
