from pathlib import Path
import json,re
ROOT = Path(__file__).resolve().parents[1]

def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
def replace_once(p,old,new):
 s=read(p)
 if old not in s: raise SystemExit(f'missing {p}: {old[:120]}')
 write(p,s.replace(old,new,1))

replace_once('app/prontoo.php',
             'require_once __DIR__ . "/Support/SecurityPrivacy.php";',
             'require_once __DIR__ . "/Runtime/Autoload/ProntooAutoloader.php";')
replace_once('app/prontoo.php','security_https_active();','\\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active();')
replace_once('br/index.php',
             'require_once dirname(__DIR__) . "/app/Support/SecurityPrivacy.php";',
             'require_once dirname(__DIR__) . "/app/Runtime/Autoload/ProntooAutoloader.php";')
replace_once('br/index.php','security_https_active();','\\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active();')

replace_once('app/Runtime/Modules/RuntimeModuleCatalog.php',"            'Support/SecurityPrivacy.php',\n",'')

infra='\\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01'
runtime='\\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01'
mapping={
 'security_ip_in_cidr': infra+'::security_ip_in_cidr',
 'security_trusted_proxy_request': runtime+'::security_trusted_proxy_request',
 'security_https_active': runtime+'::security_https_active',
 'security_disable_runtime_error_display': infra+'::security_disable_runtime_error_display',
 'privacy_sanitize_text': infra+'::privacy_sanitize_text',
 'privacy_sanitize_error_message': infra+'::privacy_sanitize_error_message',
 'privacy_log_file_label': infra+'::privacy_log_file_label',
 'security_storage_deny_file': infra+'::security_storage_deny_file',
}
for p in ROOT.rglob('*.php'):
 rel=p.relative_to(ROOT).as_posix()
 if rel in ('app/Support/SecurityPrivacy.php','app/prontoo.php','br/index.php','tools/security-regression-check.php'):
  continue
 s=p.read_text()
 before=s
 for name,target in mapping.items():
  s=re.sub(rf'(?<!function )(?<!::)\b{re.escape(name)}\s*\(',lambda _m, t=target: t+'(',s)
 s=s.replace('function_exists("security_https_active") && \\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active()',
             '\\Prontoo\\Runtime\\SecurityPrivacy\\SecurityPrivacyRuntimeOperations01::security_https_active()')
 if s!=before: p.write_text(s)

vp=ROOT/'version.json'; v=json.loads(vp.read_text())
if int(v.get('architecture_native_files_min',0))!=278 or int(v.get('architecture_transitional_files_max',0))!=44:
 raise SystemExit('unexpected baselines phase7')
v['architecture_transitional_files_max']=43
m=v.setdefault('php84_baseline_path_migrations',{})
m['app/Support/SecurityPrivacy.php']=[
 'app/Infrastructure/SecurityPrivacy/SecurityPrivacyInfrastructureOperations01.php',
 'app/Runtime/SecurityPrivacy/SecurityPrivacyRuntimeOperations01.php',
]
vp.write_text(json.dumps(v,ensure_ascii=False,indent=4,separators=(',',': '))+'\n')
mp=ROOT/'app/architecture.manifest.json'; mf=json.loads(mp.read_text()); mf['transitional_files_max']=43
removed=list(mf.get('removed_legacy_files',[])); target='app/Support/SecurityPrivacy.php'
if target not in removed: removed.append(target)
mf['removed_legacy_files']=removed
mp.write_text(json.dumps(mf,ensure_ascii=False,indent=4,separators=(',',': '))+'\n')
file=ROOT/target
if not file.exists(): raise SystemExit('SecurityPrivacy facade absent before phase7')
file.unlink()

for p in ROOT.rglob('*'):
 if not p.is_file() or '.git' in p.parts: continue
 rel=p.relative_to(ROOT).as_posix()
 if rel.startswith('docs/audits/') or rel in ('version.json','app/update.manifest.json','app/architecture.manifest.json','tools/zero-legacy-phase2-generate.py'): continue
 try: text=p.read_text()
 except UnicodeDecodeError: continue
 if 'Support/SecurityPrivacy.php' in text:
  raise SystemExit(f'legacy SecurityPrivacy path remains: {rel}')
for p in ROOT.rglob('*.php'):
 rel=p.relative_to(ROOT).as_posix(); text=p.read_text()
 if rel=='tools/security-regression-check.php': continue
 for name in mapping:
  if re.search(rf'(?<!function )(?<!::)\b{re.escape(name)}\s*\(',text):
   raise SystemExit(f'unqualified SecurityPrivacy call remains: {rel}: {name}')
print('phase7 security privacy facade transform ok')
