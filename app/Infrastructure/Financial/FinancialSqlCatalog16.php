<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog16
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.16.page_creditors.01" => (
                "UPDATE pi_financial_counterparties SET active=0,updated_at=NOW() WHERE id=? AND clinic_id=? AND kind='credor'"
            ),
            "financial.16.page_creditors.02" => (
                "SELECT COUNT(*) FROM pi_financial_counterparties WHERE clinic_id=? AND kind='credor' AND active=1"
            ),
            "financial.16.page_creditors.03" => (
                "SELECT COUNT(*) FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.kind='credor' AND fc.active=1 AND COALESCE(NULLIF(p.legal_document,''),NULLIF(p.cpf,''),'')<>''"
            ),
            "financial.16.page_creditors.04" => (
                "SELECT COUNT(*) FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.kind='credor' AND fc.active=1 AND (COALESCE(p.phone,'')<>'' OR COALESCE(p.email,'')<>'')"
            ),
            "financial.16.page_creditors.05" => (
                "SELECT COALESCE(SUM(e.amount_cents),0) FROM pi_financial_expenses e JOIN pi_financial_counterparties fc ON fc.id=e.counterparty_id AND fc.clinic_id=e.clinic_id WHERE e.clinic_id=? AND fc.kind='credor' AND fc.active=1 AND e.status='paga' AND e.paid_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            "financial.16.page_creditors.06" => (
                "SELECT fc.id,fc.notes,p.*,(SELECT COALESCE(SUM(e.amount_cents),0) FROM pi_financial_expenses e WHERE e.clinic_id=fc.clinic_id AND e.counterparty_id=fc.id AND e.status='paga') paid_total_cents,(SELECT MAX(e.paid_at) FROM pi_financial_expenses e WHERE e.clinic_id=fc.clinic_id AND e.counterparty_id=fc.id AND e.status='paga') last_paid_at,(SELECT COUNT(*) FROM pi_financial_expenses e WHERE e.clinic_id=fc.clinic_id AND e.counterparty_id=fc.id AND e.status='prevista' AND e.due_at<CURDATE()) overdue_expenses FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE $where ORDER BY p.full_name ASC LIMIT 160"
            ),
            "financial.16.admin_attention_panel.01" => (
                "SELECT COUNT(*) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu'))"
            ),
            "financial.16.admin_attention_panel.02" => (
                "SELECT COUNT(*) FROM pi_cash_sessions WHERE clinic_id=? AND status IN ('closed_pending_review','opening_pending_review')"
            ),
            "financial.16.admin_attention_panel.03" => (
                "SELECT COALESCE(SUM(ABS(difference_cents)),0) FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND difference_cents<>0"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
