from pathlib import Path
import json
import re
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.3"
PREVIOUS = "1.7.30.2"
BUILD = "1.7.30.3-server-phase3-telemetry-partitions"
NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
NOW_UNIX = int(time.time())


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def update_json(path: str, values: dict) -> None:
    target = ROOT / path
    data = json.loads(target.read_text(encoding="utf-8"))
    data.update(values)
    target.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")


def replace_function(source: str, start: str, next_start: str, replacement: str) -> str:
    begin = source.find(start)
    if begin < 0:
        raise SystemExit(f"Função não localizada: {start}")
    end = source.find(next_start, begin + len(start))
    if end < 0:
        raise SystemExit(f"Próxima função não localizada: {next_start}")
    return source[:begin] + replacement.rstrip() + "\n" + source[end:]


telemetry_path = ROOT / "app/Support/Telemetry.php"
telemetry = telemetry_path.read_text(encoding="utf-8")
page_file_marker = '''function telemetry_page_file(): string
{

    return telemetry_storage_dir() . "/page-load.json";
}
'''
page_file_replacement = '''function telemetry_page_file(): string
{

    return telemetry_storage_dir() . "/page-load.json";
}

function telemetry_page_partition_file(int $timestamp): string
{

    return telemetry_storage_dir() . "/page-load-" . gmdate("Ymd", $timestamp) . ".ndjson";
}

function telemetry_page_partition_files(int $timestamp): array
{

    return array_values(array_unique([
        telemetry_page_partition_file($timestamp),
        telemetry_page_partition_file($timestamp - 86400),
        telemetry_page_partition_file($timestamp - 172800),
    ]));
}

function telemetry_prune_page_partitions(int $timestamp): int
{

    $deleted = 0;
    $minimumDate = gmdate("Ymd", $timestamp - 172800);
    foreach ((array) glob(telemetry_storage_dir() . "/page-load-*.ndjson") as $file) {
        if (preg_match('/page-load-(\d{8})\.ndjson$/', basename((string) $file), $match) !== 1) {
            continue;
        }
        if ((string) ($match[1] ?? "") < $minimumDate && @unlink((string) $file)) {
            $deleted++;
        }
    }
    return $deleted;
}
'''
if telemetry.count(page_file_marker) != 1:
    raise SystemExit("Bloco de arquivo de telemetria divergente")
telemetry = telemetry.replace(page_file_marker, page_file_replacement, 1)

read_replacement = r'''function telemetry_read_events(): array
{

    static $requestCache = null;
    if (is_array($requestCache)) {
        return $requestCache;
    }
    try {
        $events = [];
        $legacyFile = telemetry_page_file();
        if (is_file($legacyFile)) {
            $raw = @file_get_contents($legacyFile);
            $legacy = is_string($raw) && trim($raw) !== ""
                ? json_decode($raw, true)
                : null;
            if (is_array($legacy) && isset($legacy["events"]) && is_array($legacy["events"])) {
                $events = array_merge($events, $legacy["events"]);
            }
        }
        foreach (telemetry_page_partition_files(time()) as $file) {
            if (!is_file($file)) {
                continue;
            }
            $stream = new SplFileObject($file, "rb");
            while (!$stream->eof()) {
                $line = trim((string) $stream->fgets());
                if ($line === "") {
                    continue;
                }
                $event = json_decode($line, true);
                if (is_array($event)) {
                    $events[] = $event;
                }
            }
        }
        $deduplicated = [];
        $seenDeferred = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $deferredId = (string) ($event["deferred_id"] ?? "");
            if ($deferredId !== "" && isset($seenDeferred[$deferredId])) {
                continue;
            }
            if ($deferredId !== "") {
                $seenDeferred[$deferredId] = true;
            }
            $deduplicated[] = $event;
        }
        return $requestCache = telemetry_sanitize_events($deduplicated, time());
    } catch (Throwable $error) {
        error_log("[Prontoo telemetry read] " . $error->getMessage());
        return $requestCache = [];
    }
}

'''
telemetry = replace_function(
    telemetry,
    "function telemetry_read_events(): array\n",
    "function telemetry_sanitize_events(array $events, int $nowTs): array\n",
    read_replacement,
)

