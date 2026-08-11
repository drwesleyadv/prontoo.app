<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\DatabaseSchema;

use Prontoo\Infrastructure\Database\PdoQueryDriver;
use Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01;
use Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01;
use RuntimeException;

final class RuntimeSchemaReadiness
{
    private static bool $validated = false;

    private function __construct()
    {
    }

    public static function ensure(): void
    {
        if (self::$validated || !SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        DatabaseSchemaInfrastructureOperations03::schema_apply_pending_release_migrations();
        $expectedRevision = defined('PRONTOO_SCHEMA_REV')
            ? PRONTOO_SCHEMA_REV
            : 'prontoo_1_7_20_6_clean_schema_r7_layer2_ledger';
        $revision = self::metaValue('schema_revision');
        $contract = self::metaValue('schema_contract_hash');
        if (!hash_equals($expectedRevision, $revision)) {
            throw new RuntimeException('Revisão do banco incompatível com a aplicação.');
        }
        if (!hash_equals(DatabaseSchemaInfrastructureOperations02::prontoo_schema_contract_hash(), $contract)) {
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
            !hash_equals($expectedRevision, (string) ($ready['revision'] ?? '')) ||
            !hash_equals(
                DatabaseSchemaInfrastructureOperations02::prontoo_schema_contract_hash(),
                (string) ($ready['contract'] ?? ''),
            )
        ) {
            throw new RuntimeException('Marcador do schema instalado é inválido.');
        }
        self::$validated = true;
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
