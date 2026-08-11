<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog08
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.08.close_session.01" => (
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? AND user_id=? AND status='open' FOR UPDATE"
            ),
            "financial.08.close_session.02" => (
                "UPDATE pi_cash_sessions SET closed_at=NOW(), expected_closing_cents=?, declared_closing_cents=?, keep_in_drawer_cents=?, transfer_to_safe_cents=?, difference_cents=?, closing_notes=?, status='closed_pending_review', updated_at=NOW() WHERE id=? AND clinic_id=? AND user_id=?"
            ),
            "financial.08.review_opening_request.01" => (
                "SELECT s.*,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.id=? AND s.clinic_id=? AND s.status='opening_pending_review' FOR UPDATE"
            ),
            "financial.08.review_opening_request.02" => (
                "UPDATE pi_cash_sessions SET status='opening_rejected', reviewed_by=?, reviewed_at=NOW(), review_status='rejected', review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.08.review_opening_request.03" => (
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            "financial.08.review_opening_request.04" => (
                "UPDATE pi_cash_sessions SET opened_at=NOW(), kept_closed_at=NULL, closed_at=NULL, expected_closing_cents=0, declared_closing_cents=0, keep_in_drawer_cents=0, transfer_to_safe_cents=0, difference_cents=0, status='open', reviewed_by=?, reviewed_at=NOW(), review_status='approved', review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.08.review_opening_request.05" => (
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            "financial.08.review_session.01" => (
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? AND status='closed_pending_review' FOR UPDATE"
            ),
            "financial.08.review_session.02" => (
                "UPDATE pi_cash_sessions SET status='rejected', review_status='rejected', reviewed_by=?, reviewed_at=NOW(), review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.08.review_session.03" => (
                "UPDATE pi_financial_movements SET status='rejected', reviewed_by=?, reviewed_at=NOW(), notes=CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='', '', ' | '), ?) WHERE clinic_id=? AND cash_session_id=? AND status='pending_review'"
            ),
            "financial.08.review_session.04" => (
                "INSERT INTO pi_cash_closing_reviews (clinic_id,cash_session_id,reviewed_by,decision,expected_cents,declared_cents,approved_transfer_cents,difference_cents,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())"
            ),
            "financial.08.review_session.05" => (
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? FOR UPDATE"
            ),
            "financial.08.review_session.06" => (
                "SELECT COUNT(*) FROM pi_cash_sessions WHERE clinic_id=? AND location_id=? AND business_date=? AND status='closed_pending_review' AND id<>?"
            ),
            "financial.08.review_session.07" => (
                "UPDATE pi_cash_sessions SET status='approved', review_status='approved', reviewed_by=?, reviewed_at=NOW(), review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.08.review_session.08" => (
                "UPDATE pi_financial_movements SET status='confirmed', confirmed_by=?, confirmed_at=NOW(), reviewed_by=?, reviewed_at=NOW() WHERE clinic_id=? AND cash_session_id=? AND status='pending_review'"
            ),
            "financial.08.review_session.09" => (
                "INSERT INTO pi_cash_closing_reviews (clinic_id,cash_session_id,reviewed_by,decision,expected_cents,declared_cents,approved_transfer_cents,difference_cents,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())"
            ),
            "financial.08.assert_session_reconciled.01" => (
                "SELECT movement_type,status,amount_cents,from_location_id,to_location_id,cash_session_id,source_entity,source_id FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND status IN ('confirmed','pending_review') ORDER BY id"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
