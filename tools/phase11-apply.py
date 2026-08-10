from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(path):
    return (ROOT / path).read_text()

def write(path, content):
    p = ROOT / path
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(content)

def replace(path, old, new, count=None):
    s = read(path)
    n = s.count(old)
    expected = 1 if count is None else count
    if n != expected:
        raise RuntimeError(f"{path}: esperado {expected} ocorrência(s), encontrado {n}: {old[:100]!r}")
    write(path, s.replace(old, new, expected))

def regex(path, pattern, repl, count=1, flags=re.S):
    s = read(path)
    out, n = re.subn(pattern, repl, s, count=count, flags=flags)
    if n != count:
        raise RuntimeError(f"{path}: regex esperava {count}, encontrou {n}: {pattern[:120]}")
    write(path, out)

def insert_before_last_brace(path, block):
    s = read(path)
    idx = s.rfind('}')
    if idx < 0:
        raise RuntimeError(f"{path}: fechamento final não encontrado")
    write(path, s[:idx] + block + s[idx:])

write('app/Core/Invariant/InvariantRuntimePort.php', r'''<?php
declare(strict_types=1);

namespace Prontoo\Core\Invariant;

interface InvariantRuntimePort
{
    public function clinicReadOnly(int $clinicId): bool;

    public function recordScopeViolation(string $key, string $sql, string $detail): void;

    public function foreignKeyRelations(string $table): array;

    public function foreignKeyTargetClinicId(
        string $table,
        string $column,
        mixed $value,
        string $scopeColumn,
        int $clinicId,
    ): ?int;

    public function appointmentStatuses(int $clinicId, array $ids): array;
}
''')

write('app/Core/Invariant/InvariantRuntimeBinding.php', r'''<?php
declare(strict_types=1);

namespace Prontoo\Core\Invariant;

final class InvariantRuntimeBinding
{
    private static ?InvariantRuntimePort $port = null;

    private function __construct()
    {
    }

    public static function configure(InvariantRuntimePort $port): void
    {
        self::$port = $port;
    }

    public static function port(): ?InvariantRuntimePort
    {
        return self::$port;
    }
}
''')

write('app/Runtime/Invariant/InvariantRuntimeAdapter.php', r'''<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Invariant;

use PDO;
use Prontoo\Core\Invariant\InvariantRuntimePort;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01;
use Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01;
use Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03;

final class InvariantRuntimeAdapter implements InvariantRuntimePort
{
    public function clinicReadOnly(int $clinicId): bool
    {
        return ClinicConfigRuntimeOperations01::clinic_read_only_db($clinicId);
    }

    public function recordScopeViolation(string $key, string $sql, string $detail): void
    {
        SecurityAccessRuntimeOperations03::record_scope_violation($key, $sql, $detail);
    }

    public function foreignKeyRelations(string $table): array
    {
        $statement = DatabaseSchemaInfrastructureOperations01::pdo()->prepare(
            "SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME " .
            "FROM information_schema.KEY_COLUMN_USAGE " .
            "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? " .
            "AND REFERENCED_TABLE_NAME IS NOT NULL " .
            "ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION",
        );
        $statement->execute([$table]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $sourceColumn = self::identifier((string) ($row['COLUMN_NAME'] ?? ''));
            $targetTable = self::identifier((string) ($row['REFERENCED_TABLE_NAME'] ?? ''));
            $targetColumn = self::identifier((string) ($row['REFERENCED_COLUMN_NAME'] ?? ''));
            if ($sourceColumn === '' || $targetTable === '' || $targetColumn === '') {
                continue;
            }
            $out[] = [
                'constraint' => (string) ($row['CONSTRAINT_NAME'] ?? ''),
                'source_column' => $sourceColumn,
                'target_table' => $targetTable,
                'target_column' => $targetColumn,
            ];
        }
        return $out;
    }

    public function foreignKeyTargetClinicId(
        string $table,
        string $column,
        mixed $value,
        string $scopeColumn,
        int $clinicId,
    ): ?int {
        $table = self::identifier($table);
        $column = self::identifier($column);
        $scopeColumn = self::identifier($scopeColumn);
        if ($table === '' || $column === '' || $scopeColumn === '') {
            return null;
        }
        $pdo = DatabaseSchemaInfrastructureOperations01::pdo();
        $statement = $pdo->prepare(
            "SELECT `{$scopeColumn}` FROM `{$table}` WHERE `{$column}`=? AND `{$scopeColumn}`=? LIMIT 1",
        );
        $statement->execute([$value, $clinicId]);
        $found = $statement->fetchColumn();
        if ($found !== false && $found !== null) {
            return (int) $found;
        }
        $statement = $pdo->prepare(
            "SELECT `{$scopeColumn}` FROM `{$table}` WHERE `{$column}`=? LIMIT 1",
        );
        $statement->execute([$value]);
        $found = $statement->fetchColumn();
        return $found === false || $found === null ? null : (int) $found;
    }

    public function appointmentStatuses(int $clinicId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($clinicId <= 0 || $ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = DatabaseSchemaInfrastructureOperations01::pdo()->prepare(
            "SELECT id,status FROM pi_appointments WHERE clinic_id=? AND id IN ({$placeholders})",
        );
        $statement->execute(array_merge([$clinicId], $ids));
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function identifier(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z0-9_]+$/', $value) ? $value : '';
    }
}
''')

