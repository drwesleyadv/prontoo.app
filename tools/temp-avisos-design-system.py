from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OP5 = ROOT / "app/Runtime/TasksNotices/TasksNoticesRuntimeOperations05.php"
OP6 = ROOT / "app/Runtime/TasksNotices/TasksNoticesRuntimeOperations06.php"
CSS = ROOT / "design/styles/application.css"
JS = ROOT / "public/assets/app.js"


def require(text: str, needle: str, label: str) -> int:
    pos = text.find(needle)
    if pos < 0:
        raise SystemExit(f"missing marker {label}: {needle}")
    return pos


support_method = r'''    public static function readonly_support_notice_screen(array $c, string $view = "sent"): string
    {
        \Prontoo\Runtime\Operational\OperationalComposition::tasks()->ensureSchema("readonly_support_alerts");
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? 0);
        $rows = [];
        try {
            $rows = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.05.readonly_support_notice_screen.01', [$uid, $cid], [])->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo readonly support alerts list] " . $e->getMessage());
        }

        $form =
            '<section class="form-section notice-support-compose">' .
            '<div class="section-head"><div><span class="eyebrow">Suporte</span><h2>Mensagem para o suporte</h2><span class="field-help">Use este canal para dúvidas sobre pagamento, liberação ou acesso aos seus dados.</span></div></div>' .
            '<form method="post" class="notice-form ds-entity-form ds-standalone-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="support_message"><div class="compact">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Assunto",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Ex.: Regularização da assinatura"',
                ),
            ) .
            '<label class="field notice-message-field"><span>Mensagem</span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                "body",
                "",
                'required rows="5" placeholder="Explique sua dúvida sobre pagamento, liberação ou acesso aos seus dados."',
            ) .
            '</label></div><div class="form-actions notice-form-actions"><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("support_agent") .
            '<span>Enviar ao suporte</span></button></div></form></section>';

        $cards = "";
        foreach ($rows as $r) {
            $readLabel = empty($r["read_at"]) ? "Enviada ao suporte" : "Lida pelo suporte";
            $cards .=
                '<article class="notice-row notice-support-row" data-ds-row-kind="surface"><span class="notice-row-icon" data-ds-icon-chip="1" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(empty($r["read_at"]) ? "outgoing_mail" : "done_all") .
                '</span><span class="notice-row-main"><strong class="notice-row-title">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["title"]) .
                '</strong><span class="notice-row-meta"><span>Suporte</span><span aria-hidden="true">·</span><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($readLabel) .
                '</span></span></span><time class="notice-row-time">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($r["created_at"])) .
                "</time></article>";
        }
        if ($cards === "") {
            $cards =
                '<div class="notice-empty ds-empty"><span class="notice-empty-icon" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("support_agent") .
                '</span><strong>Nenhuma mensagem enviada ao suporte.</strong><span>Use o formulário acima quando precisar falar com o Desenvolvedor.</span></div>';
        }

        $list = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head ds-section-head"><div><span class="eyebrow">Histórico</span><h2>Mensagens para o suporte</h2><span class="field-help">Acompanhe as mensagens enviadas por este consultório.</span></div><span class="notice-section-count">' .
                count($rows) .
                '</span></div><div class="notice-list">' .
                $cards .
                "</div>",
            "notice-list-card",
        );
        return $form . $list;
    }
'''

op5 = OP5.read_text()
start = require(op5, "    public static function readonly_support_notice_screen", "support method")
op5 = op5[:start] + support_method + "}\n"
for obsolete in (
    "notice-gmail-row",
    "notice-minimal-row",
    "ds-notice-row",
    "ds-notice-list",
    "ds-notice-shell",
    "notice-compose-panel",
    "notice-form-refined",
):
    if obsolete in op5:
        raise SystemExit(f"obsolete token remains in Operations05: {obsolete}")
OP5.write_text(op5)

op6 = OP6.read_text()
open_marker = '        $openId = (int) ($_GET["notice"] ?? 0);'
open_pos = require(op6, open_marker, "open notice")
form_pos = require(op6[open_pos:], '        $form = $readOnly', "page action") + open_pos
op6 = (
    op6[:open_pos]
    + open_marker
    + '\n        $team = \\Prontoo\\Runtime\\UsersPermissions\\UsersPermissionsRuntimeOperations02::team_options($cid);\n'
    + op6[form_pos:]
)

