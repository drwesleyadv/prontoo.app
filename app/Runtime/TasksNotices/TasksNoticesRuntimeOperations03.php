<?php
declare(strict_types=1);

namespace Prontoo\Runtime\TasksNotices;

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

final class TasksNoticesRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function page_admin_global_notices(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_global_notices");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = $_POST["act"] ?? "create";
            $id = (int) ($_POST["id"] ?? 0);
            if ($act === "toggle" && $id) {
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.03.page_admin_global_notices.01', [$id], []);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("aviso_global_status", "aviso_global", $id);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Status da aviso atualizado.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_global_notices");
            }
            $title = mb_trim((string) ($_POST["title"] ?? ""));
            $body = mb_trim((string) ($_POST["body"] ?? ""));
            if ($title === "" || $body === "") {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe título e mensagem.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_global_notices");
            }
            $severity = (string) ($_POST["severity"] ?? "info");
            if (!in_array($severity, ["info", "warning", "critical"], true)) {
                $severity = "info";
            }
            $startsAt = mb_trim((string) ($_POST["starts_at"] ?? ""));
            $expiresAt = mb_trim((string) ($_POST["expires_at"] ?? ""));
            $startsAt = $startsAt !== "" ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_to_db_utc($startsAt) : null;
            $expiresAt = $expiresAt !== "" ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_to_db_utc($expiresAt) : null;
            if ($startsAt !== null && $expiresAt !== null && $expiresAt <= $startsAt) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("O término do aviso deve ser posterior ao início.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_global_notices");
            }
            \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.03.page_admin_global_notices.02', [
                    $title,
                    $body,
                    $severity,
                    $startsAt,
                    $expiresAt,
                    $_SESSION["uid"] ?? null,
                ], []);
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("global_notices_total");
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                "aviso_global_criado",
                "aviso_global",
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->lastInsertId(),
                $_POST,
            );
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Aviso global publicada.");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_global_notices");
        }
        $form =
            '<details class="form-panel"><summary class="pagehead-control pagehead-control--primary">' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Nova aviso global", "notifications") .
            '</summary><form method="post" class="compact">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Título", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("title", "text", "", "required")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Mensagem", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("body", "", "required")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Severidade",
                "severity",
                [
                    "info" => "Informativo",
                    "warning" => "Atenção",
                    "critical" => "Crítico",
                ],
                "info",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Início", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("starts_at", "datetime-local")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Expira em", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("expires_at", "datetime-local")) .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Publicar") .
            "</form></details>";
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.03.page_admin_global_notices.03', [], [])->fetchAll();
        $authors = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map("users_name", \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "created_by"));
        $active = 0;
        $critical = 0;
        $inactive = 0;
        foreach ($rows as $r) {
            if ((int) $r["active"]) {
                $active++;
            } else {
                $inactive++;
            }
            if ((string) $r["severity"] === "critical" && (int) $r["active"]) {
                $critical++;
            }
        }
        $stats =
            '<div class="notice-kpis"><div class="notice-kpi">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("notifications_active") .
            "<div><b>" .
            (int) $active .
            '</b><span>Ativas</span></div></div><div class="notice-kpi notice-kpi-critical">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("priority_high") .
            "<div><b>" .
            (int) $critical .
            '</b><span>Críticas</span></div></div><div class="notice-kpi">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("visibility_off") .
            "<div><b>" .
            (int) $inactive .
            "</b><span>Inativas</span></div></div></div>";
        $cards = "";
        $severityMap = [
            "critical" => ["Crítico", "priority_high"],
            "warning" => ["Atenção", "warning"],
            "info" => ["Informativo", "info"],
        ];
        foreach ($rows as $r) {
            $a = $authors[(int) ($r["created_by"] ?? 0)] ?? [];
            $sev = (string) ($r["severity"] ?? "info");
            $sevInfo = $severityMap[$sev] ?? $severityMap["info"];
            $activeLabel = (int) $r["active"] ? "Ativa" : "Inativa";
            $btn =
                '<form method="post" class="inline">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="' .
                (int) $r["id"] .
                '"><button type="submit" class="small ' .
                ((int) $r["active"] ? "danger" : "primary") .
                '">' .
                ((int) $r["active"] ? "Desativar" : "Ativar") .
                "</button></form>";
            $period = [];
            if (!empty($r["starts_at"])) {
                $period[] = "Início: " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["starts_at"]);
            }
            if (!empty($r["expires_at"])) {
                $period[] = "Expira: " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["expires_at"]);
            }
            $cards .=
                '<article class="notice-card global severity-' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sev) .
                " " .
                ((int) $r["active"] ? "is-active" : "is-inactive") .
                '">' .
                '<div class="notice-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($sevInfo[1]) .
                "</div>" .
                '<div class="notice-main"><header><span class="notice-eyebrow">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sevInfo[0]) .
                " · " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($activeLabel) .
                "</span><time>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($r["created_at"])) .
                "</time></header>" .
                "<h3>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["title"]) .
                "</h3><p>" .
                nl2br(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["body"])) .
                "</p>" .
                "<footer><span>Autor: " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($a["name"] ?? "")) .
                "</span>" .
                ($period ? "<span>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(implode(" · ", $period)) . "</span>" : "") .
                "</footer></div>" .
                '<div class="notice-actions">' .
                $btn .
                "</div></article>";
        }
        $list =
            $cards !== ""
                ? '<div class="notice-list">' . $cards . "</div>"
                : '<div class="notice-empty">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
                    "<strong>Nenhuma aviso global.</strong></div>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Avisos Globais",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Avisos Globais", "", $form) .
                '<section class="notice-screen notice-admin">' .
                $stats .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($list, "notice-card-shell") .
                "</section>",
        );
    
    }
}
