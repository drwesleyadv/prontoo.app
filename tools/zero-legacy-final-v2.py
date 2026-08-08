from pathlib import Path
import json
import re

root = Path(__file__).resolve().parents[1]
facades = [
    'app/Admin/AdminPages.php','app/Auth/AuthOnboarding.php','app/Database/DatabaseSchema.php',
    'app/Domain/Appointments/Appointments.php','app/Domain/Audit/AuditActivity.php','app/Domain/Clinic/ClinicConfig.php',
    'app/Domain/Clinic/SubscriptionSettings.php','app/Domain/Documents/DocumentPdf.php','app/Domain/Documents/Documents.php',
    'app/Domain/Financial/Financial.php','app/Domain/Maestro/Maestro.php','app/Domain/Patients/Patients.php',
    'app/Domain/Permissions/UsersPermissions.php','app/Domain/Tasks/TasksNotices.php','app/Install/Installer.php',
    'app/Support/DeferredAudit.php','app/Support/Foundation.php','app/Support/Runtime.php','app/Support/SecurityAccess.php',
    'app/Support/ServerJsonCache.php','app/Support/Telemetry.php','app/Ui/Components.php',
]

def function_blocks(source: str):
    out = []
    for match in re.finditer(r'(?m)^function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(', source):
        name = match.group(1)
        brace = source.find('{', match.end())
        if brace < 0:
            continue
        depth = 0
        end = None
        for index in range(brace, len(source)):
            if source[index] == '{':
                depth += 1
            elif source[index] == '}':
                depth -= 1
                if depth == 0:
                    end = index + 1
                    break
        if end is None:
            continue
        body = source[brace + 1:end - 1]
        target = re.search(r'(\\Prontoo\\[A-Za-z0-9_\\]+::[A-Za-z_][A-Za-z0-9_]*)\s*\(', body)
        if not target:
            raise SystemExit(f'unmapped facade function: {name}')
        out.append((name, target.group(1)))
    return out

mapping = {}
facade_targets = {}
for facade in facades:
    path = root / facade
    if not path.is_file():
        raise SystemExit(f'facade missing before final migration: {facade}')
    targets = []
    for name, target in function_blocks(path.read_text()):
        if name in mapping and mapping[name] != target:
            raise SystemExit(f'duplicate global with divergent target: {name}')
        mapping[name] = target
        cls = target.split('::', 1)[0].lstrip('\\')
        if cls.startswith('Prontoo\\'):
            candidate = 'app/' + cls[len('Prontoo\\'):].replace('\\', '/') + '.php'
            if (root / candidate).is_file():
                targets.append(candidate)
    facade_targets[facade] = sorted(set(targets))

names_alt = '|'.join(re.escape(name) for name in sorted(mapping, key=len, reverse=True))
probe_pattern = re.compile(r"function_exists\(\s*(['\"])(" + names_alt + r")\1\s*\)")
is_callable_pattern = re.compile(r"is_callable\(\s*(['\"])(" + names_alt + r")\1\s*\)")
callback_pattern = re.compile(r"call_user_func\(\s*(['\"])(" + names_alt + r")\1\s*,")
direct_pattern = re.compile(r"(?<![A-Za-z0-9_\\:>$])\\?(" + names_alt + r")\s*\(")

CORE = 'core'; DOMAIN = 'domain'; APPLICATION = 'application'; INFRASTRUCTURE = 'infrastructure'; PRESENTATION = 'presentation'; COMPOSITION = 'composition'
allowed = {
    CORE: {CORE},
    DOMAIN: {CORE, DOMAIN},
    APPLICATION: {CORE, DOMAIN, APPLICATION},
    INFRASTRUCTURE: {CORE, DOMAIN, APPLICATION, INFRASTRUCTURE},
    PRESENTATION: {CORE, DOMAIN, APPLICATION, PRESENTATION},
    COMPOSITION: {CORE, DOMAIN, APPLICATION, INFRASTRUCTURE, PRESENTATION, COMPOSITION},
}
composition_core = {
    'app/Core/Architecture/ArchitectureVerifier.php',
    'app/Core/Install/RuntimeContract.php',
    'app/Core/Install/InstallAccess.php',
    'app/Core/Database/SchemaMutationLock.php',
}

