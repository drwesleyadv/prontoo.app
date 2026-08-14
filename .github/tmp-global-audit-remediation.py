from __future__ import annotations

import json
import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OLD = "1.8.13.8"
NEW = "1.8.13.9"
TIMESTAMP = "2026-08-13T20:20:00-04:00"
UNIX = 1786666800


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace(path: str, old: str, new: str, expected: int = 1) -> None:
    content = read(path)
    count = content.count(old)
    if count != expected:
        raise RuntimeError(f"{path}: expected {expected} occurrences, found {count}: {old[:80]}")
    write(path, content.replace(old, new))


def sub(path: str, pattern: str, replacement: str, expected: int = 1, flags: int = 0) -> None:
    content = read(path)
    updated, count = re.subn(pattern, replacement, content, flags=flags)
    if count != expected:
        raise RuntimeError(f"{path}: expected {expected} regex replacements, found {count}: {pattern[:100]}")
    write(path, updated)


def json_file(path: str) -> dict:
    data = json.loads(read(path))
    if not isinstance(data, dict):
        raise RuntimeError(f"{path}: expected JSON object")
    return data


def write_json(path: str, data: dict) -> None:
    write(path, json.dumps(data, ensure_ascii=False, indent=4) + "\n")


version = json_file("version.json")
if version.get("version") != OLD or version.get("release") != OLD:
    raise RuntimeError("version.json changed since remediation preparation")
version.update({
    "version": NEW,
    "release": NEW,
    "generated_at_unix": UNIX,
    "generated_at": TIMESTAMP,
    "updated_at": TIMESTAMP,
    "build": NEW + "-global-audit-remediation",
    "asset_version": NEW,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "functional_equivalence_policy": "canonical_current_state_global_audit_remediation_without_schema_change",
    "notes": "Fecha hardening canônico, remove superfície PHP de dispositivo persistente aposentado, elimina metadata arquitetural duplicada e fixa a supply chain do CI sem alterar schema.",
    "deployment_sync_id": "github-prontoo-" + NEW + "-global-audit-remediation",
    "deployment_sync_requested_at": TIMESTAMP,
    "release_date": "2026-08-13",
})
version["changelog"] = {
    "title": "Remediação da auditoria global",
    "items": [
        "executa diretamente os hardenings canônicos de storage, HTTPS e sanitização de erros sem guards globais inertes",
        "remove a API PHP aposentada de dispositivo persistente e mantém revogação de sessões pela geração canônica de autenticação",
        "mantém pi_user_devices apenas como estrutura congelada de schema, sem mecanismo ativo no runtime",
        "elimina métricas Runtime duplicadas do manifesto e mantém o budget monotônico como fonte verificável",
        "fixa GitHub Actions e MySQL por identidade imutável e instala Playwright a partir de lockfile",
        "remove resíduos documentais e fortalece contratos anti-regressão da auditoria global",
    ],
}
write_json("version.json", version)

sub("app/prontoo.php", r'const PRONTOO_VERSION_FALLBACK = "1\.8\.13\.8";', 'const PRONTOO_VERSION_FALLBACK = "1.8.13.9";')
sub("app/prontoo.php", r'const PRONTOO_ASSET_REV_FALLBACK = "1\.8\.13\.8";', 'const PRONTOO_ASSET_REV_FALLBACK = "1.8.13.9";')
sub("br/index.php", r'const BR_LANDING_VERSION_FALLBACK = "1\.8\.13\.8";', 'const BR_LANDING_VERSION_FALLBACK = "1.8.13.9";')

for path in ["README.md", "CHANGELOG.md"]:
    content = read(path)
    if OLD in content:
        write(path, content.replace(OLD, NEW))

manifest = json_file("app/architecture.manifest.json")
manifest.pop("runtime_input_boundary_metrics", None)
write_json("app/architecture.manifest.json", manifest)

