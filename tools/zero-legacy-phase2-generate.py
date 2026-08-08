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

write('app/Runtime/Routing/PageDispatcher.php', '''<?php
declare(strict_types=1);

namespace Prontoo\\Runtime\\Routing;

use Prontoo\\Runtime\\Dashboards\\DashboardsRuntimeOperations01;
use Prontoo\\Runtime\\Dashboards\\DashboardsRuntimeOperations03;
use Prontoo\\Runtime\\PublicWeb\\PublicWebRuntimeOperations01;

final class PageDispatcher
{
    private const NATIVE_ROUTES = [
        'home',
        'painel',
        'mobile_web_access',
    ];

    private function __construct()
    {
    }

    public static function nativeRoutes(): array
    {
        return self::NATIVE_ROUTES;
    }

    public static function hasNativeHandler(string $route): bool
    {
        return in_array($route, self::NATIVE_ROUTES, true);
    }

    public static function dispatch(string $route): bool
    {
        switch ($route) {
            case 'home':
                DashboardsRuntimeOperations01::page_home();
                return true;
            case 'painel':
                DashboardsRuntimeOperations03::page_painel();
                return true;
            case 'mobile_web_access':
                PublicWebRuntimeOperations01::page_mobile_web_access();
                return true;
            default:
                return false;
        }
    }
}
''')

path = 'app/Runtime/Runner.php'
source = read(path)
source = source.replace(
    'use Prontoo\\Runtime\\Routing\\RouteCatalog;\n',
    'use Prontoo\\Runtime\\Routing\\RouteCatalog;\nuse Prontoo\\Runtime\\Routing\\PageDispatcher;\n',
    1,
)
old = '''            $page = in_array($route, RouteCatalog::all(), true) ? 'page_' . $route : 'page_home';
            if (!function_exists($page)) {
                throw new \\RuntimeException('Rota sem função de página: ' . $page);
            }
            $page();'''
new = '''            $effectiveRoute = in_array($route, RouteCatalog::all(), true) ? $route : 'home';
            if (!PageDispatcher::dispatch($effectiveRoute)) {
                $page = 'page_' . $effectiveRoute;
                if (!function_exists($page)) {
                    throw new \\RuntimeException('Rota sem função de página: ' . $page);
                }
                $page();
            }'''
if old not in source:
    raise SystemExit(f'expected runner page dispatch block not found: {path}')
write(path, source.replace(old, new, 1))

path = 'app/Core/Install/RuntimeContract.php'
source = read(path)
source = source.replace(
    'use Prontoo\\Runtime\\Routing\\RouteCatalog;\n',
    'use Prontoo\\Runtime\\Routing\\RouteCatalog;\nuse Prontoo\\Runtime\\Routing\\PageDispatcher;\n',
    1,
)
old = "        return ['page_home', 'audit', 'audit_items', 'verify_audit_row'];"
new = "        return ['audit', 'audit_items', 'verify_audit_row'];"
if old not in source:
    raise SystemExit(f'expected requiredFullFunctions page_home not found: {path}')
source = source.replace(old, new, 1)
source = source.replace(
    '            LayeredKernel::class,\n',
    '            LayeredKernel::class,\n            PageDispatcher::class,\n',
    1,
)
old = '''            foreach (RouteCatalog::all() as $route) {
                $function = 'page_' . $route;
                if (!\\function_exists($function)) {
                    throw new \\RuntimeException('Rota sem função de página: ' . $function);
                }
            }'''
new = '''            foreach (RouteCatalog::all() as $route) {
                if (PageDispatcher::hasNativeHandler($route)) {
                    continue;
                }
                $function = 'page_' . $route;
                if (!\\function_exists($function)) {
                    throw new \\RuntimeException('Rota sem função de página: ' . $function);
                }
            }'''
if old not in source:
    raise SystemExit(f'expected runtime route assertion block not found: {path}')
write(path, source.replace(old, new, 1))