ui_start = require(op6, "        $filters =", "legacy notice rendering")
ui_block = r'''        if ($readOnly) {
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
'''
op6 = op6[:ui_start] + ui_block + "    }\n}\n"
for obsolete in (
    "notice-gmail-row",
    "notice-gmail-filters",
    "notice-minimal-row",
    "ds-notice-row",
    "ds-notice-list",
    "ds-notice-shell",
    "notice-reader-shell",
    "notice-card-shell",
    "notice-gmail-kpis",
    "notice-kpis notice-gmail-kpis",
    "noticeCreateForm",
    "userOpts",
):
    if obsolete in op6:
        raise SystemExit(f"obsolete token remains in Operations06: {obsolete}")
OP6.write_text(op6)

js = JS.read_text()
if js.count(".notice-gmail-row") != 2:
    raise SystemExit(f"unexpected notice-gmail-row count in app.js: {js.count('.notice-gmail-row')}")
js = js.replace(".notice-gmail-row", ".notice-row")
JS.write_text(js)

css = CSS.read_text()
legacy_replacements = {
    ".notice-gmail-row": ".notice-row",
    ".notice-minimal-row": ".notice-row",
    ".ds-notice-row": ".notice-row",
    ".ds-notice-list": ".notice-list",
    ".notice-gmail-filters": ".ds-notice-filters",
    ".ds-notice-shell": ".notice-list-card",
    ".notice-reader-shell": ".notice-reader-card",
    ".notice-compose-panel": ".notice-support-compose",
    ".notice-form-refined": ".notice-form",
}
for old, new in legacy_replacements.items():
    css = css.replace(old, new)
for _ in range(4):
    for token in (
        ".notice-row",
        ".notice-row:hover",
        ".notice-list",
        ".ds-notice-filters",
        ".notice-list-card",
        ".notice-reader-card",
        ".notice-form",
    ):
        css = css.replace(f"{token},{token}", token)