def layer_for_path(relative: str):
    relative = relative.replace('\\', '/').lstrip('/')
    if relative in composition_core:
        return COMPOSITION
    if '/' not in relative or (relative.startswith('app/') and '/' not in relative[4:]):
        return COMPOSITION
    if relative.startswith(('br/', 'public/')):
        return PRESENTATION
    if relative.startswith(('tools/', 'cron/')):
        return COMPOSITION
    if not relative.startswith('app/'):
        return COMPOSITION
    inner = relative[4:]
    if inner.startswith('Core/'):
        return CORE
    if inner.startswith('Domain/'):
        return DOMAIN
    if inner.startswith('Application/'):
        return APPLICATION
    if inner.startswith('Infrastructure/'):
        return INFRASTRUCTURE
    if inner.startswith('Presentation/'):
        return PRESENTATION
    if inner.startswith(('Runtime/', 'Install/')):
        return COMPOSITION
    if inner.startswith(('Database/', 'Support/')):
        return INFRASTRUCTURE
    if inner.startswith(('Auth/', 'Admin/', 'Pages/', 'Ui/')):
        return PRESENTATION
    return COMPOSITION

def layer_for_target(target: str):
    cls = target.split('::', 1)[0].lstrip('\\')
    if cls.startswith('Prontoo\\Core\\'):
        return CORE
    if cls.startswith('Prontoo\\Domain\\'):
        return DOMAIN
    if cls.startswith('Prontoo\\Application\\'):
        return APPLICATION
    if cls.startswith('Prontoo\\Infrastructure\\'):
        return INFRASTRUCTURE
    if cls.startswith('Prontoo\\Presentation\\'):
        return PRESENTATION
    if cls.startswith(('Prontoo\\Runtime\\', 'Prontoo\\Install\\')):
        return COMPOSITION
    return None

def needs_gateway(relative: str, name: str):
    from_layer = layer_for_path(relative)
    to_layer = layer_for_target(mapping[name])
    return to_layer is not None and to_layer not in allowed.get(from_layer, set())

def gateway_class():
    return r'\Prontoo\Core\Architecture\OperationGateway'

def callable_expr(relative: str, name: str):
    if needs_gateway(relative, name):
        return f"{gateway_class()}::has('{name}')"
    cls, method = mapping[name].split('::', 1)
    return f"is_callable([{cls}::class, '{method}'])"

def replace_calls(source: str, relative: str):
    source = probe_pattern.sub(lambda m: callable_expr(relative, m.group(2)), source)
    source = is_callable_pattern.sub(lambda m: callable_expr(relative, m.group(2)), source)
    def callback_repl(match):
        name = match.group(2)
        if needs_gateway(relative, name):
            return f"call_user_func({gateway_class()}::handler('{name}'),"
        cls, method = mapping[name].split('::', 1)
        return f"call_user_func([{cls}::class, '{method}'],"
    source = callback_pattern.sub(callback_repl, source)
    def direct_repl(match):
        name = match.group(1)
        prefix = match.string[max(0, match.start() - 24):match.start()]
        if re.search(r'function\s*$', prefix):
            return match.group(0)
        if needs_gateway(relative, name):
            return f"{gateway_class()}::invoke('{name}', "
        return mapping[name] + '('
    return direct_pattern.sub(direct_repl, source)

