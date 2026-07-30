from pathlib import Path
import json
import re
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.8"
PREVIOUS = "1.7.30.7"
BUILD = "1.7.30.8-developer-telemetry-dashboard"
NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
NOW_UNIX = int(time.time())


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(path: str, old: str, new: str) -> None:
    content = read(path)
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{path}: expected one occurrence, found {count}: {old[:80]}")
    write(path, content.replace(old, new, 1))


replace_once(
    "app/prontoo.php",
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.7";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.8";',
)
replace_once(
    "app/prontoo.php",
    '    "admin_performance" => ["label" => "Performance", "icon" => "speed"],\n',
    '    "admin_performance" => ["label" => "Performance", "icon" => "speed"],\n'
    '    "admin_telemetry" => ["label" => "Telemetria", "icon" => "monitoring"],\n',
)
replace_once(
    "app/Runtime/Runner.php",
    '        "admin_performance",\n',
    '        "admin_performance",\n        "admin_telemetry",\n',
)
replace_once(
    "app/Support/ModuleLoader.php",
    "        'admin_health' => $admin, 'admin_performance' => $admin,\n",
    "        'admin_health' => $admin, 'admin_performance' => $admin, 'admin_telemetry' => $admin,\n",
)

admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)
compact_old = """function admin_metric_value_compact(float $value, string $mode): string
{
    return admin_metric_duration_label($value, true);
}"""
compact_new = """function admin_metric_value_compact(float $value, string $mode): string
{
    if ($mode === "ms") {
        return admin_metric_duration_label($value, true);
    }
    return number_format($value, $value === floor($value) ? 0 : 1, ",", ".");
}"""
if admin.count(compact_old) != 1:
    raise RuntimeError("admin metric compact formatter boundary not found")
admin = admin.replace(compact_old, compact_new, 1)
marker = "function page_admin_performance(): void\n"
if admin.count(marker) != 1:
    raise RuntimeError("admin performance page boundary not found")
