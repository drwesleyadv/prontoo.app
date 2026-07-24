from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

VERSION = "1.7.24.1"
PREVIOUS_VERSION = "1.7.23.7"
GENERATED_AT = "2026-07-24T12:32:45Z"
GENERATED_AT_UNIX = 1784896365
BUILD = "1.7.24.1-admin-dashboard-access"
PACKAGE_TYPE = "admin_dashboard_fix"
DEPLOYMENT_ID = "hostoo-admin-dashboard-access-20260724T123245Z"
NOTES = (
    "Corrige a precedência do Painel do Administrador para usuários com múltiplos cargos, "
    "restaura a exibição integral dos indicadores administrativos e inclui acesso exclusivo "
    "ao Painel antes de Agenda, sem alterar a Agenda como tela inicial."
)


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8", newline="\n")


def patch_dashboard() -> None:
    path = "app/Pages/Dashboards.php"
    source = read(path)
    old = '''    if (($c["role"] ?? "") === "medico") {
        page_medico_painel($c);
        return;
    }
    if (($c["role"] ?? "") === "recepcionista") {
        page_recepcao_painel($c);
        return;
    }
    if (($c["role"] ?? "") === "assistente") {
        page_triagem_painel($c);
        return;
    }
    if (has_effective_role($c, "gerente")) {
        page_gerente_painel($c);
        return;
    }
'''
    new = '''    if (has_effective_role($c, "gerente")) {
        page_gerente_painel($c);
        return;
    }
    if (($c["role"] ?? "") === "medico") {
        page_medico_painel($c);
        return;
    }
    if (($c["role"] ?? "") === "recepcionista") {
        page_recepcao_painel($c);
        return;
    }
    if (($c["role"] ?? "") === "assistente") {
        page_triagem_painel($c);
        return;
    }
'''
    if source.count(old) == 1:
        source = source.replace(old, new, 1)
        write(path, source)
    elif new not in source:
        raise SystemExit("Âncora do despacho do Painel não encontrada")


def patch_navigation() -> None:
    path = "app/Ui/Components.php"
    source = read(path)
    old = '''        foreach (role_actions_effective($effectiveRoles) as $key => $a) {
            if (can($key)) {
                $visibleActions[$key] = $a;
            }
        }
        $visibleActions = cmdbar_order_items(
'''
    new = '''        foreach (role_actions_effective($effectiveRoles) as $key => $a) {
            if (can($key)) {
                $visibleActions[$key] = $a;
            }
        }
        if (has_effective_role($c, "gerente") && can("painel")) {
            $adminPainelAction = [
                "label" => "Painel",
                "icon" => "dashboard",
            ];
            $adminVisibleActions = [];
            $adminPainelInserted = false;
            foreach ($visibleActions as $key => $action) {
                if ($key === "painel") {
                    continue;
                }
                if (!$adminPainelInserted && $key === "appointments") {
                    $adminVisibleActions["painel"] = $adminPainelAction;
                    $adminPainelInserted = true;
                }
                $adminVisibleActions[$key] = $action;
            }
            if (!$adminPainelInserted) {
                $adminVisibleActions =
                    ["painel" => $adminPainelAction] + $adminVisibleActions;
            }
            $visibleActions = $adminVisibleActions;
        }
        $visibleActions = cmdbar_order_items(
'''
    if source.count(old) == 1:
        source = source.replace(old, new, 1)
        write(path, source)
    elif new not in source:
        raise SystemExit("Âncora da barra de contexto não encontrada")


