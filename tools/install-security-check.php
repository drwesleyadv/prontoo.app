<?php
declare(strict_types=1);
require_once __DIR__ . '/compatibility-source.php';

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/app/Runtime/Autoload/ProntooAutoloader.php';
require_once $root . '/app/Core/Install/InstallAccess.php';
require_once $root . '/app/Core/Database/SchemaMutationLock.php';

use Prontoo\Core\Database\SchemaMutationLock;
use Prontoo\Core\Install\InstallAccess;
use Prontoo\Runtime\Routing\RouteRegistry;

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
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_reject_runtime_ddl('CREATE TABLE pi_test (id int)');
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
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_reject_runtime_ddl('ALTER TABLE pi_meta ADD COLUMN forbidden int');
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
foreach ([
    '$route !== \'install\'',
    '$script !== \'install.php\'',
    '$requestPath !== \'/install.php\'',
] as $schemaWindowToken) {
    if (!str_contains($schemaLockSource, $schemaWindowToken)) {
        $errors[] = 'schema_window_install_route_contract:' . $schemaWindowToken;
    }
}

$installEntry = (string) file_get_contents($root . '/install.php');
$guardPos = strpos($installEntry, 'InstallAccess::assertInstallerEntry');
$bootstrapPos = strpos($installEntry, 'app/prontoo.php');
if ($guardPos === false || $bootstrapPos === false || $guardPos > $bootstrapPos) {
    $errors[] = 'install_entry_guard_order';
}

$installer = compatibility_source($root, 'app/Install/Installer.php');
if (str_contains($installer, 'assertLocalEntry') ||
    !str_contains($installer, 'InstallAccess::assertInstallerEntry')) {
    $errors[] = 'installer_internal_guard';
}

$runnerSource = (string) file_get_contents($root . '/app/Runtime/Runner.php');
if (str_contains($runnerSource, 'Location: /install.php') ||
    str_contains($runnerSource, 'InstallAccess::isInstallerExecutionAllowed')) {
    $errors[] = 'runtime_install_fallback_present';
}

$bootCoordinatorSource = (string) file_get_contents(
    $root . '/app/Runtime/Boot/RuntimeBootCoordinator.php',
);
if (str_contains($runnerSource, '$_GET["schema_check"]') ||
    str_contains($bootCoordinatorSource, '$_GET["schema_check"]') ||
    !str_contains($bootCoordinatorSource, 'LOCK_EX | LOCK_NB')) {
    $errors[] = 'runtime_maintenance_trigger_or_lock_policy';
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
        !str_contains($httpsRuntimeSource, '\\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active()') ||
        str_contains($httpsRuntimeSource, 'HTTP_X_FORWARDED_PROTO')) {
        $errors[] = 'runtime_https_guard:' . $httpsRuntimeFile;
    }
}
foreach (['tools/architecture-check.php', 'tools/schema-check.php', 'tools/install-security-check.php'] as $cliTool) {
    $cliToolSource = (string) file_get_contents($root . '/' . $cliTool);
    if (!preg_match('/PHP_SAPI\s*!==\s*["\']cli["\']/', $cliToolSource)) {
        $errors[] = 'tool_not_cli_only:' . $cliTool;
    }
}

