<?php
declare(strict_types=1);

function telemetry_storage_dir(): string
{
    if (function_exists("storage_path")) {
        return storage_path("telemetry");
    }
    return dirname(__DIR__, 2) . "/ssd/telemetry";
}

function telemetry_file(): string
{
    return telemetry_storage_dir() . "/telemetria.json";
}

function telemetry_schema(): string
{
    return "prontoo.telemetria.rota.v1";
}

function telemetry_retention_microseconds(): int
{
    return 20 * 86400 * 1000000;
}

function telemetry_comparison_microseconds(): int
{
    return 10 * 86400 * 1000000;
}

function telemetry_cuiaba_tz(): DateTimeZone
{
    static $timezone = null;
    return $timezone ??= new DateTimeZone("America/Cuiaba");
}

function telemetry_route_safe(string $route): string
{
    $route = strtolower(trim($route));
    $route = preg_replace('/[^a-z0-9_\-]/', '_', $route) ?? "";
    $route = trim($route, "_-");
    return substr($route !== "" ? $route : "unknown", 0, 80);
}

function telemetry_prepare_storage(): bool
{
    $dir = telemetry_storage_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return false;
    }
    @chmod($dir, 0750);
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($dir);
    } else {
        if (!is_file($dir . "/.htaccess")) {
            @file_put_contents($dir . "/.htaccess", "Require all denied\n", LOCK_EX);
        }
        if (!is_file($dir . "/index.html")) {
            @file_put_contents($dir . "/index.html", "", LOCK_EX);
        }
    }
    return is_writable($dir);
}

function telemetry_utc_from_unix_microseconds(int $unixMicroseconds): string
{
    $seconds = intdiv(max(0, $unixMicroseconds), 1000000);
    $microseconds = max(0, $unixMicroseconds) % 1000000;
    return gmdate("Y-m-d\\TH:i:s", $seconds) .
        sprintf(".%06dZ", $microseconds);
}

function telemetry_route_start_marker(
    string $route,
    ?int $startedMonotonicNs = null,
    ?int $startedUnixUs = null,
): void {
    if (PHP_SAPI === "cli" || !empty($GLOBALS["PRONTOO_TELEMETRY_REGISTERED"])) {
        return;
    }
    $GLOBALS["PRONTOO_TELEMETRY_REGISTERED"] = true;
    $GLOBALS["PRONTOO_ROUTE_NAME"] = telemetry_route_safe($route);
    $GLOBALS["PRONTOO_ROUTE_STARTED_MONOTONIC_NS"] =
        $startedMonotonicNs ?? hrtime(true);
    $GLOBALS["PRONTOO_ROUTE_STARTED_UNIX_US"] =
        $startedUnixUs ?? (int) floor(microtime(true) * 1000000);
    register_shutdown_function("telemetry_route_finish_marker");
}

function telemetry_route_identify(string $route): void
{
    if (!empty($GLOBALS["PRONTOO_TELEMETRY_REGISTERED"])) {
        $GLOBALS["PRONTOO_ROUTE_NAME"] = telemetry_route_safe($route);
    }
}

function telemetry_fatal_error(?array $error): ?string
{
    if (!is_array($error)) {
        return null;
    }
    $type = (int) ($error["type"] ?? 0);
    return in_array(
        $type,
        [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR],
        true,
    )
        ? "php_" . $type
        : null;
}

