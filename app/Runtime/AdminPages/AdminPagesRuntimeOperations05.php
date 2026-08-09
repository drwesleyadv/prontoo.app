<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AdminPages;

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

final class AdminPagesRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function page_admin_maintenance(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_maintenance");
        $uid = (int) ($c["user"]["id"] ?? 0);
        $tab = preg_replace(
            "/[^a-z_]/",
            "",
            (string) ($_GET["tab"] ?? ($_POST["tab"] ?? "manutencao")),
        );
        if (!in_array($tab, ["manutencao", "configuracoes"], true)) {
            $tab = "manutencao";
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "save_maintenance");
            if ($act === "save_maintenance") {
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set("maintenance_active", isset($_POST["active"]) ? "1" : "0");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set(
                    "maintenance_message",
                    mb_trim((string) ($_POST["message"] ?? "")) ?:
                    "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("manutencao_atualizada", "manutencao", null, $_POST);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Manutenção atualizada.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_maintenance", ["tab" => "manutencao"]);
            }
            if ($act === "save_settings") {
                foreach (
                    ["support_email", "support_phone", "session_policy_note"]
                    as $k
                ) {
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set($k, mb_trim((string) ($_POST[$k] ?? "")));
                }
                $adminTimezone = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_safe(
                    (string) ($_POST["global_admin_timezone"] ??
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_global_admin_timezone($uid)),
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set("global_admin_timezone_user_" . $uid, $adminTimezone);
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_apply_request_timezone($adminTimezone);
                $defaultPrice = max(
                    0,
                    (int) round(
                        ((float) str_replace(
                            ",",
                            ".",
                            (string) ($_POST["default_monthly_price"] ??
                                number_format(
                                    \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents() / 100,
                                    2,
                                    ".",
                                    "",
                                )),
                        )) * 100,
                    0, \RoundingMode::HalfAwayFromZero),
                );
                if ($defaultPrice <= 0) {
                    $defaultPrice = PRONTOO_MONTHLY_PRICE_CENTS;
                }
                $defaultTrialDays = max(
                    0,
                    min(
                        3650,
                        (int) ($_POST["default_trial_days"] ??
                            \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days()),
                    ),
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set("default_monthly_price_cents", (string) $defaultPrice);
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set("default_trial_days", (string) $defaultTrialDays);
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set(
                    "subscription_pix_key",
                    \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::normalize_subscription_pix_key(
                        (string) ($_POST["subscription_pix_key"] ?? ""),
                    ),
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set(
                    "signup_clinics_blocked",
                    isset($_POST["signup_clinics_blocked"]) ? "1" : "0",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("config_global_atualizada", "configuracao", null, [
                    "campos" => [
                        "suporte",
                        "pix_assinatura",
                        "regra_comercial",
                        "bloqueio_cadastros",
                        "fuso_administrador",
                        "politica_sessao",
                    ],
                    "price_cents" => $defaultPrice,
                    "trial_days" => $defaultTrialDays,
                    "global_admin_timezone" => $adminTimezone,
                    "audit_body" =>
                        "Configurações globais da plataforma atualizadas.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Configurações globais salvas.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_maintenance", ["tab" => "configuracoes"]);
            }
        }
        $tabs =
            '<nav class="notice-filter-chips lead-filter-chips admin-maintenance-tabs" aria-label="Operações de manutenção"><a class="lead-chip ds-filter-chip ' .
            ($tab === "manutencao" ? "active" : "") .
            '" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_maintenance", ["tab" => "manutencao"])) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("construction") .
            '<span class="lead-chip-label">Manutenção</span></a><a class="lead-chip ds-filter-chip ' .
            ($tab === "configuracoes" ? "active" : "") .
            '" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_maintenance", ["tab" => "configuracoes"])) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("settings") .
            '<span class="lead-chip-label">Configurações</span></a></nav>';
        if ($tab === "configuracoes") {
            $defaultPrice = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents();
            $defaultTrialDays = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days();
            $signupBlocked = is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'clinic_signup_blocked'])
                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::clinic_signup_blocked()
                : \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get("signup_clinics_blocked", "0") === "1";
            $adminTimezone = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_global_admin_timezone($uid);
            $timezoneNow = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_now_in_timezone(0, [
                "scope" => "global",
                "timezone" => $adminTimezone,
            ])->format("d/m/Y \à\s H\hi");
            $form =
                '<form method="post" class="compact">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="tab" value="configuracoes"><input type="hidden" name="act" value="save_settings"><section class="settings-section full"><h2>Suporte</h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "E-mail de suporte",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("support_email", "email", \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get("support_email", "")),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Telefone/WhatsApp de suporte",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("support_phone", "text", \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get("support_phone", "")),
                ) .
                '</section><section class="settings-section full"><h2>Preferências do Desenvolvedor</h2><p class="field-help">Este fuso é aplicado apenas à sua credencial global. Consultórios continuam usando o fuso da cidade cadastrada.</p>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                    "Meu fuso horário",
                    "global_admin_timezone",
                    \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_global_timezone_options(),
                    $adminTimezone,
                    "required",
                ) .
                '<p class="field-help">Agora para você: ' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($timezoneNow) .
                '</p></section><section class="settings-section full"><h2>Assinatura</h2><p class="field-help">Regra comercial padrão dos novos consultórios e chave Pix exibida no card de pagamento.</p><div class="two">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Mensalidade padrão",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "default_monthly_price",
                        "number",
                        number_format($defaultPrice / 100, 2, ".", ""),
                        'step="0.01" min="0"',
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Dias de gratuidade",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "default_trial_days",
                        "number",
                        (string) $defaultTrialDays,
                        'min="0" max="3650" step="1"',
                    ),
                ) .
                "</div>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Chave Pix da assinatura",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "subscription_pix_key",
                        "text",
                        \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_pix_key(),
                        'required maxlength="140" placeholder="Ex.: pix@prontoo.app"',
                    ),
                ) .
                '<label class="check-row admin-signup-lock"><input type="checkbox" name="signup_clinics_blocked" value="1" ' .
                ($signupBlocked ? "checked" : "") .
                '><span><b>Bloquear temporariamente novos consultórios</b><small>Quando ativo, os botões públicos de criação de consultório ficam ocultos e a rota de cadastro exibe aviso de pausa.</small></span></label></section><section class="settings-section full"><h2>Segurança</h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Nota interna de política de sessão",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                        "session_policy_note",
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get(
                            "session_policy_note",
                            "Sessões administrativas devem ser encerradas ao final do uso.",
                        ),
                    ),
                ) .
                '</section><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
                "<span>Salvar configurações</span></button></form>";
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Manutenção e Configurações",
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Manutenção e Configurações", "") . $tabs . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($form),
            );
            return;
        }
        $active = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::maintenance_active();
        $msg = (string) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get(
            "maintenance_message",
            "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.",
        );
        $form =
            '<form method="post" class="compact">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="tab" value="manutencao"><input type="hidden" name="act" value="save_maintenance"><label class="check"><input type="checkbox" name="active" ' .
            ($active ? "checked" : "") .
            "> Ativar manutenção para colaboradores não globais</label>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Mensagem pública de manutenção", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("message", $msg)) .
            '<button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
            "<span>Salvar manutenção</span></button></form>";
        $items = [
            [
                "icon" => $active ? "engineering" : "check_circle",
                "time" => "Agora",
                "title" => $active
                    ? "Modo manutenção ativo"
                    : "Modo manutenção inativo",
                "body" => $msg,
                "meta" => "A tela de login permanece acessível.",
            ],
        ];
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Manutenção e Configurações",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Manutenção e Configurações", "") .
                $tabs .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($form) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($items)),
        );
    
    }

    public static function page_admin_settings(): void
    
    {
    
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_maintenance", ["tab" => "configuracoes"]);
    
    }
}
