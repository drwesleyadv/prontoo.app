<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization\Definitions;

use Prontoo\Application\Authorization\ActionDefinitionCollection;
use Prontoo\Application\Authorization\ActionDefinitionSource;

final class WorkforceActionDefinitions implements ActionDefinitionSource
{
    public function definitions(): array
    {
        $definitions = new ActionDefinitionCollection();
        $users = 'Domain/Permissions/UsersPermissions.php';
        $maestro = 'Domain/Maestro/Maestro.php';

        $definitions->add('users', ['__default__', 'save'], 'clinic', $users, ['users:add'], ['users:add']);
        $definitions->add('users', 'deactivate', 'clinic', $users, ['users:delete'], ['users:deactivate']);
        $definitions->add('user', ['__default__', 'update'], 'clinic', $users, ['users:edit'], ['users:edit']);
        $definitions->add('user', 'deactivate', 'clinic', $users, ['users:delete'], ['users:deactivate']);
        $definitions->add('permissions', '__default__', 'clinic', $users, ['permissions:edit'], ['permissions:edit']);

        $definitions->add('maestro', ['__default__', 'save_rule'], 'clinic', $maestro, ['maestro:add'], ['maestro:add'], [], 'manager_only');
        $definitions->add('maestro', 'toggle_rule', 'clinic', $maestro, ['maestro:edit'], ['maestro:edit'], [], 'manager_only');
        $definitions->add('maestro', 'delete_rule', 'clinic', $maestro, ['maestro:delete'], ['maestro:delete'], [], 'manager_only');

        return $definitions->definitions();
    }
}