write('app/Application/Financial/PatientRevenueSettlementPort.php', r'''<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

interface PatientRevenueSettlementPort
{
    public function requireOpenSession(int $clinicId, int $userId): array;

    public function ensureAdminSafe(int $clinicId, int $userId): int;

    public function createMovement(
        int $clinicId,
        string $type,
        int $amount,
        ?int $from,
        ?int $to,
        ?int $sessionId,
        int $userId,
        string $title,
        string $paymentMethod = '',
        string $notes = '',
        string $status = 'confirmed',
        string $sourceEntity = '',
        int $sourceId = 0,
    ): int;
}
''')

write('app/Runtime/Financial/PatientRevenueSettlementAdapter.php', r'''<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Financial;

use Prontoo\Application\Financial\PatientRevenueSettlementPort;

final class PatientRevenueSettlementAdapter implements PatientRevenueSettlementPort
{
    public function requireOpenSession(int $clinicId, int $userId): array
    {
        return FinancialRuntimeOperations07::financial_require_open_session($clinicId, $userId);
    }

    public function ensureAdminSafe(int $clinicId, int $userId): int
    {
        return FinancialRuntimeOperations03::financial_ensure_admin_safe($clinicId, $userId);
    }

    public function createMovement(
        int $clinicId,
        string $type,
        int $amount,
        ?int $from,
        ?int $to,
        ?int $sessionId,
        int $userId,
        string $title,
        string $paymentMethod = '',
        string $notes = '',
        string $status = 'confirmed',
        string $sourceEntity = '',
        int $sourceId = 0,
    ): int {
        return FinancialRuntimeOperations06::financial_create_movement(
            $clinicId,
            $type,
            $amount,
            $from,
            $to,
            $sessionId,
            $userId,
            $title,
            $paymentMethod,
            $notes,
            $status,
            $sourceEntity,
            $sourceId,
        );
    }
}
''')

write('app/Application/SecurityAccess/SecurityAccessPersistencePort.php', r'''<?php
declare(strict_types=1);

namespace Prontoo\Application\SecurityAccess;

interface SecurityAccessPersistencePort
{
    public function hasConfig(): bool;

    public function value(string $sql, array $params = []): mixed;

    public function secretKey(): string;
}
''')

write('app/Infrastructure/SecurityAccess/PdoSecurityAccessPersistence.php', r'''<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SecurityAccess;

use Prontoo\Application\SecurityAccess\SecurityAccessPersistencePort;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01;
use Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01;

final class PdoSecurityAccessPersistence implements SecurityAccessPersistencePort
{
    public function hasConfig(): bool
    {
        return SupportFoundationInfrastructureOperations01::has_cfg();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $statement = DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        return $value === false ? null : $value;
    }

    public function secretKey(): string
    {
        try {
            $value = $this->value("SELECT meta_value FROM pi_meta WHERE meta_key='app_secret'");
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        } catch (\Throwable) {
        }
        return (string) (SupportFoundationInfrastructureOperations01::cfg()['secret'] ?? 'prontoo');
    }
}
''')