admin = admin.replace(marker, 'function admin_telemetry_window_hours(): int\n{\n    $hours = (int) ($_GET["hours"] ?? 24);\n    return in_array($hours, [6, 24, 72, 168], true) ? $hours : 24;\n}\nfunction admin_telemetry_window_label(int $hours): string\n{\n    return [\n        6 => "6 horas",\n        24 => "24 horas",\n        72 => "3 dias",\n        168 => "7 dias",\n    ][$hours] ?? "24 horas";\n}\nfunction admin_telemetry_window_html(int $hours): string\n{\n    $items = "";\n    foreach ([6 => "6h", 24 => "24h", 72 => "3 dias", 168 => "7 dias"] as $value => $label) {\n        $items .=\n            \'<a class="\' .\n            ($hours === $value ? "primary" : "secondary") .\n            \' small cmdlike" href="\' .\n            href("admin_telemetry", ["hours" => $value]) .\n            \'">\' .\n            e($label) .\n            "</a>";\n    }\n    return \'<nav class="cmd-bar admin-telemetry-window" aria-label="Janela da telemetria">\' .\n        $items .\n        "</nav>";\n}\nfunction admin_telemetry_bucket_seconds(int $hours): int\n{\n    if ($hours <= 6) {\n        return 300;\n    }\n    if ($hours <= 24) {\n        return 900;\n    }\n    if ($hours <= 72) {\n        return 3600;\n    }\n    return 10800;\n}\nfunction admin_telemetry_time_series(int $hours): array\n{\n    $hours = max(1, min(168, $hours));\n    $bucketSeconds = admin_telemetry_bucket_seconds($hours);\n    $tz = telemetry_cuiaba_tz();\n    $now = new DateTimeImmutable("now", $tz);\n    $nowTs = $now->getTimestamp();\n    $startTs = $nowTs - $hours * 3600;\n    $bucketCount = max(2, (int) ceil(($nowTs - $startTs) / $bucketSeconds) + 1);\n    $series = [\n        "response" => [],\n        "database" => [],\n        "requests" => [],\n        "errors" => [],\n    ];\n    for ($i = 0; $i < $bucketCount; $i++) {\n        $ts = $startTs + $i * $bucketSeconds;\n        $dt = new DateTimeImmutable("@" . $ts)->setTimezone($tz);\n        $label = $hours <= 24 ? $dt->format("H:i") : $dt->format("d/m H:i");\n        $tooltip = $dt->format("d/m/Y H:i");\n        foreach (array_keys($series) as $key) {\n            $series[$key][$i] = [\n                "ts" => $ts,\n                "label" => $label,\n                "tooltip" => $tooltip,\n                "value" => 0.0,\n                "sum" => 0.0,\n                "samples" => 0,\n            ];\n        }\n    }\n    foreach (telemetry_read_events() as $event) {\n        if (!is_array($event)) {\n            continue;\n        }\n        $ts = (int) ($event["ts"] ?? 0);\n        if ($ts < $startTs || $ts > $nowTs) {\n            continue;\n        }\n        $idx = min(\n            $bucketCount - 1,\n            max(0, (int) floor(($ts - $startTs) / $bucketSeconds)),\n        );\n        $elapsed = max(0.0, (float) ($event["elapsed_ms"] ?? 0));\n        $queryMs = max(0.0, (float) ($event["query_ms"] ?? 0));\n        if ($elapsed > 0) {\n            $series["response"][$idx]["sum"] += $elapsed;\n            $series["response"][$idx]["samples"]++;\n        }\n        if ($queryMs > 0) {\n            $series["database"][$idx]["sum"] += $queryMs;\n            $series["database"][$idx]["samples"]++;\n        }\n        $series["requests"][$idx]["value"]++;\n        if (empty($event["success"])) {\n            $series["errors"][$idx]["value"]++;\n        }\n    }\n    foreach (["response", "database"] as $key) {\n        foreach ($series[$key] as &$row) {\n            $row["value"] =\n                (int) $row["samples"] > 0\n                    ? round((float) $row["sum"] / (int) $row["samples"], 1)\n                    : 0.0;\n            unset($row["sum"]);\n        }\n        unset($row);\n    }\n    foreach (["requests", "errors"] as $key) {\n        foreach ($series[$key] as &$row) {\n            unset($row["sum"]);\n        }\n        unset($row);\n    }\n    return $series;\n}\nfunction admin_telemetry_spool_health(): array\n{\n    $dirs = array_values(\n        array_unique(\n            array_merge(\n                maestro_deferred_storage_dirs("audit"),\n                maestro_deferred_storage_dirs("telemetry"),\n            ),\n        ),\n    );\n    $queued = 0;\n    $processing = 0;\n    $deadLetter = 0;\n    $oldest = 0;\n    foreach ($dirs as $dir) {\n        if (!is_dir($dir)) {\n            continue;\n        }\n        foreach ((array) glob($dir . "/*.json") as $file) {\n            if (!is_file($file)) {\n                continue;\n            }\n            $queued++;\n            $oldest = max($oldest, max(0, time() - (int) @filemtime($file)));\n        }\n        foreach ((array) glob($dir . "/*.processing") as $file) {\n            if (!is_file($file)) {\n                continue;\n            }\n            $processing++;\n            $oldest = max($oldest, max(0, time() - (int) @filemtime($file)));\n        }\n        foreach ((array) glob($dir . "/dead-letter/*") as $file) {\n            if (is_file($file)) {\n                $deadLetter++;\n            }\n        }\n    }\n    return [\n        "queued" => $queued,\n        "processing" => $processing,\n        "dead_letter" => $deadLetter,\n        "oldest_age_seconds" => $oldest,\n        "alert" => $deadLetter > 0 || $oldest > 3600,\n    ];\n}\nfunction admin_telemetry_age_label(int $seconds): string\n{\n    $seconds = max(0, $seconds);\n    if ($seconds < 60) {\n        return $seconds . " s";\n    }\n    if ($seconds < 3600) {\n        return (int) floor($seconds / 60) . " min";\n    }\n    if ($seconds < 86400) {\n        return number_format($seconds / 3600, 1, ",", ".") . " h";\n    }\n    return number_format($seconds / 86400, 1, ",", ".") . " dias";\n}\nfunction admin_telemetry_cache_rows_html(array $rows): string\n{\n    if (!$rows) {\n        return \'<div class="empty-state">Ainda não há amostras de cache nesta janela.</div>\';\n    }\n    $h =\n        \'<div class="table-wrap"><table class="data-table admin-performance-table"><thead><tr><th>Categoria</th><th>Consultas</th><th>Memória</th><th>Arquivo</th><th>Perdas</th><th>Acerto</th><th>Invalidações</th><th>Fallbacks</th><th>Espera média</th></tr></thead><tbody>\';\n    foreach (array_slice($rows, 0, 20) as $row) {\n        $hitRate = (float) ($row["hit_rate"] ?? 0);\n        $tone = $hitRate >= 85 ? "is-ok" : ($hitRate >= 60 ? "is-warn" : "is-bad");\n        $h .=\n            \'<tr class="\' .\n            $tone .\n            \'"><td><code>\' .\n            e((string) ($row["category"] ?? "general")) .\n            "</code></td><td>" .\n            n((int) ($row["lookups"] ?? 0)) .\n            "</td><td>" .\n            n((int) ($row["memory_hits"] ?? 0)) .\n            "</td><td>" .\n            n((int) ($row["file_hits"] ?? 0)) .\n            "</td><td>" .\n            n((int) ($row["misses"] ?? 0)) .\n            "</td><td><b>" .\n            e(number_format($hitRate, 1, ",", ".") . "%") .\n            "</b></td><td>" .\n            n((int) ($row["invalidations"] ?? 0)) .\n            "</td><td>" .\n            n((int) ($row["generation_fallback_clears"] ?? 0)) .\n            "</td><td>" .\n            e(admin_performance_format_ms((float) ($row["avg_lock_wait_ms"] ?? 0))) .\n            "</td></tr>";\n    }\n    return $h . "</tbody></table></div>";\n}\nfunction admin_telemetry_release_rows_html(array $releases): string\n{\n    if (!$releases) {\n        return \'<div class="empty-state">Ainda não há dados suficientes para comparar versões.</div>\';\n    }\n    uasort($releases, static fn(array $a, array $b): int =>\n        strcmp((string) ($b["release"] ?? ""), (string) ($a["release"] ?? ""))\n    );\n    $h =\n        \'<div class="table-wrap"><table class="data-table admin-performance-table"><thead><tr><th>Versão</th><th>Requisições</th><th>Resposta média</th><th>Banco médio</th><th>Consultas</th><th>Falhas</th></tr></thead><tbody>\';\n    foreach (array_slice(array_values($releases), 0, 8) as $row) {\n        $errors = (int) ($row["errors"] ?? 0);\n        $h .=\n            "<tr><td><code>" .\n            e((string) ($row["release"] ?? "unknown")) .\n            "</code></td><td>" .\n            n((int) ($row["count"] ?? 0)) .\n            "</td><td><b>" .\n            e(admin_performance_format_ms((float) ($row["avg_ms"] ?? 0))) .\n            "</b></td><td>" .\n            e(admin_performance_format_ms((float) ($row["query_avg_ms"] ?? 0))) .\n            "</td><td>" .\n            e(number_format((float) ($row["queries_avg"] ?? 0), 1, ",", ".")) .\n            "</td><td>" .\n            ($errors > 0\n                ? \'<span class="status danger">\' . n($errors) . "</span>"\n                : \'<span class="status ok">0</span>\') .\n            "</td></tr>";\n    }\n    return $h . "</tbody></table></div>";\n}\nfunction page_admin_telemetry(): void\n{\n    require_can("admin_telemetry");\n    $hours = admin_telemetry_window_hours();\n    $windowLabel = admin_telemetry_window_label($hours);\n    $series = admin_telemetry_time_series($hours);\n    $routes = telemetry_route_performance_summary($hours);\n    $cache = telemetry_cache_performance_summary($hours);\n    $releases = telemetry_release_performance_summary(max(24, $hours));\n    $spool = admin_telemetry_spool_health();\n    $routeRows = isset($routes["routes"]) && is_array($routes["routes"])\n        ? $routes["routes"]\n        : [];\n    $total = (int) ($routes["total"] ?? 0);\n    $avg = (float) ($routes["avg_ms"] ?? 0);\n    $errors = (int) array_sum(\n        array_map(static fn(array $row): int => (int) ($row["errors"] ?? 0), $routeRows),\n    );\n    $errorRate = $total > 0 ? round(($errors / $total) * 100, 2) : 0.0;\n    $cacheTotal = isset($cache["total"]) && is_array($cache["total"])\n        ? $cache["total"]\n        : [];\n    $cacheLookups = (int) ($cacheTotal["lookups"] ?? 0);\n    $cacheHitRate = (float) ($cacheTotal["hit_rate"] ?? 0);\n    $queueTotal = (int) $spool["queued"] + (int) $spool["processing"];\n    $stats =\n        \'<div class="stats-grid admin-performance-stats">\' .\n        stat_card("Requisições", $total, "route", "Janela de " . $windowLabel) .\n        stat_card(\n            "Resposta média",\n            admin_performance_format_ms($avg),\n            "speed",\n            "Tempo total observado",\n        ) .\n        stat_card(\n            "Taxa de falhas",\n            number_format($errorRate, 2, ",", ".") . "%",\n            "error",\n            n($errors) . " falha(s) observada(s)",\n        ) .\n        stat_card(\n            "Acertos de cache",\n            $cacheLookups > 0 ? number_format($cacheHitRate, 1, ",", ".") . "%" : "—",\n            "cached",\n            $cacheLookups > 0 ? n($cacheLookups) . " consultas" : "Sem amostras",\n        ) .\n        stat_card(\n            "Fila diferida",\n            $queueTotal,\n            "pending_actions",\n            (int) $spool["processing"] . " em processamento",\n        ) .\n        stat_card(\n            "Item mais antigo",\n            $queueTotal > 0\n                ? admin_telemetry_age_label((int) $spool["oldest_age_seconds"])\n                : "—",\n            !empty($spool["alert"]) ? "warning" : "schedule",\n            (int) $spool["dead_letter"] . " em fila morta",\n        ) .\n        "</div>";\n    $charts =\n        \'<div class="global-performance-charts global-area-charts admin-telemetry-charts">\' .\n        admin_metric_line_chart(\n            "Tempo total de resposta",\n            "Média por intervalo, incluindo aplicação e banco.",\n            $series["response"],\n            "speed",\n            "ms",\n        ) .\n        admin_metric_line_chart(\n            "Tempo de banco",\n            "Tempo médio gasto em consultas SQL por intervalo.",\n            $series["database"],\n            "database",\n            "ms",\n        ) .\n        admin_metric_line_chart(\n            "Requisições",\n            "Volume de requisições registradas por intervalo.",\n            $series["requests"],\n            "route",\n            "count",\n        ) .\n        admin_metric_line_chart(\n            "Falhas",\n            "Requisições não concluídas com sucesso por intervalo.",\n            $series["errors"],\n            "error",\n            "count",\n        ) .\n        "</div>";\n    $updated = trim((string) ($routes["updated_at"] ?? ""));\n    $body =\n        page_head(\n            "Telemetria",\n            "Desempenho, eficiência e sinais operacionais do runtime, sem dados clínicos ou pessoais.",\n            admin_telemetry_window_html($hours),\n        ) .\n        \'<section class="admin-performance-screen admin-telemetry-screen">\' .\n        $stats .\n        card(\n            \'<div class="section-head admin-performance-head"><h2>\' .\n                icon("monitoring") .\n                "<span>Tendências · " .\n                e($windowLabel) .\n                "</span></h2><p>As séries são agregadas no fuso operacional de Cuiabá." .\n                ($updated !== "" ? " Última atualização: " . e($updated) . "." : "") .\n                "</p></div>" .\n                $charts,\n            "admin-performance-card admin-telemetry-chart-card",\n        ) .\n        card(\n            \'<div class="section-head admin-performance-head"><h2>\' .\n                icon("speed") .\n                \'<span>Rotas mais lentas</span></h2><p>Prioriza rotas por tempo médio para orientar investigação e otimização.</p></div>\' .\n                admin_performance_rows_html(array_slice($routeRows, 0, 15)),\n            "admin-performance-card",\n        ) .\n        card(\n            \'<div class="section-head admin-performance-head"><h2>\' .\n                icon("cached") .\n                \'<span>Eficiência do cache</span></h2><p>Acertos, perdas, invalidações e contenção por categoria.</p></div>\' .\n                admin_telemetry_cache_rows_html(\n                    isset($cache["categories"]) && is_array($cache["categories"])\n                        ? $cache["categories"]\n                        : [],\n                ),\n            "admin-performance-card",\n        ) .\n        card(\n            \'<div class="section-head admin-performance-head"><h2>\' .\n                icon("deployed_code") .\n                \'<span>Comparação entre versões</span></h2><p>Ajuda a identificar regressões após publicações recentes.</p></div>\' .\n                admin_telemetry_release_rows_html(\n                    isset($releases["releases"]) && is_array($releases["releases"])\n                        ? $releases["releases"]\n                        : [],\n                ),\n            "admin-performance-card",\n        ) .\n        "</section>";\n    page("Telemetria", $body);\n}\n' + marker, 1)
write(admin_path, admin)

