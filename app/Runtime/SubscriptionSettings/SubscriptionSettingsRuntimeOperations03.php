<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SubscriptionSettings;

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

final class SubscriptionSettingsRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function page_settings(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("settings");
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::seed_clinic_roles($cid);
        $tab = (string) ($_GET["tab"] ?? ($_POST["tab"] ?? "perfil"));
        $tab = trim(
            function_exists("mb_strtolower")
                ? mb_strtolower($tab, "UTF-8")
                : strtolower($tab),
        );
        if (!in_array($tab, ["perfil", "setores", "visual", "assinatura"], true)) {
            $tab = "perfil";
        }
        $cl = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.subscription_settings.03.page_settings.01', [$cid], []);
        if (!$cl) {
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Consultório não encontrado.", "bad");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments");
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "profile");
            try {
                if ($act === "profile") {
                    $uf = strtoupper(
                        mb_trim((string) ($_POST["address_state"] ?? "")),
                    );
                    $city = mb_trim((string) ($_POST["address_city"] ?? ""));
                    $cityIbge = (int) ($_POST["address_city_ibge"] ?? 0);
                    if (
                        !isset(\Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::br_states()[$uf]) ||
                        $city === "" ||
                        $cityIbge <= 0
                    ) {
                        throw new RuntimeException(
                            "Escolha uma cidade da lista do IBGE.",
                        );
                    }
                    $tz = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::timezone_from_location($uf, $city);
                    $profession = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_profession(
                        (string) ($_POST["responsible_profession"] ?? ""),
                    );
                    $legalDoc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) $_POST["legal_document"]);
                    if (strlen($legalDoc) === 11 && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($legalDoc)) {
                        throw new RuntimeException("Este CPF não existe.");
                    }
                    if (strlen($legalDoc) === 14 && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cnpj($legalDoc)) {
                        throw new RuntimeException(
                            "Informe CNPJ válido para o consultório.",
                        );
                    }
                    if (!in_array(strlen($legalDoc), [11, 14], true)) {
                        throw new RuntimeException(
                            "Informe CPF ou CNPJ válido para o consultório.",
                        );
                    }
                    \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.subscription_settings.03.page_settings.02', [
                            mb_trim((string) $_POST["display_name"]),
                            mb_trim((string) $_POST["legal_name"]),
                            $legalDoc,
                            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) $_POST["phone"]),
                            $profession,
                            mb_trim((string) ($_POST["address_line"] ?? "")),
                            $uf,
                            $city,
                            $cityIbge,
                            $tz,
                            $cid,
                        ], []);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consultorio_atualizado", "consultorio", $cid, [
                        "audit_body" =>
                            "Identificação cadastral do consultório atualizada.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Identificação atualizada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("settings", ["tab" => "perfil"]);
                }
                if ($act === "sectors") {
                    $roleLabels = (array) ($_POST["role_label"] ?? []);
                    $roleIcons = (array) ($_POST["role_icon"] ?? []);
                    $defs = [
                        "recepcionista" => "Recepção",
                        "assistente" => "Assistente",
                        "medico" => "Profissional",
                        "gerente" => "Administrativo",
                    ];
                    $i = 1;
                    foreach ($defs as $role => $fallback) {
                        $opts = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::role_icon_options($role);
                        $label = mb_trim((string) ($roleLabels[$role] ?? ""));
                        if ($label === "") {
                            $label = $fallback;
                        }
                        $ico = trim(
                            (string) ($roleIcons[$role] ??
                                \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($role)),
                        );
                        if (!isset($opts[$ico])) {
                            $ico = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($role);
                        }
                        \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.subscription_settings.03.page_settings.03', [$cid, $role, $label, $ico, 1, $i++], []);
                    }
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consultorio_atualizado", "consultorio", $cid, [
                        "audit_body" => "Departamentos do consultório atualizados.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Departamentos atualizados.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("settings", ["tab" => "setores"]);
                }
                if ($act === "visual") {
                    $clinicIcon = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_clinic_icon(
                        (string) ($_POST["clinic_icon"] ?? ""),
                    );
                    $accentColor = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_accent_color(
                        (string) ($_POST["accent_color"] ?? ""),
                    );
                    \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.subscription_settings.03.page_settings.04', [$clinicIcon, $accentColor, $cid], []);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consultorio_atualizado", "consultorio", $cid, [
                        "clinic_icon" => $clinicIcon,
                        "accent_color" => $accentColor,
                        "audit_body" => "Aparência do consultório atualizada.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Aparência atualizada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("settings", ["tab" => "visual"]);
                }
                if ($act === "subscription_claim") {
                    $msg = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations02::clinic_subscription_register_claim($cid, $uid, $cl);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($msg);
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("settings", ["tab" => "assinatura"]);
                }
            } catch (Throwable $e) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                        $e,
                        "Não foi possível salvar as configurações.",
                    ),
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("settings", ["tab" => $tab]);
            }
        }
        $cl = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.subscription_settings.03.page_settings.05', [$cid], []);
        $nav = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations02::clinic_settings_nav($tab);
        $content = "";
        $screenClass = "settings-ds-screen settings-ds-" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($tab);
        if ($tab === "perfil") {
            $identity =
                '<section class="settings-ds-panel settings-data-identity"><div class="settings-ds-head"><span class="settings-ds-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("home_health") .
                '</span><div><span class="eyebrow">Identificação</span><h2>Identificação do consultório</h2><p class="field-help">Mantenha nome, documento, telefone e profissão responsáveis pela identidade operacional do ambiente.</p></div></div><div class="settings-ds-form-grid">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Nome fantasia",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "display_name",
                        "text",
                        $cl["display_name"],
                        'required maxlength="120"',
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Razão social/nome",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "legal_name",
                        "text",
                        $cl["legal_name"],
                        'required maxlength="180"',
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "CPF/CNPJ",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "legal_document",
                        "text",
                        $cl["legal_document"],
                        'required inputmode="numeric" data-doc-mask data-document-validate',
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Telefone",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("phone", "text", $cl["phone"], 'inputmode="tel"'),
                ) .
                \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::profession_select_fields($cl) .
                "</div></section>";
            $location =
                '<section class="settings-ds-panel settings-data-location"><div class="settings-ds-head"><span class="settings-ds-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("location_on") .
                '</span><div><span class="eyebrow">Localização</span><h2>Cidade, estado e fuso</h2><p class="field-help">A cidade escolhida pelo IBGE define o fuso usado na Agenda, em horários, bloqueios e registros do consultório.</p></div></div><div class="settings-ds-form-grid">' .
                \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_location_fields($cl) .
                '</div><div class="settings-ds-note">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                "<span>O fuso é calculado automaticamente pela cidade selecionada.</span></div></section>";
            $form =
                '<form method="post" class="compact clinic-settings-form clinic-settings-ds-form clinic-data-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="profile"><input type="hidden" name="tab" value="perfil"><div class="settings-data-grid">' .
                $identity .
                $location .
                '</div><div class="form-actions full settings-ds-actions"><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
                "<span>Salvar identificação</span></button></div></form>";
            $content =
                '<div class="' .
                $screenClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    $form,
                    "clinic-settings-card clinic-data-card settings-ds-card-shell",
                ) .
                "</div>";
        } elseif ($tab === "setores") {
            $form =
                '<form method="post" class="compact clinic-settings-form clinic-settings-ds-form clinic-departments-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="sectors"><input type="hidden" name="tab" value="setores"><section class="settings-section settings-departments full settings-ds-panel"><div class="settings-section-head settings-ds-head"><span class="settings-section-icon settings-ds-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("corporate_fare") .
                '</span><div><span class="eyebrow">Do seu jeito</span><h2>Departamentos</h2><p class="field-help">Escolha como os setores aparecem para a equipe. Os nomes organizam menus, ambientes, permissões e responsáveis em todo o consultório.</p></div></div>' .
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_sector_fields($cid) .
                '</section><div class="form-actions full departments-actions settings-ds-actions"><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
                "<span>Salvar departamentos</span></button></div></form>";
            $content =
                '<div class="' .
                $screenClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($form, "clinic-departments-card settings-ds-card-shell") .
                "</div>";
        } elseif ($tab === "visual") {
            $visual = \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_visual_from_values(
                $cl["clinic_icon"] ?? null,
                $cl["accent_color"] ?? null,
                $cl["responsible_profession"] ?? null,
            );
            $previewStyle =
                "--appearance-accent:" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($visual["brand"]) .
                ";--appearance-accent-dark:" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($visual["brand_dark"]) .
                ";--appearance-accent-soft:" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($visual["brand_soft"]) .
                ";--appearance-on-accent:" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($visual["on_brand"]) .
                ";";
            $form =
                '<form method="post" class="compact clinic-settings-form clinic-settings-ds-form clinic-appearance-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="visual"><input type="hidden" name="tab" value="visual"><section class="settings-section settings-appearance full settings-ds-panel"><div class="settings-section-head settings-ds-head"><span class="settings-section-icon settings-ds-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("palette") .
                '</span><div><span class="eyebrow">Aparência</span><h2>Identidade visual</h2><p class="field-help">Escolha o ícone e a cor que organizam a identidade visual do consultório em menus, botões, cartões e destaques.</p></div></div><div class="clinic-appearance-preview" style="' .
                $previewStyle .
                '"><span class="clinic-appearance-mark">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($visual["icon"]) .
                '</span><div class="clinic-appearance-copy"><span class="eyebrow">Prévia do consultório</span><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cl["display_name"] ?? "Consultório") .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_profession(
                        (string) ($cl["responsible_profession"] ?? "Profissional"),
                    ),
                ) .
                '</small></div><span class="clinic-appearance-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified") .
                '<span>Identidade ativa</span></span></div><div class="appearance-picker-block"><div class="appearance-picker-head"><h3>Ícone do consultório</h3><p class="field-help">Use um símbolo simples e reconhecível. Ele aparece no cabeçalho, na credencial e nos atalhos do sistema.</p></div>' .
                \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_icon_picker($visual["icon"]) .
                '</div><div class="appearance-picker-block"><div class="appearance-picker-head"><h3>Cor de destaque</h3><p class="field-help">A cor selecionada gera automaticamente variações claras, médias e escuras para manter contraste e consistência.</p></div>' .
                \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_color_picker($visual["brand"]) .
                '</div></section><div class="form-actions full settings-ds-actions"><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
                "<span>Salvar aparência</span></button></div></form>";
            $content =
                '<div class="' .
                $screenClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($form, "clinic-appearance-card settings-ds-card-shell") .
                "</div>";
        } else {
            $content =
                '<div class="settings-ds-screen settings-ds-assinatura">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::clinic_subscription_status_card($cl),
                    "subscription-status-shell settings-ds-card-shell",
                ) .
                \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations02::clinic_subscription_cta($cl, $tab) .
                "</div>";
        }
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Meu Consultório", \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Meu Consultório", "") . $content);
    
    }
}
