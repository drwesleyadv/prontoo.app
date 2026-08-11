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

final class SubscriptionSettingsRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function clinic_subscription_cta(array $cl, string $tab = "assinatura"): string
    
    {
    
        if ($tab !== "assinatura") {
            return "";
        }
        $cid = (int) ($cl["id"] ?? 0);
        $pending = $cid > 0 ? \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::clinic_subscription_pending_payment($cid) : null;
        $price =
            (int) ($cl["monthly_price_cents"] ?? \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents());
        if ($price <= 0) {
            $price = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents();
        }
        if ($pending) {
            if (\Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_payment_is_proof_review($pending)) {
                $ico = "pending_actions";
                $eyebrow = "Comprovante em análise";
                $title = "Comprovante recebido";
                $body =
                    "O arquivo foi enviado para conferência do Desenvolvedor. Enquanto isso, acompanhe o retorno por Avisos.";
                $pill = "Aguardando conferência";
            } else {
                $ico = "task_alt";
                $eyebrow = "Pagamento informado";
                $title = "Acesso liberado em confiança";
                $body =
                    "Recebemos a confirmação de pagamento e a assinatura foi liberada enquanto a transação é conferida.";
                $pill = "Em conferência";
            }
            $msg =
                '<div class="subscription-pending-friendly subscription-state-card">' .
                '<span class="subscription-hero-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
                "</span>" .
                '<div class="subscription-state-copy"><span class="eyebrow">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($eyebrow) .
                "</span><h3>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                "</h3><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($body) .
                "</p></div>" .
                '<span class="subscription-state-pill">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pill) .
                "</span></span>" .
                "</div>";
            return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                $msg,
                "subscription-payment-card subscription-payment-soft only-signature-screen subscription-payment-pending",
            );
        }
        $kind = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::clinic_subscription_kind($cl);
        $label = \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::clinic_subscription_action_label($kind);
        $blocked = mb_trim((string) ($cl["subscription_trust_blocked_until"] ?? ""));
        $blockedActive = $blocked !== "" && strtotime($blocked) > time();
        $needProof = $blockedActive;
        $headline = $needProof ? "Enviar comprovante" : $label;
        $intro = $needProof
            ? "Envie o comprovante do Pix para conferência. A liberação ocorre após a aprovação administrativa."
            : ($kind === "active"
                ? "Faça o Pix e confirme o pagamento para acrescentar mais 30 dias à assinatura."
                : "Faça o Pix e confirme o pagamento para reativar a operação do consultório.");
        $pixKey = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_pix_key();
        $copyStatus =
            '<span class="pix-copy-status" data-pix-copy-status aria-live="polite"></span>';
        $pixBox =
            '<button type="button" class="pix-key-box subscription-pix-copy" data-copy-pix-key data-pix-value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pixKey) .
            '" title="Clique para copiar a chave Pix">' .
            '<span class="pix-key-symbol">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::pix_symbol() .
            "</span>" .
            '<span class="pix-key-text"><small>Chave Pix</small><strong>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pixKey) .
            "</strong>" .
            $copyStatus .
            "</span>" .
            '<span class="pix-key-value" aria-label="Valor da assinatura mensal">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($price)) .
            "</span>" .
            "</button>";
        $steps =
            '<div class="subscription-steps" aria-label="Etapas para regularizar a assinatura">' .
            '<div class="subscription-step"><span>1</span><p>Faça o Pix no valor indicado.</p></div>' .
            '<div class="subscription-step"><span>2</span><p>' .
            ($needProof
                ? "Anexe o comprovante para análise."
                : "Marque a confirmação de pagamento.") .
            "</p></div>" .
            '<div class="subscription-step"><span>3</span><p>' .
            ($needProof
                ? "Aguarde a conferência do suporte."
                : "A liberação ocorre em confiança enquanto conferimos a transação.") .
            "</p></div>" .
            "</div>";
        $html =
            '<form method="post" enctype="multipart/form-data" class="subscription-payment-box subscription-payment-friendly subscription-checkout" data-subscription-payment-form>' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="subscription_claim"><input type="hidden" name="tab" value="assinatura">' .
            '<div class="subscription-payment-head subscription-checkout-head">' .
            '<div class="subscription-payment-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::pix_symbol() .
            "</div>" .
            '<div><span class="eyebrow">Pagamento Pix</span><h3>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($headline) .
            "</h3><p>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($intro) .
            "</p></div>" .
            "</div>" .
            '<div class="subscription-checkout-grid"><div class="subscription-pix-panel"><h4>Dados do pagamento</h4>' .
            $pixBox .
            "</div>" .
            $steps .
            "</div>";
        if ($needProof) {
            $html .=
                '<div class="subscription-confirm-panel">' .
                '<p class="subscription-note">Como um pagamento anterior não foi confirmado, nesta etapa pediremos o comprovante. Após o envio, a administração receberá uma Ação Recomendada para visualizar, aprovar ou recusar o arquivo.</p>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Comprovante de pagamento",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "payment_proof",
                        "file",
                        "",
                        'accept="application/pdf,image/png,image/jpeg,image/webp" required',
                    ),
                ) .
                "</div>";
        } else {
            $html .=
                '<div class="subscription-confirm-panel">' .
                '<label class="checkline subscription-checkline"><input type="checkbox" name="already_paid" value="1" data-payment-paid-toggle required><span>Já paguei o Pix informado acima.</span></label>' .
                '<div data-payment-paid-fields hidden class="subscription-paid-fields">' .
                '<label class="checkline"><input type="checkbox" name="paid_own_account" value="1" data-own-account-toggle><span>Enviei de uma conta no meu próprio nome.</span></label>' .
                "<div data-holder-field>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Quem é o titular da conta que fez o Pix?",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "payment_holder_name",
                        "text",
                        "",
                        'maxlength="180" placeholder="Informe quando a conta não estiver no próprio nome"',
                    ),
                ) .
                "</div>" .
                "</div>" .
                "</div>";
        }
        $html .=
            '<div class="form-actions subscription-actions"><button class="primary" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($needProof ? "upload_file" : "check_circle") .
            "<span>" .
            ($needProof ? "Enviar comprovante" : "Confirmar pagamento") .
            "</span></button></div></form>";
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            $html,
            "subscription-payment-card subscription-payment-soft only-signature-screen",
        );
    
    }

    public static function clinic_subscription_rejected_notice(
        int $cid,
        bool $proofRejected = false,
    ): void 
    {
    
        try {
            $cl =
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.subscription_settings.02.clinic_subscription_rejected_notice.01', [$cid], []) ?:
                [];
            $uid = (int) ($cl["owner_user_id"] ?? 0);
            if ($uid <= 0) {
                $uid = (int) ($cl["manager_user_id"] ?? 0);
            }
            if ($uid <= 0) {
                return;
            }
            $title = $proofRejected
                ? "Comprovante não aprovado"
                : "Pagamento não confirmado";
            $body = $proofRejected
                ? "O comprovante enviado não foi aprovado. Envie um novo comprovante de pagamento na tela Meu Consultório > Assinatura para nova conferência."
                : "O pagamento informado não foi confirmado. Envie o arquivo do comprovante de pagamento na tela Meu Consultório > Assinatura para nova conferência.";
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.subscription_settings.02.clinic_subscription_rejected_notice.02', [$cid, $title, $body, 1, "user", $uid], []);
        } catch (Throwable $e) {
            error_log("[Prontoo subscription notice] " . $e->getMessage());
        }
    
    }

    public static function clinic_subscription_register_claim(
        int $cid,
        int $uid,
        array $cl,
    ): string 
    {
    
        if (\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::clinic_subscription_pending_payment($cid)) {
            throw new RuntimeException(
                "Continue trabalhando enquanto confirmamos o recebimento. Nenhuma providência é necessária neste momento.",
            );
        }
        $blocked = mb_trim((string) ($cl["subscription_trust_blocked_until"] ?? ""));
        $blockedActive = $blocked !== "" && strtotime($blocked) > time();
        $proof = null;
        if ($blockedActive) {
            $proof = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_payment_proof_upload($cid, $uid);
            if ($proof === null) {
                throw new RuntimeException(
                    "Envie o comprovante de pagamento para continuar.",
                );
            }
        } else {
            if (empty($_POST["already_paid"])) {
                throw new RuntimeException(
                    "Marque a opção Já paguei para concluir.",
                );
            }
        }
        $own = isset($_POST["paid_own_account"]) ? 1 : 0;
        $holder = mb_trim((string) ($_POST["payment_holder_name"] ?? ""));
        if (!$blockedActive && !$own && $holder === "") {
            throw new RuntimeException(
                "Informe o nome do titular da conta usada no pagamento.",
            );
        }
        $price =
            (int) ($cl["monthly_price_cents"] ?? \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents());
        if ($price <= 0) {
            $price = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents();
        }
        $until = \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_renewal_until($cl);
        $trustUntil = \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_trust_release_until();
        $provisionalUntil = \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::later_date(
            (string) ($cl["paid_until"] ?? ""),
            $trustUntil,
        );
        try {
            return \Prontoo\Runtime\Operational\OperationalComposition::subscriptionClaim()
                ->register(
                    $blockedActive,
                    $cid,
                    $uid,
                    $price,
                    (bool) $own,
                    $holder,
                    $proof,
                    $until,
                    $provisionalUntil,
                    static fn(...$arguments): bool =>
                        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                            ...$arguments,
                        ),
                );
        } catch (Throwable $e) {
            if ($proof !== null) {
                \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_delete_proof($proof);
            }
            throw $e;
        }
    
    }

    public static function clinic_settings_nav(string $tab): string
    
    {
    
        $tabs = [
            "perfil" => ["Identificação", "home_health"],
            "setores" => ["Departamentos", "corporate_fare"],
            "visual" => ["Aparência", "palette"],
            "assinatura" => ["Assinatura", "credit_card"],
        ];
        $h = "";
        foreach ($tabs as $k => $v) {
            $cls = $tab === $k ? " primary" : " ghost";
            $h .=
                '<a class="' .
                $cls .
                ' small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("settings", ["tab" => $k]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($v[1]) .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($v[0]) .
                "</span></a>";
        }
        return $h;
    
    }
}
