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
        static $series = null;
        $metric = in_array(
            $metric,
            ["duration", "landing_duration", "requests"],
            true,
        ) ? $metric : "duration";
        if (!is_array($series)) {
            $tz = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
            $nowUnixUs = (int) floor(microtime(true) * 1000000);
            $startUnixUs = $nowUnixUs - 24 * 3600 * 1000000;
            $startTs = intdiv($startUnixUs, 1000000);
            $series = ["duration" => [], "landing_duration" => [], "requests" => []];
            for ($i = 0; $i < 1440; $i++) {
                $ts = $startTs + $i * 60;
                $dt = new DateTimeImmutable("@" . $ts)->setTimezone($tz);
                $baseRow = [
                    "ts" => $ts,
                    "label" => $dt->format("H:i"),
                    "tooltip" => $dt->format("d/m H:i"),
                    "value" => 0.0,
                    "sum" => 0.0,
                    "count" => 0,
                ];
                $series["duration"][$i] = $baseRow;
                $series["landing_duration"][$i] = $baseRow;
                $series["requests"][$i] = $baseRow;
            }
            foreach (\Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_read_events($nowUnixUs) as $event) {
                $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
                if ($finishedUs < $startUnixUs || $finishedUs >= $nowUnixUs) {
                    continue;
                }
                $idx = intdiv($finishedUs - $startUnixUs, 60 * 1000000);
                if ($idx < 0 || $idx >= 1440) {
                    continue;
                }
                $series["requests"][$idx]["value"]++;
                $series["duration"][$idx]["sum"] +=
                    max(0, (int) ($event["duracao_ns"] ?? 0));
                $series["duration"][$idx]["count"]++;
                if ((string) ($event["rota"] ?? "") === "landing") {
                    $series["landing_duration"][$idx]["sum"] +=
                        max(0, (int) ($event["duracao_ns"] ?? 0));
                    $series["landing_duration"][$idx]["count"]++;
                }
            }
            foreach (["duration", "landing_duration"] as $averageSeries) {
                foreach ($series[$averageSeries] as &$row) {
                    $row["value"] = $row["count"] > 0
                        ? round(($row["sum"] / $row["count"]) / 1000000, 6, \RoundingMode::HalfAwayFromZero)
                        : 0.0;
                    $row["samples"] = (int) $row["count"];
                    $row["sum_ns"] = (int) $row["sum"];
                    unset($row["sum"], $row["count"]);
                }
                unset($row);
            }
            foreach ($series["requests"] as &$row) {
                unset($row["sum"], $row["count"]);
            }
            unset($row);
            foreach ($series as $seriesKey => $seriesRows) {
                $series[$seriesKey] = array_values($seriesRows);
            }
        }
        return $series[$metric];
    
    }

    public static function admin_global_sequence_series_20d(): array
    {
        return \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations03::telemetry_database_record_series_30d();
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
        $duration = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_metric_series_24h("duration");
        $landingDuration = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_metric_series_24h("landing_duration");
        $requests = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_route_requests_series_20d();
        $records = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_sequence_series_20d();
        $recordObservedDays = count(
            array_filter(
                $records,
                static fn(array $row): bool => !empty($row["observed"]),
            ),
        );
        $recordCoverageNote = $recordObservedDays >= 30
            ? "dados de Visualizações e Registros dos últimos 30 dias completos, em dias civis de America/Cuiaba."
            : "Visualizações cobrem os últimos 30 dias completos. Registros têm " .
                $recordObservedDays .
                " dia(s) completo(s) observado(s) desde o início da nova coleta; dias sem amostra não são tratados como zero.";
        return '<div class="global-performance-charts global-area-charts" data-admin-global-charts data-refresh-ms="900000" data-chart-window="5min">' .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_metric_dual_area_chart(
                "Velocidade",
                $duration,
                $landingDuration,
                "speed",
                [
                    "primary_label" => "Rotas",
                    "secondary_label" => "Landing Page",
                    "value_type" => "ms",
                    "recent_title" => "Tempo médio das rotas nos últimos 5 minutos",
                    "middle_title" => "Tempo médio das rotas nos últimos 30 minutos",
                    "overall_title" => "Tempo médio das rotas nas últimas 24 horas",
                    "summary_lead" => "dados de duração de rotas e da Landing Page.",
                ],
            ) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_metric_dual_area_chart(
                "Leitura e gravação",
                $requests,
                $records,
                "speed",
                [
                    "primary_label" => "Visualizações",
                    "secondary_label" => "Registros",
                    "value_type" => "count",
                    "recent_points" => 15,
                    "comparison_points" => 15,
                    "middle_points" => 15,
                    "recent_title" => "Média diária de visualizações · 15 dias recentes",
                    "middle_title" => "Média diária de visualizações · 15 dias anteriores",
                    "overall_title" => "Média diária de visualizações · últimos 30 dias",
                    "summary_lead" => $recordCoverageNote,
                ],
            ) .
            "</div>";
    
    }

    public static function admin_performance_card_content_html(bool $public = false): string
    
    {
        return '<div class="section-head admin-performance-head"><h2>Telemetria das últimas 24 horas</h2>' .
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
