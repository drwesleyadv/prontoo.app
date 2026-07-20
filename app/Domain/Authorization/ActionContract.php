<?php
declare(strict_types=1);

namespace Prontoo\Domain\Authorization;

use Prontoo\Core\Invariant\Canonical;

final readonly class ActionContract
{
    /**
     * @param list<string> $required
     * @param list<string> $effects
     * @param list<string> $delegated
     */
    public function __construct(
        public string $route,
        public string $action,
        public string $scope,
        public array $required,
        public array $effects,
        public array $delegated,
        public string $policy,
        public string $primary,
        public string $source,
    ) {
        if ($route === '' || $action === '') {
            throw new \InvalidArgumentException('Rota e ação são obrigatórias no contrato.');
        }
        if (!in_array($scope, ['public', 'authenticated', 'clinic', 'global'], true)) {
            throw new \InvalidArgumentException('Escopo de ação inválido: ' . $scope);
        }
        if ($primary === '') {
            throw new \InvalidArgumentException('Capacidade primária ausente no contrato.');
        }
        if ($source === '') {
            throw new \InvalidArgumentException('Arquivo-fonte ausente no contrato.');
        }
    }

    public function hash(): string
    {
        return Canonical::hash('action_contract', $this->evidence());
    }

    public function evidence(): array
    {
        return [
            'route' => $this->route,
            'action' => $this->action,
            'scope' => $this->scope,
            'required' => $this->required,
            'effects' => $this->effects,
            'delegated' => $this->delegated,
            'policy' => $this->policy,
            'primary' => $this->primary,
            'source' => $this->source,
        ];
    }
}
