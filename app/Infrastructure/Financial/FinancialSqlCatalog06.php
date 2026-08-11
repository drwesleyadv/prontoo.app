<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog06
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.06.location_potential_balance.01" => (
                "SELECT COALESCE(SUM(delta_cents),0) FROM (" .
                                "SELECT amount_cents delta_cents FROM pi_financial_movements WHERE to_location_id=? AND clinic_id=? AND status IN ('confirmed','pending_review')" .
                                $excludeTo .
                                " UNION ALL SELECT -amount_cents delta_cents FROM pi_financial_movements WHERE from_location_id=? AND clinic_id=? AND status IN ('confirmed','pending_review')" .
                                $excludeFrom .
                                ") financial_potential"
            ),
            "financial.06.validate_movement_invariants.01" => (
                "SELECT id FROM pi_clinics WHERE id=? FOR UPDATE"
            ),
            "financial.06.validate_movement_invariants.02" => (
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND id IN ($placeholders) AND active=1 ORDER BY id FOR UPDATE"
            ),
            "financial.06.validate_movement_invariants.03" => (
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? FOR UPDATE"
            ),
            "financial.06.update_existing_movement.01" => (
                "SELECT id FROM pi_financial_movements WHERE id=? AND clinic_id=? FOR UPDATE"
            ),
            "financial.06.update_existing_movement.02" => (
                "UPDATE pi_financial_movements SET movement_type=?,status=?,amount_cents=?,payment_method=?,from_location_id=?,to_location_id=?,cash_session_id=?,title=?,notes=?,updated_at=NOW(),confirmed_by=IF(?='confirmed',COALESCE(confirmed_by,?),NULL),confirmed_at=IF(?='confirmed',COALESCE(confirmed_at,NOW()),NULL),reviewed_by=NULL,reviewed_at=NULL WHERE id=? AND clinic_id=?"
            ),
            "financial.06.create_movement.01" => (
                "INSERT INTO pi_financial_movements (clinic_id,movement_type,status,amount_cents,payment_method,from_location_id,to_location_id,cash_session_id,source_entity,source_id,title,notes,created_by,created_at,confirmed_by,confirmed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,IF(?='confirmed',NOW(),NULL))"
            ),
            "financial.06.session_for_date.01" => (
                "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date=? LIMIT 1"
            ),
            "financial.06.latest_session.01" => (
                "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? ORDER BY business_date DESC,id DESC LIMIT 1"
            ),
            "financial.06.cashier_name.01" => (
                "SELECT name FROM pi_users WHERE id=? LIMIT 1"
            ),
            "financial.06.notify_opening_authorization_request.01" => (
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