sub(
    "app/Infrastructure/SecurityAccess/SecurityAccessInfrastructureOperations01.php",
    r'\n    public static function device_cookie_name\(\): string.*?\n    public static function tenant_scoped_tables\(\): array',
    "\n    public static function tenant_scoped_tables(): array",
    flags=re.S,
)

sub(
    "app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations02.php",
    r'\n    public static function security_session_generation_enforce\(int \$uid\): void.*?\n}\s*\Z',
    "\n}\n",
    flags=re.S,
)

sub(
    "app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations03.php",
    r'\n    public static function device_session_revoke_current\(\): void.*?\n    }\n',
    "\n",
    flags=re.S,
)

replace(
    "app/Application/Identity/UserCredentialService.php",
    "        Closure $rotateAuthGeneration,\n        Closure $retirePersistentDevices,\n        Closure $audit,",
    "        Closure $rotateAuthGeneration,\n        Closure $audit,",
)
replace(
    "app/Application/Identity/UserCredentialService.php",
    "            $rotateAuthGeneration,\n            $retirePersistentDevices,\n            $audit,",
    "            $rotateAuthGeneration,\n            $audit,",
)
replace(
    "app/Application/Identity/UserCredentialService.php",
    "            $rotateAuthGeneration($userId);\n            $retirePersistentDevices($userId);\n            $audit(",
    "            $rotateAuthGeneration($userId);\n            $audit(",
)

replace(
    "app/Runtime/AuthOnboarding/AuthOnboardingRuntimeOperations06.php",
    "                            static fn(int $userId): int =>\n                                \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($userId),\n                            static fn(int $userId): mixed =>\n                                \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_retire_persistent_devices_for_user($userId),\n                            static fn(...$arguments): bool =>",
    "                            static fn(int $userId): int =>\n                                \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($userId),\n                            static fn(...$arguments): bool =>",
)

replace(
    "app/Runtime/AuthOnboarding/AuthOnboardingRuntimeOperations01.php",
    '        unset($_SESSION["pending_login_uid"], $_SESSION["pending_device_login"]);',
    '        unset($_SESSION["pending_login_uid"]);',
)
replace(
    "app/Runtime/AuthOnboarding/AuthOnboardingRuntimeOperations01.php",
    "        \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();\n",
    "",
)
replace(
    "app/Runtime/AuthOnboarding/AuthOnboardingRuntimeOperations03.php",
    '        unset($_SESSION["pending_login_uid"], $_SESSION["pending_device_login"]);',
    '        unset($_SESSION["pending_login_uid"]);',
)

for path in [
    "app/Runtime/SecurityAccess/SessionGenerationGuard.php",
    "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations02.php",
]:
    content = read(path)
    content, count = re.subn(r'^\s*\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie\(\);\s*\n', "", content, flags=re.M)
    if count != 1:
        raise RuntimeError(f"{path}: expected one legacy cookie clear call, found {count}")
    write(path, content)

for path in [
    "app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations04.php",
    "app/Runtime/UsersPermissions/UsersPermissionsRuntimeOperations02.php",
]:
    content = read(path)
    content, count = re.subn(r'^\s*\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_retire_persistent_devices_for_user\([^\n]+\);\s*\n', "", content, flags=re.M)
    if count != 1:
        raise RuntimeError(f"{path}: expected one persistent-device retirement call, found {count}")
    write(path, content)

sql_path = "app/Infrastructure/Identity/IdentitySecuritySqlCatalog02.php"
content = read(sql_path)
for operation in [
    "identity.security02.security_retire_persistent_devices_for_user.01",
    "identity.security02.device_session_context_payload.01",
]:
    pattern = rf"\n\s*'{re.escape(operation)}' => \(\n\s*\"[^\n]+\"\n\s*\),"
    content, count = re.subn(pattern, "", content)
    if count != 1:
        raise RuntimeError(f"{sql_path}: operation removal failed: {operation} ({count})")
write(sql_path, content)

