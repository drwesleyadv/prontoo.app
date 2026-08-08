from pathlib import Path
import json
import runpy
import subprocess

root = Path(__file__).resolve().parents[1]
runpy.run_path(str(Path(__file__).with_name('zero-legacy-final-v2.py')), run_name='__main__')

# Preserve explicit native seams for environment/persistence dependencies exercised
# by the security regression harness. Production still receives the canonical
# handlers from OperationRegistry; tests may replace only the selected handlers.
gateway_path = root / 'app/Core/Architecture/OperationGateway.php'
gateway_source = gateway_path.read_text()
register_block = """    public static function register(array $handlers): void
    {
        self::$handlers = $handlers;
    }
"""
override_block = register_block + """    public static function override(string $name, callable $handler): void
    {
        self::$handlers[$name] = $handler;
    }
"""
if register_block not in gateway_source:
    raise SystemExit('operation gateway register shape changed')
gateway_path.write_text(gateway_source.replace(register_block, override_block, 1))

security_runtime_path = root / 'app/Runtime/SecurityAccess/SecurityAccessRuntimeOperations01.php'
security_runtime = security_runtime_path.read_text()
security_replacements = {
    r'\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()':
        r"\Prontoo\Core\Architecture\OperationGateway::invoke('has_cfg')",
    r'\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(':
        r"\Prontoo\Core\Architecture\OperationGateway::invoke('val', ",
    r'\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key()':
        r"\Prontoo\Core\Architecture\OperationGateway::invoke('secret_key')",
}
for old, new in security_replacements.items():
    if old not in security_runtime:
        raise SystemExit(f'security regression seam target missing: {old}')
    security_runtime = security_runtime.replace(old, new)
security_runtime_path.write_text(security_runtime)

security_test_path = root / 'tools/security-regression-check.php'
security_test = security_test_path.read_text()
autoload_line = 'require_once dirname(__DIR__) . "/app/Runtime/Autoload/ProntooAutoloader.php";\n'
seam_setup = autoload_line + r"""\Prontoo\Runtime\Architecture\OperationRegistry::register();
\Prontoo\Core\Architecture\OperationGateway::override('has_cfg', static function (): bool {
    return (bool) $GLOBALS['prontoo_test_has_cfg'];
});
\Prontoo\Core\Architecture\OperationGateway::override('val', static function (string $sql, array $params = []): mixed {
    return val($sql, $params);
});
\Prontoo\Core\Architecture\OperationGateway::override('secret_key', static function (): string {
    return str_repeat('s', 48);
});
"""
if autoload_line not in security_test:
    raise SystemExit('security regression autoloader shape changed')
security_test_path.write_text(security_test.replace(autoload_line, seam_setup, 1))

version_path = root / 'version.json'
version = json.loads(version_path.read_text())
migrations = version.setdefault('architecture_source_path_migrations', {})
for source, target in {
    'app/Auth/AuthOnboarding.php': 'app/Presentation/AuthOnboarding/AdminChoiceCardOperation.php',
    'app/Admin/AdminPages.php': 'app/Presentation/AdminPages/AdminChoiceCardOperation.php',
}.items():
    current = migrations.get(source, [])
    targets = current if isinstance(current, list) else [current]
    targets = [str(item) for item in targets if str(item)]
    if target not in targets:
        targets.append(target)
    migrations[source] = targets[0] if len(targets) == 1 else targets
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

for path in root.rglob('*.php'):
    rel = path.relative_to(root).as_posix()
    if any(part in rel for part in ('.git/', 'ssd/', 'vendor/', 'node_modules/')):
        continue
    source = path.read_text()
    normalized = source.replace('\\\\Prontoo\\Core\\Architecture\\OperationGateway', '\\Prontoo\\Core\\Architecture\\OperationGateway')
    if normalized != source:
        path.write_text(normalized)
    result = subprocess.run(['php', '-l', str(path)], text=True, capture_output=True)
    if result.returncode != 0:
        print(f'GENERATED_PHP_SYNTAX_ERROR={rel}')
        print(result.stdout)
        print(result.stderr)
        raise SystemExit(result.returncode)