append_replacement = r'''function telemetry_append_page_metric(array $event): bool
{

    if (!function_exists("storage_path") || !telemetry_prepare_storage()) {
        return false;
    }
    $nowTs = (int) ($event["ts"] ?? time());
    $row = [
        "ts" => $nowTs,
        "route" => mb_substr((string) ($event["route"] ?? ""), 0, 80),
        "queries" => max(0, (int) ($event["queries"] ?? 0)),
        "wide_selects" => max(0, (int) ($event["wide_selects"] ?? 0)),
        "module_bytes" => max(0, (int) ($event["module_bytes"] ?? 0)),
        "module_files" => max(0, (int) ($event["module_files"] ?? 0)),
        "elapsed_ms" => max(0.0, round((float) ($event["elapsed_ms"] ?? 0), 3)),
        "query_ms" => max(0.0, round((float) ($event["query_ms"] ?? 0), 3)),
        "success" => !empty($event["success"]) ? 1 : 0,
        "deferred_id" => preg_match(
            '/^\d{20}-[a-f0-9]{16}$/',
            (string) ($event["deferred_id"] ?? ""),
        ) === 1
            ? (string) $event["deferred_id"]
            : null,
    ];
    $encoded = json_encode(
        $row,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    if (!is_string($encoded)) {
        return false;
    }
    $file = telemetry_page_partition_file($nowTs);
    $handle = @fopen($file, "ab");
    if (!is_resource($handle)) {
        return false;
    }
    $written = false;
    try {
        if (!@flock($handle, LOCK_EX)) {
            return false;
        }
        $line = $encoded . "\n";
        $written = fwrite($handle, $line) === strlen($line) && fflush($handle);
    } catch (Throwable $error) {
        error_log("[Prontoo telemetry append] " . $error->getMessage());
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
    static $pruned = false;
    if (!$pruned) {
        telemetry_prune_page_partitions($nowTs);
        $pruned = true;
    }
    return $written;
}

'''
telemetry = replace_function(
    telemetry,
    "function telemetry_append_page_metric(array $event): bool\n",
    "function telemetry_route_perf_file(): string\n",
    append_replacement,
)
telemetry_path.write_text(telemetry, encoding="utf-8")

architecture_check = ROOT / "tools/architecture-check.php"
source = architecture_check.read_text(encoding="utf-8")
marker = "$result = [\n"
marker_index = source.rfind(marker)
if marker_index < 0:
    raise SystemExit("Marcador final do architecture-check.php divergente")
block = r'''$serverPhaseThreeFailures = [];
$telemetryPartitionSource = (string) file_get_contents($root . '/app/Support/Telemetry.php');
foreach ([
    'function telemetry_page_partition_file(',
    'function telemetry_page_partition_files(',
    'function telemetry_prune_page_partitions(',
    'new SplFileObject($file, "rb")',
    '$handle = @fopen($file, "ab")',
    '"deferred_id" => preg_match(',
] as $requiredToken) {
    if (!str_contains($telemetryPartitionSource, $requiredToken)) {
        $serverPhaseThreeFailures[] = 'telemetry_partition_missing:' . $requiredToken;
    }
}
$appendStart = strpos($telemetryPartitionSource, 'function telemetry_append_page_metric(');
$appendEnd = $appendStart === false
    ? false
    : strpos($telemetryPartitionSource, 'function telemetry_route_perf_file(', $appendStart);
$appendSource = $appendStart !== false && $appendEnd !== false
    ? substr($telemetryPartitionSource, $appendStart, $appendEnd - $appendStart)
    : '';
foreach (['json_decode($raw', 'ftruncate($fh, 0)', 'stream_get_contents($fh)'] as $forbiddenToken) {
    if ($appendSource === '' || str_contains($appendSource, $forbiddenToken)) {
        $serverPhaseThreeFailures[] = 'telemetry_append_forbidden:' . $forbiddenToken;
    }
}
$serverPhaseThreeCharacterization = [
    'ok' => $serverPhaseThreeFailures === [],
    'failed' => $serverPhaseThreeFailures,
];
'''
source = source[:marker_index] + block + source[marker_index:]
source = source.replace(
    "        !empty($serverPhaseTwoCharacterization['ok']),",
    "        !empty($serverPhaseTwoCharacterization['ok']) &&\n        !empty($serverPhaseThreeCharacterization['ok']),",
    1,
)
source = source.replace(
    "    'server_phase_two_characterization' => $serverPhaseTwoCharacterization,",
    "    'server_phase_two_characterization' => $serverPhaseTwoCharacterization,\n    'server_phase_three_characterization' => $serverPhaseThreeCharacterization,",
    1,
)
architecture_check.write_text(source, encoding="utf-8")