sa1 = "app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations01.php"
replace(
    sa1,
    '        if (function_exists("security_disable_runtime_error_display")) {\n            \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_disable_runtime_error_display();\n        }',
    '        \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_disable_runtime_error_display();',
)
replace(
    sa1,
    '        if (\n            function_exists("security_storage_deny_file") &&\n            is_callable([\\Prontoo\\Infrastructure\\SupportFoundation\\SupportFoundationInfrastructureOperations01::class, \'storage_path\'])\n        ) {',
    '        if (is_callable([\\Prontoo\\Infrastructure\\SupportFoundation\\SupportFoundationInfrastructureOperations01::class, \'storage_path\'])) {',
)
replace(
    sa1,
    '        $secure = function_exists("security_https_active")\n            ? \\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active()\n            : (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||\n                (string) ($_SERVER["SERVER_PORT"] ?? "") === "443";',
    '        $secure = \\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active();',
)
replace(
    sa1,
    '        \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();\n',
    "",
)
sub(
    sa1,
    r'        if \(\n            function_exists\("security_https_active"\)\n                \? \\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active\(\)\n                : \(!empty\(\$_SERVER\["HTTPS"\]\) && \$_SERVER\["HTTPS"\] !== "off"\) \|\|\n                    \(string\) \(\$_SERVER\["SERVER_PORT"\] \?\? ""\) === "443"\n        \) \{',
    '        if (\\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active()) {',
)

sf1 = "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations01.php"
sub(
    sf1,
    r'    public static function is_https\(\): bool\s*\{\s*if \(function_exists\("security_https_active"\)\) \{\s*return \\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active\(\);\s*\}\s*return \(!empty\(\$_SERVER\["HTTPS"\]\) && \$_SERVER\["HTTPS"\] !== "off"\) \|\|\s*\(string\) \(\$_SERVER\["SERVER_PORT"\] \?\? ""\) === "443";\s*\}',
    '    public static function is_https(): bool\n    {\n        return \\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active();\n    }',
    flags=re.S,
)
replace(
    sf1,
    '(function_exists("privacy_sanitize_error_message")\n                        ? \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($e, 240)\n                        : $e->getMessage())',
    '\\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($e, 240)',
)
replace(
    sf1,
    '(function_exists("privacy_log_file_label")\n                        ? \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_log_file_label($e->getFile())\n                        : basename($e->getFile()))',
    '\\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_log_file_label($e->getFile())',
)

sf2 = "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations02.php"
replace(
    sf2,
    '        $message = function_exists("privacy_sanitize_error_message")\n            ? \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($e, 900)\n            : mb_substr($e->getMessage(), 0, 900);',
    '        $message = \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($e, 900);',
)
replace(
    sf2,
    '        $file = function_exists("privacy_log_file_label")\n            ? \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_log_file_label($e->getFile())\n            : basename($e->getFile());',
    '        $file = \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_log_file_label($e->getFile());',
)
replace(
    sf2,
    '(function_exists("privacy_sanitize_error_message")\n                        ? \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($ignored, 240)\n                        : $ignored->getMessage())',
    '\\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($ignored, 240)',
)

replace(
    "app/Infrastructure/DatabaseSchema/DatabaseSchemaInfrastructureOperations01.php",
    '        $message = function_exists("privacy_sanitize_error_message")\n            ? \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($error, 220)\n            : mb_substr($error->getMessage(), 0, 220);',
    '        $message = \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($error, 220);',
)

storage_guard_files = [
    "app/Infrastructure/DeferredAudit/DeferredAuditInfrastructureOperations01.php",
    "app/Infrastructure/DocumentPdf/DocumentPdfInfrastructureOperations01.php",
    "app/Infrastructure/ServerJsonCache/ServerJsonCacheInfrastructureOperations01.php",
    "app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations01.php",
    "app/Runtime/InstallInstaller/InstallInstallerRuntimeOperations01.php",
    "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations02.php",
]
for path in storage_guard_files:
    content = read(path)
    previous = None
    while previous != content:
        previous = content
        content = re.sub(
            r'(?m)^(?P<i>\s*)if \(function_exists\("security_storage_deny_file"\)\) \{\n(?P<body>(?:(?P=i)    [^\n]*\n)+)(?P=i)\}',
            lambda m: "\n".join(line[4:] if line.startswith(m.group("i") + "    ") else line for line in m.group("body").rstrip("\n").split("\n")),
            content,
        )
    write(path, content)

