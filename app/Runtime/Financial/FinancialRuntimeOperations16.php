<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Financial;

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

final class FinancialRuntimeOperations16
{
    private function __construct()
    {
    }

    public static function page_creditors(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("patients");
        if (!\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")) {
            throw new ProntooHttpError(
                403,
                "Credores são exclusivos do ambiente Administrador.",
            );
        }
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_schema_ready();
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "creditor_save");
            try {
                if ($act === "creditor_save") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations15::financial_creditor_upsert_from_post($cid, $uid);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Credor salvo.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("creditors");
                }
                if ($act === "creditor_deactivate") {
                    $id = (int) ($_POST["creditor_id"] ?? 0);
                    \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.16.page_creditors.01", [$id, $cid], []);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("credor_desativado", "pessoa", $id, [
                        "audit_body" => "Credor desativado no contexto Pessoas.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Credor desativado.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("creditors");
                }
            } catch (Throwable $e) {
                error_log("[Prontoo credores] " . $e->getMessage());
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                        $e,
                        "Não foi possível concluir a operação financeira.",
                    ),
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("creditors");
            }
        }
        $search = mb_trim((string) ($_GET["q"] ?? ""));
        $newMode = isset($_GET["new"]) && (string) $_GET["new"] !== "0";
        $formActions =
            '<div class="form-actions"><a class="ghost" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("creditors") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
            '<span>Cancelar</span></a><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_submit_icon("Salvar credor")) .
            "<span>Salvar credor</span></button></div>";
        $form =
            '<form method="post" class="compact creditor-form patient-cpf-first-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="creditor_save"><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome/Razão social do Credor",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("creditor_name", "text", "", 'required autocomplete="name"'),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Identificação",
                "creditor_legal_type",
                ["cpf" => "CPF", "cnpj" => "CNPJ"],
                "cpf",
                "required",
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF/CNPJ",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "creditor_legal_document",
                    "text",
                    "",
                    'required inputmode="numeric" data-doc-mask data-document-validate',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nascimento (se pessoa física)",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("creditor_birth_date", "date", ""),
            ) .
            "</div>" .
            \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::person_common_profile_fields_html($cid, [], "creditor_") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Observações", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("creditor_notes", "", 'rows="3"')) .
            $formActions .
            "</form>";
        if ($newMode) {
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Novo Credor",
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Novo Credor", "") .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                        '<h2>Novo Credor</h2><p class="muted">Cadastre pessoas ou empresas que podem receber pagamentos administrativos do Consultório.</p>' .
                            $form,
                        "patient-new-screen-card creditor-new-card",
                    ),
            );
            return;
        }
        $activeCount =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.16.page_creditors.02", [$cid], []) ?? 0);
        $withDocCount =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.16.page_creditors.03", [$cid], []) ?? 0);
        $withContactCount =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.16.page_creditors.04", [$cid], []) ?? 0);
        $paid30Cents =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.16.page_creditors.05", [$cid], []) ?? 0);
        $statHtml =
            '<section class="patient-directory-overview kpis kpi-info-strip creditor-directory-overview" aria-label="Resumo de credores"><div class="patient-kpi-card kpi-card ' .
            ($activeCount > 0 ? "is-total" : "is-muted") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("receipt_long") .
            "<p><b>" .
            number_format($activeCount, 0, ",", ".") .
            '</b><span>Credores ativos</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($withDocCount === $activeCount && $activeCount > 0
                ? "is-ok"
                : "is-warn warn") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
            "<p><b>" .
            number_format($withDocCount, 0, ",", ".") .
            '</b><span>Com CPF/CNPJ</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($withContactCount === $activeCount && $activeCount > 0
                ? "is-ok"
                : "is-warn warn") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("contact_phone") .
            "<p><b>" .
            number_format($withContactCount, 0, ",", ".") .
            '</b><span>Com contato</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($paid30Cents > 0 ? "is-ok" : "is-muted") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
            "<p><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($paid30Cents)) .
            "</b><span>Pago em 30 dias</span></p></div></section>";
        $params = [$cid];
        $where = "fc.clinic_id=? AND fc.kind='credor' AND fc.active=1";
        if ($search !== "") {
            $like = "%" . $search . "%";
            $digits = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($search);
            if ($digits !== "") {
                $where .=
                    " AND (p.full_name LIKE ? OR p.cpf LIKE ? OR p.legal_document LIKE ? OR p.phone LIKE ? OR p.email LIKE ? OR fc.notes LIKE ?)";
                array_push(
                    $params,
                    $like,
                    "%" . $digits . "%",
                    "%" . $digits . "%",
                    "%" . $digits . "%",
                    $like,
                    $like,
                );
            } else {
                $where .=
                    " AND (p.full_name LIKE ? OR p.email LIKE ? OR p.address_city LIKE ? OR fc.notes LIKE ?)";
                array_push($params, $like, $like, $like, $like);
            }
        }
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.16.page_creditors.06", $params, compact('where'))->fetchAll();
        $rowsHtml = "";
        foreach ($rows as $r) {
            $rowsHtml .= \Prontoo\Runtime\Financial\FinancialRuntimeOperations15::financial_creditor_directory_card($r, $cid);
        }
        $empty =
            $search !== ""
                ? "Nenhum credor encontrado para esta busca."
                : "Nenhum Credor cadastrado.";
        $list =
            $rowsHtml !== ""
                ? '<div class="patient-directory-list ds-person-list ds-creditor-list">' .
                    $rowsHtml .
                    "</div>"
                : '<div class="empty patient-directory-empty">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("manage_search") .
                    "<strong>" .
                    $empty .
                    "</strong><span>Use a busca ou cadastre um novo credor para registrar pagamentos administrativos com mais segurança.</span></div>";
        $clear =
            $search !== ""
                ? '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("creditors") .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                    "<span>Limpar</span></a>"
                : "";
        $searchBar =
            '<section class="patient-directory-search ds-search-block creditor-directory-search"><form method="get" class="patient-search-bar creditor-search-bar" role="search"><input type="hidden" name="r" value="creditors"><label class="search-field"><input name="q" type="search" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($search) .
            '" placeholder="Nome, CPF/CNPJ, telefone, e-mail ou cidade" autocomplete="off" aria-label="Buscar credor por nome, CPF/CNPJ, telefone, e-mail ou cidade"></label><button class="primary small" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("search") .
            "<span>Busca rápida</span></button>" .
            $clear .
            "</form></section>";
        $filterLabel =
            $search !== ""
                ? '<span class="patient-filter-chip active is-active patient-filter-found" aria-current="page">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("manage_search") .
                    "<span>Encontrados</span><small>" .
                    number_format(count($rows), 0, ",", ".") .
                    "</small></span>"
                : '<span class="patient-filter-chip active is-active" aria-current="page">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("receipt_long") .
                    "<span>Ativos</span><small>" .
                    number_format($activeCount, 0, ",", ".") .
                    "</small></span>";
        $filterList =
            '<nav class="patient-filter-chips ds-filter-list-chips" aria-label="Filtros de credores">' .
            $filterLabel .
            "</nav><div>" .
            $list .
            "</div>";
        $headAction =
            '<a class="primary small cmdlike" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("creditors", ["new" => 1]) .
            '">' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Novo Credor", "person_add") .
            "</a>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Credores",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Credores",
                "Pessoas ou empresas que podem receber pagamentos administrativos do Consultório.",
                $headAction,
            ) .
                $statHtml .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    $searchBar,
                    "patient-search-card ds-search-card creditor-search-card",
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    $filterList,
                    "patient-list-card patient-directory-card creditor-directory-card ds-filter-list-block",
                ),
        );
    
    }

    public static function financial_admin_attention_panel(int $cid): string
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        [$dayStart, $dayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($today, $cid);
        $items = "";
        $pending = (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->safeScalar("financial.16.admin_attention_panel.01", [$cid, $dayEnd], 0, []);
        $closure = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_daily_drawer_closure_state($cid, $today);
        $openDrawers = (int) $closure["blocking_count"];
        $reviews = (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->safeScalar("financial.16.admin_attention_panel.02", [$cid], 0, []);
        $diffs = (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->safeScalar("financial.16.admin_attention_panel.03", [$cid, $today], 0, []);
        if ($pending > 0) {
            $items .=
                '<a class="finance-attention-item" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "conferencias"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("pending_actions") .
                "<span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($pending) .
                " atendimento(s) ainda pendente(s) de pagamento</b><small>Conclua recebimentos antes de fechar o dia.</small></span></a>";
        }
        if ($openDrawers > 0) {
            $items .=
                '<a class="finance-attention-item" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "locais"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("point_of_sale") .
                "<span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($openDrawers) .
                " gaveta(s) aberta(s)</b><small>Confira se a Recepção ainda precisa fechar a Gaveta.</small></span></a>";
        }
        if ($reviews > 0) {
            $items .=
                '<a class="finance-attention-item" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "conferencias"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("fact_check") .
                "<span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($reviews) .
                " conferência(s) aguardando</b><small>Autorizações e fechamentos ficam concentrados em Conferências.</small></span></a>";
        }
        if ($diffs > 0) {
            $items .=
                '<a class="finance-attention-item is-danger" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "conferencias"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("difference") .
                "<span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($diffs) .
                " em divergências de gaveta hoje</b><small>Revise sobras ou faltas antes da consolidação.</small></span></a>";
        }
        if ($items === "") {
            $items =
                '<div class="empty">Nada exige atenção financeira neste momento.</div>';
        }
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            "<h2>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("notifications_active") .
                '<span>Precisa de atenção</span></h2><p class="muted">O Prontoo destaca apenas o que pode atrapalhar o fechamento do dia.</p><div class="finance-attention-list">' .
                $items .
                "</div>",
            "finance-attention-card",
        );
    
    }

    public static function financial_admin_conferences_panel(int $cid, int $uid): string
    
    {
    
        $pending = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            "<h2>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("pending_actions") .
                '<span>Pendências de recebimento</span></h2><p class="muted">Atendimentos com valor previsto ainda não recebido. A Recepção recebe pela Gaveta; o Administrador recebe pela tela Operações, sempre vinculando a pendência quando ela existir.</p>' .
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_cashier_pending_receipts_html($cid),
            "finance-list finance-conference-pending-card",
        );
        return '<div class="finance-workspace finance-conferences-workspace">' .
            $pending .
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_admin_reviews_panel($cid, $uid) .
            "</div>";
    
    }
}