# Native indirection seam: lower layers depend only on Core; composition owns concrete handlers.
gateway = root / 'app/Core/Architecture/OperationGateway.php'
gateway.write_text("""<?php
declare(strict_types=1);
namespace Prontoo\\Core\\Architecture;

final class OperationGateway
{
    private static array $handlers = [];
    private function __construct() {}
    public static function register(array $handlers): void
    {
        self::$handlers = $handlers;
    }
    public static function has(string $name): bool
    {
        return isset(self::$handlers[$name]) && is_callable(self::$handlers[$name]);
    }
    public static function handler(string $name): callable
    {
        $handler = self::$handlers[$name] ?? null;
        if (!is_callable($handler)) {
            throw new \\RuntimeException('Operação nativa não registrada: ' . $name);
        }
        return $handler;
    }
    public static function invoke(string $name, mixed ...$arguments): mixed
    {
        return (self::handler($name))(...$arguments);
    }
}
""")
registry_entries = []
for name, target in sorted(mapping.items()):
    cls, method = target.split('::', 1)
    registry_entries.append(f"            '{name}' => [{cls}::class, '{method}'],")
registry = root / 'app/Runtime/Architecture/OperationRegistry.php'
registry.parent.mkdir(parents=True, exist_ok=True)
registry.write_text("""<?php
declare(strict_types=1);
namespace Prontoo\\Runtime\\Architecture;

use Prontoo\\Core\\Architecture\\OperationGateway;

final class OperationRegistry
{
    private function __construct() {}
    public static function register(): void
    {
        OperationGateway::register([
%s
        ]);
    }
}
""" % '\n'.join(registry_entries))

# Register the composition map immediately after the autoloader in the canonical runtime bootstrap.
prontoo = root / 'app/prontoo.php'
prontoo_source = prontoo.read_text()
autoload = 'require_once __DIR__ . "/Runtime/Autoload/ProntooAutoloader.php";'
registration = '\\Prontoo\\Runtime\\Architecture\\OperationRegistry::register();'
if autoload not in prontoo_source:
    raise SystemExit('canonical autoloader shape changed')
if registration not in prontoo_source:
    prontoo_source = prontoo_source.replace(autoload, autoload + '\n' + registration, 1)
prontoo.write_text(prontoo_source)

# Entry points outside scanned directories.
br_file = root / 'br/index.php'
br = br_file.read_text()
br_legacy = 'require_once dirname(__DIR__) . "/app/Support/Telemetry.php";'
br_native = 'require_once dirname(__DIR__) . "/app/Runtime/Autoload/ProntooAutoloader.php";'
if br_legacy not in br:
    raise SystemExit('landing telemetry facade require shape changed')
br = br.replace(br_legacy, br_native, 1)
first = br.find(br_native); second = br.find(br_native, first + len(br_native))
if second >= 0:
    br = br[:second] + br[second + len(br_native):]
br_file.write_text(replace_calls(br, 'br/index.php'))
root_legacy = 'require_once __DIR__ . "/app/Support/Telemetry.php";'
root_native = 'require_once __DIR__ . "/app/Runtime/Autoload/ProntooAutoloader.php";'
installer_load = '\\Prontoo\\Runtime\\Modules\\RuntimeModuleComposition::loader()->requireModule("Install/Installer.php");\n'
for rel in ['index.php', 'install.php']:
    path = root / rel
    source = path.read_text()
    if root_legacy not in source:
        raise SystemExit(f'{rel}: telemetry facade require shape changed')
    source = source.replace(root_legacy, root_native, 1).replace(installer_load, '')
    path.write_text(replace_calls(source, rel))

# Runtime loader must point at its native guard.
loader = root / 'app/Runtime/Modules/RuntimeModuleLoader.php'
loader_source = loader.read_text()
old_guard = "$this->requireModule('Domain/Financial/Financial.php');"
new_guard = "$this->requireModule('Runtime/FinancialGuard/FinancialGuardRuntimeOperations01.php');"
if old_guard not in loader_source:
    raise SystemExit('financial guard loader shape changed')
loader.write_text(loader_source.replace(old_guard, new_guard, 1))

