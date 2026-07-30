from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import time

BASE_COMMIT = "2343723e2ee5827b0e86fccf1a2fcb958adb038f"
VERSION = "1.7.30.22"
PREVIOUS = "1.7.30.21"
BUILD = "1.7.30.22-read-write-layered-mountain-fills"
DEPLOYMENT_SYNC_ID = "github-read-write-layered-mountain-fills-1-7-30-22"
NOW = datetime.now(timezone.utc).isoformat()
NOW_UNIX = int(time.time())


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"Substituição não determinística em {path}: {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


replace_once(
    "app/Admin/AdminPages.php",
    '''    $fill = static function (
        string $d,
        array $points,
        float $baseline,
    ): string {

        if (!$points || $d === "") {
            return "";
        }
        $first = $points[0];
        $last = $points[count($points) - 1];
        return $d .
            " L " .
            $last[0] .
            " " .
            round($baseline, 2) .
            " L " .
            $first[0] .
            " " .
            round($baseline, 2) .
            " Z";
    };
    $loadPoints = $makePoints($loadSeries);
    $responsePoints = $makePoints($responseSeries);
    $loadD = $path($loadPoints);
    $responseD = $path($responsePoints);
    $loadFill = $fill($loadD, $loadPoints, $baseline);
    $responseFill = $fill($responseD, $responsePoints, $baseline);
''',
    '''    $fill = static function (
        string $d,
        array $points,
        float $baseline,
    ): string {

        if (!$points || $d === "") {
            return "";
        }
        $first = $points[0];
        $last = $points[count($points) - 1];
        return $d .
            " L " .
            $last[0] .
            " " .
            round($baseline, 2) .
            " L " .
            $first[0] .
            " " .
            round($baseline, 2) .
            " Z";
    };
    $betweenFill = static function (
        array $upperPoints,
        array $lowerPoints,
    ): string {

        if (
            !$upperPoints ||
            !$lowerPoints ||
            count($upperPoints) !== count($lowerPoints)
        ) {
            return "";
        }
        $d = "";
        foreach ($upperPoints as $i => $point) {
            $d .=
                ($i === 0 ? "M" : "L") .
                $point[0] .
                " " .
                $point[1] .
                " ";
        }
        foreach (array_reverse($lowerPoints) as $point) {
            $d .= "L" . $point[0] . " " . $point[1] . " ";
        }
        return trim($d) . " Z";
    };
    $loadPoints = $makePoints($loadSeries);
    $responsePoints = $makePoints($responseSeries);
    $loadD = $path($loadPoints);
    $responseD = $path($responsePoints);
    $loadFill =
        $valueType === "count"
            ? $betweenFill($loadPoints, $responsePoints)
            : $fill($loadD, $loadPoints, $baseline);
    $responseFill = $fill($responseD, $responsePoints, $baseline);
''',
)
replace_once(
    "app/Admin/AdminPages.php",
    '''    $fillAreas = $loadArea . $responseArea;
''',
    '''    $fillAreas =
        $valueType === "count"
            ? $responseArea . $loadArea
            : $loadArea . $responseArea;
''',
)

replace_once(
    "tools/architecture-check.php",
    '''        'data-metric-value-type="',
        '$fillAreas = $loadArea . $responseArea;',
        'admin_metric_dual_area_chart("Velocidade", $load, $response, "speed")',
''',
    '''        'data-metric-value-type="',
        '$betweenFill = static function (',
        '? $betweenFill($loadPoints, $responsePoints)',
        '$fillAreas =',
        '? $responseArea . $loadArea',
        ': $loadArea . $responseArea;',
        'admin_metric_dual_area_chart("Velocidade", $load, $response, "speed")',
''',
)
replace_once(
    "tools/architecture-check.php",
    '''    'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
    '? $responseArea . $loadArea',
    '$segmentedCountAreas = static function (',
''',
    '''    'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
    '$segmentedCountAreas = static function (',
''',
)
replace_once(
    "tools/architecture-check.php",
    "dual-area-single-renderer-count-opaque-mountain-fills-data-specific-labels-one-row",
    "dual-area-single-renderer-count-opaque-layered-mountain-fills-data-specific-labels-one-row",
)

replace_once(
    "app/prontoo.php",
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.21";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.22";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PREVIOUS_VERSION = "1.7.30.20";',
    'const PRONTOO_PREVIOUS_VERSION = "1.7.30.21";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-single-renderer-count-opaque-mountain-fills-data-specific-labels-one-row";',
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-single-renderer-count-opaque-layered-mountain-fills-data-specific-labels-one-row";',
)
replace_once(
    "br/index.php",
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.21";',
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.22";',
)

version_path = Path("version.json")
metadata = json.loads(version_path.read_text(encoding="utf-8"))
metadata.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "previous_version": PREVIOUS,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
    "functional_equivalence_policy": "preserves_metrics_lines_labels_colors_and_shared_renderer_while_records_fill_to_baseline_and_requests_fill_only_between_series",
    "notes": "Leitura e gravação passa a usar duas camadas geométricas opacas: Registros da linha até a base e Requisições apenas no intervalo entre as duas linhas.",
    "deployment_sync_id": DEPLOYMENT_SYNC_ID,
    "deployment_sync_requested_at": NOW,
    "rewrite_scope": "developer_dashboard_read_write_layered_count_areas",
})
version_path.write_text(
    json.dumps(metadata, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

architecture_path = Path("app/architecture.manifest.json")
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "generated_at": NOW,
    "updated_at": NOW,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_two_charts_one_shared_renderer_count_chart_exact_line_color_opaque_layered_mountain_fills_data_specific_labels_and_public_read_only_status_card_without_authenticated_context",
    "developer_dashboard_count_area_policy": "read_write_records_fill_from_baseline_and_requests_fill_only_between_record_and_request_lines_with_exact_respective_line_colors_and_opacity_one",
})
architecture_path.write_text(
    json.dumps(architecture, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

manifest_path = Path("app/update.manifest.json")
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "previous_version": PREVIOUS,
    "deployment_sync_id": DEPLOYMENT_SYNC_ID,
    "generated_at": NOW,
    "updated_at": NOW,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
})

md_entry = (
    "## 1.7.30.22 — Camadas corretas em Leitura e gravação\n\n"
    "- mantém as cores e espessuras atuais das linhas de Requisições e Registros;\n"
    "- preenche a faixa inferior, da base até Registros, com o verde claro opaco de Registros;\n"
    "- preenche somente o intervalo entre Registros e Requisições com o verde escuro opaco de Requisições;\n"
    "- preserva o renderer compartilhado e o comportamento do gráfico Velocidade;\n"
    "- não altera banco de dados nem schema.\n\n"
)
txt_entry = (
    "Prontoo 1.7.30.22 — Camadas corretas em Leitura e gravação\n\n"
    "- Registros preenche da sua linha até a base com verde claro opaco.\n"
    "- Requisições preenche apenas a faixa entre as duas linhas com verde escuro opaco.\n"
    "- Mantém as linhas, Velocidade, banco de dados e schema.\n\n"
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

Path(".github/workflows/architecture.yml").write_bytes(
    subprocess.check_output([
        "git",
        "show",
        BASE_COMMIT + ":.github/workflows/architecture.yml",
    ])
)
Path(".github/read-write-layered-areas.trigger").unlink(missing_ok=True)
Path(".github/read_write_layered_areas_publish.py").unlink(missing_ok=True)

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
manifest["updated_at"] = NOW
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)
