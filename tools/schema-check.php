<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$schemaFile = $root . '/app/Database/schema.sql';
$contractFile = $root . '/app/Database/operational-schema.contract.json';
$schema = (string) file_get_contents($schemaFile);
$contract = json_decode((string) file_get_contents($contractFile), true, 512, JSON_THROW_ON_ERROR);

if (!defined('PRONTOO_SCHEMA_REV')) {
    define('PRONTOO_SCHEMA_REV', 'prontoo_clean_schema_r7_layer2_ledger');
}
if (!defined('PRONTOO_MIN_MYSQL_VERSION')) {
    define('PRONTOO_MIN_MYSQL_VERSION', '8.0.30');
}
$GLOBALS['PRONTOO_SCHEMA_CHECK_CFG'] = [];
$GLOBALS['PRONTOO_SCHEMA_CHECK_STORAGE'] = sys_get_temp_dir() . '/prontoo-schema-check-' . getmypid();
$schemaCheckConfigPath = sys_get_temp_dir() . '/prontoo-schema-check-config-' . getmypid() . '.php';
$schemaCheckConfig = [
    'db_host' => (string) (getenv('DB_HOST') ?: '127.0.0.1'),
    'db_name' => (string) (getenv('DB_DATABASE') ?: 'prontoo_schema'),
    'db_user' => (string) (getenv('DB_USERNAME') ?: 'root'),
    'db_pass' => (string) (getenv('DB_PASSWORD') ?: ''),
    'app_env' => 'development',
];
file_put_contents(
    $schemaCheckConfigPath,
    "<?php\nreturn " . var_export($schemaCheckConfig, true) . ";\n",
    LOCK_EX,
);
putenv('PRONTOO_CONFIG_PATH=' . $schemaCheckConfigPath);
if (!is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'cfg'])) {
    function cfg(): array
    {

        return (array) ($GLOBALS['PRONTOO_SCHEMA_CHECK_CFG'] ?? []);
    }
}
if (!is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'storage_path'])) {
    function storage_path(string $path = ''): string
    {

        $base = mb_rtrim((string) ($GLOBALS['PRONTOO_SCHEMA_CHECK_STORAGE'] ?? sys_get_temp_dir()), '/');
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}
if (!is_callable([\Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::class, 'prontoo_fs_chmod'])) {
    function prontoo_fs_chmod(string $path, int $mode, bool $required = true): bool
    {

        return !file_exists($path) || @chmod($path, $mode);
    }
}
if (!is_callable([\Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::class, 'prontoo_fs_unlink'])) {
    function prontoo_fs_unlink(string $path, bool $required = true): bool
    {

        return !file_exists($path) || @unlink($path);
    }
}
putenv('GITHUB_ACTIONS=true');
putenv('CI=true');
putenv('PRONTOO_SCHEMA_TEST_MODE=1');
putenv('PRONTOO_INSTALLER_CLI_MODE=1');
require_once $root . '/app/Runtime/Autoload/ProntooAutoloader.php';
require_once $root . '/app/Core/Install/InstallAccess.php';
require_once $root . '/app/Core/Database/SchemaMutationLock.php';

preg_match_all('/CREATE TABLE `([^`]+)` \(.*?\n\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;/s', $schema, $matches, PREG_SET_ORDER);
$blocks = [];
foreach ($matches as $match) {
    $blocks[(string) $match[1]] = (string) $match[0];
}
$errors = [];
$expectedTables = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_expected_table_names();
$expectedTableCount = count($expectedTables);
if (count($blocks) !== $expectedTableCount) {
    $errors[] = 'schema_table_count:' . count($blocks);
}
$blockNames = array_keys($blocks);
sort($blockNames, SORT_STRING);
if ($blockNames !== $expectedTables) {
    $errors[] = 'schema_table_set_divergent';
}
try {
    $runtimeStatements = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_statements();
    if (count($runtimeStatements) !== $expectedTableCount) {
        $errors[] = 'runtime_schema_statement_count:' . count($runtimeStatements);
    }
} catch (Throwable $error) {
    $errors[] = 'runtime_schema_contract:' . $error->getMessage();
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
    return mb_trim(preg_replace('/\s+/', ' ', $block) ?? $block);
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
    $pdo = PDO::connect($dsn, getenv('PRONTOO_SCHEMA_USER') ?: 'root', getenv('PRONTOO_SCHEMA_PASS') ?: '', [
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
    Prontoo\Infrastructure\Database\SeqContract::assert($pdo);
    $pdo->exec("INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES ('schema_check_a','1',UNIX_TIMESTAMP())");
    $first = (int) $pdo->query("SELECT Seq FROM pi_meta WHERE meta_key='schema_check_a'")->fetchColumn();
    $pdo->exec("INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES ('schema_check_b','1',UNIX_TIMESTAMP())");
    $second = (int) $pdo->query("SELECT Seq FROM pi_meta WHERE meta_key='schema_check_b'")->fetchColumn();
    if ($first <= 0 || $second <= $first) {
        $errors[] = 'native_seq_runtime_order';
    }
    $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'")->fetchColumn();
    if ($tableCount !== $expectedTableCount) {
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
    $ddlBlocked = false;
    try {
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_reject_runtime_ddl('ALTER TABLE pi_meta ADD COLUMN forbidden int');
    } catch (RuntimeException $error) {
        $ddlBlocked = str_contains($error->getMessage(), 'estrutura do banco está congelada');
    }
    if (!$ddlBlocked) {
        $errors[] = 'schema_mutation_lock_not_closed';
    }

    $dsnParts = [];
    foreach (explode(';', preg_replace('/^mysql:/', '', $dsn) ?? '') as $part) {
        [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
        if ($key !== '') {
            $dsnParts[$key] = $value;
        }
    }
    $GLOBALS['PRONTOO_SCHEMA_CHECK_CFG'] = [
        'db_host' => (string) ($dsnParts['host'] ?? '127.0.0.1'),
        'db_name' => (string) ($dsnParts['dbname'] ?? 'prontoo_schema'),
        'db_user' => getenv('PRONTOO_SCHEMA_USER') ?: 'root',
        'db_pass' => getenv('PRONTOO_SCHEMA_PASS') ?: '',
    ];
    $storage = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path();
    if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
        throw new RuntimeException('Não foi possível criar storage temporário do instalador.');
    }
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_clear_caches();
    Prontoo\Core\Database\SchemaMutationLock::runForInstaller(
        static function (): void {

            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::install_fresh_schema();
        },
    );
    $runtimePdo = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
    $installedCount = (int) $runtimePdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'",
    )->fetchColumn();
    if ($installedCount !== $expectedTableCount) {
        $errors[] = 'runtime_install_table_count:' . $installedCount;
    }
    $installedRevision = (string) ($runtimePdo->query(
        "SELECT meta_value FROM pi_meta WHERE meta_key='schema_revision' LIMIT 1",
    )?->fetchColumn() ?: '');
    if (!hash_equals(PRONTOO_SCHEMA_REV, $installedRevision)) {
        $errors[] = 'runtime_install_schema_revision';
    }
    if (!is_file(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_lock_file())) {
        $errors[] = 'runtime_install_schema_lock_missing';
    }
    $now = time();
    $person = $runtimePdo->prepare(
        'INSERT INTO pi_persons (full_name,cpf,created_at) VALUES (?,?,?)',
    );
    $person->execute(['Instalador CI', '52998224725', $now]);
    $personId = (int) $runtimePdo->lastInsertId();
    $user = $runtimePdo->prepare(
        'INSERT INTO pi_users (person_id,name,email,password_hash,is_global_admin,active,created_at) VALUES (?,?,?,?,1,1,?)',
    );
    $user->execute([
        $personId,
        'Instalador CI',
        'installer-ci@example.invalid',
        password_hash('Prontoo-CI-2026', PASSWORD_DEFAULT),
        $now,
    ]);
    $userId = (int) $runtimePdo->lastInsertId();
    $personSeq = (int) $runtimePdo->query('SELECT Seq FROM pi_persons WHERE id=' . $personId)->fetchColumn();
    $userSeq = (int) $runtimePdo->query('SELECT Seq FROM pi_users WHERE id=' . $userId)->fetchColumn();
    if ($personId <= 0 || $userId <= 0 || $personSeq <= 0 || $userSeq <= 0) {
        $errors[] = 'runtime_install_initial_admin_contract';
    }
    $dropAll($runtimePdo);
    \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_unlink(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_lock_file(), false);
    @rmdir($storage);

    $dbResult = [
        'executed' => true,
        'mysql_version' => $mysqlVersion,
        'tables' => $tableCount,
        'first_seq' => $first,
        'second_seq' => $second,
        'schema_mutation_lock_closed' => $ddlBlocked,
        'runtime_installer_zero_table_database' => [
            'tables_created' => $installedCount,
            'schema_revision' => $installedRevision,
            'schema_lock' => true,
            'person_id' => $personId,
            'user_id' => $userId,
            'person_seq' => $personSeq,
            'user_seq' => $userSeq,
        ],
    ];
}

@unlink($schemaCheckConfigPath);
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