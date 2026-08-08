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

# Replace direct global calls and explicit callable probes.
def replace_calls(source):
    for name,target in sorted(mapping.items(),key=lambda kv:len(kv[0]),reverse=True):
        cls,method=target.split('::',1)
        qname=re.escape(name)
        source=re.sub(r"function_exists\(\s*(['\"])"+qname+r"\1\s*\)", f"is_callable([{cls}::class, '{method}'])", source)
        source=re.sub(r"is_callable\(\s*(['\"])"+qname+r"\1\s*\)", f"is_callable([{cls}::class, '{method}'])", source)
        source=re.sub(r"call_user_func\(\s*(['\"])"+qname+r"\1\s*,", f"call_user_func([{cls}::class, '{method}'],", source)
        source=re.sub(r"(?<![A-Za-z0-9_])\\"+qname+r"\s*\(", target+'(', source)
        pattern=re.compile(r"(?<![A-Za-z0-9_\\:>])"+qname+r"\s*\(")
        pieces=[]; pos=0
        for m in pattern.finditer(source):
            prefix=source[max(0,m.start()-20):m.start()]
            if re.search(r'function\s*$',prefix): continue
            pieces.append(source[pos:m.start()]); pieces.append(target+'('); pos=m.end()
        if pieces:
            pieces.append(source[pos:]); source=''.join(pieces)
    return source

excluded=set(facades+[str(Path('tools/zero-legacy-final-generate.py'))])
for base in ['app','br','public','cron','tools']:
    b=root/base
    if not b.exists(): continue
    for file in b.rglob('*.php'):
        rel=file.relative_to(root).as_posix()
        if rel in excluded: continue
        source=file.read_text()
        changed=replace_calls(source)
        if changed!=source: file.write_text(changed)

# Remove facade paths from executable composition/contracts where they were load requirements.
path_hosts=[
'app/bootstrap_architecture.php','app/Runtime/Modules/RuntimeModuleCatalog.php','app/Core/Install/RuntimeContract.php',
'app/Core/Architecture/ArchitectureVerifier.php','tools/architecture-check.php','tools/solid-audit']
for host in path_hosts:
    file=root/host
    if not file.exists(): continue
    lines=file.read_text().splitlines(True)
    kept=[]
    for line in lines:
        if any((p in line or p.removeprefix('app/') in line) for p in facades):
            continue
        kept.append(line)
    file.write_text(''.join(kept))

# Remove deleted global names from the requiredCoreFunctions contract only.
rc=root/'app/Core/Install/RuntimeContract.php'
src=rc.read_text()
marker='public static function requiredCoreFunctions(): array'
start=src.find(marker)
if start>=0:
    brace=src.find('{',start); depth=0; end=None
    for i in range(brace,len(src)):
        if src[i]=='{': depth+=1
        elif src[i]=='}':
            depth-=1
            if depth==0: end=i+1; break
    block=src[brace+1:end-1]
    block_lines=[]
    for line in block.splitlines(True):
        if any(re.search(r"['\"]"+re.escape(name)+r"['\"]",line) for name in mapping): continue
        block_lines.append(line)
    src=src[:brace+1]+''.join(block_lines)+src[end-1:]
    rc.write_text(src)

# Build a fully native route dispatcher for every page_* wrapper plus handlers already migrated earlier.
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
    cls,method=target.split('::',1)
    entries.append(f"        '{route}' => [{cls}::class, '{method}'],")
page_dispatcher="""<?php
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
        if (!is_array($handler) || count($handler) !== 2) {
            return false;
        }
        [$class, $method] = $handler;
        $class::$method();
        return true;
    }
}
""" % '\n'.join(entries)
(root/'app/Runtime/Routing/PageDispatcher.php').write_text(page_dispatcher)

# No fallback to global page_* functions remains.
runner=root/'app/Runtime/Runner.php'
r=runner.read_text()
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

# Remove facade files.
for path in facades:
    (root/path).unlink()

# Canonical migration contracts.
version_path=root/'version.json'; version=json.loads(version_path.read_text())
version['architecture_transitional_files_max']=21
baseline=json.loads((root/'docs/audits/php84-conformance-1.8.6.1.json').read_text())
baseline_paths={str(x.get('path','')) for x in baseline.get('files',[]) if isinstance(x,dict)}
migrations=version.setdefault('php84_baseline_path_migrations',{})
for path,targets in facade_targets.items():
    if path in baseline_paths:
        migrations[path]=targets[0] if len(targets)==1 else targets
version_path.write_text(json.dumps(version,ensure_ascii=False,indent=4,separators=(',',': '))+'\n')

manifest_path=root/'app/architecture.manifest.json'; manifest=json.loads(manifest_path.read_text())
manifest['transitional_files_max']=21
manifest['compatibility_boundaries']=[]
removed=list(manifest.get('removed_legacy_files',[]))
for path in facades:
    if path not in removed: removed.append(path)
manifest['removed_legacy_files']=removed
manifest['native_migration_policy']='zero_legacy_facades_transitional_entrypoints_views_cron_and_contract_tools_only_ceiling_21'
manifest['solid_runtime_composition_policy']='native_runtime_composition_without_global_compatibility_facades'
manifest['solid_runtime_runner_policy']='native_route_catalog_json_boot_dispatch_and_feature_composition_without_global_api_facades'
manifest_path.write_text(json.dumps(manifest,ensure_ascii=False,indent=4,separators=(',',': '))+'\n')

# Documentation now distinguishes zero legacy from legitimate procedural entrypoints/tools.
for path in ['README.md','docs/architecture/overview.md','docs/architecture/layers.md','docs/architecture/responsibility-map.md','docs/architecture/dependencies.md','CONTRIBUTING.md']:
    file=root/path
    if not file.exists(): continue
    text=file.read_text()
    text=text.replace('fronteiras transitórias/compatíveis','entrypoints e ferramentas procedurais não classificados como unidades nativas')
    text=text.replace('fronteiras transitórias/compatíveis na baseline atual','entrypoints e ferramentas procedurais na baseline atual')
    text=text.replace('compatibilidade remanescente permanece explicitamente classificada e limitada','não há fachadas globais de compatibilidade; apenas entrypoints e ferramentas procedurais permanecem fora da contagem de unidades nativas')
    file.write_text(text)

# Remove the temporary migration harness from the final product.
harness=root/'.github/workflows/zero-legacy-migration.yml'
if harness.exists(): harness.unlink()

# Fail if an executable PHP file still references a deleted facade path or calls/probes a deleted global function.
residual=[]
for base in ['app','br','public','cron','tools']:
    b=root/base
    if not b.exists(): continue
    for file in b.rglob('*.php'):
        rel=file.relative_to(root).as_posix()
        if rel=='tools/zero-legacy-final-generate.py': continue
        text=file.read_text()
        for path in facades:
            if path in text or path.removeprefix('app/') in text:
                residual.append(f'{rel}:path:{path}')
        for name in mapping:
            if re.search(r'(?<![A-Za-z0-9_\\:>])\\?'+re.escape(name)+r'\s*\(',text):
                residual.append(f'{rel}:call:{name}')
            if re.search(r"function_exists\(\s*['\"]"+re.escape(name)+r"['\"]",text):
                residual.append(f'{rel}:probe:{name}')
if residual:
    print(json.dumps({'residual_count':len(residual),'residual':residual[:300]},indent=2))
    raise SystemExit('legacy residuals remain')

print(json.dumps({'removed_facades':len(facades),'removed_global_functions':len(mapping),'native_page_handlers':len(handlers),'transitional_ceiling':21},indent=2))
