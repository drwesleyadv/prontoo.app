<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

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
if (!preg_match('/<Files\s+"install\.php">\s*(?:#[^\n]*\s*)*Require\s+all\s+granted\s*<\/Files>/s', $htaccess) ||
    preg_match('/<Files\s+"install\.php">\s*Require\s+all\s+denied\s*<\/Files>/s', $htaccess)) {
    $errors[] = 'webserver_install_window_entry_not_reachable';
}
if (!preg_match('/PUBLIC_INSTALL_WINDOW_START_UNIX\s*=\s*(\d+)\s*;/', $installAccessSource, $installWindowStartMatch) ||
    !preg_match('/PUBLIC_INSTALL_WINDOW_END_UNIX\s*=\s*(\d+)\s*;/', $installAccessSource, $installWindowEndMatch) ||
    (int) ($installWindowEndMatch[1] ?? 0) - (int) ($installWindowStartMatch[1] ?? 0) !== 7200 ||
    !str_contains($installAccessSource, "self::requestHostFrom(\$request) !== 'prontoo.app'") ||
    !str_contains($installAccessSource, "!is_file(\$root . '/app/config.php')") ||
    !str_contains($installAccessSource, "!is_file(\$root . '/ssd/install.lock')")) {
    $errors[] = 'guarded_two_hour_clean_install_window_policy';
}
if (!str_contains($schemaLockSource, "$route !== 'install'") ||
    !str_contains($schemaLockSource, "$script !== 'install.php'") ||
    !str_contains($schemaLockSource, "$requestPath !== '/install.php'")) {
    $errors[] = 'schema_window_install_route_contract';
}
if (substr_count($htaccess, '[R=308,L]') < 2 ||
    !str_contains($htaccess, 'Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"') ||
    !str_contains($htaccess, 'Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"')) {
    $errors[] = 'webserver_https_policy';
}
if (str_contains($htaccess, 'HTTP:X-Forwarded-Proto') ||
    !str_contains($htaccess, 'RewriteRule ^tools(?:/|$) - [F,L,NC]') ||
    !str_contains($htaccess, 'RewriteRule ^cron(?:/|$) - [F,L,NC]')) {
    $errors[] = 'webserver_spoofed_proxy_or_internal_tools_policy';
}
foreach (['app/prontoo.php', 'br/index.php'] as $httpsRuntimeFile) {
    $httpsRuntimeSource = (string) file_get_contents($root . '/' . $httpsRuntimeFile);
    if (!str_contains($httpsRuntimeSource, 'Location: https://prontoo.app') ||
        !str_contains($httpsRuntimeSource, 'true, 308') ||
        !str_contains($httpsRuntimeSource, 'security_https_active()') ||
        str_contains($httpsRuntimeSource, 'HTTP_X_FORWARDED_PROTO')) {
        $errors[] = 'runtime_https_guard:' . $httpsRuntimeFile;
    }
}
foreach (['tools/architecture-check.php', 'tools/schema-check.php', 'tools/install-security-check.php'] as $cliTool) {
    $cliToolSource = (string) file_get_contents($root . '/' . $cliTool);
    if (!str_contains($cliToolSource, 'PHP_SAPI !== "cli"')) {
        $errors[] = 'tool_not_cli_only:' . $cliTool;
    }
}