architecture_check = read("tools/architecture-check.php")
result_marker = "$result = [\n"
if architecture_check.count(result_marker) != 1:
    raise RuntimeError("architecture result boundary not found")
architecture_check = architecture_check.replace(
    result_marker,
    '$developerTelemetryFailures = [];\n$developerTelemetrySources = [\n    \'admin\' => (string) file_get_contents($root . \'/app/Admin/AdminPages.php\'),\n    \'runner\' => (string) file_get_contents($root . \'/app/Runtime/Runner.php\'),\n    \'loader\' => (string) file_get_contents($root . \'/app/Support/ModuleLoader.php\'),\n    \'bootstrap\' => (string) file_get_contents($root . \'/app/prontoo.php\'),\n];\nforeach ([\n    \'admin\' => [\n        \'function page_admin_telemetry(): void\',\n        \'require_can("admin_telemetry")\',\n        \'admin_metric_line_chart(\',\n        \'telemetry_route_performance_summary($hours)\',\n        \'telemetry_cache_performance_summary($hours)\',\n        \'telemetry_release_performance_summary(max(24, $hours))\',\n        \'admin_telemetry_spool_health()\',\n        \'Desempenho, eficiência e sinais operacionais do runtime\',\n    ],\n    \'runner\' => [\'"admin_telemetry"\'],\n    \'loader\' => ["\'admin_telemetry\' => \\$admin"],\n    \'bootstrap\' => [\'"admin_telemetry" => ["label" => "Telemetria", "icon" => "monitoring"]\'],\n] as $sourceKey => $tokens) {\n    foreach ($tokens as $token) {\n        if (!str_contains($developerTelemetrySources[$sourceKey], $token)) {\n            $developerTelemetryFailures[] = $sourceKey . \':missing:\' . $token;\n        }\n    }\n}\n$telemetryPageStart = strpos(\n    $developerTelemetrySources[\'admin\'],\n    \'function page_admin_telemetry(): void\',\n);\n$telemetryPageEnd = $telemetryPageStart === false\n    ? false\n    : strpos(\n        $developerTelemetrySources[\'admin\'],\n        \'function page_admin_performance(): void\',\n        $telemetryPageStart,\n    );\n$telemetryPageSource =\n    $telemetryPageStart !== false && $telemetryPageEnd !== false\n        ? substr(\n            $developerTelemetrySources[\'admin\'],\n            $telemetryPageStart,\n            $telemetryPageEnd - $telemetryPageStart,\n        )\n        : \'\';\nif ($telemetryPageSource === \'\') {\n    $developerTelemetryFailures[] = \'telemetry_page_boundary\';\n}\nforeach ([\'INSERT \', \'UPDATE \', \'DELETE \', \'$_POST\'] as $token) {\n    if (str_contains($telemetryPageSource, $token)) {\n        $developerTelemetryFailures[] = \'telemetry_page_write_token:\' . $token;\n    }\n}\nif (!str_contains(\n    $developerTelemetrySources[\'admin\'],\n    \'return number_format($value, $value === floor($value) ? 0 : 1, ",", ".");\',\n)) {\n    $developerTelemetryFailures[] = \'count_chart_compact_format\';\n}\n$developerTelemetryCharacterization = [\n    \'ok\' => $developerTelemetryFailures === [],\n    \'failed\' => $developerTelemetryFailures,\n];\n' + result_marker,
    1,
)
architecture_check = architecture_check.replace(
    "        !empty($serverPhaseSixCharacterization['ok']),\n",
    "        !empty($serverPhaseSixCharacterization['ok']) &&\n"
    "        !empty($developerTelemetryCharacterization['ok']),\n",
    1,
)
architecture_check = architecture_check.replace(
    "    'server_phase_six_characterization' => $serverPhaseSixCharacterization,\n",
    "    'server_phase_six_characterization' => $serverPhaseSixCharacterization,\n"
    "    'developer_telemetry_characterization' => $developerTelemetryCharacterization,\n",
    1,
)
write("tools/architecture-check.php", architecture_check)

