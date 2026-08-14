<?php
declare(strict_types=1);
$path = dirname(__DIR__) . '/app/Runtime/SecurityAccess/SessionGenerationGuard.php';
$source = (string) file_get_contents($path);
$old = '        SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();';
$new = '        \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();';
if (substr_count($source, $old) !== 1) {
    throw new RuntimeException('SessionGenerationGuard normalization drift');
}
file_put_contents($path, str_replace($old, $new, $source), LOCK_EX);
unlink(__FILE__);