write('app/Runtime/SecurityAccess/SecurityAccessComposition.php', r'''<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SecurityAccess;

use Prontoo\Application\SecurityAccess\SecurityAccessPersistencePort;
use Prontoo\Infrastructure\SecurityAccess\PdoSecurityAccessPersistence;

final class SecurityAccessComposition
{
    private static ?SecurityAccessPersistencePort $persistence = null;

    private function __construct()
    {
    }

    public static function persistence(): SecurityAccessPersistencePort
    {
        return self::$persistence ??= new PdoSecurityAccessPersistence();
    }

    public static function configure(SecurityAccessPersistencePort $persistence): void
    {
        self::$persistence = $persistence;
    }
}
''')

write('app/Infrastructure/Database/TenantIntegrityInspector.php', r'''<?php
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
            $table = strtolower(trim((string) ($row['tenant_table_name'] ?? $row['TABLE_NAME'] ?? '')));
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
''')

# Bootstrap and canonical startup: remove the generic operation registry and bind one typed invariant port.
replace('app/prontoo.php', '\\Prontoo\\Runtime\\Architecture\\OperationRegistry::register();\n', "\\Prontoo\\Core\\Invariant\\InvariantRuntimeBinding::configure(\n    new \\Prontoo\\Runtime\\Invariant\\InvariantRuntimeAdapter(),\n);\n")
replace('app/bootstrap_architecture.php', "    __DIR__ . '/Core/Database/SchemaHardening.php',\n", '')
replace('app/bootstrap_architecture.php', "    __DIR__ . '/Core/Database/TenantIntegrity.php',\n", '')
replace('app/bootstrap_architecture.php', "    __DIR__ . '/Core/Invariant/Decision.php',\n", "    __DIR__ . '/Core/Invariant/Decision.php',\n    __DIR__ . '/Core/Invariant/InvariantRuntimePort.php',\n    __DIR__ . '/Core/Invariant/InvariantRuntimeBinding.php',\n")

# Tenant registry becomes pure; Runtime resolves the model clinic and explicitly configures the cache.
regex('app/Core/Tenant/TenantRegistry.php', r'''    public static function modelClinicId\(\): int\n    \{.*?    public static function resetModelClinicCache\(\): void\n    \{\n\n        self::\$modelClinicCache = null;\n    \}\n''', r'''    public static function configureModelClinicId(int $clinicId): void
    {
        self::$modelClinicCache = max(0, $clinicId);
    }

    public static function modelClinicId(): int
    {
        return self::$modelClinicCache ?? 0;
    }

    public static function resetModelClinicCache(): void
    {
        self::$modelClinicCache = null;
    }
''')

boot = read('app/Runtime/Boot/RuntimeBootCoordinator.php')
needle = "    private static function executeReadinessChecks(\n"
if boot.count(needle) != 1:
    raise RuntimeError('RuntimeBootCoordinator: ponto de configuração de tenant não localizado')
method = r'''    private static function configureModelClinic(): void
    {
        $clinicId = (int) (\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
            "SELECT c.id FROM pi_clinics c LEFT JOIN pi_users owner_user ON owner_user.id=c.owner_user_id LEFT JOIN pi_users manager_user ON manager_user.id=c.manager_user_id WHERE c.subscription_status='exempt' AND (COALESCE(owner_user.is_global_admin,0)=1 OR COALESCE(manager_user.is_global_admin,0)=1) ORDER BY c.id ASC LIMIT 1",
        ) ?: 0);
        \Prontoo\Core\Tenant\TenantRegistry::configureModelClinicId($clinicId);
    }

'''
boot = boot.replace(needle, method + needle)
boot = boot.replace("        \\Prontoo\\Runtime\\DatabaseSchema\\DatabaseSchemaRuntimeOperations01::ensure_runtime_schema_minimum();\n        $result['steps'][] = 'schema_contract';", "        \\Prontoo\\Runtime\\DatabaseSchema\\DatabaseSchemaRuntimeOperations01::ensure_runtime_schema_minimum();\n        self::configureModelClinic();\n        $result['steps'][] = 'schema_contract';", 1)
boot = boot.replace("            \\Prontoo\\Runtime\\DatabaseSchema\\DatabaseSchemaRuntimeOperations01::ensure_runtime_schema_minimum();\n            $result['steps'][] = 'schema_contract';", "            \\Prontoo\\Runtime\\DatabaseSchema\\DatabaseSchemaRuntimeOperations01::ensure_runtime_schema_minimum();\n            self::configureModelClinic();\n            $result['steps'][] = 'schema_contract';", 1)
write('app/Runtime/Boot/RuntimeBootCoordinator.php', boot)

