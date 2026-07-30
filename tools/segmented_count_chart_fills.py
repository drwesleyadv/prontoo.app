from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

VERSION = "1.7.30.17"
PREVIOUS_VERSION = "1.7.30.16"
BUILD = "1.7.30.17-segmented-count-chart-fills"


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one occurrence, found {count}")
    return content.replace(old, new, 1)


def write_json(path: str, payload: dict) -> None:
    write(path, json.dumps(payload, ensure_ascii=False, indent=4) + "\n")


now = datetime.now(timezone.utc)
now_iso = now.isoformat()
now_unix = int(now.timestamp())

admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)
fill_anchor = '''    $loadPoints = $makePoints($loadSeries);
    $responsePoints = $makePoints($responseSeries);'''
segmented_closure = '''    $segmentedCountAreas = static function (
        array $primaryPoints,
        array $secondaryPoints,
        float $baseline,
    ): array {
        $empty = [
            "primary" => "",
            "secondary" => "",
            "intersection" => "",
        ];
        $count = min(count($primaryPoints), count($secondaryPoints));
        if ($count < 2) {
            return $empty;
        }
        $samples = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $primaryStart = $primaryPoints[$i];
            $primaryEnd = $primaryPoints[$i + 1];
            $secondaryStart = $secondaryPoints[$i];
            $secondaryEnd = $secondaryPoints[$i + 1];
            if (
                abs((float) $primaryStart[0] - (float) $secondaryStart[0]) > 0.01 ||
                abs((float) $primaryEnd[0] - (float) $secondaryEnd[0]) > 0.01
            ) {
                return $empty;
            }
            if ($i === 0) {
                $samples[] = [
                    (float) $primaryStart[0],
                    (float) $primaryStart[1],
                    (float) $secondaryStart[1],
                ];
            }
            $startDelta =
                (float) $primaryStart[1] - (float) $secondaryStart[1];
            $endDelta =
                (float) $primaryEnd[1] - (float) $secondaryEnd[1];
            if (
                ($startDelta < 0 && $endDelta > 0) ||
                ($startDelta > 0 && $endDelta < 0)
            ) {
                $ratio = $startDelta / ($startDelta - $endDelta);
                $crossX =
                    (float) $primaryStart[0] +
                    ((float) $primaryEnd[0] - (float) $primaryStart[0]) * $ratio;
                $crossPrimaryY =
                    (float) $primaryStart[1] +
                    ((float) $primaryEnd[1] - (float) $primaryStart[1]) * $ratio;
                $crossSecondaryY =
                    (float) $secondaryStart[1] +
                    ((float) $secondaryEnd[1] - (float) $secondaryStart[1]) * $ratio;
                $crossY = ($crossPrimaryY + $crossSecondaryY) / 2;
                $samples[] = [$crossX, $crossY, $crossY];
            }
            $samples[] = [
                (float) $primaryEnd[0],
                (float) $primaryEnd[1],
                (float) $secondaryEnd[1],
            ];
        }
        if (count($samples) < 2) {
            return $empty;
        }
        $coord = static fn(float $value): string => (string) round($value, 2);
        $intersectionPath = "";
        foreach ($samples as $index => $sample) {
            $intersectionY = max((float) $sample[1], (float) $sample[2]);
            $intersectionPath .=
                ($index === 0 ? "M" : "L") .
                $coord((float) $sample[0]) .
                " " .
                $coord($intersectionY) .
                " ";
        }
        $firstSample = $samples[0];
        $lastSample = $samples[count($samples) - 1];
        $intersectionPath .=
            "L " .
            $coord((float) $lastSample[0]) .
            " " .
            $coord($baseline) .
            " L " .
            $coord((float) $firstSample[0]) .
            " " .
            $coord($baseline) .
            " Z";
        $primaryPath = "";
        $secondaryPath = "";
        for ($i = 0; $i < count($samples) - 1; $i++) {
            $start = $samples[$i];
            $end = $samples[$i + 1];
            $midDelta =
                (((float) $start[1] - (float) $start[2]) +
                    ((float) $end[1] - (float) $end[2])) /
                2;
            if (abs($midDelta) < 0.0001) {
                continue;
            }
            $segment =
                "M" .
                $coord((float) $start[0]) .
                " " .
                $coord($midDelta < 0 ? (float) $start[1] : (float) $start[2]) .
                " L " .
                $coord((float) $end[0]) .
                " " .
                $coord($midDelta < 0 ? (float) $end[1] : (float) $end[2]) .
                " L " .
                $coord((float) $end[0]) .
                " " .
                $coord($midDelta < 0 ? (float) $end[2] : (float) $end[1]) .
                " L " .
                $coord((float) $start[0]) .
                " " .
                $coord($midDelta < 0 ? (float) $start[2] : (float) $start[1]) .
                " Z ";
            if ($midDelta < 0) {
                $primaryPath .= $segment;
            } else {
                $secondaryPath .= $segment;
            }
        }
        return [
            "primary" => trim($primaryPath),
            "secondary" => trim($secondaryPath),
            "intersection" => trim($intersectionPath),
        ];
    };
'''
admin = replace_once(
    admin,
    fill_anchor,
    segmented_closure + fill_anchor,
    "segmented count area closure",
)
old_area_block = '''    $loadArea =
        $loadFill !== ""
            ? '<path d="' . e($loadFill) . '" class="metric-chart-fill-load"/>'
            : "";
    $responseArea =
        $responseFill !== ""
            ? '<path d="' .
                e($responseFill) .
                '" class="metric-chart-fill-response"/>'
            : "";
    $fillAreas =
        $valueType === "count"
            ? $responseArea . $loadArea
            : $loadArea . $responseArea;'''
