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

final class TasksNoticesRuntimeOperations06
{
    private function __construct()
    {
    }

    public static function page_notices(): void
    
    {
    
        $c = require_can("notices");
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        $readOnly = !empty(($c["billing"] ?? [])["read_only"]);
        $view = preg_replace(
            "/[^a-z_]/",
            "",
            (string) ($_GET["view"] ??
                ($_POST["view"] ?? ($readOnly ? "sent" : "received"))),
        );
        if (!in_array($view, ["received", "sent", "archived"], true)) {
            $view = $readOnly ? "sent" : "received";
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = $_POST["act"] ?? "create";
            if ($readOnly && $act === "support_message") {
                readonly_support_alerts_ensure_schema();
                $title = mb_trim((string) ($_POST["title"] ?? ""));
                $body = mb_trim((string) ($_POST["body"] ?? ""));
                if ($title === "" || $body === "") {
                    flash("Informe assunto e mensagem para o suporte.", "bad");
                    redirect("notices");
                }
                q(
                    "INSERT INTO pi_admin_alerts (sender_user_id,sender_clinic_id,source_scope,recipient_user_id,title,body,severity,created_at) VALUES (?,?,?,?,?,?,?,NOW())",
                    [
                        $uid,
                        $cid,
                        "clinic_readonly",
                        null,
                        mb_substr($title, 0, 180),
                        $body,
                        "warning",
                    ],
                );
                $id = db_last_insert_id();
                audit("aviso_suporte_assinatura", "aviso_admin", $id, [
                    "clinic_id" => $cid,
                    "audit_body" =>
                        "Usuário em modo somente leitura enviou mensagem ao Desenvolvedor pelo contexto Avisos.",
                ]);
                flash("Mensagem enviada ao suporte.");
                redirect("notices", ["view" => "sent"]);
            }
            if ($readOnly && !in_array($act, ["ack", "hide", "unhide"], true)) {
                flash(
                    "No modo somente leitura, Avisos fica restrito ao contato com o suporte.",
                    "bad",
                );
                redirect("notices");
            }
            if ($act === "ack" || $act === "hide" || $act === "unhide") {
                [$targetSql, $targetParams] = notice_target_sql($c, "n");
                $accessSql = "(" . $targetSql . " OR n.created_by=?)";
                $notice = one(
                    "SELECT n.id,n.created_by,n.requires_ack FROM pi_notices n WHERE n.id=? AND n.clinic_id=? AND $accessSql",
                    array_merge([(int) ($_POST["id"] ?? 0), $cid], $targetParams, [
                        $uid,
                    ]),
                );
                if (!$notice) {
                    flash("Aviso não encontrado para este colaborador.", "bad");
                    redirect("notices", ["view" => $view]);
                }
                if ($act === "ack") {
                    q(
                        "INSERT INTO pi_notice_reads (notice_id,user_id,read_at,ack_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at,NOW()), ack_at=COALESCE(ack_at,NOW())",
                        [(int) $notice["id"], $uid],
                    );
                    clinic_metric_inc($cid, "notice_reads");
                    audit("leitura_confirmada", "comunicado", (int) $notice["id"]);
                    flash("Leitura registrada.");
                    redirect("notices", [
                        "view" => $view,
                        "notice" => (int) $notice["id"],
                    ]);
                }
                if ($act === "unhide") {
                    q(
                        "UPDATE pi_notice_reads SET hidden_at=NULL WHERE notice_id=? AND user_id=?",
                        [(int) $notice["id"], $uid],
                    );
                    flash("Aviso restaurado.");
                    redirect("notices", [
                        "view" => "received",
                        "notice" => (int) $notice["id"],
                    ]);
                }
                q(
                    "INSERT INTO pi_notice_reads (notice_id,user_id,hidden_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE hidden_at=NOW()",
                    [(int) $notice["id"], $uid],
                );
                flash("Aviso arquivado.");
                redirect("notices", ["view" => "archived"]);
            }
            $title = mb_trim((string) ($_POST["title"] ?? ""));
            $body = mb_trim((string) ($_POST["body"] ?? ""));
            $scope = (string) ($_POST["target_scope"] ?? "all");
            if (!in_array($scope, ["all", "role", "user"], true)) {
                $scope = "all";
            }
            $targetRole = null;
            $targetUser = null;
            if ($scope === "role") {
                $targetRole = (string) $c["role"];
            }
            if ($scope === "user") {
                $targetUser = (int) ($_POST["target_user_id"] ?? 0);
                $team = team_options($cid);
                if ($targetUser < 1 || !isset($team[$targetUser])) {
                    flash(
                        "Escolha um colaborador válido para receber o aviso.",
                        "bad",
                    );
                    redirect("notices", ["view" => $view]);
                }
            }
            if ($title === "" || $body === "") {
                flash("Informe título e mensagem do aviso.", "bad");
                redirect("notices", ["view" => $view]);
            }
            q(
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $title,
                    $body,
                    isset($_POST["requires_ack"]) ? 1 : 0,
                    $scope,
                    $targetRole,
                    $targetUser,
                    $uid,
                ],
            );
            $noticeId = db_last_insert_id();
            q(
                "INSERT INTO pi_notice_reads (notice_id,user_id,read_at,ack_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=NOW(), ack_at=NOW(), hidden_at=NULL",
                [$noticeId, $uid],
            );
            counter_inc("notices_total");
            clinic_metric_inc($cid, "notices");
            audit(
                "comunicado_criado",
                "comunicado",
                $noticeId,
                array_merge($_POST, [
                    "target_scope" => $scope,
                    "target_role" => $targetRole,
                    "target_user_id" => $targetUser,
                ]),
            );
            flash("Aviso publicado.");
            redirect("notices", ["view" => "sent", "notice" => $noticeId]);
        }
        $openId = (int) ($_GET["notice"] ?? 0);
        $team = team_options($cid);
        $userOpts = '<option value="">Selecione</option>';
        foreach ($team as $id => $name) {
            $userOpts .=
                '<option value="' . (int) $id . '">' . e($name) . "</option>";
        }
        $noticeCreateForm =
            '<form method="post" class="compact notice-form notice-form-refined ds-entity-form ds-standalone-form" data-notice-form>' .
            csrf_field() .
            '<input type="hidden" name="view" value="' .
            e($view) .
            '"><div class="notice-form-grid">' .
            form_row(
                "Título",
                input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Assunto do aviso"',
                ),
            ) .
            '<label class="field notice-target-scope"><span>Destinatários</span><select name="target_scope" data-notice-target-scope><option value="all">Toda a clínica</option><option value="role">Colaboradores do meu cargo</option><option value="user">Colaborador específico</option></select></label><label class="field notice-message-field"><span>Mensagem</span>' .
            textarea(
                "body",
                "",
                'required rows="5" placeholder="Escreva a orientação que precisa ser lida pela equipe."',
            ) .
            '</label><label class="field notice-target-user" data-notice-target-user><span>Colaborador específico</span><select name="target_user_id" data-notice-target-user-select>' .
            $userOpts .
            '</select></label><label class="check notice-ack-field"><input type="checkbox" name="requires_ack" checked> Exigir confirmação de leitura</label></div><div class="form-actions notice-form-actions"><a class="ghost" href="' .
            href("notices", ["view" => $view]) .
            '">' .
            icon("close") .
            '<span>Cancelar</span></a><button type="submit" class="primary">' .
            icon("campaign") .
            "<span>Publicar</span></button></div></form>";
        if (!$readOnly && ($_GET["new"] ?? "") === "1") {
            $back =
                '<a class="ghost small" href="' .
                href("notices", ["view" => $view]) .
                '">' .
                icon("arrow_back") .
                "<span>Voltar</span></a>";
            page(
                "Novo aviso",
                page_head(
                    "Novo aviso",
                    "Publique uma orientação em tela própria, mantendo a lista de Avisos limpa para leitura e acompanhamento.",
                    $back,
                ) .
                    card(
                        $noticeCreateForm,
                        "notice-card-shell notice-create-shell ds-form-shell",
                    ),
            );
            return;
        }
        $form = $readOnly
            ? ""
            : '<a class="primary small cmdlike" href="' .
                href("notices", ["view" => $view, "new" => 1]) .
                '">' .
                action_summary_label("Novo aviso", "notifications") .
                "</a>";
        [$targetSql, $targetParams] = notice_target_sql($c, "n");
        $accessSql = "(" . $targetSql . " OR n.created_by=?)";
        $rows = q(
            "SELECT n.id,n.title,n.body,n.requires_ack,n.target_scope,n.target_role,n.target_user_id,n.created_by,n.created_at FROM pi_notices n WHERE n.clinic_id=? AND $accessSql ORDER BY n.id DESC LIMIT 160",
            array_merge([$cid], $targetParams, [$uid]),
        )->fetchAll();
        $reads = [];
        if ($rows) {
            $ids = int_ids($rows, "id");
            $ph = implode(",", array_fill(0, count($ids), "?"));
            $params = array_merge([$uid], $ids);
            foreach (
                q(
                    "SELECT notice_id,read_at,ack_at,hidden_at FROM pi_notice_reads WHERE user_id=? AND notice_id IN ($ph)",
                    $params,
                )->fetchAll()
                as $r
            ) {
                $reads[(int) $r["notice_id"]] = $r;
            }
        }
        $authors = fetch_map("pi_users", int_ids($rows, "created_by"), "id,name");
        $targetUsers = scoped_user_map(
            $cid,
            int_ids($rows, "target_user_id"),
            "id,name",
        );
        $receivedCount = 0;
        $sentCount = 0;
        $hiddenCount = 0;
        $pendingCount = 0;
        foreach ($rows as $r) {
            $read = $reads[(int) $r["id"]] ?? [];
            $isMine = (int) ($r["created_by"] ?? 0) === $uid;
            if (!empty($read["hidden_at"])) {
                $hiddenCount++;
                continue;
            }
            if ($isMine) {
                $sentCount++;
            } else {
                $receivedCount++;
                if ((int) $r["requires_ack"] === 1 && empty($read["ack_at"])) {
                    $pendingCount++;
                }
            }
        }
        $filters =
            '<nav class="notice-filter-chips lead-filter-chips notice-gmail-filters ds-notice-filters" aria-label="Filtros de avisos">' .
            '<a class="lead-chip ds-filter-chip ' .
            ($view === "received" ? "active" : "") .
            '" href="' .
            e(href("notices", ["view" => "received"])) .
            '"><span class="material-symbols-rounded">inbox</span><span class="lead-chip-label">Recebidas</span><em>' .
            (int) $receivedCount .
            "</em></a>" .
            '<a class="lead-chip ds-filter-chip ' .
            ($view === "sent" ? "active" : "") .
            '" href="' .
            e(href("notices", ["view" => "sent"])) .
            '"><span class="material-symbols-rounded">send</span><span class="lead-chip-label">Enviadas</span><em>' .
            (int) $sentCount .
            "</em></a>" .
            '<a class="lead-chip ds-filter-chip ' .
            ($view === "archived" ? "active" : "") .
            '" href="' .
            e(href("notices", ["view" => "archived"])) .
            '"><span class="material-symbols-rounded">archive</span><span class="lead-chip-label">Arquivadas</span><em>' .
            (int) $hiddenCount .
            "</em></a>" .
            "</nav>";
        $detail = "";
        if ($openId > 0) {
            $openRow = null;
            foreach ($rows as $r) {
                if ((int) $r["id"] === $openId) {
                    $openRow = $r;
                    break;
                }
            }
            if ($openRow) {
                $isMine = (int) ($openRow["created_by"] ?? 0) === $uid;
                $read = $reads[$openId] ?? [];
                if (!$isMine && empty($read["ack_at"])) {
                    q(
                        "INSERT INTO pi_notice_reads (notice_id,user_id,read_at,ack_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at,NOW()), ack_at=COALESCE(ack_at,NOW())",
                        [$openId, $uid],
                    );
                    clinic_metric_inc($cid, "notice_reads");
                    audit("leitura_registrada", "comunicado", $openId);
                    $read["read_at"] = $read["read_at"] ?? date("Y-m-d H:i:s");
                    $read["ack_at"] = $read["ack_at"] ?? date("Y-m-d H:i:s");
                    $reads[$openId] = $read;
                }
                $author = $authors[(int) ($openRow["created_by"] ?? 0)] ?? [];
                $authorFirst = first_name((string) ($author["name"] ?? "Sistema"));
                $archived = !empty($read["hidden_at"]);
                $detailView = $archived
                    ? "archived"
                    : ($isMine
                        ? "sent"
                        : "received");
                $status = $archived
                    ? "Arquivada"
                    : ($isMine
                        ? "Enviada"
                        : "Recebida · leitura registrada");
                $iconName = $archived
                    ? "archive"
                    : ($isMine
                        ? "outgoing_mail"
                        : "mark_email_read");
                $direction = $isMine
                    ? '<span class="notice-direction-pill is-sent">' .
                        icon("north_east") .
                        " Enviada</span>" .
                        notice_recipient_pills($cid, $openRow, $team)
                    : '<span class="notice-direction-pill is-received">' .
                        icon("south_west") .
                        " " .
                        e($authorFirst) .
                        "</span>" .
                        notice_recipient_pills($cid, $openRow, $team);
                $action = $archived
                    ? '<form method="post" class="inline">' .
                        csrf_field() .
                        '<input type="hidden" name="act" value="unhide"><input type="hidden" name="view" value="archived"><input type="hidden" name="id" value="' .
                        $openId .
                        '"><button type="submit" class="small ghost">Restaurar</button></form>'
                    : '<form method="post" class="inline">' .
                        csrf_field() .
                        '<input type="hidden" name="act" value="hide"><input type="hidden" name="view" value="' .
                        $detailView .
                        '"><input type="hidden" name="id" value="' .
                        $openId .
                        '"><button type="submit" class="small ghost">Arquivar</button></form>';
                $detail = card(
                    '<article class="notice-reader notice-reader-' .
                        $detailView .
                        '"><header><a class="ghost small" href="' .
                        e(href("notices", ["view" => $detailView])) .
                        '">' .
                        icon("arrow_back") .
                        '<span>Voltar</span></a><span class="notice-reader-state">' .
                        icon($iconName) .
                        " " .
                        e($status) .
                        "</span><time>" .
                        e(dt_notice_br($openRow["created_at"])) .
                        "</time></header><h2>" .
                        e($openRow["title"]) .
                        '</h2><div class="notice-reader-body">' .
                        nl2br(e($openRow["body"])) .
                        "</div><footer>" .
                        $direction .
                        '<div class="notice-actions">' .
                        $action .
                        "</div></footer></article>",
                    "notice-card-shell notice-reader-shell",
                );
            } else {
                $detail = card(
                    '<div class="notice-empty">' .
                        icon("campaign") .
                        "<strong>Aviso não encontrado.</strong><span>Ela pode ter sido removida ou não estar disponível para sua credencial.</span></div>",
                    "notice-card-shell",
                );
            }
        }
        $cards = "";
        foreach ($rows as $r) {
            $read = $reads[(int) $r["id"]] ?? [];
            $isMine = (int) ($r["created_by"] ?? 0) === $uid;
            $archived = !empty($read["hidden_at"]);
            if ($view === "received" && ($isMine || $archived)) {
                continue;
            }
            if ($view === "sent" && (!$isMine || $archived)) {
                continue;
            }
            if ($view === "archived" && !$archived) {
                continue;
            }
            $author = $authors[(int) ($r["created_by"] ?? 0)] ?? [];
            $authorFirst = first_name((string) ($author["name"] ?? "Sistema"));
            $needsAck =
                !$isMine &&
                (int) $r["requires_ack"] === 1 &&
                empty($read["ack_at"]);
            $stateClass = $archived
                ? "archived"
                : ($isMine
                    ? "sent"
                    : ($needsAck
                        ? "received-unread"
                        : "received-read"));
            $rowIcon = $archived
                ? "archive"
                : ($isMine
                    ? "send"
                    : ($needsAck
                        ? "mail"
                        : "drafts"));
            $senderName = $isMine
                ? notice_recipient_phrase($r, $targetUsers)
                : $authorFirst;
            $openUrl = e(
                href("notices", ["view" => $view, "notice" => (int) $r["id"]]),
            );
            $cards .=
                '<a class="notice-card notice-gmail-row ds-notice-row notice-minimal-row notice-card-' .
                $stateClass .
                " " .
                ($needsAck ? "needs-ack" : "is-read") .
                " " .
                ($archived
                    ? "is-archived"
                    : ($isMine
                        ? "is-sent"
                        : "is-received")) .
                '" href="' .
                $openUrl .
                '">' .
                '<span class="notice-minimal-icon" aria-hidden="true">' .
                icon($rowIcon) .
                "</span>" .
                '<span class="notice-minimal-sender">' .
                e($senderName) .
                "</span>" .
                '<span class="notice-minimal-subject">' .
                e($r["title"]) .
                "</span>" .
                '<time class="notice-minimal-time">' .
                e(dt_notice_br($r["created_at"])) .
                "</time>" .
                "</a>";
        }
        $globalCards = "";
        $globalCount = 0;
        if ($view === "received") {
            $globalMeta = [
                "critical" => ["Crítico", "priority_high"],
                "warning" => ["Atenção", "warning"],
                "info" => ["Informativo", "info"],
            ];
            foreach (["critical", "warning", "info"] as $sev) {
                $globals = q(
                    "SELECT id,title,body,severity,created_at FROM pi_global_notices WHERE active=1 AND severity=? AND (starts_at IS NULL OR starts_at<=NOW()) AND (expires_at IS NULL OR expires_at>=NOW()) ORDER BY id DESC LIMIT 8",
                    [$sev],
                )->fetchAll();
                foreach ($globals as $g) {
                    $globalCount++;
                    $gm = $globalMeta[$sev] ?? $globalMeta["info"];
                    $globalCards .=
                        '<article class="notice-card global ds-notice-row notice-minimal-row severity-' .
                        e($sev) .
                        '"><span class="notice-minimal-icon" aria-hidden="true">' .
                        icon($gm[1]) .
                        '</span><span class="notice-minimal-sender">Prontoo</span><span class="notice-minimal-subject">' .
                        e($g["title"]) .
                        '</span><time class="notice-minimal-time">' .
                        e(dt_notice_br($g["created_at"])) .
                        "</time></article>";
                }
            }
        }
        if ($readOnly) {
            page(
                "Avisos",
                page_head("Avisos") .
                    '<section class="notice-screen admin-alert-screen readonly-support-screen">' .
                    readonly_support_notice_screen($c, $view) .
                    "</section>",
            );
            return;
        }
        $titleMap = [
            "received" => "Recebidas",
            "sent" => "Enviadas",
            "archived" => "Arquivadas",
        ];
        $countMap = [
            "received" => $receivedCount,
            "sent" => $sentCount,
            "archived" => $hiddenCount,
        ];
        $iconMap = [
            "received" => "inbox",
            "sent" => "send",
            "archived" => "archive",
        ];
        $sectionTitle = $titleMap[$view] ?? "Recebidas";
        $sectionCount = (int) ($countMap[$view] ?? 0);
        if ($cards === "") {
            $emptyMsg =
                $view === "received"
                    ? "Nenhum aviso recebido."
                    : ($view === "sent"
                        ? "Nenhum aviso enviado."
                        : "Nenhum aviso arquivado.");
            $cards =
                '<div class="notice-empty">' .
                icon($iconMap[$view] ?? "campaign") .
                "<strong>" .
                e($emptyMsg) .
                "</strong></div>";
        }
        $list = card(
            '<div class="notice-section-head ds-section-head notice-section-' .
                $view .
                '"><h2>' .
                e($sectionTitle) .
                "</h2><span>" .
                $sectionCount .
                '</span></div><div class="notice-list ds-notice-list">' .
                $cards .
                "</div>",
            "notice-card-shell ds-notice-shell notice-card-shell-" . $view,
        );
        if ($globalCards !== "") {
            $list .= card(
                '<div class="notice-section-head ds-section-head"><h2>Avisos do Prontoo</h2><span>' .
                    (int) $globalCount .
                    '</span></div><div class="notice-list ds-notice-list">' .
                    $globalCards .
                    "</div>",
                "notice-card-shell ds-notice-shell",
            );
        }
        $stats =
            '<div class="notice-kpis notice-gmail-kpis ds-notice-kpis"><div class="notice-kpi ds-kpi ' .
            ($pendingCount ? "notice-kpi-pending" : "") .
            '">' .
            icon("inbox") .
            "<div><b>" .
            (int) $receivedCount .
            '</b><span>Recebidas</span></div></div><div class="notice-kpi ds-kpi notice-kpi-sent">' .
            icon("send") .
            "<div><b>" .
            (int) $sentCount .
            '</b><span>Enviadas</span></div></div><div class="notice-kpi ds-kpi notice-kpi-archived">' .
            icon("archive") .
            "<div><b>" .
            (int) $hiddenCount .
            "</b><span>Arquivadas</span></div></div></div>";
        page(
            "Avisos",
            page_head("Avisos", "", $form) .
                '<section class="notice-screen notice-screen-' .
                $view .
                '">' .
                $stats .
                '<section class="notice-filter-list-block ds-filter-list-block" aria-label="Filtros e lista de avisos">' .
                $filters .
                $detail .
                $list .
                "</section></section>",
        );
    
    }
}
