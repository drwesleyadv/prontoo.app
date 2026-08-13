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

final class TasksNoticesRuntimeOperations06
{
    private function __construct()
    {
    }

    public static function page_notices(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("notices");
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
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->ensureSchema("readonly_support_alerts");
                $title = mb_trim((string) ($_POST["title"] ?? ""));
                $body = mb_trim((string) ($_POST["body"] ?? ""));
                if ($title === "" || $body === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe assunto e mensagem para o suporte.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices");
                }
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.01', [
                        $uid,
                        $cid,
                        "clinic_readonly",
                        null,
                        mb_substr($title, 0, 180),
                        $body,
                        "warning",
                    ], []);
                $id = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->lastInsertId();
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("aviso_suporte_assinatura", "aviso_admin", $id, [
                    "clinic_id" => $cid,
                    "audit_body" =>
                        "Usuário em modo somente leitura enviou mensagem ao Desenvolvedor pelo contexto Avisos.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Mensagem enviada ao suporte.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices", ["view" => "sent"]);
            }
            if ($readOnly && !in_array($act, ["ack", "hide", "unhide"], true)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "No modo somente leitura, Avisos fica restrito ao contato com o suporte.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices");
            }
            if ($act === "ack" || $act === "hide" || $act === "unhide") {
                $targetParams = \Prontoo\Domain\TasksNotices\TasksNoticesDomainOperations01::notice_target_parameters($c);
                $notice = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.06.page_notices.02', array_merge([(int) ($_POST["id"] ?? 0), $cid], $targetParams, [
                        $uid,
                    ]), []);
                if (!$notice) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Aviso não encontrado para este colaborador.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices", ["view" => $view]);
                }
                if ($act === "ack") {
                    \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.03', [(int) $notice["id"], $uid], []);
                    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "notice_reads");
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("leitura_confirmada", "comunicado", (int) $notice["id"]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Leitura registrada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices", [
                        "view" => $view,
                        "notice" => (int) $notice["id"],
                    ]);
                }
                if ($act === "unhide") {
                    \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.04', [(int) $notice["id"], $uid], []);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Aviso restaurado.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices", [
                        "view" => "received",
                        "notice" => (int) $notice["id"],
                    ]);
                }
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.05', [(int) $notice["id"], $uid], []);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Aviso arquivado.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices", ["view" => "archived"]);
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
                $team = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::team_options($cid);
                if ($targetUser < 1 || !isset($team[$targetUser])) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Escolha um colaborador válido para receber o aviso.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices", ["view" => $view]);
                }
            }
            if ($title === "" || $body === "") {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe título e mensagem do aviso.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices", ["view" => $view]);
            }
            \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.06', [
                    $cid,
                    $title,
                    $body,
                    isset($_POST["requires_ack"]) ? 1 : 0,
                    $scope,
                    $targetRole,
                    $targetUser,
                    $uid,
                ], []);
            $noticeId = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->lastInsertId();
            \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.07', [$noticeId, $uid], []);
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("notices_total");
            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "notices");
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                "comunicado_criado",
                "comunicado",
                $noticeId,
                array_merge($_POST, [
                    "target_scope" => $scope,
                    "target_role" => $targetRole,
                    "target_user_id" => $targetUser,
                ]),
            );
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Aviso publicado.");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("notices", ["view" => "sent", "notice" => $noticeId]);
        }
        $openId = (int) ($_GET["notice"] ?? 0);
        $team = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::team_options($cid);
        $userOpts = '<option value="">Selecione</option>';
        foreach ($team as $id => $name) {
            $userOpts .=
                '<option value="' . (int) $id . '">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) . "</option>";
        }
        $noticeCreateForm =
            '<form method="post" class="compact notice-form notice-form-refined ds-entity-form ds-standalone-form" data-notice-form>' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="view" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($view) .
            '"><div class="notice-form-grid">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Título",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Assunto do aviso"',
                ),
            ) .
            '<label class="field notice-target-scope"><span>Destinatários</span><select name="target_scope" data-notice-target-scope><option value="all">Toda a clínica</option><option value="role">Colaboradores do meu cargo</option><option value="user">Colaborador específico</option></select></label><label class="field notice-message-field"><span>Mensagem</span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                "body",
                "",
                'required rows="5" placeholder="Escreva a orientação que precisa ser lida pela equipe."',
            ) .
            '</label><label class="field notice-target-user" data-notice-target-user><span>Colaborador específico</span><select name="target_user_id" data-notice-target-user-select>' .
            $userOpts .
            '</select></label><label class="check notice-ack-field"><input type="checkbox" name="requires_ack" checked> Exigir confirmação de leitura</label></div><div class="form-actions notice-form-actions"><a class="ghost" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => $view]) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
            '<span>Cancelar</span></a><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
            "<span>Publicar</span></button></div></form>";
        if (!$readOnly && ($_GET["new"] ?? "") === "1") {
            $back =
                '<a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => $view]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Voltar</span></a>";
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Novo aviso",
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                    "Novo aviso",
                    "Publique uma orientação em tela própria, mantendo a lista de Avisos limpa para leitura e acompanhamento.",
                    $back,
                ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                        $noticeCreateForm,
                        "notice-card-shell notice-create-shell ds-form-shell",
                    ),
            );
            return;
        }
        $form = $readOnly
            ? ""
            : '<a class="pagehead-control pagehead-control--primary" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => $view, "new" => 1]) .
                '">' .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Novo aviso", "notifications") .
                "</a>";
        $targetParams = \Prontoo\Domain\TasksNotices\TasksNoticesDomainOperations01::notice_target_parameters($c);
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.08', array_merge([$cid], $targetParams, [$uid]), [])->fetchAll();
        $reads = [];
        if ($rows) {
            $ids = \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "id");
            $params = array_merge([$uid], $ids);
            foreach (
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.09', $params, ['itemCount' => count($ids)])->fetchAll()
                as $r
            ) {
                $reads[(int) $r["notice_id"]] = $r;
            }
        }
        $authors = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map("users_name", \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "created_by"));
        $targetUsers = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::scoped_user_map(
            $cid,
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "target_user_id"),
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => "received"])) .
            '"><span class="material-symbols-rounded">inbox</span><span class="lead-chip-label">Recebidas</span><em>' .
            (int) $receivedCount .
            "</em></a>" .
            '<a class="lead-chip ds-filter-chip ' .
            ($view === "sent" ? "active" : "") .
            '" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => "sent"])) .
            '"><span class="material-symbols-rounded">send</span><span class="lead-chip-label">Enviadas</span><em>' .
            (int) $sentCount .
            "</em></a>" .
            '<a class="lead-chip ds-filter-chip ' .
            ($view === "archived" ? "active" : "") .
            '" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => "archived"])) .
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
                    \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.10', [$openId, $uid], []);
                    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "notice_reads");
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("leitura_registrada", "comunicado", $openId);
                    $read["read_at"] = $read["read_at"] ?? date("Y-m-d H:i:s");
                    $read["ack_at"] = $read["ack_at"] ?? date("Y-m-d H:i:s");
                    $reads[$openId] = $read;
                }
                $author = $authors[(int) ($openRow["created_by"] ?? 0)] ?? [];
                $authorFirst = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($author["name"] ?? "Sistema"));
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
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("north_east") .
                        " Enviada</span>" .
                        \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::notice_recipient_pills($cid, $openRow, $team)
                    : '<span class="notice-direction-pill is-received">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("south_west") .
                        " " .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($authorFirst) .
                        "</span>" .
                        \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::notice_recipient_pills($cid, $openRow, $team);
                $action = $archived
                    ? '<form method="post" class="inline">' .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        '<input type="hidden" name="act" value="unhide"><input type="hidden" name="view" value="archived"><input type="hidden" name="id" value="' .
                        $openId .
                        '"><button type="submit" class="small ghost">Restaurar</button></form>'
                    : '<form method="post" class="inline">' .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        '<input type="hidden" name="act" value="hide"><input type="hidden" name="view" value="' .
                        $detailView .
                        '"><input type="hidden" name="id" value="' .
                        $openId .
                        '"><button type="submit" class="small ghost">Arquivar</button></form>';
                $detail = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<article class="notice-reader notice-reader-' .
                        $detailView .
                        '"><header><a class="ghost small" href="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => $detailView])) .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                        '<span>Voltar</span></a><span class="notice-reader-state">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
                        " " .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status) .
                        "</span><time>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($openRow["created_at"])) .
                        "</time></header><h2>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($openRow["title"]) .
                        '</h2><div class="notice-reader-body">' .
                        nl2br(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($openRow["body"])) .
                        "</div><footer>" .
                        $direction .
                        '<div class="notice-actions">' .
                        $action .
                        "</div></footer></article>",
                    "notice-card-shell notice-reader-shell",
                );
            } else {
                $detail = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<div class="notice-empty">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
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
            $authorFirst = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($author["name"] ?? "Sistema"));
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
                ? \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::notice_recipient_phrase($r, $targetUsers)
                : $authorFirst;
            $openUrl = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => $view, "notice" => (int) $r["id"]]),
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($rowIcon) .
                "</span>" .
                '<span class="notice-minimal-sender">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($senderName) .
                "</span>" .
                '<span class="notice-minimal-subject">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["title"]) .
                "</span>" .
                '<time class="notice-minimal-time">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($r["created_at"])) .
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
                $globals = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.11', [$sev], [])->fetchAll();
                foreach ($globals as $g) {
                    $globalCount++;
                    $gm = $globalMeta[$sev] ?? $globalMeta["info"];
                    $globalCards .=
                        '<article class="notice-card global ds-notice-row notice-minimal-row severity-' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sev) .
                        '"><span class="notice-minimal-icon" aria-hidden="true">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($gm[1]) .
                        '</span><span class="notice-minimal-sender">Prontoo</span><span class="notice-minimal-subject">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($g["title"]) .
                        '</span><time class="notice-minimal-time">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($g["created_at"])) .
                        "</time></article>";
                }
            }
        }
        if ($readOnly) {
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Avisos",
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Avisos") .
                    '<section class="notice-screen admin-alert-screen readonly-support-screen">' .
                    \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations05::readonly_support_notice_screen($c, $view) .
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconMap[$view] ?? "campaign") .
                "<strong>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($emptyMsg) .
                "</strong></div>";
        }
        $list = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="notice-section-head ds-section-head notice-section-' .
                $view .
                '"><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sectionTitle) .
                "</h2><span>" .
                $sectionCount .
                '</span></div><div class="notice-list ds-notice-list">' .
                $cards .
                "</div>",
            "notice-card-shell ds-notice-shell notice-card-shell-" . $view,
        );
        if ($globalCards !== "") {
            $list .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("inbox") .
            "<div><b>" .
            (int) $receivedCount .
            '</b><span>Recebidas</span></div></div><div class="notice-kpi ds-kpi notice-kpi-sent">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("send") .
            "<div><b>" .
            (int) $sentCount .
            '</b><span>Enviadas</span></div></div><div class="notice-kpi ds-kpi notice-kpi-archived">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("archive") .
            "<div><b>" .
            (int) $hiddenCount .
            "</b><span>Arquivadas</span></div></div></div>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Avisos",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Avisos", "", $form) .
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
