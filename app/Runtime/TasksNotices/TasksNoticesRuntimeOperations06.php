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
        if ($readOnly) {
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Avisos",
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                    "Avisos",
                    "Fale com o suporte sobre pagamento, liberação ou acesso aos seus dados.",
                ) .
                    '<section class="readonly-support-screen form-screen">' .
                    \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations05::readonly_support_notice_screen($c, $view) .
                    "</section>",
            );
            return;
        }

        $filterItems = [
            ["received", "inbox", "Recebidas", $receivedCount],
            ["sent", "send", "Enviadas", $sentCount],
            ["archived", "archive", "Arquivadas", $hiddenCount],
        ];
        $filters = '<nav class="ds-notice-filters" aria-label="Caixas de avisos">';
        foreach ($filterItems as [$filterView, $filterIcon, $filterLabel, $filterCount]) {
            $active = $view === $filterView;
            $filters .=
                '<a class="ds-filter-chip' .
                ($active ? " is-active" : "") .
                '" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => $filterView]),
                ) .
                '"' .
                ($active ? ' aria-current="page"' : "") .
                '>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($filterIcon) .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($filterLabel) .
                '</span><em>' .
                (int) $filterCount .
                "</em></a>";
        }
        $filters .= "</nav>";

        $detail = "";
        $detailView = $view;
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
                    if ((int) ($openRow["requires_ack"] ?? 0) === 1 && $pendingCount > 0) {
                        $pendingCount--;
                    }
                    $read["read_at"] = $read["read_at"] ?? date("Y-m-d H:i:s");
                    $read["ack_at"] = $read["ack_at"] ?? date("Y-m-d H:i:s");
                    $reads[$openId] = $read;
                }
                $author = $authors[(int) ($openRow["created_by"] ?? 0)] ?? [];
                $authorFirst = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($author["name"] ?? "Sistema"));
                $archived = !empty($read["hidden_at"]);
                $detailView = $archived ? "archived" : ($isMine ? "sent" : "received");
                $status = $archived ? "Arquivada" : ($isMine ? "Enviada" : "Recebida · leitura registrada");
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
                        '"><button type="submit" class="small ghost">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("unarchive") .
                        '<span>Restaurar</span></button></form>'
                    : '<form method="post" class="inline">' .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        '<input type="hidden" name="act" value="hide"><input type="hidden" name="view" value="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($detailView) .
                        '"><input type="hidden" name="id" value="' .
                        $openId .
                        '"><button type="submit" class="small ghost">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("archive") .
                        '<span>Arquivar</span></button></form>';
                $detail = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<article class="notice-reader notice-reader-' .
                        $detailView .
                        '"><div class="section-head notice-reader-head"><div><span class="eyebrow">Aviso</span><h2 id="notice-reader-title">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($openRow["title"]) .
                        '</h2><span class="field-help">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status) .
                        " · " .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($openRow["created_at"])) .
                        '</span></div></div><div class="notice-reader-body">' .
                        nl2br(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($openRow["body"])) .
                        '</div><footer class="notice-reader-footer"><div class="notice-reader-meta">' .
                        $direction .
                        '</div><div class="notice-actions">' .
                        $action .
                        "</div></footer></article>",
                    "notice-reader-card",
                );
            } else {
                $detail = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<div class="notice-empty ds-empty"><span class="notice-empty-icon" aria-hidden="true">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
                        '</span><strong>Aviso não encontrado.</strong><span>Ele pode ter sido removido ou não estar disponível para sua credencial.</span></div>',
                    "notice-reader-card",
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
            $needsAck = !$isMine && (int) $r["requires_ack"] === 1 && empty($read["ack_at"]);
            $rowIcon = $archived ? "archive" : ($isMine ? "send" : ($needsAck ? "mail" : "drafts"));
            $context = $isMine
                ? \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::notice_recipient_phrase($r, $targetUsers)
                : $authorFirst;
            $stateLabel = $archived ? "Arquivada" : ($isMine ? "Enviada" : ($needsAck ? "Leitura pendente" : "Lida"));
            $openUrl = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => $view, "notice" => (int) $r["id"]]),
            );
            $cards .=
                '<a class="notice-row' .
                ($needsAck ? " is-unread" : "") .
                ($archived ? " is-archived" : "") .
                '" data-ds-row-kind="surface" href="' .
                $openUrl .
                '"><span class="notice-row-icon" data-ds-icon-chip="1" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($rowIcon) .
                '</span><span class="notice-row-main"><strong class="notice-row-title">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["title"]) .
                '</strong><span class="notice-row-meta"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($context) .
                '</span><span aria-hidden="true">·</span><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stateLabel) .
                '</span></span></span><time class="notice-row-time">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($r["created_at"])) .
                "</time></a>";
        }

        $titleMap = ["received" => "Recebidas", "sent" => "Enviadas", "archived" => "Arquivadas"];
        $countMap = ["received" => $receivedCount, "sent" => $sentCount, "archived" => $hiddenCount];
        $iconMap = ["received" => "inbox", "sent" => "send", "archived" => "archive"];
        $descriptionMap = [
            "received" => "Orientações recebidas de outros colaboradores deste consultório.",
            "sent" => "Avisos publicados por você para a equipe.",
            "archived" => "Avisos retirados da caixa principal e disponíveis para restauração.",
        ];
        $sectionTitle = $titleMap[$view] ?? "Recebidas";
        $sectionCount = (int) ($countMap[$view] ?? 0);
        if ($cards === "") {
            $emptyTitle = $view === "received"
                ? "Nenhum aviso recebido."
                : ($view === "sent" ? "Nenhum aviso publicado." : "Nenhum aviso arquivado.");
            $emptyText = $view === "received"
                ? "Quando alguém da equipe publicar uma orientação para você, ela aparecerá aqui."
                : ($view === "sent"
                    ? "Publique uma orientação para sua equipe e acompanhe o envio nesta caixa."
                    : "Os avisos arquivados ficam disponíveis aqui para restauração.");
            $emptyAction = $view === "sent"
                ? '<div class="notice-empty-actions"><a class="primary small" href="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => "sent", "new" => 1]),
                    ) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("notifications") .
                    '<span>Novo aviso</span></a></div>'
                : "";
            $cards =
                '<div class="notice-empty ds-empty"><span class="notice-empty-icon" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconMap[$view] ?? "campaign") .
                '</span><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($emptyTitle) .
                '</strong><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($emptyText) .
                "</span>" .
                $emptyAction .
                "</div>";
        }
        $list = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head ds-section-head"><div><span class="eyebrow">Caixa</span><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sectionTitle) .
                '</h2><span class="field-help">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($descriptionMap[$view] ?? $descriptionMap["received"]) .
                '</span></div><span class="notice-section-count">' .
                $sectionCount .
                '</span></div><div class="notice-list">' .
                $cards .
                "</div>",
            "notice-list-card",
        );

        $systemList = "";
        if ($view === "received") {
            $globalCards = "";
            $globalCount = 0;
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
                        '<article class="notice-row notice-system-row severity-' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sev) .
                        '" data-ds-row-kind="surface"><span class="notice-row-icon" data-ds-icon-chip="1" aria-hidden="true">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($gm[1]) .
                        '</span><span class="notice-row-main"><strong class="notice-row-title">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($g["title"]) .
                        '</strong><span class="notice-row-meta"><span>Prontoo</span><span class="notice-status-pill severity-' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sev) .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($gm[0]) .
                        '</span></span></span><time class="notice-row-time">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($g["created_at"])) .
                        "</time></article>";
                }
            }
            if ($globalCards !== "") {
                $systemList = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<div class="section-head ds-section-head"><div><span class="eyebrow">Prontoo</span><h2>Comunicados do Prontoo</h2><span class="field-help">Informações institucionais separadas das mensagens internas da equipe.</span></div><span class="notice-section-count">' .
                        (int) $globalCount .
                        '</span></div><div class="notice-list notice-system-list">' .
                        $globalCards .
                        "</div>",
                    "notice-system-card",
                );
            }
        }

        $pageDescription = "Comunique orientações à equipe e acompanhe as informações recebidas.";
        if ($pendingCount > 0) {
            $pageDescription .= " " .
                $pendingCount .
                ($pendingCount === 1 ? " aviso aguarda sua leitura." : " avisos aguardam sua leitura.");
        }
        $pageAction = $form;
        $content = $filters . $list . $systemList;
        if ($detail !== "") {
            $pageDescription = "Leia a comunicação, confira os destinatários e gerencie seu arquivamento.";
            $pageAction =
                '<a class="ghost small" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => $detailView]),
                ) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                '<span>Voltar</span></a>';
            $content = $detail;
        }

        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Avisos",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Avisos",
                $pageDescription,
                $pageAction,
            ) .
                '<section class="notice-screen">' .
                $content .
                "</section>",
        );
    }
}
