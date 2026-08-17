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

final class AdminPagesRuntimeOperations06
{
    private function __construct()
    {
    }

    public static function admin_subscription_payment_review_handle(
        string $act,
        Closure $redirectAfterClinicAction,
    ): void
    {
        if (
            in_array(
                $act,
                ["confirm_subscription_payment", "reject_subscription_payment"],
                true,
            )
        ) {
            $paymentId = (int) ($_POST["payment_id"] ?? 0);
            $payment =
                $paymentId > 0
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.admin_pages.08.page_admin_clinics.11', [$paymentId], [])
                    : null;
            if (!$payment) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Pedido de assinatura não encontrado ou já analisado.",
                    "bad",
                );
                $redirectAfterClinicAction();
            }
            $adminId = (int) (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx()["user"]["id"] ?? 0);
            $clinicId = (int) $payment["clinic_id"];
            $hadProof = mb_trim((string) ($payment["proof_path"] ?? "")) !== "";
            if ($act === "confirm_subscription_payment") {
                $reviewNote = $hadProof ? "Comprovante aprovado." : "Recebimento confirmado.";
                if ($hadProof) {
                    \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_delete_proof(
                        (string) $payment["proof_path"],
                    );
                }
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.12', [$adminId, $reviewNote, $paymentId, $clinicId], []);
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.13', [$payment["applied_until"] ?: null, $clinicId], []);
                if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                    \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                    $hadProof ? "assinatura_comprovante_aprovado" : "assinatura_pagamento_confirmado",
                    "assinatura",
                    $clinicId,
                    [
                        "pagamento_id" => $paymentId,
                        "audit_body" => $hadProof
                            ? "Desenvolvedor aprovou o comprovante enviado. A assinatura foi ativada de forma definitiva."
                            : "Desenvolvedor confirmou o pagamento informado. A assinatura foi ativada de forma definitiva.",
                    ],
                );
                if ($hadProof) {
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                        "assinatura_comprovante_excluido",
                        "assinatura",
                        $clinicId,
                        [
                            "pagamento_id" => $paymentId,
                            "audit_body" => "Após a aprovação, o comprovante enviado foi excluído dos registros operacionais da assinatura.",
                        ],
                    );
                }
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    $hadProof
                        ? "Comprovante aprovado. A assinatura foi ativada de forma definitiva."
                        : "Pagamento confirmado. A assinatura foi ativada de forma definitiva.",
                );
                $redirectAfterClinicAction();
            }
            $reviewNote = $hadProof ? "Comprovante recusado." : "Recebimento não confirmado.";
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.14', [$adminId, $reviewNote, $paymentId, $clinicId], []);
            $trustBlockedUntil = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp(
                "2099-12-31 23:59:59",
            );
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.15', [$trustBlockedUntil, $clinicId], []);
            if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
            }
            \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations02::clinic_subscription_rejected_notice($clinicId, $hadProof);
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                $hadProof ? "assinatura_comprovante_recusado" : "assinatura_pagamento_nao_confirmado",
                "assinatura",
                $clinicId,
                [
                    "pagamento_id" => $paymentId,
                    "audit_body" => $hadProof
                        ? "Desenvolvedor recusou o comprovante enviado. Consultório retornou para Somente Leitura e poderá enviar novo comprovante."
                        : "Desenvolvedor recusou o pagamento informado. Consultório retornou para Somente Leitura e exigirá comprovante.",
                ],
            );
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                $hadProof
                    ? "Comprovante recusado. O consultório voltou para Somente Leitura e poderá enviar novo comprovante."
                    : "Pagamento recusado. O consultório voltou para Somente Leitura e a clínica recebeu aviso.",
            );
            $redirectAfterClinicAction();
        }
    }

    public static function admin_subscription_payment_reviews_html(): string
    {
        $paymentReviewActions = [];
        try {
            $pendingPayments = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.16', [], [])->fetchAll();
            foreach ($pendingPayments as $payment) {
                $holder =
                    (int) $payment["account_self"] === 1
                        ? "Conta própria"
                        : "Titular: " . ($payment["account_holder_name"] ?: "não informado");
                $viewLink = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_payment_proof_view_link($payment);
                $isProofReview = \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_payment_is_proof_review($payment);
                $confirmLabel = $isProofReview ? "Aprovar comprovante" : "Confirmar pagamento";
                $confirmIcon = $isProofReview ? "verified" : "check_circle";
                $rejectLabel = $isProofReview ? "Recusar comprovante" : "Recusar";
                $rejectQuestion = $isProofReview ? "Recusar este comprovante?" : "Recusar este pagamento informado?";
                $forms =
                    $viewLink .
                    '<form method="post" class="inline">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="confirm_subscription_payment"><input type="hidden" name="payment_id" value="' .
                    (int) $payment["id"] .
                    '"><button class="primary small" type="submit">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label($confirmLabel, $confirmIcon) .
                    '</button></form><form method="post" class="inline" onsubmit="return confirm(&quot;' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($rejectQuestion) .
                    '&quot;)">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="reject_subscription_payment"><input type="hidden" name="payment_id" value="' .
                    (int) $payment["id"] .
                    '"><button class="danger small" type="submit">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label($rejectLabel, "block") .
                    "</button></form>";
                $paymentReviewActions[] = [
                    "icon" => $isProofReview ? "upload_file" : "payments",
                    "time" => "Assinatura",
                    "title" =>
                        ($isProofReview ? "Visualizar e aprovar comprovante de " : "Confirmar pagamento informado por ") .
                        ($payment["display_name"] ?? "consultório"),
                    "body" =>
                        ($isProofReview
                            ? "O consultório enviou comprovante após um pagamento recusado. Abra o arquivo antes de aprovar ou recusar. Valor informado: "
                            : "Valor informado: ") .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $payment["amount_cents"]) .
                        " · " .
                        $holder,
                    "meta" => $isProofReview
                        ? "Ao aprovar, a assinatura fica ativa de forma definitiva. Ao recusar, o consultório volta para Somente Leitura."
                        : "Ao confirmar, a assinatura fica ativa de forma definitiva. Ao recusar, o consultório volta para Somente Leitura e deverá enviar comprovante.",
                    "html" => $forms,
                    "class" => $isProofReview ? "subscription-action proof-review" : "subscription-action",
                ];
            }
        } catch (Throwable $e) {
            error_log("[Prontoo pending subscription payments] " . $e->getMessage());
        }
        $paymentReviews =
            $paymentReviewActions === []
                ? ""
                : \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<div class="section-head"><div><h2>Pagamentos aguardando análise</h2><p>Confirme ou recuse as solicitações enviadas pelos consultórios.</p></div></div>' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($paymentReviewActions),
                    "developer-actions-card admin-clinic-payment-reviews",
                );
        return $paymentReviews;
    }

    public static function page_admin_administration(): void

    {

        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_administration");
        $content = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::developer_administration_tools_html(
            static fn(string $route): string => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route),
        );
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Administração", "") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($content, "developer-administration-card");
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Administração · Desenvolvedor", $body);
    }

    public static function page_admin_people(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_users");
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.06.page_admin_people.01', [], [])->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                "icon" => (int) $r["active"] ? "person" : "person_off",
                "time" => (int) $r["is_global_admin"] ? "Desenvolvedor" : "Usuário",
                "title" => (string) $r["name"],
                "body" =>
                    "CPF " .
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) ($r["cpf"] ?? "")) .
                    " · " .
                    ((int) $r["vinculos"]) .
                    " vínculo(s) com consultórios",
                "meta" => mb_trim((string) ($r["email"] ?? "")) ?: "sem e-mail",
            ];
        }
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Usuários",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Usuários",
                "Credenciais, pessoas cadastradas e vínculos ativos na plataforma.",
            ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($items, "Nenhuma pessoa cadastrada."),
                    "admin-people-card",
                ),
        );
    
    }

    public static function page_admin_users(): void
    
    {
    
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_people");
    
    }

}