security_test = "tools/security-regression-check.php"
sub(
    security_test,
    r'\nfunction security_storage_deny_file\(string \$dir\): void\s*\{.*?\n}\n\nfunction security_regression_assert',
    "\nfunction security_regression_assert",
    flags=re.S,
)

install_check = "tools/install-security-check.php"
sub(
    install_check,
    r'if \(!preg_match\(\'/function\\s\+device_session_auto_login.*?\$errors\[\] = \'persistent_device_retirement_policy\';\n}\n',
    """$retiredDeviceTokens = [
    'PRONTOO_DEVICE',
    'pending_device_login',
    'device_session_auto_login',
    'device_session_remember_after_login',
    'device_session_update_current_context',
    'device_session_enforce_current',
    'device_session_revoke_current',
    'device_cookie_name',
    'security_clear_legacy_device_cookie',
    'security_retire_persistent_devices_for_user',
    'security_session_generation_enforce',
];
foreach ($retiredDeviceTokens as $retiredDeviceToken) {
    if (str_contains($securityAccessSource . $authSecuritySource, $retiredDeviceToken)) {
        $errors[] = 'retired_persistent_device_surface:' . $retiredDeviceToken;
    }
}
if (!str_contains($securityAccessSource, 'user_auth_generation_rotate')) {
    $errors[] = 'auth_generation_revocation_missing';
}
""",
    flags=re.S,
)
sub(
    install_check,
    r'if \(str_contains\(\$authSecuritySource, \'\\\\Prontoo\\\\Infrastructure\\\\SecurityAccess\\\\SecurityAccessInfrastructureOperations01::device_login_fields\(\'\).*?\$errors\[\] = \'persistent_device_auth_surface\';\n}\n',
    "",
    flags=re.S,
)
sub(
    install_check,
    r'\$foundationAuthSource = canonical_source\(\$root, \[.*?\]\);\nif \(str_contains\(\$foundationAuthSource, .*?\$errors\[\] = \'login_autotest_persistent_device_bypass\';\n}\n',
    "$foundationAuthSource = canonical_source($root, [\"app/Infrastructure/SupportFoundation/SupportFoundationInfrastructureOperations01.php\", \"app/Presentation/SupportFoundation/SupportFoundationPresentationOperations01.php\", \"app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations01.php\", \"app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations02.php\"]);\nforeach ($retiredDeviceTokens as $retiredDeviceToken) {\n    if (str_contains($foundationAuthSource, $retiredDeviceToken)) {\n        $errors[] = 'retired_persistent_device_foundation_surface:' . $retiredDeviceToken;\n    }\n}\n",
    flags=re.S,
)

arch_check = "tools/architecture-consolidation-check"
replace(
    arch_check,
    "$failures = [];",
    "$failures = [];\nif (array_key_exists('runtime_input_boundary_metrics', $manifest)) {\n    $failures[] = 'duplicate_runtime_input_boundary_metrics';\n}",
)

checkout = "11d5960a326750d5838078e36cf38b85af677262"
setup_node = "49933ea5288caeca8642d1e84afbd3f7d6820020"
setup_php = "f3e473d116dcccaddc5834248c87452386958240"
mysql = "mysql:8.0.43@sha256:3e646bcda0d9448ffa3d2024eef04e1bca95528ec19b9e8b76749da9d97d4a10"
for path in [".github/workflows/architecture.yml", ".github/workflows/documentation.yml", ".github/workflows/presentation-ux.yml"]:
    content = read(path)
    content = content.replace("actions/checkout@v4", "actions/checkout@" + checkout)
    content = content.replace("actions/setup-node@v4", "actions/setup-node@" + setup_node)
    content = content.replace("shivammathur/setup-php@v2", "shivammathur/setup-php@" + setup_php)
    write(path, content)
