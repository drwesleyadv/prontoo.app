<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog07
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.07.request_opening_authorization.01" => (
                "UPDATE pi_cash_sessions SET location_id=?, opened_at=NULL, kept_closed_at=NULL, opening_balance_cents=?, expected_closing_cents=?, declared_closing_cents=?, keep_in_drawer_cents=?, transfer_to_safe_cents=0, difference_cents=?, status='opening_pending_review', closing_notes=?, reviewed_by=NULL, reviewed_at=NULL, review_status=NULL, review_notes=NULL, updated_at=NOW() WHERE id=? AND clinic_id=? AND user_id=?"
            ),
            "financial.07.request_opening_authorization.02" => (
                "INSERT INTO pi_cash_sessions (clinic_id,user_id,location_id,business_date,opened_at,kept_closed_at,opening_balance_cents,expected_closing_cents,declared_closing_cents,keep_in_drawer_cents,transfer_to_safe_cents,difference_cents,status,closing_notes,created_at,updated_at) VALUES (?,?,?,?,NULL,NULL,?,?,?,?,0,?,'opening_pending_review',?,NOW(),NOW())"
            ),
            "financial.07.unclosed_previous_session.01" => (
                "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date<? AND status='open' ORDER BY business_date DESC,id DESC LIMIT 1"
            ),
            "financial.07.keep_closed.01" => (
                "SELECT id FROM pi_clinics WHERE id=? FOR UPDATE"
            ),
            "financial.07.keep_closed.02" => (
                "INSERT INTO pi_cash_sessions (clinic_id,user_id,location_id,business_date,opened_at,kept_closed_at,opening_balance_cents,closed_at,expected_closing_cents,declared_closing_cents,keep_in_drawer_cents,transfer_to_safe_cents,difference_cents,status,closing_notes,created_at,updated_at) VALUES (?,?,?,?,NULL,NOW(),?,NULL,?,?,?,0,0,'kept_closed',?,NOW(),NOW())"
            ),
            "financial.07.open_session.01" => (
                "SELECT id FROM pi_clinics WHERE id=? FOR UPDATE"
            ),
            "financial.07.open_session.02" => (
                "UPDATE pi_cash_sessions SET location_id=?, opened_at=NOW(), kept_closed_at=NULL, opening_balance_cents=?, closed_at=NULL, expected_closing_cents=0, declared_closing_cents=0, keep_in_drawer_cents=0, transfer_to_safe_cents=0, difference_cents=0, status='open', closing_notes=NULL, reviewed_by=NULL, reviewed_at=NULL, review_status=NULL, review_notes=NULL, updated_at=NOW() WHERE id=? AND clinic_id=? AND user_id=?"
            ),
            "financial.07.open_session.03" => (
                "INSERT INTO pi_cash_sessions (clinic_id,user_id,location_id,business_date,opened_at,opening_balance_cents,status,created_at,updated_at) VALUES (?,?,?,?,NOW(),?,'open',NOW(),NOW())"
            ),
            "financial.07.session_movement_totals.01" => (
                "SELECT movement_type,COALESCE(SUM(amount_cents),0) total FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND status IN ('confirmed','pending_review') GROUP BY movement_type"
            ),
            "financial.07.ensure_closing_adjustment.01" => (
                "SELECT COUNT(*) FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND movement_type='adjustment' AND source_entity='cash_closing_adjustment' AND source_id=? AND status IN ('confirmed','pending_review')"
            ),
            "financial.07.current_open_session.01" => (
                "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date=? AND status='open' LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
