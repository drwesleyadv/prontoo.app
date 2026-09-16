<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\DatabaseSchema;

use PDO;
use Prontoo\Core\Database\SchemaMutationLock;
use RuntimeException;

final class KnownSchemaUpgrade
{
    private const UPGRADE_ID = 'agenda_notes_timed_reservations_1_9_15_9';
    private const PREVIOUS_CONTRACT = '28515f46af3081e5ac0dded5723e6d2d7ceddec11e9c6f782975f31931a69d33';
    private const DATABASE_LOCK = 'prontoo-schema-upgrade-agenda-notes-1-9-15-9';

    private function __construct()
    {
    }

    public static function attempt(string $storedContract, string $expectedContract): bool
    {
        if (
            $storedContract === '' ||
            $expectedContract === '' ||
            hash_equals($expectedContract, $storedContract) ||
            !hash_equals(self::PREVIOUS_CONTRACT, $storedContract)
        ) {
            return false;
        }

        $connection = DatabaseSchemaInfrastructureOperations01::pdo();
        if (!self::acquireLock($connection)) {
            throw new RuntimeException('Não foi possível obter a trava da atualização estrutural conhecida.');
        }

        try {
            $currentContract = self::metaValue($connection, 'schema_contract_hash');
            if (hash_equals($expectedContract, $currentContract)) {
                return true;
            }
            if (!hash_equals(self::PREVIOUS_CONTRACT, $currentContract)) {
                return false;
            }

            SchemaMutationLock::runForTrustedUpgrade(
                self::UPGRADE_ID,
                static function () use ($connection, $expectedContract): void {
                    self::upgradeAgendaNotes($connection);
                    DatabaseSchemaInfrastructureOperations03::schema_validate_complete();
                    self::writeMeta($connection, 'schema_revision', (string) PRONTOO_SCHEMA_REV);
                    self::writeMeta($connection, 'schema_contract_hash', $expectedContract);
                    DatabaseSchemaInfrastructureOperations03::schema_mark_ready();
                },
            );
            return true;
        } finally {
            self::releaseLock($connection);
        }
    }

    private static function upgradeAgendaNotes(PDO $connection): void
    {
        if (!DatabaseSchemaInfrastructureOperations02::db_table_exists('pi_agenda_notes')) {
            throw new RuntimeException('Schema predecessor inválido: pi_agenda_notes ausente.');
        }

        self::ensureColumn(
            $connection,
            'doctor_user_id',
            'int unsigned',
            'ALTER TABLE `pi_agenda_notes` ADD COLUMN `doctor_user_id` int UNSIGNED DEFAULT NULL AFTER `clinic_id`',
        );
        self::ensureColumn(
            $connection,
            'start_at',
            'bigint unsigned',
            'ALTER TABLE `pi_agenda_notes` ADD COLUMN `start_at` bigint UNSIGNED DEFAULT NULL AFTER `note_date`',
        );
        self::ensureColumn(
            $connection,
            'end_at',
            'bigint unsigned',
            'ALTER TABLE `pi_agenda_notes` ADD COLUMN `end_at` bigint UNSIGNED DEFAULT NULL AFTER `start_at`',
        );
        self::ensureIndex(
            $connection,
            'idx_agenda_notes_timed',
            ['clinic_id', 'doctor_user_id', 'note_date', 'start_at', 'end_at', 'deleted_at', 'id'],
            'ALTER TABLE `pi_agenda_notes` ADD KEY `idx_agenda_notes_timed` (`clinic_id`,`doctor_user_id`,`note_date`,`start_at`,`end_at`,`deleted_at`,`id`)',
        );
        self::ensureIndex(
            $connection,
            'idx_fk_agenda_notes_doctor_user_id',
            ['doctor_user_id'],
            'ALTER TABLE `pi_agenda_notes` ADD KEY `idx_fk_agenda_notes_doctor_user_id` (`doctor_user_id`)',
        );
        self::ensureForeignKey(
            $connection,
            'fk_agenda_notes_doctor_user',
            'doctor_user_id',
            'pi_users',
            'id',
            'SET NULL',
            'ALTER TABLE `pi_agenda_notes` ADD CONSTRAINT `fk_agenda_notes_doctor_user` FOREIGN KEY (`doctor_user_id`) REFERENCES `pi_users` (`id`) ON DELETE SET NULL',
        );
    }

