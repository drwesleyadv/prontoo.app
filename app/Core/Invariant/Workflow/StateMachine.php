<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Workflow;

final readonly class StateMachine
{
    public function __construct(
        private array $transitions,
        private array $aliases = [],
        private array $initialStates = [],
    ) {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.StateMachine::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Workflow/StateMachine.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Workflow.AppointmentWorkflow::machine`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public function normalize(string $state): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.StateMachine::normalize
         * Responsabilidade: Transforma e normaliza “normalize” para um formato canônico utilizado pelo restante da aplicação.
         * Local arquitetural: app/Core/Invariant/Workflow/StateMachine.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `strtolower`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $state = strtolower(trim($state));
        return (string) ($this->aliases[$state] ?? $state);
    }

    public function acceptsInitial(string $state): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.StateMachine::acceptsInitial
         * Responsabilidade: Implementa a responsabilidade “accepts initial” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Workflow/StateMachine.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `in_array`, `->normalize`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return in_array($this->normalize($state), $this->initialStates, true);
    }

    public function knows(string $state): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.StateMachine::knows
         * Responsabilidade: Implementa a responsabilidade “knows” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Workflow/StateMachine.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `->normalize`, `in_array`, `array_key_exists`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $state = $this->normalize($state);
        return in_array($state, $this->initialStates, true) || array_key_exists($state, $this->transitions);
    }

    public function canTransition(string $from, string $to): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.StateMachine::canTransition
         * Responsabilidade: Implementa a responsabilidade “can transition” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Workflow/StateMachine.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `->normalize`, `->knows`, `in_array`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $from = $this->normalize($from);
        $to = $this->normalize($to);
        if ($from === $to) {
            return $this->knows($from);
        }
        return in_array($to, $this->transitions[$from] ?? [], true);
    }

    public function allowedFrom(string $state): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.StateMachine::allowedFrom
         * Responsabilidade: Implementa a responsabilidade “allowed from” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Workflow/StateMachine.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `array_values`, `->normalize`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return array_values($this->transitions[$this->normalize($state)] ?? []);
    }
}
