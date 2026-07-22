<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/Core/Install/InstallAccess.php';
require_once $root . '/app/Core/Database/SchemaMutationLock.php';
require_once $root . '/app/Database/DatabaseSchema.php';

use Prontoo\Core\Database\SchemaMutationLock;
use Prontoo\Core\Install\InstallAccess;

$errors = [];
$originalServer = $_SERVER;
$originalEnv = [];
foreach ([
    'GITHUB_ACTIONS',
    'CI',
    'PRONTOO_SCHEMA_TEST_MODE',
    'PRONTOO_INSTALLER_CLI_MODE',
    'PRONTOO_ALLOW_LOCAL_INSTALL',
] as $name) {
    $value = getenv($name);
    $originalEnv[$name] = $value === false ? null : (string) $value;
    putenv($name);
}

$publicServer = [
    'REMOTE_ADDR' => '203.0.113.10',
    'HTTP_HOST' => 'prontoo.app',
    'HTTPS' => 'on',
    'SERVER_PORT' => '443',
];
$localServer = [
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_HOST' => 'localhost',
    'SERVER_PORT' => '80',
];
if (InstallAccess::isInstallerExecutionAllowed($publicServer)) {
    $errors[] = 'public_http_installer_allowed';
}
if (InstallAccess::isInstallerExecutionAllowed($localServer)) {
    $errors[] = 'local_http_installer_allowed';
}
if (InstallAccess::isInstallerExecutionAllowed()) {
    $errors[] = 'unmarked_cli_installer_allowed';
}

putenv('CI=true');
if (InstallAccess::isInstallerExecutionAllowed()) {
    $errors[] = 'partial_cli_installer_allowed_ci_only';
}
putenv('GITHUB_ACTIONS=true');
putenv('PRONTOO_SCHEMA_TEST_MODE=1');
if (InstallAccess::isInstallerExecutionAllowed()) {
    $errors[] = 'partial_cli_installer_allowed_without_installer_mode';
}
putenv('PRONTOO_INSTALLER_CLI_MODE=1');
if (!InstallAccess::isInstallerExecutionAllowed()) {
    $errors[] = 'fully_marked_github_actions_cli_denied';
}

$opened = false;
try {
    $opened = SchemaMutationLock::runForInstaller(static function (): bool {
        /*
         * GUIA DE MANUTENÇÃO — closure@tools/install-security-check.php:62
         * Responsabilidade: Confirma que a janela estrutural só abre no contexto integral da certificação e que o nonce interno é válido durante o callback.
         * Local arquitetural: tools/install-security-check.php (ferramentas de certificação e manutenção).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `SchemaMutationLock::isActive`, `db_reject_runtime_ddl`.
         * Efeitos colaterais: executa somente uma prova controlada, sem persistir estrutura ou dados.
         * Cuidado 1: Mantenha esta closure única para preservar o inventário documental da baseline.
         */
        if (!SchemaMutationLock::isActive()) {
            return false;
        }
        db_reject_runtime_ddl('CREATE TABLE pi_test (id int)');
        return true;
    });
} catch (Throwable $error) {
    $errors[] = 'fully_marked_schema_window:' . $error->getMessage();
}
if (!$opened || SchemaMutationLock::isActive()) {
    $errors[] = 'schema_window_lifecycle';
}

putenv('PRONTOO_INSTALLER_CLI_MODE');
$blockedPartial = false;
try {
    SchemaMutationLock::runForInstaller('strlen');
} catch (RuntimeException $error) {
    $blockedPartial = str_contains($error->getMessage(), 'janela estrutural');
}
if (!$blockedPartial) {
    $errors[] = 'partial_schema_markers_not_blocked';
}

$ddlBlocked = false;
try {
    db_reject_runtime_ddl('ALTER TABLE pi_meta ADD COLUMN forbidden int');
} catch (RuntimeException $error) {
    $ddlBlocked = str_contains($error->getMessage(), 'estrutura do banco está congelada');
}
if (!$ddlBlocked) {
    $errors[] = 'runtime_ddl_not_blocked';
}

$installAccessSource = (string) file_get_contents($root . '/app/Core/Install/InstallAccess.php');
foreach ([
    'TEMPORARY_PUBLIC_HOST',
    'TEMPORARY_PUBLIC_WINDOW_START_UNIX',
    'TEMPORARY_PUBLIC_WINDOW_END_UNIX',
] as $legacy) {
    if (str_contains($installAccessSource, $legacy)) {
        $errors[] = 'legacy_public_window_symbol:' . $legacy;
    }
}
foreach ([
    "getenv('GITHUB_ACTIONS')",
    "getenv('CI')",
    "getenv('PRONTOO_SCHEMA_TEST_MODE')",
    "getenv('PRONTOO_INSTALLER_CLI_MODE')",
] as $required) {
    if (!str_contains($installAccessSource, $required)) {
        $errors[] = 'installer_marker_missing:' . $required;
    }
}

