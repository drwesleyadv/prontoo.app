<?php
declare(strict_types=1);
@date_default_timezone_set("UTC");
function app_root(): string
{
    return defined("PRONTOO_ROOT") ? PRONTOO_ROOT : dirname(__DIR__, 2);
}
function cfg_file(): string
{
    $env = trim((string) (getenv("PRONTOO_CONFIG_PATH") ?: ""));
    if ($env !== "" && str_starts_with($env, "/")) {
        return $env;
    }
    return app_root() . "/app/config.php";
}
function has_cfg(): bool
{
    return is_file(cfg_file());
}
function now(): string
{
    return (string) time();
}
function app_timezone_safe(string $tz): string
{
    $tz = trim($tz);
    return in_array($tz, timezone_identifiers_list(), true)
        ? $tz
        : "America/Cuiaba";
}
function app_global_admin_timezone(int $userId = 0): string
{
    $userId = $userId > 0 ? $userId : (int) ($_SESSION["uid"] ?? 0);
    if ($userId > 0 && has_cfg() && function_exists("val")) {
        try {
            $tz =
                (string) (val(
                    "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
                    ["global_admin_timezone_user_" . $userId],
                ) ?:
                "");
            if ($tz !== "") {
                return app_timezone_safe($tz);
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo global admin timezone user] " . $e->getMessage(),
            );
        }
    }
    if (has_cfg() && function_exists("val")) {
        try {
            $tz =
                (string) (val(
                    "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
                    ["global_admin_timezone_default"],
                ) ?:
                "");
            if ($tz !== "") {
                return app_timezone_safe($tz);
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo global admin timezone default] " . $e->getMessage(),
            );
        }
    }
    return app_timezone_safe(
        (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? "America/Cuiaba"),
    );
}
function app_force_utc_runtime(): void
{
    @date_default_timezone_set("UTC");
    if (function_exists("pdo") && has_cfg()) {
        try {
            pdo()->exec("SET time_zone='+00:00'");
        } catch (Throwable $e) {
            error_log("[Prontoo timezone UTC session] " . $e->getMessage());
        }
    }
}
function app_timezone_offset_string(string $tz, ?int $timestamp = null): string
{
    $zone = new DateTimeZone(app_timezone_safe($tz));
    $dt = new DateTimeImmutable("@" . ($timestamp ?? time()));
    $offset = $zone->getOffset($dt);
    $sign = $offset < 0 ? "-" : "+";
    $offset = abs($offset);
    return sprintf(
        "%s%02d:%02d",
        $sign,
        intdiv($offset, 3600),
        intdiv($offset % 3600, 60),
    );
}
function app_timezone_offset_minutes(string $tz, ?int $timestamp = null): int
{
    $zone = new DateTimeZone(app_timezone_safe($tz));
    $dt = new DateTimeImmutable("@" . ($timestamp ?? time()));
    return (int) floor($zone->getOffset($dt) / 60);
}
function app_apply_request_timezone(string $tz): void
{
    $GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] = app_timezone_safe($tz);
    app_force_utc_runtime();
}
function app_context_timezone(?array $context = null, int $clinicId = 0): string
{
    if (
        is_array($context) &&
        trim((string) ($context["timezone"] ?? "")) !== ""
    ) {
        return app_timezone_safe((string) $context["timezone"]);
    }
    if ($clinicId > 0 && function_exists("val") && has_cfg()) {
        static $cache = [];
        if (isset($cache[$clinicId])) {
            return $cache[$clinicId];
        }
        try {
            $tz =
                (string) (val(
                    "SELECT timezone FROM pi_clinics WHERE id=? LIMIT 1",
                    [$clinicId],
                ) ?:
                "America/Cuiaba");
            return $cache[$clinicId] = app_timezone_safe($tz);
        } catch (Throwable $e) {
            error_log("[Prontoo clinic timezone] " . $e->getMessage());
        }
    }
    if (function_exists("ctx")) {
        try {
            $c = ctx();
            if (($c["scope"] ?? "") === "clinic") {
                return app_timezone_safe(
                    (string) ($c["timezone"] ?? "America/Cuiaba"),
                );
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    return app_timezone_safe(
        (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? "America/Cuiaba"),
    );
}
function app_now_utc(): DateTimeImmutable
{
    return new DateTimeImmutable("now", new DateTimeZone("UTC"));
}
function app_now_in_timezone(
    int $clinicId = 0,
    ?array $context = null,
): DateTimeImmutable {
    return app_now_utc()->setTimezone(
        new DateTimeZone(app_context_timezone($context, $clinicId)),
    );
}
function app_today_in_timezone(
    int $clinicId = 0,
    ?array $context = null,
): string {
    return app_now_in_timezone($clinicId, $context)->format("Y-m-d");
}
function app_parse_db_utc(null|string|int $value): ?DateTimeImmutable
{
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return null;
    }
    try {
        if (preg_match('/^-?\d+$/', $value)) {
            return new DateTimeImmutable("@" . (int) $value);
        }
        return new DateTimeImmutable($value, new DateTimeZone("UTC"));
    } catch (Throwable $e) {
        return null;
    }
}
function app_db_utc_to_local(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): ?DateTimeImmutable {
    $dt = app_parse_db_utc($value);
    if (!$dt) {
        return null;
    }
    return $dt->setTimezone(
        new DateTimeZone(app_context_timezone($context, $clinicId)),
    );
}
function app_local_to_db_utc(
    ?string $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return "";
    }
    if (class_exists("\\Prontoo\\Core\\Temporal\\PiTime")) {
        return \Prontoo\Core\Temporal\PiTime::fromLocalToUtcTimestamp(
            $value,
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
        );
    }
    try {
        $dt = new DateTimeImmutable(
            $value,
            new DateTimeZone(app_context_timezone($context, $clinicId)),
        );
        return (string) $dt
            ->setTimezone(new DateTimeZone("UTC"))
            ->getTimestamp();
    } catch (Throwable $e) {
        return $value;
    }
}
function app_storage_timestamp(
    null|string|int $value,
    bool $endOfDay = false,
): int {
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return 0;
    }
    if (preg_match('/^-?\d+$/', $value)) {
        return (int) $value;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $value .= $endOfDay ? " 23:59:59" : " 00:00:00";
    }
    $ts = strtotime($value . " UTC");
    if (!$ts) {
        $ts = strtotime($value);
    }
    return $ts ? (int) $ts : 0;
}
function app_date_input_from_storage(null|string|int $value): string
{
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return "";
    }
    if (preg_match('/^-?\d+$/', $value)) {
        return gmdate("Y-m-d", (int) $value);
    }
    return preg_match("/^\d{4}-\d{2}-\d{2}/", $value)
        ? substr($value, 0, 10)
        : "";
}
function app_db_utc_to_local_input(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    $dt = app_db_utc_to_local($value, $clinicId, $context);
    return $dt ? $dt->format("Y-m-d\TH:i") : "";
}
function app_local_day_utc_range(
    string $day,
    int $clinicId = 0,
    ?array $context = null,
): array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $day = app_today_in_timezone($clinicId, $context);
    }
    $zone = new DateTimeZone(app_context_timezone($context, $clinicId));
    $start = new DateTimeImmutable($day . " 00:00:00", $zone);
    $end = $start->modify("+1 day");
    return [
        (string) $start->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
        (string) $end->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
    ];
}
function app_date_only_end_timestamp(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): int {
    $ymd = app_date_input_from_storage($value);
    if ($ymd === "" || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return 0;
    }
    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return 0;
    }
    [, $end] = app_local_day_utc_range($ymd, $clinicId, $context);
    return max(0, (int) $end - 1);
}
function app_time_br(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    $dt = app_db_utc_to_local($value, $clinicId, $context);
    return $dt ? $dt->format("H:i") : "--:--";
}
function app_date_br(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    $dt = app_db_utc_to_local($value, $clinicId, $context);
    return $dt ? $dt->format("d/m/Y") : "—";
}
function app_datetime_br(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    $dt = app_db_utc_to_local($value, $clinicId, $context);
    if (!$dt) {
        return trim((string) ($value ?? "")) ?: "—";
    }
    $m = prontoo_months_br();
    $year = (int) $dt->format("Y");
    $current = (int) app_now_in_timezone($clinicId, $context)->format("Y");
    $date =
        $dt->format("d") .
        " de " .
        $m[(int) $dt->format("n")] .
        ($year === $current ? "" : " de " . $dt->format("Y"));
    return $date . ", às " . $dt->format("H\hi");
}
function patient_display_name(int $patientLinkId, ?int $cid = null): string
{
    try {
        if (function_exists("audit_patient_name_by_link")) {
            $name = trim((string) audit_patient_name_by_link($patientLinkId, $cid));
            if ($name !== "") {
                return $name;
            }
        }
        $params = [$patientLinkId];
        $where = "pp.id=?";
        if ($cid !== null && $cid > 0) {
            $where .= " AND pp.clinic_id=?";
            $params[] = $cid;
        }
        $name = trim((string) (val(
            "SELECT p.full_name FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE $where LIMIT 1",
            $params,
        ) ?: ""));
        return $name !== "" ? $name : "paciente #" . $patientLinkId;
    } catch (Throwable $e) {
        error_log("[Prontoo patient display name] " . $e->getMessage());
        return "paciente #" . $patientLinkId;
    }
}
function maintenance_active(): bool
{
    return has_cfg() && function_exists("meta_get") && meta_get("maintenance_active", "0") === "1";
}
function page_maintenance_notice(): void
{
    $message = function_exists("meta_get")
        ? meta_get(
            "maintenance_message",
            "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.",
        )
        : "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.";
    page(
        "Manutenção programada",
        '<section class="auth widebox"><h1>Prontoo em manutenção</h1><p>' .
            e($message) .
            '</p><p><a class="ghost" href="' .
            e(href("login")) .
            '">Voltar ao início</a></p></section>',
        ["public" => true, "robots" => "noindex,nofollow"],
    );
}
function only_digits(string $s): string
{
    return preg_replace("/\D+/", "", $s) ?? "";
}
function cpf_br(?string $cpf): string
{
    $d = only_digits((string) ($cpf ?? ""));
    return strlen($d) === 11
        ? substr($d, 0, 3) .
                "." .
                substr($d, 3, 3) .
                "." .
                substr($d, 6, 3) .
                "-" .
                substr($d, 9, 2)
        : (string) ($cpf ?? "");
}
function app_config_string(string $key, string $default = ""): string
{
    try {
        if (function_exists("cfg") && has_cfg()) {
            $c = cfg();
            if (isset($c[$key]) && is_scalar($c[$key])) {
                return trim((string) $c[$key]);
            }
        }
    } catch (Throwable $e) {
        error_log("[Prontoo config string] " . $e->getMessage());
    }
    $env = getenv("PRONTOO_" . strtoupper($key));
    return is_string($env) && trim($env) !== "" ? trim($env) : $default;
}
function app_is_production(): bool
{
    $defaultEnv = has_cfg() ? "production" : "development";
    $env = strtolower(
        app_config_string(
            "app_env",
            (string) (getenv("APP_ENV") ?:
            getenv("PRONTOO_ENV") ?:
            $defaultEnv),
        ),
    );
    return in_array($env, ["prod", "production", "prd"], true);
}