function telemetry_build_event(
    string $route,
    int $startedMonotonicNs,
    int $finishedMonotonicNs,
    int $startedUnixUs,
    int $finishedUnixUs,
    int $statusCode,
    ?string $fatalError = null,
    ?string $release = null,
): array {
    $durationNs = max(0, $finishedMonotonicNs - $startedMonotonicNs);
    $statusCode = $statusCode >= 100 && $statusCode <= 599 ? $statusCode : 200;
    $route = telemetry_route_safe($route);
    return [
        "schema" => telemetry_schema(),
        "evento_id" => substr(
            hash(
                "sha256",
                $route . "|" . $startedUnixUs . "|" . $finishedUnixUs . "|" . $durationNs,
            ),
            0,
            32,
        ),
        "rota" => $route,
        "inicio_utc" => telemetry_utc_from_unix_microseconds($startedUnixUs),
        "fim_utc" => telemetry_utc_from_unix_microseconds($finishedUnixUs),
        "inicio_unix_us" => $startedUnixUs,
        "fim_unix_us" => $finishedUnixUs,
        "inicio_monotonico_ns" => $startedMonotonicNs,
        "fim_monotonico_ns" => $finishedMonotonicNs,
        "duracao_ns" => $durationNs,
        "duracao_ms" => round($durationNs / 1000000, 6, \RoundingMode::HalfAwayFromZero),
        "status_http" => $statusCode,
        "sucesso" => $fatalError === null && $statusCode < 500,
        "erro_fatal" => $fatalError,
        "versao" => mb_trim((string) ($release ??
            (defined("PRONTOO_VERSION") ? PRONTOO_VERSION :
                (defined("BR_LANDING_VERSION") ? BR_LANDING_VERSION : "unknown")))),
    ];
}

function telemetry_normalize_event(array $event): ?array
{
    if ((string) ($event["schema"] ?? "") !== telemetry_schema()) {
        return null;
    }
    $route = telemetry_route_safe((string) ($event["rota"] ?? ""));
    $startedNs = (int) ($event["inicio_monotonico_ns"] ?? -1);
    $finishedNs = (int) ($event["fim_monotonico_ns"] ?? -1);
    $startedUs = (int) ($event["inicio_unix_us"] ?? 0);
    $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
    $durationNs = (int) ($event["duracao_ns"] ?? -1);
    if (
        $route === "unknown" ||
        $startedNs < 0 ||
        $finishedNs < $startedNs ||
        $startedUs <= 0 ||
        $finishedUs <= 0 ||
        $durationNs !== $finishedNs - $startedNs
    ) {
        return null;
    }
    $event["rota"] = $route;
    $event["duracao_ms"] = round($durationNs / 1000000, 6, \RoundingMode::HalfAwayFromZero);
    $event["status_http"] = max(100, min(599, (int) ($event["status_http"] ?? 200)));
    $event["sucesso"] = (bool) ($event["sucesso"] ?? false);
    return $event;
}

function telemetry_json_line(array $event): ?string
{
    $event = telemetry_normalize_event($event);
    if ($event === null) {
        return null;
    }
    $json = json_encode(
        $event,
        JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRESERVE_ZERO_FRACTION,
    );
    return is_string($json) ? $json . "\n" : null;
}

