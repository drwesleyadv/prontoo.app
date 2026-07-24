from __future__ import annotations

import hashlib
import json
import re
import subprocess
from pathlib import Path

VERSION = "1.7.24.1"
PREVIOUS = "1.7.23.7"
GENERATED_AT = "2026-07-24T12:36:00Z"
GENERATED_AT_UNIX = 1784896560
BUILD = "1.7.24.1-admin-panel-navigation"
PACKAGE_TYPE = "admin_panel_ux_fix"
SYNC_ID = "hostoo-admin-panel-20260724T123600Z"


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"Âncora {label!r} encontrada {count} vez(es), esperado 1")
    return content.replace(old, new, 1)


# 1. Navegação exclusiva do Administrador: Painel antes de Agenda.
security_path = "app/Support/SecurityAccess.php"
security = read(security_path)
security = replace_once(
    security,
    '        "gerente" => [\n            "appointments",',
    '        "gerente" => [\n            "painel",\n            "appointments",',
    "ordem exclusiva do gerente",
)
security = replace_once(
    security,
    '    $order = [\n        "appointments",\n        "painel",',
    '    $order = [\n        "painel",\n        "appointments",',
    "ordem efetiva da cmdbar",
)
write(security_path, security)

# 2. Estrutura explícita para todos os itens do Painel administrativo.
dashboards_path = "app/Pages/Dashboards.php"
dashboards = read(dashboards_path)
old_panel = '''    page(
        "Painel",
        page_head(
            "Painel",
            "Indicadores operacionais, meta mensal, financeiro e ações prioritárias.",
        ) .
            $kpis .
            $goalHtml .
            $finance .
            $actionsHtml .
            $procedureBlock,
    );
}
function page_painel(): void'''
new_panel = '''    page(
        "Painel",
        page_head(
            "Painel",
            "Indicadores operacionais, meta mensal, financeiro e ações prioritárias.",
        ) .
            '<div class="manager-dashboard" aria-label="Painel administrativo">' .
            $kpis .
            $goalHtml .
            $finance .
            $actionsHtml .
            $procedureBlock .
            "</div>",
    );
}
function page_painel(): void'''
dashboards = replace_once(dashboards, old_panel, new_panel, "composição do Painel administrativo")
write(dashboards_path, dashboards)

# 3. Layout resiliente: o contêiner e todos os blocos permanecem visíveis.
css_path = "public/assets/design-system.css"
css = read(css_path)
css_anchor = 'body[data-route="painel"] .pagehead{'
css_rules = '''body[data-route="painel"] .manager-dashboard{
  display:grid!important;
  gap:var(--pt-dashboard-gap)!important;
  min-width:0!important;
}
body[data-route="painel"] .manager-dashboard > *{
  min-width:0!important;
  margin-top:0!important;
  margin-bottom:0!important;
}
'''
css = replace_once(css, css_anchor, css_rules + css_anchor, "contêiner visual do Painel")
css = replace_once(
    css,
    'body[data-route="painel"] .card.manager-procedures,\nbody[data-route="painel"] main > .card{',
    'body[data-route="painel"] .card.manager-procedures,\nbody[data-route="painel"] .card.manager-finance-card,\nbody[data-route="painel"] main > .card{',
    "card financeiro administrativo",
)
css = replace_once(
    css,
    'body[data-route="painel"] .manager-procedures h2,\nbody[data-route="painel"] main > .card h2{',
    'body[data-route="painel"] .manager-procedures h2,\nbody[data-route="painel"] .manager-finance-card h2,\nbody[data-route="painel"] main > .card h2{',
    "título do card financeiro administrativo",
)
write(css_path, css)

# 4. Contrato de versão.
version_path = "version.json"
version = json.loads(read(version_path))
version.update(
    {
        "version": VERSION,
        "release": VERSION,
        "generated_at_unix": GENERATED_AT_UNIX,
        "generated_at": GENERATED_AT,
        "updated_at": GENERATED_AT,
        "build": BUILD,
        "package_type": PACKAGE_TYPE,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "documentation_changes": True,
        "previous_version": PREVIOUS,
        "notes": "Corrige a exibição dos itens do Painel do Administrador e restaura o acesso Painel antes de Agenda, com ícone space_dashboard.",
        "deployment_sync_id": SYNC_ID,
        "deployment_sync_requested_at": GENERATED_AT,
        "admin_panel_policy": "manager_only_panel_button_before_agenda_and_explicit_dashboard_sections",
    }
)
write(version_path, json.dumps(version, ensure_ascii=False, indent=2) + "\n")

architecture_path = "app/architecture.manifest.json"
architecture = json.loads(read(architecture_path))
architecture["version"] = VERSION
write(architecture_path, json.dumps(architecture, ensure_ascii=False, indent=2) + "\n")

prontoo_path = "app/prontoo.php"
prontoo = read(prontoo_path)
prontoo = replace_once(
    prontoo,
    'const PRONTOO_VERSION_FALLBACK = "1.7.23.7";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.24.1";',
    "fallback do runtime",
)
prontoo = replace_once(
    prontoo,
    'const PRONTOO_PREVIOUS_VERSION = "1.7.23.6";',
    'const PRONTOO_PREVIOUS_VERSION = "1.7.23.7";',
    "versão anterior do runtime",
)
write(prontoo_path, prontoo)