function app_canonical_host(): string
{
    $host = app_config_string(
        "canonical_host",
        defined("PRONTOO_CANONICAL_HOST")
            ? (string) PRONTOO_CANONICAL_HOST
            : "",
    );
    $host = strtolower(
        preg_replace(
            "/[^a-zA-Z0-9.\-]/",
            "",
            preg_replace('/:\d+$/', "", $host),
        ) ?:
        "",
    );
    return $host;
}
function request_host_raw(): string
{
    $host = (string) ($_SERVER["HTTP_HOST"] ?? "localhost");
    $host = preg_replace("/[^a-zA-Z0-9\.\-:\[\]]/", "", $host) ?: "localhost";
    return $host;
}
function app_enforce_canonical_host(): void
{
    if (PHP_SAPI === "cli" || !app_is_production()) {
        return;
    }
    $canonical = app_canonical_host();
    if ($canonical === "") {
        return;
    }
    $raw = strtolower(preg_replace('/:\d+$/', "", request_host_raw()) ?: "");
    if (
        $raw === "" ||
        in_array($raw, ["localhost", "127.0.0.1", "::1"], true)
    ) {
        return;
    }
    if ($raw !== $canonical) {
        throw new ProntooHttpError(
            421,
            "Host não reconhecido para esta instalação.",
        );
    }
}

