from pathlib import Path
import json
import re
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.7"
PREVIOUS = "1.7.30.6"
BUILD = "1.7.30.7-final-architecture-contract-sync"
NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
NOW_UNIX = int(time.time())


def update_json(path: str, values: dict) -> None:
    target = ROOT / path
    data = json.loads(target.read_text(encoding="utf-8"))
    data.update(values)
    target.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")


architecture_check = ROOT / "tools/architecture-check.php"
source = architecture_check.read_text(encoding="utf-8")
old = "$versionMetadata = json_decode((string) file_get_contents($root . '/version.json'), true, 512, JSON_THROW_ON_ERROR);\n"
new = old + "$architectureMetadata = json_decode((string) file_get_contents($root . '/app/architecture.manifest.json'), true, 512, JSON_THROW_ON_ERROR);\nforeach ([\n    'version' => 'version',\n    'architecture_native_files_min' => 'native_files_min',\n    'architecture_transitional_files_max' => 'transitional_files_max',\n] as $versionKey => $architectureKey) {\n    if (($versionMetadata[$versionKey] ?? null) !== ($architectureMetadata[$architectureKey] ?? null)) {\n        throw new RuntimeException('Contrato arquitetural divergente entre version.json e architecture.manifest.json: ' . $versionKey);\n    }\n}\n"
if source.count(old) != 1:
    raise SystemExit("Carregamento de version.json divergente")
architecture_check.write_text(source.replace(old, new, 1), encoding="utf-8")

common = {
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "package_type": "contract_correction",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": False,
    "visual_changes": False,
    "functional_equivalence_policy": "preserves_1_7_30_6_runtime_behavior",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "notes": "Sincroniza os limites arquiteturais canônicos e impede nova divergência entre contratos.",
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "architecture_contract_metadata_consistency",
    "deployment_sync_id": "github-final-architecture-contract-sync-1-7-30-7",
    "deployment_sync_requested_at": NOW,
    "architecture_native_files_min": 37,
    "architecture_transitional_files_max": 80,
}
update_json("version.json", common)
update_json("app/update.manifest.json", common)
update_json("app/architecture.manifest.json", {
    "version": VERSION,
    "generated_at": NOW,
    "updated_at": NOW,
    "baseline_source": PREVIOUS,
    "native_files_min": 37,
    "transitional_files_max": 80,
    "contract_consistency_policy": "version_and_architecture_manifests_must_match_version_native_min_and_transitional_max",
    "contract_consistency_check": "tools/architecture-check.php",
})

for path, constant in [("app/prontoo.php", "PRONTOO_VERSION_FALLBACK"), ("br/index.php", "BR_LANDING_VERSION_FALLBACK")]:
    target = ROOT / path
    current = target.read_text(encoding="utf-8")
    pattern = rf"(const\s+{constant}\s*=\s*['\"])[^'\"]+(['\"]\s*;)"
    current, count = re.subn(pattern, rf"\g<1>{VERSION}\g<2>", current, count=1)
    if count != 1:
        raise SystemExit(f"Fallback não localizado: {path}")
    target.write_text(current, encoding="utf-8")

changelog = ROOT / "CHANGELOG.md"
text = changelog.read_text(encoding="utf-8")
entry = """\n## 1.7.30.7 — Sincronização final dos contratos arquiteturais

- sincroniza a baseline nativa em 37 nos contratos de versão e arquitetura;
- mantém o teto transitório em 80;
- adiciona verificação fail-fast de consistência entre os dois manifestos;
- não altera banco, schema, lógica, interface ou comportamento operacional.\n"""
if entry.strip() not in text:
    if not text.startswith("# Histórico de versões"):
        raise SystemExit("Cabeçalho do CHANGELOG.md divergente")
    changelog.write_text(text.replace("# Histórico de versões\n", "# Histórico de versões\n" + entry, 1), encoding="utf-8")

change_txt = ROOT / "ChangeLog.txt"
text = change_txt.read_text(encoding="utf-8")
entry = """Prontoo 1.7.30.7 — Sincronização final dos contratos arquiteturais

- Corrige a baseline nativa para 37 e impede divergência futura entre manifestos.
- Mantém banco, schema, lógica, interface e comportamento operacional.
"""
if not text.startswith("Prontoo 1.7.30.7"):
    change_txt.write_text(entry + text, encoding="utf-8")
