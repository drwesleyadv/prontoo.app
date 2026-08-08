<?php
declare(strict_types=1);

namespace Prontoo\Runtime\UiComponents;

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

final class UiComponentsRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function reception_cash_state_icon(?array $context = null): string
    
    {
    
        try {
            $c = $context;
            if (!is_array($c) || !$c) {
                if (function_exists("ctx")) {
                    $c = ctx();
                }
            }
            if (
                !is_array($c) ||
                ($c["scope"] ?? "") !== "clinic" ||
                !has_effective_role($c, "recepcionista")
            ) {
                return "point_of_sale";
            }
            $cid = (int) ($c["clinic_id"] ?? 0);
            $uid = (int) ($c["user"]["id"] ?? 0);
            if ($cid <= 0 || $uid <= 0) {
                return "lock_clock";
            }
            if (function_exists("financial_current_open_session")) {
                return financial_current_open_session($cid, $uid)
                    ? "currency_exchange"
                    : "lock_clock";
            }
            if (function_exists("one") && function_exists("financial_today")) {
                $open = one(
                    "SELECT id FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date=? AND status='open' LIMIT 1",
                    [$cid, $uid, financial_today($cid)],
                );
                return $open ? "currency_exchange" : "lock_clock";
            }
        } catch (Throwable $e) {
            return "lock_clock";
        }
        return "lock_clock";
    
    }

    public static function date_br(null|string|int $value): string
    
    {
    
        $value = mb_trim((string) ($value ?? ""));
        if ($value === "") {
            return "—";
        }
        if (preg_match('/^-?\d+$/', $value)) {
            return app_date_br($value);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $ts = strtotime($value . " 00:00:00 UTC");
            return $ts ? gmdate("d/m/Y", $ts) : $value;
        }
        return app_date_br($value);
    
    }

    public static function date_extenso_br(null|string|int $value): string
    
    {
    
        $value = mb_trim((string) ($value ?? ""));
        if ($value === "") {
            return "—";
        }
        $dt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? DateTimeImmutable::createFromFormat(
                "!Y-m-d",
                $value,
                new DateTimeZone("UTC"),
            )
            : app_db_utc_to_local($value);
        if (!$dt) {
            return $value;
        }
        $m = prontoo_months_br();
        return $dt->format("d") .
            " de " .
            $m[(int) $dt->format("n")] .
            " de " .
            $dt->format("Y");
    
    }

    public static function dt_br(null|string|int $value): string
    
    {
    
        return app_datetime_br($value);
    
    }

    public static function dt_notice_br(null|string|int $value): string
    
    {
    
        return dt_br($value);
    
    }

    public static function dt_card_full_br(null|string|int $value): string
    
    {
    
        return dt_br($value);
    
    }

    public static function notification_button_light(array $c): string
    
    {
    
        if (($c["scope"] ?? "") !== "clinic") {
            return "";
        }
        $n = 0;
        try {
            $cid = (int) ($c["clinic_id"] ?? 0);
            $uid = (int) ($c["user"]["id"] ?? 0);
            if (
                $cid > 0 &&
                $uid > 0 &&
                db_table_exists("pi_notices") &&
                db_table_exists("pi_notice_reads")
            ) {
                [$targetSql, $targetParams] = function_exists("notice_target_sql")
                    ? notice_target_sql($c, "n")
                    : [
                        "(n.target_scope='all' OR (n.target_scope='role' AND n.target_role=?) OR (n.target_scope='user' AND n.target_user_id=?))",
                        [(string) ($c["role"] ?? ""), $uid],
                    ];
                $params = array_merge([$cid], $targetParams, [$uid]);
                $n =
                    (int) (val(
                        "SELECT COUNT(*) FROM pi_notices n WHERE n.clinic_id=? AND $targetSql AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND r.user_id=? AND (r.read_at IS NOT NULL OR r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1)",
                        $params,
                    ) ?:
                    0);
            }
        } catch (Throwable $e) {
            $n = 0;
        }
        return '<a class="notify-link top-icon" href="' .
            href("notices") .
            '" aria-label="Avisos" title="Avisos">' .
            icon("campaign") .
            ($n > 0 ? '<b class="badge">' . min(99, $n) . "</b>" : "") .
            "</a>";
    
    }

    public static function shared_goal_cmdbar_html(?array $c = null): string
    
    {
    
        try {
            if (!is_array($c) || !$c) {
                if (function_exists("ctx")) {
                    $c = ctx();
                }
            }
            if (!is_array($c) || ($c["scope"] ?? "") !== "clinic") {
                return "";
            }
            $cid = (int) ($c["clinic_id"] ?? 0);
            if (
                $cid <= 0 ||
                !function_exists("db_table_exists") ||
                !function_exists("one") ||
                !function_exists("val")
            ) {
                return "";
            }
            if (
                !db_table_exists("pi_financial_goals") ||
                !db_table_exists("pi_financial_revenues")
            ) {
                return "";
            }
            $month = app_month_in_timezone($cid, $c);
            $goal = one(
                "SELECT target_cents,base_metric,share_with_team FROM pi_financial_goals WHERE clinic_id=? AND month_key=? AND share_with_team=1 LIMIT 1",
                [$cid, $month],
            );
            if (!$goal) {
                return "";
            }
            $target = (int) ($goal["target_cents"] ?? 0);
            if ($target <= 0) {
                return "";
            }
            $base = (string) ($goal["base_metric"] ?? "efetivada");
            if (!in_array($base, ["prevista", "efetivada"], true)) {
                $base = "efetivada";
            }
            [$start, $next] = app_local_month_utc_range($month, $cid, $c);
            if ($base === "prevista") {
                $done =
                    (int) (val(
                        "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.expected_at>=? AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))",
                        [$cid, $start, $next],
                    ) ?? 0);
            } else {
                $done =
                    (int) (val(
                        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?",
                        [$cid, $start, $next],
                    ) ?? 0);
            }
            $pct = $target > 0 ? round(($done / $target) * 100, 0, \RoundingMode::HalfAwayFromZero) : 0;
            $pct = max(0, min(100, (int) $pct));
            $label = "Meta compartilhada: " . $pct . "%";
            return '<div class="cmdbar-shared-goal" role="group" aria-label="' .
                e($label) .
                '" title="Meta compartilhada: ' .
                e((string) $pct) .
                '%">' .
                icon("flag") .
                '<div class="cmdbar-shared-goal-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' .
                e((string) $pct) .
                '" aria-label="' .
                e($label) .
                '"><i style="width:' .
                e((string) $pct) .
                '%"></i></div><strong>' .
                e((string) $pct) .
                "%</strong></div>";
        } catch (Throwable $e) {
            error_log("[Prontoo shared goal cmdbar] " . $e->getMessage());
            return "";
        }
    
    }

    public static function cmdbar_access_touch(array $c, string $actionKey): void
    
    {
    
        if ($actionKey === "") {
            return;
        }
        [$uid, $scope, $clinic, $role] = cmdbar_context_key($c);
        if ($uid <= 0 || !cmdbar_access_schema_ready()) {
            return;
        }
        try {
            q(
                "INSERT INTO pi_user_cmdbar_access (user_id,scope,clinic_scope_id,role_code,action_key,last_accessed_at,access_count,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),1,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_accessed_at=VALUES(last_accessed_at), access_count=access_count+1, updated_at=NOW()",
                [$uid, $scope, $clinic, $role, $actionKey],
            );
        } catch (Throwable $e) {
            error_log("[Prontoo cmdbar access touch] " . $e->getMessage());
        }
    
    }

    public static function cmdbar_access_recency(array $c, array $keys): array
    
    {
    
        $keys = array_values(
            array_unique(array_filter(array_map("strval", $keys))),
        );
        if (!$keys) {
            return [];
        }
        [$uid, $scope, $clinic, $role] = cmdbar_context_key($c);
        if ($uid <= 0 || !cmdbar_access_schema_ready()) {
            return [];
        }
        try {
            $ph = implode(",", array_fill(0, count($keys), "?"));
            $params = array_merge([$uid, $scope, $clinic, $role], $keys);
            $rows = q(
                "SELECT action_key,last_accessed_at FROM pi_user_cmdbar_access WHERE user_id=? AND scope=? AND clinic_scope_id=? AND role_code=? AND action_key IN ($ph)",
                $params,
            )->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r["action_key"]] =
                    strtotime((string) $r["last_accessed_at"]) ?: 0;
            }
            return $out;
        } catch (Throwable $e) {
            error_log("[Prontoo cmdbar access recency] " . $e->getMessage());
            return [];
        }
    
    }

    public static function cmdbar_order_items(
        array $items,
        string $current,
        array $c,
        bool $global = false,
    ): array 
    {
    
        $active = cmdbar_parent_key($current, $items, $global);
        if ($active !== "") {
            cmdbar_access_touch($c, $active);
        }
        return $items;
    
    }

    public static function floating_pending_task_access_sql(array $c, string $alias = "t"): array
    
    {
    
        $uid = (int) ($c["user"]["id"] ?? 0);
        $role = (string) ($c["role"] ?? "");
        $a = $alias !== "" ? $alias . "." : "";
        $scope =
            "COALESCE(NULLIF(" .
            $a .
            "target_scope,''),CASE WHEN " .
            $a .
            "target_user_id IS NOT NULL THEN 'user' WHEN NULLIF(" .
            $a .
            "target_role,'') IS NOT NULL THEN 'role' ELSE 'clinic' END)";
        $clinicWide =
            function_exists("has_effective_role") &&
            has_effective_role($c, "gerente")
                ? " OR " . $scope . "='clinic'"
                : "";
        $sql =
            "(" .
            $a .
            "assigned_to=? OR (" .
            $a .
            "assigned_to IS NULL AND ((" .
            $scope .
            "='role' AND " .
            $a .
            "target_role=?) OR (" .
            $scope .
            "='user' AND " .
            $a .
            "target_user_id=?)" .
            $clinicWide .
            ")))";
        return [$sql, [$uid, $role, $uid]];
    
    }

    public static function floating_pending_count(string $sql, array $params = []): int
    
    {
    
        try {
            return (int) (val($sql, $params) ?: 0);
        } catch (Throwable $e) {
            error_log("[Prontoo floating pending count] " . $e->getMessage());
            return 0;
        }
    
    }

    public static function floating_pending_cards_html(array $c, string $current): string
    
    {
    
        if (($c["scope"] ?? "") !== "clinic") {
            return "";
        }
        if (!function_exists("has_cfg") || !has_cfg()) {
            return "";
        }
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? 0);
        if ($cid <= 0 || $uid <= 0) {
            return "";
        }
        $cards = [];
        $active = "t.status IN ('aberta','em_andamento','aguardando')";
        if (function_exists("db_table_exists") && db_table_exists("pi_tasks")) {
            [$access, $accessParams] = floating_pending_task_access_sql($c, "t");
            $base = array_merge([$cid], $accessParams);
            $overdue = floating_pending_count(
                "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND $active AND ($access) AND t.due_at IS NOT NULL AND t.due_at<NOW()",
                $base,
            );
            $today = 0;
            try {
                if (
                    function_exists("app_today_in_timezone") &&
                    function_exists("app_local_day_utc_range")
                ) {
                    [$start, $end] = app_local_day_utc_range(
                        app_today_in_timezone($cid, $c),
                        $cid,
                        $c,
                    );
                    $today = floating_pending_count(
                        "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND $active AND ($access) AND t.due_at IS NOT NULL AND t.due_at>=? AND t.due_at<?",
                        array_merge($base, [$start, $end]),
                    );
                } else {
                    $today = floating_pending_count(
                        "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND $active AND ($access) AND t.due_at IS NOT NULL AND t.due_at>=CURDATE() AND t.due_at<DATE_ADD(CURDATE(), INTERVAL 1 DAY)",
                        $base,
                    );
                }
            } catch (Throwable $e) {
                $today = 0;
            }
            $mine = floating_pending_count(
                "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND $active AND ($access)",
                $base,
            );
            $progress = floating_pending_count(
                "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND t.status='em_andamento' AND (t.assigned_to=? OR t.started_by=?)",
                [$cid, $uid, $uid],
            );
            if ($overdue > 0) {
                $cards[] = [
                    "tasks overdue",
                    "task_alt",
                    (string) $overdue,
                    "Tarefa",
                    href("tasks", ["view" => "overdue"]),
                    $overdue === 1
                        ? "1 tarefa atrasada"
                        : $overdue . " tarefas atrasadas",
                ];
            } elseif ($today > 0) {
                $cards[] = [
                    "tasks today",
                    "task_alt",
                    (string) $today,
                    "Tarefa",
                    href("tasks", ["view" => "today"]),
                    $today === 1
                        ? "1 tarefa vence hoje"
                        : $today . " tarefas vencem hoje",
                ];
            } elseif ($progress > 0) {
                $cards[] = [
                    "tasks progress",
                    "task_alt",
                    (string) $progress,
                    "Tarefa",
                    href("tasks", ["view" => "progress"]),
                    $progress === 1
                        ? "1 tarefa em andamento"
                        : $progress . " tarefas em andamento",
                ];
            } elseif ($mine > 0) {
                $cards[] = [
                    "tasks",
                    "task_alt",
                    (string) $mine,
                    "Tarefa",
                    href("tasks"),
                    $mine === 1 ? "1 tarefa aberta" : $mine . " tarefas abertas",
                ];
            }
        }
        if (
            function_exists("db_table_exists") &&
            db_table_exists("pi_notices") &&
            db_table_exists("pi_notice_reads") &&
            function_exists("notice_target_sql")
        ) {
            try {
                [$targetSql, $targetParams] = notice_target_sql($c, "n");
                $notice = floating_pending_count(
                    "SELECT COUNT(*) FROM pi_notices n WHERE n.clinic_id=? AND n.requires_ack=1 AND $targetSql AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND r.user_id=? AND (r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1)",
                    array_merge([$cid], $targetParams, [$uid]),
                );
                if ($notice > 0) {
                    $cards[] = [
                        "notices",
                        "campaign",
                        (string) $notice,
                        "Avisos",
                        href("notices"),
                        $notice === 1
                            ? "1 aviso aguardando ciência"
                            : $notice . " avisos aguardando ciência",
                    ];
                }
            } catch (Throwable $e) {
                error_log("[Prontoo floating pending notices] " . $e->getMessage());
            }
        }
        if (!$cards) {
            return "";
        }
        $cardHtml = "";
        foreach (array_slice($cards, 0, 2) as $card) {
            [$kind, $ico, $value, $label, $url, $hint] = $card;
            $kindClass = str_replace(
                " ",
                " context-floating-pending-card-",
                preg_replace("/[^a-z0-9_\- ]/i", "", (string) $kind),
            );
            $aria = $label . ": " . $hint;
            $cardHtml .=
                '<a class="agenda-floating-card context-floating-pending-card context-floating-pending-card-' .
                e($kindClass) .
                '" href="' .
                e($url) .
                '" aria-label="' .
                e($aria) .
                '" title="' .
                e($hint) .
                '">' .
                '<span class="agenda-floating-icon" aria-hidden="true">' .
                icon($ico) .
                '</span><div class="agenda-floating-copy"><b>' .
                e($value) .
                '</b><span class="agenda-floating-label">' .
                e($label) .
                '</span><span class="sr-only">' .
                e($hint) .
                "</span></div></a>";
        }
        return '<aside class="context-floating-pending-kpis" data-ds-fixed-layer="context-pending" aria-label="Pendências do usuário">' .
            $cardHtml .
            "</aside>";
    
    }
}