canonical_css = r'''

/* Avisos — linguagem canônica do Design System */
.notice-screen{display:grid;gap:var(--pt-ds-gap-md);min-width:0;background:transparent;border:0;box-shadow:none}
.notice-list-card,.notice-system-card,.notice-reader-card{display:grid;gap:0;min-width:0;background:var(--pt-ds-surface);border:var(--pt-ds-border);border-radius:var(--pt-ds-radius-lg);box-shadow:var(--md-sys-elevation-level1)}
.notice-list{display:grid;gap:8px}.notice-list-card .section-head,.notice-system-card .section-head{align-items:flex-start;margin:0 0 12px;padding:0 0 12px}.notice-section-count{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:30px;padding:0 10px;border-radius:999px;background:var(--md-sys-color-surface-container-high);color:var(--md-sys-color-on-surface);font:var(--md-sys-typescale-label-medium);font-weight:900}
.ds-notice-filters{margin:0;padding:10px;border-radius:var(--pt-ds-radius-lg);background:var(--pt-ds-surface-soft);border:var(--pt-ds-border-soft);box-shadow:none}.ds-notice-filters .ds-filter-chip{min-height:40px}.ds-notice-filters .ds-filter-chip em{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;padding:0 7px;border-radius:999px;background:var(--md-sys-color-surface-container-high);color:var(--md-sys-color-on-surface-variant);font:var(--md-sys-typescale-label-small);font-style:normal;font-weight:900}.ds-notice-filters .ds-filter-chip.is-active em{background:color-mix(in srgb,var(--md-sys-color-primary) 16%,var(--md-sys-color-primary-container));color:var(--md-sys-color-on-primary-container)}
.notice-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:12px;min-height:64px;padding:11px 13px;color:var(--md-sys-color-on-surface);text-decoration:none}.notice-row:hover{color:var(--md-sys-color-on-surface);text-decoration:none}.notice-row-icon{width:40px;height:40px;min-width:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:15px;background:color-mix(in srgb,var(--md-sys-color-primary) 10%,var(--md-sys-color-surface-container-lowest));color:var(--md-sys-color-primary);border:1px solid color-mix(in srgb,var(--md-sys-color-primary) 12%,transparent)}.notice-row-main{display:grid;gap:3px;min-width:0}.notice-row-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--md-sys-color-on-surface);font:var(--md-sys-typescale-title-small);font-weight:850;line-height:1.25}.notice-row-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:0;color:var(--md-sys-color-on-surface-variant);font:var(--md-sys-typescale-body-small);line-height:1.3}.notice-row-time{justify-self:end;color:var(--md-sys-color-on-surface-variant);font:var(--md-sys-typescale-body-small);white-space:nowrap}.notice-row.is-unread{border-left:4px solid var(--md-sys-color-primary)!important;background:color-mix(in srgb,var(--md-sys-color-primary) 4%,var(--md-sys-color-surface-container-lowest))!important}.notice-row.is-unread .notice-row-title{font-weight:900}.notice-row.is-archived{opacity:.74}
.notice-system-row{background:color-mix(in srgb,var(--md-sys-color-tertiary) 3%,var(--md-sys-color-surface-container-lowest))!important}.notice-system-row .notice-row-icon{background:var(--md-sys-color-tertiary-container);color:var(--md-sys-color-on-tertiary-container);border-color:color-mix(in srgb,var(--md-sys-color-tertiary) 14%,transparent)}.notice-status-pill{display:inline-flex;align-items:center;min-height:24px;padding:4px 8px;border-radius:999px;background:var(--md-sys-color-tertiary-container);color:var(--md-sys-color-on-tertiary-container);font:var(--md-sys-typescale-label-small);font-weight:900}.notice-status-pill.severity-warning{background:var(--pt-color-warning-container);color:var(--pt-color-on-warning-container)}.notice-status-pill.severity-critical{background:var(--md-sys-color-error-container);color:var(--md-sys-color-on-error-container)}.notice-system-row.severity-warning{border-left:4px solid var(--pt-color-warning)!important}.notice-system-row.severity-critical{border-left:4px solid var(--md-sys-color-error)!important}
.notice-reader-card{padding:var(--pt-ds-section-pad)}.notice-reader{display:grid;gap:0}.notice-reader-head.section-head{align-items:flex-start;margin:0;padding:0 0 14px}.notice-reader-body{padding:18px 0;border:0;border-radius:0;background:transparent;color:var(--md-sys-color-on-surface);font:var(--md-sys-typescale-body-large);line-height:1.62;white-space:normal}.notice-reader-footer{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding-top:14px;border-top:1px solid color-mix(in srgb,var(--md-sys-color-outline-variant) 76%,transparent);background:transparent}.notice-reader-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;min-width:0}.notice-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-left:auto}
.notice-empty{display:grid;justify-items:center;align-content:center;gap:8px;min-height:152px;text-align:center}.notice-empty-icon{width:46px;height:46px;display:inline-flex;align-items:center;justify-content:center;border-radius:17px;background:var(--md-sys-color-primary-container);color:var(--md-sys-color-primary)}.notice-empty strong{color:var(--md-sys-color-on-surface);font:var(--md-sys-typescale-title-small);font-weight:900}.notice-empty>span:not(.notice-empty-icon){max-width:58ch;color:var(--md-sys-color-on-surface-variant);font:var(--md-sys-typescale-body-medium)}.notice-empty-actions{display:flex;justify-content:center;gap:8px;margin-top:4px}
.readonly-support-screen{display:grid;gap:var(--pt-ds-gap-lg)}.notice-support-compose{display:grid;gap:0}.notice-support-compose .section-head{align-items:flex-start}.notice-support-compose .notice-form{gap:12px}.notice-support-row .notice-row-icon{background:var(--md-sys-color-secondary-container);color:var(--md-sys-color-on-secondary-container);border-color:color-mix(in srgb,var(--md-sys-color-secondary) 14%,transparent)}
@media(max-width:760px){.notice-row{grid-template-columns:auto minmax(0,1fr);align-items:start}.notice-row-time{grid-column:2;justify-self:start}.notice-row-title{white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.notice-reader-footer{align-items:stretch}.notice-reader-meta{width:100%}.notice-actions{width:100%;margin-left:0;justify-content:stretch}.notice-actions form,.notice-actions button{width:100%}.ds-notice-filters{overflow-x:auto;flex-wrap:nowrap;scrollbar-width:none}.ds-notice-filters::-webkit-scrollbar{display:none}.ds-notice-filters .ds-filter-chip{flex:0 0 auto}}
'''
css = css.rstrip() + canonical_css + "\n"
for obsolete in (
    ".notice-gmail-row",
    ".notice-minimal-row",
    ".ds-notice-row",
    ".ds-notice-list",
    ".notice-gmail-filters",
    ".ds-notice-shell",
    ".notice-reader-shell",
    ".notice-compose-panel",
    ".notice-form-refined",
):
    if obsolete in css:
        raise SystemExit(f"obsolete selector remains in application.css: {obsolete}")
CSS.write_text(css)

Path(__file__).unlink()
