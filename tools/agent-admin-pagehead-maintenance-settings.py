#!/usr/bin/env python3
from pathlib import Path
import hashlib
import json
import os
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
OLD = "1.8.18.3"
NEW = "1.8.18.4"
STAMP = "2026-08-18T10:49:00-04:00"
STAMP_UNIX = 1787064540


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1 trecho, encontrados {count}")
    return source.replace(old, new, 1)


# PageHead global: Administração passa a expor as duas operações reais.
path = "app/Presentation/AdminPages/AdminPagesPresentationOperations01.php"
source = read(path)
source = replace_once(
    source,
    '            "admin_administration" => [["admin_administration", "Administração", "tune"]],',
    '            "admin_administration" => [\n'
    '                ["admin_maintenance", "Manutenção", "construction"],\n'
    '                ["admin_settings", "Configuração", "settings"],\n'
    '            ],',
    "PageHead de Administração",
)
write(path, source)

# A rota de entrada Administração deixa de renderizar a tela intermediária.
path = "app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php"
source = read(path)
start = source.index("    public static function page_admin_administration(): void")
end = source.index("    public static function page_admin_people(): void", start)
replacement = '''    public static function page_admin_administration(): void\n\n    {\n\n        \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations04::require_can("admin_administration");\n        \\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::redirect("admin_maintenance");\n    }\n\n'''
source = source[:start] + replacement + source[end:]
write(path, source)

# Remove o renderer morto da antiga tela de cartões.
path = "app/Presentation/AdminPages/AdminPagesPresentationOperations03.php"
source = read(path)
start = source.index("    public static function developer_administration_tools_html(")
end = source.index("    public static function admin_metrics_html(", start)
source = source[:start] + source[end:]
write(path, source)

# Manutenção e Configuração continuam compartilhando a implementação, mas a rota define a seção.
path = "app/Runtime/AdminPages/AdminPagesRuntimeOperations05.php"
source = read(path)
old_header = '''        $c = \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations04::require_can("admin_maintenance");\n        $uid = (int) ($c["user"]["id"] ?? 0);\n        $tab = preg_replace(\n            "/[^a-z_]/",\n            "",\n            (string) ($_GET["tab"] ?? ($_POST["tab"] ?? "manutencao")),\n        );\n        if (!in_array($tab, ["manutencao", "configuracoes"], true)) {\n            $tab = "manutencao";\n        }\n'''
new_header = '''        $currentRoute = \\Prontoo\\Presentation\\SupportFoundation\\SupportFoundationPresentationOperations01::route();\n        $tab = $currentRoute === "admin_settings" ? "configuracoes" : "manutencao";\n        $c = \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations04::require_can(\n            $tab === "configuracoes" ? "admin_settings" : "admin_maintenance",\n        );\n        $uid = (int) ($c["user"]["id"] ?? 0);\n'''
source = replace_once(source, old_header, new_header, "seleção da seção administrativa")
source = replace_once(
    source,
    '\\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::redirect("admin_maintenance", ["tab" => "manutencao"]);',
    '\\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::redirect("admin_maintenance");',
    "redirect Manutenção",
)
source = replace_once(
    source,
    '\\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::redirect("admin_maintenance", ["tab" => "configuracoes"]);',
    '\\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::redirect("admin_settings");',
    "redirect Configuração",
)

tabs_start = source.index('        $tabs =\n')
config_start = source.index('        if ($tab === "configuracoes") {', tabs_start)
source = source[:tabs_start] + source[config_start:]
source = source.replace('<input type="hidden" name="tab" value="configuracoes">', '', 1)
source = source.replace('<input type="hidden" name="tab" value="manutencao">', '', 1)

config_block_start = source.index('        if ($tab === "configuracoes") {')
config_block_end = source.index('            return;', config_block_start)
config_block = source[config_block_start:config_block_end]
config_block = config_block.replace('"Manutenção e Configuração"', '"Configuração"')
config_block = config_block.replace(' . $tabs . ', ' . ')
source = source[:config_block_start] + config_block + source[config_block_end:]