landing_path = "br/index.php"
landing = read(landing_path)
landing = replace_once(
    landing,
    'const BR_LANDING_VERSION_FALLBACK = "1.7.23.7";',
    'const BR_LANDING_VERSION_FALLBACK = "1.7.24.1";',
    "fallback da landing",
)
write(landing_path, landing)

changelog_path = "ChangeLog.txt"
changelog = read(changelog_path)
entry = '''Prontoo 1.7.24.1 — Painel do Administrador

- Restaura a exibição do acesso Painel exclusivamente no perfil Administrador.
- Posiciona Painel antes de Agenda na barra de contextos, usando o ícone Material Symbol space_dashboard.
- Estrutura KPIs, meta, indicadores financeiros, ações recomendadas e receita por procedimento em um contêiner administrativo explícito, preservando os estados vazios informativos.
- Mantém Agenda como primeira tela do primeiro login; o novo botão não altera a rota inicial.
- Banco e schema permanecem inalterados.

'''
if not changelog.startswith("Prontoo 1.7.23.7"):
    raise RuntimeError("Topo inesperado do ChangeLog")
write(changelog_path, entry + changelog)

# 5. CI canônico final com regressão permanente da navegação e dos blocos.
workflow = subprocess.check_output(
    ["git", "show", "origin/main:.github/workflows/architecture.yml"],
    text=True,
    encoding="utf-8",
)
workflow_anchor = "      - name: Mathematical integrity properties\n"
regression_step = r'''      - name: Admin panel navigation and content regression
        shell: bash
        run: |
          php <<'PHP'
          <?php
          declare(strict_types=1);
          $security = (string) file_get_contents('app/Support/SecurityAccess.php');
          $dashboard = (string) file_get_contents('app/Pages/Dashboards.php');
          $css = (string) file_get_contents('public/assets/design-system.css');
          foreach ([
              '"gerente" => [' . "\n" . '            "painel",' . "\n" . '            "appointments",',
              '$order = [' . "\n" . '        "painel",' . "\n" . '        "appointments",',
              '"painel" => "space_dashboard"',
          ] as $required) {
              if (!str_contains($security, $required)) {
                  throw new RuntimeException('Regressão da navegação administrativa: ' . $required);
              }
          }
          foreach ([
              'class="manager-dashboard"',
              '$kpis .',
              '$goalHtml .',
              '$finance .',
              '$actionsHtml .',
              '$procedureBlock .',
          ] as $required) {
              if (!str_contains($dashboard, $required)) {
                  throw new RuntimeException('Item do Painel administrativo ausente: ' . $required);
              }
          }
          foreach ([
              'body[data-route="painel"] .manager-dashboard{',
              'body[data-route="painel"] .card.manager-finance-card,',
              'body[data-route="painel"] .manager-finance-card h2,',
          ] as $required) {
              if (!str_contains($css, $required)) {
                  throw new RuntimeException('Contrato visual do Painel ausente: ' . $required);
              }
          }
          PHP

'''
workflow = replace_once(workflow, workflow_anchor, regression_step + workflow_anchor, "etapa CI do Painel")
final_workflow_path = ".automation/final-architecture.yml"
write(final_workflow_path, workflow)

# 6. Manifesto selado contra o workflow canônico final, não contra o aplicador temporário.
manifest_path = "app/update.manifest.json"
manifest = json.loads(read(manifest_path))
manifest.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "package_type": PACKAGE_TYPE,
        "generated_at": GENERATED_AT,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "documentation_changes": True,
        "previous_version": PREVIOUS,
        "updated_at": GENERATED_AT,
        "notes": "Painel do Administrador com itens restaurados e botão Painel antes de Agenda.",
        "deployment_sync_id": SYNC_ID,
        "admin_panel_policy": "manager_only_panel_button_before_agenda_and_explicit_dashboard_sections",
    }
)
files = dict(manifest.get("files", {}))
if ".github/workflows/architecture.yml" not in files:
    raise RuntimeError("Workflow canônico ausente do manifesto")
final_workflow_bytes = workflow.encode("utf-8")
bytes_total = 0
for path in files:
    if path == ".github/workflows/architecture.yml":
        data = final_workflow_bytes
    else:
        data = Path(path).read_bytes()
    files[path] = hashlib.sha256(data).hexdigest()
    bytes_total += len(data)
manifest["files"] = files
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = bytes_total
write(manifest_path, json.dumps(manifest, ensure_ascii=False, indent=4) + "\n")

# 7. Invariantes finais locais da transformação.
assert '"gerente" => [\n            "painel",\n            "appointments",' in read(security_path)
assert '$order = [\n        "painel",\n        "appointments",' in read(security_path)
assert 'class="manager-dashboard"' in read(dashboards_path)
assert json.loads(read(version_path))["version"] == VERSION
assert json.loads(read(manifest_path))["version"] == VERSION
assert json.loads(read(architecture_path))["version"] == VERSION

print(
    json.dumps(
        {
            "ok": True,
            "version": VERSION,
            "previous_version": PREVIOUS,
            "database_changes": False,
            "schema_changes": False,
            "manager_panel_before_agenda": True,
            "manager_panel_icon": "space_dashboard",
            "manager_dashboard_sections": [
                "kpis",
                "goal",
                "finance",
                "recommended_actions",
                "procedure_revenue",
            ],
            "manifest_files": len(files),
            "manifest_bytes": bytes_total,
        },
        ensure_ascii=False,
        indent=2,
    )
)
