<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Database;

final class CleanInstallReset
{
    private const ELIGIBLE_REVISIONS = [
        'prontoo_1_7_13_1_clean_schema_r6_multirole',
    ];

    private function __construct() {}

    public static function inspect(\PDO $pdo, string $newRevision): array
    {
        $tables = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME",
        )?->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $tables = array_values(array_filter(array_map('strval', $tables)));
        $foreign = array_values(array_filter(
            $tables,
            static fn(string $table): bool => !preg_match('/^pi_[a-z0-9_]+$/', $table),
        ));
        $revision = '';
        if (in_array('pi_meta', $tables, true)) {
            try {
                $revision = trim((string) ($pdo
                    ->query("SELECT meta_value FROM pi_meta WHERE meta_key='schema_revision' LIMIT 1")
                    ?->fetchColumn() ?: ''));
            } catch (\Throwable) {
                $revision = '';
            }
        }
        return [
            'tables' => $tables,
            'foreign_tables' => $foreign,
            'revision' => $revision,
            'already_current' => $revision !== '' && hash_equals($newRevision, $revision),
            'eligible' => $tables === [] || in_array($revision, self::ELIGIBLE_REVISIONS, true),
        ];
    }

    public static function reset(\PDO $pdo, string $newRevision): array
    {
        $state = self::inspect($pdo, $newRevision);
        if ($state['already_current']) {
            return ['reset' => false, 'reason' => 'already_current'] + $state;
        }
        if ($state['foreign_tables'] !== []) {
            throw new \RuntimeException(
                'A limpeza automática foi bloqueada porque o banco contém tabelas externas ao Prontoo: ' .
                    implode(', ', array_slice($state['foreign_tables'], 0, 10)),
            );
        }
        if (!$state['eligible']) {
            throw new \RuntimeException(
                'A limpeza automática foi bloqueada: revisão anterior não reconhecida.',
            );
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (array_reverse($state['tables']) as $table) {
                $pdo->exec('DROP TABLE `' . str_replace('`', '``', (string) $table) . '`');
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        $remaining = (int) ($pdo
            ->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND TABLE_TYPE='BASE TABLE'")
            ?->fetchColumn() ?: 0);
        if ($remaining !== 0) {
            throw new \RuntimeException('A limpeza do banco não terminou em estado vazio.');
        }
        return [
            'reset' => true,
            'previous_revision' => $state['revision'],
            'tables_removed' => count($state['tables']),
        ];
    }
}
