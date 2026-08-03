<?php
declare(strict_types=1);

function server_json_cache_enabled(): bool
{

    if (defined("PRONTOO_SERVER_JSON_CACHE") && !PRONTOO_SERVER_JSON_CACHE) {
        return false;
    }
    return (string) getenv("PRONTOO_DISABLE_SERVER_JSON_CACHE") !== "1";
}

function server_json_cache_root(): string
{

    static $resolved = null;
    if (is_string($resolved)) {
        return $resolved;
    }
    $resolved = storage_path("cache/server-json");
    if (!is_dir($resolved) && !@mkdir($resolved, 0750, true) && !is_dir($resolved)) {
        return $resolved;
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($resolved);
    } else {
        $deny = $resolved . "/.htaccess";
        if (!is_file($deny)) {
            @file_put_contents($deny, "Require all denied\n", LOCK_EX);
        }
    }
    return $resolved;
}

function server_json_cache_category_dir(string $category): string
{

    static $resolved = [];
    $category = preg_replace("/[^a-z0-9_\-]/i", "_", $category) ?: "general";
    if (isset($resolved[$category])) {
        return $resolved[$category];
    }
    $dir = server_json_cache_root() . "/" . $category;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return $resolved[$category] = $dir;
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($dir);
    }
    return $resolved[$category] = $dir;
}

function server_json_cache_generation_file(string $category): string
{

    return server_json_cache_category_dir($category) . "/generation.txt";
}

