<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog13
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.13.admin_reviews_panel.01" => (
                "SELECT s.id,s.status,s.business_date,s.opening_balance_cents,s.expected_closing_cents,u.name user_name,l.name location_name FROM pi_cash_sessions s JOIN pi_users u ON u.id=s.user_id LEFT JOIN pi_financial_locations l ON l.id=s.location_id AND l.clinic_id=s.clinic_id WHERE s.clinic_id=? AND s.status IN ('opening_pending_review','opening_rejected') ORDER BY FIELD(s.status,'opening_pending_review','opening_rejected'), s.business_date DESC,s.id DESC LIMIT 80"
            ),
            "financial.13.admin_reviews_panel.02" => (
                "SELECT s.id,s.location_id,s.status,s.business_date,s.expected_closing_cents,s.declared_closing_cents,s.difference_cents,s.transfer_to_safe_cents,u.name user_name,l.name location_name,l.drawer_locked_business_date FROM pi_cash_sessions s JOIN pi_users u ON u.id=s.user_id LEFT JOIN pi_financial_locations l ON l.id=s.location_id AND l.clinic_id=s.clinic_id WHERE s.clinic_id=? AND s.status IN ('closed_pending_review','approved','rejected') ORDER BY FIELD(s.status,'closed_pending_review','rejected','approved'), s.business_date DESC,s.id DESC LIMIT 120"
            ),
            "financial.13.location_account_id.01" => (
                "SELECT account_id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            "financial.13.admin_locations_panel.01" => (
                "SELECT a.id,a.name,a.bank_name,l.id location_id FROM pi_financial_accounts a LEFT JOIN pi_financial_locations l ON l.account_id=a.id AND l.clinic_id=a.clinic_id AND l.location_type='bank_account' WHERE a.clinic_id=? AND a.active=1 AND a.account_type IN ('conta_corrente','conta_poupanca','conta_pagamento','investimento') ORDER BY a.name"
            ),
            "financial.13.admin_movements_panel.01" => (
                "SELECT m.movement_type,m.title,m.created_at,m.amount_cents,m.status,lf.name from_name,lt.name to_name,u.name user_name FROM pi_financial_movements m LEFT JOIN pi_financial_locations lf ON lf.id=m.from_location_id AND lf.clinic_id=m.clinic_id LEFT JOIN pi_financial_locations lt ON lt.id=m.to_location_id AND lt.clinic_id=m.clinic_id LEFT JOIN pi_users u ON u.id=m.created_by WHERE m.clinic_id=? ORDER BY m.created_at DESC,m.id DESC LIMIT " .
                                max(10, min(300, $limit))
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