replace(".github/workflows/architecture.yml", "image: mysql:8.0", "image: " + mysql)
replace(
    ".github/workflows/presentation-ux.yml",
    "          npm install --no-save --no-package-lock playwright@1.59.1",
    "          npm ci --ignore-scripts",
)

write_json("package.json", {
    "name": "prontoo-ci-contracts",
    "version": "1.0.0",
    "private": True,
    "devDependencies": {"playwright": "1.59.1"},
})

global_contract = r'''#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$root = dirname(__DIR__);
$forbidden = [
    'PRONTOO_DEVICE',
    'pending_device_login',
    'device_session_auto_login',
    'device_session_remember_after_login',
    'device_session_update_current_context',
    'device_session_enforce_current',
    'device_session_revoke_current',
    'device_cookie_name',
    'security_clear_legacy_device_cookie',
    'security_retire_persistent_devices_for_user',
    'security_session_generation_enforce',
    'identity.security02.security_retire_persistent_devices_for_user.01',
    'identity.security02.device_session_context_payload.01',
    'function_exists("security_storage_deny_file")',
    'function_exists("security_disable_runtime_error_display")',
    'function_exists("security_https_active")',
    'function_exists("privacy_sanitize_error_message")',
    'function_exists("privacy_log_file_label")',
];
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'json'], true)) {
        $files[] = $file->getPathname();
    }
}
$failures = [];
foreach ($files as $file) {
    $source = (string) file_get_contents($file);
    foreach ($forbidden as $token) {
        if (str_contains($source, $token)) {
            $failures[] = str_replace($root . '/', '', $file) . ':' . $token;
        }
    }
}
$manifest = json_decode((string) file_get_contents($root . '/app/architecture.manifest.json'), true, 512, JSON_THROW_ON_ERROR);
if (array_key_exists('runtime_input_boundary_metrics', (array) $manifest)) {
    $failures[] = 'app/architecture.manifest.json:runtime_input_boundary_metrics';
}
$result = [
    'ok' => $failures === [],
    'policy' => 'canonical-global-audit-remediation-v1',
    'forbidden_tokens' => count($forbidden),
    'failures' => array_values(array_unique($failures)),
];
fwrite($failures === [] ? STDOUT : STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($failures === [] ? 0 : 1);
'''
write("tools/global-audit-contract-check", global_contract)
(ROOT / "tools/global-audit-contract-check").chmod(0o755)

