<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\AdminPages;

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

final class AdminPagesRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function admin_global_ops_finance_html(): string
    
    {
    
        $qInt = function (string $sql, array $p = []): int {
    
            return (int) safe_val($sql, $p, 0);
        };
        $qCents = function (string $sql, array $p = []): int {
    
            return (int) safe_val($sql, $p, 0);
        };
        $since = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $modelClinicWhere = admin_model_clinic_exclude_sql("id");
        $modelScopedWhere = admin_model_clinic_exclude_sql("clinic_id");
        $activeClinics = $qInt(
            "SELECT COUNT(DISTINCT clinic_id) FROM pi_audit WHERE clinic_id IS NOT NULL AND created_at>=$since $modelScopedWhere",
        );
        if ($activeClinics <= 0) {
            $activeClinics = $qInt(
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (created_at>=$since OR updated_at>=$since OR trial_started_at>=$since OR paid_until>=CURDATE()) $modelClinicWhere",
            );
        }
        $newClinics = $qInt(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND created_at>=$since $modelClinicWhere",
        );
        $exemptClinics = $qInt(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='exempt' $modelClinicWhere",
        );
        $readOnly = $qInt(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (subscription_status='read_only' OR (paid_until IS NOT NULL AND paid_until<CURDATE())) $modelClinicWhere",
        );
        $linkedUsers = $qInt(
            "SELECT COUNT(DISTINCT user_id) FROM pi_user_roles WHERE active=1 $modelScopedWhere",
        );
        $appointments30 = $qInt(
            "SELECT COUNT(*) FROM pi_appointments WHERE start_at>=$since AND start_at<NOW() AND status<>'cancelado' $modelScopedWhere",
        );
        $appointmentsFuture = $qInt(
            "SELECT COUNT(*) FROM pi_appointments WHERE start_at>=NOW() AND start_at<DATE_ADD(NOW(), INTERVAL 30 DAY) AND status<>'cancelado' $modelScopedWhere",
        );
        $activeLeads = $qInt(
            "SELECT COUNT(*) FROM pi_leads WHERE created_at>=$since AND " .
                lead_active_stage_sql("stage") .
                " $modelScopedWhere",
        );
        $patients30 = $qInt(
            "SELECT COUNT(*) FROM pi_patients WHERE created_at>=$since AND active=1 AND deleted_at IS NULL $modelScopedWhere",
        );
        $documents30 = $qInt(
            "SELECT COUNT(*) FROM pi_documents WHERE issued_at>=$since AND document_status<>'cancelado' $modelScopedWhere",
        );
        $tasks30 = $qInt(
            "SELECT COUNT(*) FROM pi_tasks WHERE created_at>=$since AND status NOT IN ('concluida','cancelada') $modelScopedWhere",
        );
        $overdueTasks = $qInt(
            "SELECT COUNT(*) FROM pi_tasks WHERE due_at>=$since AND due_at<NOW() AND status NOT IN ('concluida','cancelada') $modelScopedWhere",
        );
        $clinicNotices30 = $qInt(
            "SELECT COUNT(*) FROM pi_notices WHERE created_at>=$since $modelScopedWhere",
        );
        $globalNotices30 = $qInt(
            "SELECT COUNT(*) FROM pi_global_notices WHERE created_at>=$since",
        );
        $notices30 = $clinicNotices30 + $globalNotices30;
        $maestroRegencies = $qInt(
            "SELECT COUNT(*) FROM pi_maestro_rules WHERE 1=1 $modelScopedWhere",
        );
        $revenue30 = $qCents(
            "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE status='efetivada' AND received_at>=$since $modelScopedWhere",
        );
        $expected30 = $qCents(
            "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE status='prevista' AND expected_at>=$since $modelScopedWhere",
        );
        $expenses30 = $qCents(
            "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_expenses WHERE status='paga' AND paid_at>=$since $modelScopedWhere",
        );
        $result30 = $revenue30 - $expenses30;
        $note = "Ativas";
        return '<div class="global-compact-summary"><div class="global-pill-section"><div class="global-pill-title">' .
            icon("account_tree") .
            '<b>Operação · últimos 30 dias</b></div><div class="global-pill-list">' .
            admin_global_compact_pill(
                "novos consultórios",
                (string) $newClinics,
                "domain",
                "Últimos 30 dias",
                "admin_clinics",
            ) .
            admin_global_compact_pill(
                "consultórios ativos",
                (string) $activeClinics,
                "verified",
                $note,
                "admin_clinics",
            ) .
            admin_global_compact_pill(
                "isentos",
                (string) $exemptClinics,
                "workspace_premium",
                "fora da cobrança",
                "admin_clinics",
            ) .
            admin_global_compact_pill(
                "somente leitura",
                (string) $readOnly,
                "lock",
                "status atual",
                "admin_clinics",
            ) .
            admin_global_compact_pill(
                "colaboradores",
                (string) $linkedUsers,
                "group",
                "com vínculo",
                "admin_people",
            ) .
            admin_global_compact_pill(
                "interessados",
                (string) $activeLeads,
                "person",
                "ativos criados",
                "admin_integrity",
            ) .
            admin_global_compact_pill(
                "pacientes",
                (string) $patients30,
                "person",
                "Últimos 30 dias",
                "admin_integrity",
            ) .
            admin_global_compact_pill(
                "agendamentos",
                (string) $appointments30,
                "calendar_month",
                $appointmentsFuture . " próximos",
                "admin_integrity",
            ) .
            admin_global_compact_pill(
                "documentos",
                (string) $documents30,
                "description",
                "Últimos 30 dias",
                "admin_integrity",
            ) .
            admin_global_compact_pill(
                "tarefas",
                (string) $tasks30,
                "task_alt",
                $overdueTasks . " vencidas",
                "admin_integrity",
            ) .
            admin_global_compact_pill(
                "avisos",
                (string) $notices30,
                "campaign",
                "inclui globais",
                "admin_global_notices",
            ) .
            admin_global_compact_pill(
                "rotinas",
                (string) $maestroRegencies,
                "event_repeat",
                "cadastradas",
            ) .
            '</div></div><div class="global-pill-section"><div class="global-pill-title">' .
            icon("payments") .
            '<b>Financeiro · últimos 30 dias</b></div><div class="global-pill-list">' .
            admin_global_compact_pill(
                "receita efetivada",
                money_br($revenue30),
                "payments",
                "Últimos 30 dias",
            ) .
            admin_global_compact_pill(
                "receita prevista",
                money_br($expected30),
                "payments",
                "Últimos 30 dias",
            ) .
            admin_global_compact_pill(
                "despesas pagas",
                money_br($expenses30),
                "receipt",
                "Últimos 30 dias",
            ) .
            admin_global_compact_pill(
                "resultado",
                money_br($result30),
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
            $tz = telemetry_cuiaba_tz();
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
            foreach (telemetry_read_events($nowUnixUs) as $event) {
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
    
        $tz = telemetry_cuiaba_tz();
        $today = new DateTimeImmutable("today", $tz);
        $days = [];
        $select = [];
        $params = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = $today->modify("-" . $i . " days");
            $next = $day->modify("+1 day");
            $key = $day->format("Y-m-d");
            $days[$key] = [
                "key" => $key,
                "label" => telemetry_day_axis_label($day),
                "tooltip" => $day->format("d/m/Y"),
                "value" => 0,
            ];
            $select[] =
                "SUM(CASE WHEN created_at>=? AND created_at<? AND status='committed' THEN mutation_count ELSE 0 END) AS d" .
                (29 - $i);
            $params[] = $day->getTimestamp();
            $params[] = $next->getTimestamp();
        }
        try {
            if (
                function_exists("db_table_exists") &&
                !db_table_exists("pi_action_ledger")
            ) {
                return array_values($days);
            }
            $sql =
                "SELECT " .
                implode(",", $select) .
                " FROM pi_action_ledger WHERE created_at>=? AND created_at<?";
            $first = array_key_first($days);
            $last = array_key_last($days);
            $params[] = new DateTimeImmutable(
                $first . " 00:00:00",
                $tz,
            )->getTimestamp();
            $params[] = new DateTimeImmutable($last . " 00:00:00", $tz)
                ->modify("+1 day")
                ->getTimestamp();
            $row = q($sql, $params)->fetch() ?: [];
            $idx = 0;
            foreach ($days as $key => &$day) {
                $day["value"] = (int) ($row["d" . $idx] ?? 0);
                $idx++;
            }
            unset($day);
        } catch (Throwable $e) {
            error_log("[Prontoo admin seq chart] " . $e->getMessage());
        }
        return array_values($days);
    
    }

    public static function admin_maestro_health_time_label(?string $value): string
    
    {
    
        $value = mb_trim((string) ($value ?? ""));
        if ($value === "") {
            return "--h--";
        }
        $context = null;
        try {
            if (function_exists("ctx")) {
                $context = ctx();
            }
        } catch (Throwable $e) {
            $context = null;
        }
        $clinicId =
            is_array($context) && ($context["scope"] ?? "") === "clinic"
                ? (int) ($context["clinic_id"] ?? 0)
                : 0;
        if (function_exists("app_db_utc_to_local")) {
            $dt = app_db_utc_to_local(
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
            if (has_cfg()) {
                $schemaReady = true;
                if ($allowSchemaEnsure) {
                    maestro_ensure_schema();
                } elseif (function_exists("db_table_exists")) {
                    $schemaReady = db_table_exists("pi_maestro_job_runs");
                }
                if ($schemaReady) {
                    $hasSuccess = db_column_exists("pi_maestro_job_runs", "success");
                $cols = $hasSuccess
                    ? "started_at,finished_at,duration_ms,note,success,errors_count"
                    : "started_at,finished_at,duration_ms,note";
                $latest = q(
                    "SELECT $cols FROM pi_maestro_job_runs ORDER BY id DESC LIMIT 1",
                )->fetch();
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
                    $timeLabel = admin_maestro_health_time_label($base);
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
            icon($ico) .
            "<b>" .
            e($label) .
            "</b></span>";
    
    }

    public static function admin_global_perf_charts_html(): string
    
    {
        $duration = admin_global_metric_series_24h("duration");
        $landingDuration = admin_global_metric_series_24h("landing_duration");
        $requests = telemetry_route_requests_series_20d();
        $records = admin_global_sequence_series_20d();
        return '<div class="global-performance-charts global-area-charts" data-admin-global-charts data-refresh-ms="900000" data-chart-window="5min">' .
            admin_metric_dual_area_chart(
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
            admin_metric_dual_area_chart(
                "Leitura e gravação",
                $requests,
                $records,
                "speed",
                [
                    "primary_label" => "Requisições",
                    "secondary_label" => "Registros",
                    "value_type" => "count",
                    "recent_points" => 1,
                    "middle_points" => 7,
                    "recent_title" => "Requisições de hoje",
                    "middle_title" => "Média diária de requisições nos últimos 7 dias",
                    "overall_title" => "Média diária de requisições nos últimos 20 dias",
                    "summary_lead" => "dados de Requisições e Registros dos últimos 20 dias.",
                ],
            ) .
            "</div>";
    
    }

    public static function admin_performance_card_content_html(bool $public = false): string
    
    {
        return '<div class="section-head admin-performance-head"><h2>Telemetria das últimas 24 horas</h2>' .
            admin_maestro_health_pill_html(!$public) .
            "</div>" .
            admin_global_perf_charts_html();
    
    }

    public static function admin_performance_card_html(bool $public = false): string
    
    {
        return card(
            admin_performance_card_content_html($public),
            "admin-performance-card",
        );
    
    }
}