function telemetry_append_event(array $event): bool
{
    $line = telemetry_json_line($event);
    if ($line === null || !telemetry_prepare_storage()) {
        return false;
    }
    $handle = @fopen(telemetry_file(), "ab");
    if (!is_resource($handle)) {
        return false;
    }
    try {
        if (!@flock($handle, LOCK_EX)) {
            return false;
        }
        $written = @fwrite($handle, $line);
        return $written === strlen($line) && @fflush($handle);
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function telemetry_read_events(?int $nowUnixUs = null): array
{
    $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
    $minimumUnixUs = $nowUnixUs - telemetry_retention_microseconds();
    $file = telemetry_file();
    if (!is_file($file)) {
        return [];
    }
    $handle = @fopen($file, "rb");
    if (!is_resource($handle)) {
        return [];
    }
    $events = [];
    try {
        if (!@flock($handle, LOCK_SH)) {
            return [];
        }
        while (($line = fgets($handle)) !== false) {
            $decoded = json_decode(trim($line), true);
            $event = is_array($decoded) ? telemetry_normalize_event($decoded) : null;
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($event !== null && $finishedUs >= $minimumUnixUs) {
                $events[] = $event;
            }
        }
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
    return $events;
}

function telemetry_prune(?int $nowUnixUs = null): int
{
    $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
    $minimumUnixUs = $nowUnixUs - telemetry_retention_microseconds();
    $file = telemetry_file();
    if (!is_file($file)) {
        return 0;
    }
    $handle = @fopen($file, "c+");
    if (!is_resource($handle)) {
        return 0;
    }
    $kept = [];
    $removed = 0;
    try {
        if (!@flock($handle, LOCK_EX)) {
            return 0;
        }
        rewind($handle);
        while (($line = fgets($handle)) !== false) {
            $decoded = json_decode(trim($line), true);
            $event = is_array($decoded) ? telemetry_normalize_event($decoded) : null;
            if (
                $event === null ||
                (int) ($event["fim_unix_us"] ?? 0) < $minimumUnixUs
            ) {
                $removed++;
                continue;
            }
            $normalizedLine = telemetry_json_line($event);
            if ($normalizedLine !== null) {
                $kept[] = $normalizedLine;
            }
        }
        rewind($handle);
        if (!@ftruncate($handle, 0)) {
            return 0;
        }
        foreach ($kept as $line) {
            if (@fwrite($handle, $line) !== strlen($line)) {
                throw new RuntimeException("Falha ao compactar telemetria.");
            }
        }
        @fflush($handle);
        return $removed;
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function telemetry_route_finish_marker(): void
{
    static $finished = false;
    if ($finished || PHP_SAPI === "cli") {
        return;
    }
    $finished = true;
    $finishedMonotonicNs = hrtime(true);
    $finishedUnixUs = (int) floor(microtime(true) * 1000000);
    try {
        $startedMonotonicNs = (int) ($GLOBALS["PRONTOO_ROUTE_STARTED_MONOTONIC_NS"] ?? 0);
        $startedUnixUs = (int) ($GLOBALS["PRONTOO_ROUTE_STARTED_UNIX_US"] ?? 0);
        if ($startedMonotonicNs <= 0 || $startedUnixUs <= 0) {
            return;
        }
        $statusCode = (int) http_response_code();
        $fatalError = telemetry_fatal_error(error_get_last());
        $event = telemetry_build_event(
            (string) ($GLOBALS["PRONTOO_ROUTE_NAME"] ?? "unknown"),
            $startedMonotonicNs,
            $finishedMonotonicNs,
            $startedUnixUs,
            $finishedUnixUs,
            $statusCode,
            $fatalError,
        );
        if (!telemetry_append_event($event)) {
            error_log("[Prontoo telemetria] Evento de rota não persistido.");
        }
        telemetry_prune($finishedUnixUs);
    } catch (Throwable $error) {
        error_log("[Prontoo telemetria] " . $error->getMessage());
    }
}

function telemetry_percentage_variation(int|float $current, int|float $previous): ?float
{
    if ((float) $previous === 0.0) {
        return (float) $current === 0.0 ? 0.0 : null;
    }
    return round((((float) $current - (float) $previous) / (float) $previous) * 100, 6, \RoundingMode::HalfAwayFromZero);
}

function telemetry_nullable_percentage_variation(
    ?float $current,
    ?float $previous,
): ?float {
    if ($current === null || $previous === null) {
        return null;
    }
    return telemetry_percentage_variation($current, $previous);
}

function telemetry_empty_period(): array
{
    return [
        "requests" => 0,
        "duration_ns" => 0,
        "average_ms" => null,
        "landing_requests" => 0,
        "landing_duration_ns" => 0,
        "landing_average_ms" => null,
    ];
}

function telemetry_finalize_period(array $period): array
{
    $requests = max(0, (int) ($period["requests"] ?? 0));
    $landingRequests = max(0, (int) ($period["landing_requests"] ?? 0));
    $period["average_ms"] = $requests > 0
        ? ((int) ($period["duration_ns"] ?? 0) / $requests) / 1000000
        : null;
    $period["landing_average_ms"] = $landingRequests > 0
        ? ((int) ($period["landing_duration_ns"] ?? 0) / $landingRequests) / 1000000
        : null;
    return $period;
}

function telemetry_comparative_summary(?int $nowUnixUs = null): array
{
    $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
    $windowUs = telemetry_comparison_microseconds();
    $currentStartUs = $nowUnixUs - $windowUs;
    $previousStartUs = $currentStartUs - $windowUs;
    $current = telemetry_empty_period();
    $previous = telemetry_empty_period();
    foreach (telemetry_read_events($nowUnixUs) as $event) {
        $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
        if ($finishedUs < $previousStartUs || $finishedUs >= $nowUnixUs) {
            continue;
        }
        $target = $finishedUs >= $currentStartUs ? "current" : "previous";
        if ($target === "current") {
            $current["requests"]++;
            $current["duration_ns"] += (int) ($event["duracao_ns"] ?? 0);
            if ((string) ($event["rota"] ?? "") === "landing") {
                $current["landing_requests"]++;
                $current["landing_duration_ns"] += (int) ($event["duracao_ns"] ?? 0);
            }
        } else {
            $previous["requests"]++;
            $previous["duration_ns"] += (int) ($event["duracao_ns"] ?? 0);
            if ((string) ($event["rota"] ?? "") === "landing") {
                $previous["landing_requests"]++;
                $previous["landing_duration_ns"] += (int) ($event["duracao_ns"] ?? 0);
            }
        }
    }
    $current = telemetry_finalize_period($current);
    $previous = telemetry_finalize_period($previous);
    return [
        "generated_at_unix_us" => $nowUnixUs,
        "current_start_unix_us" => $currentStartUs,
        "previous_start_unix_us" => $previousStartUs,
        "current" => $current,
        "previous" => $previous,
        "variations" => [
            "requests_pct" => telemetry_percentage_variation(
                $current["requests"],
                $previous["requests"],
            ),
            "average_ms_pct" => telemetry_nullable_percentage_variation(
                isset($current["average_ms"])
                    ? (float) $current["average_ms"]
                    : null,
                isset($previous["average_ms"])
                    ? (float) $previous["average_ms"]
                    : null,
            ),
            "landing_requests_pct" => telemetry_percentage_variation(
                $current["landing_requests"],
                $previous["landing_requests"],
            ),
            "landing_average_ms_pct" => telemetry_nullable_percentage_variation(
                isset($current["landing_average_ms"])
                    ? (float) $current["landing_average_ms"]
                    : null,
                isset($previous["landing_average_ms"])
                    ? (float) $previous["landing_average_ms"]
                    : null,
            ),
        ],
    ];
}

function telemetry_route_performance_summary(int $hours = 240): array
{
    $hours = max(1, min(480, $hours));
    $nowUs = (int) floor(microtime(true) * 1000000);
    $startUs = $nowUs - $hours * 3600 * 1000000;
    $routes = [];
    foreach (telemetry_read_events($nowUs) as $event) {
        $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
        if ($finishedUs < $startUs || $finishedUs >= $nowUs) {
            continue;
        }
        $route = (string) ($event["rota"] ?? "unknown");
        if (!isset($routes[$route])) {
            $routes[$route] = [
                "route" => $route,
                "count" => 0,
                "duration_ns" => 0,
                "max_ns" => 0,
                "errors" => 0,
            ];
        }
        $durationNs = max(0, (int) ($event["duracao_ns"] ?? 0));
        $routes[$route]["count"]++;
        $routes[$route]["duration_ns"] += $durationNs;
        $routes[$route]["max_ns"] = max($routes[$route]["max_ns"], $durationNs);
        if (empty($event["sucesso"])) {
            $routes[$route]["errors"]++;
        }
    }
    $total = 0;
    $totalDurationNs = 0;
    foreach ($routes as &$row) {
        $total += $row["count"];
        $totalDurationNs += $row["duration_ns"];
        $row["avg_ms"] = $row["count"] > 0
            ? ($row["duration_ns"] / $row["count"]) / 1000000
            : 0.0;
        $row["max_ms"] = $row["max_ns"] / 1000000;
        unset($row["duration_ns"], $row["max_ns"]);
    }
    unset($row);
    usort(
        $routes,
        static fn(array $a, array $b): int =>
            ($b["avg_ms"] <=> $a["avg_ms"]) ?: strcmp($a["route"], $b["route"]),
    );
    return [
        "hours" => $hours,
        "total" => $total,
        "avg_ms" => $total > 0 ? ($totalDurationNs / $total) / 1000000 : 0.0,
        "routes" => array_values($routes),
        "updated_at" => telemetry_utc_from_unix_microseconds($nowUs),
    ];
}

function telemetry_route_requests_series_20d(?int $nowUnixUs = null): array
{
    $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
    $timezone = telemetry_cuiaba_tz();
    $today = (new DateTimeImmutable("@" . intdiv($nowUnixUs, 1000000)))
        ->setTimezone($timezone)
        ->setTime(0, 0);
    $days = [];
    for ($offset = 19; $offset >= 0; $offset--) {
        $day = $today->modify("-" . $offset . " days");
        $key = $day->format("Y-m-d");
        $days[$key] = [
            "ts" => $day->getTimestamp(),
            "label" => $day->format("d/m"),
            "tooltip" => $day->format("d/m/Y"),
            "value" => 0,
        ];
    }
    foreach (telemetry_read_events($nowUnixUs) as $event) {
        $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
        if ($finishedUs >= $nowUnixUs) {
            continue;
        }
        $key = (new DateTimeImmutable("@" . intdiv($finishedUs, 1000000)))
            ->setTimezone($timezone)
            ->format("Y-m-d");
        if (isset($days[$key])) {
            $days[$key]["value"]++;
        }
    }
    return array_values($days);
}

function telemetry_sequence_records_series_20d(?int $nowUnix = null): array
{
    $timezone = telemetry_cuiaba_tz();
    $today = $nowUnix === null
        ? new DateTimeImmutable("today", $timezone)
        : (new DateTimeImmutable("@" . max(0, $nowUnix)))
  ->setTimezone($timezone)
  ->setTime(0, 0);
    $days = [];
    $select = [];
    $params = [];
    for ($i = 19; $i >= 0; $i--) {
        $day = $today->modify("-" . $i . " days");
        $next = $day->modify("+1 day");
        $key = $day->format("Y-m-d");
        $days[$key] = [
  "key" => $key,
  "label" => $day->format("d"),
  "tooltip" => $day->format("d/m/Y"),
  "value" => 0,
        ];
        $select[] =
  "SUM(CASE WHEN created_at>=? AND created_at<? AND status='committed' THEN mutation_count ELSE 0 END) AS d" .
  (19 - $i);
        $params[] = $day->getTimestamp();
        $params[] = $next->getTimestamp();
    }
    if (
        !function_exists("has_cfg") ||
        !has_cfg() ||
        !function_exists("db_table_exists") ||
        !db_table_exists("pi_action_ledger")
    ) {
        return array_values($days);
    }
    try {
        $sql =
  "SELECT " .
  implode(",", $select) .
  " FROM pi_action_ledger WHERE created_at>=? AND created_at<?";
        $first = array_key_first($days);
        $last = array_key_last($days);
        $params[] = new DateTimeImmutable(
  $first . " 00:00:00",
  $timezone,
        )->getTimestamp();
        $params[] = (new DateTimeImmutable(
  $last . " 00:00:00",
  $timezone,
        ))
  ->modify("+1 day")
  ->getTimestamp();
        $row = q($sql, $params)->fetch() ?: [];
        $index = 0;
        foreach ($days as &$day) {
  $day["value"] = max(0, (int) ($row["d" . $index] ?? 0));
  $index++;
        }
        unset($day);
    } catch (Throwable $error) {
        error_log(
  "[Prontoo login telemetry records] " .
      $error->getMessage(),
        );
    }
    return array_values($days);
}
