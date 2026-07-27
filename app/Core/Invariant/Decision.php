<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

final readonly class Decision
{
    public function __construct(
        public bool $allowed,
        public bool $skipped,
        public string $scope,
        public ?string $module,
        public string $operation,
        public string $reason,
        public array $evidence,
        public string $proofHash,
    ) {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Decision::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Decision.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function allow(
        string $scope,
        ?string $module,
        string $operation,
        string $reason,
        array $evidence = [],
    ): self {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Decision::allow
         * Responsabilidade: Avalia ou impõe a regra “allow”, falhando de forma controlada quando a pré-condição não é satisfeita.
         * Local arquitetural: app/Core/Invariant/Decision.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Application.Authorization.AuthorizationService::evaluate`.
         * Dependências chamadas: `self::make`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return self::make(true, false, $scope, $module, $operation, $reason, $evidence);
    }

    public static function deny(
        string $scope,
        ?string $module,
        string $operation,
        string $reason,
        array $evidence = [],
    ): self {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Decision::deny
         * Responsabilidade: Implementa a responsabilidade “deny” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Decision.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Application.Authorization.AuthorizationService::evaluate`, `Application.Authorization.AuthorizationService::assertScope`.
         * Dependências chamadas: `self::make`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return self::make(false, false, $scope, $module, $operation, $reason, $evidence);
    }

    public static function skip(string $reason, array $evidence = []): self
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Decision::skip
         * Responsabilidade: Implementa a responsabilidade “skip” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Decision.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Application.Authorization.AuthorizationService::evaluate`.
         * Dependências chamadas: `self::make`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return self::make(true, true, "none", null, "view", $reason, $evidence);
    }

    private static function make(
        bool $allowed,
        bool $skipped,
        string $scope,
        ?string $module,
        string $operation,
        string $reason,
        array $evidence,
    ): self {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Decision::make
         * Responsabilidade: Implementa a responsabilidade “make” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Decision.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Decision::allow`, `Core.Invariant.Decision::deny`, `Core.Invariant.Decision::skip`.
         * Dependências chamadas: `self`, `Canonical::hash`.
         * Classes ou serviços instanciados: `self`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $payload = [
            "allowed" => $allowed,
            "skipped" => $skipped,
            "scope" => $scope,
            "module" => $module,
            "operation" => $operation,
            "reason" => $reason,
            "evidence" => $evidence,
        ];
        return new self(
            $allowed,
            $skipped,
            $scope,
            $module,
            $operation,
            $reason,
            $evidence,
            Canonical::hash("decision", $payload),
        );
    }

    public function toArray(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Decision::toArray
         * Responsabilidade: Converte o estado do objeto para uma representação serializável consumida por outras camadas.
         * Local arquitetural: app/Core/Invariant/Decision.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return [
            "allowed" => $this->allowed,
            "skipped" => $this->skipped,
            "scope" => $this->scope,
            "module" => $this->module,
            "operation" => $this->operation,
            "reason" => $this->reason,
            "evidence" => $this->evidence,
            "proof_hash" => $this->proofHash,
        ];
    }
}