$runnerSecuritySource = (string) file_get_contents($root . '/app/Runtime/Runner.php');
if (str_contains($runnerSecuritySource, '$_GET["schema_check"]') ||
    !str_contains($runnerSecuritySource, 'LOCK_EX | LOCK_NB')) {
    $errors[] = 'runtime_maintenance_trigger_or_lock_policy';
}
$securityAccessSource = (string) file_get_contents($root . '/app/Support/SecurityAccess.php');
if (!str_contains($securityAccessSource, 'storage_path("cache/rate-limits")') ||
    !str_contains($securityAccessSource, 'flock($handle, LOCK_EX)')) {
    $errors[] = 'atomic_rate_limit_policy';
}
$authSecuritySource = (string) file_get_contents($root . '/app/Auth/AuthOnboarding.php');
if (!str_contains($authSecuritySource, 'SELECT GET_LOCK(?,2)') ||
    !str_contains($authSecuritySource, 'fail_count=LEAST(100000,fail_count+1)') ||
    !str_contains($authSecuritySource, 'login|all-ip-addresses') ||
    !str_contains($authSecuritySource, 'login|all-subjects') ||
    !str_contains($authSecuritySource, 'login_locks_cleanup_maybe();')) {
    $errors[] = 'atomic_login_limit_policy';
}
if (!preg_match('/function\\s+password_ok\\(string\\s+\\$s\\):\\s*bool\\s*\\{.*?return\\s+\\$length\\s*>=\\s*8\\s*&&\\s*\\$length\\s*<=\\s*128\\s*&&\\s*!password_common_rejected\\(\\$s\\);/s', $securityAccessSource) ||
    substr_count($authSecuritySource, 'minlength="8" maxlength="128"') < 5 ||
    str_contains($authSecuritySource, 'minlength="15"') ||
    str_contains($securityAccessSource, '$length >= 15')) {
    $errors[] = 'password_minimum_8_policy';
}
$prontooSecuritySource = (string) file_get_contents($root . '/app/prontoo.php');
if (!preg_match('/const\s+PRONTOO_SESSION_IDLE_SECONDS\s*=\s*3600\s*;/', $prontooSecuritySource) ||
    !str_contains($prontooSecuritySource, 'PRONTOO_AUTH_POLICY_GENERATION')) {
    $errors[] = 'session_idle_or_policy_generation';
}
if (!preg_match('/function\s+device_session_auto_login\(\):\s*bool\s*\{.*?return\s+false\s*;\s*\}/s', $securityAccessSource) ||
    !str_contains($securityAccessSource, 'setcookie(device_cookie_name(), "",') ||
    !str_contains($securityAccessSource, 'security_retire_persistent_devices_for_user') ||
    !str_contains($securityAccessSource, 'user_auth_generation_rotate') ||
    !str_contains($securityAccessSource, 'security_clear_legacy_device_cookie();')) {
    $errors[] = 'persistent_device_retirement_policy';
}
foreach ([
    'aes-256-gcm',
    'function mfa_enroll_user(',
    'function mfa_verify_user_code(',
    '"last_counter"',
    'SELECT GET_LOCK(?,5)',
    'function security_global_scope_verified(',
] as $requiredMfaPolicy) {
    if (!str_contains($securityAccessSource, $requiredMfaPolicy)) {
        $errors[] = 'mfa_runtime_policy:' . $requiredMfaPolicy;
    }
}
foreach ([
    'function page_mfa(): void',
    'function page_global_reauth(): void',
    'mfa_begin_pending_login(',
    'password_verify($password',
    'mfa_verify_user_code($uid, $code)',
    'CPF ou senha não conferem.',
    'user_auth_generation_rotate($uid);',
] as $requiredAuthFlow) {
    if (!str_contains($authSecuritySource, $requiredAuthFlow)) {
        $errors[] = 'auth_flow_policy:' . $requiredAuthFlow;
    }
}
if (str_contains($authSecuritySource, 'device_login_fields(') ||
    str_contains($authSecuritySource, 'device_session_auto_login()')) {
    $errors[] = 'persistent_device_auth_surface';
}
$foundationAuthSource = (string) file_get_contents($root . '/app/Support/Foundation.php');
if (str_contains($foundationAuthSource, 'device_session_auto_login()') ||
    !str_contains($foundationAuthSource, 'security_clear_legacy_device_cookie();')) {
    $errors[] = 'login_autotest_persistent_device_bypass';
}
$runnerAuthSource = (string) file_get_contents($root . '/app/Runtime/Runner.php');
$catalogAuthSource = (string) file_get_contents($root . '/app/Application/Authorization/ActionCatalog.php');
foreach (['"mfa"', '"global_reauth"'] as $requiredRoute) {
    if (!str_contains($runnerAuthSource, $requiredRoute) ||
        !str_contains($catalogAuthSource, trim($requiredRoute, '"'))) {
        $errors[] = 'mfa_route_contract:' . $requiredRoute;
    }
}
$patientSecuritySource = (string) file_get_contents($root . '/app/Domain/Patients/Patients.php');
if (!str_contains($patientSecuritySource, '"patient_lookup_c" . $cid . "_u" . $uid') ||
    !preg_match('/"patient_lookup_c"\s*\.\s*\$cid.*?\b6,\s*60,/s', $patientSecuritySource) ||
    !str_contains($patientSecuritySource, 'JOIN pi_user_roles ur ON ur.user_id=u.id')) {
    $errors[] = 'patient_lookup_limit_or_tenant_policy';
}
$leadSecuritySource = (string) file_get_contents($root . '/app/Domain/Leads/Leads.php');
if (!str_contains($leadSecuritySource, 'WHERE p.cpf=?') ||
    !str_contains($leadSecuritySource, 'WHERE u.person_id=p.id AND ur.clinic_id=?')) {
    $errors[] = 'lead_patient_lookup_tenant_policy';
}
$teamSecuritySource = (string) file_get_contents($root . '/app/Domain/Permissions/UsersPermissions.php');
if (!str_contains($teamSecuritySource, '!$alreadyLinked') ||
    !str_contains($teamSecuritySource, 'password_verify($pass')) {
    $errors[] = 'cross_clinic_credential_reuse_policy';
}
$documentSecuritySource = (string) file_get_contents($root . '/app/Domain/Documents/Documents.php');
if (!str_contains($documentSecuritySource, 'b|strong|i|em|u|p|br|div|ul|ol|li|h2|h3')) {
    $errors[] = 'document_html_attribute_allowlist_policy';
}

