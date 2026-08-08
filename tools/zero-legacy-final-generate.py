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

def functions(source):
    out=[]
    for m in re.finditer(r'(?m)^function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(', source):
        name=m.group(1)
        brace=source.find('{',m.end())
        if brace<0: continue
        depth=0; end=None
        i=brace
        while i<len(source):
            ch=source[i]
            if ch=='{': depth+=1
            elif ch=='}':
                depth-=1
                if depth==0:
                    end=i+1; break
            i+=1
        if end is None: continue
        body=source[brace+1:end-1]
        target=None
        tm=re.search(r'\\Prontoo\\[A-Za-z0-9_\\]+::[A-Za-z_][A-Za-z0-9_]*\s*\(', body)
        if tm: target=tm.group(0)[:-1].strip()
        out.append((name,target,' '.join(body.split())[:180]))
    return out

report={}; total=0; mapped=0
for path in facades:
    src=(root/path).read_text()
    fs=functions(src); total+=len(fs); mapped+=sum(1 for _,t,_ in fs if t)
    report[path]={'count':len(fs),'unmapped':[{'name':n,'body':b} for n,t,b in fs if not t]}
print(json.dumps({'facades':len(facades),'functions':total,'mapped':mapped,'report':report},indent=2,ensure_ascii=False))
raise SystemExit('characterization-only')
