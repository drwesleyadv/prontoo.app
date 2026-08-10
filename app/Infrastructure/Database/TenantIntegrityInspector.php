<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Database;

use PDO;
use Prontoo\Core\Tenant\TenantRegistry;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01;
use Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01;

final class TenantIntegrityInspector
{
    private const GOVERNANCE_TABLES_WITH_CLINIC_ID = [
        'pi_audit',
        'pi_scope_violations',
        'pi_action_ledger',
        'pi_clinics',
        'pi_persons',
        'pi_error_events',
        'pi_user_devices',
    ];

    private function __construct()
    {
    }

    public static function assertRegistryMatchesSchema(bool $strict = false): void
    {
        try {
            $statement = DatabaseSchemaInfrastructureOperations01::pdo()->query(
                "SELECT TABLE_NAME AS tenant_table_name, COLUMN_NAME AS tenant_column_name " .
                "FROM information_schema.columns " .
                "WHERE table_schema=DATABASE() " .
                "AND table_name LIKE 'pi\\_%' " .
                "AND column_name='clinic_id'",
            );
            $rows = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $error) {
            error_log('[Prontoo tenant integrity] não foi possível inspecionar schema: ' . $error->getMessage());
            return;
        }
        $schemaTables = [];
        foreach ($rows as $row) {
            $table = strtolower(mb_trim((string) ($row['tenant_table_name'] ?? $row['TABLE_NAME'] ?? '')));
            if ($table !== '') {
                $schemaTables[] = $table;
            }
        }
        $schemaTables = array_values(array_unique($schemaTables));
        $registered = array_keys(TenantRegistry::scopedTables());
        $missingRegistered = array_values(array_diff($registered, $schemaTables));
        if ($strict && $missingRegistered !== []) {
            error_log('[Prontoo tenant integrity] tabela(s) registrada(s) ainda ausente(s): ' . implode(', ', $missingRegistered));
        }
        $unknown = array_values(array_diff($schemaTables, $registered, self::GOVERNANCE_TABLES_WITH_CLINIC_ID));
        self::writeDiagnostic($schemaTables, $registered, $unknown);
        if ($unknown === []) {
            return;
        }
        $message = 'Tabela(s) com clinic_id fora do registro de isolamento: ' . implode(', ', $unknown);
        error_log('[Prontoo tenant integrity] ' . $message);
        if ($strict && defined('PRONTOO_TENANT_INTEGRITY_STRICT') && PRONTOO_TENANT_INTEGRITY_STRICT) {
            throw new \RuntimeException($message);
        }
    }

    private static function writeDiagnostic(array $schemaTables, array $registered, array $unknown): void
    {
        $payload = [
            'checked_at' => date('c'),
            'strict_runtime' => defined('PRONTOO_TENANT_INTEGRITY_STRICT') ? (bool) PRONTOO_TENANT_INTEGRITY_STRICT : false,
            'schema_tables_with_clinic_id' => array_values($schemaTables),
            'registered_scoped_tables' => array_values($registered),
            'governance_tables_with_clinic_id' => self::GOVERNANCE_TABLES_WITH_CLINIC_ID,
            'unregistered_tables_with_clinic_id' => array_values($unknown),
            'registered_tables_not_found_yet' => array_values(array_diff($registered, $schemaTables)),
        ];
        $file = SupportFoundationInfrastructureOperations01::storage_path('tenant_integrity.json');
        @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($file, 0640);
    }
}
