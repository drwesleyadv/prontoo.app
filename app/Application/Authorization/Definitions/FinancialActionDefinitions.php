<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization\Definitions;

use Prontoo\Application\Authorization\ActionDefinitionCollection;
use Prontoo\Application\Authorization\ActionDefinitionSource;

final class FinancialActionDefinitions implements ActionDefinitionSource
{
    public function definitions(): array
    {
        $definitions = new ActionDefinitionCollection();
        $financial = 'Domain/Financial/Financial.php';

        $definitions->add('financial', ['cash_open', 'cash_keep_closed', 'cash_receipt', 'cash_payment', 'cash_close'], 'clinic', $financial, ['financial:edit'], ['financial:cashier'], [], 'financial_operational');
        $definitions->add('financial', ['drawer_create', 'bank_account'], 'clinic', $financial, ['financial:add'], ['financial:add']);
        $definitions->add(
            'financial',
            ['drawer_rename', 'drawer_assign', 'drawer_unassign', 'drawer_deactivate', 'drawer_schedule_unlock', 'goal', 'review_close', 'review_opening', 'daily_consolidate', 'admin_receive', 'admin_payment', 'admin_transfer', 'safe_payment', 'safe_receipt', 'deposit_bank'],
            'clinic',
            $financial,
            ['financial:edit'],
            ['financial:edit'],
        );
        $definitions->add('creditors', ['__default__', 'creditor_save', 'creditor_deactivate'], 'clinic', $financial, ['financial:edit'], ['financial:counterparty']);

        return $definitions->definitions();
    }
}