function server_json_cache_generation(string $category): int
{

    $category = preg_replace("/[^a-z0-9_\-]/i", "_", $category) ?: "general";
    $generations = $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"] ?? [];
    if (is_array($generations) && isset($generations[$category])) {
        return max(1, (int) $generations[$category]);
    }
    $raw = @file_get_contents(server_json_cache_generation_file($category));
    $generation = max(1, (int) trim(is_string($raw) ? $raw : "1"));
    if (!isset($GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"]) ||
        !is_array($GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"])) {
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"] = [];
    }
    $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"][$category] = $generation;
    return $generation;
}

function server_json_cache_bump_generation(string $category): int
{

    $category = preg_replace("/[^a-z0-9_\-]/i", "_", $category) ?: "general";
    $dir = server_json_cache_category_dir($category);
    $file = server_json_cache_generation_file($category);
    $handle = @fopen($file, "c+");
    if (!is_resource($handle)) {
        server_json_cache_rrmdir($dir);
        @mkdir($dir, 0750, true);
        @file_put_contents($file, "1\n", LOCK_EX);
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"][$category] = 1;
        return 1;
    }
    try {
        if (!@flock($handle, LOCK_EX)) {
            throw new RuntimeException("Não foi possível bloquear a geração do cache.");
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $current = max(1, (int) trim(is_string($raw) ? $raw : "1"));
        $next = $current >= PHP_INT_MAX - 1 ? 1 : $current + 1;
        rewind($handle);
        ftruncate($handle, 0);
        if (fwrite($handle, (string) $next . "\n") === false || !fflush($handle)) {
            throw new RuntimeException("Não foi possível persistir a geração do cache.");
        }
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"][$category] = $next;
        return $next;
    } catch (Throwable $error) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
        server_json_cache_rrmdir($dir);
        @mkdir($dir, 0750, true);
        @file_put_contents($file, "1\n", LOCK_EX);
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"][$category] = 1;
        error_log("[Prontoo cache generation] " . $error->getMessage());
        return 1;
    } finally {
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
}

function server_json_cache_ttl(string $category): int
{

    return match ($category) {
        
        "hot", "agenda", "recepcao", "financial", "gavetas" => 20,
        "warm", "kpi", "cards", "dashboard" => 45,

        "context" => 60,
        "cmdbar", "permissions" => 120,

        "clinic", "catalog", "work_hours", "templates" => 300,
        "lookup", "auxiliary" => 300,
        "meta" => 60,
        "cold" => 900,
        default => 120,
    };
}

function server_json_cache_safe_key(string $namespace, mixed $keyParts): string
{

    $namespace = preg_replace("/[^a-z0-9_\-]/i", "_", $namespace) ?: "cache";
    $payload = is_string($keyParts)
        ? $keyParts
        : json_encode(
            $keyParts,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    return $namespace . "_" . hash("sha256", (string) $payload);
}

function server_json_cache_file(string $category, string $key): string
{

    $key = preg_replace("/[^a-z0-9_\-\.]/i", "_", $key) ?: "cache";
    $categoryDir = server_json_cache_category_dir($category);
    $generationDir =
        $categoryDir .
        "/generation-" .
        sprintf("%020d", server_json_cache_generation($category));
    if (!is_dir($generationDir) &&
        !@mkdir($generationDir, 0750, true) &&
        !is_dir($generationDir)) {
        return $generationDir . "/" . $key . ".json";
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($generationDir);
    }
    return $generationDir . "/" . $key . ".json";
}

function server_json_cache_request_is_read(): bool
{

    if (PHP_SAPI === "cli") {
        return false;
    }
    return strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")) === "GET";
}

function server_json_cache_read_allowed(): bool
{

    return server_json_cache_enabled() &&
        function_exists("storage_path") &&
        server_json_cache_request_is_read();
}

function server_json_cache_write_allowed(): bool
{

    return server_json_cache_read_allowed();
}

function server_json_cache_memory_get(string $file, bool &$found): mixed
{

    $memory = $GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"] ?? [];
    if (is_array($memory) && array_key_exists($file, $memory)) {
        $found = true;
        return $memory[$file];
    }
    $found = false;
    return null;
}

function server_json_cache_memory_set(string $file, mixed $value): void
{

    if (!isset($GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"]) ||
        !is_array($GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"])) {
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"] = [];
    }
    $GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"][$file] = $value;
}

function server_json_cache_memory_forget_prefix(string $prefix): void
{

    $memory = $GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"] ?? [];
    if (!is_array($memory)) {
        return;
    }
    foreach (array_keys($memory) as $file) {
        if (str_starts_with((string) $file, $prefix)) {
            unset($GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"][$file]);
        }
    }
}

function server_json_cache_get(
    string $category,
    string $key,
    ?int $ttlSeconds = null,
): mixed {

    if (!server_json_cache_read_allowed()) {
        return null;
    }
    $ttl = $ttlSeconds ?? server_json_cache_ttl($category);
    if ($ttl <= 0) {
        return null;
    }
    $file = server_json_cache_file($category, $key);
    $memoryFound = false;
    $memoryValue = server_json_cache_memory_get($file, $memoryFound);
    if ($memoryFound) {
        return $memoryValue;
    }
    if (!is_file($file)) {
        return null;
    }
    $mtime = @filemtime($file);
    if ($mtime === false || time() - $mtime > $ttl) {
        @unlink($file);
        return null;
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || trim($raw) === "") {
        @unlink($file);
        return null;
    }
    $json = json_decode($raw, true);
    if (!is_array($json) || !array_key_exists("value", $json)) {
        @unlink($file);
        return null;
    }
    if (($json["server_side_only"] ?? "") !== "storage-json") {
        @unlink($file);
        return null;
    }
    if ((int) ($json["expires_at"] ?? 0) < time()) {
        @unlink($file);
        return null;
    }
    server_json_cache_memory_set($file, $json["value"]);
    return $json["value"];
}

function server_json_cache_set(
    string $category,
    string $key,
    mixed $value,
    ?int $ttlSeconds = null,
    array $tags = [],
): mixed {

    if (!server_json_cache_write_allowed()) {
        return $value;
    }
    $ttl = $ttlSeconds ?? server_json_cache_ttl($category);
    if ($ttl <= 0) {
        return $value;
    }
    $now = time();
    $payload = [
        "server_side_only" => "storage-json",
        "category" => $category,
        "key" => $key,
        "created_at" => $now,
        "expires_at" => $now + $ttl,
        "ttl_seconds" => $ttl,
        "tags" => array_values(array_filter(array_map("strval", $tags))),
        "value" => $value,
    ];
    $encoded = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    if ($encoded === false) {
        return $value;
    }
    $file = server_json_cache_file($category, $key);
    try {
        $suffix = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        error_log("[Prontoo server JSON cache random] " . $e->getMessage());
        return $value;
    }
    $tmp = $file . ".tmp." . getmypid() . "." . $suffix;
    if (@file_put_contents($tmp, $encoded, LOCK_EX) !== false) {
        @chmod($tmp, 0640);
        if (@rename($tmp, $file)) {
            @chmod($file, 0640);
        } else {
            @unlink($tmp);
        }
    } elseif (is_file($tmp)) {
        @unlink($tmp);
    }
    server_json_cache_memory_set($file, $value);
    return $value;
}

function server_json_cache_remember(
    string $category,
    string $key,
    ?int $ttlSeconds,
    callable $loader,
    array $tags = [],
): mixed {

    $cached = server_json_cache_get($category, $key, $ttlSeconds);
    if ($cached !== null) {
        return $cached;
    }
    if (!server_json_cache_read_allowed()) {
        return $loader();
    }

    $file = server_json_cache_file($category, $key);
    $lock = @fopen($file . ".lock", "c");
    $locked = false;
    if (is_resource($lock)) {
        @chmod($file . ".lock", 0640);
        $deadline = microtime(true) + 0.05;
        do {
            $locked = @flock($lock, LOCK_EX | LOCK_NB);
            if (!$locked) {
                usleep(5000);
            }
        } while (!$locked && microtime(true) < $deadline);
    }
    if ($locked && is_resource($lock)) {
        try {
            unset($GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"][$file]);
            $cached = server_json_cache_get($category, $key, $ttlSeconds);
            if ($cached !== null) {
                return $cached;
            }
            $value = $loader();
            return server_json_cache_set(
                $category,
                $key,
                $value,
                $ttlSeconds,
                $tags,
            );
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    if (is_resource($lock)) {
        fclose($lock);
    }
    return $loader();
}

function server_json_cache_rrmdir(string $dir): void
{

    if (!is_dir($dir)) {
        return;
    }
    $items = @scandir($dir);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === "." || $item === ".." || $item === ".htaccess") {
            continue;
        }
        $path = $dir . "/" . $item;
        if (is_dir($path)) {
            server_json_cache_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
}

function server_json_cache_clear_categories(array $categories): void
{

    $categories = array_values(array_unique(array_map("strval", $categories)));
    foreach ($categories as $category) {
        $dir = server_json_cache_category_dir($category);
        server_json_cache_bump_generation($category);
        server_json_cache_memory_forget_prefix($dir . "/");
    }
}

function server_json_cache_all_categories(): array
{

    return [
        "hot", "agenda", "recepcao", "financial", "gavetas",
        "warm", "kpi", "cards", "dashboard", "context", "cmdbar",
        "permissions", "clinic", "catalog", "work_hours", "templates",
        "lookup", "auxiliary", "meta", "cold",
    ];
}

function server_json_cache_clear_all_runtime(): void
{

    server_json_cache_clear_categories(server_json_cache_all_categories());
}

function server_json_cache_clear_all_json_files(): int
{

    $root = storage_path("cache");
    if (!is_dir($root)) {
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"] = [];
        return 0;
    }
    $deleted = 0;
    $walk = static function (string $directory) use (&$walk, &$deleted): void {

        $items = @scandir($directory);
        if (!is_array($items)) {
            throw new RuntimeException(
                "Não foi possível ler o diretório de cache JSON.",
            );
        }
        foreach ($items as $item) {
            if ($item === "." || $item === "..") {
                continue;
            }
            $path = $directory . "/" . $item;
            if (is_link($path)) {
                continue;
            }
            if (is_dir($path)) {
                $walk($path);
                continue;
            }
            if (!is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== "json") {
                continue;
            }
            if (!@unlink($path) && is_file($path)) {
                throw new RuntimeException(
                    "Não foi possível remover um arquivo de cache JSON.",
                );
            }
            $deleted++;
        }
    };
    $walk($root);
    $GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"] = [];
    return $deleted;
}

function server_json_cache_write_categories(string $route, string $act): array
{

    $route = strtolower(trim($route));
    $act = strtolower(trim($act));

    if ($route === "logout") {
        return [];
    }

    $categories = [
        "hot", "agenda", "recepcao", "financial", "gavetas",
        "warm", "kpi", "cards", "dashboard",
    ];

    if (in_array($route, [
        "login", "logout", "switch", "profile", "signup", "onboarding", "users",
        "user", "permissions", "settings", "admin_users", "admin_people",
        "admin_clinics", "admin_onboarding", "admin_settings",
        "admin_maintenance", "admin_payment_proof",
    ], true)) {
        $categories = array_merge($categories, [
            "context", "cmdbar", "permissions", "clinic", "catalog",
            "work_hours", "lookup", "auxiliary", "meta",
        ]);
    }
    if ($route === "procedures") {
        $categories = array_merge($categories, ["catalog", "templates"]);
    }
    if (in_array($route, ["patient", "patients", "leads"], true)) {
        $categories = array_merge($categories, ["lookup", "auxiliary"]);
    }
    if ($route === "documents") {
        $categories = array_merge($categories, [
            "templates", "catalog", "lookup", "auxiliary",
        ]);
    }
    if (in_array($route, ["leads", "tasks", "notices", "audit"], true)) {
        $categories = array_merge($categories, ["lookup", "auxiliary"]);
    }
    if (str_contains($act, "role") || str_contains($act, "permission")) {
        $categories = array_merge($categories, [
            "context", "cmdbar", "permissions", "clinic", "work_hours",
        ]);
    }
    return array_values(array_unique($categories));
}

function server_json_cache_invalidate_for_write(
    string $route = "",
    string $act = "",
): void {

    server_json_cache_clear_categories(
        server_json_cache_write_categories($route, $act),
    );
}

function server_json_cache_register_deferred_invalidation(): void
{

    if (!empty($GLOBALS["PRONTOO_CACHE_INVALIDATE_SHUTDOWN_REGISTERED"])) {
        return;
    }
    $GLOBALS["PRONTOO_CACHE_INVALIDATE_SHUTDOWN_REGISTERED"] = true;
    register_shutdown_function(static function (): void {

        $pending = $GLOBALS["PRONTOO_CACHE_INVALIDATE_AFTER_WRITE"] ?? [];
        if (is_array($pending) && $pending) {
            server_json_cache_clear_categories($pending);
        }
        $GLOBALS["PRONTOO_CACHE_INVALIDATE_AFTER_WRITE"] = [];
    });
}
function server_json_cache_schedule_invalidation_for_write(
    string $route = "",
    string $act = "",
): void {

    $categories = server_json_cache_write_categories($route, $act);
    $pending = $GLOBALS["PRONTOO_CACHE_INVALIDATE_AFTER_WRITE"] ?? [];
    $GLOBALS["PRONTOO_CACHE_INVALIDATE_AFTER_WRITE"] = array_values(
        array_unique(array_merge(is_array($pending) ? $pending : [], $categories)),
    );
    server_json_cache_register_deferred_invalidation();
}

function server_json_cache_context_key(
    int $uid,
    string $scope,
    int $clinicId,
    int $ucId,
    string $role,
): string {

    return server_json_cache_safe_key("ctx", [
        "uid" => $uid,
        "scope" => $scope,
        "clinic_id" => $clinicId,
        "uc_id" => $ucId,
        "role" => $role,
        "user_auth_generation" =>
            (string) ($_SESSION["user_auth_generation"] ?? ""),
        "version" => defined("PRONTOO_VERSION") ? PRONTOO_VERSION : "",
        "schema" => defined("PRONTOO_SCHEMA_REV") ? PRONTOO_SCHEMA_REV : "",
    ]);
}

function server_json_cache_sanitize_context(array $ctx): array
{

    if (isset($ctx["user"]) && is_array($ctx["user"])) {
        unset(
            $ctx["user"]["password_hash"],
            $ctx["user"]["failed_login_count"],
            $ctx["user"]["locked_until"],
        );
    }
    return $ctx;
}

function server_json_cache_apply_context_session(array $ctx): void
{

    if (($ctx["scope"] ?? "") === "global") {
        $_SESSION["scope"] = "global";
        unset(
            $_SESSION["clinic_id"],
            $_SESSION["role_code"],
            $_SESSION["uc_id"],
            $_SESSION["effective_roles"],
        );
    } elseif (($ctx["scope"] ?? "") === "clinic") {
        $_SESSION["scope"] = "clinic";
        $_SESSION["clinic_id"] = (int) ($ctx["clinic_id"] ?? 0);
        $_SESSION["role_code"] = (string) ($ctx["role"] ?? "");
        $_SESSION["effective_roles"] = [(string) ($ctx["role"] ?? "")];
        $ucId = (int) ($ctx["uc_id"] ?? 0);
        if ($ucId > 0) {
            $_SESSION["uc_id"] = $ucId;
        }
    }
    if (function_exists("app_apply_request_timezone") && !empty($ctx["timezone"])) {
        app_apply_request_timezone((string) $ctx["timezone"]);
    }
}
