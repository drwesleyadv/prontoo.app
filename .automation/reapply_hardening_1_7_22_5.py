#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

ROOT = Path.cwd()
VERSION = "1.7.22.5"
PREVIOUS = "1.7.22.4"
BUILD = "1.7.22.5-commissioned-hardening-https"
PACKAGE = "commissioned_hardening_https"
GENERATED_AT = "2026-07-22T18:45:00Z"
GENERATED_UNIX = 1784745900
SYNC_ID = "hostoo-hardening-https-20260722T184500Z"


def load_json(path: str) -> dict:
    data = json.loads((ROOT / path).read_text(encoding="utf-8"))
    if not isinstance(data, dict):
        raise RuntimeError(f"JSON raiz inválido: {path}")
    return data


def write_json(path: str, data: dict, indent: int = 2) -> None:
    (ROOT / path).write_text(
        json.dumps(data, ensure_ascii=False, indent=indent) + "\n",
        encoding="utf-8",
    )


version = load_json("version.json")
version.update(
    {
        "version": VERSION,
        "release": VERSION,
        "generated_at_unix": GENERATED_UNIX,
        "generated_at": GENERATED_AT,
        "updated_at": GENERATED_AT,
        "build": BUILD,
        "package_type": PACKAGE,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "documentation_changes": True,
        "previous_version": PREVIOUS,
        "notes": "Hardening pós-reinstalação: bloqueia instalação e DDL em produção e exige HTTPS com redirecionamento 308 no servidor e no runtime.",
        "installer_locked": True,
        "installer_access_policy": "http_disabled_github_actions_cli_certification_only",
        "schema_mutation_policy": "runtime_frozen_github_actions_cli_four_markers_only",
        "https_policy": "canonical_host_https_308_webserver_and_runtime",
        "deployment_sync_id": SYNC_ID,
        "deployment_sync_requested_at": GENERATED_AT,
    }
)
version.pop("temporary_public_installer", None)
write_json("version.json", version)

for path in (
    "app/architecture.manifest.json",
    "app/Database/operational-schema.contract.json",
):
    data = load_json(path)
    data["version"] = VERSION
    if "release" in data:
        data["release"] = VERSION
    if "generated_at" in data:
        data["generated_at"] = GENERATED_AT
    write_json(path, data)

prontoo_path = ROOT / "app/prontoo.php"
prontoo = prontoo_path.read_text(encoding="utf-8")
prontoo = re.sub(
    r'const PRONTOO_VERSION_FALLBACK = "[^"]+";',
    f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";',
    prontoo,
    count=1,
)
prontoo = re.sub(
    r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";',
    f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS}";',
    prontoo,
    count=1,
)
root_anchor = '''if (!defined("PRONTOO_ROOT")) {
    define("PRONTOO_ROOT", dirname(__DIR__));
}
'''
https_guard = '''if (!defined("PRONTOO_ROOT")) {
    define("PRONTOO_ROOT", dirname(__DIR__));
}
if (PHP_SAPI !== "cli") {
    // PRONTOO_HTTPS_RUNTIME_GUARD
    $prontooHttps = strtolower(trim((string) ($_SERVER["HTTPS"] ?? "")));
    $prontooForwardedProtoParts = explode(",", strtolower((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")));
    $prontooForwardedProto = trim((string) ($prontooForwardedProtoParts[0] ?? ""));
    $prontooRequestSecure = in_array($prontooHttps, ["on", "1"], true) ||
        (int) ($_SERVER["SERVER_PORT"] ?? 0) === 443 ||
        $prontooForwardedProto === "https";
    $prontooRequestHost = strtolower(trim((string) ($_SERVER["HTTP_HOST"] ?? "")));
    $prontooRequestHost = preg_replace('/:\\d+$/', '', $prontooRequestHost) ?? "";
    if (!$prontooRequestSecure || $prontooRequestHost !== "prontoo.app") {
        $prontooRequestUri = (string) ($_SERVER["REQUEST_URI"] ?? "/");
        if ($prontooRequestUri === "" || !str_starts_with($prontooRequestUri, "/")) {
            $prontooRequestUri = "/";
        }
        if (!headers_sent()) {
            header("Location: https://prontoo.app" . $prontooRequestUri, true, 308);
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        exit;
    }
    unset(
        $prontooHttps,
        $prontooForwardedProtoParts,
        $prontooForwardedProto,
        $prontooRequestSecure,
        $prontooRequestHost,
        $prontooRequestUri,
    );
}
'''
if root_anchor not in prontoo:
    raise RuntimeError("Âncora PRONTOO_ROOT não localizada.")