path = 'app/Runtime/Dashboards/DashboardsRuntimeOperations03.php'
source = read(path)
source = source.replace(
    'use \\Throwable;\n',
    'use \\Throwable;\nuse Prontoo\\Presentation\\Dashboards\\DashboardsPresentationOperations01;\n',
    1,
)
for old, new in [
    ('manager_metric_val(', 'DashboardsRuntimeOperations02::manager_metric_val('),
    ('manager_metric_row(', 'DashboardsRuntimeOperations02::manager_metric_row('),
    ('manager_count_business_days(', 'DashboardsPresentationOperations01::manager_count_business_days('),
    ('manager_percent_label(', 'DashboardsPresentationOperations01::manager_percent_label('),
    ('manager_dashboard_card(', 'DashboardsPresentationOperations01::manager_dashboard_card('),
    ('manager_action_card(', 'DashboardsRuntimeOperations02::manager_action_card('),
    ('page_medico_painel($c)', 'DashboardsRuntimeOperations01::page_medico_painel($c)'),
    ('page_recepcao_painel($c)', 'DashboardsRuntimeOperations01::page_recepcao_painel($c)'),
    ('page_triagem_painel($c)', 'DashboardsRuntimeOperations02::page_triagem_painel($c)'),
]:
    source = source.replace(old, new)
write(path, source)

replace_once('app/Runtime/Modules/RuntimeModuleCatalog.php', "            'Ui/PublicWeb.php',\n", '')
replace_once('app/Runtime/Modules/RuntimeModuleCatalog.php', "            'Pages/Dashboards.php',\n", '')
replace_once(
    'app/Runtime/Modules/RuntimeModuleCatalog.php',
    "        $commonClinic = ['dashboards' => ['Pages/Dashboards.php']];",
    "        $commonClinic = [];",
)

version_path = ROOT / 'version.json'
version = json.loads(version_path.read_text())
if int(version.get('architecture_native_files_min', 0)) != 277 or int(version.get('architecture_transitional_files_max', 0)) != 47:
    raise SystemExit('unexpected architecture baselines before phase 5')
version['architecture_native_files_min'] = 278
version['architecture_transitional_files_max'] = 45
migrations = version.setdefault('php84_baseline_path_migrations', {})
migrations['app/Pages/Dashboards.php'] = [
    'app/Runtime/Routing/PageDispatcher.php',
    'app/Runtime/Dashboards/DashboardsRuntimeOperations01.php',
    'app/Runtime/Dashboards/DashboardsRuntimeOperations02.php',
    'app/Runtime/Dashboards/DashboardsRuntimeOperations03.php',
    'app/Presentation/Dashboards/DashboardsPresentationOperations01.php',
]
migrations['app/Ui/PublicWeb.php'] = [
    'app/Runtime/Routing/PageDispatcher.php',
    'app/Runtime/PublicWeb/PublicWebRuntimeOperations01.php',
    'app/Presentation/PublicWeb/PublicWebEscapeOperation.php',
]
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

manifest_path = ROOT / 'app/architecture.manifest.json'
manifest = json.loads(manifest_path.read_text())
manifest['native_files_min'] = 278
manifest['transitional_files_max'] = 45
removed = list(manifest.get('removed_legacy_files', []))
removed_targets = ['app/Pages/Dashboards.php', 'app/Ui/PublicWeb.php']
for target in removed_targets:
    if target not in removed:
        removed.append(target)
manifest['removed_legacy_files'] = removed
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

for target in removed_targets:
    file = ROOT / target
    if not file.exists():
        raise SystemExit(f'phase 5 target already absent: {target}')
    file.unlink()

catalog = read('app/Runtime/Modules/RuntimeModuleCatalog.php')
for token in ['Ui/PublicWeb.php', 'Pages/Dashboards.php']:
    if token in catalog:
        raise SystemExit(f'removed page facade remains in module catalog: {token}')
runner = read('app/Runtime/Runner.php')
if "? 'page_' . $route : 'page_home'" in runner:
    raise SystemExit('legacy page_home fallback remains in Runner')
contract = read('app/Core/Install/RuntimeContract.php')
if "['page_home', 'audit'" in contract:
    raise SystemExit('page_home remains a required global function')

print('phase5 native page dispatcher transform ok')
