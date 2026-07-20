<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schemaFile = $root . '/app/Database/schema.sql';
$contractFile = $root . '/app/Database/operational-schema.contract.json';
$schema = (string) file_get_contents($schemaFile);
$contract = json_decode((string) file_get_contents($contractFile), true, 512, JSON_THROW_ON_ERROR);

preg_match_all('/CREATE TABLE `([^`]+)` \(.*?\n\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;/s', $schema, $matches, PREG_SET_ORDER);
$blocks = [];
foreach ($matches as $match) {
    $blocks[(string) $match[1]] = (string) $match[0];
}
$errors = [];
if (count($blocks) !== 62) {
    $errors[] = 'schema_table_count:' . count($blocks);
}
if (isset($blocks['pi_sequence'])) {
    $errors[] = 'legacy_sequence_table_present';
}
if (!isset($blocks['pi_action_ledger'])) {
    $errors[] = 'action_ledger_missing';
}
$forbidden = array_merge(
    (array) ($contract['removed_support_tables'] ?? []),
    ['pi_action_proofs'],
);
foreach ($forbidden as $table) {
    if (isset($blocks[(string) $table])) {
        $errors[] = 'removed_support_table_present:' . $table;
    }
    foreach ([$root . '/app', $root . '/cron'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            if (!in_array(strtolower($file->getExtension()), ['php', 'sql', 'json'], true)) {
                continue;
            }
            $path = $file->getPathname();
            if ($path === $contractFile) {
                continue;
            }
            if ($table === 'pi_sequence' && str_ends_with(str_replace('\\', '/', $path), '/app/Infrastructure/Database/SeqContract.php')) {
                continue;
            }
            if (str_contains((string) file_get_contents($path), (string) $table)) {
                $errors[] = 'removed_support_reference:' . $table . ':' . str_replace($root . '/', '', $path);
            }
        }
    }
}
foreach ($blocks as $table => $block) {
    if (!preg_match('/`Seq` bigint UNSIGNED NOT NULL DEFAULT \(UUID_SHORT\(\)\)/', $block)) {
        $errors[] = 'native_seq_default_missing:' . $table;
    }
    if (!preg_match('/UNIQUE KEY `[^`]*seq[^`]*` \(`Seq`\)/i', $block)) {
        $errors[] = 'unique_seq_index_missing:' . $table;
    }
}
$normalize = static function (string $block): string {
    $block = preg_replace(
        '/`Seq` bigint UNSIGNED (?:DEFAULT NULL|NOT NULL DEFAULT \(UUID_SHORT\(\)\))/',
        '`Seq` <native-seq>',
        $block,
    ) ?? $block;
    return trim(preg_replace('/\s+/', ' ', $block) ?? $block);
};
$retained = (array) ($contract['tables'] ?? []);
if (count($retained) !== (int) ($contract['retained_table_count'] ?? -1)) {
    $errors[] = 'operational_contract_count';
}
foreach ($retained as $table => $expectedHash) {
    if (!isset($blocks[$table])) {
        $errors[] = 'retained_table_missing:' . $table;
        continue;
    }
    $actual = hash('sha256', $normalize($blocks[$table]));
    if (!hash_equals((string) $expectedHash, $actual)) {
        $errors[] = 'retained_table_changed:' . $table;
    }
}
$ledger = $blocks['pi_action_ledger'] ?? '';
foreach (['authorization_hash', 'contract_hash', 'mutation_hash', 'mutation_count', 'affected_tables_json', 'mutation_json', 'status', 'finalized_at'] as $column) {
    if (!str_contains($ledger, '`' . $column . '`')) {
        $errors[] = 'action_ledger_column_missing:' . $column;
    }
}
$audit = $blocks['pi_audit'] ?? '';
foreach (['event_key', 'event_label', 'event_icon', 'entity_key', 'entity_label', 'friendly_text', 'ip_hash', 'user_agent'] as $column) {
    if (!str_contains($audit, '`' . $column . '`')) {
        $errors[] = 'audit_inline_column_missing:' . $column;
    }
}

