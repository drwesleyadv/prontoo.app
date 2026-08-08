from pathlib import Path
import json,re
ROOT = Path(__file__).resolve().parents[1]

def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
def replace_once(p, old, new):
    s=read(p)
    if old not in s: raise SystemExit(f'missing {p}: {old[:100]!r}')
    write(p,s.replace(old,new,1))

p='app/Domain/Leads/LeadsDomainOperations01.php'; s=read(p)
s=s.replace('array_key_exists($candidate, lead_stage_options())','array_key_exists($candidate, self::lead_stage_options())')
s=s.replace('lead_active_stages(),','self::lead_active_stages(),')
s=s.replace('lead_stage_sql_case($column) .','self::lead_stage_sql_case($column) .')
write(p,s)

def replace_calls(path, mapping):
    s=read(path)
    for name,target in mapping.items():
        pat=rf'(?<!function )\b{re.escape(name)}\('
        s,n=re.subn(pat,target+'(',s)
    write(path,s)

p='app/Runtime/Leads/LeadsRuntimeOperations01.php'; s=read(p)
s=s.replace('use \\Throwable;\n','use \\Throwable;\nuse Prontoo\\Domain\\Leads\\LeadsDomainOperations01;\n',1)
write(p,s)
replace_calls(p, {
    'lead_phone_digits':'self::lead_phone_digits',
    'lead_cpf_br':'self::lead_cpf_br',
    'lead_find_by_phone':'self::lead_find_by_phone',
    'lead_event_create':'self::lead_event_create',
    'lead_prepare_person_for_patient':'self::lead_prepare_person_for_patient',
    'lead_patient_by_cpf':'self::lead_patient_by_cpf',
    'lead_patient_by_phone':'self::lead_patient_by_phone',
    'lead_history_html':'self::lead_history_html',
    'lead_stage_options':'LeadsDomainOperations01::lead_stage_options',
    'lead_stage_icons':'LeadsDomainOperations01::lead_stage_icons',
    'lead_stage_normalize':'LeadsDomainOperations01::lead_stage_normalize',
    'lead_active_stages':'LeadsDomainOperations01::lead_active_stages',
    'lead_active_stage_sql':'LeadsDomainOperations01::lead_active_stage_sql',
    'lead_stage_sql_case':'LeadsDomainOperations01::lead_stage_sql_case',
})

p='app/Runtime/Leads/LeadsRuntimeOperations02.php'; s=read(p)
s=s.replace('use \\Throwable;\n','use \\Throwable;\nuse Prontoo\\Domain\\Leads\\LeadsDomainOperations01;\n',1)
write(p,s)
replace_calls(p, {
    'lead_phone_digits':'LeadsRuntimeOperations01::lead_phone_digits',
    'lead_cpf_br':'LeadsRuntimeOperations01::lead_cpf_br',
    'lead_find_by_phone':'LeadsRuntimeOperations01::lead_find_by_phone',
    'lead_event_create':'LeadsRuntimeOperations01::lead_event_create',
    'lead_prepare_person_for_patient':'LeadsRuntimeOperations01::lead_prepare_person_for_patient',
    'lead_patient_by_cpf':'LeadsRuntimeOperations01::lead_patient_by_cpf',
    'lead_patient_by_phone':'LeadsRuntimeOperations01::lead_patient_by_phone',
    'lead_history_html':'LeadsRuntimeOperations01::lead_history_html',
    'lead_stage_options':'LeadsDomainOperations01::lead_stage_options',
    'lead_stage_icons':'LeadsDomainOperations01::lead_stage_icons',
    'lead_stage_normalize':'LeadsDomainOperations01::lead_stage_normalize',
    'lead_active_stages':'LeadsDomainOperations01::lead_active_stages',
    'lead_active_stage_sql':'LeadsDomainOperations01::lead_active_stage_sql',
    'lead_stage_sql_case':'LeadsDomainOperations01::lead_stage_sql_case',
})

for p in ['app/Runtime/AdminPages/AdminPagesRuntimeOperations02.php','app/Runtime/Dashboards/DashboardsRuntimeOperations01.php','app/Runtime/Dashboards/DashboardsRuntimeOperations03.php']:
    s=read(p)
    if 'use Prontoo\\Domain\\Leads\\LeadsDomainOperations01;' not in s:
        if 'use \\Throwable;\n' in s:
            s=s.replace('use \\Throwable;\n','use \\Throwable;\nuse Prontoo\\Domain\\Leads\\LeadsDomainOperations01;\n',1)
        else:
            idx=s.find('\n\nfinal class')
            if idx<0: raise SystemExit(f'import point missing {p}')
            s=s[:idx]+'\nuse Prontoo\\Domain\\Leads\\LeadsDomainOperations01;'+s[idx:]
    s=s.replace('lead_active_stage_sql(', 'LeadsDomainOperations01::lead_active_stage_sql(')
    write(p,s)

