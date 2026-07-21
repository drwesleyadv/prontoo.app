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
     * @param list<string> $producers
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
        public array $producers = [],
    ) {
        /*
         * GUIA DE MANUTENÇÃO — Domain.Authorization.ActionContract::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Domain/Authorization/ActionContract.php (domínio e regras de negócio).
         * Chamadores detectados: `Application.Authorization.ActionCatalog::resolve`, `Infrastructure.Authorization.RuntimeCapabilityProvider::logicSelfTest`.
         * Dependências chamadas: `in_array`, `trim`.
         * Classes ou serviços instanciados: `.InvalidArgumentException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
         */
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
        foreach ($producers as $producer) {
            if (trim((string) $producer) === '') {
                throw new \InvalidArgumentException('Produtor de ação inválido no contrato.');
            }
        }
    }

    public function hash(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Domain.Authorization.ActionContract::hash
         * Responsabilidade: Implementa a responsabilidade “hash” dentro do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Authorization/ActionContract.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `Canonical::hash`, `->evidence`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
         */
        return Canonical::hash('action_contract', $this->evidence());
    }

    public function evidence(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Domain.Authorization.ActionContract::evidence
         * Responsabilidade: Implementa a responsabilidade “evidence” dentro do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Authorization/ActionContract.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
         */
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
            'producers' => $this->producers,
        ];
    }
}
