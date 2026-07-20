<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Database;

final class SeqContract
{
    private function __construct() {}

    public static function assert(\PDO $pdo): void
    {
        if (self::tableExists($pdo, 'pi_sequence')) {
            throw new \RuntimeException('A tabela legada pi_sequence não pode existir no schema limpo.');
        }
        $rows = $pdo->query(
            "SELECT t.TABLE_NAME,c.IS_NULLABLE,c.COLUMN_DEFAULT,c.EXTRA " .
            "FROM information_schema.tables t " .
            "LEFT JOIN information_schema.columns c ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME='Seq' " .
            "WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_TYPE='BASE TABLE' AND t.TABLE_NAME LIKE 'pi\\_%' ESCAPE '\\\\' " .
            "ORDER BY t.TABLE_NAME",
        )?->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            throw new \RuntimeException('Nenhuma tabela Prontoo foi encontrada para validar Seq.');
        }
        foreach ($rows as $row) {
            $table = (string) ($row['TABLE_NAME'] ?? '');
            $nullable = strtoupper((string) ($row['IS_NULLABLE'] ?? ''));
            $default = strtolower(preg_replace('/\s+/', '', (string) ($row['COLUMN_DEFAULT'] ?? '')) ?? '');
            if ($nullable !== 'NO') {
                throw new \RuntimeException("Coluna Seq ausente ou anulável em {$table}.");
            }
            if (!str_contains($default, 'uuid_short()')) {
                throw new \RuntimeException("Default nativo de Seq ausente em {$table}.");
            }
            if (!self::hasUniqueSeq($pdo, $table)) {
                throw new \RuntimeException("Índice único de Seq ausente em {$table}.");
            }
        }
    }

    public static function max(\PDO $pdo): int
    {
        $tables = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.columns " .
            "WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='Seq' AND TABLE_NAME LIKE 'pi\\_%' ESCAPE '\\\\' " .
            "ORDER BY TABLE_NAME",
        )?->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $selects = [];
        foreach ($tables as $table) {
            $table = (string) $table;
            if (!preg_match('/^pi_[a-z0-9_]+$/', $table)) {
                continue;
            }
            $selects[] = "SELECT COALESCE(MAX(`Seq`),0) AS max_seq FROM `{$table}`";
        }
        if ($selects === []) {
            return 0;
        }
        $value = $pdo->query(
            'SELECT COALESCE(MAX(max_seq),0) FROM (' . implode(' UNION ALL ', $selects) . ') seq_union',
        )?->fetchColumn();
        return max(0, (int) $value);
    }

    private static function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1',
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private static function hasUniqueSeq(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND column_name='Seq' AND non_unique=0 LIMIT 1",
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
