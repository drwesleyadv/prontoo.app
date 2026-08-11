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

final class FinancialRuntimeOperations14
{
    private function __construct()
    {
    }

    public static function financial_admin_daily_conference_panel(int $cid, int $uid): string
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        $state = \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_consolidation_state($cid, $today);
        $metrics = \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_metrics($cid, $today);
        $expected = (int) $metrics["expected_cents"];
        $received = (int) $metrics["received_cents"];
        $pending = (int) $metrics["pending_cents"];
        $movements = (int) $metrics["movement_count"];
        $diffs = (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->safeScalar("financial.14.admin_daily_conference_panel.01", [$cid, $today], 0, []);
        $pos = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_global_position($cid);
        $summary =
            '<div class="finance-conference-summary"><article><span>Previsto</span><b>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected) .
            "</b></article><article><span>Recebido</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($received) .
            "</b></article><article><span>Pendente</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($pending) .
            "</b></article><article><span>Movimentos</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($movements) .
            "</b></article></div>";
        $where =
            '<div class="finance-conference-summary finance-conference-places"><article><span>Gavetas</span><b>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $pos["pos_cents"]) .
            "</b></article><article><span>Cofre</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $pos["safe_cents"]) .
            "</b></article><article><span>Bancos</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $pos["bank_cents"]) .
            "</b></article><article><span>Divergências</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($diffs) .
            "</b></article></div>";
        $partials = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            "<h2>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("point_of_sale") .
                '<span>Parciais por Colaborador</span></h2><p class="muted">A conferência diária considera cada colaborador vinculado à Gaveta. Movimentos da Recepção só se tornam definitivos após confirmação do Administrativo.</p>' .
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_daily_drawer_partials_html($cid, $uid, (array) $state),
            "finance-list finance-drawer-partials-card",
        );
        if (!empty($state["consolidated"])) {
            $action =
                '<div class="finance-conference-lock"><span class="pill ok">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified") .
                'Dia já consolidado</span><p class="muted">Novos lançamentos do dia ficam bloqueados para preservar a conferência.</p><a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "painel"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Voltar ao Painel</span></a></div>";
        } elseif (empty($state["conference_released"])) {
            $action =
                '<div class="finance-conference-lock"><span class="pill warn">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("point_of_sale") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n((int) ($state["pending_open_drawers"] ?? 0)) .
                ' colaborador(es) ainda sem fechamento</span><p class="muted">A conferência só é liberada quando todos os colaboradores vinculados à mesma Gaveta tiverem fechado ou mantido a Gaveta fechada no expediente.</p>' .
                \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_daily_drawer_closure_blocking_html(
                    (array) $state["closure"],
                ) .
                '<a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "locais"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Ver locais</span></a></div>";
        } elseif (empty($state["can_consolidate"])) {
            $action =
                '<div class="finance-conference-lock"><span class="pill warn">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("fact_check") .
                'Conferência liberada</span><p class="muted">Revise as parciais por colaborador. Confirme o que estiver correto ou devolva com justificativa para correção antes da consolidação definitiva.</p>' .
                \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_daily_consolidation_blockers_html($state) .
                "</div>";
        } else {
            $action =
                '<form method="post" class="finance-conference-action">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="daily_consolidate"><button class="primary" type="submit">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified") .
                '<span>Confirmar consolidação definitiva</span></button><a class="ghost" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "painel"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Voltar ao Painel</span></a></form>";
        }
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            "<h2>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("fact_check") .
                "<span>Resumo do dia</span></h2>" .
                $summary .
                $where .
                $action,
            "finance-report-card finance-daily-conference-card",
        ) .
            $partials .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Movimentos do dia</h2><p class="muted">Revise entradas, saídas e transferências antes de confirmar a consolidação.</p>' .
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations12::financial_admin_daily_ledger_timeline($cid),
                "finance-ledger-card",
            );
    
    }

    public static function financial_admin_daily_consolidate(int $cid, int $uid): void
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->ensureDailyClosingSchema();
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->atomic(function () use ($cid, $uid): void {
    
            $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.14.admin_daily_consolidate.01", [$cid], []);
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.14.admin_daily_consolidate.02", [$cid, $today], []);
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_reconciliation($cid, $today, $uid, true);
            $state = \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_consolidation_state($cid, $today);
            if (!empty($state["consolidated"])) {
                throw new RuntimeException(
                    "Este dia financeiro já foi consolidado.",
                );
            }
            if (empty($state["conference_released"])) {
                throw new RuntimeException(
                    "A conferência diária só é liberada quando todas as gavetas abertas hoje, por todos os colaboradores, estiverem fechadas.",
                );
            }
            if (empty($state["can_consolidate"])) {
                throw new RuntimeException(
                    "Ainda existem conferências, devoluções ou movimentos pendentes. Resolva tudo antes de consolidar o dia.",
                );
            }
            $reconciliation = (array) ($state["reconciliation"] ?? []);
            if (empty($reconciliation["ok"])) {
                throw new RuntimeException(
                    "A reconciliação matemática do dia não foi confirmada.",
                );
            }
            $metrics = (array) $reconciliation["metrics"];
            $expected = (int) $metrics["expected_cents"];
            $received = (int) $metrics["received_cents"];
            $pending = (int) $metrics["pending_cents"];
            $movements = (int) $metrics["movement_count"];
            $pos = (array) $reconciliation["position"];
            $integrityNote =
                "integrity:financial-reconciliation-v2:" .
                (string) $reconciliation["hash"];
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.14.admin_daily_consolidate.03", [
                    $cid,
                    $today,
                    "consolidado",
                    $expected,
                    $received,
                    $pending,
                    (int) $pos["pos_cents"],
                    (int) $pos["safe_cents"],
                    (int) $pos["bank_cents"],
                    $movements,
                    (int) ($state["closure"]["opened_count"] ?? 0),
                    $integrityNote,
                    $uid,
                ], []);
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("financeiro_dia_consolidado", "financeiro", $cid, [
                "business_date" => $today,
                "opened_drawers_checked" =>
                    (int) ($state["closure"]["opened_count"] ?? 0),
                "movimentos" => $movements,
                "reconciliation_hash" => (string) $reconciliation["hash"],
                "audit_body" =>
                    "Administrador conferiu e consolidou os lançamentos financeiros do dia após fechamento e revisão das gavetas abertas.",
            ]);
        });
    
    }

    public static function financial_admin_save_receipt(int $cid, int $uid): void
    
    {
    
        $patientId = is_callable([\Prontoo\Runtime\Patients\PatientsRuntimeOperations02::class, 'resolve_patient_lookup_id'])
            ? \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::resolve_patient_lookup_id(
                $cid,
                (int) ($_POST["patient_link_id"] ?? 0),
                is_callable([\Prontoo\Presentation\Patients\PatientsPresentationOperations01::class, 'posted_patient_search_value'])
                    ? \Prontoo\Presentation\Patients\PatientsPresentationOperations01::posted_patient_search_value()
                    : "",
            )
            : 0;
        if ($patientId <= 0) {
            throw new RuntimeException(
                "Todo recebimento precisa estar vinculado a um Paciente devedor.",
            );
        }
        $revenueId = (int) ($_POST["expected_revenue_id"] ?? 0);
        $method =
            \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method((string) ($_POST["payment_method"] ?? "")) ?:
            "pix";
        $to = (int) ($_POST["to_location_id"] ?? 0);
        if ($to <= 0 || !\Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_admin_location_belongs($cid, $to)) {
            throw new RuntimeException(
                "Recebimento pelo Administrador deve entrar em Cofre ou Banco do Consultório. Gaveta pertence ao fluxo da Recepção.",
            );
        }
        if ($revenueId > 0) {
            if (
                !\Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_pending_revenue_belongs_to_patient(
                    $cid,
                    $revenueId,
                    $patientId,
                )
            ) {
                throw new RuntimeException(
                    "A pendência selecionada não pertence ao Paciente informado ou já foi recebida.",
                );
            }
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_admin_receive_expected_revenue(
                $cid,
                $uid,
                $revenueId,
                $method,
                $to,
                mb_trim((string) ($_POST["notes"] ?? "")),
            );
            return;
        }
        $pending = \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_patient_pending_revenue_count($cid, $patientId);
        $avulso =
            isset($_POST["receipt_without_appointment"]) &&
            (string) ($_POST["receipt_without_appointment"] ?? "") === "1";
        $notes = mb_trim((string) ($_POST["notes"] ?? ""));
        if (!$avulso) {
            throw new RuntimeException(
                "Selecione uma pendência de agendamento ou marque recebimento sem agendamento vinculado com motivo.",
            );
        }
        if ($pending > 0 && !$avulso) {
            throw new RuntimeException(
                "Este Paciente possui pendência de agendamento. Selecione a pendência ou marque recebimento sem agendamento vinculado com motivo.",
            );
        }
        if ($avulso && $notes === "") {
            throw new RuntimeException(
                "Informe o motivo do recebimento sem agendamento vinculado.",
            );
        }
        $amount = \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents((string) ($_POST["amount"] ?? "0"));
        if ($amount <= 0) {
            throw new RuntimeException("Informe o valor recebido.");
        }
        $title = trim(
            (string) ($_POST["title"] ?? "Recebimento sem agendamento vinculado"),
        );
        if ($title === "") {
            $title = "Recebimento sem agendamento vinculado";
        }
        $accountId = \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_location_account_id($cid, $to);
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.14.admin_save_receipt.01", [
                $cid,
                $patientId,
                $title,
                $amount,
                "efetivada",
                $method,
                $accountId,
                date("Y-m-d H:i:s"),
                $uid,
            ], []);
        $rid = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_create_movement(
            $cid,
            "receipt",
            $amount,
            null,
            $to,
            null,
            $uid,
            $title,
            $method,
            $notes,
            "confirmed",
            "financial_revenue",
            $rid,
        );
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("recebimento_administrativo_avulso", "financeiro", $rid, [
            "patient_link_id" => $patientId,
            "valor" => $amount,
            "audit_body" =>
                "Administrador registrou recebimento sem agendamento vinculado, com motivo obrigatório quando havia pendências do paciente.",
        ]);
    
    }

    public static function financial_admin_save_payment(int $cid, int $uid): void
    
    {
    
        $creditorId = (int) ($_POST["counterparty_id"] ?? 0);
        $cred =
            $creditorId > 0
                ? \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.14.admin_save_payment.01", [$creditorId, $cid], [])
                : null;
        if (!$cred) {
            throw new RuntimeException("Escolha um Credor cadastrado em Pessoas.");
        }
        $from = (int) ($_POST["from_location_id"] ?? 0);
        if ($from <= 0 || !\Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_admin_location_belongs($cid, $from)) {
            throw new RuntimeException(
                "Pagamento pelo Administrador deve sair de Cofre ou Banco do Consultório. Gaveta pertence ao fluxo da Recepção/Caixa.",
            );
        }
        $amount = \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents((string) ($_POST["amount"] ?? "0"));
        if ($amount <= 0) {
            throw new RuntimeException("Informe o valor da despesa.");
        }
        $method =
            \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method((string) ($_POST["payment_method"] ?? "")) ?:
            "pix";
        $category = (string) ($_POST["expense_category"] ?? "outras");
        if (!array_key_exists($category, \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_expense_category_options())) {
            $category = "outras";
        }
        $title = mb_trim((string) ($_POST["title"] ?? "Pagamento ao credor"));
        if ($title === "") {
            $title = "Pagamento ao credor";
        }
        $notes = mb_trim((string) ($_POST["notes"] ?? ""));
        $accountId = \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_location_account_id($cid, $from);
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.14.admin_save_payment.02", [
                $cid,
                $creditorId,
                $accountId,
                $title,
                $category,
                $amount,
                $method,
                $notes ?: null,
                $uid,
            ], []);
        $eid = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_create_movement(
            $cid,
            "payment",
            $amount,
            $from,
            null,
            null,
            $uid,
            $title . " · " . (string) $cred["full_name"],
            $method,
            $notes,
            "confirmed",
            "financial_expense",
            $eid,
        );
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("pagamento_administrativo", "financeiro", $eid, [
            "counterparty_id" => $creditorId,
            "valor" => $amount,
            "from_location_id" => $from,
            "categoria" => $category,
            "audit_body" =>
                "Administrador registrou pagamento de despesa informando Credor e Cofre/Banco de saída.",
        ]);
    
    }

    public static function financial_admin_save_transfer(int $cid, int $uid): void
    
    {
    
        $from = (int) ($_POST["from_location_id"] ?? 0);
        $to = (int) ($_POST["to_location_id"] ?? 0);
        if ($from <= 0 || !\Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_admin_location_belongs($cid, $from)) {
            throw new RuntimeException(
                "Transferência administrativa deve sair de Cofre ou Banco. Gaveta é movimentada pelo fluxo de Caixa/fechamento.",
            );
        }
        if ($to <= 0 || !\Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_admin_location_belongs($cid, $to)) {
            throw new RuntimeException(
                "Transferência administrativa deve ir para Cofre ou Banco. Gaveta é movimentada pelo fluxo de Caixa/fechamento.",
            );
        }
        if ($from === $to) {
            throw new RuntimeException("Origem e destino precisam ser diferentes.");
        }
        $amount = \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents((string) ($_POST["amount"] ?? "0"));
        if ($amount <= 0) {
            throw new RuntimeException("Informe o valor da transferência.");
        }
        $title = mb_trim((string) ($_POST["title"] ?? "Transferência entre locais"));
        if ($title === "") {
            $title = "Transferência entre locais";
        }
        $notes = mb_trim((string) ($_POST["notes"] ?? ""));
        $mid = \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_create_movement(
            $cid,
            "transfer",
            $amount,
            $from,
            $to,
            null,
            $uid,
            $title,
            "transferencia",
            $notes,
            "confirmed",
            "financial_transfer",
            0,
        );
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("transferencia_financeira_administrativa", "financeiro", $mid, [
            "from_location_id" => $from,
            "to_location_id" => $to,
            "valor" => $amount,
            "audit_body" =>
                "Administrador transferiu valor apenas entre Cofre/Banco do Consultório.",
        ]);
    
    }
}
