from pathlib import Path
import re, json

root = Path(__file__).resolve().parents[1]
facades = [
'app/Admin/AdminPages.php','app/Auth/AuthOnboarding.php','app/Database/DatabaseSchema.php',
'app/Domain/Appointments/Appointments.php','app/Domain/Audit/AuditActivity.php','app/Domain/Clinic/ClinicConfig.php',
'app/Domain/Clinic/SubscriptionSettings.php','app/Domain/Documents/DocumentPdf.php','app/Domain/Documents/Documents.php',
'app/Domain/Financial/Financial.php','app/Domain/Maestro/Maestro.php','app/Domain/Patients/Patients.php',
'app/Domain/Permissions/UsersPermissions.php','app/Domain/Tasks/TasksNotices.php','app/Install/Installer.php',
'app/Support/DeferredAudit.php','app/Support/Foundation.php','app/Support/Runtime.php','app/Support/SecurityAccess.php',
'app/Support/ServerJsonCache.php','app/Support/Telemetry.php','app/Ui/Components.php']

def blocks(source):
    out=[]
    for m in re.finditer(r'(?m)^function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(', source):
        name=m.group(1); brace=source.find('{',m.end())
        if brace<0: continue
        depth=0; end=None
        for i in range(brace,len(source)):
            if source[i]=='{': depth+=1
            elif source[i]=='}':
                depth-=1
                if depth==0: end=i+1; break
        if end is None: continue
        body=source[brace+1:end-1]
        tm=re.search(r'(\\Prontoo\\[A-Za-z0-9_\\]+::[A-Za-z_][A-Za-z0-9_]*)\s*\(',body)
        if not tm: raise SystemExit(f'unmapped facade function: {name}')
        out.append((name,tm.group(1)))
    return out

mapping={}; facade_targets={}
for path in facades:
    file=root/path
    if not file.exists(): raise SystemExit(f'facade missing before final migration: {path}')
    targets=[]
    for name,target in blocks(file.read_text()):
        if name in mapping and mapping[name]!=target: raise SystemExit(f'duplicate global with divergent target: {name}')
        mapping[name]=target
        cls=target.split('::',1)[0].lstrip('\\')
        if cls.startswith('Prontoo\\'):
            candidate='app/'+cls[len('Prontoo\\'):].replace('\\','/')+'.php'
            if (root/candidate).is_file(): targets.append(candidate)
    facade_targets[path]=sorted(set(targets))

names_alt='|'.join(re.escape(name) for name in sorted(mapping,key=len,reverse=True))
probe_pattern=re.compile(r"function_exists\(\s*(['\"])("+names_alt+r")\1\s*\)")
is_callable_pattern=re.compile(r"is_callable\(\s*(['\"])("+names_alt+r")\1\s*\)")
callback_pattern=re.compile(r"call_user_func\(\s*(['\"])("+names_alt+r")\1\s*,")
direct_pattern=re.compile(r"(?<![A-Za-z0-9_\\:>$])\\?("+names_alt+r")\s*\(")

def callable_expr(name):
    cls,method=mapping[name].split('::',1)
    return f"is_callable([{cls}::class, '{method}'])"

def replace_calls(source):
    source=probe_pattern.sub(lambda m: callable_expr(m.group(2)),source)
    source=is_callable_pattern.sub(lambda m: callable_expr(m.group(2)),source)
    def callback_repl(m):
        cls,method=mapping[m.group(2)].split('::',1)
        return f"call_user_func([{cls}::class, '{method}'],"
    source=callback_pattern.sub(callback_repl,source)
    def direct_repl(m):
        prefix=m.string[max(0,m.start()-24):m.start()]
        if re.search(r'function\s*$',prefix): return m.group(0)
        return mapping[m.group(1)]+'('
    return direct_pattern.sub(direct_repl,source)

br_file=root/'br/index.php'; br=br_file.read_text()
support_require='require_once dirname(__DIR__) . "/app/Support/Telemetry.php";'
autoload_require='require_once dirname(__DIR__) . "/app/Runtime/Autoload/ProntooAutoloader.php";'
if support_require not in br: raise SystemExit('landing telemetry facade require shape changed')
br=br.replace(support_require,autoload_require,1)
first=br.find(autoload_require); second=br.find(autoload_require,first+len(autoload_require))
if second>=0: br=br[:second]+br[second+len(autoload_require):]
br_file.write_text(br)

