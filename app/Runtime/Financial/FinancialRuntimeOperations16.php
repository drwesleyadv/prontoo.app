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
    
        $c = require_can("patients");
        if (!has_effective_role($c, "gerente")) {
            throw new ProntooHttpError(
                403,
                "Credores são exclusivos do ambiente Administrador.",
            );
        }
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        financial_operational_schema_ready();
        person_common_profile_schema_ready();
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "creditor_save");
            try {
                if ($act === "creditor_save") {
                    financial_creditor_upsert_from_post($cid, $uid);
                    flash("Credor salvo.");
                    redirect("creditors");
                }
                if ($act === "creditor_deactivate") {
                    $id = (int) ($_POST["creditor_id"] ?? 0);
                    q(
                        "UPDATE pi_financial_counterparties SET active=0,updated_at=NOW() WHERE id=? AND clinic_id=? AND kind='credor'",
                        [$id, $cid],
                    );
                    audit("credor_desativado", "pessoa", $id, [
                        "audit_body" => "Credor desativado no contexto Pessoas.",
                    ]);
                    flash("Credor desativado.");
                    redirect("creditors");
                }
            } catch (Throwable $e) {
                error_log("[Prontoo credores] " . $e->getMessage());
                flash(
                    app_public_error_message(
                        $e,
                        "Não foi possível concluir a operação financeira.",
                    ),
                    "bad",
                );
                redirect("creditors");
            }
        }
        $search = mb_trim((string) ($_GET["q"] ?? ""));
        $newMode = isset($_GET["new"]) && (string) $_GET["new"] !== "0";
        $formActions =
            '<div class="form-actions"><a class="ghost" href="' .
            href("creditors") .
            '">' .
            icon("close") .
            '<span>Cancelar</span></a><button type="submit" class="primary">' .
            icon(form_submit_icon("Salvar credor")) .
            "<span>Salvar credor</span></button></div>";
        $form =
            '<form method="post" class="compact creditor-form patient-cpf-first-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="creditor_save"><div class="two">' .
            form_row(
                "Nome/Razão social do Credor",
                input("creditor_name", "text", "", 'required autocomplete="name"'),
            ) .
            select_label(
                "Identificação",
                "creditor_legal_type",
                ["cpf" => "CPF", "cnpj" => "CNPJ"],
                "cpf",
                "required",
            ) .
            '</div><div class="two">' .
            form_row(
                "CPF/CNPJ",
                input(
                    "creditor_legal_document",
                    "text",
                    "",
                    'required inputmode="numeric" data-doc-mask data-document-validate',
                ),
            ) .
            form_row(
                "Nascimento (se pessoa física)",
                input("creditor_birth_date", "date", ""),
            ) .
            "</div>" .
            person_common_profile_fields_html($cid, [], "creditor_") .
            form_row("Observações", textarea("creditor_notes", "", 'rows="3"')) .
            $formActions .
            "</form>";
        if ($newMode) {
            page(
                "Novo Credor",
                page_head("Novo Credor", "") .
                    card(
                        '<h2>Novo Credor</h2><p class="muted">Cadastre pessoas ou empresas que podem receber pagamentos administrativos do Consultório.</p>' .
                            $form,
                        "patient-new-screen-card creditor-new-card",
                    ),
            );
            return;
        }
        $activeCount =
            (int) (val(
                "SELECT COUNT(*) FROM pi_financial_counterparties WHERE clinic_id=? AND kind='credor' AND active=1",
                [$cid],
            ) ?? 0);
        $withDocCount =
            (int) (val(
                "SELECT COUNT(*) FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.kind='credor' AND fc.active=1 AND COALESCE(NULLIF(p.legal_document,''),NULLIF(p.cpf,''),'')<>''",
                [$cid],
            ) ?? 0);
        $withContactCount =
            (int) (val(
                "SELECT COUNT(*) FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.kind='credor' AND fc.active=1 AND (COALESCE(p.phone,'')<>'' OR COALESCE(p.email,'')<>'')",
                [$cid],
            ) ?? 0);
        $paid30Cents =
            (int) (val(
                "SELECT COALESCE(SUM(e.amount_cents),0) FROM pi_financial_expenses e JOIN pi_financial_counterparties fc ON fc.id=e.counterparty_id AND fc.clinic_id=e.clinic_id WHERE e.clinic_id=? AND fc.kind='credor' AND fc.active=1 AND e.status='paga' AND e.paid_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$cid],
            ) ?? 0);
        $statHtml =
            '<section class="patient-directory-overview kpis kpi-info-strip creditor-directory-overview" aria-label="Resumo de credores"><div class="patient-kpi-card kpi-card ' .
            ($activeCount > 0 ? "is-total" : "is-muted") .
            '">' .
            icon("receipt_long") .
            "<p><b>" .
            number_format($activeCount, 0, ",", ".") .
            '</b><span>Credores ativos</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($withDocCount === $activeCount && $activeCount > 0
                ? "is-ok"
                : "is-warn warn") .
            '">' .
            icon("badge") .
            "<p><b>" .
            number_format($withDocCount, 0, ",", ".") .
            '</b><span>Com CPF/CNPJ</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($withContactCount === $activeCount && $activeCount > 0
                ? "is-ok"
                : "is-warn warn") .
            '">' .
            icon("contact_phone") .
            "<p><b>" .
            number_format($withContactCount, 0, ",", ".") .
            '</b><span>Com contato</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($paid30Cents > 0 ? "is-ok" : "is-muted") .
            '">' .
            icon("payments") .
            "<p><b>" .
            e(money_br($paid30Cents)) .
            "</b><span>Pago em 30 dias</span></p></div></section>";
        $params = [$cid];
        $where = "fc.clinic_id=? AND fc.kind='credor' AND fc.active=1";
        if ($search !== "") {
            $like = "%" . $search . "%";
            $digits = only_digits($search);
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
        $rows = q(
            "SELECT fc.id,fc.notes,p.*,(SELECT COALESCE(SUM(e.amount_cents),0) FROM pi_financial_expenses e WHERE e.clinic_id=fc.clinic_id AND e.counterparty_id=fc.id AND e.status='paga') paid_total_cents,(SELECT MAX(e.paid_at) FROM pi_financial_expenses e WHERE e.clinic_id=fc.clinic_id AND e.counterparty_id=fc.id AND e.status='paga') last_paid_at,(SELECT COUNT(*) FROM pi_financial_expenses e WHERE e.clinic_id=fc.clinic_id AND e.counterparty_id=fc.id AND e.status='prevista' AND e.due_at<CURDATE()) overdue_expenses FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE $where ORDER BY p.full_name ASC LIMIT 160",
            $params,
        )->fetchAll();
        $rowsHtml = "";
        foreach ($rows as $r) {
            $rowsHtml .= financial_creditor_directory_card($r, $cid);
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
                    icon("manage_search") .
                    "<strong>" .
                    $empty .
                    "</strong><span>Use a busca ou cadastre um novo credor para registrar pagamentos administrativos com mais segurança.</span></div>";
        $clear =
            $search !== ""
                ? '<a class="ghost small" href="' .
                    href("creditors") .
                    '">' .
                    icon("close") .
                    "<span>Limpar</span></a>"
                : "";
        $searchBar =
            '<section class="patient-directory-search ds-search-block creditor-directory-search"><form method="get" class="patient-search-bar creditor-search-bar" role="search"><input type="hidden" name="r" value="creditors"><label class="search-field"><input name="q" type="search" value="' .
            e($search) .
            '" placeholder="Nome, CPF/CNPJ, telefone, e-mail ou cidade" autocomplete="off" aria-label="Buscar credor por nome, CPF/CNPJ, telefone, e-mail ou cidade"></label><button class="primary small" type="submit">' .
            icon("search") .
            "<span>Busca rápida</span></button>" .
            $clear .
            "</form></section>";
        $filterLabel =
            $search !== ""
                ? '<span class="patient-filter-chip active is-active patient-filter-found" aria-current="page">' .
                    icon("manage_search") .
                    "<span>Encontrados</span><small>" .
                    number_format(count($rows), 0, ",", ".") .
                    "</small></span>"
                : '<span class="patient-filter-chip active is-active" aria-current="page">' .
                    icon("receipt_long") .
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
            href("creditors", ["new" => 1]) .
            '">' .
            action_summary_label("Novo Credor", "person_add") .
            "</a>";
        page(
            "Credores",
            page_head(
                "Credores",
                "Pessoas ou empresas que podem receber pagamentos administrativos do Consultório.",
                $headAction,
            ) .
                $statHtml .
                card(
                    $searchBar,
                    "patient-search-card ds-search-card creditor-search-card",
                ) .
                card(
                    $filterList,
                    "patient-list-card patient-directory-card creditor-directory-card ds-filter-list-block",
                ),
        );
    
    }

    public static function financial_admin_attention_panel(int $cid): string
    
    {
    
        financial_operational_schema_ready();
        $today = financial_today($cid);
        [$dayStart, $dayEnd] = app_local_day_utc_range($today, $cid);
        $items = "";
        $pending = (int) safe_val(
            "SELECT COUNT(*) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu'))",
            [$cid, $dayEnd],
            0,
        );
        $closure = financial_daily_drawer_closure_state($cid, $today);
        $openDrawers = (int) $closure["blocking_count"];
        $reviews = (int) safe_val(
            "SELECT COUNT(*) FROM pi_cash_sessions WHERE clinic_id=? AND status IN ('closed_pending_review','opening_pending_review')",
            [$cid],
            0,
        );
        $diffs = (int) safe_val(
            "SELECT COALESCE(SUM(ABS(difference_cents)),0) FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND difference_cents<>0",
            [$cid, $today],
            0,
        );
        if ($pending > 0) {
            $items .=
                '<a class="finance-attention-item" href="' .
                href("financial", ["tab" => "conferencias"]) .
                '">' .
                icon("pending_actions") .
                "<span><b>" .
                n($pending) .
                " atendimento(s) ainda pendente(s) de pagamento</b><small>Conclua recebimentos antes de fechar o dia.</small></span></a>";
        }
        if ($openDrawers > 0) {
            $items .=
                '<a class="finance-attention-item" href="' .
                href("financial", ["tab" => "locais"]) .
                '">' .
                icon("point_of_sale") .
                "<span><b>" .
                n($openDrawers) .
                " gaveta(s) aberta(s)</b><small>Confira se a Recepção ainda precisa fechar a Gaveta.</small></span></a>";
        }
        if ($reviews > 0) {
            $items .=
                '<a class="finance-attention-item" href="' .
                href("financial", ["tab" => "conferencias"]) .
                '">' .
                icon("fact_check") .
                "<span><b>" .
                n($reviews) .
                " conferência(s) aguardando</b><small>Autorizações e fechamentos ficam concentrados em Conferências.</small></span></a>";
        }
        if ($diffs > 0) {
            $items .=
                '<a class="finance-attention-item is-danger" href="' .
                href("financial", ["tab" => "conferencias"]) .
                '">' .
                icon("difference") .
                "<span><b>" .
                money_br($diffs) .
                " em divergências de gaveta hoje</b><small>Revise sobras ou faltas antes da consolidação.</small></span></a>";
        }
        if ($items === "") {
            $items =
                '<div class="empty">Nada exige atenção financeira neste momento.</div>';
        }
        return card(
            "<h2>" .
                icon("notifications_active") .
                '<span>Precisa de atenção</span></h2><p class="muted">O Prontoo destaca apenas o que pode atrapalhar o fechamento do dia.</p><div class="finance-attention-list">' .
                $items .
                "</div>",
            "finance-attention-card",
        );
    
    }

    public static function financial_admin_conferences_panel(int $cid, int $uid): string
    
    {
    
        $pending = card(
            "<h2>" .
                icon("pending_actions") .
                '<span>Pendências de recebimento</span></h2><p class="muted">Atendimentos com valor previsto ainda não recebido. A Recepção recebe pela Gaveta; o Administrador recebe pela tela Operações, sempre vinculando a pendência quando ela existir.</p>' .
                financial_cashier_pending_receipts_html($cid),
            "finance-list finance-conference-pending-card",
        );
        return '<div class="finance-workspace finance-conferences-workspace">' .
            $pending .
            financial_admin_reviews_panel($cid, $uid) .
            "</div>";
    
    }
}
