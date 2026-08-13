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
use Prontoo\Domain\Leads\LeadsDomainOperations01;

final class AdminPagesRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function admin_global_ops_finance_html(): string
    
    {
    
        $qInt = function (string $query, array $p = [], array $context = []): int {
    
            return (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->safeScalar('operational.admin_pages.02.admin_global_ops_finance_html.01', $p, 0, ['query' => $query] + $context);
        };
        $qCents = function (string $query, array $p = [], array $context = []): int {
    
            return (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->safeScalar('operational.admin_pages.02.admin_global_ops_finance_html.02', $p, 0, ['query' => $query] + $context);
        };
        $activeClinics = $qInt('read.admin_pages.02.admin_global_ops_finance_html.01');
        if ($activeClinics <= 0) {
            $activeClinics = $qInt('read.admin_pages.02.admin_global_ops_finance_html.02');
        }
        $newClinics = $qInt('read.admin_pages.02.admin_global_ops_finance_html.03');
        $exemptClinics = $qInt('read.admin_pages.02.admin_global_ops_finance_html.04');
        $readOnly = $qInt('read.admin_pages.02.admin_global_ops_finance_html.05');
        $linkedUsers = $qInt('read.admin_pages.02.admin_global_ops_finance_html.06');
        $appointments30 = $qInt('read.admin_pages.02.admin_global_ops_finance_html.07');
        $appointmentsFuture = $qInt('read.admin_pages.02.admin_global_ops_finance_html.08');
        $activeLeads = $qInt('read.admin_pages.02.admin_global_ops_finance_html.09');
        $patients30 = $qInt('read.admin_pages.02.admin_global_ops_finance_html.10');
        $documents30 = $qInt('read.admin_pages.02.admin_global_ops_finance_html.11');
        $tasks30 = $qInt('read.admin_pages.02.admin_global_ops_finance_html.12');
        $overdueTasks = $qInt('read.admin_pages.02.admin_global_ops_finance_html.13');
        $clinicNotices30 = $qInt('read.admin_pages.02.admin_global_ops_finance_html.14');
        $globalNotices30 = $qInt('read.admin_pages.02.admin_global_ops_finance_html.15');
        $notices30 = $clinicNotices30 + $globalNotices30;
        $maestroRegencies = $qInt('read.admin_pages.02.admin_global_ops_finance_html.16');
        $revenue30 = $qCents('read.admin_pages.02.admin_global_ops_finance_html.17');
        $expected30 = $qCents('read.admin_pages.02.admin_global_ops_finance_html.18');
        $expenses30 = $qCents('read.admin_pages.02.admin_global_ops_finance_html.19');
        $result30 = $revenue30 - $expenses30;
        $note = "Ativas";
        return '<div class="global-compact-summary"><div class="global-pill-section"><div class="global-pill-title">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("account_tree") .
            '<b>Operação · últimos 30 dias</b></div><div class="global-pill-list">' .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "novos consultórios",
                (string) $newClinics,
                "domain",
                "Últimos 30 dias",
                "admin_clinics",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "consultórios ativos",
                (string) $activeClinics,
                "verified",
                $note,
                "admin_clinics",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "isentos",
                (string) $exemptClinics,
                "workspace_premium",
                "fora da cobrança",
                "admin_clinics",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "somente leitura",
                (string) $readOnly,
                "lock",
                "status atual",
                "admin_clinics",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "colaboradores",
                (string) $linkedUsers,
                "group",
                "com vínculo",
                "admin_people",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "interessados",
                (string) $activeLeads,
                "person",
                "ativos criados",
                "admin_integrity",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "pacientes",
                (string) $patients30,
                "person",
                "Últimos 30 dias",
                "admin_integrity",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "agendamentos",
                (string) $appointments30,
                "calendar_month",
                $appointmentsFuture . " próximos",
                "admin_integrity",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "documentos",
                (string) $documents30,
                "description",
                "Últimos 30 dias",
                "admin_integrity",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "tarefas",
                (string) $tasks30,
                "task_alt",
                $overdueTasks . " vencidas",
                "admin_integrity",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "avisos",
                (string) $notices30,
                "campaign",
                "inclui globais",
                "admin_global_notices",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "rotinas",
                (string) $maestroRegencies,
                "event_repeat",
                "cadastradas",
            ) .
            '</div></div><div class="global-pill-section"><div class="global-pill-title">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
            '<b>Financeiro · últimos 30 dias</b></div><div class="global-pill-list">' .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "receita efetivada",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($revenue30),
                "payments",
                "Últimos 30 dias",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "receita prevista",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected30),
                "payments",
                "Últimos 30 dias",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "despesas pagas",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expenses30),
                "receipt",
                "Últimos 30 dias",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill(
                "resultado",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($result30),
                "account_balance",
                "efetivado",
            ) .
            "</div></div></div>";
    
    }

    public static function admin_global_metric_series_24h(string $metric): array
    {
        return \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_latency_series_24h($metric);
    }

    public static function admin_global_sequence_series_20d(): array
    {
        return \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations03::telemetry_database_record_series_30d();
    }

    public static function admin_global_volume_series_30d(string $metric): array
    {
        return \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_volume_series_30d($metric);
    }

    public static function admin_maestro_health_time_label(?string $value): string
    
    {
    
        $value = mb_trim((string) ($value ?? ""));
        if ($value === "") {
            return "--h--";
        }
        $context = null;
        try {
            if (is_callable([\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::class, 'ctx'])) {
                $context = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
            }
        } catch (Throwable $e) {
            $context = null;
        }
        $clinicId =
            is_array($context) && ($context["scope"] ?? "") === "clinic"
                ? (int) ($context["clinic_id"] ?? 0)
                : 0;
        if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'app_db_utc_to_local'])) {
            $dt = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local(
                $value,
                $clinicId,
                is_array($context) ? $context : null,
            );
            if ($dt) {
                return $dt->format("H\hi");
            }
        }
        $ts = strtotime($value);
        return $ts ? gmdate("H\hi", $ts) : "--h--";
    
    }

    public static function admin_maestro_health_pill_html(bool $allowSchemaEnsure = true): string
    
    {
    
        $ok = false;
        $label = "Rotinas sem execução registrada";
        try {
            if (\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
                $schemaReady = true;
                if ($allowSchemaEnsure) {
                    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_ensure_schema();
                } elseif (is_callable([\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::class, 'db_table_exists'])) {
                    $schemaReady = \Prontoo\Runtime\Operational\OperationalComposition::administration()->tableExists("pi_maestro_job_runs");
                }
                if ($schemaReady) {
                    $hasSuccess = \Prontoo\Runtime\Operational\OperationalComposition::administration()->columnExists("pi_maestro_job_runs", "success");
                $latest = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.02.admin_maestro_health_pill_html.01', [], compact('hasSuccess'))->fetch();
                if ($latest) {
                    $finished = mb_trim((string) ($latest["finished_at"] ?? ""));
                    $started = mb_trim((string) ($latest["started_at"] ?? ""));
                    $note = mb_strtolower(
                        mb_trim((string) ($latest["note"] ?? "")),
                        "UTF-8",
                    );
                    if ($hasSuccess) {
                        $ok = (int) ($latest["success"] ?? 0) === 1;
                    } else {
                        $ok =
                            $finished !== "" &&
                            $note !== "" &&
                            !str_contains($note, "erro") &&
                            !str_contains($note, "falha") &&
                            !str_contains($note, "exception");
                    }
                    $base = $ok
                        ? ($finished !== ""
                            ? $finished
                            : $started)
                        : ($finished !== ""
                            ? $finished
                            : $started);
                    $timeLabel = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_maestro_health_time_label($base);
                    $label = $ok
                        ? "Rotinas em dia às " . $timeLabel
                        : "Rotinas com atenção desde " . $timeLabel;
                }
                }
            }
        } catch (Throwable $e) {
            error_log("[Prontoo admin maestro health] " . $e->getMessage());
        }
        $class = $ok ? "ok" : "warn";
        $ico = $ok ? "tune" : "warning";
        return '<span class="maestro-health-pill ' .
            $class .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</b></span>";
    
    }

    public static function admin_global_perf_charts_html(): string
    {
        $route24h = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_metric_series_24h("route");
        $database24h = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_metric_series_24h("database");
        $pageLoads30d = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_volume_series_30d("page_load");
        $databaseQueries30d = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_volume_series_30d("database_queries");
        return '<div class="global-performance-charts global-area-charts" data-admin-global-charts data-refresh-ms="60000" data-chart-window="5min">' .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_metric_dual_area_chart(
                "Velocidade", $route24h, $database24h, "speed",
                ["primary_label" => "Rotas", "secondary_label" => "Banco de dados", "value_type" => "ms", "visual_mode" => "telemetry", "recent_points" => 5, "middle_points" => 30, "recent_title" => "Tempo médio das rotas nos últimos 5 minutos", "middle_title" => "Tempo médio das rotas nos últimos 30 minutos", "overall_title" => "Tempo médio das rotas nas últimas 24 horas", "summary_lead" => "cada ponto representa um minuto móvel; verde escuro é o tempo médio de carregamento de rotas e verde claro é o tempo médio das consultas ao banco."],
            ) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_metric_dual_area_chart(
                "Volume", $pageLoads30d, $databaseQueries30d, "monitoring",
                ["primary_label" => "Carregamentos de página", "secondary_label" => "Consultas ao banco de dados", "value_type" => "count", "visual_mode" => "telemetry", "count_summary_mode" => "sum", "recent_points" => 1, "middle_points" => 7, "recent_title" => "Carregamentos de página nas últimas 24 horas", "middle_title" => "Carregamentos de página nos últimos 7 dias", "overall_title" => "Carregamentos de página nos últimos 30 dias", "summary_lead" => "cada ponto representa um intervalo móvel de 24 horas; verde escuro é o total de carregamentos de página e verde claro é o total de consultas ao banco."],
            ) .
            "</div>";
    }

    public static function admin_performance_card_content_html(bool $public = false): string
    
    {
        return '<div class="section-head admin-performance-head"><h2>Telemetria operacional</h2>' .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_maestro_health_pill_html(!$public) .
            "</div>" .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_perf_charts_html();
    
    }

    public static function admin_performance_card_html(bool $public = false): string
    
    {
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_performance_card_content_html($public),
            "admin-performance-card",
        );
    
    }
}