loader=root/'app/Runtime/Modules/RuntimeModuleLoader.php'; loader_source=loader.read_text()
old_guard="$this->requireModule('Domain/Financial/Financial.php');"
new_guard="$this->requireModule('Runtime/FinancialGuard/FinancialGuardRuntimeOperations01.php');"
if old_guard not in loader_source: raise SystemExit('financial guard loader shape changed')
loader.write_text(loader_source.replace(old_guard,new_guard,1))

excluded=set(facades+['tools/zero-legacy-final-generate.py'])
for base in ['app','br','public','cron','tools']:
    b=root/base
    if not b.exists(): continue
    for file in b.rglob('*.php'):
        rel=file.relative_to(root).as_posix()
        if rel in excluded: continue
        source=file.read_text(); changed=replace_calls(source)
        if changed!=source: file.write_text(changed)

path_hosts=['app/bootstrap_architecture.php','app/Runtime/Modules/RuntimeModuleCatalog.php','app/Core/Install/RuntimeContract.php']
for host in path_hosts:
    file=root/host
    if not file.exists(): continue
    lines=file.read_text().splitlines(True)
    file.write_text(''.join(line for line in lines if not any((p in line or p.removeprefix('app/') in line) for p in facades)))

rc=root/'app/Core/Install/RuntimeContract.php'; src=rc.read_text()
marker='public static function requiredCoreFunctions(): array'; start=src.find(marker)
if start>=0:
    brace=src.find('{',start); depth=0; end=None
    for i in range(brace,len(src)):
        if src[i]=='{': depth+=1
        elif src[i]=='}':
            depth-=1
            if depth==0: end=i+1; break
    block=src[brace+1:end-1]
    name_line=re.compile(r"['\"](?:"+names_alt+r")[ '\"]?\s*[,)]")
    block=''.join(line for line in block.splitlines(True) if not name_line.search(line))
    src=src[:brace+1]+block+src[end-1:]; rc.write_text(src)

handlers={
'home': r'\Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations01::page_home',
'painel': r'\Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations03::page_painel',
'mobile_web_access': r'\Prontoo\Runtime\PublicWeb\PublicWebRuntimeOperations01::page_mobile_web_access',
'leads': r'\Prontoo\Runtime\Leads\LeadsRuntimeOperations02::page_leads',
'lead_lookup': r'\Prontoo\Runtime\Leads\LeadsRuntimeOperations01::page_lead_lookup',
'lead_patient_lookup': r'\Prontoo\Runtime\Leads\LeadsRuntimeOperations01::page_lead_patient_lookup',
}
for name,target in mapping.items():
    if name.startswith('page_'):
        route=name[5:]
        if route in handlers and handlers[route]!=target: raise SystemExit(f'page handler collision: {route}')
        handlers[route]=target
entries=[]
for route,target in sorted(handlers.items()):
    cls,method=target.split('::',1); entries.append(f"        '{route}' => [{cls}::class, '{method}'],")