p='app/Runtime/Routing/PageDispatcher.php'; s=read(p)
s=s.replace('use Prontoo\\Runtime\\PublicWeb\\PublicWebRuntimeOperations01;\n',
            'use Prontoo\\Runtime\\PublicWeb\\PublicWebRuntimeOperations01;\nuse Prontoo\\Runtime\\Leads\\LeadsRuntimeOperations01;\nuse Prontoo\\Runtime\\Leads\\LeadsRuntimeOperations02;\n',1)
s=s.replace("        'mobile_web_access',\n", "        'mobile_web_access',\n        'leads',\n        'lead_lookup',\n        'lead_patient_lookup',\n",1)
s=s.replace("            case 'mobile_web_access':\n                PublicWebRuntimeOperations01::page_mobile_web_access();\n                return true;\n",
'''            case 'mobile_web_access':
                PublicWebRuntimeOperations01::page_mobile_web_access();
                return true;
            case 'leads':
                LeadsRuntimeOperations02::page_leads();
                return true;
            case 'lead_lookup':
                LeadsRuntimeOperations01::page_lead_lookup();
                return true;
            case 'lead_patient_lookup':
                LeadsRuntimeOperations01::page_lead_patient_lookup();
                return true;
''',1)
write(p,s)

p='app/Runtime/Modules/RuntimeModuleCatalog.php'; s=read(p)
s=s.replace("            'Domain/Leads/Leads.php',\n",'',1)
s=s.replace("        $leads = ['leads' => ['Domain/Leads/Leads.php']];", "        $leads = [];")
s=s.replace("        $admin = ['admin' => ['Domain/Leads/Leads.php', 'Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];",
            "        $admin = ['admin' => ['Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];")
write(p,s)

replace_once('app/Application/Authorization/Definitions/SchedulingActionDefinitions.php',
             "$leads = 'Domain/Leads/Leads.php';",
             "$leads = 'Runtime/Leads/LeadsRuntimeOperations02.php';")
replace_once('tools/install-security-check.php',
             "compatibility_source($root, 'app/Domain/Leads/Leads.php')",
             "(string) file_get_contents($root . '/app/Runtime/Leads/LeadsRuntimeOperations01.php')")

vp=ROOT/'version.json'; v=json.loads(vp.read_text())
if int(v.get('architecture_native_files_min',0))!=278 or int(v.get('architecture_transitional_files_max',0))!=45:
    raise SystemExit('unexpected baselines phase6')
v['architecture_transitional_files_max']=44
m=v.setdefault('php84_baseline_path_migrations',{})
m['app/Domain/Leads/Leads.php']=[
    'app/Domain/Leads/LeadsDomainOperations01.php',
    'app/Runtime/Leads/LeadsRuntimeOperations01.php',
    'app/Runtime/Leads/LeadsRuntimeOperations02.php',
    'app/Runtime/Routing/PageDispatcher.php',
]
vp.write_text(json.dumps(v,ensure_ascii=False,indent=4,separators=(',',': '))+'\n')
mp=ROOT/'app/architecture.manifest.json'; mfest=json.loads(mp.read_text()); mfest['transitional_files_max']=44
removed=list(mfest.get('removed_legacy_files',[])); target='app/Domain/Leads/Leads.php'
if target not in removed: removed.append(target)
mfest['removed_legacy_files']=removed
mp.write_text(json.dumps(mfest,ensure_ascii=False,indent=4,separators=(',',': '))+'\n')

lead=ROOT/target
if not lead.exists(): raise SystemExit('Leads facade absent before phase6')
lead.unlink()

for p in ROOT.rglob('*'):
    if not p.is_file() or '.git' in p.parts: continue
    rel=p.relative_to(ROOT).as_posix()
    if rel.startswith('docs/audits/') or rel in ('version.json','app/update.manifest.json','app/architecture.manifest.json','tools/zero-legacy-phase2-generate.py'): continue
    try: text=p.read_text()
    except UnicodeDecodeError: continue
    if 'app/Domain/Leads/Leads.php' in text or "Domain/Leads/Leads.php" in text:
        raise SystemExit(f'legacy Leads path remains: {rel}')
legacy_names=['lead_phone_digits','lead_cpf_br','lead_stage_options','lead_stage_icons','lead_stage_normalize','lead_active_stages','lead_active_stage_sql','lead_stage_sql_case','lead_find_by_phone','lead_event_create','lead_prepare_person_for_patient','lead_patient_by_cpf','lead_patient_by_phone','lead_history_html','page_lead_lookup','page_lead_patient_lookup','page_leads']
for p in ROOT.rglob('*.php'):
    rel=p.relative_to(ROOT).as_posix()
    if rel.startswith('docs/audits/'): continue
    text=p.read_text()
    for name in legacy_names:
        if re.search(rf'(?<!function )(?<!::)\b{re.escape(name)}\s*\(', text):
            raise SystemExit(f'unqualified legacy lead call remains: {rel}: {name}')

print('phase6 leads facade transform ok')