version = json.loads(read("version.json"))
version.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "package_type": "incremental_feature",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "functional_equivalence_policy": "adds_developer_telemetry_without_clinical_runtime_changes",
    "notes": "Adiciona item Telemetria ao Painel do Desenvolvedor com gráficos de desempenho, cache, versões e fila diferida.",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "deployment_sync_id": "github-developer-telemetry-dashboard-1-7-30-8",
    "deployment_sync_requested_at": NOW,
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "developer_telemetry_dashboard",
})
write(
    "version.json",
    json.dumps(version, ensure_ascii=False, indent=4) + "\n",
)

architecture = json.loads(read("app/architecture.manifest.json"))
architecture.update({
    "version": VERSION,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "generated_at": NOW,
    "updated_at": NOW,
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "developer_telemetry_dashboard_policy": "global_developer_read_only_runtime_metrics_without_clinical_or_personal_payloads",
    "developer_telemetry_dashboard_components": [
        "app/Admin/AdminPages.php",
        "app/Support/Telemetry.php",
    ],
    "developer_telemetry_dashboard_check": "tools/architecture-check.php",
})
write(
    "app/architecture.manifest.json",
    json.dumps(architecture, ensure_ascii=False, indent=4) + "\n",
)

manifest = json.loads(read("app/update.manifest.json"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "package_type": "incremental_feature",
    "generated_at": NOW,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
})
write(
    "app/update.manifest.json",
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
)

