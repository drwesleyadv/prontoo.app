<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog10
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.10.location_select_options.01" => (
                "SELECT id,name,location_type FROM pi_financial_locations WHERE $where ORDER BY FIELD(location_type,'admin_safe','pos','bank_account'), name"
            ),
            "financial.10.office_destination_options.01" => (
                "SELECT id FROM pi_financial_accounts WHERE clinic_id=? AND active=1 AND account_type IN ('conta_corrente','conta_poupanca','conta_pagamento','investimento') ORDER BY name"
            ),
            "financial.10.office_destination_options.02" => (
                "SELECT l.id,l.name,l.location_type,a.bank_name FROM pi_financial_locations l LEFT JOIN pi_financial_accounts a ON a.id=l.account_id AND a.clinic_id=l.clinic_id WHERE l.clinic_id=? AND l.active=1 AND l.location_type IN ('admin_safe','bank_account') ORDER BY FIELD(l.location_type,'admin_safe','bank_account'), l.name"
            ),
            "financial.10.office_destination_belongs.01" => (
                "SELECT id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') LIMIT 1"
            ),
            "financial.10.expected_appointment_revenue_options.01" => (
                "SELECT r.id,r.amount_cents,r.title,r.expected_at,a.start_at,a.status,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.clinic_id=? AND r.status='prevista' AND r.appointment_id IS NOT NULL AND r.procedure_id IS NOT NULL AND r.amount_cents>0 AND a.status NOT IN ('cancelado','nao_compareceu') ORDER BY CASE WHEN a.status IN ('atendimento_concluido','finalizado') THEN 0 ELSE 1 END, COALESCE(a.start_at,r.expected_at,NOW()) ASC,r.id ASC LIMIT 200"
            ),
            "financial.10.receive_expected_appointment_revenue.01" => (
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? AND user_id=? AND status='open' FOR UPDATE"
            ),
            "financial.10.receive_expected_appointment_revenue.02" => (
                "SELECT r.*,a.status appointment_status,a.start_at,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.id=? AND r.clinic_id=? AND r.status='prevista' AND r.appointment_id IS NOT NULL AND r.procedure_id IS NOT NULL AND r.amount_cents>0 AND a.status NOT IN ('cancelado') FOR UPDATE"
            ),
            "financial.10.receive_expected_appointment_revenue.03" => (
                "SELECT id,account_id,location_type,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            "financial.10.receive_expected_appointment_revenue.04" => (
                "SELECT id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' ORDER BY id DESC LIMIT 1 FOR UPDATE"
            ),
            "financial.10.receive_expected_appointment_revenue.05" => (
                "UPDATE pi_financial_revenues SET status='efetivada', payment_method=?, account_id=?, received_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.10.receive_expected_appointment_revenue.06" => (
                "UPDATE pi_appointments SET payment_status='efetivada', payment_method=?, payment_amount_cents=?, payment_confirmed_at=NOW(), revenue_id=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