$schemaLockSource = (string) file_get_contents($root . '/app/Core/Database/SchemaMutationLock.php');
if (str_contains($schemaLockSource, 'PRONTOO_ALLOW_LOCAL_INSTALL')) {
    $errors[] = 'legacy_local_install_bypass';
}
if (str_contains($schemaLockSource, 'InstallAccess::isInstallerExecutionAllowed') ||
    str_contains($schemaLockSource, 'use Prontoo\\Core\\Install\\InstallAccess')) {
    $errors[] = 'schema_lock_depends_on_http_install_access';
}

$installEntry = (string) file_get_contents($root . '/install.php');
$guardPos = strpos($installEntry, 'InstallAccess::assertInstallerEntry');
$bootstrapPos = strpos($installEntry, 'app/prontoo.php');
if ($guardPos === false || $bootstrapPos === false || $guardPos > $bootstrapPos) {
    $errors[] = 'install_entry_guard_order';
}

$installer = (string) file_get_contents($root . '/app/Install/Installer.php');
if (str_contains($installer, 'assertLocalEntry') ||
    !str_contains($installer, 'InstallAccess::assertInstallerEntry')) {
    $errors[] = 'installer_internal_guard';
}

$runtime = (string) file_get_contents($root . '/app/Runtime/Runner.php');
if (str_contains($runtime, 'Location: /install.php') ||
    str_contains($runtime, 'InstallAccess::isInstallerExecutionAllowed')) {
    $errors[] = 'runtime_install_fallback_present';
}

$htaccess = (string) file_get_contents($root . '/.htaccess');
if (!preg_match('/<Files\s+"install\.php">\s*(?:#[^\n]*\s*)*Require\s+all\s+denied\s*<\/Files>/s', $htaccess) ||
    preg_match('/<Files\s+"install\.php">\s*Require\s+all\s+granted\s*<\/Files>/s', $htaccess)) {
    $errors[] = 'webserver_install_not_denied';
}
if (substr_count($htaccess, '[R=308,L]') < 2 ||
    !str_contains($htaccess, 'Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"') ||
    !str_contains($htaccess, 'Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"')) {
    $errors[] = 'webserver_https_policy';
}
foreach (['app/prontoo.php', 'br/index.php'] as $httpsRuntimeFile) {
    $httpsRuntimeSource = (string) file_get_contents($root . '/' . $httpsRuntimeFile);
    if (!str_contains($httpsRuntimeSource, 'PRONTOO_HTTPS_RUNTIME_GUARD') ||
        !str_contains($httpsRuntimeSource, 'Location: https://prontoo.app') ||
        !str_contains($httpsRuntimeSource, 'true, 308')) {
        $errors[] = 'runtime_https_guard:' . $httpsRuntimeFile;
    }
}

$components = (string) file_get_contents($root . '/app/Ui/Components.php');
if (!preg_match('/\$current\s*===\s*"admin_painel"\)\s*\{\s*return\s+"network_ping";/s', $components)) {
    $errors[] = 'developer_panel_network_ping_icon';
}

$forbiddenAssignments = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo ||
        !$file->isFile() ||
        strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace(
        str_replace('\\', '/', $root) . '/',
        '',
        str_replace('\\', '/', $file->getPathname()),
    );
    $content = (string) file_get_contents($file->getPathname());
    if ($relative !== 'app/Core/Database/SchemaMutationLock.php' &&
        preg_match('/PRONTOO_SCHEMA_INSTALLING["\']?\]\s*=/', $content)) {
        $forbiddenAssignments[] = $relative;
    }
}
if ($forbiddenAssignments !== []) {
    $errors[] = 'schema_flag_assignment_outside_lock:' . implode(',', $forbiddenAssignments);
}

$_SERVER = $originalServer;
foreach ($originalEnv as $name => $value) {
    if ($value === null) {
        putenv($name);
    } else {
        putenv($name . '=' . $value);
    }
}

$errors = array_values(array_unique($errors));
$result = [
    'ok' => $errors === [],
    'policy' => 'commissioned-installation-lockdown-v1',
    'public_installer' => false,
    'local_http_installer' => false,
    'runtime_install_redirect' => false,
    'schema_mutation' => 'github-actions-cli-four-markers-only',
    'webserver_install_denied' => true,
    'developer_panel_icon' => 'network_ping',
    'schema_frozen' => true,
    'https_enforced' => true,
    'errors' => $errors,
];
echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
), PHP_EOL;
exit($errors === [] ? 0 : 1);