replace('app/Core/Install/RuntimeContract.php', '\\Prontoo\\Core\\Database\\TenantIntegrity::assertRegistryMatchesSchema($strict);', '\\Prontoo\\Infrastructure\\Database\\TenantIntegrityInspector::assertRegistryMatchesSchema($strict);')

# Pure formatting stays inside Domain.
replace('app/Domain/Documents/DocumentsCompatibilityOperations01.php', "$digits = \\Prontoo\\Core\\Architecture\\OperationGateway::invoke('only_digits', $cpf);", "$digits = preg_replace('/\\D+/', '', $cpf) ?? '';")

# Typed invariant runtime boundary replaces generic calls in Core.
regex('app/Core/Invariant/Relation/ForeignKeyGraph.php', r'''    private static function relationsFor\(string \$table\): array\n    \{.*?    private static function identifier\(string \$value\): string''', r'''    private static function relationsFor(string $table): array
    {
        $table = self::identifier($table);
        if ($table === '') {
            return [];
        }
        if (array_key_exists($table, self::$relations)) {
            return self::$relations[$table];
        }
        $port = \Prontoo\Core\Invariant\InvariantRuntimeBinding::port();
        if ($port === null) {
            return self::$relations[$table] = [];
        }
        try {
            return self::$relations[$table] = $port->foreignKeyRelations($table);
        } catch (\Throwable $error) {
            error_log('[Prontoo invariant relation graph] metadados indisponíveis para ' . $table . ': ' . $error->getMessage());
            return self::$relations[$table] = [];
        }
    }

    private static function targetClinicId(
        string $table,
        string $column,
        mixed $value,
        int $clinicId,
    ): ?int {
        $table = self::identifier($table);
        $column = self::identifier($column);
        $scopeColumn = TenantRegistry::scopeColumn($table);
        $scopeColumn = is_string($scopeColumn) ? self::identifier($scopeColumn) : '';
        $port = \Prontoo\Core\Invariant\InvariantRuntimeBinding::port();
        if ($table === '' || $column === '' || $scopeColumn === '' || $port === null) {
            return null;
        }
        return $port->foreignKeyTargetClinicId($table, $column, $value, $scopeColumn, $clinicId);
    }

    private static function identifier(string $value): string''')
regex('app/Core/Invariant/Relation/ForeignKeyGraph.php', r'''    private static function deny\(string \$key, string \$sql, string \$detail\): never\n    \{.*?        throw new \\ProntooHttpError\(''', r'''    private static function deny(string $key, string $sql, string $detail): never
    {
        $port = \Prontoo\Core\Invariant\InvariantRuntimeBinding::port();
        if ($port !== null) {
            $port->recordScopeViolation($key, $sql, $detail);
        } else {
            error_log(
                '[Prontoo invariant relation violation] ' . $key . ' | ' .
                    hash('sha256', preg_replace('/\\s+/', ' ', trim($sql)) ?? $sql),
            );
        }
        throw new \\ProntooHttpError(''')

mutation = read('app/Core/Invariant/Mutation/MutationInvariant.php')
mutation = re.sub(r'''        \$readOnly = \\Prontoo\\Core\\Architecture\\OperationGateway::has\('clinic_read_only_db'\)\n            \? \(bool\) \\Prontoo\\Core\\Architecture\\OperationGateway::invoke\('clinic_read_only_db', \$clinicId\)\n            : false;''', "        $runtimePort = \\Prontoo\\Core\\Invariant\\InvariantRuntimeBinding::port();\n        $readOnly = $runtimePort?->clinicReadOnly($clinicId) ?? false;", mutation, count=1)
mutation = re.sub(r'''        if \(\\Prontoo\\Core\\Architecture\\OperationGateway::has\('record_scope_violation'\)\) \{\n            \\Prontoo\\Core\\Architecture\\OperationGateway::invoke\('record_scope_violation', \$key, \$sql, \$detail\);\n        \} else \{''', "        $runtimePort = \\Prontoo\\Core\\Invariant\\InvariantRuntimeBinding::port();\n        if ($runtimePort !== null) {\n            $runtimePort->recordScopeViolation($key, $sql, $detail);\n        } else {", mutation, count=1)
write('app/Core/Invariant/Mutation/MutationInvariant.php', mutation)

