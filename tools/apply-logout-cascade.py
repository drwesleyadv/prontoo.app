from pathlib import Path
import hashlib
import json

root = Path(__file__).resolve().parents[1]

auth_path = root / "app/Support/SecurityAccess.php"
auth = auth_path.read_text(encoding="utf-8")
old = '    meta_set(user_auth_generation_key($uid), $generation);\n    return $generation;'
direct = 'INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)'
new = '''    q(
        "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)",
        [user_auth_generation_key($uid), $generation],
    );
    return $generation;'''
if old in auth:
    auth = auth.replace(old, new, 1)
elif direct not in auth:
    raise SystemExit("Trecho canônico da rotação não encontrado.")
auth_path.write_text(auth, encoding="utf-8")

check_path = root / "tools/architecture-check.php"
check = check_path.read_text(encoding="utf-8")
old_required = "        'meta_set(user_auth_generation_key($uid), $generation);',"
new_required = "        'INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)',"
if old_required in check:
    check = check.replace(old_required, new_required, 1)
elif new_required not in check:
    raise SystemExit("Prova da gravação geracional não encontrada.")

old_forbidden = "    'auth' => ['server_json_cache_clear_categories([\"context\", \"meta\"])'],"
new_forbidden = '''    'auth' => [
        'server_json_cache_clear_categories(["context", "meta"])',
        'meta_set(user_auth_generation_key($uid), $generation);',
    ],'''
if old_forbidden in check:
    check = check.replace(old_forbidden, new_forbidden, 1)
elif new_forbidden not in check:
    raise SystemExit("Lista de proibições da cascata não encontrada.")

old_logout_source = "    'logout' => (string) file_get_contents($root . '/app/Auth/AuthOnboarding.php'),"
if old_logout_source in check:
    anchor = "$logoutCascadeSources = ["
    prelude = '''$logoutModuleSource = (string) file_get_contents(
    $root . '/app/Auth/AuthOnboarding.php',
);
$logoutPageStart = strpos($logoutModuleSource, 'function page_logout(): void');
$logoutPageEnd = $logoutPageStart === false
    ? false
    : strpos($logoutModuleSource, 'function page_profile(): void', $logoutPageStart);
$logoutPageSource =
    $logoutPageStart !== false && $logoutPageEnd !== false
        ? substr($logoutModuleSource, $logoutPageStart, $logoutPageEnd - $logoutPageStart)
        : '';
$logoutCascadeSources = ['''
    if check.count(anchor) != 1:
        raise SystemExit("Âncora da prova de logout divergente.")
    check = check.replace(anchor, prelude, 1)
    check = check.replace(old_logout_source, "    'logout' => $logoutPageSource,", 1)
elif "    'logout' => $logoutPageSource," not in check:
    raise SystemExit("Escopo da prova de logout não encontrado.")
check_path.write_text(check, encoding="utf-8")

manifest_path = root / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
refreshed = {}
total = 0
for relative in manifest.get("files", {}):
    path = root / relative
    data = path.read_bytes()
    refreshed[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["files"] = refreshed
manifest["file_count"] = len(refreshed)
manifest["total_uncompressed_bytes"] = total
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)
