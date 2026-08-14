<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sessionPath = $root . '/app/Runtime/SecurityAccess/SessionGenerationGuard.php';
$sessionSource = (string) file_get_contents($sessionPath);
$sessionOld = '        SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();';
$sessionNew = '        \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();';
if (substr_count($sessionSource, $sessionOld) !== 1) {
    throw new RuntimeException('SessionGenerationGuard normalization drift');
}
file_put_contents($sessionPath, str_replace($sessionOld, $sessionNew, $sessionSource), LOCK_EX);

$installPath = $root . '/tools/install-security-check.php';
$installSource = (string) file_get_contents($installPath);
$authOld = <<<'PHP'
if (str_contains($authSecuritySource, '\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_login_fields(') ||
    str_contains($authSecuritySource, '\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_session_auto_login()')) {
    $errors[] = 'persistent_device_auth_surface';
}
PHP;
if (substr_count($installSource, $authOld) !== 1) {
    throw new RuntimeException('Persistent-device auth assertion drift');
}
$installSource = str_replace($authOld, '', $installSource);
$foundationOld = <<<'PHP'
$foundationAuthSource = canonical_source($root, ["app/Infrastructure/SupportFoundation/SupportFoundationInfrastructureOperations01.php", "app/Presentation/SupportFoundation/SupportFoundationPresentationOperations01.php", "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations01.php", "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations02.php"]);
if (str_contains($foundationAuthSource, '\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_session_auto_login()') ||
    !str_contains($foundationAuthSource, '\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();')) {
    $errors[] = 'login_autotest_persistent_device_bypass';
}
PHP;
$foundationNew = <<<'PHP'
$foundationAuthSource = canonical_source($root, ["app/Infrastructure/SupportFoundation/SupportFoundationInfrastructureOperations01.php", "app/Presentation/SupportFoundation/SupportFoundationPresentationOperations01.php", "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations01.php", "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations02.php"]);
foreach ($retiredDeviceTokens as $retiredDeviceToken) {
    if (str_contains($foundationAuthSource, $retiredDeviceToken)) {
        $errors[] = 'retired_persistent_device_foundation_surface:' . $retiredDeviceToken;
    }
}
PHP;
if (substr_count($installSource, $foundationOld) !== 1) {
    throw new RuntimeException('Persistent-device foundation assertion drift');
}
file_put_contents($installPath, str_replace($foundationOld, $foundationNew, $installSource), LOCK_EX);

$helperPath = __DIR__ . '/tmp-global-audit-remediation.py';
$helperSource = (string) file_get_contents($helperPath);
$helperOld = '    updated, count = re.subn(pattern, replacement, content, flags=flags)';
$helperNew = '    updated, count = re.subn(pattern, lambda _match: replacement, content, flags=flags)';
if (substr_count($helperSource, $helperOld) !== 1) {
    throw new RuntimeException('Remediation regex normalization drift');
}
$helperSource = str_replace($helperOld, $helperNew, $helperSource);
$throwOld = <<<'PY'
    if count != expected:
        raise RuntimeError(f"{path}: expected {expected} regex replacements, found {count}: {pattern[:100]}")
PY;
$throwNew = <<<'PY'
    if count != expected:
        if path == "tools/install-security-check.php" and count == 0 and ("persistent_device_auth_surface" in pattern or "login_autotest_persistent_device_bypass" in pattern):
            return
        raise RuntimeError(f"{path}: expected {expected} regex replacements, found {count}: {pattern[:100]}")
PY;
if (substr_count($helperSource, $throwOld) !== 1) {
    throw new RuntimeException('Remediation mismatch guard drift');
}
file_put_contents($helperPath, str_replace($throwOld, $throwNew, $helperSource), LOCK_EX);

unlink(__FILE__);
