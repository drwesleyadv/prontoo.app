import os
import stat
from pathlib import Path

root = Path.cwd()

cache_path = root / "app/Infrastructure/ServerJsonCache/ServerJsonCacheInfrastructureOperations01.php"
cache_source = cache_path.read_text(encoding="utf-8")
cache_root_old = '''        if (function_exists("security_storage_deny_file")) {
            \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($resolved);
        } else {
            $deny = $resolved . "/.htaccess";
            if (!is_file($deny)) {
                @file_put_contents($deny, "Require all denied\\n", LOCK_EX);
            }
        }'''
cache_root_new = '        \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($resolved);'
if cache_source.count(cache_root_old) != 1:
    raise RuntimeError("ServerJsonCache root hardening drift")
cache_source = cache_source.replace(cache_root_old, cache_root_new)
for variable in ["$dir", "$generationDir"]:
    old = f'''        if (function_exists("security_storage_deny_file")) {{
            \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file({variable});
        }}'''
    new = f'        \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file({variable});'
    if cache_source.count(old) != 1:
        raise RuntimeError(f"ServerJsonCache hardening drift: {variable}")
    cache_source = cache_source.replace(old, new)
cache_path.write_text(cache_source, encoding="utf-8")

telemetry_path = root / "app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations01.php"
telemetry_source = telemetry_path.read_text(encoding="utf-8")
telemetry_old = '''        if (function_exists("security_storage_deny_file")) {
            \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
        } else {
            if (!is_file($dir . "/.htaccess")) {
                @file_put_contents($dir . "/.htaccess", "Require all denied\\n", LOCK_EX);
            }
            if (!is_file($dir . "/index.html")) {
                @file_put_contents($dir . "/index.html", "", LOCK_EX);
            }
        }'''
telemetry_new = '        \\Prontoo\\Infrastructure\\SecurityPrivacy\\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);'
if telemetry_source.count(telemetry_old) != 1:
    raise RuntimeError("SupportTelemetry hardening drift")
telemetry_path.write_text(telemetry_source.replace(telemetry_old, telemetry_new), encoding="utf-8")

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