excluded = set(facades + ['tools/zero-legacy-final-generate.py', 'tools/zero-legacy-final-v2.py'])
for base in ['app', 'br', 'public', 'cron', 'tools']:
    directory = root / base
    if not directory.exists():
        continue
    for path in directory.rglob('*.php'):
        rel = path.relative_to(root).as_posix()
        if rel in excluded:
            continue
        source = path.read_text()
        changed = replace_calls(source, rel)
        if changed != source:
            path.write_text(changed)

# Remove facade load declarations from composition/runtime contracts.
for host in ['app/bootstrap_architecture.php', 'app/Runtime/Modules/RuntimeModuleCatalog.php', 'app/Core/Install/RuntimeContract.php']:
    path = root / host
    if not path.exists():
        continue
    lines = path.read_text().splitlines(True)
    path.write_text(''.join(line for line in lines if not any((f in line or f.removeprefix('app/') in line) for f in facades)))

# Remove globals from required core-function contract.
rc = root / 'app/Core/Install/RuntimeContract.php'
src = rc.read_text()
marker = 'public static function requiredCoreFunctions(): array'
start = src.find(marker)
if start >= 0:
    brace = src.find('{', start); depth = 0; end = None
    for index in range(brace, len(src)):
        if src[index] == '{': depth += 1
        elif src[index] == '}':
            depth -= 1
            if depth == 0:
                end = index + 1; break
    block = src[brace + 1:end - 1]
    name_line = re.compile(r"['\"](?:" + names_alt + r")[ '\"]?\s*[,)]")
    block = ''.join(line for line in block.splitlines(True) if not name_line.search(line))
    rc.write_text(src[:brace + 1] + block + src[end - 1:])

# Native page dispatch replaces dynamic global page fallback.
handlers = {
    'home': r'\Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations01::page_home',
    'painel': r'\Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations03::page_painel',
    'mobile_web_access': r'\Prontoo\Runtime\PublicWeb\PublicWebRuntimeOperations01::page_mobile_web_access',
    'leads': r'\Prontoo\Runtime\Leads\LeadsRuntimeOperations02::page_leads',
    'lead_lookup': r'\Prontoo\Runtime\Leads\LeadsRuntimeOperations01::page_lead_lookup',
    'lead_patient_lookup': r'\Prontoo\Runtime\Leads\LeadsRuntimeOperations01::page_lead_patient_lookup',
}
for name, target in mapping.items():
    if name.startswith('page_'):
        route = name[5:]
        if route in handlers and handlers[route] != target:
            raise SystemExit(f'page handler collision: {route}')
        handlers[route] = target
entries = []
for route, target in sorted(handlers.items()):
    cls, method = target.split('::', 1)
    entries.append(f"        '{route}' => [{cls}::class, '{method}'],")
(root / 'app/Runtime/Routing/PageDispatcher.php').write_text("""<?php
declare(strict_types=1);
namespace Prontoo\\Runtime\\Routing;
final class PageDispatcher
{
    private const HANDLERS = [
%s
    ];
    private function __construct() {}
    public static function nativeRoutes(): array { return array_keys(self::HANDLERS); }
    public static function hasNativeHandler(string $route): bool { return isset(self::HANDLERS[$route]); }
    public static function dispatch(string $route): bool
    {
        $handler = self::HANDLERS[$route] ?? null;
        if (!is_array($handler) || count($handler) !== 2) return false;
        [$class, $method] = $handler;
        $class::$method();
        return true;
    }
}
""" % '\n'.join(entries))
runner = root / 'app/Runtime/Runner.php'
runner_source = runner.read_text()
old_runner = """            if (!PageDispatcher::dispatch($effectiveRoute)) {
                $page = 'page_' . $effectiveRoute;
                if (!function_exists($page)) {
                    throw new \\RuntimeException('Rota sem função de página: ' . $page);
                }
                $page();
            }
"""
new_runner = """            if (!PageDispatcher::dispatch($effectiveRoute)) {
                throw new \\RuntimeException('Rota sem handler nativo: ' . $effectiveRoute);
            }
"""
if old_runner not in runner_source:
    raise SystemExit('Runner global page fallback shape changed')
