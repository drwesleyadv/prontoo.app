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

final class AdminPagesRuntimeOperations09
{
    private function __construct()
    {
    }

    public static function admin_alerts_admin_users(): array
    
    {
    
        try {
            $rows = q(
                "SELECT id,name,email FROM pi_users WHERE is_global_admin=1 AND active=1 ORDER BY name ASC,id ASC LIMIT 300",
            )->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r["id"]] = $r;
            }
            return $out;
        } catch (Throwable $e) {
            error_log("[Prontoo admin alerts users] " . $e->getMessage());
            return [];
        }
    
    }

    public static function page_admin_alerts(): void
    
    {
    
        $c = require_can("admin_alerts");
        admin_alerts_ensure_schema();
        $uid = (int) ($c["user"]["id"] ?? 0);
        $admins = admin_alerts_admin_users();
        $view = preg_replace(
            "/[^a-z_]/",
            "",
            (string) ($_GET["view"] ?? ($_POST["view"] ?? "received")),
        );
        if (!in_array($view, ["received", "sent"], true)) {
            $view = "received";
        }
        $composeOpen = (string) ($_GET["compose"] ?? "") === "1";
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "create");
            if ($act === "read") {
                $id = (int) ($_POST["id"] ?? 0);
                if ($id > 0) {
                    q(
                        "UPDATE pi_admin_alerts SET read_at=COALESCE(read_at,NOW()), read_by=COALESCE(read_by,?) WHERE id=? AND recipient_user_id=?",
                        [$uid, $id, $uid],
                    );
                }
                redirect("admin_alerts", ["view" => $view, "alert" => $id]);
            }
            $title = mb_trim((string) ($_POST["title"] ?? ""));
            $body = mb_trim((string) ($_POST["body"] ?? ""));
            $severity = (string) ($_POST["severity"] ?? "info");
            if (!in_array($severity, ["info", "warning", "critical"], true)) {
                $severity = "info";
            }
            $recipient = (int) ($_POST["recipient_user_id"] ?? 0);
            $recipientId = null;
            if ($recipient > 0) {
                if (!isset($admins[$recipient])) {
                    flash(
                        "Escolha um perfil de Desenvolvedor válido para receber o aviso.",
                        "bad",
                    );
                    redirect("admin_alerts", ["view" => $view]);
                }
                $recipientId = $recipient;
            }
            if ($title === "" || $body === "") {
                flash("Informe título e mensagem do aviso.", "bad");
                redirect("admin_alerts", ["view" => $view]);
            }
            q(
                "INSERT INTO pi_admin_alerts (sender_user_id,recipient_user_id,title,body,severity,created_at) VALUES (?,?,?,?,?,NOW())",
                [$uid, $recipientId, mb_substr($title, 0, 180), $body, $severity],
            );
            $id = db_last_insert_id();
            audit("aviso_admin_criado", "aviso_admin", $id, [
                "destinatario" => $recipientId ?: "todos_desenvolvedores",
                "severity" => $severity,
                "audit_body" =>
                    "Desenvolvedor enviou aviso restrito a perfis de administração técnica.",
            ]);
            flash("Aviso enviado.");
            redirect("admin_alerts", ["view" => "sent", "alert" => $id]);
        }
        $openId = (int) ($_GET["alert"] ?? 0);
        $recipientOpts = '<option value="0">Todos os Desenvolvedores</option>';
        foreach ($admins as $id => $u) {
            $recipientOpts .=
                '<option value="' .
                (int) $id .
                '">' .
                e((string) ($u["name"] ?? "Desenvolvedor")) .
                ($id === $uid ? " — você" : "") .
                "</option>";
        }
        $compose =
            '<details class="form-panel admin-alert-compose admin-alert-compose-panel"' .
            ($composeOpen ? " open" : "") .
            '><summary class="admin-alert-compose-summary"><span class="admin-alert-compose-summary-main"><span class="admin-alert-compose-icon">' .
            icon("campaign") .
            '</span><span><b>Novo aviso</b><small>Comunicação restrita ao ambiente técnico</small></span></span><span class="admin-alert-compose-toggle" aria-hidden="true">' .
            icon("expand_more") .
            '</span></summary><form method="post" class="compact admin-alert-form notice-form">' .
            csrf_field() .
            '<input type="hidden" name="view" value="' .
            e($view) .
            '"><div class="two">' .
            form_row(
                "Destinatário",
                '<select name="recipient_user_id">' . $recipientOpts . "</select>",
            ) .
            select_label(
                "Prioridade",
                "severity",
                [
                    "info" => "Informativo",
                    "warning" => "Atenção",
                    "critical" => "Crítico",
                ],
                "info",
            ) .
            "</div>" .
            form_row(
                "Título",
                input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Assunto do aviso"',
                ),
            ) .
            form_row(
                "Mensagem",
                textarea(
                    "body",
                    "",
                    'required rows="5" placeholder="Mensagem restrita ao Desenvolvedor Prontoo."',
                ),
            ) .
            '<p class="field-help admin-alert-scope-help">' .
            icon("shield_lock") .
            '<span>Este conteúdo fica restrito aos perfis do Desenvolvedor Prontoo e não é exibido nos ambientes dos consultórios.</span></p>' .
            form_actions("Enviar aviso") .
            "</form></details>";
        $rows = q(
            "SELECT id,sender_user_id,sender_clinic_id,source_scope,recipient_user_id,title,severity,read_at,read_by,created_at FROM pi_admin_alerts WHERE sender_user_id=? OR recipient_user_id=? OR recipient_user_id IS NULL ORDER BY id DESC LIMIT 180",
            [$uid, $uid],
        )->fetchAll();
        $clinicNames = [];
        $clinicIds = int_ids($rows, "sender_clinic_id");
        if ($clinicIds) {
            $clinicNames = fetch_map("pi_clinics", $clinicIds, "id,display_name");
        }
        $sentCount = 0;
        $receivedCount = 0;
        $unreadCount = 0;
        foreach ($rows as $r) {
            $mine = (int) $r["sender_user_id"] === $uid;
            if ($mine) {
                $sentCount++;
            } else {
                $receivedCount++;
                if (
                    (int) ($r["recipient_user_id"] ?? 0) === $uid &&
                    empty($r["read_at"])
                ) {
                    $unreadCount++;
                }
            }
        }
        $receivedActive = $view === "received";
        $sentActive = $view === "sent";
        $filters =
            '<nav class="notice-filter-chips lead-filter-chips ds-notice-filters admin-alert-filters" aria-label="Caixas de avisos"><span class="admin-alert-filter-label">Caixa</span><a class="lead-chip ds-filter-chip ' .
            ($receivedActive ? "active is-active" : "") .
            '" href="' .
            e(href("admin_alerts", ["view" => "received"])) .
            '"' .
            ($receivedActive ? ' aria-current="page"' : "") .
            '">' .
            icon("inbox") .
            '<span class="lead-chip-label">Recebidos</span><em>' .
            (int) $receivedCount .
            '</em></a><a class="lead-chip ds-filter-chip ' .
            ($sentActive ? "active is-active" : "") .
            '" href="' .
            e(href("admin_alerts", ["view" => "sent"])) .
            '"' .
            ($sentActive ? ' aria-current="page"' : "") .
            '">' .
            icon("send") .
            '<span class="lead-chip-label">Enviados</span><em>' .
            (int) $sentCount .
            "</em></a></nav>";
        $detail = "";
        if ($openId > 0) {
            $open = null;
            foreach ($rows as $r) {
                if ((int) $r["id"] === $openId) {
                    $open = $r;
                    break;
                }
            }
            if ($open) {
                $openBody = one(
                    "SELECT body FROM pi_admin_alerts WHERE id=? AND (sender_user_id=? OR recipient_user_id=? OR recipient_user_id IS NULL) LIMIT 1",
                    [$openId, $uid, $uid],
                );
                $open["body"] = (string) ($openBody["body"] ?? "");
                $mine = (int) $open["sender_user_id"] === $uid;
                if (
                    !$mine &&
                    (int) ($open["recipient_user_id"] ?? 0) === $uid &&
                    empty($open["read_at"])
                ) {
                    q(
                        "UPDATE pi_admin_alerts SET read_at=NOW(), read_by=? WHERE id=? AND recipient_user_id=?",
                        [$uid, $openId, $uid],
                    );
                    $open["read_at"] = date("Y-m-d H:i:s");
                }
                $sender = $admins[(int) $open["sender_user_id"]] ?? [];
                $senderClinicId = (int) ($open["sender_clinic_id"] ?? 0);
                $senderClinic =
                    $senderClinicId > 0
                        ? (string) ($clinicNames[$senderClinicId]["display_name"] ??
                            "Consultório")
                        : "";
                $recipientId = (int) ($open["recipient_user_id"] ?? 0);
                $recipient = $recipientId > 0 ? $admins[$recipientId] ?? [] : null;
                $from =
                    $senderClinic !== ""
                        ? "Consultório: " . $senderClinic
                        : admin_alert_contact_label($sender, $uid, !$mine);
                $to =
                    $recipientId > 0
                        ? admin_alert_contact_label($recipient, $uid, false)
                        : "Desenvolvedores";
                $state = $mine ? "Enviado para " . $to : "Recebido de " . $from;
                $openSeverity = (string) ($open["severity"] ?? "info");
                $openSeverityLabel = match ($openSeverity) {
                    "critical" => "Crítico",
                    "warning" => "Atenção",
                    default => "Informativo",
                };
                $detail = card(
                    '<article class="notice-reader admin-alert-reader severity-' .
                        e($openSeverity) .
                        '"><header class="admin-alert-reader-toolbar"><a class="ghost small" href="' .
                        e(
                            href("admin_alerts", [
                                "view" => $mine ? "sent" : "received",
                            ]),
                        ) .
                        '">' .
                        icon("arrow_back") .
                        '<span>Voltar</span></a><div class="admin-alert-reader-meta"><span class="notice-reader-state">' .
                        icon($mine ? "send" : "support_agent") .
                        " " .
                        e($state) .
                        "</span><time>" .
                        e(dt_notice_br($open["created_at"])) .
                        '</time></div></header><div class="admin-alert-reader-content"><span class="eyebrow">' .
                        icon("mark_email_read") .
                        '<span>Aviso técnico</span></span><h2>' .
                        e($open["title"]) .
                        '</h2><div class="notice-reader-body">' .
                        nl2br(e($open["body"])) .
                        '</div></div><footer><span class="notice-direction-pill ' .
                        ($mine ? "is-sent" : "is-received") .
                        '">' .
                        icon($mine ? "north_east" : "south_west") .
                        " " .
                        e($mine ? $to : $from) .
                        '</span><span class="pill">' .
                        e($openSeverityLabel) .
                        "</span></footer></article>",
                    "notice-card-shell admin-alert-reader-shell",
                );
            } else {
                $detail = card(
                    '<div class="notice-empty">' .
                        icon("campaign") .
                        "<strong>Aviso não encontrado.</strong></div>",
                    "notice-card-shell",
                );
            }
        }
        $cards = "";
        foreach ($rows as $r) {
            $mine = (int) $r["sender_user_id"] === $uid;
            if ($view === "received" && $mine) {
                continue;
            }
            if ($view === "sent" && !$mine) {
                continue;
            }
            $sender = $admins[(int) $r["sender_user_id"]] ?? [];
            $senderClinicId = (int) ($r["sender_clinic_id"] ?? 0);
            $senderClinic =
                $senderClinicId > 0
                    ? (string) ($clinicNames[$senderClinicId]["display_name"] ??
                        "Consultório")
                    : "";
            $recipientId = (int) ($r["recipient_user_id"] ?? 0);
            $recipient = $recipientId > 0 ? $admins[$recipientId] ?? [] : null;
            $name = $mine
                ? ($recipientId > 0
                    ? admin_alert_contact_label($recipient, $uid, false)
                    : "Desenvolvedores")
                : ($senderClinic !== ""
                    ? "Consultório: " . $senderClinic
                    : admin_alert_contact_label($sender, $uid, true));
            $sev = (string) ($r["severity"] ?? "info");
            $sevLabel = match ($sev) {
                "critical" => "Crítico",
                "warning" => "Atenção",
                default => "Informativo",
            };
            $iconName = $mine
                ? "send"
                : ($sev === "critical"
                    ? "priority_high"
                    : ($sev === "warning"
                        ? "warning"
                        : "support_agent"));
            $unread = !$mine && $recipientId === $uid && empty($r["read_at"]);
            $rowCurrent = (int) $r["id"] === $openId;
            $cards .=
                '<a class="patient-card-row admin-alert-patient-row admin-alert-row severity-' .
                e($sev) .
                " " .
                ($unread
                    ? "patient-status-warn needs-ack"
                    : "patient-status-ok is-read") .
                ($rowCurrent ? " is-current" : "") .
                '" href="' .
                e(
                    href("admin_alerts", [
                        "view" => $view,
                        "alert" => (int) $r["id"],
                    ]),
                ) .
                '" aria-label="' .
                e(
                    ($mine ? "Enviado para " : "Recebido de ") .
                        $name .
                        ": " .
                        (string) $r["title"],
                ) .
                '"' .
                ($rowCurrent ? ' aria-current="true"' : "") .
                '><span class="patient-card-avatar admin-alert-avatar" aria-hidden="true">' .
                icon($iconName) .
                '</span><span class="patient-card-main"><span class="patient-card-title"><strong>' .
                e($r["title"]) .
                '</strong><span class="admin-alert-row-badges">' .
                ($unread
                    ? '<span class="admin-alert-unread-badge">Não lido</span>'
                    : "") .
                '<span class="pill ' .
                ($sev === "critical"
                    ? "bad"
                    : ($sev === "warning"
                        ? "warn"
                        : "ok")) .
                '">' .
                e($sevLabel) .
                '</span></span></span><span class="patient-card-meta"><span>' .
                icon($mine ? "outbox" : "inbox") .
                e($name) .
                "</span><span>" .
                icon("schedule") .
                e(dt_notice_br($r["created_at"])) .
                '</span></span></span><span class="patient-card-actions admin-alert-actions"><span class="admin-alert-open-icon" aria-hidden="true">' .
                icon("chevron_right") .
                "</span></span></a>";
        }
        if ($cards === "") {
            $cards =
                '<div class="notice-empty">' .
                icon("campaign") .
                "<strong>" .
                e(
                    $view === "sent"
                        ? "Nenhum aviso enviado."
                        : "Nenhum aviso recebido.",
                ) .
                "</strong></div>";
        }
        $intro =
            '<section class="admin-alert-hero" aria-label="Central de avisos técnicos"><span class="admin-alert-hero-icon">' .
            icon("campaign") .
            '</span><div class="admin-alert-hero-copy"><span class="eyebrow">Central técnica</span><h2>Comunicação do Desenvolvedor</h2><p>Organize avisos recebidos e enviados sem expor o conteúdo aos ambientes dos consultórios.</p></div><span class="admin-alert-private-chip">' .
            icon("shield_lock") .
            "<span>Acesso restrito</span></span></section>";
        $stats =
            '<div class="notice-kpis notice-gmail-kpis ds-notice-kpis admin-alert-kpis"><div class="notice-kpi ds-kpi ' .
            ($unreadCount ? "notice-kpi-pending" : "") .
            '">' .
            icon("inbox") .
            "<div><b>" .
            (int) $receivedCount .
            '</b><span>Recebidos</span><small>' .
            (int) $unreadCount .
            ' não lido(s)</small></div></div><div class="notice-kpi ds-kpi notice-kpi-sent">' .
            icon("send") .
            "<div><b>" .
            (int) $sentCount .
            '</b><span>Enviados</span><small>por você</small></div></div><div class="notice-kpi ds-kpi notice-kpi-critical">' .
            icon("admin_panel_settings") .
            "<div><b>" .
            count($admins) .
            "</b><span>Desenvolvedores</span><small>perfis ativos</small></div></div></div>";
        $list = card(
            '<div class="notice-section-head ds-section-head admin-alert-list-head"><div><span class="eyebrow">' .
                e($view === "sent" ? "Histórico" : "Entrada") .
                '</span><h2>' .
                e($view === "sent" ? "Avisos enviados" : "Avisos recebidos") .
                '</h2></div><span class="admin-alert-result-count">' .
                (int) ($view === "sent" ? $sentCount : $receivedCount) .
                ' registro(s)</span></div><div class="ds-patient-list admin-alert-patient-list">' .
                $cards .
                "</div>",
            "patient-list-card patient-directory-card ds-filter-list-block admin-alert-shell",
        );
        page(
            "Avisos do Desenvolvedor",
            page_head("Avisos") .
                '<section class="notice-screen admin-alert-screen patient-directory-screen">' .
                $intro .
                $stats .
                '<section class="notice-filter-list-block ds-filter-list-block" aria-label="Filtros e lista de avisos administrativos">' .
                $filters .
                $compose .
                $detail .
                $list .
                "</section></section>",
        );
    
    }

    public static function page_admin_performance(): void
    
    {
    
        require_can("admin_performance");
        $summary = telemetry_route_performance_summary(240);
        $comparison = telemetry_comparative_summary();
    
        $rows = isset($summary["routes"]) && is_array($summary["routes"])
            ? $summary["routes"]
            : [];
        $total = (int) ($summary["total"] ?? 0);
        $avg = (float) ($summary["avg_ms"] ?? 0);
        $slow = $rows[0] ?? null;
        $updated = (string) ($summary["updated_at"] ?? "");
        $current = (array) ($comparison["current"] ?? []);
        $landingAverage = isset($current["landing_average_ms"])
            ? (float) $current["landing_average_ms"]
            : null;
    
        $stats =
            '<div class="stats-grid admin-performance-stats">' .
            stat_card("Requisições 10d", $total, "route", "Eventos canônicos no período") .
            stat_card(
                "Tempo médio de resposta",
                admin_performance_format_ms($avg),
                "speed",
                "Média geral nos últimos 10 dias",
            ) .
            stat_card(
                "Rota mais lenta",
                $slow ? admin_performance_format_ms((float) ($slow["avg_ms"] ?? 0)) : "—",
                "timer",
                $slow ? (string) ($slow["route"] ?? "") : "Sem dados",
            ) .
            stat_card(
                "Landing Page",
                $landingAverage !== null
                    ? admin_performance_format_ms($landingAverage)
                    : "—",
                "web",
                "Tempo médio nos últimos 10 dias",
            ) .
            '</div>';
    
        $routesCard = card(
            '<div class="section-head admin-performance-head"><h2>' .
                icon("speed") .
                '<span>Rotas nos últimos 10 dias</span></h2><p>Detalhamento calculado diretamente dos eventos canônicos de início e fim.' .
                ($updated !== "" ? " Última atualização: " . e($updated) . "." : "") .
                '</p></div>' .
                admin_performance_rows_html($rows),
            "admin-performance-card",
        );
    
        $body =
            page_head(
                "Performance",
                "Visão direta das requisições e dos tempos de rota nos últimos 10 dias.",
            ) .
            '<section class="admin-performance-screen">' .
            $stats .
            $routesCard .
            '</section>';
        page("Performance", $body);
    
    }
}
