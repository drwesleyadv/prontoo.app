from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one occurrence, found {count}")
    return text.replace(old, new, 1)


css_path = "public/assets/design-system.css"
css = read(css_path)
marker = "body.stats-public #conteudo{width:95vw!important;max-width:none!important;margin:0 auto!important;padding:0!important;}"
if marker not in css:
    css = css.rstrip() + "\n\nbody.stats-public{margin:0!important;padding:0!important;}\nbody.stats-public #conteudo{width:95vw!important;max-width:none!important;margin:0 auto!important;padding:0!important;}\nbody.stats-public .admin-performance-card{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important;}\nbody.stats-public .admin-performance-card>.section-head{margin:0!important;padding:0!important;border:0!important;}\nbody.stats-public .global-performance-charts{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;gap:0!important;}\nbody.stats-public .global-performance-charts>.metric-area-chart{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important;}\nbody.stats-public .global-performance-charts>.metric-area-chart svg{display:block;width:100%!important;max-width:none!important;}\n"
write(css_path, css)

version_path = "version.json"
version = json.loads(read(version_path))
now = datetime.now(timezone.utc)
version.update(
    {
        "version": "1.7.30.14",
        "release": "1.7.30.14",
        "generated_at_unix": int(now.timestamp()),
        "generated_at": now.isoformat(),
        "updated_at": now.isoformat(),
        "build": "1.7.30.14-public-stats-wide-borderless-layout",
        "previous_version": "1.7.30.13",
        "notes": "A página pública /stats remove bordas e espaçamentos do card e dos gráficos e ocupa 95% da largura da viewport.",
    }
)
write(version_path, json.dumps(version, ensure_ascii=False, indent=4) + "\n")

for path in ["app/prontoo.php", "br/index.php"]:
    text = read(path)
    text = text.replace("1.7.30.13", "1.7.30.14")
    text = text.replace(
        "1.7.30.13-shared-renderer-public-stats-zero-day-averages",
        "1.7.30.14-public-stats-wide-borderless-layout",
    )
    write(path, text)

manifest_path = "app/architecture.manifest.json"
manifest = json.loads(read(manifest_path))
manifest["version"] = "1.7.30.14"
manifest["release"] = "1.7.30.14"
manifest["build"] = "1.7.30.14-public-stats-wide-borderless-layout"
manifest["public_stats_layout_policy"] = "viewport_95_percent_borderless_zero_outer_spacing_public_only"
manifest["updated_at"] = now.isoformat()
write(manifest_path, json.dumps(manifest, ensure_ascii=False, indent=4) + "\n")

change = """## 1.7.30.14 — Stats amplo e sem bordas

- remove bordas, raios, sombras e espaçamentos externos do card e dos gráficos somente em `/stats`;
- faz o conteúdo ocupar 95% da largura da viewport;
- preserva o layout do Painel do Desenvolvedor;
- mantém banco e schema inalterados.

"""
changelog = read("CHANGELOG.md")
if "## 1.7.30.14" not in changelog:
    changelog = replace_once(changelog, "# Histórico de versões\n\n", "# Histórico de versões\n\n" + change, "CHANGELOG header")
write("CHANGELOG.md", changelog)

legacy = """Prontoo 1.7.30.14 — Stats amplo e sem bordas

- Remove bordas, sombras e espaçamentos externos do card e dos gráficos exclusivamente em prontoo.app/stats.
- Faz o conteúdo ocupar 95% da largura da tela.
- Preserva o Painel do Desenvolvedor e mantém banco e schema inalterados.

"""
legacy_log = read("ChangeLog.txt")
if not legacy_log.startswith("Prontoo 1.7.30.14"):
    legacy_log = legacy + legacy_log
write("ChangeLog.txt", legacy_log)

check_path = "tools/architecture-check.php"
check = read(check_path)
css_contract = """
$statsWideLayoutCss = (string) file_get_contents($root . '/public/assets/design-system.css');
$statsWideLayoutFailures = [];
foreach ([
    'body.stats-public #conteudo{width:95vw!important;max-width:none!important;margin:0 auto!important;padding:0!important;}',
    'body.stats-public .admin-performance-card{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important;}',
    'body.stats-public .global-performance-charts{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;gap:0!important;}',
    'body.stats-public .global-performance-charts>.metric-area-chart{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important;}',
] as $statsWideLayoutToken) {
    if (!str_contains($statsWideLayoutCss, $statsWideLayoutToken)) {
        $statsWideLayoutFailures[] = $statsWideLayoutToken;
    }
}
if ($statsWideLayoutFailures !== []) {
    fwrite(STDERR, "Stats wide layout contract failed: " . implode(', ', $statsWideLayoutFailures) . PHP_EOL);
    exit(1);
}
"""
if "$statsWideLayoutCss" not in check:
    position = check.rfind("$result = [")
    if position < 0:
        raise RuntimeError("architecture final result anchor missing")
    check = check[:position] + css_contract + "\n" + check[position:]
write(check_path, check)

print(json.dumps({"ok": True, "version": "1.7.30.14", "run": 2}, ensure_ascii=False))
