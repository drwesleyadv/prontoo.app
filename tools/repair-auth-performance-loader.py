from __future__ import annotations

import base64
import gzip
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
BASE_COMMIT = "1749779e8b9ccd46437f3d53fd41b5752deaa8f2"
loader = subprocess.check_output(
    ["git", "show", f"{BASE_COMMIT}:tools/apply-auth-performance.py"],
    cwd=ROOT,
    text=True,
)
match = re.search(r"b64decode\('([^']+)'\)", loader)
if not match:
    raise RuntimeError("Payload original do aplicador não encontrado")
source = gzip.decompress(base64.b64decode(match.group(1))).decode("utf-8")

old = '''retire_line = "        security_retire_persistent_devices_for_user($uid);\\n"
if apply_source.count(retire_line) != 2:
    raise RuntimeError("login_apply: esperado remover duas aposentadorias legadas")
apply_source = apply_source.replace(retire_line, "")
'''
new = '''apply_source, retire_count = re.subn(
    r"^[ \\t]*security_retire_persistent_devices_for_user\\(\\$uid\\);\\n",
    "",
    apply_source,
    count=2,
    flags=re.M,
)
if retire_count != 2:
    raise RuntimeError(
        f"login_apply: esperado remover duas aposentadorias legadas, removidas {retire_count}",
    )
'''
if old not in source:
    raise RuntimeError("Bloco de aposentadoria original não encontrado")
source = source.replace(old, new, 1)

old = '''auth = replace_once(
    auth,
    "    $user = mfa_pending_login_user();\\n",
    "    $user = $verifiedUser ?: mfa_pending_login_user();\\n",
    "reutilizar usuário MFA validado",
)
'''
new = '''auth = replace_once(
    auth,
    """function mfa_complete_pending_login(?array $verifiedUser = null): void
{
    $user = mfa_pending_login_user();
""",
    """function mfa_complete_pending_login(?array $verifiedUser = null): void
{
    $user = $verifiedUser ?: mfa_pending_login_user();
""",
    "reutilizar usuário MFA validado",
)
'''
if old not in source:
    raise RuntimeError("Bloco MFA original não encontrado")
source = source.replace(old, new, 1)

generation_pattern = re.compile(
    r'auth = replace_once\(\n'
    r'    auth,\n'
    r'    "    login_apply_resolved_credential\(\$uid, \$credential\);\\n",\n'
    r'.*?'
    r'    "reutilizar geração MFA validada",\n'
    r'\)\n',
    re.S,
)
generation_replacement = '''auth = replace_once(
    auth,
    """    $_SESSION["privileged_auth_at"] = time();
    prontoo_login_post_password_maintenance($uid);
    login_apply_resolved_credential($uid, $credential);
}
""",
    """    $_SESSION["privileged_auth_at"] = time();
    prontoo_login_post_password_maintenance($uid);
    login_apply_resolved_credential(
        $uid,
        $credential,
        (string) ($pending["user_auth_generation"] ?? ""),
    );
}
""",
    "reutilizar geração MFA validada",
)
'''
source, generation_count = generation_pattern.subn(
    generation_replacement,
    source,
    count=1,
)
if generation_count != 1:
    raise RuntimeError(
        f"Bloco de geração MFA original não encontrado: {generation_count}",
    )

old = '''script_path = ROOT / "tools/apply-auth-performance.py"
if script_path.exists():
    script_path.unlink()

files = manifest.get("files", {})
'''
new = '''for transient in [
    "tools/apply-auth-performance.py",
    "tools/repair-auth-performance-loader.py",
    "tools/auth-performance-error.txt",
    "tools/auth-performance-retry.txt",
]:
    transient_path = ROOT / transient
    if transient_path.exists():
        transient_path.unlink()

files = manifest.get("files", {})
'''
if old not in source:
    raise RuntimeError("Bloco transitório original não encontrado")
source = source.replace(old, new, 1)

payload = base64.b64encode(gzip.compress(source.encode("utf-8"), 9)).decode("ascii")
repaired = (
    "import base64,gzip\n"
    + "exec(compile(gzip.decompress(base64.b64decode("
    + repr(payload)
    + ")).decode('utf-8'), __file__, 'exec'))\n"
)
(ROOT / "tools/apply-auth-performance.py").write_text(repaired, encoding="utf-8")
print("loader repaired")
