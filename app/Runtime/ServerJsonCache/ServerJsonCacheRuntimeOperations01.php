<?php
declare(strict_types=1);

namespace Prontoo\Runtime\ServerJsonCache;

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

final class ServerJsonCacheRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function server_json_cache_read_allowed(): bool
    
    {
    
        return server_json_cache_enabled() &&
            function_exists("storage_path") &&
            server_json_cache_request_is_read();
    
    }

    public static function server_json_cache_write_allowed(): bool
    
    {
    
        return server_json_cache_read_allowed();
    
    }

    public static function server_json_cache_get(
        string $category,
        string $key,
        ?int $ttlSeconds = null,
    ): mixed 
    {
    
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

    public static function server_json_cache_set(
        string $category,
        string $key,
        mixed $value,
        ?int $ttlSeconds = null,
        array $tags = [],
    ): mixed 
    {
    
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

    public static function server_json_cache_remember(
        string $category,
        string $key,
        ?int $ttlSeconds,
        callable $loader,
        array $tags = [],
    ): mixed 
    {
    
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

    public static function server_json_cache_context_key(
        int $uid,
        string $scope,
        int $clinicId,
        int $ucId,
        string $role,
    ): string 
    {
    
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

    public static function server_json_cache_apply_context_session(array $ctx): void
    
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
}
