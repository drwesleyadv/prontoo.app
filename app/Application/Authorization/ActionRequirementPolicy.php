<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization;

final class ActionRequirementPolicy
{
    private function __construct()
    {
    }

    public static function required(
        string $route,
        string $action,
        array $definition,
        array $post,
    ): array {
        $required = ActionDefinitionCollection::tokens((array) ($definition['required'] ?? []));
        if ($route === 'documents' && $action === 'save_template') {
            $required[] = (int) ($post['id'] ?? 0) > 0 ? 'documents:edit' : 'documents:add';
        }
        if ($route === 'procedures' && $action === 'save') {
            $required[] = (int) ($post['id'] ?? 0) > 0 ? 'procedures:edit' : 'procedures:add';
        }
        return ActionDefinitionCollection::tokens($required);
    }
}
