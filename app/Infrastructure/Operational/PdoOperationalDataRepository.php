<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use Closure;
use Prontoo\Application\Operational\OperationalDataPort;
use Prontoo\Application\Operational\OperationalDataResult;
use Prontoo\Application\Operational\OperationalDatabaseContextPort;
use Prontoo\Application\Operational\OperationalSchemaPort;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02;
use RuntimeException;

final class PdoOperationalDataRepository implements OperationalDataPort, OperationalSchemaPort, OperationalDatabaseContextPort
{
    private const CATALOGS = [
        'admin_pages.02' => AdminPagesSqlCatalog02::class,
        'admin_pages.06' => AdminPagesSqlCatalog06::class,
        'admin_pages.07' => AdminPagesSqlCatalog07::class,
        'admin_pages.08' => AdminPagesSqlCatalog08::class,
        'admin_pages.09' => AdminPagesSqlCatalog09::class,
        'appointments.01' => AppointmentsSqlCatalog01::class,
        'appointments.03' => AppointmentsSqlCatalog03::class,
        'appointments.05' => AppointmentsSqlCatalog05::class,
        'appointments.06' => AppointmentsSqlCatalog06::class,
        'audit_activity.01' => AuditActivitySqlCatalog01::class,
        'audit_activity.03' => AuditActivitySqlCatalog03::class,
        'audit_activity.04' => AuditActivitySqlCatalog04::class,
        'boot.runtime' => BootSqlCatalogRuntime::class,
        'clinic_config.01' => ClinicConfigSqlCatalog01::class,
        'clinic_config.02' => ClinicConfigSqlCatalog02::class,
        'dashboards.01' => DashboardsSqlCatalog01::class,
        'dashboards.02' => DashboardsSqlCatalog02::class,
        'dashboards.03' => DashboardsSqlCatalog03::class,
        'deferred_audit.01' => DeferredAuditSqlCatalog01::class,
        'document_pdf.01' => DocumentPdfSqlCatalog01::class,
        'documents.01' => DocumentsSqlCatalog01::class,
        'documents.02' => DocumentsSqlCatalog02::class,
        'documents.03' => DocumentsSqlCatalog03::class,
        'documents.05' => DocumentsSqlCatalog05::class,
        'documents.06' => DocumentsSqlCatalog06::class,
        'financial_guard.01' => FinancialGuardSqlCatalog01::class,
        'install_installer.02' => InstallInstallerSqlCatalog02::class,
        'leads.01' => LeadsSqlCatalog01::class,
        'leads.02' => LeadsSqlCatalog02::class,
        'maestro.01' => MaestroSqlCatalog01::class,
        'maestro.02' => MaestroSqlCatalog02::class,
        'maestro.03' => MaestroSqlCatalog03::class,
        'maestro.04' => MaestroSqlCatalog04::class,
        'maestro.05' => MaestroSqlCatalog05::class,
        'patients.01' => PatientsSqlCatalog01::class,
        'patients.02' => PatientsSqlCatalog02::class,
        'patients.04' => PatientsSqlCatalog04::class,
        'patients.05' => PatientsSqlCatalog05::class,
        'patients.06' => PatientsSqlCatalog06::class,
        'patients.07' => PatientsSqlCatalog07::class,
        'subscription_settings.01' => SubscriptionSettingsSqlCatalog01::class,
        'subscription_settings.02' => SubscriptionSettingsSqlCatalog02::class,
        'subscription_settings.03' => SubscriptionSettingsSqlCatalog03::class,
        'support_foundation.01' => SupportFoundationSqlCatalog01::class,
        'support_foundation.02' => SupportFoundationSqlCatalog02::class,
        'support_telemetry.01' => SupportTelemetrySqlCatalog01::class,
        'tasks_notices.01' => TasksNoticesSqlCatalog01::class,
        'tasks_notices.02' => TasksNoticesSqlCatalog02::class,
        'tasks_notices.03' => TasksNoticesSqlCatalog03::class,
        'tasks_notices.04' => TasksNoticesSqlCatalog04::class,
        'tasks_notices.05' => TasksNoticesSqlCatalog05::class,
        'tasks_notices.06' => TasksNoticesSqlCatalog06::class,
        'ui_components.01' => UiComponentsSqlCatalog01::class,
    ];

    public function __construct(private Closure $guardedQueryExecutor)
    {
    }

    public function run(
        string $operation,
        array $parameters,
        array $context,
    ): OperationalDataResult {
        $sql = $this->statement($operation, $context);
        $statement = ($this->guardedQueryExecutor)($sql, $parameters);
        if (!is_object($statement) || !method_exists($statement, 'fetchAll')) {
            throw new RuntimeException('Executor operacional retornou resultado inválido.');
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
        return new OperationalDataResult(is_array($rows) ? $rows : [], $affectedRows);
    }

    public function atomically(Closure $operation): mixed
    {
        return DatabaseSchemaInfrastructureOperations01::db_tx($operation);
    }

    public function lastInsertId(): int
    {
        return DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
    }

    public function ensure(string $contract): void
    {
        OperationalSchemaContracts::ensure($contract);
    }

    public function tableExists(string $table): bool
    {
        return DatabaseSchemaInfrastructureOperations02::db_table_exists($table);
    }

    public function columnExists(string $table, string $column): bool
    {
        return DatabaseSchemaInfrastructureOperations02::db_column_exists($table, $column);
    }

    public function inTransaction(): bool
    {
        return DatabaseSchemaInfrastructureOperations01::pdo()->inTransaction();
    }

    private function statement(string $operation, array $context): string
    {
        $parts = explode('.', $operation);
        $key = (string) ($parts[1] ?? '') . '.' . (string) ($parts[2] ?? '');
        $catalog = self::CATALOGS[$key] ?? null;
        if (!is_string($catalog) || !is_callable([$catalog, 'statement'])) {
            throw new RuntimeException('Catálogo SQL operacional desconhecido.');
        }
        return $catalog::statement($operation, $context);
    }
}
