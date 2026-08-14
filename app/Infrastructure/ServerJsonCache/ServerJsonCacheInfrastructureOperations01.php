<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\ServerJsonCache;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class ServerJsonCacheInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function server_json_cache_enabled(): bool
    
    {
    
        if (defined("PRONTOO_SERVER_JSON_CACHE") && !PRONTOO_SERVER_JSON_CACHE) {
            return false;
        }
        return (string) getenv("PRONTOO_DISABLE_SERVER_JSON_CACHE") !== "1";
    
    }

    public static function server_json_cache_root(): string
    
    {
    
        static $resolved = null;
        if (is_string($resolved)) {
            return $resolved;
        }
        $resolved = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache/server-json");
        if (!is_dir($resolved) && !@mkdir($resolved, 0750, true) && !is_dir($resolved)) {
            return $resolved;
        }
        \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($resolved);
        return $resolved;
    
    }

    public static function server_json_cache_category_dir(string $category): string
    
    {
    
        static $resolved = [];
        $category = preg_replace("/[^a-z0-9_\-]/i", "_", $category) ?: "general";
        if (isset($resolved[$category])) {
            return $resolved[$category];
        }
        $dir = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_root() . "/" . $category;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return $resolved[$category] = $dir;
        }
        \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
        return $resolved[$category] = $dir;
    
    }

    public static function server_json_cache_generation_file(string $category): string
    
    {
    
        return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_category_dir($category) . "/generation.txt";
    
    }

    public static function server_json_cache_generation(string $category): int
    
    {
    
        $category = preg_replace("/[^a-z0-9_\-]/i", "_", $category) ?: "general";
        $generations = $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"] ?? [];
        if (is_array($generations) && isset($generations[$category])) {
            return max(1, (int) $generations[$category]);
        }
        $raw = @file_get_contents(\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_generation_file($category));
        $generation = max(1, (int) trim(is_string($raw) ? $raw : "1"));
        if (!isset($GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"]) ||
            !is_array($GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"])) {
            $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"] = [];
        }
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"][$category] = $generation;
        return $generation;
    
    }

    public static function server_json_cache_bump_generation(string $category): int
    
    {
    
        $category = preg_replace("/[^a-z0-9_\-]/i", "_", $category) ?: "general";
        $dir = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_category_dir($category);
        $file = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_generation_file($category);
        $handle = @fopen($file, "c+");
        if (!is_resource($handle)) {
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_rrmdir($dir);
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
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_rrmdir($dir);
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

    public static function server_json_cache_ttl(string $category): int
    
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

    public static function server_json_cache_safe_key(string $namespace, mixed $keyParts): string
    
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

    public static function server_json_cache_file(string $category, string $key): string
    
    {
    
        $key = preg_replace("/[^a-z0-9_\-\.]/i", "_", $key) ?: "cache";
        $categoryDir = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_category_dir($category);
        $generationDir =
            $categoryDir .
            "/generation-" .
            sprintf("%020d", \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_generation($category));
        if (!is_dir($generationDir) &&
            !@mkdir($generationDir, 0750, true) &&
            !is_dir($generationDir)) {
            return $generationDir . "/" . $key . ".json";
        }
        \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($generationDir);
        return $generationDir . "/" . $key . ".json";
    
    }

    public static function server_json_cache_memory_get(string $file, bool &$found): mixed
    
    {
    
        $memory = $GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"] ?? [];
        if (is_array($memory) && array_key_exists($file, $memory)) {
            $found = true;
            return $memory[$file];
        }
        $found = false;
        return null;
    
    }

    public static function server_json_cache_memory_set(string $file, mixed $value): void
    
    {
    
        if (!isset($GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"]) ||
            !is_array($GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"])) {
            $GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"] = [];
        }
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_MEMORY"][$file] = $value;
    
    }

    public static function server_json_cache_memory_forget_prefix(string $prefix): void
    
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

    public static function server_json_cache_rrmdir(string $dir): void
    
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
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_rrmdir($path);
            } else {
                @unlink($path);
            }
        }
    
    }

    public static function server_json_cache_clear_categories(array $categories): void
    
    {
    
        $categories = array_values(array_unique(array_map("strval", $categories)));
        foreach ($categories as $category) {
            $dir = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_category_dir($category);
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_bump_generation($category);
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_memory_forget_prefix($dir . "/");
        }
    
    }

    public static function server_json_cache_all_categories(): array
    
    {
    
        return [
            "hot", "agenda", "recepcao", "financial", "gavetas",
            "warm", "kpi", "cards", "dashboard", "context", "cmdbar",
            "permissions", "clinic", "catalog", "work_hours", "templates",
            "lookup", "auxiliary", "meta", "cold",
        ];
    
    }

    public static function server_json_cache_clear_all_runtime(): void
    
    {
    
        \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories(\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_all_categories());
    
    }

    public static function server_json_cache_clear_all_json_files(): int
    
    {
    
        $root = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache");
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

    public static function server_json_cache_write_categories(string $route, string $act): array
    
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

    public static function server_json_cache_invalidate_for_write(
        string $route = "",
        string $act = "",
    ): void 
    {
    
        \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories(
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_write_categories($route, $act),
        );
    
    }

    public static function server_json_cache_register_deferred_invalidation(): void
    
    {
    
        if (!empty($GLOBALS["PRONTOO_CACHE_INVALIDATE_SHUTDOWN_REGISTERED"])) {
            return;
        }
        $GLOBALS["PRONTOO_CACHE_INVALIDATE_SHUTDOWN_REGISTERED"] = true;
        register_shutdown_function(static function (): void {
    
            $pending = $GLOBALS["PRONTOO_CACHE_INVALIDATE_AFTER_WRITE"] ?? [];
            if (is_array($pending) && $pending) {
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories($pending);
            }
            $GLOBALS["PRONTOO_CACHE_INVALIDATE_AFTER_WRITE"] = [];
        });
    
    }

    public static function server_json_cache_schedule_invalidation_for_write(
        string $route = "",
        string $act = "",
    ): void 
    {
    
        $categories = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_write_categories($route, $act);
        $pending = $GLOBALS["PRONTOO_CACHE_INVALIDATE_AFTER_WRITE"] ?? [];
        $GLOBALS["PRONTOO_CACHE_INVALIDATE_AFTER_WRITE"] = array_values(
            array_unique(array_merge(is_array($pending) ? $pending : [], $categories)),
        );
        \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_register_deferred_invalidation();
    
    }

    public static function server_json_cache_sanitize_context(array $ctx): array
    
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
}