new_area_block = '''    if ($valueType === "count") {
        $segmentedAreas = $segmentedCountAreas(
            $loadPoints,
            $responsePoints,
            $baseline,
        );
        $intersectionArea =
            $segmentedAreas["intersection"] !== ""
                ? '<path d="' .
                    e($segmentedAreas["intersection"]) .
                    '" class="metric-chart-fill-intersection"/>'
                : "";
        $loadExclusiveArea =
            $segmentedAreas["primary"] !== ""
                ? '<path d="' .
                    e($segmentedAreas["primary"]) .
                    '" class="metric-chart-fill-load-exclusive"/>'
                : "";
        $responseExclusiveArea =
            $segmentedAreas["secondary"] !== ""
                ? '<path d="' .
                    e($segmentedAreas["secondary"]) .
                    '" class="metric-chart-fill-response-exclusive"/>'
                : "";
        $fillAreas =
            $intersectionArea . $loadExclusiveArea . $responseExclusiveArea;
    } else {
        $loadArea =
            $loadFill !== ""
                ? '<path d="' .
                    e($loadFill) .
                    '" class="metric-chart-fill-load"/>'
                : "";
        $responseArea =
            $responseFill !== ""
                ? '<path d="' .
                    e($responseFill) .
                    '" class="metric-chart-fill-response"/>'
                : "";
        $fillAreas = $loadArea . $responseArea;
    }'''
admin = replace_once(admin, old_area_block, new_area_block, "segmented count area renderer")
write(admin_path, admin)

css_path = "public/assets/design-system.css"
css = read(css_path)
old_css = '''.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:.42}'''
new_css = '''.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load-exclusive{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response-exclusive{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-intersection{fill:color-mix(in srgb,var(--md-sys-color-outline-variant) 42%,var(--md-sys-color-surface-container-lowest) 58%);opacity:1}'''
css = replace_once(css, old_css, new_css, "segmented count css")
write(css_path, css)

architecture_check_path = "tools/architecture-check.php"
architecture_check = read(architecture_check_path)
architecture_check = replace_once(
    architecture_check,
    '''        '"value_type" => "count"',
        '$recentValue = $valueType === "count"',''',
    '''        '"value_type" => "count"',
        '$segmentedCountAreas = static function (',
        '"intersection" => trim($intersectionPath)',
        'class="metric-chart-fill-load-exclusive"',
        'class="metric-chart-fill-response-exclusive"',
        'class="metric-chart-fill-intersection"',
        '$intersectionArea . $loadExclusiveArea . $responseExclusiveArea',
        '$recentValue = $valueType === "count"',''',
    "architecture admin tokens",
)
architecture_check = replace_once(
    architecture_check,
    '''        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:.42}',''',
    '''        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load-exclusive{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response-exclusive{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-intersection{fill:color-mix(in srgb,var(--md-sys-color-outline-variant) 42%,var(--md-sys-color-surface-container-lowest) 58%);opacity:1}',''',
    "architecture css tokens",
)
architecture_check = replace_once(
    architecture_check,
    '''    'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
] as $outlineToken) {''',
    '''    'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load{',
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{',
    '? $responseArea . $loadArea',
] as $outlineToken) {''',
    "architecture obsolete tokens",
)
write(architecture_check_path, architecture_check)

