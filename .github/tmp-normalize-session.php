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
$helperSource = str_replace($throwOld, $throwNew, $helperSource);
$pixOld = 'replace(components, "pix-" + OLD + ".svg", "pix-" + NEW + ".svg")';
$pixNew = 'replace(components, "pix-" + OLD + ".svg", "pix-" + NEW + ".svg", expected=2)';
if (substr_count($helperSource, $pixOld) !== 1) {
    throw new RuntimeException('Pix remediation count drift');
}
$helperSource = str_replace($pixOld, $pixNew, $helperSource);
$importOld = "import json\n";
$importNew = "import json\nimport hashlib\n";
if (substr_count($helperSource, $importOld) !== 1) {
    throw new RuntimeException('Hashlib import insertion drift');
}
$helperSource = str_replace($importOld, $importNew, $helperSource);
$printOld = 'print(json.dumps({"ok": True, "version": NEW, "policy": "global-audit-remediation-transform-v1"}, ensure_ascii=False))';
$manifestBlock = <<<'PY'
styles_manifest_path = "app/Presentation/Styles/styles.manifest.json"
styles_manifest = json_file(styles_manifest_path)
styles_source_root = ROOT / str(styles_manifest["source_root"])
styles_source_data = {}
for styles_source in styles_manifest["sources"]:
    styles_relative = str(styles_source["path"])
    styles_css = (styles_source_root / styles_relative).read_bytes()
    styles_source["sha256"] = hashlib.sha256(styles_css).hexdigest()
    styles_source["bytes"] = len(styles_css)
    styles_source["important_count"] = styles_css.count(b"!important")
    styles_source["route_scope_count"] = styles_css.count(b"body[data-route=")
    styles_source_data[styles_relative] = styles_css
styles_coverage = {styles_relative: 0 for styles_relative in styles_source_data}
styles_artifact = bytearray()
for styles_segment in styles_manifest["sequence"]:
    styles_relative = str(styles_segment["source"])
    styles_offset = int(styles_segment["offset"])
    styles_bytes = int(styles_segment["bytes"])
    if styles_relative not in styles_source_data or styles_offset != styles_coverage[styles_relative]:
        raise RuntimeError("styles manifest sequence coverage drift")
    styles_chunk = styles_source_data[styles_relative][styles_offset:styles_offset + styles_bytes]
    if len(styles_chunk) != styles_bytes:
        raise RuntimeError("styles manifest sequence slice drift")
    styles_segment["sha256"] = hashlib.sha256(styles_chunk).hexdigest()
    styles_segment["important_count"] = styles_chunk.count(b"!important")
    styles_segment["route_scope_count"] = styles_chunk.count(b"body[data-route=")
    styles_coverage[styles_relative] += styles_bytes
    styles_artifact.extend(styles_chunk)
for styles_relative, styles_css in styles_source_data.items():
    if styles_coverage[styles_relative] != len(styles_css):
        raise RuntimeError("styles manifest source coverage drift: " + styles_relative)
styles_manifest["artifact_contract"]["sha256"] = hashlib.sha256(bytes(styles_artifact)).hexdigest()
styles_manifest["artifact_contract"]["bytes"] = len(styles_artifact)
write_json(styles_manifest_path, styles_manifest)

PY;
if (substr_count($helperSource, $printOld) !== 1) {
    throw new RuntimeException('Styles manifest recalculation insertion drift');
}
$helperSource = str_replace($printOld, $manifestBlock . $printOld, $helperSource);
file_put_contents($helperPath, $helperSource, LOCK_EX);

unlink(__FILE__);
