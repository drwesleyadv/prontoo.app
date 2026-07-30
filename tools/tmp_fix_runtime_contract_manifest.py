from __future__ import annotations

import hashlib
import json
import re
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.19"
PREVIOUS = "1.7.30.18"
BUILD = "1.7.30.19-runtime-contract-manifest-sync"
DEPLOYMENT_ID = "github-runtime-contract-manifest-sync-1-7-30-19"
NOW = datetime.now(timezone.utc)
NOW_ISO = NOW.isoformat()
NOW_SECONDS = NOW.replace(microsecond=0).isoformat()
NOTES = (
    "Sincroniza o manifesto de atualização com a versão canônica, restaura o runtime "
    "e adiciona bloqueio de CI contra publicações parciais de version, release, build e schema."
)


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def load_json(path: str) -> dict:
    return json.loads(read(path))


def save_json(path: str, data: dict) -> None:
    write(path, json.dumps(data, ensure_ascii=False, indent=4) + "\n")


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1, encontrado {count}")
    return text.replace(old, new, 1)


version = load_json("version.json")
version.update(
    {
        "version": VERSION,
        "release": VERSION,
        "generated_at_unix": int(NOW.timestamp()),
        "generated_at": NOW_ISO,
        "updated_at": NOW_ISO,
        "build": BUILD,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": False,
        "documentation_changes": True,
        "previous_version": PREVIOUS,
        "notes": NOTES,
        "deployment_sync_id": DEPLOYMENT_ID,
        "deployment_sync_requested_at": NOW_ISO,
        "rewrite_scope": "runtime_contract_update_manifest_sync_guard",
    }
)
save_json("version.json", version)

architecture = load_json("app/architecture.manifest.json")
architecture.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "generated_at": NOW_ISO,
        "updated_at": NOW_ISO,
        "logic_changes": True,
        "visual_changes": False,
        "contract_consistency_policy": "version_architecture_and_update_manifests_must_match_canonical_release_metadata_and_architecture_limits",
        "runtime_version_contract_policy": "version_json_app_fallback_landing_and_update_manifest_version_release_build_schema_revision_must_match",
    }
)
save_json("app/architecture.manifest.json", architecture)

app = read("app/prontoo.php")
app = replace_once(
    app,
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.18";',
    f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";',
    "version fallback",
)
app = re.sub(
    r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";',
    f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS}";',
    app,
    count=1,
)
manifest_pattern = re.compile(
    r'    \$manifestFile = PRONTOO_ROOT \. "/app/update\.manifest\.json";\n.*?    \$landingFile = PRONTOO_ROOT \. "/br/index\.php";',
    re.S,
)
manifest_replacement = '''    $manifestFile = PRONTOO_ROOT . "/app/update.manifest.json";
    if (!is_file($manifestFile)) {
        $issues[] = "app/update.manifest.json:missing";
    } else {
        $raw = @file_get_contents($manifestFile);
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($manifest)) {
            $issues[] = "app/update.manifest.json:invalid";
        } else {
            foreach ([
                "version" => $expected,
                "release" => $expected,
                "build" => trim((string) ($metadata["build"] ?? "")),
                "schema_revision" => trim((string) ($metadata["schema_revision"] ?? "")),
            ] as $key => $canonicalValue) {
                if (trim((string) ($manifest[$key] ?? "")) !== $canonicalValue) {
                    $issues[] = "app/update.manifest.json:" . $key;
                }
            }
        }
    }
    $landingFile = PRONTOO_ROOT . "/br/index.php";'''
app, manifest_count = manifest_pattern.subn(manifest_replacement, app, count=1)
if manifest_count != 1:
    raise RuntimeError(f"bloco de manifesto do runtime: esperado 1, encontrado {manifest_count}")
write("app/prontoo.php", app)

landing = read("br/index.php")
landing, landing_count = re.subn(
    r'(BR_LANDING_VERSION_FALLBACK\s*=\s*["\'])[^"\']+(["\'])',
    rf'\g<1>{VERSION}\g<2>',
    landing,
    count=1,
)
if landing_count != 1:
    raise RuntimeError(f"landing fallback: esperado 1, encontrado {landing_count}")
write("br/index.php", landing)

