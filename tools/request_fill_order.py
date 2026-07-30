from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

BUILD = "1.7.30.16-request-fill-exact-line-tone-layer-order"


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one occurrence, found {count}")
    return content.replace(old, new, 1)


admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)
admin = replace_once(
    admin,
    '''        $overallCompact .
        ".";
    return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-metric-value-type="' .''',
    '''        $overallCompact .
        ".";
    $loadArea =
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
            : $loadArea . $responseArea;
    return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-metric-value-type="' .''',
    "area composition variables",
)
admin = replace_once(
    admin,
    '''        $grid .
        "</g>" .
        ($loadFill !== ""
            ? '<path d="' . e($loadFill) . '" class="metric-chart-fill-load"/>'
            : "") .
        ($responseFill !== ""
            ? '<path d="' .
                e($responseFill) .
                '" class="metric-chart-fill-response"/>'
            : "") .
        ($loadD !== ""''',
    '''        $grid .
        "</g>" .
        $fillAreas .
        ($loadD !== ""''',
    "shared area output",
)
write(admin_path, admin)

architecture_path = "tools/architecture-check.php"
architecture = read(architecture_path)
architecture = replace_once(
    architecture,
    '''        '"value_type" => "count"',
        '$recentValue = $valueType === "count"',''',
    '''        '"value_type" => "count"',
        '? $responseArea . $loadArea',
        ': $loadArea . $responseArea;',
        '$recentValue = $valueType === "count"',''',
    "architecture count area order",
)
write(architecture_path, architecture)

changelog_path = "CHANGELOG.md"
changelog = read(changelog_path)
changelog = replace_once(
    changelog,
    '''- remove a diluição causada pela opacidade parcial apenas no preenchimento de Requisições;
- preserva Registros, Velocidade, métricas, banco e schema.''',
    '''- remove a diluição causada pela opacidade parcial apenas no preenchimento de Requisições;
- desenha Registros primeiro e Requisições depois, mantendo o tom exato da área visível de Requisições;
- mantém a fórmula cromática de Registros e preserva Velocidade, métricas, banco e schema.''',
    "markdown changelog",
)
write(changelog_path, changelog)

plain_path = "ChangeLog.txt"
plain = read(plain_path)
plain = replace_once(
    plain,
    '''- A área inferior de Requisições passa a ter exatamente o mesmo tom visual da linha correspondente.
- Registros, Velocidade, métricas, banco e schema permanecem inalterados.''',
    '''- A área inferior de Requisições passa a ter exatamente o mesmo tom visual da linha correspondente.
- Registros é desenhado primeiro e Requisições depois para preservar o tom visual solicitado.
- A fórmula cromática de Registros, Velocidade, métricas, banco e schema permanece inalterada.''',
    "plain changelog",
)
write(plain_path, plain)

now = datetime.now(timezone.utc).isoformat()
version_path = "version.json"
version = json.loads(read(version_path))
version["generated_at_unix"] = int(datetime.now(timezone.utc).timestamp())
version["generated_at"] = now
version["updated_at"] = now
version["build"] = BUILD
version["notes"] = "No gráfico Leitura e gravação, Registros é desenhado primeiro e a área opaca de Requisições é desenhada depois com exatamente o tom de sua linha."
version["rewrite_scope"] = "developer_dashboard_request_fill_exact_line_tone_and_count_layer_order"
write(version_path, json.dumps(version, ensure_ascii=False, indent=4) + "\n")

manifest_path = "app/architecture.manifest.json"
manifest = json.loads(read(manifest_path))
manifest["generated_at"] = now
manifest["updated_at"] = now
manifest["build"] = BUILD
manifest["developer_dashboard_observability_policy"] = "single_global_dashboard_four_kpis_two_charts_one_shared_renderer_count_averages_include_zero_days_records_painted_before_opaque_request_fill_exactly_matching_line_tone_and_public_read_only_status_card_without_authenticated_context"
write(manifest_path, json.dumps(manifest, ensure_ascii=False, indent=4) + "\n")

update_path = "app/update.manifest.json"
update = json.loads(read(update_path))
update["generated_at"] = now
update["build"] = BUILD
write(update_path, json.dumps(update, ensure_ascii=False, indent=4) + "\n")