task = read('app/Core/Invariant/Context/TaskContextInvariant.php')
task = re.sub(r'''        if \(\\Prontoo\\Core\\Architecture\\OperationGateway::has\('record_scope_violation'\)\) \{\n            \\Prontoo\\Core\\Architecture\\OperationGateway::invoke\('record_scope_violation',\s*\n                "task_context_" \. \$key,\n                \$sql,\n                "Valor estrutural de Tarefas/Avisos recusado pelo contrato especializado\.",\n            \);\n        \}''', "        $runtimePort = \\Prontoo\\Core\\Invariant\\InvariantRuntimeBinding::port();\n        if ($runtimePort !== null) {\n            $runtimePort->recordScopeViolation(\n                'task_context_' . $key,\n                $sql,\n                'Valor estrutural de Tarefas/Avisos recusado pelo contrato especializado.',\n            );\n        }", task, count=1)
write('app/Core/Invariant/Context/TaskContextInvariant.php', task)

appt = read('app/Core/Invariant/Workflow/AppointmentWorkflow.php')
appt = re.sub(r'''        \$placeholders = implode\(",", array_fill\(0, count\(\$ids\), "\?"\)\);\n        \$statement = \\Prontoo\\Core\\Architecture\\OperationGateway::invoke\('pdo', \)->prepare\(\n            "SELECT id,status FROM pi_appointments WHERE clinic_id=\? AND id IN \(\{\$placeholders\}\)",\n        \);\n        \$statement->execute\(array_merge\(\[\$clinicId\], \$ids\)\);\n        \$rows = \$statement->fetchAll\(\\PDO::FETCH_ASSOC\) \?: \[\];''', "        $runtimePort = \\Prontoo\\Core\\Invariant\\InvariantRuntimeBinding::port();\n        $rows = $runtimePort?->appointmentStatuses($clinicId, $ids) ?? [];", appt, count=1)
appt = re.sub(r'''        if \(\\Prontoo\\Core\\Architecture\\OperationGateway::has\('record_scope_violation'\)\) \{\n            \\Prontoo\\Core\\Architecture\\OperationGateway::invoke\('record_scope_violation',\s*\n                \$key,\n                \$sql,\n                "Transição da Jornada do Paciente recusada pelo autômato formal\.",\n            \);\n        \}''', "        $runtimePort = \\Prontoo\\Core\\Invariant\\InvariantRuntimeBinding::port();\n        if ($runtimePort !== null) {\n            $runtimePort->recordScopeViolation(\n                $key,\n                $sql,\n                'Transição da Jornada do Paciente recusada pelo autômato formal.',\n            );\n        }", appt, count=1)
write('app/Core/Invariant/Workflow/AppointmentWorkflow.php', appt)

