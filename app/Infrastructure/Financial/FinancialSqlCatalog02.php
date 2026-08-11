<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.02.counterparty_lookup_field.01" => (
                "SELECT p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.id=? AND fc.clinic_id=? AND fc.active=1 LIMIT 1"
            ),
            "financial.02.resolve_counterparty_lookup_id.01" => (
                "SELECT id FROM pi_financial_counterparties WHERE id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            "financial.02.resolve_counterparty_lookup_id.02" => (
                "SELECT fc.id,p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.active=1 ORDER BY p.full_name ASC LIMIT 1000"
            ),
            "financial.02.page_counterparty_lookup.01" => (
                "SELECT id,full_name,cpf,birth_date,legal_document FROM pi_persons WHERE cpf=? LIMIT 1"
            ),
            "financial.02.page_counterparty_lookup.02" => (
                "SELECT id,full_name,cpf,birth_date,legal_document FROM pi_persons WHERE legal_document=? LIMIT 1"
            ),
            "financial.02.page_counterparty_lookup.03" => (
                "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' AND active=1 LIMIT 1"
            ),
            "financial.02.page_counterparty_suggest.01" => (
                "SELECT fc.id,p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE $where ORDER BY p.full_name ASC, fc.id DESC LIMIT " .
                                (int) $limit
            ),
            "financial.02.counterparty_options.01" => (
                "SELECT fc.id,p.full_name FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.active=1 ORDER BY p.full_name LIMIT 300"
            ),
            "financial.02.account_options.01" => (
                "SELECT id,name FROM pi_financial_accounts WHERE clinic_id=? AND active=1 ORDER BY name LIMIT 200"
            ),
            "financial.02.account_label_options.01" => (
                "SELECT id,name FROM pi_financial_accounts WHERE clinic_id=? AND active=1 ORDER BY FIELD(account_type,'caixa_interno','conta_corrente','conta_pagamento','conta_poupanca','investimento'), name LIMIT 200"
            ),
            "financial.02.ensure_default_accounts.01" => (
                "SELECT id FROM pi_financial_accounts WHERE clinic_id=? AND account_type='caixa_interno' AND LOWER(name)=LOWER('Cofre do Consultório') LIMIT 1"
            ),
            "financial.02.ensure_default_accounts.02" => (
                "INSERT INTO pi_financial_accounts (clinic_id,name,bank_name,account_type,opening_balance_cents,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())"
            ),
            "financial.02.account_belongs.01" => (
                "SELECT id FROM pi_financial_accounts WHERE id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            "financial.02.counterparty_light.01" => (
                "SELECT p.id FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND LOWER(p.full_name)=LOWER(?) AND p.cpf IS NULL AND p.legal_document IS NULL ORDER BY fc.id ASC LIMIT 1"
            ),
            "financial.02.counterparty_light.02" => (
                "UPDATE pi_persons SET full_name=?, updated_at=NOW() WHERE id=?"
            ),
            "financial.02.counterparty_light.03" => (
                "INSERT INTO pi_persons (full_name,cpf,birth_date,created_at) VALUES (?,NULL,NULL,NOW())"
            ),
            "financial.02.counterparty_light.04" => (
                "INSERT INTO pi_financial_counterparties (clinic_id,person_id,kind,notes,created_by,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE notes=COALESCE(NULLIF(VALUES(notes),''),notes), active=1, updated_at=NOW()"
            ),
            "financial.02.counterparty_light.05" => (
                "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' LIMIT 1"
            ),
            "financial.02.account_balances.01" => (
                "SELECT id,name,account_type,bank_name,opening_balance_cents,active FROM pi_financial_accounts WHERE clinic_id=? ORDER BY active DESC, FIELD(account_type,'caixa_interno','conta_corrente','conta_pagamento','conta_poupanca','investimento'), name LIMIT 200"
            ),
            "financial.02.account_balances.02" => (
                match ((string) ($balanceSource ?? '')) {
                    'revenue' => "SELECT account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND account_id IS NOT NULL GROUP BY account_id",
                    'expense' => "SELECT account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_expenses WHERE clinic_id=? AND status='paga' AND account_id IS NOT NULL GROUP BY account_id",
                    'transfer_in' => "SELECT account_to_id account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_transfers WHERE clinic_id=? GROUP BY account_to_id",
                    'transfer_out' => "SELECT account_from_id account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_transfers WHERE clinic_id=? GROUP BY account_from_id",
                    default => throw new RuntimeException('Fonte de saldo financeiro desconhecida.'),
                }
            ),
            "financial.02.dashboard_numbers.01" => (
                "SELECT COALESCE(SUM(CASE WHEN status='prevista' AND expected_at IS NOT NULL AND expected_at<? THEN amount_cents ELSE 0 END),0) receive30, COALESCE(SUM(CASE WHEN status='prevista' AND expected_at IS NOT NULL AND expected_at<? THEN amount_cents ELSE 0 END),0) receive7, COALESCE(SUM(CASE WHEN status='prevista' AND expected_at>=? AND expected_at<? THEN amount_cents ELSE 0 END),0) receiveToday, COALESCE(SUM(CASE WHEN status='prevista' AND expected_at<? THEN amount_cents ELSE 0 END),0) overRec FROM pi_financial_revenues WHERE clinic_id=?"
            ),
            "financial.02.dashboard_numbers.02" => (
                "SELECT COALESCE(SUM(CASE WHEN status='prevista' AND due_at IS NOT NULL AND due_at<? THEN amount_cents ELSE 0 END),0) pay30, COALESCE(SUM(CASE WHEN status='prevista' AND due_at IS NOT NULL AND due_at<? THEN amount_cents ELSE 0 END),0) pay7, COALESCE(SUM(CASE WHEN status='prevista' AND due_at>=? AND due_at<? THEN amount_cents ELSE 0 END),0) payToday, COALESCE(SUM(CASE WHEN status='prevista' AND due_at<? THEN amount_cents ELSE 0 END),0) overPay FROM pi_financial_expenses WHERE clinic_id=?"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