$gitignore = (string) file_get_contents($root . '/.gitignore');
if (!str_contains($gitignore, "/ssd/") || !str_contains($gitignore, "/storage/") || !str_contains($gitignore, "/pdfs/")) {
    $errors[] = 'ssd_gitignore_policy';
}
$foundationSource = (string) file_get_contents($root . '/app/Support/Foundation.php');
if (!str_contains($foundationSource, 'app_root() . "/ssd"') || str_contains($foundationSource, 'app_root() . "/storage"')) {
    $errors[] = 'ssd_storage_path_policy';
}
$prontooSource = (string) file_get_contents($root . '/app/prontoo.php');
foreach (['PRONTOO_SSD_ROOT', 'PRONTOO_PDF_ROOT', 'PRONTOO_IMAGE_UPLOAD_ROOT'] as $requiredPersistenceMarker) {
    if (!str_contains($prontooSource, $requiredPersistenceMarker)) {
        $errors[] = 'ssd_migration_marker:' . $requiredPersistenceMarker;
    }
}
$documentPdfSource = (string) file_get_contents($root . '/app/Domain/Documents/DocumentPdf.php');
if (!str_contains($documentPdfSource, 'storage_path("pdfs")') || str_contains($documentPdfSource, 'app_root() . "/pdfs"')) {
    $errors[] = 'ssd_pdf_policy';
}
$subscriptionSource = (string) file_get_contents($root . '/app/Domain/Clinic/SubscriptionSettings.php');
foreach (['storage_path("img/payment-proofs")', '"ssd/img/payment-proofs"', '"ssd/payment-proofs"', '"storage/payment-proofs/"', '$mime !== "application/pdf"'] as $requiredUploadPolicy) {
    if (!str_contains($subscriptionSource, $requiredUploadPolicy)) {
        $errors[] = 'ssd_upload_policy:' . $requiredUploadPolicy;
    }
}
$htaccessPolicy = (string) file_get_contents($root . '/.htaccess');
if (!str_contains($htaccessPolicy, 'RewriteRule ^ssd/ - [F,L,NC]') || !str_contains($htaccessPolicy, 'RewriteRule ^storage/ - [F,L,NC]')) {
    $errors[] = 'ssd_webserver_policy';
}
if (is_file($root . '/pdfs/.htaccess') || is_file($root . '/pdfs/index.html')) {
    $errors[] = 'legacy_root_pdfs_router_present';
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
    'persistent_root' => 'ssd',
    'pdf_storage' => 'ssd/pdfs',
    'image_upload_storage' => 'ssd/img',
    'errors' => $errors,
];
echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
), PHP_EOL;
exit($errors === [] ? 0 : 1);
