<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schemaFile = $root . '/app/Database/schema.sql';
$contractFile = $root . '/app/Database/operational-schema.contract.json';
$schema = (string) file_get_contents($schemaFile);
$contract = json_decode((string) file_get_contents($contractFile), true, 512, JSON_THROW_ON_ERROR);

if (!defined('PRONTOO_SCHEMA_REV')) {
    define('PRONTOO_SCHEMA_REV', 'prontoo_1_7_20_6_clean_schema_r7_layer2_ledger');
}
if (!defined('PRONTOO_MIN_MYSQL_VERSION')) {
    define('PRONTOO_MIN_MYSQL_VERSION', '8.0.30');
}
$GLOBALS['PRONTOO_SCHEMA_CHECK_CFG'] = [];
$GLOBALS['PRONTOO_SCHEMA_CHECK_STORAGE'] = sys_get_temp_dir() . '/prontoo-schema-check-' . getmypid();
if (!function_exists('cfg')) {
    function cfg(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — cfg
         * Responsabilidade: Implementa a responsabilidade “cfg” dentro do módulo de ferramentas de certificação e manutenção.
         * Local arquitetural: tools/schema-check.php (ferramentas de certificação e manutenção).
         * Chamadores detectados: `pdo`, `app_config_string`, `app_debug`, `secret_key`, `seq_footer_deterministic_alphabet`, `pdo_metric`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        return (array) ($GLOBALS['PRONTOO_SCHEMA_CHECK_CFG'] ?? []);
    }
}
if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        /*
         * GUIA DE MANUTENÇÃO — storage_path
         * Responsabilidade: Implementa a responsabilidade “storage path” dentro do módulo de ferramentas de certificação e manutenção.
         * Local arquitetural: tools/schema-check.php (ferramentas de certificação e manutenção).
         * Chamadores detectados: `platform_storage_status`, `db_runtime_notice_once`, `schema_lock_file`, `subscription_payment_proof_storage`, `subscription_payment_proof_absolute_path`, `document_pdf_storage_dir`, `document_pdf_cleanup_due`, `maestro_cron_run` e mais 18.
         * Dependências chamadas: `rtrim`, `sys_get_temp_dir`, `ltrim`.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $base = rtrim((string) ($GLOBALS['PRONTOO_SCHEMA_CHECK_STORAGE'] ?? sys_get_temp_dir()), '/');
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}
if (!function_exists('prontoo_fs_chmod')) {
    function prontoo_fs_chmod(string $path, int $mode, bool $required = true): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — prontoo_fs_chmod
         * Responsabilidade: Implementa a responsabilidade “prontoo fs chmod” dentro do módulo de ferramentas de certificação e manutenção.
         * Local arquitetural: tools/schema-check.php (ferramentas de certificação e manutenção).
         * Chamadores detectados: `prontoo_schema_promote_release_contract`, `schema_mark_ready`, `install_write_failure_log`, `prontoo_install`.
         * Dependências chamadas: `file_exists`, `chmod`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return !file_exists($path) || @chmod($path, $mode);
    }
}
if (!function_exists('prontoo_fs_unlink')) {
    function prontoo_fs_unlink(string $path, bool $required = true): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — prontoo_fs_unlink
         * Responsabilidade: Implementa a responsabilidade “prontoo fs unlink” dentro do módulo de ferramentas de certificação e manutenção.
         * Local arquitetural: tools/schema-check.php (ferramentas de certificação e manutenção).
         * Chamadores detectados: `prontoo_schema_promote_release_contract`, `install_fresh_schema`, `install_environment_checks`, `prontoo_install`, `closure@app/Install/Installer.php:872`.
         * Dependências chamadas: `file_exists`, `unlink`.
         * Efeitos colaterais: acessa o sistema de arquivos.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return !file_exists($path) || @unlink($path);
    }
}
putenv('GITHUB_ACTIONS=true');
putenv('CI=true');
putenv('PRONTOO_SCHEMA_TEST_MODE=1');
putenv('PRONTOO_INSTALLER_CLI_MODE=1');
require_once $root . '/app/Core/Install/InstallAccess.php';
require_once $root . '/app/Core/Database/SchemaMutationLock.php';
require_once $root . '/app/Database/DatabaseSchema.php';

preg_match_all('/CREATE TABLE `([^`]+)` \(.*?\n\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;/s', $schema, $matches, PREG_SET_ORDER);
$blocks = [];
foreach ($matches as $match) {
    $blocks[(string) $match[1]] = (string) $match[0];
}
$errors = [];
$expectedTables = prontoo_schema_expected_table_names();
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
    $runtimeStatements = prontoo_schema_statements();
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
    /*
     * GUIA DE MANUTENÇÃO — closure@tools/schema-check.php:117
     * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de ferramentas de certificação e manutenção.
     * Local arquitetural: tools/schema-check.php (ferramentas de certificação e manutenção).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `preg_replace`, `trim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
        /*
         * GUIA DE MANUTENÇÃO — closure@tools/schema-check.php:181
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de ferramentas de certificação e manutenção.
         * Local arquitetural: tools/schema-check.php (ferramentas de certificação e manutenção).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `->query`, `->fetchAll`, `->exec`, `str_replace`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
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
        db_reject_runtime_ddl('ALTER TABLE pi_meta ADD COLUMN forbidden int');
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
    $storage = storage_path();
    if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
        throw new RuntimeException('Não foi possível criar storage temporário do instalador.');
    }
    prontoo_schema_clear_caches();
    Prontoo\Core\Database\SchemaMutationLock::runForInstaller(
        static function (): void {
            /*
             * GUIA DE MANUTENÇÃO — closure@tools/schema-check.php:224
             * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de ferramentas de certificação e manutenção.
             * Local arquitetural: tools/schema-check.php (ferramentas de certificação e manutenção).
             * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
             * Dependências chamadas: `install_fresh_schema`.
             * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
             * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
             */
            install_fresh_schema();
        },
    );
    $runtimePdo = pdo();
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
    if (!is_file(schema_lock_file())) {
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
    prontoo_fs_unlink(schema_lock_file(), false);
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
