<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization;

final class ActionDefinitionRegistry implements ActionDefinitionSource
{
    private ?array $definitions = null;

    public function __construct(private readonly array $sources)
    {
        foreach ($this->sources as $source) {
            if (!$source instanceof ActionDefinitionSource) {
                throw new \InvalidArgumentException('Fonte de definição de ação inválida.');
            }
        }
    }

    public function definitions(): array
    {
        if (is_array($this->definitions)) {
            return $this->definitions;
        }
        $merged = [];
        foreach ($this->sources as $source) {
            foreach ($source->definitions() as $route => $actions) {
                foreach ($actions as $action => $definition) {
                    if (isset($merged[$route][$action])) {
                        throw new \LogicException("Contrato duplicado: {$route}:{$action}");
                    }
                    $merged[$route][$action] = $definition;
                }
            }
        }
        return $this->definitions = $merged;
    }
}