landing_path = "br/index.php"
landing = read(landing_path)
landing, count = re.subn(
    r'(BR_LANDING_VERSION_FALLBACK\s*=\s*["\'])1\.7\.30\.7(["\'])',
    r'\g<1>1.7.30.8\g<2>',
    landing,
    count=1,
)
if count != 1:
    raise RuntimeError("landing version fallback not found")
write(landing_path, landing)

changelog = read("CHANGELOG.md")
entry = """## 1.7.30.8 — Telemetria no Painel do Desenvolvedor

- adiciona o item Telemetria à navegação global do Desenvolvedor;
- apresenta gráficos de resposta, banco, volume e falhas em janelas selecionáveis;
- mostra eficiência do cache, rotas lentas, comparação entre versões e saúde da fila diferida;
- mantém a tela estritamente somente leitura e sem dados clínicos ou pessoais;
- não altera banco ou schema.

"""
if not changelog.startswith("# Histórico de versões\n\n"):
    raise RuntimeError("changelog header not found")
write("CHANGELOG.md", "# Histórico de versões\n\n" + entry + changelog[len("# Histórico de versões\n\n"):])

legacy = read("ChangeLog.txt")
legacy_entry = """Prontoo 1.7.30.8 — Telemetria no Painel do Desenvolvedor

- Adiciona gráficos de desempenho, cache, versões e fila diferida em uma tela global somente leitura.
- Mantém banco, schema e dados clínicos inalterados.
"""
write("ChangeLog.txt", legacy_entry + legacy)

