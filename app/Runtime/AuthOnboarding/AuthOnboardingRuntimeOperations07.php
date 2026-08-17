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
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
    
    }

    public static function page_onboarding(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        if (!\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::is_responsible_doctor($c)) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments");
        }
        $cid = (int) $c["clinic_id"];
        if (is_callable([\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::class, 'ensure_clinic_trial_active'])) {
            \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::ensure_clinic_trial_active($cid, true);
        }
        $cl = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth07.page_onboarding.01', [$cid], []);
        if (!$cl) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments");
        }
        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::seed_clinic_roles($cid);
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $rolesEnabled = array_fill_keys(array_keys(PRONTOO_ROLES), 0);
            foreach ((array) ($_POST["roles"] ?? []) as $r) {
                if (isset(PRONTOO_ROLES[$r])) {
                    $rolesEnabled[$r] = 1;
                }
            }
            $rolesEnabled["medico"] = 1;
            $rolesEnabled["gerente"] = 1;
            try {
                $uf = strtoupper(mb_trim((string) ($_POST["address_state"] ?? "")));
                $city = mb_trim((string) ($_POST["address_city"] ?? ""));
                $cityIbge = (int) ($_POST["address_city_ibge"] ?? 0);
                if (!isset(\Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::br_states()[$uf]) || $city === "" || $cityIbge <= 0) {
                    throw new RuntimeException(
                        "Escolha uma cidade da lista do IBGE.",
                    );
                }
                $tz = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::timezone_from_location($uf, $city);
                $profession = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_profession(
                    (string) ($_POST["responsible_profession"] ?? ""),
                );
                $clinicIcon = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_clinic_icon(
                    (string) ($_POST["clinic_icon"] ?? ($cl["clinic_icon"] ?? "")),
                );
                $accentColor = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_accent_color(
                    (string) ($_POST["accent_color"] ??
                        ($cl["accent_color"] ?? "")),
                );
                $trialStart = time();
                $trialEnd = is_callable([\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::class, 'subscription_trial_end_from_start'])
                    ? \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_trial_end_from_start(
                        $trialStart,
                        \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days(),
                    )
                    : $trialStart + max(1, \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days()) * 86400;
                \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::clinicOnboarding()
                    ->complete(
                        [
                            'clinic_id' => $cid,
                            'display_name' => mb_trim((string) $_POST["display_name"]),
                            'phone' => \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) $_POST["phone"]),
                            'profession' => $profession,
                            'clinic_icon' => $clinicIcon,
                            'accent_color' => $accentColor,
                            'address_line' => mb_trim((string) ($_POST["address_line"] ?? "")),
                            'state' => $uf,
                            'city' => $city,
                            'city_ibge' => $cityIbge,
                            'timezone' => $tz,
                            'trial_start' => $trialStart,
                            'trial_end' => $trialEnd,
                        ],
                        $rolesEnabled,
                        PRONTOO_ROLES,
                        (array) ($_POST["role_label"] ?? []),
                        \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::default_permissions(),
                        \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::actions(),
                        $_POST,
                        static fn(int $clinicId, array $payload): int =>
                            \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::save_team_member(
                                $clinicId,
                                $payload,
                            ),
                        static fn(...$arguments): bool =>
                            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                                ...$arguments,
                            ),
                    );
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Configuração inicial concluída. Você pode ajustar equipe, permissões e identidade visual depois em Meu Consultório.",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments");
            } catch (Throwable $e) {
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
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    $msg !== ""
                        ? $msg
                        : "Não foi possível salvar a configuração inicial. Revise os campos e tente novamente.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("onboarding");
            }
        }
        $roles = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_roles($cid, false);
        $enabled = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth07.page_onboarding.05', [$cid], [])->fetchAll(PDO::FETCH_KEY_PAIR);
        $roleCards = "";
        foreach (PRONTOO_ROLES as $role => $default) {
            $locked = in_array($role, ["medico", "gerente"], true);
            $checked = $locked || (int) ($enabled[$role] ?? 0) ? "checked" : "";
            $disabled = $locked ? "disabled" : "";
            $hidden = $locked
                ? '<input type="hidden" name="roles[]" value="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($role) . '">'
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($role) .
                '" ' .
                $checked .
                " " .
                $disabled .
                ">" .
                $hidden .
                " Usar este departamento</label>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Nome visível",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "role_label[" . $role . "]",
                        "text",
                        $roles[$role] ?? $default,
                        "required",
                    ),
                ) .
                "<p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($desc) .
                "</p></article>";
        }
        $visual = \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_visual_from_values(
            $cl["clinic_icon"] ?? null,
            $cl["accent_color"] ?? null,
            $cl["responsible_profession"] ?? null,
        );
        $visualStep =
            '<div class="clinic-visual-preview"><span class="brand-mark">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($visual["icon"]) .
            "</span><div><strong>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cl["display_name"] ?? "Consultório") .
            "</strong><small>Seus ambientes de trabalho terão as cores e ícones escolhidos.</small></div></div><h3>Escolha um ícone</h3>" .
            \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_icon_picker($visual["icon"]) .
            "<h3>Escolha uma cor</h3>" .
            \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_color_picker($visual["brand"]);
        $steps =
            '<div class="wizard-progress" aria-label="Etapas da configuração"><span class="is-active">1</span><span>2</span><span>3</span><span>4</span></div>';
        $form =
            '<section class="wizard onboarding-wizard" data-onboarding-wizard><div class="wizard-head"><span class="eyebrow">Seu consultório, suas regras</span><h1>Vamos deixar seu consultório com a sua cara</h1><p>Informe os dados abaixo. Se precisar, ajustes podem ser feitos mais tarde.</p></div><form method="post" class="compact onboarding-form clinic-settings-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            $steps .
            '<section class="wizard-step is-active" data-wizard-step="0"><div class="wizard-step-title"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("home_health") .
            '</span><div><h2>Fale sobre o Consultório</h2><p>Confirme o nome, telefone, profissão e cidade de atuação.</p></div></div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome do consultório",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("display_name", "text", $cl["display_name"], "required"),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Telefone principal",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("phone", "text", $cl["phone"] ?? ""),
            ) .
            "</div>" .
            \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::profession_select_fields($cl) .
            \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_location_fields($cl) .
            '</section><section class="wizard-step" data-wizard-step="1" hidden><div class="wizard-step-title"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
            '</span><div><h2>Seus departamentos</h2><p>Os departamentos permitem que você distribua as tarefas por cargos.</p></div></div><div class="wizard-roles">' .
            $roleCards .
            '</div></section><section class="wizard-step" data-wizard-step="2" hidden><div class="wizard-step-title"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("person_add") .
            "</span><div><h2>Primeiro membro da equipe</h2><p>Você foi configurado como Administrativo e <span data-onboarding-profession>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_profession(
                    (string) ($cl["responsible_profession"] ?? "Profissional"),
                ),
            ) .
            '</span> do Consultório. Cadastre outros membros da equipe agora ou clique em Avançar para fazer isto mais tarde.</p></div></div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Nome completo", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("team_name", "text")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("team_cpf", "text", "", 'inputmode="numeric" maxlength="14"'),
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Nascimento", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("team_birth", "date")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("E-mail", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("team_email", "email")) .
            "</div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Cargos",
                \Prontoo\Presentation\UsersPermissions\UsersPermissionsPresentationOperations01::role_checkbox_group("team_roles", \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::manageable_team_roles($cid), [
                    "recepcionista",
                ]),
            ) .
            '<div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Senha inicial",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "team_password",
                    "password",
                    "",
                    'minlength="8" maxlength="128" autocomplete="new-password" data-password-strength',
                ),
            ) .
            '</div></section><section class="wizard-step" data-wizard-step="3" hidden><div class="wizard-step-title"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("palette") .
            "</span><div><h2>Aparência</h2><p>Escolha o ícone e a cor que melhor representam o seu consultório.</p></div></div>" .
            $visualStep .
            '</section><div class="wizard-nav"><button type="button" class="ghost" data-wizard-prev hidden>Voltar</button><button type="button" class="primary" data-wizard-next>Avançar</button><button type="submit" class="primary" data-wizard-submit hidden>Concluir configuração</button></div></form></section>';
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Seu consultório, suas regras", $form);
    
    }

    public static function person_autosuggest_datalist(
        int $cid,
        string $id = "prontoo_person_suggestions",
    ): string 
    {
    
        $rows = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth07.person_autosuggest_datalist.01', [$cid, $cid], [])->fetchAll();
        $h = '<datalist id="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($id) . '">';
        foreach ($rows as $r) {
            $label = trim(
                (!empty($r["cpf"])
                    ? "CPF " . \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) $r["cpf"])
                    : "CPF não informado") .
                    " · " .
                    (!empty($r["birth_date"])
                        ? "Nascimento " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["birth_date"])
                        : "nascimento não informado"),
                " ·",
            );
            $h .=
                '<option value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $r["full_name"]) .
                '" label="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                '" data-cpf="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) ($r["cpf"] ?? ""))) .
                '" data-birth="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::db_birth_date_input($r["birth_date"] ?? "")) .
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
        $doc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($doc);
        if ($name === "") {
            throw new RuntimeException("Nome da pessoa não informado.");
        }
        if (strlen($doc) === 11) {
            return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::save_person_flexible($name, $doc, $birth);
        }
        if (strlen($doc) === 14) {
            if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cnpj($doc)) {
                throw new RuntimeException("Informe CNPJ válido.");
            }
            $id = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.auth07.save_person_by_document.01', [
                $doc,
            ], []);
            if ($id) {
                \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth07.save_person_by_document.02', [$name, $id], []);
                return (int) $id;
            }
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth07.save_person_by_document.03', [$name, $doc], []);
            return \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->lastInsertId();
        }
        throw new RuntimeException("Informe CPF ou CNPJ válido.");
    
    }

    public static function footer_telemetry_wave_data(): array
    
    {
        try {
            $pageLoads = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_volume_series_30d("page_load");
            $databaseQueries = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_volume_series_30d("database_queries");
            return [
      "page_loads" => \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::footer_telemetry_line_values($pageLoads),
      "database_queries" => \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::footer_telemetry_line_values($databaseQueries),
            ];
        } catch (Throwable $error) {
            error_log(
      "[Prontoo footer telemetry wave] " . $error->getMessage(),
            );
            return ["page_loads" => [], "database_queries" => []];
        }
    
    }

    public static function page_footer_telemetry_wave(): void
    
    {
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET") {
            http_response_code(405);
            header("Allow: GET");
            header("Content-Type: application/json; charset=utf-8");
            echo '{"error":"method_not_allowed"}';
            return;
        }
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: private, max-age=60, stale-while-revalidate=300");
        header("X-Content-Type-Options: nosniff");
        echo json_encode(
            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::footer_telemetry_wave_data(),
            JSON_UNESCAPED_UNICODE |
      JSON_UNESCAPED_SLASHES |
      JSON_PRESERVE_ZERO_FRACTION,
        );
    
    }
}
