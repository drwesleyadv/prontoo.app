<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization\Definitions;

use Prontoo\Application\Authorization\ActionDefinitionCollection;
use Prontoo\Application\Authorization\ActionDefinitionSource;

final class AdminActionDefinitions implements ActionDefinitionSource
{
    public function definitions(): array
    {
        $definitions = new ActionDefinitionCollection();
        $admin = 'Runtime/AdminPages';
        $tasks = 'Runtime/TasksNotices';

        $globalActions = [
            'admin_clinics' => ['activate_subscription', 'billing', 'confirm_subscription_payment', 'deactivate_subscription', 'default_billing', 'reject_subscription_payment', 'toggle'],
            'admin_alerts' => ['create', 'read'],
            'admin_maintenance' => ['save_settings', 'save_maintenance'],
        ];
        foreach ($globalActions as $route => $actions) {
            $definitions->add($route, $actions, 'global', $admin, ['admin:*'], ['admin:write'], [], 'global_admin');
        }
        $definitions->add('admin_global_notices', 'create', 'global', $admin, ['admin:*'], ['admin:write'], [], 'global_admin');
        $definitions->add('admin_global_notices', 'toggle', 'global', $admin, ['admin:*'], ['admin:write'], [], 'global_admin', null, [$tasks]);

        return $definitions->definitions();
    }
}
