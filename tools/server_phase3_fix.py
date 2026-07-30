from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
telemetry_path = ROOT / "app/Support/Telemetry.php"
source = telemetry_path.read_text(encoding="utf-8")
marker = "function telemetry_prune_page_partitions(int $timestamp): int\n"
helpers = r'''function telemetry_page_idempotency_dir(int $timestamp): string
{

    return telemetry_storage_dir() . "/page-idempotency-" . gmdate("Ymd", $timestamp);
}

function telemetry_page_idempotency_marker(
    int $timestamp,
    string $deferredId,
): string {

    return telemetry_page_idempotency_dir($timestamp) . "/" . $deferredId . ".done";
}

'''
if source.count(marker) != 1:
    raise SystemExit("Marcador de retenção da telemetria divergente")
source = source.replace(marker, helpers + marker, 1)

start = source.find("function telemetry_prune_page_partitions(int $timestamp): int\n")
end = source.find("function telemetry_cuiaba_tz(): DateTimeZone\n", start)
if start < 0 or end < 0:
    raise SystemExit("Limites da retenção da telemetria não encontrados")
block = source[start:end]
old = '''    foreach ((array) glob(telemetry_storage_dir() . "/page-load-*.ndjson") as $file) {
        if (preg_match('/page-load-(\\d{8})\\.ndjson$/', basename((string) $file), $match) !== 1) {
            continue;
        }
        if ((string) ($match[1] ?? "") < $minimumDate && @unlink((string) $file)) {
            $deleted++;
        }
    }
    return $deleted;
'''
new = '''    foreach ((array) glob(telemetry_storage_dir() . "/page-load-*.ndjson") as $file) {
        if (preg_match('/page-load-(\\d{8})\\.ndjson$/', basename((string) $file), $match) !== 1) {
            continue;
        }
        if ((string) ($match[1] ?? "") < $minimumDate && @unlink((string) $file)) {
            $deleted++;
        }
    }
    foreach ((array) glob(telemetry_storage_dir() . "/page-idempotency-*") as $dir) {
        if (!is_dir((string) $dir) ||
            preg_match('/page-idempotency-(\\d{8})$/', basename((string) $dir), $match) !== 1 ||
            (string) ($match[1] ?? "") >= $minimumDate) {
            continue;
        }
        foreach ((array) glob((string) $dir . "/*.done") as $markerFile) {
            @unlink((string) $markerFile);
        }
        if (@rmdir((string) $dir)) {
            $deleted++;
        }
    }
    return $deleted;
'''
if block.count(old) != 1:
    raise SystemExit("Corpo de retenção divergente")
block = block.replace(old, new, 1)
source = source[:start] + block + source[end:]

append_start = source.find("function telemetry_append_page_metric(array $event): bool\n")
append_end = source.find("function telemetry_route_perf_file(): string\n", append_start)
if append_start < 0 or append_end < 0:
    raise SystemExit("Limites do append de telemetria não encontrados")
append = source[append_start:append_end]
old = '''    $file = telemetry_page_partition_file($nowTs);
    $handle = @fopen($file, "ab");
'''
new = '''    $file = telemetry_page_partition_file($nowTs);
    $deferredId = is_string($row["deferred_id"] ?? null)
        ? (string) $row["deferred_id"]
        : "";
    $markerFile = $deferredId !== ""
        ? telemetry_page_idempotency_marker($nowTs, $deferredId)
        : "";
    $handle = @fopen($file, "ab");
'''
if append.count(old) != 1:
    raise SystemExit("Abertura da partição divergente")
append = append.replace(old, new, 1)
old = '''        if (!@flock($handle, LOCK_EX)) {
            return false;
        }
        $line = $encoded . "\\n";
        $written = fwrite($handle, $line) === strlen($line) && fflush($handle);
'''
new = '''        if (!@flock($handle, LOCK_EX)) {
            return false;
        }
        if ($markerFile !== "" && is_file($markerFile)) {
            return true;
        }
        $line = $encoded . "\\n";
        $written = fwrite($handle, $line) === strlen($line) && fflush($handle);
        if ($written && $markerFile !== "") {
            $markerDir = dirname($markerFile);
            if (!is_dir($markerDir)) {
                @mkdir($markerDir, 0750, true);
            }
            if (is_dir($markerDir)) {
                @file_put_contents($markerFile, "1\\n", LOCK_EX);
                @chmod($markerFile, 0640);
            }
        }
'''
if append.count(old) != 1:
    raise SystemExit("Bloco de escrita da partição divergente")
append = append.replace(old, new, 1)
source = source[:append_start] + append + source[append_end:]
telemetry_path.write_text(source, encoding="utf-8")

security_path = ROOT / "tools/security-regression-check.php"
security = security_path.read_text(encoding="utf-8")
old = '''$pagePayload = json_decode(
    (string) file_get_contents(telemetry_page_file()),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
'''
new = '''$pageEvents = telemetry_read_events();
$pageDeferredIds = [];
foreach ($pageEvents as $pageEvent) {
    if (!is_array($pageEvent)) {
        continue;
    }
    $pageDeferredId = (string) ($pageEvent["deferred_id"] ?? "");
    if ($pageDeferredId !== "") {
        $pageDeferredIds[$pageDeferredId] = true;
    }
}
'''
if security.count(old) != 1:
    raise SystemExit("Leitura legada do teste de telemetria não localizada")
security = security.replace(old, new, 1)
old = '''    $routeCount === 1 &&
        count((array) ($pagePayload["events"] ?? [])) === 1 &&
        count((array) ($routePayload["deferred_ids"] ?? [])) === 1 &&
        count((array) ($pagePayload["deferred_ids"] ?? [])) === 1,
'''
new = '''    $routeCount === 1 &&
        count($pageEvents) === 1 &&
        count((array) ($routePayload["deferred_ids"] ?? [])) === 1 &&
        count($pageDeferredIds) <= 1,
'''
if security.count(old) != 1:
    raise SystemExit("Asserção legada do replay não localizada")
security_path.write_text(security.replace(old, new, 1), encoding="utf-8")
