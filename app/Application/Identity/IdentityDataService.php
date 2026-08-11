<?php
declare(strict_types=1);

namespace Prontoo\Application\Identity;

use Closure;
use InvalidArgumentException;
use Throwable;

final class IdentityDataService
{
    public function __construct(
        private IdentityDataPort $port,
        private ?Closure $failureReporter = null,
    ) {
    }

    public function result(
        string $operation,
        array $parameters = [],
        array $context = [],
    ): IdentityDataResult {
        $this->assertOperation($operation);
        return $this->port->run($operation, $parameters, $context);
    }

    public function row(
        string $operation,
        array $parameters = [],
        array $context = [],
    ): ?array {
        $row = $this->result($operation, $parameters, $context)->fetch();
        return is_array($row) ? $row : null;
    }

    public function scalar(
        string $operation,
        array $parameters = [],
        array $context = [],
    ): mixed {
        $value = $this->result($operation, $parameters, $context)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function safeScalar(
        string $operation,
        array $parameters = [],
        mixed $default = null,
        array $context = [],
    ): mixed {
        try {
            return $this->scalar($operation, $parameters, $context) ?? $default;
        } catch (Throwable $error) {
            if ($this->failureReporter !== null) {
                ($this->failureReporter)($error);
            }
            return $default;
        }
    }

    public function atomic(Closure $operation): mixed
    {
        return $this->port->atomically($operation);
    }

    public function lastInsertId(): int
    {
        return max(0, $this->port->lastInsertId());
    }

    public function ensureOnboardingTipsSchema(): void
    {
        $this->port->ensure('onboarding_tips');
    }

    private function assertOperation(string $operation): void
    {
        if (preg_match('/^identity\.(?:security|auth|permissions)(?:0[1-9])\.[a-z][a-z0-9_]*\.[0-9]{2}$/D', $operation) !== 1) {
            throw new InvalidArgumentException('Operação de identidade não catalogada.');
        }
    }
}