# Infrastructure adapters use their concrete PDO instead of a generic service locator.
audit = read('app/Infrastructure/Audit/AuditChain.php')
audit = audit.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('one', ", 'self::one(')
audit = audit.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('q', ", 'self::q(')
write('app/Infrastructure/Audit/AuditChain.php', audit)
insert_before_last_brace('app/Infrastructure/Audit/AuditChain.php', r'''    private static function one(string $sql, array $params = []): ?array
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return is_array($row) ? $row : null;
    }

    private static function q(string $sql, array $params = []): \PDOStatement
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

''')

for path in ['app/Infrastructure/Patients/PdoPatientTabCommandRepository.php', 'app/Infrastructure/Patients/PdoPatientContactCommandRepository.php']:
    s = read(path)
    s = s.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('one', ", 'self::one(')
    s = s.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('q', ", 'self::q(')
    write(path, s)
    insert_before_last_brace(path, r'''    private static function one(string $sql, array $params = []): ?array
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return is_array($row) ? $row : null;
    }

    private static function q(string $sql, array $params = []): \PDOStatement
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

''')

revenue = read('app/Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php')
revenue = revenue.replace('use Prontoo\\Application\\Financial\\PatientRevenueReceiptPort;\n', 'use Prontoo\\Application\\Financial\\PatientRevenueReceiptPort;\nuse Prontoo\\Application\\Financial\\PatientRevenueSettlementPort;\n')
revenue = revenue.replace('final class PdoPatientRevenueReceiptRepository implements PatientRevenueReceiptPort\n{\n', 'final class PdoPatientRevenueReceiptRepository implements PatientRevenueReceiptPort\n{\n    public function __construct(private PatientRevenueSettlementPort $settlement)\n    {\n    }\n\n')
revenue = revenue.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('one', ", '$this->one(')
revenue = revenue.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('val', ", '$this->val(')
revenue = revenue.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('q', ", '$this->q(')
revenue = revenue.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('financial_require_open_session', ", '$this->settlement->requireOpenSession(')
revenue = revenue.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('financial_ensure_admin_safe', ", '$this->settlement->ensureAdminSafe(')
revenue = revenue.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('financial_create_movement', ", '$this->settlement->createMovement(')
write('app/Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php', revenue)
insert_before_last_brace('app/Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php', r'''    private function one(string $sql, array $params = []): ?array
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return is_array($row) ? $row : null;
    }

    private function val(string $sql, array $params = []): mixed
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        return $value === false ? null : $value;
    }

    private function q(string $sql, array $params = []): \PDOStatement
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

''')

financial_comp = read('app/Runtime/Financial/FinancialComposition.php')
financial_comp = financial_comp.replace('new PdoPatientRevenueReceiptRepository(),', 'new PdoPatientRevenueReceiptRepository(new PatientRevenueSettlementAdapter()),')
write('app/Runtime/Financial/FinancialComposition.php', financial_comp)

# Authorization persistence is explicit: Infrastructure owns PDO, Runtime supplies the permission policy resolver.
auth = read('app/Infrastructure/Authorization/RuntimeCapabilityProvider.php')
auth = auth.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('one', ", 'self::one(')
auth = auth.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('q', ", 'self::q(')
auth = re.sub(r'''            if \(\$scope === 'global'\) \{\n                if \(!\\Prontoo\\Core\\Architecture\\OperationGateway::has\('one'\)\) \{\n                    throw new \\RuntimeException\('Leitura de credencial global indisponível\.'\);\n                \}\n''', "            if ($scope === 'global') {\n", auth, count=1)
auth = re.sub(r'''            if \(\$clinicId <= 0 \|\| !\\Prontoo\\Core\\Architecture\\OperationGateway::has\('q'\)\) \{\n                throw new \\RuntimeException\('Leitura de vínculo clínico indisponível\.'\);\n            \}\n''', "            if ($clinicId <= 0) {\n                throw new \\RuntimeException('Leitura de vínculo clínico indisponível.');\n            }\n", auth, count=1)
auth = re.sub(r'''        if \(!\\Prontoo\\Core\\Architecture\\OperationGateway::has\('permission_rules_for_role'\)\) \{\n            return \$this->permissionCache\[\$key\] = \[\];\n        \}\n        try \{\n            \$matrix = \\Prontoo\\Core\\Architecture\\OperationGateway::invoke\('permission_rules_for_role', \$role, \$clinicId\);\n            return \$this->permissionCache\[\$key\] = is_array\(\$matrix\) \? \$matrix : \[\];\n        \} catch \(\\Throwable \$error\) \{\n            error_log\('\[Prontoo layered capability\] falha ao resolver capacidade: ' \. \$error->getMessage\(\)\);\n            return \$this->permissionCache\[\$key\] = \[\];\n        \}\n''', "        return $this->permissionCache[$key] = [];\n", auth, count=1)
write('app/Infrastructure/Authorization/RuntimeCapabilityProvider.php', auth)
insert_before_last_brace('app/Infrastructure/Authorization/RuntimeCapabilityProvider.php', r'''    private static function one(string $sql, array $params = []): ?array
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return is_array($row) ? $row : null;
    }

    private static function q(string $sql, array $params = []): \PDOStatement
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

''')
replace('app/Runtime/LayeredKernel.php', 'new AuthorizationService(new RuntimeCapabilityProvider()),', "new AuthorizationService(new RuntimeCapabilityProvider(\n                null,\n                static fn(string $role, int $clinicId): array =>\n                    \\Prontoo\\Runtime\\UsersPermissions\\UsersPermissionsRuntimeOperations02::permission_rules_for_role($role, $clinicId),\n            )),")

# Security runtime gets a small typed persistence port; tests can replace the port without a global operation registry.
security = read('app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations01.php')
security = security.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('val', ", '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessComposition::persistence()->value(')
security = security.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('secret_key', )", '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessComposition::persistence()->secretKey()')
security = security.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('secret_key')", '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessComposition::persistence()->secretKey()')
security = security.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('has_cfg')", '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessComposition::persistence()->hasConfig()')
write('app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations01.php', security)

infra_security = read('app/Infrastructure/SecurityAccess/SecurityAccessInfrastructureOperations01.php')
infra_security = infra_security.replace("return $expected > 0 ? $expected : \\Prontoo\\Core\\Architecture\\OperationGateway::invoke('session_clinic_scope_id', );", 'return $expected > 0 ? $expected : 0;')
write('app/Infrastructure/SecurityAccess/SecurityAccessInfrastructureOperations01.php', infra_security)

runtime3 = read('app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations03.php')
runtime3 = runtime3.replace('$cid = \\Prontoo\\Infrastructure\\SecurityAccess\\SecurityAccessInfrastructureOperations01::scope_guard_active_clinic_id();', "$cid = \\Prontoo\\Infrastructure\\SecurityAccess\\SecurityAccessInfrastructureOperations01::scope_guard_active_clinic_id();\n        if ($cid <= 0) {\n            $cid = \\Prontoo\\Runtime\\Tenant\\SessionTenantAccess::clinicId();\n        }")
write('app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations03.php', runtime3)

pi = read('app/Infrastructure/Integrity/PiIntegrity.php')
pi = re.sub(r'''    private static function secret\(\): string\n    \{.*?    \}\n(?=\})''', r'''    private static function secret(): string
    {
        try {
            if (\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
                $statement = self::pdo()->query("SELECT meta_value FROM pi_meta WHERE meta_key='app_secret' LIMIT 1");
                $value = $statement ? $statement->fetchColumn() : false;
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        } catch (\Throwable) {
        }
        try {
            return (string) (\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg()['secret'] ?? 'prontoo-integrity');
        } catch (\Throwable) {
            return 'prontoo-integrity';
        }
    }
''', pi, count=1, flags=re.S)
write('app/Infrastructure/Integrity/PiIntegrity.php', pi)

# Entrypoint telemetry calls the concrete Runtime boundary directly.
br = read('br/index.php')
br = br.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('telemetry_route_start_marker', ", '\\Prontoo\\Runtime\\SupportTelemetry\\SupportTelemetryRuntimeOperations01::telemetry_route_start_marker(')
br = br.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('telemetry_route_finish_marker', )", '\\Prontoo\\Runtime\\SupportTelemetry\\SupportTelemetryRuntimeOperations01::telemetry_route_finish_marker()')
write('br/index.php', br)

telemetry_check = read('tools/page-load-telemetry-contract-check')
telemetry_check = re.sub(r'''    \$gatewayFinish = str_contains\(\n        \$source,\n        "\\\\Prontoo\\\\Core\\\\Architecture\\\\OperationGateway::invoke\('telemetry_route_finish_marker',",\n    \);\n    \$telemetryContractAssert\(\n        \$nativeFinish \|\| \$gatewayFinish,''', "    $telemetryContractAssert(\n        $nativeFinish,", telemetry_check, count=1)
telemetry_check = telemetry_check.replace('    $gatewayFinish,\n', '')
write('tools/page-load-telemetry-contract-check', telemetry_check)

# Security regression now injects a typed fake persistence adapter.
sec_test = read('tools/security-regression-check.php')
sec_test = re.sub(r'''\\Prontoo\\Runtime\\Architecture\\OperationRegistry::register\(\);\n\\Prontoo\\Core\\Architecture\\OperationGateway::override\('has_cfg'.*?\n\}\);\n\n''', r'''\Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::configure(
    new class implements \Prontoo\Application\SecurityAccess\SecurityAccessPersistencePort {
        public function hasConfig(): bool
        {
            return (bool) $GLOBALS['prontoo_test_has_cfg'];
        }

        public function value(string $sql, array $params = []): mixed
        {
            return val($sql, $params);
        }

        public function secretKey(): string
        {
            return str_repeat('s', 48);
        }
    },
);

''', sec_test, count=1, flags=re.S)
write('tools/security-regression-check.php', sec_test)

# Critical integration smoke references concrete boundaries explicitly.
critical = read('tools/critical-runtime-smoke-check')
critical = critical.replace("\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('pdo')", '\\Prontoo\\Infrastructure\\DatabaseSchema\\DatabaseSchemaInfrastructureOperations01::pdo()')
map_ops = {
    'active_clinic_roles_for_user': r'\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::active_clinic_roles_for_user',
    'login_resolve_user_credential': r'\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_resolve_user_credential',
    'user_auth_generation_ensure': r'\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_ensure',
    'user_auth_generation_rotate': r'\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_rotate',
    'user_auth_generation_current': r'\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_current',
    'mfa_totp_secret_generate': r'\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_secret_generate',
    'mfa_record_save': r'\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_save',
    'mfa_secret_encrypt': r'\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_secret_encrypt',
    'mfa_enrollment_state': r'\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state',
    'mfa_record_load': r'\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_load',
    'mfa_secret_decrypt': r'\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_secret_decrypt',
    'val': r'\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val',
}
for op, target in map_ops.items():
    critical = critical.replace(f"\\Prontoo\\Core\\Architecture\\OperationGateway::invoke('{op}', ", target + '(')
critical = critical.replace('new \\Prontoo\\Infrastructure\\Financial\\PdoPatientRevenueReceiptRepository(),', 'new \\Prontoo\\Infrastructure\\Financial\\PdoPatientRevenueReceiptRepository(\n            new \\Prontoo\\Runtime\\Financial\\PatientRevenueSettlementAdapter(),\n        ),')
write('tools/critical-runtime-smoke-check', critical)

http = read('tools/http-runtime-smoke-check')
http = http.replace("$pdo = \\Prontoo\\Core\\Architecture\\OperationGateway::invoke('pdo');", '$pdo = \\Prontoo\\Infrastructure\\DatabaseSchema\\DatabaseSchemaInfrastructureOperations01::pdo();')
write('tools/http-runtime-smoke-check', http)

# Composition root policy recognizes the new security composition root.
replace('tools/composition-root-check', "    'app/Runtime/Financial/FinancialComposition.php',\n", "    'app/Runtime/Financial/FinancialComposition.php',\n    'app/Runtime/SecurityAccess/SecurityAccessComposition.php',\n")

# Gateway debt is closed permanently.
write('app/architecture.gateway-budget.json', '''{
    "schema": "prontoo-operation-gateway-budget-v1",
    "policy": "operation_gateway_is_forbidden_and_dependencies_must_be_typed_explicit_boundaries",
    "max_invoke_calls": 0
}
''')

checker = read('tools/operation-gateway-budget-check')
checker = checker.replace("$result = [\n    'ok' => $total <= $maximum,", "$forbiddenFiles = array_values(array_filter([\n    'app/Core/Architecture/OperationGateway.php',\n    'app/Runtime/Architecture/OperationRegistry.php',\n], static fn(string $path): bool => is_file($root . '/' . $path)));\n$result = [\n    'ok' => $total <= $maximum && $forbiddenFiles === [],")
checker = checker.replace("    'top_files' => array_slice($files, 0, 12, true),", "    'top_files' => array_slice($files, 0, 12, true),\n    'forbidden_gateway_files' => $forbiddenFiles,")
write('tools/operation-gateway-budget-check', checker)

# Delete obsolete service locator and misplaced/unused Core effects.
for path in [
    'app/Core/Architecture/OperationGateway.php',
    'app/Runtime/Architecture/OperationRegistry.php',
    'app/Core/Database/SchemaHardening.php',
    'app/Core/Database/TenantIntegrity.php',
]:
    p = ROOT / path
    if not p.exists():
        raise RuntimeError(f'{path}: arquivo esperado para remoção não existe')
    p.unlink()

# No executable source may retain the generic gateway after this phase.
remaining = []
for base in ['app', 'br', 'tools', 'index.php', 'install.php', 'cron']:
    p = ROOT / base
    paths = [p] if p.is_file() else list(p.rglob('*')) if p.exists() else []
    for file in paths:
        if not file.is_file() or file == Path(__file__):
            continue
        if file.suffix not in {'.php', ''}:
            continue
        text = file.read_text(errors='ignore')
        if 'OperationGateway::' in text or 'OperationRegistry::' in text:
            remaining.append(str(file.relative_to(ROOT)))
if remaining:
    raise RuntimeError('Referências executáveis residuais ao gateway: ' + ', '.join(sorted(remaining)))

print('phase11 transformations complete')
