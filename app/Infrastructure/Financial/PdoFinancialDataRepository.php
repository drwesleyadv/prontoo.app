<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use Closure;
use Prontoo\Application\Financial\FinancialDataPort;
use Prontoo\Application\Financial\FinancialDataResult;
use RuntimeException;

final class PdoFinancialDataRepository implements FinancialDataPort
{
    public function __construct(private Closure $guardedQueryExecutor)
    {
    }

    public function run(
        string $operation,
        array $parameters,
        array $context,
    ): FinancialDataResult {
        $sql = $this->statement($operation, $context);
        $statement = ($this->guardedQueryExecutor)($sql, $parameters);
        if (!is_object($statement) || !method_exists($statement, 'fetchAll')) {
            throw new RuntimeException('Executor financeiro retornou resultado inválido.');
        }
        $rows = preg_match('/^\s*(?:SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $sql) === 1
            ? $statement->fetchAll()
            : [];
        $affectedRows = method_exists($statement, 'rowCount')
            ? (int) $statement->rowCount()
            : 0;
        if (method_exists($statement, 'closeCursor')) {
            $statement->closeCursor();
        }
        return new FinancialDataResult(is_array($rows) ? $rows : [], $affectedRows);
    }

    public function atomically(Closure $operation): mixed
    {
        return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_tx($operation);
    }

    public function lastInsertId(): int
    {
        return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
    }

    public function ensure(string $contract): void
    {
        match ($contract) {
            'financial_operational' => \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::ensure_financial_operational_schema(),
            'financial_daily_closing' => FinancialInfrastructureOperations01::financial_daily_closing_ensure_schema(),
            default => throw new RuntimeException('Contrato de schema financeiro desconhecido.'),
        };
    }

    private function statement(string $operation, array $context): string
    {
        return match ((string) (explode('.', $operation)[1] ?? '')) {
            '01' => FinancialSqlCatalog01::statement($operation, $context),
            '02' => FinancialSqlCatalog02::statement($operation, $context),
            '03' => FinancialSqlCatalog03::statement($operation, $context),
            '04' => FinancialSqlCatalog04::statement($operation, $context),
            '05' => FinancialSqlCatalog05::statement($operation, $context),
            '06' => FinancialSqlCatalog06::statement($operation, $context),
            '07' => FinancialSqlCatalog07::statement($operation, $context),
            '08' => FinancialSqlCatalog08::statement($operation, $context),
            '09' => FinancialSqlCatalog09::statement($operation, $context),
            '10' => FinancialSqlCatalog10::statement($operation, $context),
            '11' => FinancialSqlCatalog11::statement($operation, $context),
            '12' => FinancialSqlCatalog12::statement($operation, $context),
            '13' => FinancialSqlCatalog13::statement($operation, $context),
            '14' => FinancialSqlCatalog14::statement($operation, $context),
            '15' => FinancialSqlCatalog15::statement($operation, $context),
            '16' => FinancialSqlCatalog16::statement($operation, $context),
            '17' => FinancialSqlCatalog17::statement($operation, $context),
            default => throw new RuntimeException('Catálogo SQL financeiro desconhecido.'),
        };
    }
}