function is_https(): bool
{
    if (function_exists("security_https_active")) {
        return security_https_active();
    }
    return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
        (string) ($_SERVER["SERVER_PORT"] ?? "") === "443";
}
function request_host(): string
{
    $canonical = app_is_production() ? app_canonical_host() : "";
    if ($canonical !== "") {
        return $canonical;
    }
    return request_host_raw();
}
function base_path(): string
{
    $script = (string) ($_SERVER["SCRIPT_NAME"] ?? "/");
    $dir = rtrim(str_replace(["install.php", "index.php"], "", $script), "/");
    return $dir ?: "";
}
function base_url(string $suffix = ""): string
{
    return (is_https() ? "https://" : "http://") .
        request_host() .
        base_path() .
        "/" .
        ltrim($suffix, "/");
}
function href(string $route, array $params = []): string
{
    return (base_path() ?: "") .
        "/?" .
        http_build_query(["r" => $route] + $params);
}
function redirect(string $route, array $params = []): void
{
    if (!headers_sent()) {
        header("Location: " . href($route, $params));
    }
    exit();
}
function route(): string
{
    return preg_replace("/[^a-z0-9_\-]/i", "", $_GET["r"] ?? "login") ?:
        "login";
}
function app_debug(): bool
{
    if (
        app_is_production() &&
        (string) getenv("PRONTOO_ALLOW_PRODUCTION_DEBUG") !== "1"
    ) {
        return false;
    }
    try {
        $c = has_cfg() ? cfg() : [];
        return !empty($c["debug"]);
    } catch (Throwable $e) {
        error_log("[Prontoo debug cfg] " . $e->getMessage());
        return false;
    }
}
function app_public_error_message(
    Throwable $error,
    string $fallback = "Não foi possível concluir esta ação.",
): string {
    $message = trim($error->getMessage());
    if ($message === "" || $error instanceof PDOException) {
        return $fallback;
    }
    if (
        preg_match(
            "/(?:SQLSTATE|\b(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\b|\b(?:table|column|constraint|driver|database)\b|\.php\b|stack trace|\/app\/|\\app\\)/i",
            $message,
        )
    ) {
        return $fallback;
    }
    return mb_substr($message, 0, 240);
}

