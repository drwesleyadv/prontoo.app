from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import time

version = "1.7.30.20"
previous = "1.7.30.19"
build = "1.7.30.20-administrator-daily-agenda"
now = datetime.now(timezone.utc).isoformat()
now_unix = int(time.time())


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"Substituição não determinística em {path}: {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


replace_once(
    "app/Ui/Components.php",
    '''        if (has_effective_role($c, "gerente") && can("painel")) {
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
''',
    "",
)

replace_once(
    "app/Auth/AuthOnboarding.php",
    '''    $destination =
        (string) ($choice["role_code"] ?? "") === "gerente"
            ? "painel"
            : "appointments";
    if ($redirectAfterLogin) {
        redirect($destination);
    }
    return $destination;
''',
    '''    $isAdministrator =
        (string) ($choice["role_code"] ?? "") === "gerente";
    $destination = "appointments";
    if ($redirectAfterLogin) {
        redirect(
            $destination,
            $isAdministrator ? ["view" => "diario"] : [],
        );
    }
    return $destination;
''',
)

replace_once(
    "app/Pages/Dashboards.php",
    '''function page_painel(): void
{

    $c = require_can("painel");
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    if (has_effective_role($c, "gerente")) {
        page_gerente_painel($c);
        return;
    }
''',
    '''function page_painel(): void
{

    $c = need_login();
    if (has_effective_role($c, "gerente")) {
        redirect("appointments", ["view" => "diario"]);
    }
    $c = require_can("painel");
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
''',
)

replace_once(
    "app/prontoo.php",
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.19";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.20";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PREVIOUS_VERSION = "1.7.30.18";',
    'const PRONTOO_PREVIOUS_VERSION = "1.7.30.19";',
)
replace_once(
    "br/index.php",
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.19";',
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.20";',
)

version_path = Path("version.json")
metadata = json.loads(version_path.read_text(encoding="utf-8"))
metadata.update({
    "version": version,
    "release": version,
    "generated_at_unix": now_unix,
    "generated_at": now,
    "updated_at": now,
    "build": build,
    "previous_version": previous,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
    "functional_equivalence_policy": "administrator_profile_has_no_dashboard_entry_and_opens_daily_agenda_without_changing_other_roles",
    "notes": "Remove o Painel do perfil Administrador, abre a visão diária da Agenda após o login e redireciona defensivamente a rota antiga.",
    "deployment_sync_id": "github-administrator-daily-agenda-1-7-30-20",
    "deployment_sync_requested_at": now,
    "rewrite_scope": "administrator_navigation_and_login_destination",
})
version_path.write_text(
    json.dumps(metadata, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

architecture_path = Path("app/architecture.manifest.json")
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
    "version": version,
    "release": version,
    "build": build,
    "generated_at": now,
    "updated_at": now,
    "database_changes": False,
    "schema_changes": False,
    "visual_changes": True,
    "administrator_navigation_policy": "manager_dashboard_entry_absent_login_and_legacy_dashboard_route_resolve_to_daily_appointments",
})
architecture_path.write_text(
    json.dumps(architecture, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

manifest_path = Path("app/update.manifest.json")
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": version,
    "release": version,
    "build": build,
    "generated_at": now,
    "updated_at": now,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
})

md_entry = (
    "## 1.7.30.20 — Agenda diária como entrada do Administrador\n\n"
    "- suprime completamente o item `Painel` da navegação do perfil Administrador;\n"
    "- direciona o login do Administrador para a visão diária da Agenda;\n"
    "- redireciona a rota legada `painel` para a Agenda diária, evitando acesso residual por URL;\n"
    "- preserva a navegação e os painéis dos demais cargos;\n"
    "- não altera banco de dados nem schema.\n\n"
)
txt_entry = (
    "Prontoo 1.7.30.20 — Agenda diária como entrada do Administrador\n\n"
    "- Remove o item Painel do perfil Administrador.\n"
    "- Abre a visão diária da Agenda após o login do Administrador.\n"
    "- Redireciona acessos residuais à rota Painel para a Agenda diária.\n"
    "- Preserva os demais cargos, o banco de dados e o schema.\n\n"
)
changelog = Path("CHANGELOG.md")
changelog_text = changelog.read_text(encoding="utf-8")
marker = "# Histórico de versões\n\n"
if changelog_text.count(marker) != 1:
    raise RuntimeError("Cabeçalho canônico do CHANGELOG não encontrado")
changelog.write_text(
    changelog_text.replace(marker, marker + md_entry, 1),
    encoding="utf-8",
)
legacy = Path("ChangeLog.txt")
legacy.write_text(txt_entry + legacy.read_text(encoding="utf-8"), encoding="utf-8")

for workflow in ("architecture.yml", "documentation.yml"):
    content = subprocess.check_output([
        "git",
        "show",
        f"68fca3a7eff5bfc48eadcf4e2809b6bf047be8d2:.github/workflows/{workflow}",
    ])
    Path(f".github/workflows/{workflow}").write_bytes(content)
Path(".github/workflows/agent-administrator-agenda.yml").unlink(missing_ok=True)
Path("tools/administrator-agenda-publish.py").unlink()

files = {}
total = 0
excluded_roots = (".git/", "ssd/", "vendor/", "node_modules/")
for file in Path(".").rglob("*"):
    if not file.is_file() or file.is_symlink():
        continue
    relative = file.as_posix()
    if relative == "app/update.manifest.json" or relative.startswith(excluded_roots):
        continue
    data = file.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["files"] = dict(sorted(files.items()))
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["updated_at"] = now
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)