runner.write_text(runner_source.replace(old_runner, new_runner, 1))

# Historical source identifiers become explicit migration metadata.
version_path = root / 'version.json'
version = json.loads(version_path.read_text())
version['architecture_transitional_files_max'] = 21
version['architecture_source_path_migrations'] = {path: (targets[0] if len(targets) == 1 else targets) for path, targets in facade_targets.items()}
baseline = json.loads((root / 'docs/audits/php84-conformance-1.8.6.1.json').read_text())
baseline_paths = {str(item.get('path', '')) for item in baseline.get('files', []) if isinstance(item, dict)}
php84 = version.setdefault('php84_baseline_path_migrations', {})
for path, targets in facade_targets.items():
    if path in baseline_paths:
        php84[path] = targets[0] if len(targets) == 1 else targets
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

# Resolver follows explicit source migrations after a facade is physically removed.
resolver = root / 'app/Core/Architecture/CompatibilitySourceResolver.php'
resolver_source = resolver.read_text()
old_missing = """        $file = $root . '/' . $relative;
        if (!is_file($file)) {
            return array_keys($paths);
        }
        $source = (string) file_get_contents($file);
"""
new_missing = """        $file = $root . '/' . $relative;
        if (!is_file($file)) {
            $versionFile = $root . '/version.json';
            $version = is_file($versionFile) ? json_decode((string) file_get_contents($versionFile), true) : null;
            $key = str_starts_with($relative, 'app/') ? $relative : 'app/' . $relative;
            $targets = is_array($version) ? ($version['architecture_source_path_migrations'][$key] ?? []) : [];
            $targets = is_array($targets) ? $targets : [$targets];
            foreach ($targets as $target) {
                $target = mb_ltrim((string) $target, '/');
                if ($target !== '' && is_file($root . '/' . $target)) $paths[$target] = true;
            }
            return array_keys($paths);
        }
        $source = (string) file_get_contents($file);
"""
if old_missing not in resolver_source:
    raise SystemExit('source resolver shape changed')
resolver.write_text(resolver_source.replace(old_missing, new_missing, 1))

# Architecture verifier treats a migrated historical source as present and scans resolved content.
verifier = root / 'app/Core/Architecture/ArchitectureVerifier.php'
vs = verifier.read_text()
vs = vs.replace("if (!is_file($root . '/' . $source)) {", "if (CompatibilitySourceResolver::content($root, $source) === '') {")
vs = vs.replace("$contract->action !== ActionCatalog::DEFAULT_ACTION && is_file($root . '/' . $source)", "$contract->action !== ActionCatalog::DEFAULT_ACTION && CompatibilitySourceResolver::content($root, $source) !== ''")
vs = vs.replace("if (!is_file($root . '/' . $producer)) {", "if (CompatibilitySourceResolver::content($root, $producer) === '') {")
old_discovery = """            $path = $root . '/' . $source;
            if (!is_file($path)) {
                continue;
            }
            foreach (self::discoverActionTokens(CompatibilitySourceResolver::content($root, $source)) as $token) {
"""
new_discovery = """            $content = CompatibilitySourceResolver::content($root, $source);
            if ($content === '') {
                continue;
            }
            foreach (self::discoverActionTokens($content) as $token) {
"""
if old_discovery not in vs:
    raise SystemExit('architecture source discovery shape changed')
verifier.write_text(vs.replace(old_discovery, new_discovery, 1))

# Architecture contract expects the native self-check call after globals disappear.
arch_check = root / 'tools/architecture-check.php'
ac = arch_check.read_text().replace("'\\\\runtime_self_check();',", "'::runtime_self_check();',")
arch_check.write_text(ac)

for facade in facades:
    (root / facade).unlink()

