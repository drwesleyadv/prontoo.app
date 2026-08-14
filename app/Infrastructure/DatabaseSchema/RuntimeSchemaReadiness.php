<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\DatabaseSchema;

use Prontoo\Infrastructure\Database\PdoQueryDriver;
use Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01;
use Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01;
use RuntimeException;

final class RuntimeSchemaReadiness
{
    private const PERSISTED_SCHEMA_REVISION = 'prontoo_1_7_20_6_clean_schema_r7_layer2_ledger';

    private static bool $validated = false;

    private function __construct()
    {
    }

    public static function ensure(): void
    {
        if (self::$validated || !SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        $expectedRevision = (string) PRONTOO_SCHEMA_REV;
        $revision = self::metaValue('schema_revision');
        $contract = self::metaValue('schema_contract_hash');
        if (!self::revisionAccepted($revision, $expectedRevision)) {
            throw new RuntimeException('Revisão do banco incompatível com a aplicação.');
        }
        $expectedContract = DatabaseSchemaInfrastructureOperations02::prontoo_schema_contract_hash();
        if (!hash_equals($expectedContract, $contract)) {
            throw new RuntimeException('Contrato do banco incompatível com a aplicação.');
        }
        $ready = json_decode(
            (string) SupportRuntimeInfrastructureOperations01::prontoo_fs_read(
                DatabaseSchemaInfrastructureOperations02::schema_lock_file(),
            ),
            true,
        );
        if (
            !is_array($ready) ||
            !hash_equals($revision, (string) ($ready['revision'] ?? '')) ||
            !hash_equals($expectedContract, (string) ($ready['contract'] ?? ''))
        ) {
            throw new RuntimeException('Marcador do schema instalado é inválido.');
        }
        self::$validated = true;
    }

    private static function revisionAccepted(string $revision, string $expectedRevision): bool
    {
        return hash_equals($expectedRevision, $revision) ||
            hash_equals(self::PERSISTED_SCHEMA_REVISION, $revision);
    }

    private static function metaValue(string $key): string
    {
        $statement = PdoQueryDriver::execute(
            'SELECT meta_value FROM pi_meta WHERE meta_key=?',
            [$key],
        );
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        return $value === false ? '' : (string) $value;
    }
}
