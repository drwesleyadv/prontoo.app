<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\TasksNotices;

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
    
        require_can("admin_global_notices");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = $_POST["act"] ?? "create";
            $id = (int) ($_POST["id"] ?? 0);
            if ($act === "toggle" && $id) {
                q(
                    "UPDATE pi_global_notices SET active=1-active, updated_at=NOW() WHERE id=?",
                    [$id],
                );
                audit("aviso_global_status", "aviso_global", $id);
                flash("Status da aviso atualizado.");
                redirect("admin_global_notices");
            }
            $title = mb_trim((string) ($_POST["title"] ?? ""));
            $body = mb_trim((string) ($_POST["body"] ?? ""));
            if ($title === "" || $body === "") {
                flash("Informe título e mensagem.", "bad");
                redirect("admin_global_notices");
            }
            $severity = (string) ($_POST["severity"] ?? "info");
            if (!in_array($severity, ["info", "warning", "critical"], true)) {
                $severity = "info";
            }
            $startsAt = mb_trim((string) ($_POST["starts_at"] ?? ""));
            $expiresAt = mb_trim((string) ($_POST["expires_at"] ?? ""));
            $startsAt = $startsAt !== "" ? app_local_to_db_utc($startsAt) : null;
            $expiresAt = $expiresAt !== "" ? app_local_to_db_utc($expiresAt) : null;
            if ($startsAt !== null && $expiresAt !== null && $expiresAt <= $startsAt) {
                flash("O término do aviso deve ser posterior ao início.", "bad");
                redirect("admin_global_notices");
            }
            q(
                "INSERT INTO pi_global_notices (title,body,severity,starts_at,expires_at,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())",
                [
                    $title,
                    $body,
                    $severity,
                    $startsAt,
                    $expiresAt,
                    $_SESSION["uid"] ?? null,
                ],
            );
            counter_inc("global_notices_total");
            audit(
                "aviso_global_criado",
                "aviso_global",
                db_last_insert_id(),
                $_POST,
            );
            flash("Aviso global publicada.");
            redirect("admin_global_notices");
        }
        $form =
            '<details class="form-panel"><summary class="primary small cmdlike">' .
            action_summary_label("Nova aviso global", "notifications") .
            '</summary><form method="post" class="compact">' .
            csrf_field() .
            form_row("Título", input("title", "text", "", "required")) .
            form_row("Mensagem", textarea("body", "", "required")) .
            select_label(
                "Severidade",
                "severity",
                [
                    "info" => "Informativo",
                    "warning" => "Atenção",
                    "critical" => "Crítico",
                ],
                "info",
            ) .
            form_row("Início", input("starts_at", "datetime-local")) .
            form_row("Expira em", input("expires_at", "datetime-local")) .
            form_actions("Publicar") .
            "</form></details>";
        $rows = q(
            "SELECT id,title,body,severity,active,starts_at,expires_at,created_by,created_at FROM pi_global_notices ORDER BY created_at DESC LIMIT 100",
        )->fetchAll();
        $authors = fetch_map("pi_users", int_ids($rows, "created_by"), "id,name");
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
            icon("notifications_active") .
            "<div><b>" .
            (int) $active .
            '</b><span>Ativas</span></div></div><div class="notice-kpi notice-kpi-critical">' .
            icon("priority_high") .
            "<div><b>" .
            (int) $critical .
            '</b><span>Críticas</span></div></div><div class="notice-kpi">' .
            icon("visibility_off") .
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
                csrf_field() .
                '<input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="' .
                (int) $r["id"] .
                '"><button type="submit" class="small ' .
                ((int) $r["active"] ? "danger" : "primary") .
                '">' .
                ((int) $r["active"] ? "Desativar" : "Ativar") .
                "</button></form>";
            $period = [];
            if (!empty($r["starts_at"])) {
                $period[] = "Início: " . dt_br($r["starts_at"]);
            }
            if (!empty($r["expires_at"])) {
                $period[] = "Expira: " . dt_br($r["expires_at"]);
            }
            $cards .=
                '<article class="notice-card global severity-' .
                e($sev) .
                " " .
                ((int) $r["active"] ? "is-active" : "is-inactive") .
                '">' .
                '<div class="notice-icon">' .
                icon($sevInfo[1]) .
                "</div>" .
                '<div class="notice-main"><header><span class="notice-eyebrow">' .
                e($sevInfo[0]) .
                " · " .
                e($activeLabel) .
                "</span><time>" .
                e(dt_notice_br($r["created_at"])) .
                "</time></header>" .
                "<h3>" .
                e($r["title"]) .
                "</h3><p>" .
                nl2br(e($r["body"])) .
                "</p>" .
                "<footer><span>Autor: " .
                e(first_name($a["name"] ?? "")) .
                "</span>" .
                ($period ? "<span>" . e(implode(" · ", $period)) . "</span>" : "") .
                "</footer></div>" .
                '<div class="notice-actions">' .
                $btn .
                "</div></article>";
        }
        $list =
            $cards !== ""
                ? '<div class="notice-list">' . $cards . "</div>"
                : '<div class="notice-empty">' .
                    icon("campaign") .
                    "<strong>Nenhuma aviso global.</strong></div>";
        page(
            "Avisos Globais",
            page_head("Avisos Globais", "", $form) .
                '<section class="notice-screen notice-admin">' .
                $stats .
                card($list, "notice-card-shell") .
                "</section>",
        );
    
    }
}
