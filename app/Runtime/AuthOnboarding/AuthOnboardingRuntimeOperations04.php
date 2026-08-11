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
            [$pair, $subject, $ip] = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::login_bucket_keys($cpf);
            $now = time();
            $r = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth04.login_lock.01', [
                    $now,
                    $pair[0],
                    $pair[1],
                    $subject[0],
                    $subject[1],
                    $ip[0],
                    $ip[1],
                ], []);
            if (!$r) {
                return 0;
            }
            return max(0, (int) ($r["wait_seconds"] ?? 0));
        } catch (Throwable $e) {
            error_log("[Prontoo login_lock] " . $e->getMessage());
            return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_wait();
        }
    
    }

    public static function login_fail(string $cpf): int
    
    {
        
        $seconds = 60;
        try {
            [$pair, $subject, $ip] = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::login_bucket_keys($cpf);
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth04.login_fail.01', [
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
                ], []);
            $seconds = max(1, \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_lock($cpf));
        } catch (Throwable $e) {
            error_log("[Prontoo login_fail] " . $e->getMessage());
        }
        return $seconds;
    
    }

    public static function login_clear(string $cpf): void
    
    {
    
        try {
            [$pair, $subject] = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::login_bucket_keys($cpf);
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth04.login_clear.01', [$pair[0], $pair[1], $subject[0], $subject[1]], []);
        } catch (Throwable $e) {
            error_log("[Prontoo login_clear] " . $e->getMessage());
        }
    
    }

    public static function mark_login_success(int $uid): void
    
    {
        
        try {
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth04.mark_login_success.01', [$uid], []);
        } catch (Throwable $e) {
            error_log("[Prontoo mark_login_success] " . $e->getMessage());
        }
    
    }

    public static function page_signup(): void
    
    {
    
        $currentCtx = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
        if ($currentCtx) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect(
                ($currentCtx["scope"] ?? "") === "global"
                    ? "admin_painel"
                    : "painel",
            );
        }
        if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'clinic_signup_blocked']) && \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::clinic_signup_blocked()) {
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Cadastro pausado",
                '<section class="auth widebox"><h1>A inauguração de consultórios foi pausada</h1><p>Atingimos nossa capacidade máxima de consultórios hoje. Tente novamente mais tarde.</p><p><a class="ghost" href="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("login")) .
                    '">Tentarei mais tarde</a></p></section>',
                ["public" => true, "robots" => "noindex,nofollow"],
            );
            return;
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            if (empty($_POST["trial_accept"])) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Confirme que deseja experimentar sem custo.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
            }
            $pass = (string) $_POST["password"];
            $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) $_POST["cpf"]);
            $doc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) $_POST["legal_document"]);
            if (
                \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("signup"), 5, 3600) ||
                \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_ip_bucket("signup"), 20, 3600) ||
                ($cpf !== "" &&
                    \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
                        \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_value_bucket("signup_cpf", $cpf),
                        3,
                        3600,
                    )) ||
                ($doc !== "" &&
                    \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
                        \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_value_bucket("signup_doc", $doc),
                        4,
                        3600,
                    ))
            ) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Muitas tentativas de cadastro. Aguarde alguns instantes antes de tentar novamente.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
            }
            $legalType = (string) ($_POST["legal_type"] ?? "");
            $profession = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_profession(
                (string) ($_POST["responsible_profession"] ?? ""),
            );
            if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Este CPF não existe.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
            }
            if (!\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date((string) $_POST["birth_date"])) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Informe uma data de nascimento válida para o responsável pelo consultório.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
            }
            if (!in_array($legalType, ["cpf", "cnpj"], true)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Escolha se o consultório será pessoa física ou jurídica.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
            }
            if ($legalType === "cpf" && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($doc)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Este CPF não existe.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
            }
            if ($legalType === "cnpj" && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cnpj($doc)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe CNPJ válido para o consultório.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
            }
            $uf = strtoupper(mb_trim((string) ($_POST["address_state"] ?? "")));
            $city = mb_trim((string) ($_POST["address_city"] ?? ""));
            $cityIbge = (int) ($_POST["address_city_ibge"] ?? 0);
            if (!isset(\Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::br_states()[$uf]) || $city === "" || $cityIbge <= 0) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Escolha a cidade de atuação na lista do IBGE.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
            }
            $timezone = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::timezone_from_location($uf, $city);
            $accentColor = PRONTOO_DEFAULT_ACCENT_COLOR;
            try {
                $trialStart = time();
                $trialEnd = is_callable([\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::class, 'subscription_trial_end_from_start'])
                    ? \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_trial_end_from_start(
                        $trialStart,
                        \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days(),
                    )
                    : $trialStart + max(1, \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days()) * 86400;
                [$uid, $managerRoleId] = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::clinicRegistration()
                    ->register(
                        [
                            'doctor_name' => mb_trim((string) $_POST["doctor_name"]),
                            'cpf' => $cpf,
                            'birth_date' => (string) $_POST["birth_date"],
                            'email' => mb_trim((string) $_POST["email"]),
                            'password' => $pass,
                            'legal_type' => $legalType,
                            'legal_name' => mb_trim((string) $_POST["legal_name"]),
                            'document' => $doc,
                            'display_name' => mb_trim((string) $_POST["display_name"]),
                            'phone' => \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) $_POST["phone"]),
                            'profession' => $profession,
                            'accent_color' => $accentColor,
                            'address_line' => mb_trim((string) ($_POST["address_line"] ?? "")),
                            'state' => $uf,
                            'city' => $city,
                            'city_ibge' => $cityIbge,
                            'timezone' => $timezone,
                            'trial_start' => $trialStart,
                            'trial_end' => $trialEnd,
                            'monthly_price_cents' => \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents(),
                        ],
                        static fn(string $name, string $personCpf, string $birthDate): int =>
                            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::upsert_person(
                                $name,
                                $personCpf,
                                $birthDate,
                            ),
                        static fn(int $personId): mixed =>
                            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::lock_person_user_identity($personId),
                        static fn(string $plain, string $hash): bool => password_verify($plain, $hash),
                        static fn(string $plain): bool =>
                            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_ok($plain),
                        static fn(string $plain): string =>
                            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_hash_secure($plain),
                        static fn(string $counter): mixed =>
                            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc($counter),
                        static function (int $clinicId, bool $force): void {
                            if (is_callable([\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::class, 'ensure_clinic_trial_active'])) {
                                \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::ensure_clinic_trial_active($clinicId, $force);
                            }
                        },
                        static fn(int $clinicId): mixed =>
                            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::seed_permissions($clinicId),
                        static fn(int $clinicId): mixed =>
                            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::seed_clinic_roles($clinicId),
                        static fn(...$arguments): bool =>
                            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                                ...$arguments,
                            ),
                    );
                \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_last_credential_remember(
                    $uid,
                    "clinic",
                    $managerRoleId,
                );
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Seu consultório foi inaugurado e configurado. Insira CPF e Senha para entrar como Administrativo.",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
            } catch (Throwable $e) {
                if ($e->getMessage() === '__signup_credentials__') {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Não foi possível confirmar as credenciais informadas. Revise CPF, senha e dados do consultório.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
                }
                if ($e->getMessage() === '__signup_password__') {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Use senha com 8 a 128 caracteres que não seja uma senha comum.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
                }
                error_log("[Prontoo signup] " . $e->getMessage());
                $reason =
                    "Revise os dados informados, a senha atual do CPF ou o documento do consultório.";
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Não foi possível concluir o cadastro. " . $reason, "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("signup");
            }
        }
        $signupPrice = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br(\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents());
        $signupTrialDays = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days();
        $signupTrialLabel = \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::trial_period_label($signupTrialDays);
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "cpf",
                    "text",
                    "",
                    'required inputmode="numeric" maxlength="14" autocomplete="username" placeholder="000.000.000-00" data-person-cpf-lookup="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("person_lookup")) .
                        '" data-person-lookup-context="signup" data-person-name-target="doctor_name" data-person-birth-target="birth_date"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome completo",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "doctor_name",
                    "text",
                    "",
                    'required autocomplete="name" placeholder="Nome do responsável"',
                ),
            ) .
            '</div><div class="signup-ds-grid two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Nascimento", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("birth_date", "date", "", "required")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "E-mail",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "email",
                    "email",
                    "",
                    'required autocomplete="email" placeholder="email@exemplo.com"',
                ),
            ) .
            "</div>" .
            \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::profession_select_fields() .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Senha",
                '<div class="password-field">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "password",
                        "password",
                        "",
                        'required minlength="8" maxlength="128" autocomplete="new-password" placeholder="Senha nova ou senha atual se o CPF já existir" data-password-strength data-password-toggle',
                    ) .
                    '<button type="button" class="password-toggle" data-password-toggle-button aria-label="Mostrar senha">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("visibility") .
                    "</button></div>",
            );
        $clinicFields =
            '<div class="signup-ds-grid two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Tipo de pessoa",
                "legal_type",
                ["cpf" => "Pessoa física", "cnpj" => "Pessoa jurídica"],
                null,
                "required",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF/CNPJ do consultório",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "legal_document",
                    "text",
                    "",
                    'required inputmode="numeric" placeholder="Documento do consultório" data-doc-mask data-document-validate',
                ),
            ) .
            '</div><div class="signup-ds-grid two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome/Razão social",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "legal_name",
                    "text",
                    "",
                    'required placeholder="Nome jurídico do consultório"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome fantasia",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "display_name",
                    "text",
                    "",
                    'required placeholder="Nome exibido no sistema"',
                ),
            ) .
            "</div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Telefone principal",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "phone",
                    "text",
                    "",
                    'autocomplete="tel" inputmode="tel" placeholder="(00) 00000-0000"',
                ),
            ) .
            \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_location_fields();
        $form =
            '<section class="auth widebox signup-card signup-steps-card signup-ds-shell signup-screen-flow"><header class="signup-ds-hero"><div class="signup-ds-hero-main"><span class="eyebrow signup-opening-label">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("home_health") .
            '<span>Novo consultório</span></span><h1>Criar consultório</h1><p>Configure o acesso inicial, identifique o responsável e registre o consultório em um fluxo simples e seguro. Cada etapa aparece em uma tela própria.</p></div><div class="auth-brandmark signup-brandmark" data-app-favicon-brandmark><img class="auth-brandmark-favicon app-brandmark-img" src="/public/assets/app-icon-' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(PRONTOO_ASSET_REV) .
            '.png" alt="" aria-hidden="true"></div></header><ol class="signup-ds-stepper" aria-label="Etapas para criar consultório"><li class="is-active" data-signup-indicator="0"><b>1</b><span><strong>Experimente</strong><small>Sem compromisso</small></span></li><li data-signup-indicator="1"><b>2</b><span><strong>Responsável</strong><small>CPF e acesso</small></span></li><li data-signup-indicator="2"><b>3</b><span><strong>Consultório</strong><small>Dados principais</small></span></li></ol><form method="post" class="compact signup-form signup-wizard signup-ds-form" data-signup-steps>' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<section class="signup-step signup-ds-step signup-screen-panel is-active" data-signup-step="0"><div class="signup-screen-kicker"><span>Etapa 1 de 3</span><strong>Experimente sem compromisso</strong></div><div class="signup-ds-layout"><article class="signup-ds-offer-card"><span class="signup-ds-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified") .
            '</span><div><span class="eyebrow">Teste inicial</span><h2>Comece com calma</h2><p>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($signupTrialCopy) .
            '</p></div></article><article class="signup-ds-price-card"><span class="eyebrow">Após o período inicial</span><strong>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($signupPrice) .
            '<small>/mês</small></strong><p>Plano mensal, sem fidelidade, com cancelamento livre.</p></article></div><div class="signup-ds-feature-grid"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
            "<b>Agenda</b><small>Consultas e bloqueios organizados.</small></span><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("patient_list") .
            "<b>Pacientes</b><small>Dados clínicos acessíveis por perfil.</small></span><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
            '<b>Financeiro</b><small>Entradas, baixas e acompanhamento.</small></span></div><label class="checkline signup-consent signup-ds-consent"><input type="checkbox" name="trial_accept" value="1" required data-signup-trial-accept data-required-message="Confirme que deseja experimentar sem custo."><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($signupConsent) .
            '</span></label><div class="signup-actions signup-step-actions signup-ds-actions"><a class="ghost" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("login") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            '<span>Voltar para entrada</span></a><button type="button" class="primary" data-signup-next>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_forward") .
            '<span>Continuar</span></button></div></section><section class="signup-step signup-ds-step signup-screen-panel" data-signup-step="1" hidden><div class="signup-screen-kicker"><span>Etapa 2 de 3</span><strong>Responsável e acesso</strong></div><fieldset class="signup-ds-fieldset"><legend><span class="signup-ds-icon small">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("account_circle") .
            '</span><span><b>Responsável pelo consultório</b><small>Use CPF, dados pessoais e senha de acesso.</small></span></legend><p class="field-help">Se este CPF já existir, informe a senha atual para vincular o novo consultório ao mesmo acesso.</p>' .
            $responsibleFields .
            '</fieldset><div class="signup-actions signup-step-actions signup-ds-actions"><button type="button" class="ghost" data-signup-prev>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            '<span>Voltar</span></button><button type="button" class="primary" data-signup-next>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_forward") .
            '<span>Dados do consultório</span></button></div></section><section class="signup-step signup-ds-step signup-screen-panel" data-signup-step="2" hidden><div class="signup-screen-kicker"><span>Etapa 3 de 3</span><strong>Identificação do consultório</strong></div><fieldset class="signup-ds-fieldset"><legend><span class="signup-ds-icon small">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("domain_add") .
            '</span><span><b>Identificação do consultório</b><small>Dados definitivos para abrir o ambiente inicial.</small></span></legend><p class="field-help">A cidade de atuação define automaticamente o fuso horário. Identidade visual, departamentos, equipe e permissões serão orientados dentro do consultório.</p>' .
            $clinicFields .
            '</fieldset><div class="signup-actions signup-step-actions signup-ds-actions"><button type="button" class="ghost" data-signup-prev>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            '<span>Voltar</span></button><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check_circle") .
            "<span>Criar consultório</span></button></div></section></form></section>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Criar consultório", $form, ["public" => true]);
    
    }
}
