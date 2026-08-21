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

final class AdminPagesRuntimeOperations09
{
    private function __construct()
    {
    }

    public static function admin_alerts_admin_users(): array
    
    {
    
        try {
            $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.09.admin_alerts_admin_users.01', [], [])->fetchAll();
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
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_alerts");
        \Prontoo\Runtime\Operational\OperationalComposition::administration()->ensureSchema("admin_alerts");
        $uid = (int) ($c["user"]["id"] ?? 0);
        $admins = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations09::admin_alerts_admin_users();
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
                    \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.09.page_admin_alerts.01', [$uid, $id, $uid], []);
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_alerts", ["view" => $view, "alert" => $id]);
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
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Escolha um perfil de Desenvolvedor válido para receber o aviso.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_alerts", ["view" => $view]);
                }
                $recipientId = $recipient;
            }
            if ($title === "" || $body === "") {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe título e mensagem do aviso.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_alerts", ["view" => $view]);
            }
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.09.page_admin_alerts.02', [$uid, $recipientId, mb_substr($title, 0, 180), $body, $severity], []);
            $id = \Prontoo\Runtime\Operational\OperationalComposition::administration()->lastInsertId();
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("aviso_admin_criado", "aviso_admin", $id, [
                "destinatario" => $recipientId ?: "todos_desenvolvedores",
                "severity" => $severity,
                "audit_body" =>
                    "Desenvolvedor enviou aviso restrito a perfis de administração técnica.",
            ]);
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Aviso enviado.");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_alerts", ["view" => "sent", "alert" => $id]);
        }
        $openId = (int) ($_GET["alert"] ?? 0);
        $recipientOpts = '<option value="0">Todos os Desenvolvedores</option>';
        foreach ($admins as $id => $u) {
            $recipientOpts .=
                '<option value="' .
                (int) $id .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($u["name"] ?? "Desenvolvedor")) .
                ($id === $uid ? " — você" : "") .
                "</option>";
        }
        $compose =
            '<details class="form-panel admin-alert-compose admin-alert-compose-panel"' .
            ($composeOpen ? " open" : "") .
            '><summary class="admin-alert-compose-summary"><span class="admin-alert-compose-summary-main"><span class="admin-alert-compose-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
            '</span><span><b>Novo aviso</b><small>Comunicação restrita ao ambiente técnico</small></span></span><span class="admin-alert-compose-toggle" aria-hidden="true">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("expand_more") .
            '</span></summary><form method="post" class="compact admin-alert-form notice-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="view" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($view) .
            '"><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Destinatário",
                '<select name="recipient_user_id">' . $recipientOpts . "</select>",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Título",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Assunto do aviso"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Mensagem",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                    "body",
                    "",
                    'required rows="5" placeholder="Mensagem restrita ao Desenvolvedor Prontoo."',
                ),
            ) .
            '<p class="field-help admin-alert-scope-help">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("shield_lock") .
            '<span>Este conteúdo fica restrito aos perfis do Desenvolvedor Prontoo e não é exibido nos ambientes dos consultórios.</span></p>' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Enviar aviso") .
            "</form></details>";
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.09.page_admin_alerts.03', [$uid, $uid], [])->fetchAll();
        $clinicNames = [];
        $clinicIds = \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "sender_clinic_id");
        if ($clinicIds) {
            $clinicNames = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map("clinics_name", $clinicIds);
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
            '<nav class="ds-notice-filters admin-alert-filters" aria-label="Caixas de avisos"><span class="admin-alert-filter-label">Caixa</span><a class="ds-filter-chip ' .
            ($receivedActive ? "is-active" : "") .
            '" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_alerts", ["view" => "received"])) .
            '"' .
            ($receivedActive ? ' aria-current="page"' : "") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("inbox") .
            '<span>Recebidos</span><em>' .
            (int) $receivedCount .
            '</em></a><a class="ds-filter-chip ' .
            ($sentActive ? "is-active" : "") .
            '" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_alerts", ["view" => "sent"])) .
            '"' .
            ($sentActive ? ' aria-current="page"' : "") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("send") .
            '<span>Enviados</span><em>' .
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
                $openBody = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.admin_pages.09.page_admin_alerts.04', [$openId, $uid, $uid], []);
                $open["body"] = (string) ($openBody["body"] ?? "");
                $mine = (int) $open["sender_user_id"] === $uid;
                if (
                    !$mine &&
                    (int) ($open["recipient_user_id"] ?? 0) === $uid &&
                    empty($open["read_at"])
                ) {
                    \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.09.page_admin_alerts.05', [$uid, $openId, $uid], []);
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
                        : \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_alert_contact_label($sender, $uid, !$mine);
                $to =
                    $recipientId > 0
                        ? \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_alert_contact_label($recipient, $uid, false)
                        : "Desenvolvedores";
                $state = $mine ? "Enviado para " . $to : "Recebido de " . $from;
                $openSeverity = (string) ($open["severity"] ?? "info");
                $openSeverityLabel = match ($openSeverity) {
                    "critical" => "Crítico",
                    "warning" => "Atenção",
                    default => "Informativo",
                };
                $detail = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<article class="notice-reader admin-alert-reader severity-' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($openSeverity) .
                        '"><header class="admin-alert-reader-toolbar"><a class="ghost small" href="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_alerts", [
                                "view" => $mine ? "sent" : "received",
                            ]),
                        ) .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                        '<span>Voltar</span></a><div class="admin-alert-reader-meta"><span class="notice-reader-state">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($mine ? "send" : "support_agent") .
                        " " .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                        "</span><time>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($open["created_at"])) .
                        '</time></div></header><div class="admin-alert-reader-content"><span class="eyebrow">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("mark_email_read") .
                        '<span>Aviso técnico</span></span><h2>' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($open["title"]) .
                        '</h2><div class="notice-reader-body">' .
                        nl2br(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($open["body"])) .
                        '</div></div><footer><span class="notice-direction-pill ' .
                        ($mine ? "is-sent" : "is-received") .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($mine ? "north_east" : "south_west") .
                        " " .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($mine ? $to : $from) .
                        '</span><span class="pill">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($openSeverityLabel) .
                        "</span></footer></article>",
                    "notice-card-shell admin-alert-reader-shell",
                );
            } else {
                $detail = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<div class="notice-empty">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
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
                    ? \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_alert_contact_label($recipient, $uid, false)
                    : "Desenvolvedores")
                : ($senderClinic !== ""
                    ? "Consultório: " . $senderClinic
                    : \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_alert_contact_label($sender, $uid, true));
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sev) .
                " " .
                ($unread
                    ? "patient-status-warn needs-ack"
                    : "patient-status-ok is-read") .
                ($rowCurrent ? " is-current" : "") .
                '" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_alerts", [
                        "view" => $view,
                        "alert" => (int) $r["id"],
                    ]),
                ) .
                '" aria-label="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    ($mine ? "Enviado para " : "Recebido de ") .
                        $name .
                        ": " .
                        (string) $r["title"],
                ) .
                '"' .
                ($rowCurrent ? ' aria-current="true"' : "") .
                '><span class="patient-card-avatar admin-alert-avatar" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
                '</span><span class="patient-card-main"><span class="patient-card-title"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["title"]) .
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sevLabel) .
                '</span></span></span><span class="patient-card-meta"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($mine ? "outbox" : "inbox") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
                "</span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($r["created_at"])) .
                '</span></span></span><span class="patient-card-actions admin-alert-actions"><span class="admin-alert-open-icon" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("chevron_right") .
                "</span></span></a>";
        }
        if ($cards === "") {
            $cards =
                '<div class="notice-empty">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
                "<strong>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    $view === "sent"
                        ? "Nenhum aviso enviado."
                        : "Nenhum aviso recebido.",
                ) .
                "</strong></div>";
        }
        $intro =
            '<section class="admin-alert-hero" aria-label="Central de avisos técnicos"><span class="admin-alert-hero-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
            '</span><div class="admin-alert-hero-copy"><span class="eyebrow">Central técnica</span><h2>Mensagens internas</h2><p>Organize avisos recebidos e enviados sem expor o conteúdo aos ambientes dos consultórios.</p></div><span class="admin-alert-private-chip">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("shield_lock") .
            "<span>Acesso restrito</span></span></section>";
        $stats =
            '<div class="notice-kpis ds-notice-kpis admin-alert-kpis"><div class="notice-kpi ds-kpi ' .
            ($unreadCount ? "notice-kpi-pending" : "") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("inbox") .
            "<div><b>" .
            (int) $receivedCount .
            '</b><span>Recebidos</span><small>' .
            (int) $unreadCount .
            ' não lido(s)</small></div></div><div class="notice-kpi ds-kpi notice-kpi-sent">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("send") .
            "<div><b>" .
            (int) $sentCount .
            '</b><span>Enviados</span><small>por você</small></div></div><div class="notice-kpi ds-kpi notice-kpi-critical">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("admin_panel_settings") .
            "<div><b>" .
            count($admins) .
            "</b><span>Desenvolvedores</span><small>perfis ativos</small></div></div></div>";
        $list = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="notice-section-head ds-section-head admin-alert-list-head"><div><span class="eyebrow">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($view === "sent" ? "Histórico" : "Entrada") .
                '</span><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($view === "sent" ? "Avisos enviados" : "Avisos recebidos") .
                '</h2></div><span class="admin-alert-result-count">' .
                (int) ($view === "sent" ? $sentCount : $receivedCount) .
                ' registro(s)</span></div><div class="ds-patient-list admin-alert-patient-list">' .
                $cards .
                "</div>",
            "patient-list-card patient-directory-card ds-filter-list-block admin-alert-shell",
        );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Mensagens internas",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Mensagens internas") .
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
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_performance");
        $summary = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_developer_metrics_summary();
        $telemetryCharts = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_performance_card_html();
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Métricas",
                "Páginas, variação líquida de registros e carregamentos da Landing Page comparados ao período anterior.",
            ) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_metrics_html(
                $summary,
                $telemetryCharts,
            );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Métricas · Desenvolvedor", $body);
    }

    public static function page_admin_routes(): void
    {
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_routes");
        $summary = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_route_performance_summary(240);
        $body = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
            "Rotas",
            "Volume de carregamentos e tempo médio por função da aplicação.",
        ) . \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_routes_html($summary);
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Rotas · Desenvolvedor", $body);
    }

}