def update_release_contract() -> None:
    version_path = Path("version.json")
    version = json.loads(version_path.read_text(encoding="utf-8"))
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
            "notes": NOTES,
            "previous_version": PREVIOUS_VERSION,
            "documentation_changes": True,
            "deployment_sync_id": DEPLOYMENT_ID,
            "deployment_sync_requested_at": GENERATED_AT,
        }
    )
    version_path.write_text(
        json.dumps(version, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
        newline="\n",
    )

    architecture_path = Path("app/architecture.manifest.json")
    architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
    architecture.update(
        {
            "version": VERSION,
            "database_changes": False,
            "schema_changes": False,
            "visual_changes": True,
            "logic_changes": True,
        }
    )
    architecture_path.write_text(
        json.dumps(architecture, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
        newline="\n",
    )

    runtime_path = "app/prontoo.php"
    runtime = read(runtime_path)
    runtime, fallback_count = re.subn(
        r'const PRONTOO_VERSION_FALLBACK = "[^"]+";',
        f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";',
        runtime,
        count=1,
    )
    runtime, previous_count = re.subn(
        r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";',
        f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS_VERSION}";',
        runtime,
        count=1,
    )
    if fallback_count != 1 or previous_count != 1:
        raise SystemExit("Contrato de versão do runtime não localizado")
    write(runtime_path, runtime)

    landing_path = "br/index.php"
    landing = read(landing_path)
    landing, landing_count = re.subn(
        r'(BR_LANDING_VERSION_FALLBACK\s*=\s*)["\'][^"\']+["\']',
        rf'\1"{VERSION}"',
        landing,
        count=1,
    )
    if landing_count != 1:
        raise SystemExit("Fallback da landing não localizado")
    write(landing_path, landing)

    changelog_path = "ChangeLog.txt"
    changelog = read(changelog_path)
    header = f"Prontoo {VERSION} — Painel do Administrador restaurado"
    if not changelog.startswith(header):
        entry = f'''{header}

- Dá precedência ao cargo Administrativo ao abrir a tela Painel, inclusive quando o usuário possui múltiplos cargos ativos.
- Restaura a exibição dos indicadores operacionais, meta mensal, financeiro, ações recomendadas e receita por procedimento do Administrador.
- Inclui, exclusivamente para o Administrador, o botão Painel imediatamente antes de Agenda, com ícone próprio da tela.
- Mantém Agenda como tela inicial do primeiro login e dos acessos comuns; não altera autenticação, banco ou schema.

'''
        write(changelog_path, entry + changelog)

    manifest_path = Path("app/update.manifest.json")
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
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
            "previous_version": PREVIOUS_VERSION,
            "notes": NOTES,
            "deployment_sync_id": DEPLOYMENT_ID,
            "deployment_sync_requested_at": GENERATED_AT,
        }
    )
    files = manifest.get("files")
    if not isinstance(files, dict) or not files:
        raise SystemExit("Mapa de arquivos do manifesto ausente")
    total = 0
    for file_name in list(files):
        file_path = Path(file_name)
        if not file_path.is_file():
            raise SystemExit(f"Arquivo do manifesto ausente: {file_name}")
        payload = file_path.read_bytes()
        files[file_name] = hashlib.sha256(payload).hexdigest()
        total += len(payload)
    manifest["file_count"] = len(files)
    manifest["total_uncompressed_bytes"] = total
    manifest_path.write_text(
        json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
        encoding="utf-8",
        newline="\n",
    )


def validate_source_order() -> None:
    dashboards = read("app/Pages/Dashboards.php")
    page_start = dashboards.index("function page_painel(): void")
    page_source = dashboards[page_start:]
    manager_pos = page_source.index('has_effective_role($c, "gerente")')
    doctor_pos = page_source.index('($c["role"] ?? "") === "medico"')
    if manager_pos > doctor_pos:
        raise SystemExit("Administrador não tem precedência no Painel")

    components = read("app/Ui/Components.php")
    panel_pos = components.index('$adminVisibleActions["painel"]')
    agenda_pos = components.index('$key === "appointments"')
    if panel_pos < agenda_pos:
        raise SystemExit("Validação de ordem encontrou âncora inesperada")
    for required in [
        '"label" => "Painel"',
        '"icon" => "dashboard"',
        'has_effective_role($c, "gerente") && can("painel")',
    ]:
        if required not in components:
            raise SystemExit(f"Acesso administrativo ausente: {required}")


patch_dashboard()
patch_navigation()
update_release_contract()
validate_source_order()