docs_index = read("docs/index.md")
docs_anchor = "- [Telemetria particionada](performance/telemetry-partitions.md)\n"
if docs_index.count(docs_anchor) != 1:
    raise RuntimeError("docs index telemetry anchor not found")
docs_index = docs_index.replace(
    docs_anchor,
    docs_anchor + "- [Painel de telemetria do Desenvolvedor](performance/developer-telemetry-dashboard.md)\n",
    1,
)
write("docs/index.md", docs_index)

write(
    "docs/performance/developer-telemetry-dashboard.md",
    """# Painel de telemetria do Desenvolvedor

## Objetivo

A rota `admin_telemetry` fornece uma leitura operacional do desempenho e da saúde do runtime para o perfil Desenvolvedor.

## Fontes

A tela reutiliza exclusivamente métricas técnicas já registradas:

- eventos de requisição particionados em NDJSON;
- agregados de desempenho por rota e versão;
- contadores de cache JSON;
- estado dos diretórios de trabalho diferido do Maestro.

Nenhum conteúdo clínico, identificador de paciente ou payload de formulário é exibido.

## Indicadores

A visão apresenta:

- volume e tempo médio de resposta;
- tempo de banco;
- falhas por intervalo;
- eficiência e contenção do cache;
- rotas com maior latência;
- comparação entre versões;
- itens pendentes, em processamento e em fila morta.

## Segurança e custo

A rota é global, exige a capacidade `admin_telemetry` e não possui operações POST. As janelas aceitas são limitadas a 6, 24, 72 ou 168 horas. A leitura não cria tabelas, não altera o schema e não modifica os arquivos de telemetria.
""",
)

print(VERSION)
