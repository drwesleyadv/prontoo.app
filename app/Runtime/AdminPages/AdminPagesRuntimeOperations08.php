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
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::admin_subscription_payment_review_handle($act, $redirectAfterClinicAction);
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
            '<section class="patient-directory-overview kpis kpi-info-strip stats-grid admin-clinic-attention-grid" aria-label="Resumo de consultórios">' .
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
            "</section>";
        $paymentReviews = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::admin_subscription_payment_reviews_html();
        $search = mb_trim((string) ($_GET["q"] ?? ""));
        $view = (string) ($_GET["view"] ?? "all");
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.08.page_admin_clinics.10', [], [])->fetchAll();
        $ids = \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "id");
        $peopleCounts = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations07::admin_clinic_people_counts_by_cpf($ids);
        $directoryRows = [];
        foreach ($rows as $r) {
            $id = (int) ($r["id"] ?? 0);
            $directoryRows[] = [
                "clinic" => $r,
                "billing" => \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::billing_state($r),
                "professionals" => (int) ($peopleCounts[$id]["professionals"] ?? 0),
                "collaborators" => (int) ($peopleCounts[$id]["collaborators"] ?? 0),
                "global_admin_owned" => $id > 0 && \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_is_global_admin_owned($id),
            ];
        }
        $directory = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::developer_clinic_directory_html(
            $directoryRows,
            $view,
            $search,
            static fn(string $route, array $params = []): string => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route, $params),
            static fn(mixed $value): string => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($value),
        );
        $searchBar = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::developer_clinic_search_html(
            $search,
            $view,
            static fn(string $route, array $params = []): string => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route, $params),
        );
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Consultórios",
                "Lista global para localizar, conferir e gerenciar consultórios com rapidez.",
            ) .
            $commercial .
            $paymentReviews .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                $searchBar,
                "patient-search-card ds-search-card admin-clinic-search-card",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                $directory,
                "patient-list-card patient-directory-card ds-filter-list-block admin-clinics-list-card clinic-attention-card",
            );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Consultórios", $body);
    
    }

    public static function page_admin_audit(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_administration");
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
