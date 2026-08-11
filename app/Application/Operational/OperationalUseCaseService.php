<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

use Closure;
use InvalidArgumentException;
use Throwable;

final class OperationalUseCaseService
{
    private const SCOPES = [
        'tasks' => ['tasks_notices'],
        'appointments' => ['appointments'],
        'patients' => ['patients'],
        'documents' => ['documents', 'document_pdf'],
        'leads' => ['leads'],
        'maestro' => ['maestro'],
        'administration' => [
            'admin_pages',
            'audit_activity',
            'clinic_config',
            'dashboards',
            'subscription_settings',
            'ui_components',
        ],
        'platform' => [
            'boot',
            'deferred_audit',
            'financial_guard',
            'install_installer',
            'support_foundation',
            'support_telemetry',
        ],
    ];

    public function __construct(
        private OperationalDataPort $port,
        private OperationalSchemaPort $schema,
        private OperationalDatabaseContextPort $databaseContext,
        private string $scope,
        private ?Closure $failureReporter = null,
    ) {
        if (!isset(self::SCOPES[$scope])) {
            throw new InvalidArgumentException('Escopo operacional desconhecido.');
        }
    }

    public function result(
        string $operation,
        array $parameters = [],
        array $context = [],
    ): OperationalDataResult {
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

    public function ensureSchema(string $contract): void
    {
        $this->schema->ensure($contract);
    }

    public function tableExists(string $table): bool
    {
        return $this->schema->tableExists($table);
    }

    public function columnExists(string $table, string $column): bool
    {
        return $this->schema->columnExists($table, $column);
    }

    public function inTransaction(): bool
    {
        return $this->databaseContext->inTransaction();
    }

    private function assertOperation(string $operation): void
    {
        if (preg_match('/^operational\.([a-z][a-z0-9_]*)\.(?:0[1-9]|runtime)\.[a-z][a-z0-9_]*\.[0-9]{2}$/D', $operation, $match) !== 1) {
            throw new InvalidArgumentException('Operação operacional não catalogada.');
        }
        if (!in_array((string) $match[1], self::SCOPES[$this->scope], true)) {
            throw new InvalidArgumentException('Operação fora do escopo do caso de uso.');
        }
    }
}
