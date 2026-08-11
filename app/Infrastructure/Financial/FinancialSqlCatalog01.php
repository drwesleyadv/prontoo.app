<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.01.seed_payment_methods.01" => (
                "SELECT COUNT(*) FROM pi_financial_payment_methods WHERE clinic_id=?"
            ),
            "financial.01.seed_payment_methods.02" => (
                "INSERT INTO pi_financial_payment_methods (clinic_id,name,method_type,settlement_days,active,created_by,created_at) VALUES (?,?,?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE active=VALUES(active)"
            ),
            "financial.01.payment_method_options.01" => (
                "SELECT id,name,method_type FROM pi_financial_payment_methods WHERE clinic_id=? AND active=1 ORDER BY FIELD(method_type,'pix','dinheiro','cartao_debito','cartao_credito','transferencia','boleto','cheque','outro'), name"
            ),
            "financial.01.payment_method_from_post.01" => (
                "SELECT id,name,method_type FROM pi_financial_payment_methods WHERE id=? AND clinic_id=? AND active=1"
            ),
            "financial.01.appointment_payment_destination_options.01" => (
                "SELECT id FROM pi_financial_accounts WHERE clinic_id=? AND active=1 AND account_type IN ('conta_corrente','conta_poupanca','conta_pagamento','investimento') ORDER BY id LIMIT 80"
            ),
            "financial.01.appointment_payment_destination_options.02" => (
                "SELECT id,name,location_type FROM pi_financial_locations WHERE clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') ORDER BY FIELD(location_type,'admin_safe','bank_account'), name,id"
            ),
            "financial.01.appointment_payment_existing_destination.01" => (
                "SELECT to_location_id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' AND status='confirmed' ORDER BY id DESC LIMIT 1"
            ),
            "financial.01.appointment_payment_form_html.01" => (
                "SELECT price_cents FROM pi_procedures WHERE id=? AND clinic_id=?"
            ),
            "financial.01.sync_appointment.01" => (
                "SELECT id,patient_link_id,procedure_id,start_at,reason,payment_amount_cents,payment_method,payment_status,payment_confirmed_at,revenue_id FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            "financial.01.sync_appointment.02" => (
                "SELECT price_cents FROM pi_procedures WHERE id=? AND clinic_id=?"
            ),
            "financial.01.sync_appointment.03" => (
                "UPDATE pi_appointments SET payment_amount_cents=? WHERE id=? AND clinic_id=?"
            ),
            "financial.01.sync_appointment.04" => (
                "SELECT id FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=?"
            ),
            "financial.01.sync_appointment.05" => (
                "UPDATE pi_financial_revenues SET procedure_id=?, patient_link_id=?, title=?, amount_cents=?, status=?, payment_method=?, expected_at=?, received_at=?, updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.01.sync_appointment.06" => (
                "UPDATE pi_appointments SET revenue_id=? WHERE id=? AND clinic_id=?"
            ),
            "financial.01.sync_appointment.07" => (
                "INSERT INTO pi_financial_revenues (clinic_id,appointment_id,procedure_id,patient_link_id,title,amount_cents,status,payment_method,expected_at,received_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())"
            ),
            "financial.01.sync_appointment.08" => (
                "UPDATE pi_appointments SET revenue_id=? WHERE id=? AND clinic_id=?"
            ),
            "financial.01.monthly_goal_status.01" => (
                "SELECT target_cents,base_metric,share_with_team FROM pi_financial_goals WHERE clinic_id=? AND month_key=?"
            ),
            "financial.01.monthly_goal_status.02" => (
                "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.expected_at>=? AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))"
            ),
            "financial.01.monthly_goal_status.03" => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?"
            ),
            "financial.01.counterparty_autosuggest_datalist.01" => (
                "SELECT fc.id,p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.active=1 ORDER BY p.full_name ASC LIMIT 800"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
