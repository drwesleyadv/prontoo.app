<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Documents;

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

final class DocumentsRuntimeOperations06
{
    private function __construct()
    {
    }

    public static function page_procedures(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("procedures");
        if (!\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")) {
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("acesso_negado", "rota", "procedures", [
                "janela" => "procedures",
            ]);
            http_response_code(403);
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Acesso restrito",
                '<div class="empty">Apenas a Gestão cadastra procedimentos.</div>',
            );
            return;
        }
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "save");
            $id = (int) ($_POST["id"] ?? 0);
            $backParams = $id > 0 ? ["edit" => $id] : ["new" => "1"];
            if ($act === "toggle") {
                $p = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.06.page_procedures.01', [$id, $cid], []);
                if ($p) {
                    $active = (int) $p["active"] === 1 ? 0 : 1;
                    \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.06.page_procedures.02', [$active, $uid, $id, $cid], []);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("procedimento_status", "procedimento", $id, [
                        "active" => $active,
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        $active
                            ? "Procedimento reativado."
                            : "Procedimento desativado.",
                    );
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("procedures");
            }
            $title = mb_trim((string) ($_POST["title"] ?? ""));
            if ($title === "") {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o nome do procedimento.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("procedures", $backParams);
            }
            $duration = max(5, min(600, (int) ($_POST["duration_minutes"] ?? 30)));
            $price = \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents((string) ($_POST["price"] ?? "0"));
            $category = mb_trim((string) ($_POST["category"] ?? ""));
            $description = mb_trim((string) ($_POST["description"] ?? ""));
            $payments = mb_trim((string) ($_POST["payment_methods"] ?? ""));
            $pre = mb_trim((string) ($_POST["pre_instructions"] ?? ""));
            $post = mb_trim((string) ($_POST["post_care"] ?? ""));
            if ($id > 0) {
                $p = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.06.page_procedures.03', [$id, $cid], []);
                if (!$p) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Procedimento não encontrado.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("procedures");
                }
                \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.06.page_procedures.04', [
                        $title,
                        $category,
                        $description,
                        $duration,
                        $price,
                        $payments,
                        $pre,
                        $post,
                        $uid,
                        $id,
                        $cid,
                    ], []);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("procedimento_atualizado", "procedimento", $id, [
                    "titulo" => $title,
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Procedimento atualizado.");
            } else {
                $submissionToken = trim(
                    (string) ($_POST["procedure_submission_token"] ?? ""),
                );
                $submissionTokens =
                    $_SESSION["procedure_create_submission_tokens"] ?? [];
                if (!is_array($submissionTokens)) {
                    $submissionTokens = [];
                }
                $submissionAccepted =
                    $submissionToken !== "" &&
                    isset($submissionTokens[$submissionToken]);
                if ($submissionAccepted) {
                    unset($submissionTokens[$submissionToken]);
                    $_SESSION["procedure_create_submission_tokens"] =
                        $submissionTokens;
                }
                if (!$submissionAccepted) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Este cadastro já foi enviado. Confira a lista antes de tentar novamente.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("procedures");
                }
                \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.06.page_procedures.05', [
                        $cid,
                        $title,
                        $category,
                        $description,
                        $duration,
                        $price,
                        $payments,
                        $pre,
                        $post,
                        $uid,
                    ], []);
                $id = \Prontoo\Runtime\Operational\OperationalComposition::documents()->lastInsertId();
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("procedimento_criado", "procedimento", $id, [
                    "titulo" => $title,
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Procedimento cadastrado.");
            }
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("procedures");
        }
        $isCreate =
            isset($_GET["new"]) || (string) ($_GET["mode"] ?? "") === "create";
        $editId = (int) ($_GET["edit"] ?? 0);
        if ($isCreate || $editId > 0) {
            $row = [
                "id" => 0,
                "title" => "",
                "category" => "",
                "description" => "",
                "duration_minutes" => 30,
                "price_cents" => 0,
                "payment_methods" => "",
                "pre_instructions" => "",
                "post_care" => "",
                "active" => 1,
            ];
            if ($editId > 0) {
                $found = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.06.page_procedures.06', [$editId, $cid], []);
                if (!$found) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Procedimento não encontrado.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("procedures");
                }
                $row = $found;
            }
            $isEdit = $editId > 0;
            $submissionToken = "";
            if (!$isEdit) {
                $submissionTokens =
                    $_SESSION["procedure_create_submission_tokens"] ?? [];
                if (!is_array($submissionTokens)) {
                    $submissionTokens = [];
                }
                $submissionCutoff = time() - 1800;
                foreach ($submissionTokens as $token => $issuedAt) {
                    if ((int) $issuedAt < $submissionCutoff) {
                        unset($submissionTokens[$token]);
                    }
                }
                if (count($submissionTokens) >= 8) {
                    $submissionTokens = array_slice(
                        $submissionTokens,
                        -7,
                        null,
                        true,
                    );
                }
                $submissionToken = bin2hex(random_bytes(24));
                $submissionTokens[$submissionToken] = time();
                $_SESSION["procedure_create_submission_tokens"] =
                    $submissionTokens;
            }
            $title = $isEdit ? "Editar procedimento" : "Novo procedimento";
            $subtitle = $isEdit
                ? "Revise duração, valor, formas de pagamento e orientações usadas pela Agenda."
                : "Cadastre um atendimento para ser usado em agendamentos, orientações e financeiro.";
            $cancel = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("procedures");
            $priceValue =
                (int) ($row["price_cents"] ?? 0) > 0
                    ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $row["price_cents"])
                    : "";
            $summary =
                '<div class="agenda-quick-summary procedure-route-summary" aria-label="Resumo do procedimento"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($isEdit ? "edit_note" : "playlist_add") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($isEdit ? "Edição" : "Cadastro") .
                "</b><small>Modo</small></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("timer") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($row["duration_minutes"] ?? 30)) .
                " min</b><small>Duração</small></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($priceValue !== "" ? $priceValue : "Sem valor") .
                "</b><small>Valor</small></span></div>";
            $hero =
                '<div class="agenda-quick-hero procedure-route-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("medical_services") .
                '</span><div class="agenda-quick-hero-copy"><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                "</h2><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($subtitle) .
                '</p></div><a class="ghost small agenda-quick-back" href="' .
                $cancel .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Procedimentos</span></a></div>";
            $form =
                '<section class="form-panel agenda-route-form agenda-quick-form-panel procedure-route-form-panel">' .
                $hero .
                $summary .
                '<form method="post" class="compact agenda-create-form agenda-quick-form procedure-route-form"' .
                (!$isEdit ? " data-submit-once" : "") .
                ">" .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="save"><input type="hidden" name="id" value="' .
                (int) ($row["id"] ?? 0) .
                '">' .
                (!$isEdit
                    ? '<input type="hidden" name="procedure_submission_token" value="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($submissionToken) .
                        '">'
                    : "") .
                '<fieldset class="agenda-quick-section agenda-quick-main"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_note") .
                '<span>Identificação</span></legend><div class="agenda-quick-grid">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Nome do procedimento",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "title",
                        "text",
                        $row["title"] ?? "",
                        'required maxlength="160" placeholder="Ex.: Consulta inicial, retorno, exame..." autocomplete="off"',
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Categoria",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "category",
                        "text",
                        $row["category"] ?? "",
                        'maxlength="80" placeholder="Consulta, exame, evento..." autocomplete="off"',
                    ),
                ) .
                '</div></fieldset><fieldset class="agenda-quick-section agenda-quick-time"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("timer") .
                '<span>Duração e valor</span></legend><div class="agenda-quick-time-grid">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Duração média",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "duration_minutes",
                        "number",
                        (string) ($row["duration_minutes"] ?? 30),
                        'required min="5" max="600" step="5" inputmode="numeric"',
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Valor",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "price",
                        "text",
                        $priceValue,
                        'inputmode="decimal" placeholder="R$ 0,00"',
                    ),
                ) .
                "</div>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Formas de pagamento disponíveis",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "payment_methods",
                        "text",
                        $row["payment_methods"] ?? "",
                        'placeholder="Pix, dinheiro, cartão, convênio..."',
                    ),
                ) .
                '</fieldset><fieldset class="agenda-quick-section agenda-quick-notes"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("notes") .
                "<span>Orientações</span></legend>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "O que será feito",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                        "description",
                        $row["description"] ?? "",
                        'rows="3" placeholder="Resumo claro para orientar recepção, profissional e financeiro"',
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Pré-preparativos",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                        "pre_instructions",
                        $row["pre_instructions"] ?? "",
                        'rows="3" placeholder="Orientações antes do atendimento"',
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Cuidados pós-procedimento",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                        "post_care",
                        $row["post_care"] ?? "",
                        'rows="3" placeholder="Orientações após o procedimento"',
                    ),
                ) .
                '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
                $cancel .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                '<span>Desistir</span></a><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
                "<span>Salvar procedimento</span></button></div></form></section>";
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                $title,
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                    $title,
                    $subtitle,
                    '<a class="ghost small" href="' .
                        $cancel .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_view_day") .
                        "<span>Lista</span></a>",
                ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                        $form,
                        "agenda-route-card agenda-quick-route-card procedure-route-card",
                    ),
            );
            return;
        }
        $allRows = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_options($cid, false);
        $search = mb_trim((string) ($_GET["q"] ?? ""));
        $rows = $allRows;
        if ($search !== "") {
            $needle = mb_strtolower($search);
            $rows = array_values(
                array_filter($allRows, function (array $r) use ($needle): bool {
    
                    $hay = mb_strtolower(
                        trim(
                            (string) ($r["title"] ?? "") .
                                " " .
                                (string) ($r["category"] ?? "") .
                                " " .
                                (string) ($r["description"] ?? "") .
                                " " .
                                (string) ($r["payment_methods"] ?? ""),
                        ),
                    );
                    return $needle === "" || str_contains($hay, $needle);
                }),
            );
        }
        $active = 0;
        $inactive = 0;
        $durationSum = 0;
        $durationCount = 0;
        $priced = 0;
        foreach ($allRows as $r) {
            if ((int) $r["active"] === 1) {
                $active++;
            } else {
                $inactive++;
            }
            $durationSum += (int) $r["duration_minutes"];
            $durationCount++;
            if ((int) $r["price_cents"] > 0) {
                $priced++;
            }
        }
        $avgDuration =
            $durationCount > 0 ? (int) round($durationSum / $durationCount, 0, \RoundingMode::HalfAwayFromZero) : 0;
        $stats =
            '<div class="kpis procedure-kpis procedure-kpis-refined procedure-ds-kpis"><div>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
            "<p><b>" .
            (int) $active .
            "</b><span>ativos na Agenda</span></p></div><div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("inventory_2") .
            "<p><b>" .
            (int) count($allRows) .
            "</b><span>cadastrados</span></p></div><div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
            "<p><b>" .
            (int) $priced .
            "</b><span>com valor</span></p></div><div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("timer") .
            "<p><b>" .
            ($avgDuration > 0 ? (int) $avgDuration . " min" : "—") .
            "</b><span>duração média</span></p></div></div>";
        $clearHref = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("procedures");
        $searchCard =
            '<section class="card patient-search-card ds-search-card procedures-search-card"><form method="get" class="patient-search-bar procedure-search-bar" role="search"><input type="hidden" name="r" value="procedures"><label class="search-field"><input type="search" name="q" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($search) .
            '" placeholder="Buscar por nome, categoria ou forma de pagamento" aria-label="Buscar procedimentos"></label><button class="primary small" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("search") .
            "<span>Busca rápida</span></button>" .
            ($search !== ""
                ? '<a class="ghost small" href="' .
                    $clearHref .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                    "<span>Limpar</span></a>"
                : "") .
            "</form></section>";
        $cards = "";
        foreach ($rows as $r) {
            $id = (int) $r["id"];
            $isActive = (int) $r["active"] === 1;
            $status = $isActive ? "Ativo" : "Inativo";
            $category = mb_trim((string) ($r["category"] ?? ""));
            $payments = mb_trim((string) ($r["payment_methods"] ?? ""));
            $priceLabel =
                (int) $r["price_cents"] > 0
                    ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["price_cents"])
                    : "Sem valor";
            $durationLabel = ((int) $r["duration_minutes"]) . " min";
            $categoryLabel = $category !== "" ? $category : "Sem categoria";
            $paymentLabel = $payments !== "" ? $payments : "Sem forma definida";
            $statusClass = $isActive ? "ok" : "warn";
            $edit =
                '<a class="ghost small procedure-edit-link" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("procedures", ["edit" => $id]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit") .
                "<span>Editar</span></a>";
            $toggle =
                '<form method="post" class="inline procedure-toggle">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="' .
                $id .
                '"><button class="ghost small" type="submit" aria-label="' .
                ($isActive ? "Desativar procedimento" : "Ativar procedimento") .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($isActive ? "toggle_on" : "toggle_off") .
                "<span>" .
                ($isActive ? "Desativar" : "Ativar") .
                "</span></button></form>";
            $cards .=
                '<article class="procedure-row ds-person-row ' .
                ($isActive ? "patient-status-ok" : "patient-status-warn") .
                '"><span class="ds-person-avatar" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("medical_services") .
                '</span><div class="ds-person-main"><div class="ds-person-title"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["title"]) .
                '</strong><span class="ds-status-pill ' .
                $statusClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status) .
                '</span><span class="procedure-category-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($categoryLabel) .
                '</span></div><div class="ds-person-meta procedure-row-meta"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("timer") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($durationLabel) .
                "</b></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($priceLabel) .
                "</b></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("credit_card") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($paymentLabel) .
                '</b></span></div></div><div class="ds-person-actions procedure-card-actions">' .
                $edit .
                $toggle .
                "</div></article>";
        }
        if ($cards === "") {
            $emptyText =
                $search !== ""
                    ? "Nenhum procedimento encontrado para esta busca."
                    : "Nenhum procedimento cadastrado.";
            $emptyHelp =
                $search !== ""
                    ? "Revise o termo pesquisado ou limpe a busca para voltar à lista completa."
                    : "Cadastre os tipos de atendimento para orientar a Agenda, estimar duração e padronizar orientações.";
            $cards =
                '<div class="empty procedure-empty"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("medical_services") .
                "</span><strong>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($emptyText) .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($emptyHelp) .
                "</small>" .
                ($search !== ""
                    ? '<a class="ghost small" href="' .
                        $clearHref .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                        "<span>Limpar busca</span></a>"
                    : '<a class="primary small" href="' .
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("procedures", ["new" => "1"]) .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("add") .
                        "<span>Novo procedimento</span></a>") .
                "</div>";
        }
        $foundChip =
            $search !== ""
                ? '<span class="task-chip info">' .
                    (int) count($rows) .
                    " encontrados</span>"
                : '<span class="task-chip info">' .
                    (int) $active .
                    " ativos</span>";
        $listHead =
            '<div class="procedure-list-head"><div><h2>Procedimentos cadastrados</h2><p>Lista compacta para manter Agenda, Recepção e Financeiro alinhados.</p></div>' .
            $foundChip .
            "</div>";
        $action =
            '<a class="primary small" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("procedures", ["new" => "1"]) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("add") .
            "<span>Novo procedimento</span></a>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Procedimentos",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Procedimentos",
                "Tipos de atendimento, consultas e eventos usados pela Recepção ao agendar.",
                $action,
            ) .
                '<div class="procedures-ds-screen patient-directory-screen procedure-directory-screen">' .
                $stats .
                $searchCard .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    $listHead .
                        '<div class="procedure-list ds-person-list">' .
                        $cards .
                        "</div>",
                    "procedure-list-card procedure-list-card-refined patient-list-card patient-directory-card ds-filter-list-block procedures-ds-list",
                ) .
                "</div>",
        );
    
    }
}
