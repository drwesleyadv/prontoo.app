from pathlib import Path
import shutil

root = Path.cwd()
target = root / "app/Runtime/SecurityAccess/SessionGenerationGuard.php"
source = target.read_text(encoding="utf-8")
old = "        SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();"
new = "        \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();"
if source.count(old) != 1:
    raise RuntimeError("SessionGenerationGuard normalization drift")
target.write_text(source.replace(old, new), encoding="utf-8")
Path(__file__).unlink()
shutil.rmtree(Path(__file__).parent / "__pycache__", ignore_errors=True)