$securityAccessSource = compatibility_source($root, 'app/Support/SecurityAccess.php');
if (!str_contains($securityAccessSource, '\\Prontoo\\Infrastructure\\SupportFoundation\\SupportFoundationInfrastructureOperations01::storage_path("cache/rate-limits")') ||
    !str_contains($securityAccessSource, 'flock($handle, LOCK_EX)')) {
    $errors[] = 'atomic_rate_limit_policy';
}
$authSecuritySource = compatibility_source($root, 'app/Auth/AuthOnboarding.php');
if (!str_contains($authSecuritySource, 'SELECT GET_LOCK(?,2)') ||
    !str_contains($authSecuritySource, 'fail_count=LEAST(100000,fail_count+1)') ||
    !str_contains($authSecuritySource, 'login|all-ip-addresses') ||
    !str_contains($authSecuritySource, 'login|all-subjects') ||
    !str_contains($authSecuritySource, '\\Prontoo\\Runtime\\AuthOnboarding\\AuthOnboardingRuntimeOperations03::login_locks_cleanup_maybe();')) {
    $errors[] = 'atomic_login_limit_policy';
}
$nativeSecurityAccessSource = (string) file_get_contents(
    $root . '/app/Infrastructure/SecurityAccess/SecurityAccessInfrastructureOperations01.php',
);
if (!str_contains($nativeSecurityAccessSource, 'public static function password_ok(string $s): bool') ||
    !str_contains($nativeSecurityAccessSource, '$length >= 8 &&') ||
    !str_contains($nativeSecurityAccessSource, '$length <= 128 &&') ||
    !str_contains($nativeSecurityAccessSource, '::password_common_rejected($s);') ||
    substr_count($authSecuritySource, 'minlength="8" maxlength="128"') < 5 ||
    str_contains($authSecuritySource, 'minlength="15"') ||
    str_contains($nativeSecurityAccessSource, '$length >= 15')) {
    $errors[] = 'password_minimum_8_policy';
}
$prontooSecuritySource = (string) file_get_contents($root . '/app/prontoo.php');
if (!preg_match('/const\s+PRONTOO_SESSION_IDLE_SECONDS\s*=\s*3600\s*;/', $prontooSecuritySource) ||
    !str_contains($prontooSecuritySource, 'PRONTOO_AUTH_POLICY_GENERATION')) {
    $errors[] = 'session_idle_or_policy_generation';
}
if (!preg_match('/function\s+device_session_auto_login\(\):\s*bool\s*\{.*?return\s+false\s*;\s*\}/s', $securityAccessSource) ||
    !str_contains($securityAccessSource, 'setcookie(\\Prontoo\\Infrastructure\\SecurityAccess\\SecurityAccessInfrastructureOperations01::device_cookie_name(), "",') ||
    !str_contains($securityAccessSource, 'security_retire_persistent_devices_for_user') ||
    !str_contains($securityAccessSource, 'user_auth_generation_rotate') ||
    !str_contains($securityAccessSource, '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();')) {
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
    '\\Prontoo\\Runtime\\AuthOnboarding\\AuthOnboardingRuntimeOperations01::mfa_begin_pending_login(',
    'password_verify($password',
    '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::mfa_verify_user_code($uid, $code)',
    'CPF ou senha não conferem.',
    '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($uid);',
] as $requiredAuthFlow) {
    if (!str_contains($authSecuritySource, $requiredAuthFlow)) {
        $errors[] = 'auth_flow_policy:' . $requiredAuthFlow;
    }
}
if (str_contains($authSecuritySource, '\\Prontoo\\Infrastructure\\SecurityAccess\\SecurityAccessInfrastructureOperations01::device_login_fields(') ||
    str_contains($authSecuritySource, '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::device_session_auto_login()')) {
    $errors[] = 'persistent_device_auth_surface';
}
$foundationAuthSource = compatibility_source($root, 'app/Support/Foundation.php');
if (str_contains($foundationAuthSource, '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::device_session_auto_login()') ||
    !str_contains($foundationAuthSource, '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();')) {
    $errors[] = 'login_autotest_persistent_device_bypass';
}

$authDefinitionSource = (string) file_get_contents(
    $root . '/app/Application/Authorization/Definitions/AuthActionDefinitions.php',
);
$requiredMfaRoutes = [
    'mfa' => [
        \\Prontoo\\Runtime\\AuthOnboarding\\AuthOnboardingRuntimeOperations02::class,
        'page_mfa',
        true,
    ],
    'global_reauth' => [
        \\Prontoo\\Runtime\\AuthOnboarding\\AuthOnboardingRuntimeOperations02::class,
        'page_global_reauth',
        false,
    ],
];
foreach ($requiredMfaRoutes as $requiredRoute => [$handlerClass, $handlerMethod, $public]) {
    $definition = RouteRegistry::definition($requiredRoute);
    if ($definition === null ||
        $definition->handlerClass !== $handlerClass ||
        $definition->handlerMethod !== $handlerMethod ||
        $definition->public !== $public ||
        !str_contains($authDefinitionSource, "'" . $requiredRoute . "'")) {
        $errors[] = 'mfa_route_contract:' . $requiredRoute;
    }
}

