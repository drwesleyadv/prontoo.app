from __future__ import annotations

import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def write(rel: str, content: str) -> None:
    (ROOT / rel).write_text(content, encoding="utf-8")


def replace_once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1 trecho, encontrado {count}")
    return source.replace(old, new, 1)


auth = read("app/Auth/AuthOnboarding.php")
auth = replace_once(
    auth,
    "                    $userRow = $person;\n$passwordValid = $person && $userRow\n",
    "                    $userRow = $person;\n                    $passwordValid = $person && $userRow\n",
    "indentação password_verify",
)

credential_write = '''        q(
            "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE
               meta_value=IF(meta_value<>VALUES(meta_value),VALUES(meta_value),meta_value)",
            [login_last_credential_key($uid), $payload],
        );
'''
credential_write_precise = credential_write + '''        if (
            function_exists("server_json_cache_file") &&
            function_exists("server_json_cache_safe_key")
        ) {
            $cacheFile = server_json_cache_file(
                "meta",
                server_json_cache_safe_key("meta", [
                    login_last_credential_key($uid),
                ]),
            );
            if (is_file($cacheFile)) {
                @unlink($cacheFile);
            }
        }
'''
auth = replace_once(
    auth,
    credential_write,
    credential_write_precise,
    "invalidação precisa da última credencial",
)

mfa_query_old = '''    $user = one(
        "SELECT id,name,email,password_hash,is_global_admin,active FROM pi_users WHERE id=? AND active=1 LIMIT 1",
        [$uid],
    );
    if (
        !$user ||
        (int) ($user["is_global_admin"] ?? 0) !== 1 ||
        !hash_equals(
            user_auth_generation_current($uid),
            (string) ($pending["user_auth_generation"] ?? ""),
        )
    ) {
'''
mfa_query_new = '''    $user = one(
        "SELECT u.id,u.name,u.email,u.password_hash,u.is_global_admin,u.active,
                m.meta_value user_auth_generation
         FROM pi_users u
         LEFT JOIN pi_meta m ON m.meta_key=CONCAT('auth_user_',u.id)
         WHERE u.id=? AND u.active=1 LIMIT 1",
        [$uid],
    );
    if (
        !$user ||
        (int) ($user["is_global_admin"] ?? 0) !== 1 ||
        !hash_equals(
            (string) ($user["user_auth_generation"] ?? "0"),
            (string) ($pending["user_auth_generation"] ?? ""),
        )
    ) {
'''
auth = replace_once(
    auth,
    mfa_query_old,
    mfa_query_new,
    "revalidação MFA combinada",
)
auth = replace_once(
    auth,
    "function mfa_complete_pending_login(?array $verifiedUser = null): void\n{\n    $user = $verifiedUser ?: mfa_pending_login_user();\n",
    "function mfa_complete_pending_login(): void\n{\n    $user = mfa_pending_login_user();\n",
    "revalidação MFA final",
)
auth = replace_once(
    auth,
    '''    login_apply_resolved_credential(
        $uid,
        $credential,
        (string) ($pending["user_auth_generation"] ?? ""),
    );
''',
    '''    login_apply_resolved_credential(
        $uid,
        $credential,
        (string) ($user["user_auth_generation"] ?? ""),
    );
''',
    "geração MFA final",
)
if auth.count("mfa_complete_pending_login($user);") != 2:
    raise RuntimeError("Chamadas MFA otimizadas inesperadas")
auth = auth.replace("mfa_complete_pending_login($user);", "mfa_complete_pending_login();")
write("app/Auth/AuthOnboarding.php", auth)

security = read("app/Support/SecurityAccess.php")
storage_old = '''        $storageGuardFresh =
            is_file($storageGuardMarker) &&
            time() - (int) filemtime($storageGuardMarker) < 3600;
'''
storage_new = '''        $storageGuardFiles = [
            storage_path() . "/.htaccess",
            storage_path() . "/index.html",
            storage_path("cache") . "/.htaccess",
            storage_path("cache") . "/index.html",
            storage_path("logs") . "/.htaccess",
            storage_path("logs") . "/index.html",
        ];
        $storageGuardFilesPresent = true;
        foreach ($storageGuardFiles as $storageGuardFile) {
            if (!is_file($storageGuardFile)) {
                $storageGuardFilesPresent = false;
                break;
            }
        }
        $storageGuardFresh =
            $storageGuardFilesPresent &&
            is_file($storageGuardMarker) &&
            time() - (int) filemtime($storageGuardMarker) < 3600;
'''
security = replace_once(
    security,
    storage_old,
    storage_new,
    "proteção fail-closed dos diretórios",
)
write("app/Support/SecurityAccess.php", security)

check = read("tools/architecture-check.php")
check = replace_once(
    check,
    "        'mfa_complete_pending_login($user);',\n",
    "        \"LEFT JOIN pi_meta m ON m.meta_key=CONCAT('auth_user_',u.id)\",\n        'server_json_cache_file(',\n",
    "prova regressiva MFA e cache preciso",
)
check = replace_once(
    check,
    "        '.security-storage-',\n",
    "        '.security-storage-',\n        '$storageGuardFilesPresent',\n",
    "prova regressiva de hardening",
)
write("tools/architecture-check.php", check)

changelog = read("ChangeLog.txt")
changelog = changelog.replace(
    "- Reutiliza usuário e geração já validados no mesmo POST de MFA, memoiza a chave criptográfica por requisição e retira consultas de timezone antes de respostas que apenas redirecionam.",
    "- Combina usuário, privilégio e geração em uma única consulta por revalidação MFA, preserva a rechecagem final contra revogação concorrente, memoiza a chave criptográfica por requisição e retira consultas de timezone antes de respostas que apenas redirecionam.",
    1,
)
write("ChangeLog.txt", changelog)

for transient in [
    "tools/finalize-auth-performance.py",
    "tools/auth-finalize-error.txt",
    "tools/auth-finalize-retry.txt",
]:
    transient_path = ROOT / transient
    if transient_path.exists():
        transient_path.unlink()

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
files = manifest.get("files", {})
if not isinstance(files, dict) or not files:
    raise RuntimeError("Manifesto de arquivos inválido")
new_files: dict[str, str] = {}
total = 0
for rel in sorted(files):
    path = ROOT / rel
    if not path.is_file():
        raise RuntimeError(f"Arquivo versionado ausente: {rel}")
    data = path.read_bytes()
    new_files[rel] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["files"] = new_files
manifest["file_count"] = len(new_files)
manifest["total_uncompressed_bytes"] = total
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
    encoding="utf-8",
)

print("auth performance finalization complete")
