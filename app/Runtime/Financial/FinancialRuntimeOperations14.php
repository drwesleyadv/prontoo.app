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
    
        financial_operational_schema_ready();
        $today = financial_today($cid);
        $state = financial_daily_consolidation_state($cid, $today);
        $metrics = financial_daily_metrics($cid, $today);
        $expected = (int) $metrics["expected_cents"];
        $received = (int) $metrics["received_cents"];
        $pending = (int) $metrics["pending_cents"];
        $movements = (int) $metrics["movement_count"];
        $diffs = (int) safe_val(
            "SELECT COALESCE(SUM(ABS(difference_cents)),0) FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND difference_cents<>0",
            [$cid, $today],
            0,
        );
        $pos = financial_global_position($cid);
        $summary =
            '<div class="finance-conference-summary"><article><span>Previsto</span><b>' .
            money_br($expected) .
            "</b></article><article><span>Recebido</span><b>" .
            money_br($received) .
            "</b></article><article><span>Pendente</span><b>" .
            money_br($pending) .
            "</b></article><article><span>Movimentos</span><b>" .
            n($movements) .
            "</b></article></div>";
        $where =
            '<div class="finance-conference-summary finance-conference-places"><article><span>Gavetas</span><b>' .
            money_br((int) $pos["pos_cents"]) .
            "</b></article><article><span>Cofre</span><b>" .
            money_br((int) $pos["safe_cents"]) .
            "</b></article><article><span>Bancos</span><b>" .
            money_br((int) $pos["bank_cents"]) .
            "</b></article><article><span>Divergências</span><b>" .
            money_br($diffs) .
            "</b></article></div>";
        $partials = card(
            "<h2>" .
                icon("point_of_sale") .
                '<span>Parciais por Colaborador</span></h2><p class="muted">A conferência diária considera cada colaborador vinculado à Gaveta. Movimentos da Recepção só se tornam definitivos após confirmação do Administrativo.</p>' .
                financial_daily_drawer_partials_html($cid, $uid, (array) $state),
            "finance-list finance-drawer-partials-card",
        );
        if (!empty($state["consolidated"])) {
            $action =
                '<div class="finance-conference-lock"><span class="pill ok">' .
                icon("verified") .
                'Dia já consolidado</span><p class="muted">Novos lançamentos do dia ficam bloqueados para preservar a conferência.</p><a class="ghost small" href="' .
                href("financial", ["tab" => "painel"]) .
                '">' .
                icon("arrow_back") .
                "<span>Voltar ao Painel</span></a></div>";
        } elseif (empty($state["conference_released"])) {
            $action =
                '<div class="finance-conference-lock"><span class="pill warn">' .
                icon("point_of_sale") .
                n((int) ($state["pending_open_drawers"] ?? 0)) .
                ' colaborador(es) ainda sem fechamento</span><p class="muted">A conferência só é liberada quando todos os colaboradores vinculados à mesma Gaveta tiverem fechado ou mantido a Gaveta fechada no expediente.</p>' .
                financial_daily_drawer_closure_blocking_html(
                    (array) $state["closure"],
                ) .
                '<a class="ghost small" href="' .
                href("financial", ["tab" => "locais"]) .
                '">' .
                icon("arrow_back") .
                "<span>Ver locais</span></a></div>";
        } elseif (empty($state["can_consolidate"])) {
            $action =
                '<div class="finance-conference-lock"><span class="pill warn">' .
                icon("fact_check") .
                'Conferência liberada</span><p class="muted">Revise as parciais por colaborador. Confirme o que estiver correto ou devolva com justificativa para correção antes da consolidação definitiva.</p>' .
                financial_daily_consolidation_blockers_html($state) .
                "</div>";
        } else {
            $action =
                '<form method="post" class="finance-conference-action">' .
                csrf_field() .
                '<input type="hidden" name="act" value="daily_consolidate"><button class="primary" type="submit">' .
                icon("verified") .
                '<span>Confirmar consolidação definitiva</span></button><a class="ghost" href="' .
                href("financial", ["tab" => "painel"]) .
                '">' .
                icon("arrow_back") .
                "<span>Voltar ao Painel</span></a></form>";
        }
        return card(
            "<h2>" .
                icon("fact_check") .
                "<span>Resumo do dia</span></h2>" .
                $summary .
                $where .
                $action,
            "finance-report-card finance-daily-conference-card",
        ) .
            $partials .
            card(
                '<h2>Movimentos do dia</h2><p class="muted">Revise entradas, saídas e transferências antes de confirmar a consolidação.</p>' .
                    financial_admin_daily_ledger_timeline($cid),
                "finance-ledger-card",
            );
    
    }

    public static function financial_admin_daily_consolidate(int $cid, int $uid): void
    
    {
    
        financial_operational_schema_ready();
        financial_daily_closing_ensure_schema();
        db_tx(function () use ($cid, $uid): void {
    
            $today = financial_today($cid);
            q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
            q(
                "SELECT id FROM pi_financial_daily_closings WHERE clinic_id=? AND business_date=? FOR UPDATE",
                [$cid, $today],
            );
            financial_daily_reconciliation($cid, $today, $uid, true);
            $state = financial_daily_consolidation_state($cid, $today);
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
            q(
                "INSERT INTO pi_financial_daily_closings (clinic_id,business_date,status,expected_cents,received_cents,pending_cents,drawer_cents,safe_cents,bank_cents,movement_count,closed_drawer_count,notes,consolidated_by,consolidated_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE status='consolidado', expected_cents=VALUES(expected_cents), received_cents=VALUES(received_cents), pending_cents=VALUES(pending_cents), drawer_cents=VALUES(drawer_cents), safe_cents=VALUES(safe_cents), bank_cents=VALUES(bank_cents), movement_count=VALUES(movement_count), closed_drawer_count=VALUES(closed_drawer_count), notes=VALUES(notes), consolidated_by=VALUES(consolidated_by), consolidated_at=NOW(), updated_at=NOW()",
                [
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
                ],
            );
            audit("financeiro_dia_consolidado", "financeiro", $cid, [
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
    
        $patientId = function_exists("resolve_patient_lookup_id")
            ? resolve_patient_lookup_id(
                $cid,
                (int) ($_POST["patient_link_id"] ?? 0),
                function_exists("posted_patient_search_value")
                    ? posted_patient_search_value()
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
            normalize_payment_method((string) ($_POST["payment_method"] ?? "")) ?:
            "pix";
        $to = (int) ($_POST["to_location_id"] ?? 0);
        if ($to <= 0 || !financial_admin_location_belongs($cid, $to)) {
            throw new RuntimeException(
                "Recebimento pelo Administrador deve entrar em Cofre ou Banco do Consultório. Gaveta pertence ao fluxo da Recepção.",
            );
        }
        if ($revenueId > 0) {
            if (
                !financial_pending_revenue_belongs_to_patient(
                    $cid,
                    $revenueId,
                    $patientId,
                )
            ) {
                throw new RuntimeException(
                    "A pendência selecionada não pertence ao Paciente informado ou já foi recebida.",
                );
            }
            financial_admin_receive_expected_revenue(
                $cid,
                $uid,
                $revenueId,
                $method,
                $to,
                mb_trim((string) ($_POST["notes"] ?? "")),
            );
            return;
        }
        $pending = financial_patient_pending_revenue_count($cid, $patientId);
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
        $amount = parse_money_cents((string) ($_POST["amount"] ?? "0"));
        if ($amount <= 0) {
            throw new RuntimeException("Informe o valor recebido.");
        }
        $title = trim(
            (string) ($_POST["title"] ?? "Recebimento sem agendamento vinculado"),
        );
        if ($title === "") {
            $title = "Recebimento sem agendamento vinculado";
        }
        $accountId = financial_location_account_id($cid, $to);
        q(
            "INSERT INTO pi_financial_revenues (clinic_id,patient_link_id,title,amount_cents,status,payment_method,account_id,received_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $patientId,
                $title,
                $amount,
                "efetivada",
                $method,
                $accountId,
                date("Y-m-d H:i:s"),
                $uid,
            ],
        );
        $rid = db_last_insert_id();
        financial_create_movement(
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
        audit("recebimento_administrativo_avulso", "financeiro", $rid, [
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
                ? one(
                    "SELECT fc.id,p.full_name FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.id=? AND fc.clinic_id=? AND fc.kind='credor' AND fc.active=1 LIMIT 1",
                    [$creditorId, $cid],
                )
                : null;
        if (!$cred) {
            throw new RuntimeException("Escolha um Credor cadastrado em Pessoas.");
        }
        $from = (int) ($_POST["from_location_id"] ?? 0);
        if ($from <= 0 || !financial_admin_location_belongs($cid, $from)) {
            throw new RuntimeException(
                "Pagamento pelo Administrador deve sair de Cofre ou Banco do Consultório. Gaveta pertence ao fluxo da Recepção/Caixa.",
            );
        }
        $amount = parse_money_cents((string) ($_POST["amount"] ?? "0"));
        if ($amount <= 0) {
            throw new RuntimeException("Informe o valor da despesa.");
        }
        $method =
            normalize_payment_method((string) ($_POST["payment_method"] ?? "")) ?:
            "pix";
        $category = (string) ($_POST["expense_category"] ?? "outras");
        if (!array_key_exists($category, financial_expense_category_options())) {
            $category = "outras";
        }
        $title = mb_trim((string) ($_POST["title"] ?? "Pagamento ao credor"));
        if ($title === "") {
            $title = "Pagamento ao credor";
        }
        $notes = mb_trim((string) ($_POST["notes"] ?? ""));
        $accountId = financial_location_account_id($cid, $from);
        q(
            "INSERT INTO pi_financial_expenses (clinic_id,counterparty_id,account_id,title,expense_category,amount_cents,status,due_at,paid_at,payment_method,notes,created_by,created_at) VALUES (?,?,?,?,?,?,'paga',CURDATE(),NOW(),?,?,?,NOW())",
            [
                $cid,
                $creditorId,
                $accountId,
                $title,
                $category,
                $amount,
                $method,
                $notes ?: null,
                $uid,
            ],
        );
        $eid = db_last_insert_id();
        financial_create_movement(
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
        audit("pagamento_administrativo", "financeiro", $eid, [
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
        if ($from <= 0 || !financial_admin_location_belongs($cid, $from)) {
            throw new RuntimeException(
                "Transferência administrativa deve sair de Cofre ou Banco. Gaveta é movimentada pelo fluxo de Caixa/fechamento.",
            );
        }
        if ($to <= 0 || !financial_admin_location_belongs($cid, $to)) {
            throw new RuntimeException(
                "Transferência administrativa deve ir para Cofre ou Banco. Gaveta é movimentada pelo fluxo de Caixa/fechamento.",
            );
        }
        if ($from === $to) {
            throw new RuntimeException("Origem e destino precisam ser diferentes.");
        }
        $amount = parse_money_cents((string) ($_POST["amount"] ?? "0"));
        if ($amount <= 0) {
            throw new RuntimeException("Informe o valor da transferência.");
        }
        $title = mb_trim((string) ($_POST["title"] ?? "Transferência entre locais"));
        if ($title === "") {
            $title = "Transferência entre locais";
        }
        $notes = mb_trim((string) ($_POST["notes"] ?? ""));
        $mid = financial_create_movement(
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
        audit("transferencia_financeira_administrativa", "financeiro", $mid, [
            "from_location_id" => $from,
            "to_location_id" => $to,
            "valor" => $amount,
            "audit_body" =>
                "Administrador transferiu valor apenas entre Cofre/Banco do Consultório.",
        ]);
    
    }
}