$dbResult = ['executed' => false];
$dsn = getenv('PRONTOO_SCHEMA_DSN') ?: '';
if ($dsn !== '') {
    $pdo = new PDO($dsn, getenv('PRONTOO_SCHEMA_USER') ?: 'root', getenv('PRONTOO_SCHEMA_PASS') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $existing = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'")->fetchColumn();
    if ($existing !== 0) {
        throw new RuntimeException('Banco de teste não está vazio.');
    }
    foreach ($blocks as $block) {
        $pdo->exec($block);
    }
    require_once $root . '/app/Infrastructure/Database/SeqContract.php';
    require_once $root . '/app/Infrastructure/Database/CleanInstallReset.php';
    Prontoo\Infrastructure\Database\SeqContract::assert($pdo);
    $pdo->exec("INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES ('schema_check_a','1',UNIX_TIMESTAMP())");
    $first = (int) $pdo->query("SELECT Seq FROM pi_meta WHERE meta_key='schema_check_a'")->fetchColumn();
    $pdo->exec("INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES ('schema_check_b','1',UNIX_TIMESTAMP())");
    $second = (int) $pdo->query("SELECT Seq FROM pi_meta WHERE meta_key='schema_check_b'")->fetchColumn();
    if ($first <= 0 || $second <= $first) {
        $errors[] = 'native_seq_runtime_order';
    }
    $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'")->fetchColumn();
    if ($tableCount !== 62) {
        $errors[] = 'mysql_table_count:' . $tableCount;
    }
    $mysqlVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $dropAll = static function (PDO $connection): void {
        $tables = $connection->query(
            "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'",
        )->fetchAll(PDO::FETCH_COLUMN);
        $connection->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ((array) $tables as $table) {
                $connection->exec('DROP TABLE `' . str_replace('`', '``', (string) $table) . '`');
            }
        } finally {
            $connection->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    };
    $dropAll($pdo);
    $pdo->exec("CREATE TABLE pi_meta (meta_key varchar(80) PRIMARY KEY, meta_value text, updated_at bigint unsigned NOT NULL DEFAULT 0) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE pi_dummy (id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES ('schema_revision','prontoo_1_7_13_1_clean_schema_r6_multirole',UNIX_TIMESTAMP())");
    $reset = Prontoo\Infrastructure\Database\CleanInstallReset::reset(
        $pdo,
        'prontoo_1_7_20_6_clean_schema_r7_layer2_ledger',
    );
    $afterReset = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'",
    )->fetchColumn();
    if (empty($reset['reset']) || $afterReset !== 0) {
        $errors[] = 'clean_reset_did_not_empty_database';
    }
    $pdo->exec("CREATE TABLE pi_meta (meta_key varchar(80) PRIMARY KEY, meta_value text, updated_at bigint unsigned NOT NULL DEFAULT 0) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE external_table (id int unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES ('schema_revision','prontoo_1_7_13_1_clean_schema_r6_multirole',UNIX_TIMESTAMP())");
    $foreignBlocked = false;
    try {
        Prontoo\Infrastructure\Database\CleanInstallReset::reset(
            $pdo,
            'prontoo_1_7_20_6_clean_schema_r7_layer2_ledger',
        );
    } catch (RuntimeException $error) {
        $foreignBlocked = str_contains($error->getMessage(), 'tabelas externas');
    }
    if (!$foreignBlocked) {
        $errors[] = 'clean_reset_foreign_table_guard';
    }
    $dropAll($pdo);
    $dbResult = [
        'executed' => true,
        'mysql_version' => $mysqlVersion,
        'tables' => $tableCount,
        'first_seq' => $first,
        'second_seq' => $second,
        'clean_reset_tables_removed' => (int) ($reset['tables_removed'] ?? 0),
        'foreign_table_guard' => $foreignBlocked,
    ];
}

$errors = array_values(array_unique($errors));
$result = [
    'ok' => $errors === [],
    'policy' => 'clean-schema-layer2-ledger-v1',
    'tables' => count($blocks),
    'retained_tables_verified' => count($retained),
    'removed_support_tables' => $forbidden,
    'database' => $dbResult,
    'errors' => $errors,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($errors === [] ? 0 : 1);
