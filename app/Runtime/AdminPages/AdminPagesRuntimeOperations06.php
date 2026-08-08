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
    
        require_can("admin_painel");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            if ($act === "goal") {
                $goalContext = ctx();
                $cid = (int) ($goalContext["clinic_id"] ?? 0);
                $uid = (int) ($goalContext["user"]["id"] ?? 0);
                if ($cid <= 0 || $uid <= 0) {
                    throw new RuntimeException(
                        "A meta mensal exige um consultório ativo.",
                    );
                }
                $target = parse_money_cents((string) ($_POST["target"] ?? "0"));
                $share = isset($_POST["share_with_team"]) ? 1 : 0;
                $base = (string) ($_POST["base_metric"] ?? "efetivada");
                if (!in_array($base, ["prevista", "efetivada"], true)) {
                    $base = "efetivada";
                }
                $month = app_month_in_timezone($cid, $goalContext);
                q(
                    "INSERT INTO pi_financial_goals (clinic_id,month_key,target_cents,base_metric,share_with_team,updated_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_cents=VALUES(target_cents), base_metric=VALUES(base_metric), share_with_team=VALUES(share_with_team), updated_by=VALUES(updated_by), updated_at=NOW()",
                    [$cid, $month, $target, $base, $share, $uid],
                );
                audit("meta_financeira_salva", "financeiro", $cid, [
                    "valor" => $target,
                    "base" => $base,
                    "compartilhar" => $share,
                ]);
                flash("Meta mensal atualizada.");
                redirect("financial", ["tab" => "meta"]);
            }
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
                        ? one(
                            "SELECT sp.*,c.display_name FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.id=? AND sp.status='pending_admin'",
                            [$pid],
                        )
                        : null;
                if (!$p) {
                    flash(
                        "Pedido de assinatura não encontrado ou já analisado.",
                        "bad",
                    );
                    redirect("admin_painel");
                }
                $adminId = (int) (ctx()["user"]["id"] ?? 0);
                $cid = (int) $p["clinic_id"];
                $hadProof = mb_trim((string) ($p["proof_path"] ?? "")) !== "";
                if ($act === "confirm_subscription_payment") {
                    $reviewNote = $hadProof
                        ? "Comprovante aprovado."
                        : "Recebimento confirmado.";
                    if ($hadProof) {
                        subscription_payment_delete_proof(
                            (string) $p["proof_path"],
                        );
                    }
                    q(
                        "UPDATE pi_subscription_payments SET status='confirmed', reviewed_by=?, reviewed_at=NOW(), review_note=?, proof_path=NULL WHERE id=? AND clinic_id=?",
                        [$adminId, $reviewNote, $pid, $cid],
                    );
                    q(
                        "UPDATE pi_clinics SET active=1, subscription_status='active', paid_until=COALESCE(?,paid_until), subscription_trust_blocked_until=NULL, subscription_last_payment_claim_at=NULL, updated_at=NOW() WHERE id=?",
                        [$p["applied_until"] ?: null, $cid],
                    );
                    audit(
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
                        audit(
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
                    flash(
                        $hadProof
                            ? "Comprovante aprovado. A assinatura foi ativada de forma definitiva."
                            : "Pagamento confirmado. A assinatura foi ativada de forma definitiva.",
                    );
                    redirect("admin_painel");
                }
                $reviewNote = $hadProof
                    ? "Comprovante recusado."
                    : "Recebimento não confirmado.";
                q(
                    "UPDATE pi_subscription_payments SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=? AND clinic_id=?",
                    [$adminId, $reviewNote, $pid, $cid],
                );
                $trustBlockedUntil = app_storage_timestamp(
                    "2099-12-31 23:59:59",
                );
                q(
                    "UPDATE pi_clinics SET subscription_status='read_only', paid_until=CURDATE(), subscription_trust_blocked_until=?, updated_at=NOW() WHERE id=?",
                    [$trustBlockedUntil, $cid],
                );
                clinic_subscription_rejected_notice($cid, $hadProof);
                audit(
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
                flash(
                    $hadProof
                        ? "Comprovante recusado. O consultório voltou para Somente Leitura e poderá enviar novo comprovante."
                        : "Pagamento recusado. O consultório voltou para Somente Leitura e a clínica recebeu aviso.",
                );
                redirect("admin_painel");
            }
        }
        $qInt = function (string $sql, array $p = []): int {
    
            return (int) safe_val($sql, $p, 0);
        };
        $modelClinicWhere = admin_model_clinic_exclude_sql("id");
        $readOnly = $qInt(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (subscription_status='read_only' OR (paid_until IS NOT NULL AND paid_until<CURDATE())) $modelClinicWhere",
        );
        $trialEnding = $qInt(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='trial' AND trial_ends_at IS NOT NULL AND trial_ends_at>=NOW() AND trial_ends_at<DATE_ADD(NOW(), INTERVAL 7 DAY) $modelClinicWhere",
        );
        $onboardingPending = $qInt(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND onboarding_done=0 $modelClinicWhere",
        );
        $locks = $qInt(
            "SELECT COUNT(*) FROM pi_login_locks WHERE locked_until>NOW()",
        );
        $openErrors = $qInt(
            "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL",
        );
        $errors24h = $qInt(
            "SELECT COUNT(*) FROM pi_error_events WHERE created_at>=DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        );
        $scopeStats24h = admin_scope_guard_stats(24);
        $scopeViolations24h = (int) $scopeStats24h["actionable"];
        $scopeGroups24h = $scopeViolations24h > 0
            ? admin_scope_guard_groups(24, 12)
            : [];
        $checks = platform_backend_selftest([
            "open_errors" => $openErrors,
            "login_locks" => $locks,
            "scope_alerts_24h" => $scopeViolations24h,
        ]);
        $actions = [];
        try {
            $pendingModelWhere = admin_model_clinic_exclude_sql("sp.clinic_id");
            $pending = q(
                "SELECT sp.id,sp.clinic_id,sp.amount_cents,sp.account_self,sp.account_holder_name,sp.proof_path,sp.applied_until,sp.created_at,c.display_name,(SELECT COUNT(*) FROM pi_subscription_payments spr WHERE spr.clinic_id=sp.clinic_id AND spr.status='rejected') AS rejected_count FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.status='pending_admin' $pendingModelWhere ORDER BY sp.created_at ASC LIMIT 20",
            )->fetchAll();
            foreach ($pending as $p) {
                $hasProof = mb_trim((string) ($p["proof_path"] ?? "")) !== "";
                $hadRejected = (int) ($p["rejected_count"] ?? 0) > 0;
                $holder =
                    (int) $p["account_self"] === 1
                        ? "Conta própria"
                        : "Titular: " .
                            ($p["account_holder_name"] ?: "não informado");
                $view = subscription_payment_proof_view_link($p);
                $isProofReview = subscription_payment_is_proof_review($p);
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
                    csrf_field() .
                    '<input type="hidden" name="act" value="confirm_subscription_payment"><input type="hidden" name="payment_id" value="' .
                    (int) $p["id"] .
                    '"><button class="primary small" type="submit">' .
                    action_summary_label($confirmLabel, $confirmIcon) .
                    '</button></form><form method="post" class="inline" onsubmit="return confirm(&quot;' .
                    e($rejectQuestion) .
                    '&quot;)">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="reject_subscription_payment"><input type="hidden" name="payment_id" value="' .
                    (int) $p["id"] .
                    '"><button class="danger small" type="submit">' .
                    action_summary_label($rejectLabel, "block") .
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
                            money_br((int) $p["amount_cents"]) .
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
                            money_br((int) $p["amount_cents"]) .
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
        if ($scopeViolations24h > 0) {
            $patterns = max(1, (int) ($scopeStats24h["patterns"] ?? 0));
            $objective = (int) ($scopeStats24h["objective"] ?? 0);
            $review = (int) ($scopeStats24h["review"] ?? 0);
            $latest = $scopeGroups24h[0] ?? [];
            $latestDefinition = $latest
                ? admin_scope_guard_definition(
                    (string) ($latest["violation_key"] ?? ""),
                )
                : [];
            $detailsHtml = $latest
                ? admin_scope_evidence_html($latest, true)
                : "";
            $detailsHtml .=
                '<a class="ghost small" href="' .
                href("admin_security") .
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
        if ($trialEnding > 0) {
            $actions[] = [
                "icon" => "hourglass_top",
                "time" => "Assinaturas",
                "title" =>
                    $trialEnding . " assinatura(s) iniciais vencendo em até 7 dias",
                "body" => "São consultórios próximos da decisão de contratação.",
                "meta" => "Sinal de conversão ou risco de perda.",
            ];
        }
        if ($onboardingPending > 0) {
            $actions[] = [
                "icon" => "playlist_add_check",
                "time" => "Onboarding",
                "title" =>
                    $onboardingPending .
                    " consultório(s) ainda sem onboarding concluído",
                "body" => "A configuração inicial incompleta reduz adoção.",
                "meta" =>
                    "Revise dados do consultório, cargos, procedimentos e agenda.",
            ];
        }
        $charts = admin_performance_card_html();
        $telemetry =
            '<div class="stats-grid admin-overview-kpis global-telemetry-grid">' .
            admin_telemetry_kpi_cards_html(true) .
            "</div>";
        $actionsCard = $actions
            ? card(
                "<h2>Ações recomendadas</h2>" . timeline($actions),
                "priority-actions",
            )
            : "";
        $body =
            page_head("Desenvolvedor Prontoo", "") .
            card(
                "<h2>Telemetria do sistema</h2>" . $telemetry,
                "admin-telemetry-card",
            ) .
            $charts .
            $actionsCard;
        page("Desenvolvedor Prontoo", $body);
    
    }

    public static function page_admin_people(): void
    
    {
    
        require_can("admin_users");
        $rows = q(
            "SELECT u.id,u.name,u.email,u.active,u.is_global_admin,p.cpf,p.birth_date,COUNT(ur.id) AS vinculos FROM pi_users u JOIN pi_persons p ON p.id=u.person_id LEFT JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.active=1 GROUP BY u.id,u.name,u.email,u.active,u.is_global_admin,p.cpf,p.birth_date ORDER BY u.name ASC LIMIT 200",
        )->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                "icon" => (int) $r["active"] ? "person" : "person_off",
                "time" => (int) $r["is_global_admin"] ? "Desenvolvedor" : "Usuário",
                "title" => (string) $r["name"],
                "body" =>
                    "CPF " .
                    mask((string) ($r["cpf"] ?? "")) .
                    " · " .
                    ((int) $r["vinculos"]) .
                    " vínculo(s) com consultórios",
                "meta" => mb_trim((string) ($r["email"] ?? "")) ?: "sem e-mail",
            ];
        }
        page(
            "Usuários",
            page_head(
                "Usuários",
                "Credenciais, pessoas cadastradas e vínculos ativos na plataforma.",
            ) .
                card(
                    timeline($items, "Nenhuma pessoa cadastrada."),
                    "admin-people-card",
                ),
        );
    
    }

    public static function page_admin_users(): void
    
    {
    
        redirect("admin_people");
    
    }

    public static function page_admin_stats(): void
    
    {
    
        redirect("admin_painel");
    
    }
}