write("docs/performance/telemetry-partitions.md", """# Telemetria particionada

## Objetivo

A Fase 3 remove a leitura e reescrita integral do histórico bruto durante a consolidação de cada amostra. Eventos de página passam a ser acrescentados em arquivos NDJSON particionados por data UTC.

## Robustez

A escrita utiliza bloqueio exclusivo, uma linha completa por evento e `fflush`. A leitura aceita simultaneamente o arquivo JSON legado e as três partições necessárias à janela de 25 horas. Identificadores diferidos são deduplicados durante a leitura.

## Retenção

A limpeza de partições antigas ocorre no consumidor diferido, uma vez por processo, e não no caminho da requisição interativa. A telemetria agregada por rota continua sendo a fonte para avaliação operacional.

## Conformidade

Os eventos mantêm apenas rota, contadores técnicos, duração, módulos, sucesso e identificador técnico diferido. Conteúdo clínico, nomes, CPF, parâmetros SQL e textos livres não integram o formato.
""")

changelog = ROOT / "CHANGELOG.md"
text = changelog.read_text(encoding="utf-8")
entry = """\n## 1.7.30.3 — Evolução do servidor, Fase 3: telemetria particionada

- substitui reescrita integral do histórico bruto por append NDJSON;
- particiona eventos por data UTC e mantém leitura do formato legado;
- deduplica identificadores diferidos durante a leitura;
- move retenção de partições para o consumidor assíncrono;
- não altera banco, schema ou interface.\n"""
if entry.strip() not in text:
    changelog.write_text(text.replace("# Histórico de versões\n", "# Histórico de versões\n" + entry, 1), encoding="utf-8")
change_txt = ROOT / "ChangeLog.txt"
text = change_txt.read_text(encoding="utf-8")
entry = """Prontoo 1.7.30.3 — Evolução do servidor, Fase 3: telemetria particionada

- Registra eventos brutos em partições NDJSON append-only e mantém compatibilidade de leitura.
- Mantém banco, schema, interface, permissões e comportamento funcional.
"""
if not text.startswith("Prontoo 1.7.30.3"):
    change_txt.write_text(entry + text, encoding="utf-8")
docs_index = ROOT / "docs/index.md"
text = docs_index.read_text(encoding="utf-8")
line = "- [Telemetria particionada](performance/telemetry-partitions.md)\n"
if line not in text:
    docs_index.write_text(text.rstrip() + "\n" + line, encoding="utf-8")

for path, constant in [("app/prontoo.php", "PRONTOO_VERSION_FALLBACK"), ("br/index.php", "BR_LANDING_VERSION_FALLBACK")]:
    target = ROOT / path
    source = target.read_text(encoding="utf-8")
    pattern = rf"(const\s+{constant}\s*=\s*['\"])[^'\"]+(['\"]\s*;)"
    source, count = re.subn(pattern, rf"\g<1>{VERSION}\g<2>", source, count=1)
    if count != 1:
        raise SystemExit(f"Fallback não localizado: {path}")
    target.write_text(source, encoding="utf-8")

common = {
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "package_type": "incremental_refactor",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": False,
    "visual_changes": False,
    "functional_equivalence_policy": "preserves_1_7_30_2_runtime_behavior",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "notes": "Particiona a telemetria bruta em NDJSON append-only com leitura compatível.",
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "telemetry_append_only_daily_partitions",
    "deployment_sync_id": "github-server-phase3-telemetry-partitions-1-7-30-3",
    "deployment_sync_requested_at": NOW,
}
update_json("version.json", common)
update_json("app/update.manifest.json", common)
update_json("app/architecture.manifest.json", {
    "version": VERSION,
    "generated_at": NOW,
    "updated_at": NOW,
    "baseline_source": PREVIOUS,
    "native_files_min": 30,
    "transitional_files_max": 80,
    "server_evolution_phase_three_policy": "raw_page_telemetry_is_append_only_partitioned_deduplicated_and_pruned_off_request_path",
    "server_evolution_phase_three_components": ["app/Support/Telemetry.php"],
    "server_evolution_phase_three_check": "tools/architecture-check.php",
})