prontoo = prontoo.replace(root_anchor, https_guard, 1)
prontoo_path.write_text(prontoo, encoding="utf-8")

landing_path = ROOT / "br/index.php"
landing = landing_path.read_text(encoding="utf-8")
landing = re.sub(
    r'BR_LANDING_VERSION_FALLBACK\s*=\s*["\'][^"\']+["\']',
    f'BR_LANDING_VERSION_FALLBACK = "{VERSION}"',
    landing,
    count=1,
)
landing_anchor = '''declare(strict_types=1);
const BR_LANDING_VERSION_FALLBACK'''
landing_guard = '''declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    // PRONTOO_HTTPS_RUNTIME_GUARD
    $brHttps = strtolower(trim((string) ($_SERVER["HTTPS"] ?? "")));
    $brForwardedParts = explode(",", strtolower((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")));
    $brForwardedProto = trim((string) ($brForwardedParts[0] ?? ""));
    $brSecure = in_array($brHttps, ["on", "1"], true) ||
        (int) ($_SERVER["SERVER_PORT"] ?? 0) === 443 ||
        $brForwardedProto === "https";
    $brHost = strtolower(trim((string) ($_SERVER["HTTP_HOST"] ?? "")));
    $brHost = preg_replace('/:\\d+$/', '', $brHost) ?? "";
    if (!$brSecure || $brHost !== "prontoo.app") {
        $brUri = (string) ($_SERVER["REQUEST_URI"] ?? "/");
        if ($brUri === "" || !str_starts_with($brUri, "/")) {
            $brUri = "/";
        }
        if (!headers_sent()) {
            header("Location: https://prontoo.app" . $brUri, true, 308);
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        exit;
    }
    unset($brHttps, $brForwardedParts, $brForwardedProto, $brSecure, $brHost, $brUri);
}
const BR_LANDING_VERSION_FALLBACK'''
if landing_anchor not in landing:
    raise RuntimeError("Âncora da landing não localizada.")
landing = landing.replace(landing_anchor, landing_guard, 1)
landing_path.write_text(landing, encoding="utf-8")

htaccess_path = ROOT / ".htaccess"
htaccess = htaccess_path.read_text(encoding="utf-8")
htaccess = htaccess.replace("[R=301,L]", "[R=308,L]")
hsts = '    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"\n'
csp = '    Header always set Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"\n'
if hsts not in htaccess:
    raise RuntimeError("Cabeçalho HSTS não localizado.")
if csp not in htaccess:
    htaccess = htaccess.replace(hsts, hsts + csp, 1)
htaccess_path.write_text(htaccess, encoding="utf-8")