$patientSecuritySource = compatibility_source($root, 'app/Domain/Patients/Patients.php');
if (!str_contains($patientSecuritySource, '"patient_lookup_c" . $cid . "_u" . $uid') ||
    !preg_match('/"patient_lookup_c"\s*\.\s*\$cid.*?\b6,\s*60,/s', $patientSecuritySource) ||
    !str_contains($patientSecuritySource, 'JOIN pi_user_roles ur ON ur.user_id=u.id')) {
    $errors[] = 'patient_lookup_limit_or_tenant_policy';
}
$leadSecuritySource = (string) file_get_contents($root . '/app/Runtime/Leads/LeadsRuntimeOperations01.php');
if (!str_contains($leadSecuritySource, 'WHERE p.cpf=?') ||
    !str_contains($leadSecuritySource, 'WHERE u.person_id=p.id AND ur.clinic_id=?')) {
    $errors[] = 'lead_patient_lookup_tenant_policy';
}
$teamSecuritySource = compatibility_source($root, 'app/Domain/Permissions/UsersPermissions.php');
if (!str_contains($teamSecuritySource, '!$alreadyLinked') ||
    !str_contains($teamSecuritySource, 'password_verify($pass')) {
    $errors[] = 'cross_clinic_credential_reuse_policy';
}
$documentSecuritySource = compatibility_source($root, 'app/Domain/Documents/Documents.php');
if (!str_contains($documentSecuritySource, 'b|strong|i|em|u|p|br|div|ul|ol|li|h2|h3')) {
    $errors[] = 'document_html_attribute_allowlist_policy';
}

$gitignore = (string) file_get_contents($root . '/.gitignore');
if (!str_contains($gitignore, '/ssd/') ||
    !str_contains($gitignore, '/storage/') ||
    !str_contains($gitignore, '/pdfs/')) {
    $errors[] = 'ssd_gitignore_policy';
}
$foundationSource = compatibility_source($root, 'app/Support/Foundation.php');
if (!str_contains($foundationSource, '\\Prontoo\\Infrastructure\\SupportFoundation\\SupportFoundationInfrastructureOperations01::app_root() . "/ssd"') ||
    str_contains($foundationSource, '\\Prontoo\\Infrastructure\\SupportFoundation\\SupportFoundationInfrastructureOperations01::app_root() . "/storage"')) {
    $errors[] = 'ssd_storage_path_policy';
}
$prontooSource = (string) file_get_contents($root . '/app/prontoo.php');
foreach (['PRONTOO_SSD_ROOT', 'PRONTOO_PDF_ROOT', 'PRONTOO_IMAGE_UPLOAD_ROOT'] as $requiredPersistenceMarker) {
    if (!str_contains($prontooSource, $requiredPersistenceMarker)) {
        $errors[] = 'ssd_migration_marker:' . $requiredPersistenceMarker;
    }
}

$_SERVER = $originalServer;
foreach ($originalEnv as $name => $value) {
    if ($value === null) {
        putenv($name);
    } else {
        putenv($name . '=' . $value);
    }
}

$result = [
    'ok' => $errors === [],
    'policy' => 'commissioned-installation-lockdown-v2',
    'public_installer' => false,
    'local_http_installer' => false,
    'runtime_install_redirect' => false,
    'schema_mutation' => 'github-actions-cli-four-markers-only',
    'webserver_install_denied' => true,
    'schema_frozen' => true,
    'https_enforced' => true,
    'persistent_root' => 'ssd',
    'pdf_storage' => 'ssd/pdfs',
    'image_upload_storage' => 'ssd/img',
    'errors' => array_values(array_unique($errors)),
];
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($result['ok'] ? 0 : 1);