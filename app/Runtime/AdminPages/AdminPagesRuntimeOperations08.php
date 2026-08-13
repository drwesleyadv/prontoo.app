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
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_clinics");
        $detailId = max(0, (int) ($_GET["clinic_id"] ?? 0));
        $returnClinicId = max(0, (int) ($_POST["return_clinic_id"] ?? 0));
        $redirectAfterClinicAction = static function (int $clinicId = 0): void {
    
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect(
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
                                    \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents() / 100,
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
                            \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days()),
                    ),
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set("default_monthly_price_cents", (string) $price);
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set("default_trial_days", (string) $trialDays);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("assinatura_atualizada", "assinatura", null, [
                    "price_cents" => $price,
                    "trial_days" => $trialDays,
                    "audit_body" =>
                        "Regra comercial padrão da plataforma atualizada.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Regra comercial padrão atualizada.");
                $redirectAfterClinicAction();
            }
            $id = (int) ($_POST["id"] ?? 0);
            $cl =
                $id > 0
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.admin_pages.08.page_admin_clinics.01', [$id], [])
                    : null;
            if (!$cl) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Consultório não encontrado.", "bad");
                $redirectAfterClinicAction();
            }
            if ($act === "activate_subscription") {
                $price = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents();
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.02', [$price, $id], []);
                if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                    \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("assinatura_ativada", "assinatura", $id, [
                    "audit_body" =>
                        "Consultório definido como Ativo pelo Desenvolvedor.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Consultório definido como Ativo.");
                $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
            }
            if ($act === "deactivate_subscription") {
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.03', [$id], []);
                if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                    \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("assinatura_desativada", "assinatura", $id, [
                    "audit_body" =>
                        "Consultório definido como Somente leitura pelo Desenvolvedor.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Consultório definido como Somente leitura.");
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
                $price = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents();
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.04', [$status, $paid, $price, $id], []);
                if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                    \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("assinatura_atualizada", "assinatura", $id, [
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
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Status do consultório atualizado.");
                $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
            }
            $new = (int) $cl["active"] ? 0 : 1;
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.05', [
                $new,
                $id,
            ], []);
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("clinica_status", "clinica", $id, [
                "status" => $new ? "ativa" : "inativa",
            ]);
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Status do consultório atualizado.");
            $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
        }
        if ($detailId > 0) {
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations07::admin_clinic_detail_page($detailId);
            return;
        }
        $total = (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.08.page_admin_clinics.06', [], []);
        $active = (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.08.page_admin_clinics.07', [], []);
        $exempt = (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.08.page_admin_clinics.08', [], []);
        $readonly = (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.08.page_admin_clinics.09', [], []);
        $activeOperational = max(0, $active - $readonly - $exempt);
        $defaultPrice = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents();
        $defaultTrialDays = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days();
        $defaultBilling =
            '<details class="stat-card default-price-card"><summary>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
            "<div><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($defaultPrice) .
            "</b><span>Mensalidade única</span><small>" .
            (int) $defaultTrialDays .
            ' dias gratuitos</small></div></summary><form method="post" class="compact">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="default_billing">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Novo valor padrão",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "default_monthly_price",
                    "number",
                    number_format($defaultPrice / 100, 2, ".", ""),
                    'step="0.01" min="0"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Dias gratuitos",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "default_trial_days",
                    "number",
                    (string) $defaultTrialDays,
                    'min="0" max="3650" step="1"',
                ),
            ) .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Salvar", "primary small") .
            "</form></details>";
        $commercial =
            '<div class="stats-grid admin-clinic-attention-grid">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Ativo",
                $activeOperational,
                "verified",
                "operação liberada",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Somente leitura",
                $readonly,
                "lock",
                "alterações bloqueadas",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Isento", $exempt, "workspace_premium", "fora da cobrança") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Consultórios",
                $total,
                "home_health",
                $active . " ativo(s)",
            ) .
            $defaultBilling .
            "</div>";
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.10', [], [])->fetchAll();
        $ids = \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "id");
        $peopleCounts = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations07::admin_clinic_people_counts_by_cpf($ids);
        $bodyRows = "";
        foreach ($rows as $r) {
            $id = (int) $r["id"];
            $professionals =
                (int) ($peopleCounts[$id]["professionals"] ?? 0);
            $collaborators =
                (int) ($peopleCounts[$id]["collaborators"] ?? 0);
            $billing = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::billing_state($r);
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
                $attentionNote = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_is_global_admin_owned($id)
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($attentionIcon) .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($attentionLabel) .
                "</b></span>";
            $actions =
                '<a class="pagehead-control pagehead-control--secondary clinic-actions-summary" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_clinics", ["clinic_id" => $id])) .
                '" aria-label="Abrir gestão do consultório">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_forward") .
                '<span>Gerenciar</span></a>';
            $createdLabel = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["created_at"] ?? "");
            $dueLabel = !empty($billing["exempt"])
                ? "Isento"
                : (mb_trim((string) ($billing["paid_until"] ?? "")) !== ""
                    ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($billing["paid_until"])
                    : (!empty($billing["trial_active"])
                        ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($billing["trial_ends_at"] ?? "")
                        : "Sem vencimento"));
            $professionLabel =
                mb_trim((string) ($r["responsible_profession"] ?? "")) ?:
                "Área não informada";
            $adminStatsNote =
                !empty($billing["exempt"]) && \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_is_global_admin_owned($id)
                    ? '<span class="ds-clinic-test-pill">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("bar_chart_off") .
                        "<span>Fora das estatísticas</span></span>"
                    : "";
            $clinicMetrics =
                '<span class="ds-clinic-row-meta-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($createdLabel) .
                '</b><small>Data Cadastro</small></span><span class="ds-clinic-row-meta-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("stethoscope") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $professionals) .
                '</b><small>Profissionais</small></span><span class="ds-clinic-row-meta-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("groups") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $collaborators) .
                '</b><small>Colaboradores</small></span><span class="ds-clinic-row-meta-chip ds-clinic-due-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($dueLabel) .
                "</b><small>Vencimento</small></span>";
            $bodyRows .=
                '<article class="clinic-attention-item ds-clinic-list-item ds-clinic-list-item-inline ' .
                $attentionClass .
                '"><div class="ds-clinic-list-identity clinic-attention-main"><span class="clinic-attention-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($attentionIcon) .
                '</span><span class="clinic-attention-copy"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["display_name"]) .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    ((int) $r["active"] ? "Operando" : "Inativo") .
                        " · " .
                        $professionLabel,
                ) .
                '</small></span></div><div class="clinic-attention-state ds-clinic-list-status">' .
                $attentionChip .
                ($attentionNote !== ""
                    ? "<small>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($attentionNote) . "</small>"
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
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Consultórios", "") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($commercial, "admin-clinics-focus-card") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                $onboardingHead . $table,
                "admin-onboarding-card admin-clinics-list-card clinic-attention-card",
            );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Consultórios", $body);
    
    }

    public static function page_admin_security(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_security");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            if ($act !== "release_login_lock") {
                throw new ProntooHttpError(400, "Ação de segurança inválida.");
            }
            $id = (int) ($_POST["id"] ?? 0);
            if ($id <= 0) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Bloqueio não informado.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_security");
            }
            $removed = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_security.01', [$id], [])->rowCount();
            if ($removed < 1) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Bloqueio não encontrado ou já liberado.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_security");
            }
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("bloqueio_login_removido", "seguranca", $id);
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Bloqueio removido.");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_security");
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
                    (is_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root() . "/storage/install.lock")
                        ? "instalação bloqueada"
                        : "instalação aberta"),
                "meta" => "Banco limpo com tabelas pi_",
            ],
            [
                "icon" => "lock",
                "time" => "Agora",
                "title" =>
                    (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.08.page_admin_security.02', [], []) . " bloqueio(s) de entrada ativo(s)",
                "body" => "Pausas progressivas por CPF e IP continuam no servidor.",
                "meta" => "Proteção de força bruta",
            ],
        ];
        $locks = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_security.03', [], [])->fetchAll();
        foreach ($locks as $l) {
            $btn =
                '<form method="post" class="inline">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="release_login_lock">' .
                '<input type="hidden" name="id" value="' .
                (int) $l["id"] .
                '"><button type="submit" class="danger small">Liberar</button></form>';
            $items[] = [
                "icon" => "lock_clock",
                "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($l["locked_until"]),
                "title" => "Entrada pausada",
                "body" => "Falhas consecutivas: " . $l["fail_count"],
                "meta" => "Identificadores armazenados por hash",
                "html" => $btn,
            ];
        }
        $scopeStats = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_guard_stats(24);
        $scopeGroups = (int) $scopeStats["actionable"] > 0
            ? \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_guard_groups(24, 40)
            : [];
        $scopeItems = [];
        foreach ($scopeGroups as $scopeGroup) {
            $scopeItems[] = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_guard_timeline_item($scopeGroup);
        }
        $scopeLogic = class_exists("\\Prontoo\\Core\\Database\\SqlScopeGuard")
            ? \Prontoo\Core\Database\SqlScopeGuard::logicSelfTest()
            : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["class_missing"]];
        $scopeContext = is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::class, 'scope_guard_context_selftest'])
            ? \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::scope_guard_context_selftest()
            : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["function_missing"]];
        $scopeSummary =
            '<section id="scope-isolation" class="scope-isolation-panel"><div class="section-head"><div><span class="eyebrow">Isolamento entre consultórios</span><h2>Bloqueios com evidência explicável</h2><p>O guardião nega a operação antes do SQL. Os números abaixo medem bloqueios preventivos, não a probabilidade nem a confirmação de vazamento.</p></div><a class="ghost small" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_integrity") .
            '">Ver vínculos persistidos</a></div><div class="stats-grid scope-guard-kpis">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Causa demonstrável",
                (int) $scopeStats["objective"],
                "shield_lock",
                "ocorrências em 24h",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Prova insuficiente",
                (int) $scopeStats["review"],
                "rule",
                "revisão de código",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Padrões distintos",
                (int) $scopeStats["patterns"],
                "fingerprint",
                "chave + rota + consultório",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Política de assinatura",
                (int) $scopeStats["policy"],
                "lock_clock",
                "fora do risco de isolamento",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Prova lógica interna",
                !empty($scopeLogic["ok"]) ? "Aprovada" : "Falhou",
                !empty($scopeLogic["ok"]) ? "verified" : "gpp_bad",
                (int) ($scopeLogic["passed"] ?? 0) .
                    "/" .
                    (int) ($scopeLogic["total"] ?? 0) .
                    " casos lógicos críticos",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Contexto determinístico",
                !empty($scopeContext["ok"]) ? "Aprovado" : "Falhou",
                !empty($scopeContext["ok"]) ? "account_tree" : "gpp_bad",
                (int) ($scopeContext["passed"] ?? 0) .
                    "/" .
                    (int) ($scopeContext["total"] ?? 0) .
                    " contratos sistema/consultório",
            ) .
            '</div><div class="kpi-info-strip scope-isolation-note">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("info") .
            '<span>Repetições idênticas dentro da mesma requisição são deduplicadas e eventos recentes são agrupados por fingerprint. Recorrência ajuda a priorizar a correção, mas não é usada como probabilidade de acesso cruzado.</span></div>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline(
                $scopeItems,
                "Nenhuma operação de escopo exigiu revisão nas últimas 24 horas.",
            ) .
            "</section>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Segurança",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Segurança",
                "Robustez, bloqueios de entrada e isolamento explicável entre consultórios.",
            ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($items), "admin-security-access-card") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($scopeSummary, "admin-security-scope-card"),
        );
    
    }

    public static function page_admin_audit(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_health");
        $rows = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light(["scope" => "all"], [], 120);
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Atividades",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Atividades",
                "Histórico direto recente de toda a plataforma.",
            ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<div class="activity-timeline">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline(
                            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations02::audit_items($rows, true),
                            "Nenhuma atividade encontrada.",
                        ) .
                        "</div>",
                ),
        );
    
    }
}
