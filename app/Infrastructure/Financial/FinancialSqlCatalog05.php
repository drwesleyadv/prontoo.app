<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog05
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.05.daily_metrics.01" => (
                "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.amount_cents>0 AND r.expected_at>=? AND r.expected_at<? AND " .
                                $activeAppointment
            ),
            "financial.05.daily_metrics.02" => (
                "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='efetivada' AND r.amount_cents>0 AND r.expected_at>=? AND r.expected_at<? AND " .
                                $activeAppointment
            ),
            "financial.05.daily_metrics.03" => (
                "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.expected_at>=? AND r.expected_at<? AND " .
                                $activeAppointment
            ),
            "financial.05.daily_metrics.04" => (
                "SELECT COUNT(DISTINCT m.id) FROM pi_financial_movements m LEFT JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND m.status='confirmed' AND ((m.created_at>=? AND m.created_at<?) OR s.business_date=?)"
            ),
            "financial.05.daily_reconciliation.01" => (
                "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND status IN ('approved','kept_closed') ORDER BY id"
            ),
            "financial.05.daily_reconciliation.02" => (
                "SELECT DISTINCT m.id,m.movement_type,m.status,m.amount_cents,m.from_location_id,m.to_location_id,m.cash_session_id,m.source_entity,m.source_id FROM pi_financial_movements m LEFT JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND m.status='confirmed' AND ((m.created_at>=? AND m.created_at<?) OR s.business_date=?) ORDER BY m.id"
            ),
            "financial.05.daily_reconciliation.03" => (
                "SELECT location_id FROM pi_cash_sessions WHERE id=? AND clinic_id=? LIMIT 1"
            ),
            "financial.05.daily_consolidation_state.01" => (
                "SELECT COUNT(*) FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND status IN ('closed_pending_review','rejected','opening_pending_review','opening_rejected')"
            ),
            "financial.05.daily_consolidation_state.02" => (
                "SELECT COUNT(DISTINCT m.id) FROM pi_financial_movements m LEFT JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND m.status IN ('pending_review','rejected') AND ((m.created_at>=? AND m.created_at<?) OR s.business_date=?)"
            ),
            "financial.05.patient_pending_revenue_count.01" => (
                "SELECT COUNT(*) FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.patient_link_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.appointment_id IS NOT NULL AND a.status NOT IN ('cancelado','nao_compareceu','reagendado')"
            ),
            "financial.05.pending_revenue_belongs_to_patient.01" => (
                "SELECT r.id FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.id=? AND r.clinic_id=? AND r.patient_link_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.appointment_id IS NOT NULL AND a.status NOT IN ('cancelado','nao_compareceu','reagendado') LIMIT 1"
            ),
            "financial.05.cancel_appointment_revenue.01" => (
                "SELECT id,status FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=? FOR UPDATE"
            ),
            "financial.05.cancel_appointment_revenue.02" => (
                "UPDATE pi_financial_revenues SET status='cancelada', updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status='prevista'"
            ),
            "financial.05.cancel_appointment_revenue.03" => (
                "UPDATE pi_financial_movements SET status='cancelled', confirmed_by=NULL, confirmed_at=NULL, reviewed_at=NOW(), notes=CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='', '', ' | '), ?) WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' AND status<>'confirmed'"
            ),
            "financial.05.admin_receive_expected_revenue.01" => (
                "SELECT r.*,a.status appointment_status,a.start_at,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.id=? AND r.clinic_id=? AND r.status='prevista' AND r.appointment_id IS NOT NULL AND r.amount_cents>0 AND a.status NOT IN ('cancelado','nao_compareceu','reagendado') FOR UPDATE"
            ),
            "financial.05.admin_receive_expected_revenue.02" => (
                "SELECT id,account_id,location_type,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') LIMIT 1"
            ),
            "financial.05.admin_receive_expected_revenue.03" => (
                "SELECT id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' ORDER BY id DESC LIMIT 1 FOR UPDATE"
            ),
            "financial.05.admin_receive_expected_revenue.04" => (
                "UPDATE pi_financial_revenues SET status='efetivada', payment_method=?, account_id=?, received_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.05.admin_receive_expected_revenue.05" => (
                "UPDATE pi_appointments SET payment_status='efetivada', payment_method=?, payment_amount_cents=?, payment_confirmed_at=NOW(), revenue_id=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.05.session_position_cents.01" => (
                "SELECT id,amount_cents,from_location_id,to_location_id FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND status IN ('confirmed','pending_review')" .
                    ((int) ($excludeMovementId ?? 0) > 0 ? " AND id<>?" : "") .
                    " ORDER BY id"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
