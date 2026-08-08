from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text()

def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content)

def replace_once(path: str, old: str, new: str) -> None:
    source = read(path)
    if old not in source:
        raise SystemExit(f'expected block not found: {path}: {old[:120]}')
    write(path, source.replace(old, new, 1))

path = 'app/Runtime/Runner.php'
source = read(path)
source = source.replace(
    'use Prontoo\\Runtime\\Modules\\RuntimeModuleComposition;\n',
    'use Prontoo\\Runtime\\Modules\\RuntimeModuleComposition;\nuse Prontoo\\Runtime\\FinancialGuard\\FinancialGuardRuntimeOperations01;\n',
    1,
)
old = '''            if ($context &&
                ($context['scope'] ?? '') === 'clinic' &&
                function_exists('financial_cashier_requires_attention_light') &&
                \\financial_cashier_requires_attention_light($context) &&
                !in_array($route, ['financial', 'logout', 'switch', 'goal_status', 'notices'], true)) {'''
new = '''            if ($context &&
                ($context['scope'] ?? '') === 'clinic' &&
                FinancialGuardRuntimeOperations01::financial_cashier_requires_attention_light($context) &&
                !in_array($route, ['financial', 'logout', 'switch', 'goal_status', 'notices'], true)) {'''
if old not in source:
    raise SystemExit(f'expected runner financial guard block not found: {path}')
write(path, source.replace(old, new, 1))

path = 'app/Runtime/DatabaseSchema/DatabaseSchemaRuntimeOperations01.php'
source = read(path)
source = source.replace(
    'use \\Throwable;\n',
    'use \\Throwable;\nuse Prontoo\\Runtime\\FinancialGuard\\FinancialGuardRuntimeOperations01;\n',
    1,
)
old = '''                if (function_exists("financial_movement_write_guard")) {
                    financial_movement_write_guard($sql, $params);
                }'''
new = '''                FinancialGuardRuntimeOperations01::financial_movement_write_guard($sql, $params);'''
if old not in source:
    raise SystemExit(f'expected database financial guard block not found: {path}')
write(path, source.replace(old, new, 1))

replace_once(
    'app/Runtime/Modules/RuntimeModuleCatalog.php',
    "            'Support/FinancialGuard.php',\n",
    '',
)

path = 'app/Presentation/SpeedChartGeometry/SpeedChartGeometryPresentationOperations01.php'
source = read(path)
source = source.replace('$html = prontoo_performance_replace_chart(', '$html = self::prontoo_performance_replace_chart(')
source = source.replace('$html = prontoo_performance_transform_html($html);', '$html = self::prontoo_performance_transform_html($html);')
source = source.replace("prontoo_speed_chart_geometry_script() . '</body>'", "self::prontoo_speed_chart_geometry_script() . '</body>'")
source = source.replace(
    "ob_start('prontoo_performance_geometry_transform_html');",
    "ob_start([self::class, 'prontoo_performance_geometry_transform_html']);",
)
write(path, source)

replace_once(
    'app/prontoo.php',
    'require_once __DIR__ . "/bootstrap_specialized.php";',
    '''\\Prontoo\\Presentation\\SpeedChartGeometry\\SpeedChartGeometryPresentationOperations01::prontoo_register_speed_chart_geometry();
\\Prontoo\\Runtime\\Modules\\RuntimeModuleComposition::loader()->loadRuntimeCore(
    \\Prontoo\\Runtime\\Modules\\RuntimeBootPolicy::useLightBoot(getenv('PRONTOO_DISABLE_LIGHT_BOOT')),
);''',
)
replace_once(
    'app/Core/Install/RuntimeContract.php',
    "            'app/bootstrap_specialized.php',\n",
    '',
)
replace_once(
    'tools/solid-audit',
    "    'app/bootstrap_specialized.php',\n",
    '',
)

version_path = ROOT / 'version.json'
version = json.loads(version_path.read_text())
if int(version.get('architecture_transitional_files_max', 0)) != 50:
    raise SystemExit('unexpected architecture_transitional_files_max before phase 4')
version['architecture_transitional_files_max'] = 47
migrations = version.setdefault('php84_baseline_path_migrations', {})
migrations['app/Support/FinancialGuard.php'] = 'app/Runtime/FinancialGuard/FinancialGuardRuntimeOperations01.php'
migrations['app/Ui/SpeedChartGeometry.php'] = 'app/Presentation/SpeedChartGeometry/SpeedChartGeometryPresentationOperations01.php'
migrations['app/bootstrap_specialized.php'] = [
    'app/prontoo.php',
    'app/Runtime/Modules/RuntimeModuleComposition.php',
    'app/Runtime/Modules/RuntimeBootPolicy.php',
    'app/Presentation/SpeedChartGeometry/SpeedChartGeometryPresentationOperations01.php',
]
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

manifest_path = ROOT / 'app/architecture.manifest.json'
manifest = json.loads(manifest_path.read_text())
manifest['transitional_files_max'] = 47
removed = list(manifest.get('removed_legacy_files', []))
removed_targets = [
    'app/Support/FinancialGuard.php',
    'app/Ui/SpeedChartGeometry.php',
    'app/bootstrap_specialized.php',
]
for target in removed_targets:
    if target not in removed:
        removed.append(target)
manifest['removed_legacy_files'] = removed
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

for target in removed_targets:
    file = ROOT / target
    if not file.exists():
        raise SystemExit(f'phase 4 target already absent: {target}')
    file.unlink()

checks = {
    'app/Runtime/Modules/RuntimeModuleCatalog.php': ['Support/FinancialGuard.php'],
    'app/prontoo.php': ['bootstrap_specialized.php'],
    'tools/solid-audit': ['app/bootstrap_specialized.php'],
}
for path, tokens in checks.items():
    value = read(path)
    for token in tokens:
        if token in value:
            raise SystemExit(f'phase 4 removed target still referenced: {path}: {token}')

speed = read('app/Presentation/SpeedChartGeometry/SpeedChartGeometryPresentationOperations01.php')
for token in [
    '$html = prontoo_performance_replace_chart(',
    '$html = prontoo_performance_transform_html($html);',
    "ob_start('prontoo_performance_geometry_transform_html');",
]:
    if token in speed:
        raise SystemExit(f'legacy speed chart global callback remains: {token}')

print('phase4 thin facades transform ok')