function app_fail(Throwable $e, int $status = 500): void
{
    $status = $e instanceof ProntooHttpError ? $e->status : $status;
    $http = $e instanceof ProntooHttpError;
    if (!$http || $status >= 500) {
        error_log(
            "[Prontoo fatal] " .
                (function_exists("privacy_sanitize_error_message")
                    ? privacy_sanitize_error_message($e, 240)
                    : $e->getMessage()) .
                " in " .
                (function_exists("privacy_log_file_label")
                    ? privacy_log_file_label($e->getFile())
                    : basename($e->getFile())) .
                ":" .
                $e->getLine(),
        );
        log_runtime_error($e, $status);
    }
    if (!headers_sent()) {
        http_response_code($status);
        header("Content-Type: text/html; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    $title = $http
        ? "Não foi possível continuar com esta sessão."
        : "Não foi possível concluir esta ação.";
    $message = $http
        ? $e->getMessage()
        : "O Prontoo registrou uma instabilidade e evitou continuar em estado inconsistente. Tente novamente em alguns instantes.";
    $detail = app_debug()
        ? "<pre>" .
            e($e->getMessage()) .
            "
" .
            e($e->getFile() . ":" . $e->getLine()) .
            "</pre>"
        : "";
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="canonical" href="https://prontoo.app/"><title>Prontoo — instabilidade temporária</title><meta name="theme-color" content="#334155"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="prontoo-version" content="' .
        e(PRONTOO_VERSION) .
        '"><meta name="csrf-token" content="' .
        e(csrf()) .
        '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" href="/public/assets/favicon-' .
        rawurlencode(PRONTOO_ASSET_REV) .
        '.png" type="image/png"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
        rawurlencode(
            defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
        ) .
        '"></head><body class="public"><main><section class="auth widebox"><h1>' .
        e($title) .
        "</h1><p>" .
        e($message) .
        "</p>" .
        $detail .
        '<p><a class="primary" href="' .
        e(href("login")) .
        '">Voltar ao início</a></p></section></main></body></html>';
    exit();
}
function runtime_self_check(): void
{
    \Prontoo\Core\Install\RuntimeContract::assert(app_root(), PRONTOO_VERSION);
}
function cfg(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = has_cfg() ? require cfg_file() : [];
    return is_array($c) ? $c : [];
}
function storage_path(string $path = ""): string
{
    return app_root() . "/storage" . ($path ? "/" . ltrim($path, "/") : "");
}
function cache_path(string $key): string
{
    $dir = storage_path("cache");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir .
        "/" .
        preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $key) .
        ".json";
}
function cache_get(string $key, int $ttl): mixed
{
    $f = cache_path($key);
    if (!is_file($f) || time() - filemtime($f) > $ttl) {
        return null;
    }
    $raw = @file_get_contents($f);
    if ($raw === false) {
        return null;
    }
    $j = json_decode($raw, true);
    return is_array($j) && array_key_exists("v", $j) ? $j["v"] : null;
}
function cache_set(string $key, mixed $value): mixed
{
    @file_put_contents(
        cache_path($key),
        json_encode(["t" => time(), "v" => $value], JSON_UNESCAPED_UNICODE),
        LOCK_EX,
    );
    return $value;
}
function cache_remember(string $key, int $ttl, callable $fn): mixed
{
    if ($ttl <= 0) {
        try {
            return $fn();
        } catch (Throwable $e) {
            error_log("[Prontoo cache_remember realtime] " . $e->getMessage());
            return null;
        }
    }
    $v = cache_get($key, $ttl);
    if ($v !== null) {
        return $v;
    }
    try {
        return cache_set($key, $fn());
    } catch (Throwable $e) {
        error_log("[Prontoo cache_remember] " . $e->getMessage());
        return null;
    }
}
function cached_val(string $key, int $ttl, string $sql, array $p = []): mixed
{
    $ttl = max(0, $ttl);
    if ($ttl <= 0) {
        return val($sql, $p);
    }
    $ck =
        "sql_" .
        $key .
        "_" .
        hash(
            "sha256",
            $sql .
                "|" .
                json_encode(
                    $p,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
        );
    if (
        function_exists("server_json_cache_remember") &&
        server_json_cache_read_allowed()
    ) {
        return server_json_cache_remember(
            "warm",
            $ck,
            $ttl,
            fn() => val($sql, $p),
            ["sql"],
        );
    }
    $cached = cache_get($ck, $ttl);
    if ($cached !== null) {
        return $cached;
    }
    return cache_set($ck, val($sql, $p));
}
function bounded_limit(int $n, int $max = PRONTOO_HOT_LIST_LIMIT): int
{
    return max(1, min($max, $n));
}
function counter_key(string $name): string
{
    return preg_replace("/[^a-z0-9_\-\.]/i", "_", $name) ?: "counter";
}
function counter_inc(string $name, int $by = 1): void
{
    try {
        q(
            "INSERT INTO pi_platform_counters (counter_key,counter_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE counter_value=counter_value+VALUES(counter_value), updated_at=NOW()",
            [counter_key($name), $by],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo counter_inc] " . $e->getMessage());
    }
}
function counter_get(string $name, int $fallback = 0): int
{
    try {
        return (int) (val(
            "SELECT counter_value FROM pi_platform_counters WHERE counter_key=?",
            [counter_key($name)],
        ) ?? $fallback);
    } catch (Throwable $e) {
        error_log("[Prontoo counter_get] " . $e->getMessage());
        return $fallback;
    }
}
function prontoo_login_selftest_light(): array
{
    $ok = true;
    $checks = ["mode" => "login_light", "version" => PRONTOO_VERSION];
    try {
        $dbOk = (int) pdo()->query("SELECT 1")->fetchColumn() === 1;
        $checks["db"] = $dbOk;
        $checks["database"] = $dbOk;
        $ok = $ok && $dbOk;
    } catch (Throwable $e) {
        $checks["db"] = false;
        $checks["database"] = false;
        $checks["db_error_hash"] = hash("sha256", $e->getMessage());
        $ok = false;
    }
    try {
        $runtimeOk =
            db_table_exists("pi_meta");
        $checks["pi_runtime"] = $runtimeOk;
    } catch (Throwable $e) {
        $checks["pi_runtime"] = false;
    }
    try {
        $storageDir = function_exists("storage_path")
            ? storage_path()
            : app_root();
        $free = @disk_free_space($storageDir);
        $writable = is_dir($storageDir) && is_writable($storageDir);
        $freeOk = $free === false ? true : $free > 20 * 1024 * 1024;
        $checks["storage"] = $writable && $freeOk;
        $checks["storage_free_bytes"] = $free === false ? null : (float) $free;
        $ok = $ok && (bool) $checks["storage"];
    } catch (Throwable $e) {
        $checks["storage"] = false;
        $ok = false;
    }
    $checks["maintenance_deferred"] = true;
    $checks["ok"] = $ok;
    return $checks;
}
function page_login_autotest(): void
{
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    if (
        security_rate_limit(security_client_bucket("login_autotest"), 90, 300)
    ) {
        http_response_code(429);
        echo json_encode(
            [
                "ok" => false,
                "auto_login" => false,
                "redirect" => "",
                "version" => PRONTOO_VERSION,
                "mode" => "login_light",
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        exit();
    }
    $checks = prontoo_login_selftest_light();
    $auto = false;
    $redirect = "";
    if (!empty($checks["ok"]) && device_session_auto_login()) {
        $auto = true;
        $c = ctx();
        $redirect = href(
            ($c["scope"] ?? "") === "global" ? "admin_painel" : "appointments",
        );
    }
    if (function_exists("platform_login_loaded_audit")) {
        platform_login_loaded_audit($checks, $auto);
    } elseif (function_exists("audit")) {
        try {
            audit("login_autoteste_leve", "login", null, [
                "ok" => !empty($checks["ok"]) ? 1 : 0,
                "maintenance_deferred" => 1,
                "audit_body" =>
                    "Autoteste leve do login concluído; diagnósticos e manutenção pesada ficam para depois da senha correta.",
            ]);
        } catch (Throwable $e) {
            error_log("[Prontoo light login audit] " . $e->getMessage());
        }
    }
    echo json_encode(
        [
            "ok" => !empty($checks["ok"]),
            "auto_login" => $auto,
            "redirect" => $redirect,
            "version" => PRONTOO_VERSION,
            "mode" => $checks["mode"] ?? "login_light",
            "maintenance_deferred" => true,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    exit();
}
function person_signature_value(
    string $identity,
    string $name,
    ?string $date,
): string {
    $base =
        only_digits($identity) .
        "|" .
        mb_strtolower(trim($name), "UTF-8") .
        "|" .
        trim((string) $date);
    return hash_hmac("sha256", $base, secret_key());
}
function person_signature_sync(int $personId, bool $verify = false): void
{
    if ($personId <= 0) {
        return;
    }
    try {
        $person = one(
            "SELECT id,full_name,cpf,birth_date,assinatura FROM pi_persons WHERE id=?",
            [$personId],
        );
        if (!$person) {
            if ($verify) {
                throw new RuntimeException("Pessoa não encontrada para validar a identidade.");
            }
            return;
        }
        $expected = person_signature_value(
            (string) ($person["cpf"] ?? ""),
            (string) ($person["full_name"] ?? ""),
            (string) ($person["birth_date"] ?? ""),
        );
        $current = trim((string) ($person["assinatura"] ?? ""));
        if ($current === "" || !hash_equals($expected, $current)) {
            q(
                "UPDATE pi_persons SET assinatura=?, updated_at=COALESCE(updated_at,NOW()) WHERE id=?",
                [$expected, $personId],
            );
        }
        if ($verify) {
            $stored = trim((string) val(
                "SELECT assinatura FROM pi_persons WHERE id=?",
                [$personId],
            ));
            if ($stored === "" || !hash_equals($expected, $stored)) {
                throw new RuntimeException(
                    "A assinatura de identidade da pessoa não pôde ser atualizada.",
                );
            }
        }
    } catch (Throwable $e) {
        if ($verify) {
            throw $e;
        }
        error_log("[Prontoo person signature] " . $e->getMessage());
    }
}
function person_signature_refresh(int $personId): void
{
    person_signature_sync($personId, false);
}
function person_signature_refresh_verified(int $personId): void
{
    person_signature_sync($personId, true);
}
function person_identity_immutable_values(
    int $personId,
    ?string $cpfInput = null,
    ?string $birthInput = null,
    bool $requireMissing = false,
): array {
    $current =
        $personId > 0
            ? (one("SELECT cpf,birth_date FROM pi_persons WHERE id=?", [
                $personId,
            ]) ?:
            [])
            : [];
    $currentCpf = only_digits((string) ($current["cpf"] ?? ""));
    $currentBirthRaw = trim((string) ($current["birth_date"] ?? ""));
    $currentBirth = app_date_input_from_storage($currentBirthRaw);
    $newCpf = only_digits((string) ($cpfInput ?? ""));
    $newBirth = app_date_input_from_storage(trim((string) ($birthInput ?? "")));
    if ($currentCpf !== "") {
        if ($newCpf !== "" && $newCpf !== $currentCpf) {
            throw new RuntimeException(
                "CPF é dado de identidade imutável e não pode ser alterado.",
            );
        }
        $newCpf = $currentCpf;
    } else {
        if ($newCpf !== "" && !valid_cpf($newCpf)) {
            throw new RuntimeException("Este CPF não existe.");
        }
        if ($requireMissing && $newCpf === "") {
            throw new RuntimeException("Informe um CPF válido.");
        }
    }
    if ($currentBirth !== "") {
        if ($newBirth !== "" && $newBirth !== $currentBirth) {
            throw new RuntimeException(
                "Nascimento é dado de identidade imutável e não pode ser alterado.",
            );
        }
        $newBirth = $currentBirthRaw !== "" ? $currentBirthRaw : $currentBirth;
    } else {
        if ($newBirth !== "" && !valid_birth_date($newBirth)) {
            throw new RuntimeException("Nascimento inválido.");
        }
        if ($requireMissing && $newBirth === "") {
            throw new RuntimeException("Nascimento inválido.");
        }
    }
    return ["cpf" => $newCpf, "birth_date" => $newBirth];
}
function count_recent_or_counter(
    string $counter,
    string $sql,
    array $p = [],
    int $ttl = PRONTOO_DASHBOARD_COUNTER_TTL,
): int {
    try {
        return (int) cached_val("counter_" . $counter, max(0, $ttl), $sql, $p);
    } catch (Throwable $e) {
        error_log(
            "[Prontoo realtime counter] " . $counter . " " . $e->getMessage(),
        );
        return 0;
    }
}
function meta_cache_ttl(string $key): int
{
    if ($key === "auth_generation") {
        return 15;
    }
    if (str_starts_with($key, "global_admin_timezone_")) {
        return 300;
    }
    if (in_array($key, ["signup_clinics_blocked", "maintenance"], true)) {
        return 30;
    }
    return 60;
}
function meta_get(string $key, mixed $default = null): mixed
{
    $loader = function () use ($key, $default): mixed {
        try {
            $value = val("SELECT meta_value FROM pi_meta WHERE meta_key=?", [$key]);
            return $value === null ? $default : $value;
        } catch (Throwable $e) {
            error_log("[Prontoo meta_get] " . $e->getMessage());
            return $default;
        }
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "meta",
            server_json_cache_safe_key("meta", [$key]),
            meta_cache_ttl($key),
            $loader,
            ["meta:" . $key],
        );
    }
    return $loader();
}
function meta_set(string $key, mixed $value): void
{
    q(
        "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), updated_at=NOW()",
        [$key, (string) $value],
    );
    if (function_exists("server_json_cache_clear_categories")) {
        $categories = ["meta"];
        if ($key === "auth_generation") {
            $categories[] = "context";
        }
        if (str_starts_with($key, "global_admin_timezone_") || $key === "maintenance") {
            $categories[] = "context";
            $categories[] = "clinic";
        }
        server_json_cache_clear_categories($categories);
    }
}
function clinic_signup_blocked(): bool
{
    if (!function_exists("has_cfg") || !has_cfg()) {
        return false;
    }
    return (string) meta_get("signup_clinics_blocked", "0") === "1";
}
function log_runtime_error(Throwable $e, int $status = 500): void
{
    $message = function_exists("privacy_sanitize_error_message")
        ? privacy_sanitize_error_message($e, 900)
        : mb_substr($e->getMessage(), 0, 900);
    $file = function_exists("privacy_log_file_label")
        ? privacy_log_file_label($e->getFile())
        : basename($e->getFile());
    $dir = storage_path("logs");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($dir);
    }
    $line =
        "[" .
        date("c") .
        "] " .
        route() .
        " " .
        $status .
        " " .
        $message .
        " @ " .
        $file .
        ":" .
        $e->getLine() .
        "
";
    @file_put_contents($dir . "/runtime.log", $line, FILE_APPEND | LOCK_EX);
    @chmod($dir . "/runtime.log", 0640);
    if (!has_cfg() || db_temp_space_error($e)) {
        return;
    }
    try {
        $hash = hash(
            "sha256",
            route() .
                "|" .
                $status .
                "|" .
                $message .
                "|" .
                $file .
                "|" .
                $e->getLine(),
        );
        $throttle = cache_get("err_" . $hash, 60);
        if ($throttle) {
            return;
        }
        cache_set("err_" . $hash, 1);
        q(
            "INSERT INTO pi_error_events (route,method,http_status,message,file,line,user_id,clinic_id,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [
                route(),
                (string) ($_SERVER["REQUEST_METHOD"] ?? "GET"),
                $status,
                $message,
                mb_substr($file, 0, 255),
                $e->getLine(),
                $_SESSION["uid"] ?? null,
                session_clinic_scope_id() ?: null,
            ],
        );
    } catch (Throwable $ignored) {
        error_log(
            "[Prontoo error_event] " .
                (function_exists("privacy_sanitize_error_message")
                    ? privacy_sanitize_error_message($ignored, 240)
                    : $ignored->getMessage()),
        );
    }
}
function person_common_profile_schema_ready(): void
{
    ensure_runtime_schema_minimum();
}
function person_common_profile_from_array(
    array $data,
    string $prefix = "",
): array {
    $k = function (string $name) use ($data, $prefix) {
        return trim((string) ($data[$prefix . $name] ?? ""));
    };
    $doc = only_digits($k("legal_document"));
    $type = $k("legal_type");
    if ($type === "" && $doc !== "") {
        $type = strlen($doc) === 14 ? "cnpj" : "cpf";
    }
    if (!in_array($type, ["cpf", "cnpj"], true)) {
        $type = null;
    }
    $email = $k("email");
    $phone = phone_br($k("phone"));
    $uf = strtoupper($k("address_state"));
    $city = $k("address_city");
    $cityIbge = (int) ($data[$prefix . "address_city_ibge"] ?? 0);
    return [
        "legal_type" => $type,
        "legal_document" => $doc !== "" ? $doc : null,
        "phone" => $phone !== "" ? $phone : null,
        "email" => $email !== "" ? $email : null,
        "address_zip" => only_digits($k("address_zip")) ?: null,
        "address" => $k("address") ?: null,
        "address_number" => $k("address_number") ?: null,
        "address_neighborhood" => $k("address_neighborhood") ?: null,
        "address_complement" => $k("address_complement") ?: null,
        "address_state" => $uf ?: null,
        "address_city" => $city ?: null,
        "address_city_ibge" => $cityIbge > 0 ? $cityIbge : null,
    ];
}
function person_common_profile_update(int $personId, array $profile): void
{
    if ($personId <= 0) {
        return;
    }
    person_common_profile_schema_ready();
    $allowed = [
        "legal_type",
        "legal_document",
        "phone",
        "email",
        "address_zip",
        "address",
        "address_number",
        "address_neighborhood",
        "address_complement",
        "address_state",
        "address_city",
        "address_city_ibge",
    ];
    $sets = [];
    $vals = [];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $profile)) {
            continue;
        }
        $val = $profile[$col];
        if (
            $col === "email" &&
            $val !== null &&
            $val !== "" &&
            !filter_var((string) $val, FILTER_VALIDATE_EMAIL)
        ) {
            $val = null;
        }
        $sets[] = $col . "=?";
        $vals[] = $val !== "" ? $val : null;
    }
    if (!$sets) {
        return;
    }
    $sets[] = "updated_at=NOW()";
    $vals[] = $personId;
    try {
        q(
            "UPDATE pi_persons SET " . implode(",", $sets) . " WHERE id=?",
            $vals,
        );
    } catch (Throwable $e) {
        error_log("[Prontoo pessoas perfil comum update] " . $e->getMessage());
    }
}
function person_common_profile_fields_html(
    int $cid,
    array $p = [],
    string $prefix = "",
    bool $includeEmail = true,
    bool $includePhone = true,
): string {
    $get = function (string $k, string $def = "") use ($p) {
        return (string) ($p[$k] ?? $def);
    };
    $uf = $get("address_state");
    $city = $get("address_city");
    $cityIbge = $get("address_city_ibge");
    $cityOptions =
        $city !== ""
            ? '<option value="' .
                e($city) .
                '" data-ibge="' .
                e($cityIbge) .
                '" selected>' .
                e($city) .
                "</option>"
            : '<option value="">Escolha primeiro o estado</option>';
    $states =
        ["" => "Escolha o estado"] +
        (function_exists("br_states") ? br_states() : []);
    $contact = "";
    $contactFields = "";
    if ($includePhone) {
        $contactFields .= form_row(
            "Telefone",
            input(
                $prefix . "phone",
                "text",
                $get("phone"),
                'autocomplete="tel" inputmode="tel" placeholder="(00) 00000-0000"',
            ),
        );
    }
    if ($includeEmail) {
        $contactFields .= form_row(
            "E-mail",
            input(
                $prefix . "email",
                "email",
                $get("email"),
                'autocomplete="email"',
            ),
        );
    }
    if ($contactFields !== "") {
        $contact = '<div class="two">' . $contactFields . "</div>";
    }
    return $contact .
        '<section class="patient-address-block person-common-address-block" data-patient-address-block>' .
        '<div class="two">' .
        form_row(
            "CEP",
            input(
                $prefix . "address_zip",
                "text",
                $get("address_zip"),
                'inputmode="numeric" maxlength="9" autocomplete="postal-code" data-patient-address-zip',
            ),
        ) .
        form_row(
            "Logradouro",
            input(
                $prefix . "address",
                "text",
                $get("address"),
                'maxlength="255" autocomplete="address-line1" data-patient-address-street',
            ),
        ) .
        '</div><div class="two">' .
        form_row(
            "Número",
            input(
                $prefix . "address_number",
                "text",
                $get("address_number"),
                'maxlength="20" autocomplete="address-line2" data-patient-address-number',
            ),
        ) .
        form_row(
            "Bairro",
            input(
                $prefix . "address_neighborhood",
                "text",
                $get("address_neighborhood"),
                'maxlength="120" data-patient-address-neighborhood',
            ),
        ) .
        '</div><div class="two">' .
        form_row(
            "Complemento",
            input(
                $prefix . "address_complement",
                "text",
                $get("address_complement"),
                'maxlength="120" autocomplete="address-line3" data-patient-address-complement',
            ),
        ) .
        select_label(
            "Estado",
            $prefix . "address_state",
            $states,
            $uf,
            "data-br-state data-patient-address-state",
        ) .
        "</div>" .
        form_row(
            "Cidade",
            '<select name="' .
                e($prefix . "address_city") .
                '" data-br-city data-selected-city="' .
                e($city) .
                '" data-patient-address-city>' .
                $cityOptions .
                '</select><input type="hidden" name="' .
                e($prefix . "address_city_ibge") .
                '" value="' .
                e($cityIbge) .
                '" data-br-city-ibge data-patient-address-ibge>',
        ) .
        "</section>";
}