ci_contract = r'''#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$root = dirname(__DIR__);
$checkout = '11d5960a326750d5838078e36cf38b85af677262';
$setupNode = '49933ea5288caeca8642d1e84afbd3f7d6820020';
$setupPhp = 'f3e473d116dcccaddc5834248c87452386958240';
$mysql = 'mysql:8.0.43@sha256:3e646bcda0d9448ffa3d2024eef04e1bca95528ec19b9e8b76749da9d97d4a10';
$workflows = [
    'architecture' => (string) file_get_contents($root . '/.github/workflows/architecture.yml'),
    'documentation' => (string) file_get_contents($root . '/.github/workflows/documentation.yml'),
    'presentation' => (string) file_get_contents($root . '/.github/workflows/presentation-ux.yml'),
];
$failures = [];
foreach ($workflows as $name => $source) {
    if (preg_match('/uses:\s+[^\s]+@v\d+/i', $source) === 1) {
        $failures[] = $name . ':floating_action_tag';
    }
    if (!str_contains($source, 'actions/checkout@' . $checkout)) {
        $failures[] = $name . ':checkout_pin';
    }
}
foreach (['architecture', 'documentation'] as $name) {
    if (!str_contains($workflows[$name], 'shivammathur/setup-php@' . $setupPhp)) {
        $failures[] = $name . ':setup_php_pin';
    }
}
if (!str_contains($workflows['presentation'], 'actions/setup-node@' . $setupNode)) {
    $failures[] = 'presentation:setup_node_pin';
}
if (!str_contains($workflows['architecture'], 'image: ' . $mysql)) {
    $failures[] = 'architecture:mysql_digest';
}
if (!str_contains($workflows['presentation'], 'npm ci --ignore-scripts') || str_contains($workflows['presentation'], '--no-package-lock')) {
    $failures[] = 'presentation:lockfile_install';
}
$package = json_decode((string) file_get_contents($root . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
$lock = json_decode((string) file_get_contents($root . '/package-lock.json'), true, 512, JSON_THROW_ON_ERROR);
if (($package['devDependencies']['playwright'] ?? '') !== '1.59.1' || ($lock['packages']['node_modules/playwright']['version'] ?? '') !== '1.59.1') {
    $failures[] = 'playwright:version_lock';
}
$result = [
    'ok' => $failures === [],
    'policy' => 'immutable-ci-supply-chain-v1',
    'failures' => $failures,
];
fwrite($failures === [] ? STDOUT : STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($failures === [] ? 0 : 1);
'''
write("tools/ci-supply-chain-check", ci_contract)
(ROOT / "tools/ci-supply-chain-check").chmod(0o755)

quality = read("tools/quality-gate")
needle = "$run('architecture-consolidation-contract', 'tools/architecture-consolidation-check');\n"
if quality.count(needle) != 1:
    raise RuntimeError("quality-gate insertion point drift")
quality = quality.replace(
    needle,
    needle + "$run('global-audit-contract', 'tools/global-audit-contract-check');\n$run('ci-supply-chain-contract', 'tools/ci-supply-chain-check');\n",
)
write("tools/quality-gate", quality)

components = "app/Presentation/Styles/components/components.css"
replace(components, "pix-" + OLD + ".svg", "pix-" + NEW + ".svg")

assets = [
    ("app-icon", ".png"),
    ("favicon", ".png"),
    ("favicon", ".ico"),
    ("pix", ".svg"),
    ("prontoo-mark", ".png"),
]
for prefix, suffix in assets:
    src = ROOT / "public/assets" / f"{prefix}-{OLD}{suffix}"
    dst = ROOT / "public/assets" / f"{prefix}-{NEW}{suffix}"
    if not src.is_file():
        raise RuntimeError(f"missing canonical asset: {src.relative_to(ROOT)}")
    shutil.copy2(src, dst)
    src.unlink()

for path in [
    "app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations01.php",
    "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations01.php",
    "app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations02.php",
    "app/Infrastructure/DatabaseSchema/DatabaseSchemaInfrastructureOperations01.php",
    *storage_guard_files,
]:
    source = read(path)
    for token in [
        'function_exists("security_storage_deny_file")',
        'function_exists("security_disable_runtime_error_display")',
        'function_exists("security_https_active")',
        'function_exists("privacy_sanitize_error_message")',
        'function_exists("privacy_log_file_label")',
    ]:
        if token in source:
            raise RuntimeError(f"{path}: unresolved compatibility guard: {token}")

retired = [
    "PRONTOO_DEVICE",
    "pending_device_login",
    "device_session_auto_login",
    "device_session_remember_after_login",
    "device_session_update_current_context",
    "device_session_enforce_current",
    "device_session_revoke_current",
    "device_cookie_name",
    "security_clear_legacy_device_cookie",
    "security_retire_persistent_devices_for_user",
    "security_session_generation_enforce",
]
for path in (ROOT / "app").rglob("*.php"):
    source = path.read_text(encoding="utf-8")
    for token in retired:
        if token in source:
            raise RuntimeError(f"{path.relative_to(ROOT)}: retired token remains: {token}")

print(json.dumps({"ok": True, "version": NEW, "policy": "global-audit-remediation-transform-v1"}, ensure_ascii=False))
