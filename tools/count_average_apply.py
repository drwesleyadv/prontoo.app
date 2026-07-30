from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
NOW = datetime.now(timezone.utc).replace(microsecond=0)
NOW_ISO = NOW.isoformat()
NOW_UNIX = int(NOW.timestamp())
BUILD = "1.7.30.13-shared-renderer-public-stats-zero-day-averages"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one occurrence, found {count}")
    return content.replace(old, new, 1)


admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)
admin = replace_once(
    admin,
    '''    $recentValue = admin_metric_recent_average($loadSeries, $recentPoints);
    $loadNonZero = array_values(
        array_filter($loadValues, static fn($v) => (float) $v > 0),
    );
    $overallValue = count($loadNonZero)
        ? array_sum($loadNonZero) / count($loadNonZero)
        : 0.0;
    $middleValues = array_slice($loadValues, -$middlePoints);
    $middleNonZero = array_values(
        array_filter($middleValues, static fn($v) => (float) $v > 0),
    );
    $middleValue = count($middleNonZero)
        ? array_sum($middleNonZero) / count($middleNonZero)
        : 0.0;''',
    '''    $recentValues = array_slice($loadValues, -$recentPoints);
    $recentValue = $valueType === "count"
        ? ($recentValues
            ? array_sum($recentValues) / count($recentValues)
            : 0.0)
        : admin_metric_recent_average($loadSeries, $recentPoints);
    $overallAverageValues = $valueType === "count"
        ? $loadValues
        : array_values(
            array_filter($loadValues, static fn($v) => (float) $v > 0),
        );
    $overallValue = count($overallAverageValues)
        ? array_sum($overallAverageValues) / count($overallAverageValues)
        : 0.0;
    $middleValues = array_slice($loadValues, -$middlePoints);
    $middleAverageValues = $valueType === "count"
        ? $middleValues
        : array_values(
            array_filter($middleValues, static fn($v) => (float) $v > 0),
        );
    $middleValue = count($middleAverageValues)
        ? array_sum($middleAverageValues) / count($middleAverageValues)
        : 0.0;''',
    "count averages retain zero days",
)
write(admin_path, admin)

check_path = "tools/architecture-check.php"
check = read(check_path)
check = replace_once(
    check,
    '''        '"value_type" => "count"',
        '$averageResponseMs',''',
    '''        '"value_type" => "count"',
        '$recentValue = $valueType === "count"',
        '$overallAverageValues = $valueType === "count"',
        '$middleAverageValues = $valueType === "count"',
        '$averageResponseMs',''',
    "count average architecture tokens",
)
write(check_path, check)

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW_ISO,
    "updated_at": NOW_ISO,
    "build": BUILD,
    "notes": "Velocidade e Leitura e gravação usam o mesmo renderer; /stats publica somente o card de desempenho; médias de contagem preservam dias com zero requisições.",
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

update_path = ROOT / "app/update.manifest.json"
update = json.loads(update_path.read_text(encoding="utf-8"))
update.update({
    "build": BUILD,
    "generated_at": NOW_ISO,
    "updated_at": NOW_ISO,
    "notes": "Renderer dual-area único, /stats público somente leitura e médias diárias de contagem incluindo dias zerados.",
})
update_path.write_text(json.dumps(update, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
    "generated_at": NOW_ISO,
    "updated_at": NOW_ISO,
    "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_two_charts_one_shared_renderer_count_averages_include_zero_days_and_public_read_only_stats_card_without_authenticated_context",
})
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

changelog_path = "CHANGELOG.md"
changelog = read(changelog_path)
changelog = replace_once(
    changelog,
    '''- disponibiliza `https://prontoo.app/stats` como página pública GET com exclusivamente o card de desempenho, sem contexto autenticado, POST ou criação de schema;
- mantém banco e schema inalterados.''',
    '''- disponibiliza `https://prontoo.app/stats` como página pública GET com exclusivamente o card de desempenho, sem contexto autenticado, POST ou criação de schema;
- inclui dias com zero requisições nas médias diárias de 7 e 30 dias;
- mantém banco e schema inalterados.''',
    "markdown count average note",
)
write(changelog_path, changelog)

text_path = "ChangeLog.txt"
text = read(text_path)
text = replace_once(
    text,
    '''- Disponibiliza prontoo.app/stats com somente o card de desempenho, em GET público e sem contexto autenticado.
- Mantém banco e schema inalterados.''',
    '''- Disponibiliza prontoo.app/stats com somente o card de desempenho, em GET público e sem contexto autenticado.
- Inclui dias sem requisições nas médias diárias de 7 e 30 dias.
- Mantém banco e schema inalterados.''',
    "text count average note",
)
write(text_path, text)

print(json.dumps({"ok": True, "build": BUILD}, ensure_ascii=False))