    private static function ensureColumn(PDO $connection, string $column, string $columnType, string $ddl): void
    {
        $statement = $connection->prepare(
            'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1',
        );
        $statement->execute(['pi_agenda_notes', $column]);
        $definition = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if ($definition === false) {
            DatabaseSchemaInfrastructureOperations02::run_schema_sql($ddl);
            return;
        }

        if (
            strtolower((string) ($definition['COLUMN_TYPE'] ?? '')) !== $columnType ||
            strtoupper((string) ($definition['IS_NULLABLE'] ?? '')) !== 'YES' ||
            ($definition['COLUMN_DEFAULT'] ?? null) !== null
        ) {
            throw new RuntimeException('Schema predecessor inválido: pi_agenda_notes.' . $column . ' diverge do upgrade conhecido.');
        }
    }

    private static function ensureIndex(PDO $connection, string $index, array $columns, string $ddl): void
    {
        $statement = $connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=? ORDER BY SEQ_IN_INDEX ASC',
        );
        $statement->execute(['pi_agenda_notes', $index]);
        $actual = array_map(
            static fn(array $row): string => (string) ($row['COLUMN_NAME'] ?? ''),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
        $statement->closeCursor();

        if ($actual === []) {
            DatabaseSchemaInfrastructureOperations02::run_schema_sql($ddl);
            return;
        }
        if ($actual !== $columns) {
            throw new RuntimeException('Schema predecessor inválido: índice ' . $index . ' diverge do upgrade conhecido.');
        }
    }

    private static function ensureForeignKey(
        PDO $connection,
        string $constraint,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $deleteRule,
        string $ddl,
    ): void {
        $statement = $connection->prepare(
            'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE FROM information_schema.key_column_usage k JOIN information_schema.referential_constraints r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.CONSTRAINT_NAME=? LIMIT 1',
        );
        $statement->execute(['pi_agenda_notes', $constraint]);
        $definition = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if ($definition === false) {
            DatabaseSchemaInfrastructureOperations02::run_schema_sql($ddl);
            return;
        }
        if (
            (string) ($definition['COLUMN_NAME'] ?? '') !== $column ||
            (string) ($definition['REFERENCED_TABLE_NAME'] ?? '') !== $referencedTable ||
            (string) ($definition['REFERENCED_COLUMN_NAME'] ?? '') !== $referencedColumn ||
            strtoupper((string) ($definition['DELETE_RULE'] ?? '')) !== $deleteRule
        ) {
            throw new RuntimeException('Schema predecessor inválido: constraint ' . $constraint . ' diverge do upgrade conhecido.');
        }
    }

    private static function metaValue(PDO $connection, string $key): string
    {
        $statement = $connection->prepare('SELECT meta_value FROM pi_meta WHERE meta_key=?');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        return $value === false ? '' : (string) $value;
    }

    private static function writeMeta(PDO $connection, string $key, string $value): void
    {
        $statement = $connection->prepare(
            'INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value),updated_at=VALUES(updated_at)',
        );
        $statement->execute([$key, $value, time()]);
        $statement->closeCursor();
    }

    private static function acquireLock(PDO $connection): bool
    {
        $statement = $connection->prepare('SELECT GET_LOCK(?, 15)');
        $statement->execute([self::DATABASE_LOCK]);
        $acquired = (int) $statement->fetchColumn() === 1;
        $statement->closeCursor();
        return $acquired;
    }

    private static function releaseLock(PDO $connection): void
    {
        $statement = $connection->prepare('SELECT RELEASE_LOCK(?)');
        $statement->execute([self::DATABASE_LOCK]);
        $statement->closeCursor();
    }
}
