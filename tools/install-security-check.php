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
$cases = [
    ['127.0.0.1', 'localhost', [], true, 'ipv4_localhost'],
    ['127.7.9.3', '127.0.0.1:8080', [], true, 'ipv4_loopback_range'],
    ['::1', '[::1]:8080', [], true, 'ipv6_loopback'],
    ['203.0.113.10', 'localhost', [], false, 'public_remote'],
    ['127.0.0.1', 'prontoo.app', [], false, 'public_host'],
    ['127.0.0.1', 'localhost', ['HTTP_X_FORWARDED_FOR' => '203.0.113.10'], false, 'forwarded_request'],
];
foreach ($cases as [$remote, $host, $extra, $expected, $label]) {
    $_SERVER = ['REMOTE_ADDR' => $remote, 'HTTP_HOST' => $host] + $extra;
    if (InstallAccess::isLocalServer($_SERVER) !== $expected) {
        $errors[] = 'install_access:' . $label;
    }
}
$_SERVER = $originalServer;

if (SchemaMutationLock::isActive()) {
    $errors[] = 'schema_lock_active_by_default';
}
$blocked = false;
try {
    db_reject_runtime_ddl('DROP TABLE pi_users');
} catch (RuntimeException $error) {
    $blocked = str_contains($error->getMessage(), 'estrutura do banco está congelada');
}
if (!$blocked) {
    $errors[] = 'runtime_ddl_not_blocked';
}
putenv('CI=true');
putenv('PRONTOO_SCHEMA_TEST_MODE=1');
$opened = SchemaMutationLock::runForInstaller(static function (): bool {
    db_reject_runtime_ddl('CREATE TABLE pi_test (id int)');
    return SchemaMutationLock::isActive();
});
if (!$opened || SchemaMutationLock::isActive()) {
    $errors[] = 'private_schema_window_contract';
}

$installEntry = (string) file_get_contents($root . '/install.php');
$guardPos = strpos($installEntry, 'InstallAccess::assertLocalEntry');
$bootstrapPos = strpos($installEntry, 'app/prontoo.php');
if ($guardPos === false || $bootstrapPos === false || $guardPos > $bootstrapPos) {
    $errors[] = 'install_entry_guard_order';
}
$htaccess = (string) file_get_contents($root . '/.htaccess');
if (!preg_match('/<Files\s+"install\.php">\s*Require\s+local\s*<\/Files>/s', $htaccess)) {
    $errors[] = 'webserver_local_only_rule';
}
if (is_file($root . '/app/Infrastructure/Database/CleanInstallReset.php')) {
    $errors[] = 'clean_install_reset_still_present';
}
$runtime = (string) file_get_contents($root . '/app/prontoo.php');
if (str_contains($runtime, 'prontoo_force_clean_install_1_7_21_1') || str_contains($runtime, 'CleanInstallReset::reset')) {
    $errors[] = 'destructive_reset_runtime_reference';
}

$forbiddenAssignments = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace(str_replace('\\', '/', $root) . '/', '', str_replace('\\', '/', $file->getPathname()));
    $content = (string) file_get_contents($file->getPathname());
    if ($relative !== 'app/Core/Database/SchemaMutationLock.php' && preg_match('/PRONTOO_SCHEMA_INSTALLING["\']?\]\s*=/', $content)) {
        $forbiddenAssignments[] = $relative;
    }
}
if ($forbiddenAssignments !== []) {
    $errors[] = 'schema_flag_assignment_outside_lock:' . implode(',', $forbiddenAssignments);
}

$result = [
    'ok' => $errors === [],
    'policy' => 'localhost-installer-private-schema-window-v1',
    'public_installer' => false,
    'schema_frozen' => true,
    'errors' => array_values(array_unique($errors)),
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($errors === [] ? 0 : 1);
