<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog04
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.04.drawer_lock_after_close.01" => (
                "SELECT COUNT(*) FROM pi_financial_location_users WHERE clinic_id=? AND location_id=? AND active=1"
            ),
            "financial.04.drawer_lock_after_close.02" => (
                "SELECT COUNT(DISTINCT user_id) FROM pi_cash_sessions WHERE clinic_id=? AND location_id=? AND business_date=? AND status IN ('closed_pending_review','approved','rejected','kept_closed')"
            ),
            "financial.04.drawer_lock_after_close.03" => (
                "UPDATE pi_financial_locations SET drawer_lock_status='locked', drawer_locked_business_date=?, drawer_locked_at=NOW(), drawer_unlock_at=NULL, drawer_unlocked_at=NULL, drawer_reviewed_by=NULL, drawer_reviewed_at=NULL, drawer_review_notes=NULL, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'"
            ),
            "financial.04.schedule_drawer_unlock.01" => (
                "UPDATE pi_financial_locations SET drawer_unlock_at=?, drawer_reviewed_by=?, drawer_reviewed_at=NOW(), drawer_review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'"
            ),
            "financial.04.drawer_open_session.01" => (
                "SELECT s.*,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id=? AND s.status='open'" .
                    ((int) ($excludeUid ?? 0) > 0 ? " AND s.user_id<>?" : "") .
                    " ORDER BY s.opened_at DESC,s.id DESC LIMIT 1"
            ),
            "financial.04.latest_drawer_session.01" => (
                "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND location_id=? AND status IN ('closed_pending_review','approved','kept_closed')" .
                    ((string) ($maxDate ?? '') !== '' ? " AND business_date<=?" : "") .
                    ((int) ($ignoreSessionId ?? 0) > 0 ? " AND id<>?" : "") .
                    " ORDER BY business_date DESC, COALESCE(closed_at,kept_closed_at,created_at) DESC, id DESC LIMIT 1"
            ),
            "financial.04.drawer_pending_previous_review.01" => (
                "SELECT s.*,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id=? AND s.business_date<? AND s.status IN ('closed_pending_review','rejected') ORDER BY s.business_date DESC,s.id DESC LIMIT 1"
            ),
            "financial.04.drawer_balance_snapshot.01" => (
                "SELECT s.id,s.location_id,s.opening_balance_cents,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id IN ($locationPh) AND s.status='open' ORDER BY s.location_id,s.opened_at DESC,s.id DESC"
            ),
            "financial.04.drawer_balance_snapshot.02" => (
                "SELECT cash_session_id,from_location_id,to_location_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id IN ($sessionPh) AND status IN ('confirmed','pending_review') GROUP BY cash_session_id,from_location_id,to_location_id"
            ),
            "financial.04.drawer_balance_snapshot.03" => (
                "SELECT location_id,keep_in_drawer_cents FROM (SELECT location_id,keep_in_drawer_cents,ROW_NUMBER() OVER (PARTITION BY location_id ORDER BY business_date DESC,COALESCE(closed_at,kept_closed_at,created_at) DESC,id DESC) row_rank FROM pi_cash_sessions WHERE clinic_id=? AND location_id IN ($closedPh) AND status IN ('closed_pending_review','approved','kept_closed')) ranked WHERE row_rank=1"
            ),
            "financial.04.drawer_daily_totals_map.01" => (
                "SELECT id,location_id,business_date,opening_balance_cents,keep_in_drawer_cents,transfer_to_safe_cents,declared_closing_cents,status FROM pi_cash_sessions WHERE clinic_id=? AND location_id IN ($locationPh) AND business_date IN ($datePh) ORDER BY location_id,business_date,id ASC"
            ),
            "financial.04.drawer_daily_totals_map.02" => (
                "SELECT s.location_id,s.business_date,m.movement_type,COALESCE(SUM(m.amount_cents),0) total FROM pi_financial_movements m JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND s.location_id IN ($locationPh) AND s.business_date IN ($datePh) AND m.status IN ('confirmed','pending_review') GROUP BY s.location_id,s.business_date,m.movement_type"
            ),
            "financial.04.location_belongs.01" => (
                "SELECT id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            "financial.04.admin_location_belongs.01" => (
                "SELECT id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') LIMIT 1"
            ),
            "financial.04.admin_location_select_options.01" => (
                "SELECT id,name,location_type FROM pi_financial_locations WHERE clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') ORDER BY FIELD(location_type,'admin_safe','bank_account'), name,id"
            ),
            "financial.04.day_is_consolidated.01" => (
                "SELECT id FROM pi_financial_daily_closings WHERE clinic_id=? AND business_date=? AND status='consolidado' LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