architecture_check = read("tools/architecture-check.php")
anchor = """}
if (!defined('PRONTOO_VERSION')) {
"""
insert = """}
$updateManifestMetadata = json_decode(
    (string) file_get_contents($root . '/app/update.manifest.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
foreach ([
    'version' => 'version',
    'release' => 'release',
    'build' => 'build',
    'package_type' => 'package_type',
    'schema_revision' => 'schema_revision',
    'minimum_php' => 'minimum_php',
    'minimum_mysql' => 'minimum_mysql',
    'database_changes' => 'database_changes',
    'schema_changes' => 'schema_changes',
    'logic_changes' => 'logic_changes',
    'visual_changes' => 'visual_changes',
    'documentation_changes' => 'documentation_changes',
    'previous_version' => 'previous_version',
    'deployment_sync_id' => 'deployment_sync_id',
] as $versionKey => $manifestKey) {
    if (($versionMetadata[$versionKey] ?? null) !== ($updateManifestMetadata[$manifestKey] ?? null)) {
        throw new RuntimeException(
            'Contrato de release divergente entre version.json e app/update.manifest.json: ' . $versionKey,
        );
    }
}
if (!defined('PRONTOO_VERSION')) {
"""
architecture_check = replace_once(
    architecture_check,
    anchor,
    insert,
    "guard do update manifest",
)
write("tools/architecture-check.php", architecture_check)

changelog = read("CHANGELOG.md")
heading = "# Histórico de versões\n\n"
entry = (
    "## 1.7.30.19 — Sincronização do contrato de versão\n\n"
    "- corrige `app/update.manifest.json`, que permaneceu em 1.7.30.17 após a publicação 1.7.30.18;\n"
    "- sincroniza version, release, build, schema e hashes do pacote com a fonte canônica;\n"
    "- torna o diagnóstico do runtime específico por campo divergente;\n"
    "- adiciona guard de CI que bloqueia publicação parcial antes do merge;\n"
    "- preserva banco, schema e comportamento funcional da plataforma.\n\n"
)
changelog = replace_once(changelog, heading, heading + entry, "CHANGELOG")
write("CHANGELOG.md", changelog)

text_changelog = read("ChangeLog.txt")
text_entry = (
    "Prontoo 1.7.30.19 — Sincronização do contrato de versão\n\n"
    "- Corrige o manifesto de atualização que permaneceu em 1.7.30.17 após a publicação 1.7.30.18.\n"
    "- Sincroniza versão, release, build, schema e hashes com version.json.\n"
    "- Adiciona validação de CI contra futuras publicações parciais.\n"
    "- Banco, schema e comportamento funcional permanecem inalterados.\n\n"
)
write("ChangeLog.txt", text_entry + text_changelog)

manifest = load_json("app/update.manifest.json")
manifest.update(
    {
        "version": VERSION,
        "release": VERSION,
        "schema_revision": version["schema_revision"],
        "build": BUILD,
        "package_type": version["package_type"],
        "generated_at": NOW_SECONDS,
        "minimum_php": version["minimum_php"],
        "minimum_mysql": version["minimum_mysql"],
        "database_changes": version["database_changes"],
        "schema_changes": version["schema_changes"],
        "logic_changes": version["logic_changes"],
        "visual_changes": version["visual_changes"],
        "documentation_changes": version["documentation_changes"],
        "previous_version": PREVIOUS,
        "updated_at": NOW_SECONDS,
        "notes": NOTES,
        "deployment_sync_id": DEPLOYMENT_ID,
    }
)
files = manifest.get("files")
if not isinstance(files, dict) or not files:
    raise RuntimeError("mapa de arquivos ausente no update manifest")
size_total = 0
for relative in list(files):
    path = ROOT / relative
    if not path.is_file():
        raise RuntimeError(f"arquivo do manifesto ausente: {relative}")
    payload = path.read_bytes()
    files[relative] = hashlib.sha256(payload).hexdigest()
    size_total += len(payload)
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = size_total
manifest["files"] = files
save_json("app/update.manifest.json", manifest)

canonical = load_json("version.json")
update = load_json("app/update.manifest.json")
for key in (
    "version",
    "release",
    "build",
    "package_type",
    "schema_revision",
    "minimum_php",
    "minimum_mysql",
    "database_changes",
    "schema_changes",
    "logic_changes",
    "visual_changes",
    "documentation_changes",
    "previous_version",
    "deployment_sync_id",
):
    if canonical.get(key) != update.get(key):
        raise RuntimeError(f"contrato final divergente: {key}")

app_final = read("app/prontoo.php")
for required in (
    f'PRONTOO_VERSION_FALLBACK = "{VERSION}"',
    f'PRONTOO_PREVIOUS_VERSION = "{PREVIOUS}"',
    'app/update.manifest.json:version',
    'app/update.manifest.json:release',
    'app/update.manifest.json:build',
    'app/update.manifest.json:schema_revision',
):
    if required not in app_final:
        raise RuntimeError(f"token final ausente: {required}")

print(json.dumps({"version": VERSION, "build": BUILD, "files": len(files)}, ensure_ascii=False))
