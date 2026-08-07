<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization;

final class ActionDefinitionCollection implements ActionDefinitionSource
{
    private array $definitions = [];

    public function add(
        string $route,
        string|array $actions,
        string $scope,
        string $source,
        array $required = [],
        array $effects = [],
        array $delegated = [],
        string $policy = 'matrix',
        ?string $primary = null,
        array $producers = [],
    ): self {
        foreach ((array) $actions as $action) {
            $action = (string) $action;
            $action = $action !== '' ? $action : '__default__';
            if (isset($this->definitions[$route][$action])) {
                throw new \LogicException("Contrato duplicado: {$route}:{$action}");
            }
            $required = self::tokens($required);
            $this->definitions[$route][$action] = [
                'scope' => $scope,
                'required' => $required,
                'effects' => self::tokens($effects),
                'delegated' => self::tokens($delegated),
                'policy' => $policy,
                'primary' => $primary ?? ($required[0] ?? ($scope === 'global' ? 'admin:*' : 'session:self')),
                'source' => $source,
                'producers' => self::tokens($producers),
            ];
        }
        return $this;
    }

    public function definitions(): array
    {
        return $this->definitions;
    }

    public static function tokens(array $values): array
    {
        $tokens = [];
        foreach ($values as $value) {
            $token = mb_trim((string) $value);
            if ($token !== '') {
                $tokens[$token] = true;
            }
        }
        return array_keys($tokens);
    }
}
