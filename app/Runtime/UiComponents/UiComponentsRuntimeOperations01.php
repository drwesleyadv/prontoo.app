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
                if (is_callable([\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::class, 'ctx'])) {
                    $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
                }
            }
            if (
                !is_array($c) ||
                ($c["scope"] ?? "") !== "clinic" ||
                !\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "recepcionista")
            ) {
                return "point_of_sale";
            }
            $cid = (int) ($c["clinic_id"] ?? 0);
            $uid = (int) ($c["user"]["id"] ?? 0);
            if ($cid <= 0 || $uid <= 0) {
                return "lock_clock";
            }
            if (is_callable([\Prontoo\Runtime\Financial\FinancialRuntimeOperations07::class, 'financial_current_open_session'])) {
                return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_current_open_session($cid, $uid)
                    ? "currency_exchange"
                    : "lock_clock";
            }
            if (is_callable([\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::class, 'one']) && is_callable([\Prontoo\Runtime\Financial\FinancialRuntimeOperations03::class, 'financial_today'])) {
                $open = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.ui_components.01.reception_cash_state_icon.01', [$cid, $uid, \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid)], []);
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
            return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_date_br($value);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $ts = strtotime($value . " 00:00:00 UTC");
            return $ts ? gmdate("d/m/Y", $ts) : $value;
        }
        return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_date_br($value);
    
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
            : \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($value);
        if (!$dt) {
            return $value;
        }
        $m = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::prontoo_months_br();
        return $dt->format("d") .
            " de " .
            $m[(int) $dt->format("n")] .
            " de " .
            $dt->format("Y");
    
    }

    public static function dt_br(null|string|int $value): string
    
    {
    
        return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_datetime_br($value);
    
    }

    public static function dt_notice_br(null|string|int $value): string
    
    {
    
        return \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($value);
    
    }

    public static function dt_card_full_br(null|string|int $value): string
    
    {
    
        return \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($value);
    
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
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->tableExists("pi_notices") &&
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->tableExists("pi_notice_reads")
            ) {
                $targetParams = \Prontoo\Domain\TasksNotices\TasksNoticesDomainOperations01::notice_target_parameters($c);
                $params = array_merge([$cid], $targetParams, [$uid]);
                $n =
                    (int) (\Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.ui_components.01.notification_button_light.01', $params, []) ?:
                    0);
            }
        } catch (Throwable $e) {
            $n = 0;
        }
        return '<a class="notify-link top-icon" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices") .
            '" aria-label="Avisos" title="Avisos">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
            ($n > 0 ? '<b class="badge">' . min(99, $n) . "</b>" : "") .
            "</a>";
    
    }

    public static function shared_goal_cmdbar_html(?array $c = null): string
    
    {
    
        try {
            if (!is_array($c) || !$c) {
                if (is_callable([\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::class, 'ctx'])) {
                    $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
                }
            }
            if (!is_array($c) || ($c["scope"] ?? "") !== "clinic") {
                return "";
            }
            $cid = (int) ($c["clinic_id"] ?? 0);
            if (
                $cid <= 0 ||
                !is_callable([\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::class, 'db_table_exists']) ||
                !is_callable([\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::class, 'one']) ||
                !is_callable([\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::class, 'val'])
            ) {
                return "";
            }
            if (
                !\Prontoo\Runtime\Operational\OperationalComposition::administration()->tableExists("pi_financial_goals") ||
                !\Prontoo\Runtime\Operational\OperationalComposition::administration()->tableExists("pi_financial_revenues")
            ) {
                return "";
            }
            $month = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_month_in_timezone($cid, $c);
            $goal = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.ui_components.01.shared_goal_cmdbar_html.01', [$cid, $month], []);
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
            [$start, $next] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_month_utc_range($month, $cid, $c);
            if ($base === "prevista") {
                $done =
                    (int) (\Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.ui_components.01.shared_goal_cmdbar_html.02', [$cid, $start, $next], []) ?? 0);
            } else {
                $done =
                    (int) (\Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.ui_components.01.shared_goal_cmdbar_html.03', [$cid, $start, $next], []) ?? 0);
            }
            $pct = $target > 0 ? round(($done / $target) * 100, 0, \RoundingMode::HalfAwayFromZero) : 0;
            $pct = max(0, min(100, (int) $pct));
            $label = "Meta compartilhada: " . $pct . "%";
            return '<div class="cmdbar-shared-goal" role="group" aria-label="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                '" title="Meta compartilhada: ' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $pct) .
                '%">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("flag") .
                '<div class="cmdbar-shared-goal-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $pct) .
                '" aria-label="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                '"><i style="width:' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $pct) .
                '%"></i></div><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $pct) .
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
        [$uid, $scope, $clinic, $role] = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::cmdbar_context_key($c);
        if ($uid <= 0 || !\Prontoo\Infrastructure\UiComponents\UiComponentsInfrastructureOperations01::cmdbar_access_schema_ready()) {
            return;
        }
        try {
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.ui_components.01.cmdbar_access_touch.01', [$uid, $scope, $clinic, $role, $actionKey], []);
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
        [$uid, $scope, $clinic, $role] = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::cmdbar_context_key($c);
        if ($uid <= 0 || !\Prontoo\Infrastructure\UiComponents\UiComponentsInfrastructureOperations01::cmdbar_access_schema_ready()) {
            return [];
        }
        try {
            $params = array_merge([$uid, $scope, $clinic, $role], $keys);
            $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.ui_components.01.cmdbar_access_recency.01', $params, ['itemCount' => count($keys)])->fetchAll();
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
    
        $active = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::cmdbar_parent_key($current, $items, $global);
        if ($active !== "") {
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::cmdbar_access_touch($c, $active);
        }
        return $items;
    
    }

    public static function floating_pending_task_access(array $c): array
    
    {
    
        $uid = (int) ($c["user"]["id"] ?? 0);
        $role = (string) ($c["role"] ?? "");
        $clinicWide =
            is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::class, 'has_effective_role']) &&
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente");
        return ["parameters" => [$uid, $role, $uid], "clinicWide" => $clinicWide];
    
    }

    public static function floating_pending_count(string $query, array $params = [], array $context = []): int
    
    {
    
        try {
            return (int) (\Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.ui_components.01.floating_pending_count.01', $params, ['query' => $query] + $context) ?: 0);
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
        if (!is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'has_cfg']) || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return "";
        }
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? 0);
        if ($cid <= 0 || $uid <= 0) {
            return "";
        }
        $cards = [];
        if (is_callable([\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::class, 'db_table_exists']) && \Prontoo\Runtime\Operational\OperationalComposition::administration()->tableExists("pi_tasks")) {
            $access = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::floating_pending_task_access($c);
            $accessParams = (array) ($access["parameters"] ?? []);
            $pendingContext = ["clinicWide" => !empty($access["clinicWide"])];
            $base = array_merge([$cid], $accessParams);
            $overdue = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::floating_pending_count('read.ui_components.01.floating_pending_cards_html.01', $base, $pendingContext);
            $today = 0;
            try {
                if (
                    is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'app_today_in_timezone']) &&
                    is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'app_local_day_utc_range'])
                ) {
                    [$start, $end] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range(
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c),
                        $cid,
                        $c,
                    );
                    $today = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::floating_pending_count('read.ui_components.01.floating_pending_cards_html.02', array_merge($base, [$start, $end]), $pendingContext);
                } else {
                    $today = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::floating_pending_count('read.ui_components.01.floating_pending_cards_html.03', $base, $pendingContext);
                }
            } catch (Throwable $e) {
                $today = 0;
            }
            $mine = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::floating_pending_count('read.ui_components.01.floating_pending_cards_html.04', $base, $pendingContext);
            $progress = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::floating_pending_count('read.ui_components.01.floating_pending_cards_html.05', [$cid, $uid, $uid], []);
            if ($overdue > 0) {
                $cards[] = [
                    "tasks overdue",
                    "task_alt",
                    (string) $overdue,
                    "Tarefa",
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", ["view" => "overdue"]),
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
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", ["view" => "today"]),
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
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", ["view" => "progress"]),
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
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks"),
                    $mine === 1 ? "1 tarefa aberta" : $mine . " tarefas abertas",
                ];
            }
        }
        if (
            is_callable([\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::class, 'db_table_exists']) &&
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->tableExists("pi_notices") &&
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->tableExists("pi_notice_reads") &&
            is_callable([\Prontoo\Domain\TasksNotices\TasksNoticesDomainOperations01::class, 'notice_target_parameters'])
        ) {
            try {
                $targetParams = \Prontoo\Domain\TasksNotices\TasksNoticesDomainOperations01::notice_target_parameters($c);
                $notice = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::floating_pending_count('read.ui_components.01.floating_pending_cards_html.06', array_merge([$cid], $targetParams, [$uid]));
                if ($notice > 0) {
                    $cards[] = [
                        "notices",
                        "campaign",
                        (string) $notice,
                        "Avisos",
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices"),
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($kindClass) .
                '" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($url) .
                '" aria-label="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($aria) .
                '" title="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($hint) .
                '">' .
                '<span class="agenda-floating-icon" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
                '</span><div class="agenda-floating-copy"><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) .
                '</b><span class="agenda-floating-label">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                '</span><span class="sr-only">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($hint) .
                "</span></div></a>";
        }
        return '<aside class="context-floating-pending-kpis" data-ds-fixed-layer="context-pending" aria-label="Pendências do usuário">' .
            $cardHtml .
            "</aside>";
    
    }
}