maintenance_page_start = source.index('        \\Prontoo\\Runtime\\UiComponents\\UiComponentsRuntimeOperations02::page(', config_block_end)
maintenance_page_end = source.index('    public static function page_admin_settings(): void', maintenance_page_start)
maintenance_block = source[maintenance_page_start:maintenance_page_end]
maintenance_block = maintenance_block.replace('"Manutenção e Configuração"', '"Manutenção"')
maintenance_block = maintenance_block.replace('                $tabs .\n', '')
source = source[:maintenance_page_start] + maintenance_block + source[maintenance_page_end:]

settings_start = source.index('    public static function page_admin_settings(): void')
settings_body_start = source.index('    {', settings_start)
settings_end = source.index('\n    }', settings_body_start) + len('\n    }')
settings_replacement = '''    public static function page_admin_settings(): void\n    \n    {\n    \n        \\Prontoo\\Runtime\\AdminPages\\AdminPagesRuntimeOperations05::page_admin_maintenance();\n    }'''
source = source[:settings_start] + settings_replacement + source[settings_end:]
write(path, source)

# Atualiza o contrato executável da navegação sem relaxar os demais ratchets.
path = "tools/presentation-visual-contract-check"
source = read(path)
source = replace_once(
    source,
    "$adminClinicsRuntime = (string) file_get_contents($root . '/app/Runtime/AdminPages/AdminPagesRuntimeOperations08.php');",
    "$adminClinicsRuntime = (string) file_get_contents($root . '/app/Runtime/AdminPages/AdminPagesRuntimeOperations08.php');\n"
    "$adminAdministrationRuntime = (string) file_get_contents($root . '/app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php');\n"
    "$adminMaintenanceRuntime = (string) file_get_contents($root . '/app/Runtime/AdminPages/AdminPagesRuntimeOperations05.php');",
    "fontes do contrato administrativo",
)
source = replace_once(
    source,
    ": strpos($adminClinicsPresentation, 'public static function developer_administration_tools_html(', $adminClinicsDirectoryStart);",
    ": strpos($adminClinicsPresentation, 'public static function admin_metrics_html(', $adminClinicsDirectoryStart);",
    "limite do diretório de consultórios",
)
source = replace_once(
    source,
    "    'admin_administration' => [\n        ['admin_administration', 'Administração', 'tune'],\n    ],",
    "    'admin_administration' => [\n        ['admin_maintenance', 'Manutenção', 'construction'],\n        ['admin_settings', 'Configuração', 'settings'],\n    ],",
    "contrato da PageHead Administração",
)
admin_check_start = source.index("$administrationStart = strpos($adminClinicsPresentation, 'public static function developer_administration_tools_html(');")
admin_check_end = source.index("foreach (['admin_painel', 'HealthEvidence', 'HealthCanary']", admin_check_start)
admin_check = '''if (str_contains($adminClinicsPresentation, 'developer_administration_tools_html(')) {\n    $failures[] = ['rule' => 'developer_administration_intermediate_screen_present'];\n}\nif (!str_contains($adminAdministrationRuntime, 'redirect("admin_maintenance")')) {\n    $failures[] = ['rule' => 'developer_administration_default_maintenance_missing'];\n}\nif (str_contains($adminMaintenanceRuntime, 'admin-maintenance-tabs')) {\n    $failures[] = ['rule' => 'developer_administration_internal_tabs_present'];\n}\nif (\n    !str_contains($adminMaintenanceRuntime, '$currentRoute === "admin_settings" ? "configuracoes" : "manutencao"') ||\n    !str_contains($adminMaintenanceRuntime, 'page_admin_maintenance();')\n) {\n    $failures[] = ['rule' => 'developer_administration_route_owned_sections_missing'];\n}\n'''
source = source[:admin_check_start] + admin_check + source[admin_check_end:]
write(path, source)

# Documentação normativa acompanha o novo comportamento.
path = "docs/operations/developer-control-center.md"
source = read(path)
source = replace_once(
    source,
    "- Administração contém somente **Manutenção** e **Configuração**.",
    "- A PageHead de Administração contém, nesta ordem, **Manutenção** e **Configuração**. Ao acessar Administração, Manutenção abre diretamente e fica ativa; a antiga tela intermediária que listava essas duas opções foi suprimida.",
    "documentação da Administração",
)
write(path, source)

