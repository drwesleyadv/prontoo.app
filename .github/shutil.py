import os
import stat
from pathlib import Path

root = Path.cwd()
path = root / "app/Infrastructure/ServerJsonCache/ServerJsonCacheInfrastructureOperations01.php"
source = path.read_text(encoding="utf-8")
root_old = '''        if (function_exists("security_storage_deny_file")) {
            \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($resolved);
        } else {
            $deny = $resolved . "/.htaccess";
            if (!is_file($deny)) {
                @file_put_contents($deny, "Require all denied\\n", LOCK_EX);
            }
        }'''
root_new = '        \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($resolved);'
if source.count(root_old) != 1:
    raise RuntimeError("ServerJsonCache root hardening drift")
source = source.replace(root_old, root_new)
for variable in ["$dir", "$generationDir"]:
    old = f'''        if (function_exists("security_storage_deny_file")) {{
            \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file({variable});
        }}'''
    new = f'        \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file({variable});'
    if source.count(old) != 1:
        raise RuntimeError(f"ServerJsonCache hardening drift: {variable}")
    source = source.replace(old, new)
path.write_text(source, encoding="utf-8")

try:
    Path(__file__).unlink()
except OSError:
    pass

def copy2(src, dst):
    source_path = Path(src)
    destination_path = Path(dst)
    destination_path.write_bytes(source_path.read_bytes())
    os.chmod(destination_path, stat.S_IMODE(os.stat(source_path).st_mode))
    return str(destination_path)
