<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$tempRoot = sys_get_temp_dir() . '/prontoo-login-runtime-' . getmypid();
if (!is_dir($tempRoot) && !mkdir($tempRoot, 0750, true) && !is_dir($tempRoot)) {
    throw new RuntimeException('Não foi possível criar diretório temporário do teste de login.');
}

$configPath = $tempRoot . '/config.php';
$config = [
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_DATABASE') ?: 'prontoo_schema',
    'db_user' => getenv('DB_USERNAME') ?: 'root',
    'db_pass' => getenv('DB_PASSWORD') ?: 'root',
    'secret' => 'prontoo-login-runtime-ci-secret',
    'debug' => true,
    'app_env' => 'development',
];
file_put_contents(
    $configPath,
    '<?php return ' . var_export($config, true) . ';' . PHP_EOL,
    LOCK_EX,
);
putenv('PRONTOO_CONFIG_PATH=' . $configPath);
putenv('GITHUB_ACTIONS=true');
putenv('CI=true');
putenv('PRONTOO_SCHEMA_TEST_MODE=1');
putenv('PRONTOO_INSTALLER_CLI_MODE=1');

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $config['db_host'],
    $config['db_name'],
);
$admin = PDO::connect($dsn, $config['db_user'], $config['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$tables = $admin->query(
    "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'",
)->fetchAll(PDO::FETCH_COLUMN);
$admin->exec('SET FOREIGN_KEY_CHECKS=0');
try {
    foreach ((array) $tables as $table) {
        $admin->exec('DROP TABLE `' . str_replace('`', '``', (string) $table) . '`');
    }
} finally {
    $admin->exec('SET FOREIGN_KEY_CHECKS=1');
}

require $root . '/app/prontoo.php';

\Prontoo\Core\Database\SchemaMutationLock::runForInstaller(
    static function (): void {
        install_fresh_schema();
    },
);

$now = time();
$pdo = pdo();
$person = $pdo->prepare(
    'INSERT INTO pi_persons (full_name,cpf,created_at) VALUES (?,?,?)',
);
$person->execute(['Login Runtime CI', '52998224725', $now]);
$personId = (int) $pdo->lastInsertId();
$user = $pdo->prepare(
    'INSERT INTO pi_users (person_id,name,email,password_hash,is_global_admin,active,created_at) VALUES (?,?,?,?,0,1,?)',
);
$user->execute([
    $personId,
    'Login Runtime CI',
    'login-runtime-ci@example.invalid',
    password_hash('Prontoo-CI-2026', PASSWORD_DEFAULT),
    $now,
]);
$userId = (int) $pdo->lastInsertId();

$marker = prontoo_schema_boot_marker_path();
if (is_file($marker)) {
    prontoo_fs_unlink($marker, false);
}

$result = prontoo_login_post_password_maintenance($userId);
if (empty($result['ok'])) {
    throw new RuntimeException('Manutenção pós-senha não confirmou sucesso.');
}
if (!prontoo_schema_boot_marker_valid()) {
    throw new RuntimeException('Manutenção pós-senha não registrou marcador de runtime válido.');
}

printf(
    "%s\n",
    json_encode([
        'ok' => true,
        'policy' => 'login-post-password-runtime-v1',
        'user_id' => $userId,
        'maintenance' => $result,
        'schema_marker' => basename($marker),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
);