# Versão incremental canônica.
version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "version": NEW,
    "release": NEW,
    "generated_at_unix": STAMP_UNIX,
    "generated_at": STAMP,
    "updated_at": STAMP,
    "build": "1.8.18.4-admin-pagehead-maintenance-settings",
    "asset_version": NEW,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
    "functional_equivalence_policy": "developer_administration_pagehead_maintenance_settings_default_maintenance_without_schema_change",
    "notes": "Administração passa a usar Manutenção e Configuração na PageHead, abre em Manutenção e remove a tela intermediária e as abas internas duplicadas.",
    "deployment_sync_id": "github-prontoo-1.8.18.4-admin-pagehead-maintenance-settings",
    "deployment_sync_requested_at": STAMP,
    "release_date": "2026-08-18",
    "changelog": {
        "title": "Manutenção e Configuração na PageHead de Administração",
        "items": [
            "promove Manutenção e Configuração para itens da PageHead de Administração",
            "faz Administração abrir diretamente em Manutenção, que fica ativa por padrão",
            "remove a tela intermediária que listava Manutenção e Configuração como opções",
            "remove as abas internas duplicadas e preserva os formulários e ações existentes de cada seção",
            "mantém permissões, banco e schema sem alterações estruturais"
        ]
    },
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

# O contrato canônico não mantém literais da release superada em texto rastreado.
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=ROOT).split(b"\0")
skip = {
    "tools/agent-admin-pagehead-maintenance-settings.py",
    ".github/workflows/agent-admin-pagehead-maintenance-settings.yml",
}
for raw in tracked:
    if not raw:
        continue
    rel = raw.decode("utf-8")
    if rel in skip:
        continue
    file = ROOT / rel
    if not file.is_file():
        continue
    try:
        text = file.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        continue
    if OLD in text:
        file.write_text(text.replace(OLD, NEW), encoding="utf-8")

# Republica os assets versionados sem alterar os bytes.
asset_names = [
    "app-icon-{v}.png",
    "favicon-{v}.ico",
    "favicon-{v}.png",
    "pix-{v}.svg",
    "prontoo-mark-{v}.png",
]
for pattern in asset_names:
    old_path = ROOT / "public/assets" / pattern.format(v=OLD)
    new_path = ROOT / "public/assets" / pattern.format(v=NEW)
    if not old_path.exists():
        raise RuntimeError(f"asset anterior ausente: {old_path}")
    os.rename(old_path, new_path)

# Se um literal de asset mudou dentro da fonte CSS, reconcilia o manifesto determinístico.
manifest_path = ROOT / "app/Presentation/Styles/styles.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
source_root = ROOT / manifest["source_root"]
source_bytes = {}
for entry in manifest["sources"]:
    data = (source_root / entry["path"]).read_bytes()
    source_bytes[entry["path"]] = data
    entry["sha256"] = hashlib.sha256(data).hexdigest()
    entry["bytes"] = len(data)
    entry["important_count"] = data.count(b"!important")
    entry["route_scope_count"] = data.count(b"body[data-route=")

built = bytearray()
for segment in manifest["sequence"]:
    data = source_bytes[segment["source"]]
    offset = int(segment["offset"])
    size = int(segment["bytes"])
    chunk = data[offset:offset + size]
    if len(chunk) != size:
        raise RuntimeError("slice CSS fora da fonte")
    segment["sha256"] = hashlib.sha256(chunk).hexdigest()
    segment["important_count"] = chunk.count(b"!important")
    segment["route_scope_count"] = chunk.count(b"body[data-route=")
    built.extend(chunk)
manifest["artifact_contract"]["sha256"] = hashlib.sha256(built).hexdigest()
manifest["artifact_contract"]["bytes"] = len(built)
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

# Contrato visual acompanha a release e o artefato que será regenerado pelo builder canônico.
visual_path = ROOT / "app/presentation.visual-contract.json"
visual = json.loads(visual_path.read_text(encoding="utf-8"))
visual["version"] = NEW
visual["delivery"]["current_sha256"] = hashlib.sha256(built).hexdigest()
visual["delivery"]["current_bytes"] = len(built)
visual_path.write_text(json.dumps(visual, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

print(json.dumps({
    "ok": True,
    "release": NEW,
    "administration_default": "admin_maintenance",
    "pagehead": ["admin_maintenance", "admin_settings"],
    "intermediate_screen": False,
    "internal_tabs": False,
}, ensure_ascii=False, indent=2))
