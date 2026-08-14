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

$helperPath = __DIR__ . '/tmp-global-audit-remediation.py';
$helperSource = (string) file_get_contents($helperPath);
$helperOld = '    updated, count = re.subn(pattern, replacement, content, flags=flags)';
$helperNew = '    updated, count = re.subn(pattern, lambda _match: replacement, content, flags=flags)';
if (substr_count($helperSource, $helperOld) !== 1) {
    throw new RuntimeException('Remediation regex normalization drift');
}
file_put_contents($helperPath, str_replace($helperOld, $helperNew, $helperSource), LOCK_EX);

unlink(__FILE__);
