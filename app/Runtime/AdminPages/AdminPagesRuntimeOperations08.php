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

final class AdminPagesRuntimeOperations08
{
    private function __construct()
    {
    }

    public static function page_admin_clinics(): void
    
    {
    
        require_can("admin_clinics");
        $detailId = max(0, (int) ($_GET["clinic_id"] ?? 0));
        $returnClinicId = max(0, (int) ($_POST["return_clinic_id"] ?? 0));
        $redirectAfterClinicAction = static function (int $clinicId = 0): void {
    
            redirect(
                "admin_clinics",
                $clinicId > 0 ? ["clinic_id" => $clinicId] : [],
            );
        };
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "toggle");
            if ($act === "default_billing") {
                $price = max(
                    0,
                    (int) round(
                        ((float) str_replace(
                            ",",
                            ".",
                            (string) ($_POST["default_monthly_price"] ??
                                number_format(
                                    default_monthly_price_cents() / 100,
                                    2,
                                    ".",
                                    "",
                                )),
                        )) * 100,
                    0, \RoundingMode::HalfAwayFromZero),
                );
                if ($price <= 0) {
                    $price = PRONTOO_MONTHLY_PRICE_CENTS;
                }
                $trialDays = max(
                    0,
                    min(
                        3650,
                        (int) ($_POST["default_trial_days"] ??
                            default_trial_days()),
                    ),
                );
                meta_set("default_monthly_price_cents", (string) $price);
                meta_set("default_trial_days", (string) $trialDays);
                audit("assinatura_atualizada", "assinatura", null, [
                    "price_cents" => $price,
                    "trial_days" => $trialDays,
                    "audit_body" =>
                        "Regra comercial padrão da plataforma atualizada.",
                ]);
                flash("Regra comercial padrão atualizada.");
                $redirectAfterClinicAction();
            }
            $id = (int) ($_POST["id"] ?? 0);
            $cl =
                $id > 0
                    ? one(
                        "SELECT id,active,monthly_price_cents FROM pi_clinics WHERE id=?",
                        [$id],
                    )
                    : null;
            if (!$cl) {
                flash("Consultório não encontrado.", "bad");
                $redirectAfterClinicAction();
            }
            if ($act === "activate_subscription") {
                $price = default_monthly_price_cents();
                q(
                    "UPDATE pi_clinics SET active=1, subscription_status='active', paid_until=DATE_ADD(CURDATE(), INTERVAL 30 DAY), monthly_price_cents=?, updated_at=NOW() WHERE id=?",
                    [$price, $id],
                );
                if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                    \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
                }
                audit("assinatura_ativada", "assinatura", $id, [
                    "audit_body" =>
                        "Consultório definido como Ativo pelo Desenvolvedor.",
                ]);
                flash("Consultório definido como Ativo.");
                $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
            }
            if ($act === "deactivate_subscription") {
                q(
                    "UPDATE pi_clinics SET subscription_status='read_only', paid_until=CURDATE(), updated_at=NOW() WHERE id=?",
                    [$id],
                );
                if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                    \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
                }
                audit("assinatura_desativada", "assinatura", $id, [
                    "audit_body" =>
                        "Consultório definido como Somente leitura pelo Desenvolvedor.",
                ]);
                flash("Consultório definido como Somente leitura.");
                $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
            }
            if ($act === "billing") {
                $status = (string) ($_POST["subscription_status"] ?? "active");
                if (!in_array($status, ["active", "read_only", "exempt"], true)) {
                    $status = "active";
                }
                $paid = mb_trim((string) ($_POST["paid_until"] ?? "")) ?: null;
                if ($status === "exempt") {
                    $paid = null;
                }
                $price = default_monthly_price_cents();
                q(
                    "UPDATE pi_clinics SET subscription_status=?, paid_until=?, monthly_price_cents=?, updated_at=NOW() WHERE id=?",
                    [$status, $paid, $price, $id],
                );
                if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                    \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
                }
                audit("assinatura_atualizada", "assinatura", $id, [
                    "status" => $status,
                    "paid_until" => $paid,
                    "price_cents" => $price,
                    "audit_body" =>
                        "Status do consultório atualizado para " .
                        ([
                            "active" => "Ativo",
                            "read_only" => "Somente leitura",
                            "exempt" => "Isento",
                        ][$status] ??
                            $status) .
                        ".",
                ]);
                flash("Status do consultório atualizado.");
                $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
            }
            $new = (int) $cl["active"] ? 0 : 1;
            q("UPDATE pi_clinics SET active=?,updated_at=NOW() WHERE id=?", [
                $new,
                $id,
            ]);
            audit("clinica_status", "clinica", $id, [
                "status" => $new ? "ativa" : "inativa",
            ]);
            flash("Status do consultório atualizado.");
            $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
        }
        if ($detailId > 0) {
            admin_clinic_detail_page($detailId);
            return;
        }
        $exclude = admin_model_clinic_exclude_sql("id");
        $total = (int) val("SELECT COUNT(*) FROM pi_clinics WHERE 1=1 $exclude");
        $active = (int) val(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 $exclude",
        );
        $exempt = (int) val(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='exempt' $exclude",
        );
        $readonly = (int) val(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status<>'exempt' AND (subscription_status='read_only' OR (subscription_status<>'active' AND (paid_until IS NULL OR paid_until<CURDATE()) AND (trial_ends_at IS NULL OR trial_ends_at<NOW()))) $exclude",
        );
        $activeOperational = max(0, $active - $readonly - $exempt);
        $defaultPrice = default_monthly_price_cents();
        $defaultTrialDays = default_trial_days();
        $defaultBilling =
            '<details class="stat-card default-price-card"><summary>' .
            icon("payments") .
            "<div><b>" .
            money_br($defaultPrice) .
            "</b><span>Mensalidade única</span><small>" .
            (int) $defaultTrialDays .
            ' dias gratuitos</small></div></summary><form method="post" class="compact">' .
            csrf_field() .
            '<input type="hidden" name="act" value="default_billing">' .
            form_row(
                "Novo valor padrão",
                input(
                    "default_monthly_price",
                    "number",
                    number_format($defaultPrice / 100, 2, ".", ""),
                    'step="0.01" min="0"',
                ),
            ) .
            form_row(
                "Dias gratuitos",
                input(
                    "default_trial_days",
                    "number",
                    (string) $defaultTrialDays,
                    'min="0" max="3650" step="1"',
                ),
            ) .
            form_actions("Salvar", "primary small") .
            "</form></details>";
        $commercial =
            '<div class="stats-grid admin-clinic-attention-grid">' .
            stat_card(
                "Ativo",
                $activeOperational,
                "verified",
                "operação liberada",
            ) .
            stat_card(
                "Somente leitura",
                $readonly,
                "lock",
                "alterações bloqueadas",
            ) .
            stat_card("Isento", $exempt, "workspace_premium", "fora da cobrança") .
            stat_card(
                "Consultórios",
                $total,
                "home_health",
                $active . " ativo(s)",
            ) .
            $defaultBilling .
            "</div>";
        $rows = q(
            "SELECT id,display_name,responsible_profession,active,onboarding_done,owner_user_id,manager_user_id,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents FROM pi_clinics ORDER BY CASE WHEN active=0 THEN 4 WHEN subscription_status='exempt' THEN 3 WHEN subscription_status='read_only' OR (subscription_status<>'active' AND (paid_until IS NULL OR paid_until<CURDATE()) AND (trial_ends_at IS NULL OR trial_ends_at<NOW())) THEN 0 ELSE 2 END ASC, updated_at DESC, id DESC LIMIT 120",
        )->fetchAll();
        $ids = int_ids($rows, "id");
        $peopleCounts = admin_clinic_people_counts_by_cpf($ids);
        $bodyRows = "";
        foreach ($rows as $r) {
            $id = (int) $r["id"];
            $professionals =
                (int) ($peopleCounts[$id]["professionals"] ?? 0);
            $collaborators =
                (int) ($peopleCounts[$id]["collaborators"] ?? 0);
            $billing = billing_state($r);
            $status = (string) ($billing["status"] ?? "active");
            $attentionClass = "is-stable";
            $attentionIcon = "verified";
            $attentionLabel = "Ativo";
            $attentionNote = "";
            if ((int) $r["active"] !== 1) {
                $attentionClass = "is-muted";
                $attentionIcon = "pause_circle";
                $attentionLabel = "Inativo";
                $attentionNote = "Consultório desativado";
            } elseif (!empty($billing["exempt"])) {
                $attentionClass = "is-exempt";
                $attentionIcon = "workspace_premium";
                $attentionLabel = "Isento";
                $attentionNote = clinic_is_global_admin_owned($id)
                    ? "Isento do Desenvolvedor fora das estatísticas"
                    : "Isento de cobrança";
            } elseif (!empty($billing["read_only"])) {
                $attentionClass = "is-critical";
                $attentionIcon = "lock";
                $attentionLabel = "Somente leitura";
                $attentionNote = "Alterações temporariamente bloqueadas";
            }
            $attentionChip =
                '<span class="clinic-attention-chip ' .
                $attentionClass .
                '">' .
                icon($attentionIcon) .
                "<b>" .
                e($attentionLabel) .
                "</b></span>";
            $actions =
                '<a class="ghost small cmdlike clinic-actions-summary" href="' .
                e(href("admin_clinics", ["clinic_id" => $id])) .
                '" aria-label="Abrir gestão do consultório">' .
                icon("arrow_forward") .
                '<span>Gerenciar</span></a>';
            $createdLabel = date_br($r["created_at"] ?? "");
            $dueLabel = !empty($billing["exempt"])
                ? "Isento"
                : (mb_trim((string) ($billing["paid_until"] ?? "")) !== ""
                    ? date_br($billing["paid_until"])
                    : (!empty($billing["trial_active"])
                        ? date_br($billing["trial_ends_at"] ?? "")
                        : "Sem vencimento"));
            $professionLabel =
                mb_trim((string) ($r["responsible_profession"] ?? "")) ?:
                "Área não informada";
            $adminStatsNote =
                !empty($billing["exempt"]) && clinic_is_global_admin_owned($id)
                    ? '<span class="ds-clinic-test-pill">' .
                        icon("bar_chart_off") .
                        "<span>Fora das estatísticas</span></span>"
                    : "";
            $clinicMetrics =
                '<span class="ds-clinic-row-meta-chip">' .
                icon("event") .
                "<b>" .
                e($createdLabel) .
                '</b><small>Data Cadastro</small></span><span class="ds-clinic-row-meta-chip">' .
                icon("stethoscope") .
                "<b>" .
                e((string) $professionals) .
                '</b><small>Profissionais</small></span><span class="ds-clinic-row-meta-chip">' .
                icon("groups") .
                "<b>" .
                e((string) $collaborators) .
                '</b><small>Colaboradores</small></span><span class="ds-clinic-row-meta-chip ds-clinic-due-chip">' .
                icon("event_available") .
                "<b>" .
                e($dueLabel) .
                "</b><small>Vencimento</small></span>";
            $bodyRows .=
                '<article class="clinic-attention-item ds-clinic-list-item ds-clinic-list-item-inline ' .
                $attentionClass .
                '"><div class="ds-clinic-list-identity clinic-attention-main"><span class="clinic-attention-icon">' .
                icon($attentionIcon) .
                '</span><span class="clinic-attention-copy"><strong>' .
                e($r["display_name"]) .
                "</strong><small>" .
                e(
                    ((int) $r["active"] ? "Operando" : "Inativo") .
                        " · " .
                        $professionLabel,
                ) .
                '</small></span></div><div class="clinic-attention-state ds-clinic-list-status">' .
                $attentionChip .
                ($attentionNote !== ""
                    ? "<small>" . e($attentionNote) . "</small>"
                    : "") .
                $adminStatsNote .
                '</div><div class="ds-clinic-row-meta">' .
                $clinicMetrics .
                '</div><div class="clinic-attention-actions">' .
                $actions .
                "</div></article>";
        }
        $table =
            '<div class="clinic-attention-list">' .
            ($bodyRows !== ""
                ? $bodyRows
                : '<div class="empty">Nenhum consultório exige atenção agora.</div>') .
            "</div>";
        $onboardingHead =
            '<div class="admin-onboarding-head clinic-attention-head"><div><span class="eyebrow">Acompanhamento</span><h2>Consultórios</h2><p>Leitura compacta por status, data de cadastro, profissionais, colaboradores e vencimento. Status padronizados: Ativo, Somente leitura e Isento.</p></div></div>';
        $body =
            page_head("Consultórios", "") .
            card($commercial, "admin-clinics-focus-card") .
            card(
                $onboardingHead . $table,
                "admin-onboarding-card admin-clinics-list-card clinic-attention-card",
            );
        page("Consultórios", $body);
    
    }

    public static function page_admin_security(): void
    
    {
    
        require_can("admin_security");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            if ($act !== "release_login_lock") {
                throw new ProntooHttpError(400, "Ação de segurança inválida.");
            }
            $id = (int) ($_POST["id"] ?? 0);
            if ($id <= 0) {
                flash("Bloqueio não informado.", "bad");
                redirect("admin_security");
            }
            $removed = q("DELETE FROM pi_login_locks WHERE id=?", [$id])->rowCount();
            if ($removed < 1) {
                flash("Bloqueio não encontrado ou já liberado.", "bad");
                redirect("admin_security");
            }
            audit("bloqueio_login_removido", "seguranca", $id);
            flash("Bloqueio removido.");
            redirect("admin_security");
        }
        $items = [
            [
                "icon" => "verified_user",
                "time" => "Sistema",
                "title" => "Versão " . PRONTOO_VERSION,
                "body" =>
                    "PHP " .
                    PHP_VERSION .
                    " · " .
                    (is_file(app_root() . "/storage/install.lock")
                        ? "instalação bloqueada"
                        : "instalação aberta"),
                "meta" => "Banco limpo com tabelas pi_",
            ],
            [
                "icon" => "lock",
                "time" => "Agora",
                "title" =>
                    (int) val(
                        "SELECT COUNT(*) FROM pi_login_locks WHERE locked_until>NOW()",
                    ) . " bloqueio(s) de entrada ativo(s)",
                "body" => "Pausas progressivas por CPF e IP continuam no servidor.",
                "meta" => "Proteção de força bruta",
            ],
        ];
        $locks = q(
            "SELECT id,fail_count,locked_until FROM pi_login_locks WHERE locked_until>NOW() ORDER BY id DESC LIMIT 30",
        )->fetchAll();
        foreach ($locks as $l) {
            $btn =
                '<form method="post" class="inline">' .
                csrf_field() .
                '<input type="hidden" name="act" value="release_login_lock">' .
                '<input type="hidden" name="id" value="' .
                (int) $l["id"] .
                '"><button type="submit" class="danger small">Liberar</button></form>';
            $items[] = [
                "icon" => "lock_clock",
                "time" => dt_br($l["locked_until"]),
                "title" => "Entrada pausada",
                "body" => "Falhas consecutivas: " . $l["fail_count"],
                "meta" => "Identificadores armazenados por hash",
                "html" => $btn,
            ];
        }
        $scopeStats = admin_scope_guard_stats(24);
        $scopeGroups = (int) $scopeStats["actionable"] > 0
            ? admin_scope_guard_groups(24, 40)
            : [];
        $scopeItems = [];
        foreach ($scopeGroups as $scopeGroup) {
            $scopeItems[] = admin_scope_guard_timeline_item($scopeGroup);
        }
        $scopeLogic = class_exists("\\Prontoo\\Core\\Database\\SqlScopeGuard")
            ? \Prontoo\Core\Database\SqlScopeGuard::logicSelfTest()
            : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["class_missing"]];
        $scopeContext = function_exists("scope_guard_context_selftest")
            ? scope_guard_context_selftest()
            : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["function_missing"]];
        $scopeSummary =
            '<section id="scope-isolation" class="scope-isolation-panel"><div class="section-head"><div><span class="eyebrow">Isolamento entre consultórios</span><h2>Bloqueios com evidência explicável</h2><p>O guardião nega a operação antes do SQL. Os números abaixo medem bloqueios preventivos, não a probabilidade nem a confirmação de vazamento.</p></div><a class="ghost small" href="' .
            href("admin_integrity") .
            '">Ver vínculos persistidos</a></div><div class="stats-grid scope-guard-kpis">' .
            stat_card(
                "Causa demonstrável",
                (int) $scopeStats["objective"],
                "shield_lock",
                "ocorrências em 24h",
            ) .
            stat_card(
                "Prova insuficiente",
                (int) $scopeStats["review"],
                "rule",
                "revisão de código",
            ) .
            stat_card(
                "Padrões distintos",
                (int) $scopeStats["patterns"],
                "fingerprint",
                "chave + rota + consultório",
            ) .
            stat_card(
                "Política de assinatura",
                (int) $scopeStats["policy"],
                "lock_clock",
                "fora do risco de isolamento",
            ) .
            stat_card(
                "Prova lógica interna",
                !empty($scopeLogic["ok"]) ? "Aprovada" : "Falhou",
                !empty($scopeLogic["ok"]) ? "verified" : "gpp_bad",
                (int) ($scopeLogic["passed"] ?? 0) .
                    "/" .
                    (int) ($scopeLogic["total"] ?? 0) .
                    " casos lógicos críticos",
            ) .
            stat_card(
                "Contexto determinístico",
                !empty($scopeContext["ok"]) ? "Aprovado" : "Falhou",
                !empty($scopeContext["ok"]) ? "account_tree" : "gpp_bad",
                (int) ($scopeContext["passed"] ?? 0) .
                    "/" .
                    (int) ($scopeContext["total"] ?? 0) .
                    " contratos sistema/consultório",
            ) .
            '</div><div class="kpi-info-strip scope-isolation-note">' .
            icon("info") .
            '<span>Repetições idênticas dentro da mesma requisição são deduplicadas e eventos recentes são agrupados por fingerprint. Recorrência ajuda a priorizar a correção, mas não é usada como probabilidade de acesso cruzado.</span></div>' .
            timeline(
                $scopeItems,
                "Nenhuma operação de escopo exigiu revisão nas últimas 24 horas.",
            ) .
            "</section>";
        page(
            "Segurança",
            page_head(
                "Segurança",
                "Robustez, bloqueios de entrada e isolamento explicável entre consultórios.",
            ) .
                card(timeline($items), "admin-security-access-card") .
                card($scopeSummary, "admin-security-scope-card"),
        );
    
    }

    public static function page_admin_audit(): void
    
    {
    
        require_can("admin_health");
        $rows = audit_rows_light("1=1", [], 120);
        page(
            "Atividades",
            page_head(
                "Atividades",
                "Histórico direto recente de toda a plataforma.",
            ) .
                card(
                    '<div class="activity-timeline">' .
                        timeline(
                            audit_items($rows, true),
                            "Nenhuma atividade encontrada.",
                        ) .
                        "</div>",
                ),
        );
    
    }
}
