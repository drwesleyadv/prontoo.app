<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog09
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.09.location_movement_balances.01" => (
                "SELECT location_id,COALESCE(SUM(delta_cents),0) balance_cents FROM (SELECT to_location_id location_id,amount_cents delta_cents FROM pi_financial_movements WHERE clinic_id=? AND to_location_id IN (" . \Prontoo\Infrastructure\Operational\OperationalSequenceSql::placeholders((int) $itemCount) . ") AND status='confirmed' UNION ALL SELECT from_location_id location_id,-amount_cents delta_cents FROM pi_financial_movements WHERE clinic_id=? AND from_location_id IN (" . \Prontoo\Infrastructure\Operational\OperationalSequenceSql::placeholders((int) $itemCount) . ") AND status='confirmed') movement_totals GROUP BY location_id"
            ),
            "financial.09.global_position.01" => (
                "SELECT COALESCE(SUM(transfer_to_safe_cents),0) FROM pi_cash_sessions WHERE clinic_id=? AND status='closed_pending_review'"
            ),
            "financial.09.global_position.02" => (
                "SELECT l.id,l.user_id,l.name FROM pi_financial_locations l WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,l.id"
            ),
            "financial.09.global_position.03" => (
                "SELECT location_id,COUNT(*) total FROM pi_financial_location_users WHERE clinic_id=? AND location_id IN (" . \Prontoo\Infrastructure\Operational\OperationalSequenceSql::placeholders((int) $itemCount) . ") AND active=1 GROUP BY location_id"
            ),
            "financial.09.global_position.04" => (
                "SELECT l.id,l.name,l.account_id,a.bank_name,a.account_type FROM pi_financial_locations l LEFT JOIN pi_financial_accounts a ON a.id=l.account_id AND a.clinic_id=l.clinic_id WHERE l.clinic_id=? AND l.location_type='bank_account' AND l.active=1 ORDER BY l.name"
            ),
            "financial.09.register_appointment_payment_movement.01" => (
                "SELECT id,status FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' ORDER BY id DESC LIMIT 1"
            ),
            "financial.09.register_appointment_payment_movement.02" => (
                "UPDATE pi_financial_movements SET status='cancelled', confirmed_by=NULL, confirmed_at=NULL, notes=CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='', '', ' | '), 'Pagamento desmarcado no agendamento.'), reviewed_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.09.revenue_id_for_appointment.01" => (
                "SELECT id FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=? LIMIT 1"
            ),
            "financial.09.revenue_id_for_appointment.02" => (
                "SELECT id FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=? LIMIT 1"
            ),
            "financial.09.daily_drawer_closure_state.01" => (
                "SELECT l.id location_id,COALESCE(l.name,'Gaveta') drawer_name,u.id user_id,COALESCE(u.name,'Colaborador') user_name FROM pi_financial_locations l JOIN pi_financial_location_users lu ON lu.location_id=l.id AND lu.clinic_id=l.clinic_id AND lu.active=1 JOIN pi_users u ON u.id=lu.user_id AND u.active=1 WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,u.name"
            ),
            "financial.09.daily_drawer_closure_state.02" => (
                "SELECT s.id,s.status,s.opened_at,s.closed_at,s.business_date,s.location_id,s.user_id,s.opening_balance_cents,s.expected_closing_cents,s.declared_closing_cents,s.keep_in_drawer_cents,s.transfer_to_safe_cents,s.difference_cents,COALESCE(l.name,'Gaveta') drawer_name,COALESCE(u.name,'Colaborador') user_name FROM pi_cash_sessions s LEFT JOIN pi_financial_locations l ON l.id=s.location_id AND l.clinic_id=s.clinic_id LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.business_date=? ORDER BY COALESCE(l.name,''),COALESCE(u.name,''),s.id"
            ),
            "financial.09.admin_daily_consolidation_html.01" => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND amount_cents>0 AND expected_at>=? AND expected_at<?"
            ),
            "financial.09.admin_daily_consolidation_html.02" => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?"
            ),
            "financial.09.admin_daily_consolidation_html.03" => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND amount_cents>0 AND expected_at<?"
            ),
            "financial.09.cashier_pending_receipts_html.01" => (
                "SELECT r.id,r.amount_cents,r.title,a.id appointment_id,a.start_at,a.status,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.clinic_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.expected_at<? AND a.status NOT IN ('cancelado','nao_compareceu') ORDER BY COALESCE(a.start_at,r.expected_at,NOW()) ASC,r.id ASC LIMIT 8"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