security_path = ROOT / "tools/install-security-check.php"
security = security_path.read_text(encoding="utf-8")
security_anchor = '''if (!preg_match('/<Files\\s+"install\\.php">\\s*(?:#[^\\n]*\\s*)*Require\\s+all\\s+denied\\s*<\\/Files>/s', $htaccess) ||
    preg_match('/<Files\\s+"install\\.php">\\s*Require\\s+all\\s+granted\\s*<\\/Files>/s', $htaccess)) {
    $errors[] = 'webserver_install_not_denied';
}
'''
security_https = security_anchor + '''if (substr_count($htaccess, '[R=308,L]') < 2 ||
    !str_contains($htaccess, 'Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"') ||
    !str_contains($htaccess, 'Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"')) {
    $errors[] = 'webserver_https_policy';
}
foreach (['app/prontoo.php', 'br/index.php'] as $httpsRuntimeFile) {
    $httpsRuntimeSource = (string) file_get_contents($root . '/' . $httpsRuntimeFile);
    if (!str_contains($httpsRuntimeSource, 'PRONTOO_HTTPS_RUNTIME_GUARD') ||
        !str_contains($httpsRuntimeSource, 'Location: https://prontoo.app') ||
        !str_contains($httpsRuntimeSource, 'true, 308')) {
        $errors[] = 'runtime_https_guard:' . $httpsRuntimeFile;
    }
}
'''
if security_anchor not in security:
    raise RuntimeError("Âncora do teste de .htaccess não localizada.")
security = security.replace(security_anchor, security_https, 1)
security = security.replace(
    "    'schema_frozen' => true,\n",
    "    'schema_frozen' => true,\n    'https_enforced' => true,\n",
    1,
)
security_path.write_text(security, encoding="utf-8")

changelog_path = ROOT / "ChangeLog.txt"
changelog = changelog_path.read_text(encoding="utf-8")
entry = f'''Prontoo {VERSION} — hardening pós-reinstalação e HTTPS obrigatório

- Restaura integralmente o hardening pós-comissionamento da árvore certificada 1.7.22.3.
- Bloqueia `install.php` no Apache/LiteSpeed e novamente no primeiro ponto PHP.
- Mantém a estrutura do banco congelada em runtime; DDL só pode abrir na certificação CLI integral do GitHub Actions.
- Exige o host canônico `prontoo.app` e HTTPS em todas as páginas, com redirecionamento permanente 308 no servidor e fallback no runtime PHP.
- Mantém HSTS e adiciona `upgrade-insecure-requests; block-all-mixed-content`.
- Preserva o schema r7, os dados e a configuração da instalação atual; não há alteração de banco ou schema.

'''
changelog_path.write_text(entry + changelog, encoding="utf-8")

manifest = load_json("app/update.manifest.json")
manifest.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "package_type": PACKAGE,
        "generated_at": GENERATED_AT,
        "updated_at": GENERATED_AT,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "documentation_changes": True,
        "previous_version": PREVIOUS,
        "notes": "Hardening pós-reinstalação com instalador e DDL bloqueados e HTTPS obrigatório em todas as páginas.",
        "installer_policy": "http_disabled_github_actions_cli_certification_only",
        "schema_mutation_policy": "runtime_frozen_github_actions_cli_four_markers_only",
        "https_policy": "canonical_host_https_308_webserver_and_runtime",
        "deployment_sync_id": SYNC_ID,
    }
)
manifest.pop("temporary_public_installer", None)
files = manifest.get("files")
if not isinstance(files, dict):
    raise RuntimeError("Manifesto sem mapa de arquivos.")
for relative in list(files):
    path = ROOT / relative
    if not path.is_file():
        raise RuntimeError(f"Arquivo ausente no manifesto: {relative}")
    files[relative] = hashlib.sha256(path.read_bytes()).hexdigest()
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = sum(
    (ROOT / relative).stat().st_size for relative in files
)
write_json("app/update.manifest.json", manifest, 4)

for relative, expected in load_json("app/update.manifest.json")["files"].items():
    actual = hashlib.sha256((ROOT / relative).read_bytes()).hexdigest()
    if actual != expected:
        raise RuntimeError(f"Hash divergente após hardening: {relative}")

print(
    json.dumps(
        {
            "ok": True,
            "version": VERSION,
            "previous_version": PREVIOUS,
            "https_policy": version["https_policy"],
            "installer_locked": version["installer_locked"],
            "schema_changes": version["schema_changes"],
        },
        ensure_ascii=False,
    )
)