manifest_path = root / 'app/architecture.manifest.json'
manifest = json.loads(manifest_path.read_text())
manifest['transitional_files_max'] = 21
manifest['compatibility_boundaries'] = []
removed = list(manifest.get('removed_legacy_files', []))
for facade in facades:
    if facade not in removed:
        removed.append(facade)
manifest['removed_legacy_files'] = removed
manifest['native_migration_policy'] = 'zero_legacy_facades_entrypoints_views_cron_and_contract_tools_only_ceiling_21'
manifest['solid_runtime_composition_policy'] = 'native_runtime_composition_with_core_operation_gateway_without_global_compatibility_facades'
manifest['solid_runtime_runner_policy'] = 'native_route_catalog_json_boot_dispatch_and_feature_composition_without_global_api_facades'
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

for doc in ['README.md','docs/architecture/overview.md','docs/architecture/layers.md','docs/architecture/responsibility-map.md','docs/architecture/dependencies.md','CONTRIBUTING.md']:
    path = root / doc
    if path.exists():
        text = path.read_text().replace('fronteiras transitórias/compatíveis', 'entrypoints e ferramentas procedurais não classificados como unidades nativas').replace('compatibilidade remanescente permanece explicitamente classificada e limitada', 'não há fachadas globais de compatibilidade; apenas entrypoints e ferramentas procedurais permanecem fora da contagem de unidades nativas')
        path.write_text(text)

for temporary in [
    '.github/workflows/zero-legacy-migration.yml',
    '.github/workflows/zero-legacy-final-readonly.yml',
    '.github/workflows/zero-legacy-final-push.yml',
    'tools/zero-legacy-root-entrypoints-fix.py',
    'tools/sitecustomize.py',
]:
    path = root / temporary
    if path.exists():
        path.unlink()

# Executable zero-legacy assertion includes the root entrypoints.
residual = []
for base in ['app','br','public','cron','tools']:
    directory = root / base
    if not directory.exists():
        continue
    for path in directory.rglob('*.php'):
        rel = path.relative_to(root).as_posix()
        text = path.read_text()
        for match in direct_pattern.finditer(text):
            prefix = text[max(0, match.start() - 24):match.start()]
            if not re.search(r'function\s*$', prefix):
                residual.append(f'{rel}:call:{match.group(1)}')
        for match in probe_pattern.finditer(text):
            residual.append(f'{rel}:probe:{match.group(2)}')
for rel in ['index.php','install.php']:
    text = (root / rel).read_text()
    for match in direct_pattern.finditer(text): residual.append(f'{rel}:call:{match.group(1)}')
    for match in probe_pattern.finditer(text): residual.append(f'{rel}:probe:{match.group(2)}')
    if 'Support/Telemetry.php' in text: residual.append(f'{rel}:loads-telemetry-facade')
    if 'Install/Installer.php' in text: residual.append(f'{rel}:loads-installer-facade')
for facade in facades:
    if (root / facade).exists(): residual.append(f'file-present:{facade}')
if 'Support/Telemetry.php' in (root / 'br/index.php').read_text(): residual.append('landing-loads-telemetry-facade')
if 'Domain/Financial/Financial.php' in (root / 'app/Runtime/Modules/RuntimeModuleLoader.php').read_text(): residual.append('loader-loads-financial-facade')
if residual:
    print(json.dumps({'residual_count': len(residual), 'residual': residual[:300]}, indent=2))
    raise SystemExit('executable legacy residuals remain')

print(json.dumps({
    'removed_facades': len(facades),
    'removed_global_functions': len(mapping),
    'native_page_handlers': len(handlers),
    'transitional_ceiling': 21,
    'source_migrations': len(facade_targets),
    'root_entrypoints_migrated': 2,
    'operation_gateway_handlers': len(mapping),
}, indent=2))

# The v2 generator is a migration tool, not part of the final product.
self_path = Path(__file__).resolve()
if self_path.exists():
    self_path.unlink()
