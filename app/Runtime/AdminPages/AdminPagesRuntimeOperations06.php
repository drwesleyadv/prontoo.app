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

    public static function page_admin_painel(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_painel");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            if (
                in_array(
                    $act,
                    ["confirm_subscription_payment", "reject_subscription_payment"],
                    true,
                )
            ) {
                $pid = (int) ($_POST["payment_id"] ?? 0);
                $p =
                    $pid > 0
                        ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.admin_pages.06.page_admin_painel.02', [$pid], [])
                        : null;
                if (!$p) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Pedido de assinatura não encontrado ou já analisado.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_painel");
                }
                $adminId = (int) (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx()["user"]["id"] ?? 0);
                $cid = (int) $p["clinic_id"];
                $hadProof = mb_trim((string) ($p["proof_path"] ?? "")) !== "";
                if ($act === "confirm_subscription_payment") {
                    $reviewNote = $hadProof
                        ? "Comprovante aprovado."
                        : "Recebimento confirmado.";
                    if ($hadProof) {
                        \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_delete_proof(
                            (string) $p["proof_path"],
                        );
                    }
                    \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.06.page_admin_painel.03', [$adminId, $reviewNote, $pid, $cid], []);
                    \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.06.page_admin_painel.04', [$p["applied_until"] ?: null, $cid], []);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                        $hadProof
                            ? "assinatura_comprovante_aprovado"
                            : "assinatura_pagamento_confirmado",
                        "assinatura",
                        $cid,
                        [
                            "pagamento_id" => $pid,
                            "audit_body" => $hadProof
                                ? "Desenvolvedor aprovou o comprovante enviado. A assinatura foi ativada de forma definitiva."
                                : "Desenvolvedor confirmou o pagamento informado. A assinatura foi ativada de forma definitiva.",
                        ],
                    );
                    if ($hadProof) {
                        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                            "assinatura_comprovante_excluido",
                            "assinatura",
                            $cid,
                            [
                                "pagamento_id" => $pid,
                                "audit_body" =>
                                    "Após a aprovação, o comprovante enviado foi excluído dos registros operacionais da assinatura.",
                            ],
                        );
                    }
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        $hadProof
                            ? "Comprovante aprovado. A assinatura foi ativada de forma definitiva."
                            : "Pagamento confirmado. A assinatura foi ativada de forma definitiva.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_painel");
                }
                $reviewNote = $hadProof
                    ? "Comprovante recusado."
                    : "Recebimento não confirmado.";
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.06.page_admin_painel.05', [$adminId, $reviewNote, $pid, $cid], []);
                $trustBlockedUntil = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp(
                    "2099-12-31 23:59:59",
                );
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.06.page_admin_painel.06', [$trustBlockedUntil, $cid], []);
                \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations02::clinic_subscription_rejected_notice($cid, $hadProof);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                    $hadProof
                        ? "assinatura_comprovante_recusado"
                        : "assinatura_pagamento_nao_confirmado",
                    "assinatura",
                    $cid,
                    [
                        "pagamento_id" => $pid,
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
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_painel");
            }
        }
        $qInt = function (string $query, array $p = [], array $context = []): int {
    
            return (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->safeScalar('operational.admin_pages.06.page_admin_painel.07', $p, 0, ['query' => $query] + $context);
        };
        $readOnly = $qInt('read.admin_pages.06.page_admin_painel.01');
        $locks = $qInt('read.admin_pages.06.page_admin_painel.04', [], []);
        $openErrors = $qInt('read.admin_pages.06.page_admin_painel.05', [], []);
        $errors24h = $qInt('read.admin_pages.06.page_admin_painel.06', [], []);
        $scopeStats24h = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_guard_stats(24);
        $scopeViolations24h = (int) $scopeStats24h["actionable"];
        $scopeGroups24h = $scopeViolations24h > 0
            ? \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_guard_groups(24, 12)
            : [];
        $health = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::platform_health_snapshot([
            "open_errors" => $openErrors,
            "login_locks" => $locks,
            "scope_alerts_24h" => $scopeViolations24h,
        ]);
        $checks = (array) ($health["checks"] ?? []);
        $actions = [];
        try {
            $pending = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.06.page_admin_painel.08', [], [])->fetchAll();
            foreach ($pending as $p) {
                $hasProof = mb_trim((string) ($p["proof_path"] ?? "")) !== "";
                $hadRejected = (int) ($p["rejected_count"] ?? 0) > 0;
                $holder =
                    (int) $p["account_self"] === 1
                        ? "Conta própria"
                        : "Titular: " .
                            ($p["account_holder_name"] ?: "não informado");
                $view = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_payment_proof_view_link($p);
                $isProofReview = \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_payment_is_proof_review($p);
                $confirmLabel = $isProofReview
                    ? "Aprovar comprovante"
                    : "Confirmar pagamento";
                $confirmIcon = $isProofReview ? "verified" : "check_circle";
                $rejectLabel = $isProofReview ? "Recusar comprovante" : "Recusar";
                $rejectQuestion = $isProofReview
                    ? "Recusar este comprovante?"
                    : "Recusar este pagamento informado?";
                $forms =
                    $view .
                    '<form method="post" class="inline">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="confirm_subscription_payment"><input type="hidden" name="payment_id" value="' .
                    (int) $p["id"] .
                    '"><button class="primary small" type="submit">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label($confirmLabel, $confirmIcon) .
                    '</button></form><form method="post" class="inline" onsubmit="return confirm(&quot;' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($rejectQuestion) .
                    '&quot;)">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="reject_subscription_payment"><input type="hidden" name="payment_id" value="' .
                    (int) $p["id"] .
                    '"><button class="danger small" type="submit">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label($rejectLabel, "block") .
                    "</button></form>";
                if ($isProofReview) {
                    $actions[] = [
                        "icon" => "upload_file",
                        "time" => "Assinatura",
                        "title" =>
                            "Visualizar e aprovar comprovante de " .
                            ($p["display_name"] ?? "consultório"),
                        "body" =>
                            "O consultório enviou comprovante após um pagamento recusado. Abra o arquivo antes de aprovar ou recusar. Valor informado: " .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $p["amount_cents"]) .
                            " · " .
                            $holder,
                        "meta" =>
                            "Ao aprovar, a assinatura fica ativa de forma definitiva. Ao recusar, o consultório volta para Somente Leitura.",
                        "html" => $forms,
                        "class" => "subscription-action proof-review",
                    ];
                } else {
                    $actions[] = [
                        "icon" => "payments",
                        "time" => "Assinatura",
                        "title" =>
                            "Confirmar pagamento informado por " .
                            ($p["display_name"] ?? "consultório"),
                        "body" =>
                            "Valor informado: " .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $p["amount_cents"]) .
                            " · " .
                            $holder,
                        "meta" =>
                            "Ao confirmar, a assinatura fica ativa de forma definitiva. Ao recusar, o consultório volta para Somente Leitura e deverá enviar comprovante.",
                        "html" => $forms,
                        "class" => "subscription-action",
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo pending subscription payments] " . $e->getMessage(),
            );
        }
        if (empty($checks["database"])) {
            $actions[] = [
                "icon" => "database_off",
                "time" => "Banco",
                "title" => "Banco de dados indisponível no autoteste",
                "body" =>
                    "A plataforma não conseguiu confirmar a conexão básica com o banco.",
                "meta" => "Incidente técnico crítico.",
            ];
        }
        if (empty($checks["storage"])) {
            $actions[] = [
                "icon" => "folder_off",
                "time" => "Arquivos",
                "title" => "Storage sem permissão de escrita",
                "body" =>
                    "Arquivos temporários, métricas e comprovantes dependem de escrita em /ssd.",
                "meta" => "Verifique permissões.",
            ];
        }
        $scopeLogicOk = !empty(($checks["scope_guard_logic"] ?? [])["ok"]);
        $scopeContextOk = !empty(($checks["scope_guard_context"] ?? [])["ok"]);
        if (!$scopeLogicOk || !$scopeContextOk) {
            $actions[] = [
                "icon" => "shield_lock",
                "time" => "Isolamento",
                "title" => "Autoteste de isolamento exige revisão",
                "body" => "A prova determinística do guardião de escopo ou do contexto entre consultórios não concluiu todos os casos críticos.",
                "meta" => "Trate como condição estrutural até a investigação.",
            ];
        }
        if (empty(($health["components"]["integrity"] ?? [])["ok"])) {
            $actions[] = [
                "icon" => "gpp_bad",
                "time" => "Integridade",
                "title" => "Integridade da auditoria exige revisão",
                "body" => "A cadeia de auditoria ou registros recentes não concluíram a verificação.",
                "meta" => "Investigue antes de considerar a plataforma operacional.",
            ];
        }
        if (empty(($health["components"]["version"] ?? [])["ok"])) {
            $actions[] = [
                "icon" => "deployed_code_alert",
                "time" => "Versão",
                "title" => "Contrato de versão divergente",
                "body" => "A release publicada não concluiu o contrato determinístico de versão.",
                "meta" => "Revise os artefatos canônicos da release.",
            ];
        }
        if ($openErrors > 0) {
            $actions[] = [
                "icon" => "bug_report",
                "time" => "Erros",
                "title" => $openErrors . " erro(s) aberto(s)",
                "body" => "Há eventos técnicos sem resolução registrada.",
                "meta" => $errors24h . " nas últimas 24h.",
            ];
        }
        if ($locks > 0) {
            $actions[] = [
                "icon" => "lock_clock",
                "time" => "Segurança",
                "title" => $locks . " bloqueio(s) de login ativo(s)",
                "body" =>
                    "Confirme se são usuários reais com dificuldade ou tentativa indevida.",
                "meta" => "Use o painel de Segurança.",
            ];
        }
        if ($scopeViolations24h > 0 && $scopeLogicOk && $scopeContextOk) {
            $patterns = max(1, (int) ($scopeStats24h["patterns"] ?? 0));
            $objective = (int) ($scopeStats24h["objective"] ?? 0);
            $review = (int) ($scopeStats24h["review"] ?? 0);
            $latest = $scopeGroups24h[0] ?? [];
            $latestDefinition = $latest
                ? \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_scope_guard_definition(
                    (string) ($latest["violation_key"] ?? ""),
                )
                : [];
            $detailsHtml = $latest
                ? \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_evidence_html($latest, true)
                : "";
            $detailsHtml .=
                '<a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_security") .
                '#scope-isolation">Abrir todas as evidências em Segurança</a>';
            $actions[] = [
                "icon" => "policy",
                "time" => "Isolamento",
                "title" =>
                    $patterns .
                    " padrão(ões) de escopo bloqueado(s) nas últimas 24h",
                "body" =>
                    "As " .
                    $scopeViolations24h .
                    " ocorrência(s) foram interrompidas antes da execução SQL. " .
                    $objective .
                    " têm causa estrutural demonstrável e " .
                    $review .
                    " exigem revisão porque a prova lógica foi insuficiente." .
                    ($latestDefinition
                        ? " Mais recente: " .
                            (string) $latestDefinition["cause"]
                        : ""),
                "meta" =>
                    "O agrupamento por fingerprint reduz duplicidade; esta contagem não confirma acesso cruzado.",
                "html" => $detailsHtml,
                "class" => "scope-recommended-action",
            ];
        }
        if ($readOnly > 0) {
            $actions[] = [
                "icon" => "payments",
                "time" => "Assinaturas",
                "title" =>
                    $readOnly . " consultório(s) em somente leitura ou vencido(s)",
                "body" =>
                    "O acesso operacional pode estar limitado por assinatura.",
                "meta" => "Impacta agenda, financeiro e rotina dos consultórios.",
            ];
        }

        foreach ($actions as &$action) {
            if (!empty($action["html"])) {
                continue;
            }
            $targetRoute = match ((string) ($action["time"] ?? "")) {
                "Erros" => "admin_errors",
                "Segurança", "Isolamento", "Banco", "Arquivos" => "admin_health",
                "Integridade" => "admin_integrity",
                "Versão" => "admin_diagnostics",
                "Assinaturas" => "admin_clinics",
                default => "",
            };
            if ($targetRoute !== "") {
                $action["html"] =
                    '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($targetRoute) .
                    '">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Abrir", "arrow_forward") .
                    "</a>";
            }
        }
        unset($action);

        $overview = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::developer_overview_html(
            [
                "actions" => $actions,
                "health" => $health,
            ],
            static fn(string $route): string => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route),
            static fn(string $label, string $icon): string => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label($label, $icon),
        );
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Visão geral", "") .
            $overview;
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Visão geral · Desenvolvedor", $body);
    
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
