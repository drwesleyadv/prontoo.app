from pathlib import Path
import re

telemetry = Path('app/Support/Telemetry.php')
text = telemetry.read_text(encoding='utf-8')

anchor = 'function telemetry_route_perf_file(): string\n'
helpers = r'''function telemetry_route_cycle_file(): string
{
    return telemetry_storage_dir() . "/route-cycles-v2.json";
}
function telemetry_route_cycle_mutate(callable $mutator): mixed
{
    if (!telemetry_prepare_storage()) return null;
    $file = telemetry_route_cycle_file();
    $fh = @fopen($file, "c+");
    if (!is_resource($fh)) return null;
    try {
        if (!flock($fh, LOCK_EX)) return null;
        rewind($fh);
        $raw = stream_get_contents($fh);
        $state = is_string($raw) && trim($raw) !== "" ? json_decode($raw, true) : [];
        if (!is_array($state) || (int) ($state["version"] ?? 0) !== 2) {
            $state = ["version" => 2, "timezone" => "America/Cuiaba", "pending" => [], "days" => []];
        }
        $result = $mutator($state);
        $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) return null;
        rewind($fh);
        ftruncate($fh, 0);
        if (fwrite($fh, $encoded) !== strlen($encoded) || !fflush($fh)) return null;
        return $result;
    } finally {
        @flock($fh, LOCK_UN);
        @fclose($fh);
    }
}
function telemetry_route_cycle_begin(string $route): ?array
{
    $route = telemetry_route_perf_safe_route($route);
    $id = bin2hex(random_bytes(16));
    $startedNs = hrtime(true);
    $startedUs = (int) round(microtime(true) * 1000000);
    $ok = telemetry_route_cycle_mutate(static function (array &$state) use ($id, $route, $startedNs, $startedUs): bool {
        $pending = isset($state["pending"]) && is_array($state["pending"]) ? $state["pending"] : [];
        $expiry = $startedUs - 6 * 3600 * 1000000;
        foreach ($pending as $key => $row) {
            if (!is_array($row) || (int) ($row["started_us"] ?? 0) < $expiry) unset($pending[$key]);
        }
        $pending[$id] = ["route" => $route, "started_ns" => $startedNs, "started_us" => $startedUs];
        $state["pending"] = $pending;
        $state["updated_at_us"] = $startedUs;
        return true;
    });
    return $ok === true ? ["id" => $id, "route" => $route, "started_ns" => $startedNs, "started_us" => $startedUs] : null;
}
function telemetry_route_cycle_finish(array $token): bool
{
    $id = strtolower((string) ($token["id"] ?? ""));
    if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) return false;
    $finishedNs = hrtime(true);
    $finishedUs = (int) round(microtime(true) * 1000000);
    $result = telemetry_route_cycle_mutate(static function (array &$state) use ($id, $finishedNs, $finishedUs): bool {
        $pending = isset($state["pending"]) && is_array($state["pending"]) ? $state["pending"] : [];
        $row = isset($pending[$id]) && is_array($pending[$id]) ? $pending[$id] : null;
        if ($row === null) return false;
        unset($pending[$id]);
        $route = telemetry_route_perf_safe_route((string) ($row["route"] ?? ""));
        $startedNs = max(0, (int) ($row["started_ns"] ?? 0));
        if ($startedNs <= 0 || $finishedNs < $startedNs) {
            $state["pending"] = $pending;
            return false;
        }
        $durationNs = $finishedNs - $startedNs;
        $day = (new DateTimeImmutable("@" . intdiv($finishedUs, 1000000)))->setTimezone(telemetry_cuiaba_tz())->format("Y-m-d");
        $days = isset($state["days"]) && is_array($state["days"]) ? $state["days"] : [];
        $cut = (new DateTimeImmutable("today", telemetry_cuiaba_tz()))->modify("-35 days")->format("Y-m-d");
        foreach (array_keys($days) as $key) if ((string) $key < $cut) unset($days[$key]);
        $daily = isset($days[$day]) && is_array($days[$day]) ? $days[$day] : ["completed" => 0, "total_ns" => 0, "landing" => 0];
        $daily["completed"] = max(0, (int) ($daily["completed"] ?? 0)) + 1;
        $daily["total_ns"] = max(0, (int) ($daily["total_ns"] ?? 0)) + $durationNs;
        $daily["landing"] = max(0, (int) ($daily["landing"] ?? 0)) + ($route === "landing" ? 1 : 0);
        $daily["last_finished_us"] = $finishedUs;
        $days[$day] = $daily;
        ksort($days, SORT_STRING);
        $state["pending"] = $pending;
        $state["days"] = $days;
        $state["updated_at_us"] = $finishedUs;
        return true;
    });
    return $result === true;
}
function telemetry_route_cycle_register(string $route): void
{
    $token = telemetry_route_cycle_begin($route);
    if (!is_array($token)) return;
    register_shutdown_function(static function () use ($token): void {
        try { telemetry_route_cycle_finish($token); }
        catch (Throwable $e) { error_log("[Prontoo route cycle] " . $e->getMessage()); }
    });
}
function telemetry_status_cards_snapshot(): array
{
    $today = (new DateTimeImmutable("today", telemetry_cuiaba_tz()))->format("Y-m-d");
    $state = [];
    $file = telemetry_route_cycle_file();
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $state = is_string($raw) && trim($raw) !== "" ? json_decode($raw, true) : [];
    }
    $days = is_array($state) && isset($state["days"]) && is_array($state["days"]) ? $state["days"] : [];
    $row = isset($days[$today]) && is_array($days[$today]) ? $days[$today] : [];
    $total = max(0, (int) ($row["completed"] ?? 0));
    $totalNs = max(0, (int) ($row["total_ns"] ?? 0));
    return [
        "source" => "route_cycles_v2",
        "period" => "today",
        "timezone" => "America/Cuiaba",
        "day" => $today,
        "total" => $total,
        "avg_ms" => $total > 0 ? round(($totalNs / $total) / 1000000, 3) : 0.0,
        "landing" => max(0, (int) ($row["landing"] ?? 0)),
        "last_finished_us" => max(0, (int) ($row["last_finished_us"] ?? 0)),
    ];
}

'''
if 'function telemetry_route_cycle_begin(' not in text:
    if anchor not in text: raise SystemExit('Telemetry anchor missing')
    text = text.replace(anchor, helpers + anchor, 1)