(root/'app/Runtime/Routing/PageDispatcher.php').write_text("""<?php
declare(strict_types=1);

namespace Prontoo\\Runtime\\Routing;

final class PageDispatcher
{
    private const HANDLERS = [
%s
    ];

    private function __construct()
    {
    }

    public static function nativeRoutes(): array
    {
        return array_keys(self::HANDLERS);
    }

    public static function hasNativeHandler(string $route): bool
    {
        return isset(self::HANDLERS[$route]);
    }

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

runner=root/'app/Runtime/Runner.php'; r=runner.read_text()
old="""            if (!PageDispatcher::dispatch($effectiveRoute)) {
                $page = 'page_' . $effectiveRoute;
                if (!function_exists($page)) {
                    throw new \\RuntimeException('Rota sem função de página: ' . $page);
                }
                $page();
            }
"""
new="""            if (!PageDispatcher::dispatch($effectiveRoute)) {
                throw new \\RuntimeException('Rota sem handler nativo: ' . $effectiveRoute);
            }
"""
if old not in r: raise SystemExit('Runner global page fallback shape changed')
runner.write_text(r.replace(old,new,1))

version_path=root/'version.json'; version=json.loads(version_path.read_text())
version['architecture_transitional_files_max']=21
version['architecture_source_path_migrations']={path:(targets[0] if len(targets)==1 else targets) for path,targets in facade_targets.items()}
baseline=json.loads((root/'docs/audits/php84-conformance-1.8.6.1.json').read_text())
baseline_paths={str(x.get('path','')) for x in baseline.get('files',[]) if isinstance(x,dict)}
php84=version.setdefault('php84_baseline_path_migrations',{})
for path,targets in facade_targets.items():
    if path in baseline_paths: php84[path]=targets[0] if len(targets)==1 else targets
version_path.write_text(json.dumps(version,ensure_ascii=False,indent=4,separators=(',',': '))+'\n')

resolver=root/'app/Core/Architecture/CompatibilitySourceResolver.php'; rs=resolver.read_text()
old_missing="""        $file = $root . '/' . $relative;
        if (!is_file($file)) {
            return array_keys($paths);
        }
        $source = (string) file_get_contents($file);
"""
new_missing="""        $file = $root . '/' . $relative;
        if (!is_file($file)) {
            $versionFile = $root . '/version.json';
            $version = is_file($versionFile)
                ? json_decode((string) file_get_contents($versionFile), true)
                : null;
            $key = str_starts_with($relative, 'app/') ? $relative : 'app/' . $relative;
            $targets = is_array($version)
                ? ($version['architecture_source_path_migrations'][$key] ?? [])
                : [];
            $targets = is_array($targets) ? $targets : [$targets];
            foreach ($targets as $target) {
                $target = mb_ltrim((string) $target, '/');
                if ($target !== '' && is_file($root . '/' . $target)) {
                    $paths[$target] = true;
                }
            }
            return array_keys($paths);
        }
        $source = (string) file_get_contents($file);
"""
if old_missing not in rs: raise SystemExit('source resolver shape changed')
resolver.write_text(rs.replace(old_missing,new_missing,1))

for path in facades: (root/path).unlink()

manifest_path=root/'app/architecture.manifest.json'; manifest=json.loads(manifest_path.read_text())
manifest['transitional_files_max']=21; manifest['compatibility_boundaries']=[]
removed=list(manifest.get('removed_legacy_files',[]))
for path in facades:
    if path not in removed: removed.append(path)
manifest['removed_legacy_files']=removed
manifest['native_migration_policy']='zero_legacy_facades_entrypoints_views_cron_and_contract_tools_only_ceiling_21'
manifest['solid_runtime_composition_policy']='native_runtime_composition_without_global_compatibility_facades'
manifest['solid_runtime_runner_policy']='native_route_catalog_json_boot_dispatch_and_feature_composition_without_global_api_facades'
manifest_path.write_text(json.dumps(manifest,ensure_ascii=False,indent=4,separators=(',',': '))+'\n')

for path in ['README.md','docs/architecture/overview.md','docs/architecture/layers.md','docs/architecture/responsibility-map.md','docs/architecture/dependencies.md','CONTRIBUTING.md']:
    file=root/path
    if file.exists():
        text=file.read_text().replace('fronteiras transitórias/compatíveis','entrypoints e ferramentas procedurais não classificados como unidades nativas').replace('compatibilidade remanescente permanece explicitamente classificada e limitada','não há fachadas globais de compatibilidade; apenas entrypoints e ferramentas procedurais permanecem fora da contagem de unidades nativas')
        file.write_text(text)

harness=root/'.github/workflows/zero-legacy-migration.yml'
if harness.exists(): harness.unlink()

residual=[]
for base in ['app','br','public','cron','tools']:
    b=root/base
    if not b.exists(): continue
    for file in b.rglob('*.php'):
        rel=file.relative_to(root).as_posix()
        if rel=='tools/zero-legacy-final-generate.py': continue
        text=file.read_text()
        for m in direct_pattern.finditer(text):
            prefix=text[max(0,m.start()-24):m.start()]
            if not re.search(r'function\s*$',prefix): residual.append(f'{rel}:call:{m.group(1)}')
        for m in probe_pattern.finditer(text): residual.append(f'{rel}:probe:{m.group(2)}')
for path in facades:
    if (root/path).exists(): residual.append(f'file-present:{path}')
if 'Support/Telemetry.php' in (root/'br/index.php').read_text(): residual.append('landing-loads-telemetry-facade')
if 'Domain/Financial/Financial.php' in (root/'app/Runtime/Modules/RuntimeModuleLoader.php').read_text(): residual.append('loader-loads-financial-facade')
if residual:
    print(json.dumps({'residual_count':len(residual),'residual':residual[:300]},indent=2)); raise SystemExit('executable legacy residuals remain')

print(json.dumps({'removed_facades':len(facades),'removed_global_functions':len(mapping),'native_page_handlers':len(handlers),'transitional_ceiling':21,'source_migrations':len(facade_targets)},indent=2))