changelog = read("CHANGELOG.md")
changelog_entry = '''## 1.7.30.17 — Áreas segmentadas em Leitura e gravação

- separa geometricamente as áreas exclusivas de Requisições e Registros;
- mantém cada área exclusiva com exatamente a mesma cor e opacidade da respectiva linha;
- trata a interseção como uma terceira área neutra, sem mistura cromática entre as séries;
- preserva linhas, pontos, eixos, tooltips, gráfico Velocidade, métricas, banco e schema.

'''
changelog = replace_once(changelog, "# Histórico de versões\n\n", "# Histórico de versões\n\n" + changelog_entry, "markdown changelog")
write("CHANGELOG.md", changelog)

text_changelog = read("ChangeLog.txt")
text_entry = '''Prontoo 1.7.30.17 — Áreas segmentadas em Leitura e gravação

- Requisições e Registros passam a ter preenchimentos exclusivos com o mesmo tom de suas linhas.
- A área compartilhada recebe tratamento neutro próprio, sem sobreposição cromática.
- Velocidade, métricas, banco e schema permanecem inalterados.

'''
write("ChangeLog.txt", text_entry + text_changelog)

for path, old, new in [
    ("app/prontoo.php", 'const PRONTOO_VERSION_FALLBACK = "1.7.30.16";', 'const PRONTOO_VERSION_FALLBACK = "1.7.30.17";'),
    ("br/index.php", 'const BR_LANDING_VERSION_FALLBACK = "1.7.30.16";', 'const BR_LANDING_VERSION_FALLBACK = "1.7.30.17";'),
]:
    content = read(path)
    content = replace_once(content, old, new, f"version fallback {path}")
    write(path, content)

version_path = "version.json"
version_payload = json.loads(read(version_path))
version_payload.update(
    {
        "version": VERSION,
        "release": VERSION,
        "generated_at_unix": now_unix,
        "generated_at": now_iso,
        "updated_at": now_iso,
        "build": BUILD,
        "previous_version": PREVIOUS_VERSION,
        "notes": "No gráfico Leitura e gravação, Requisições e Registros usam áreas exclusivas com o mesmo tom de suas linhas e a interseção recebe preenchimento neutro próprio.",
        "deployment_sync_id": "github-segmented-count-chart-fills-1-7-30-17",
        "deployment_sync_requested_at": now_iso,
        "rewrite_scope": "developer_dashboard_segmented_request_record_fills_neutral_intersection",
    }
)
write_json(version_path, version_payload)

update_manifest_path = "app/update.manifest.json"
update_manifest = json.loads(read(update_manifest_path))
update_manifest.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "generated_at": now_iso,
        "updated_at": now_iso,
        "previous_version": PREVIOUS_VERSION,
        "notes": "Leitura e gravação passa a usar áreas exclusivas para Requisições e Registros e uma área neutra para a interseção.",
        "deployment_sync_id": "github-segmented-count-chart-fills-1-7-30-17",
        "deployment_sync_requested_at": now_iso,
        "rewrite_scope": "developer_dashboard_segmented_request_record_fills_neutral_intersection",
    }
)
write_json(update_manifest_path, update_manifest)

architecture_manifest_path = "app/architecture.manifest.json"
architecture_manifest = json.loads(read(architecture_manifest_path))
architecture_manifest.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "generated_at": now_iso,
        "updated_at": now_iso,
        "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_two_charts_one_shared_renderer_count_averages_include_zero_days_non_overlapping_exact_tone_request_and_record_exclusive_fills_with_neutral_intersection_and_public_read_only_status_card_without_authenticated_context",
    }
)
write_json(architecture_manifest_path, architecture_manifest)
