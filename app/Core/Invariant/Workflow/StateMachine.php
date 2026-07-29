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

    }

    public function normalize(string $state): string
    {

        $state = strtolower(trim($state));
        return (string) ($this->aliases[$state] ?? $state);
    }

    public function acceptsInitial(string $state): bool
    {

        return in_array($this->normalize($state), $this->initialStates, true);
    }

    public function knows(string $state): bool
    {

        $state = $this->normalize($state);
        return in_array($state, $this->initialStates, true) || array_key_exists($state, $this->transitions);
    }

    public function canTransition(string $from, string $to): bool
    {

        $from = $this->normalize($from);
        $to = $this->normalize($to);
        if ($from === $to) {
            return $this->knows($from);
        }
        return in_array($to, $this->transitions[$from] ?? [], true);
    }

    public function allowedFrom(string $state): array
    {

        return array_values($this->transitions[$this->normalize($state)] ?? []);
    }
}