# Remove the entire previous status-card source and comparison implementation.
text = re.sub(r'\n        \$statusSource = isset\(\$json\["status_card_source_v1"\].*?\n        \$payload = \[', '\n        $payload = [', text, count=1, flags=re.S)
text = text.replace('            "status_card_source_v1" => $statusSource,\n', '')
text = re.sub(r'function telemetry_seven_day_comparison\(\): array\n\{.*?\n\}\n\nfunction telemetry_route_count_last_days', 'function telemetry_route_count_last_days', text, count=1, flags=re.S)
telemetry.write_text(text, encoding='utf-8')

runner = Path('app/Runtime/Runner.php')
r = runner.read_text(encoding='utf-8')
needle = '        $r = route();\n'
if 'telemetry_route_cycle_register($r);' not in r:
    if needle not in r: raise SystemExit('Runner route anchor missing')
    r = r.replace(needle, needle + '        if (function_exists("telemetry_route_cycle_register")) telemetry_route_cycle_register($r);\n', 1)
runner.write_text(r, encoding='utf-8')

br = Path('br/runtime-telemetry.php')
br.write_text('''<?php\nif (!function_exists("storage_path")) {\n    function storage_path(string $suffix = ""): string {\n        $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . "ssd";\n        return $suffix === "" ? $base : $base . DIRECTORY_SEPARATOR . ltrim($suffix, "/\\\\");\n    }\n}\ntry {\n    $configFile = dirname(__DIR__) . "/app/config.php";\n    if (is_file($configFile)) {\n        require_once dirname(__DIR__) . "/app/Support/Telemetry.php";\n        if (function_exists("telemetry_route_cycle_register")) telemetry_route_cycle_register("landing");\n    }\n} catch (Throwable $e) {\n    error_log("[Prontoo landing route cycle] " . $e->getMessage());\n}\n''', encoding='utf-8')

admin = Path('app/Admin/AdminPages.php')
a = admin.read_text(encoding='utf-8')
a = re.sub(r'function admin_telemetry_variation_text\(.*?\n\}\nfunction admin_telemetry_seven_day_cards_html\(array \$comparison\): string\n\{.*?\n\}', '''function admin_telemetry_today_cards_html(array $snapshot): string
{
    $detail = "hoje · ciclos completos entre marcador inicial e final";
    return stat_card("Requisições", max(0, (int) ($snapshot["total"] ?? 0)), "sync_alt", $detail) .
        stat_card("Tempo Médio", number_format(max(0.0, (float) ($snapshot["avg_ms"] ?? 0.0)), 3, ",", ".") . " ms", "speed", $detail) .
        stat_card("Carregamentos da landing page", max(0, (int) ($snapshot["landing"] ?? 0)), "web", $detail);
}''', a, count=1, flags=re.S)
a = a.replace('telemetry_seven_day_comparison()', 'telemetry_status_cards_snapshot()')
a = a.replace('admin_telemetry_seven_day_cards_html(', 'admin_telemetry_today_cards_html(')
a = a.replace('["current" => [], "variation" => []]', '["total" => 0, "avg_ms" => 0.0, "landing" => 0]')
a = a.replace('Comparação automática com os 7 dias anteriores.', 'Medição exata dos ciclos concluídos no dia atual.')
admin.write_text(a, encoding='utf-8')
