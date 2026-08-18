<?php
declare(strict_types=1);

namespace Prontoo\Runtime\ServerJsonCache;

use Prontoo\Infrastructure\ServerJsonCache\ScopedServerJsonCacheInfrastructureOperations01;
use \Throwable;

final class ServerJsonCacheRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function server_json_cache_read_allowed(): bool
    {
        return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_enabled() &&
            is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'storage_path']) &&
            \Prontoo\Presentation\ServerJsonCache\ServerJsonCachePresentationOperations01::server_json_cache_request_is_read();
    }

    public static function server_json_cache_write_allowed(): bool
    {
        return self::server_json_cache_read_allowed();
    }

    public static function server_json_cache_get(
        string $category,
        string $key,
        ?int $ttlSeconds = null,
    ): mixed {
        if (!self::server_json_cache_read_allowed()) {
            self::server_json_cache_metric('bypass', $category);
            return null;
        }
        $ttl = ScopedServerJsonCacheInfrastructureOperations01::server_json_cache_effective_ttl(
            $category,
            $ttlSeconds,
        );
        if ($ttl <= 0) {
            self::server_json_cache_metric('bypass', $category);
            return null;
        }
        $file = ScopedServerJsonCacheInfrastructureOperations01::server_json_cache_file($category, $key);
        $memoryFound = false;
        $memoryValue = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_memory_get($file, $memoryFound);
        if ($memoryFound) {
            self::server_json_cache_metric('memory_hit', $category);
            return $memoryValue;
        }
        if (!is_file($file)) {
            self::server_json_cache_metric('miss', $category);
            return null;
        }
        $mtime = @filemtime($file);
        if ($mtime === false || time() - $mtime > $ttl) {
            @unlink($file);
            self::server_json_cache_metric('expired', $category);
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || trim($raw) === '') {
            @unlink($file);
            self::server_json_cache_metric('invalid', $category);
            return null;
        }
        $json = json_decode($raw, true);
        if (!is_array($json) || !array_key_exists('value', $json)) {
            @unlink($file);
            self::server_json_cache_metric('invalid', $category);
            return null;
        }
        if (($json['server_side_only'] ?? '') !== 'storage-json') {
            @unlink($file);
            self::server_json_cache_metric('invalid', $category);
            return null;
        }
        if ((int) ($json['expires_at'] ?? 0) < time()) {
            @unlink($file);
            self::server_json_cache_metric('expired', $category);
            return null;
        }
        \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_memory_set($file, $json['value']);
        self::server_json_cache_metric('disk_hit', $category);
        return $json['value'];
    }

    public static function server_json_cache_set(
        string $category,
        string $key,
        mixed $value,
        ?int $ttlSeconds = null,
        array $tags = [],
    ): mixed {
        if (!self::server_json_cache_write_allowed()) {
            return $value;
        }
        $ttl = ScopedServerJsonCacheInfrastructureOperations01::server_json_cache_effective_ttl(
            $category,
            $ttlSeconds,
        );
        if ($ttl <= 0) {
            return $value;
        }
        $now = time();
        $payload = [
            'server_side_only' => 'storage-json',
            'category' => $category,
            'key' => $key,
            'created_at' => $now,
            'expires_at' => $now + $ttl,
            'ttl_seconds' => $ttl,
            'tags' => array_values(array_filter(array_map('strval', $tags))),
            'value' => $value,
        ];
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if ($encoded === false) {
            self::server_json_cache_metric('encode_failure', $category);
            return $value;
        }
        $file = ScopedServerJsonCacheInfrastructureOperations01::server_json_cache_file($category, $key);
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable $error) {
            error_log('[Prontoo server JSON cache random] ' . $error->getMessage());
            self::server_json_cache_metric('write_failure', $category);
            return $value;
        }
        $tmp = $file . '.tmp.' . getmypid() . '.' . $suffix;
        $stored = false;
        if (@file_put_contents($tmp, $encoded, LOCK_EX) !== false) {
            @chmod($tmp, 0640);
            if (@rename($tmp, $file)) {
                @chmod($file, 0640);
                $stored = true;
            } else {
                @unlink($tmp);
            }
        } elseif (is_file($tmp)) {
            @unlink($tmp);
        }
        if ($stored) {
            self::server_json_cache_metric('write', $category);
        } else {
            self::server_json_cache_metric('write_failure', $category);
        }
        \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_memory_set($file, $value);
        return $value;
    }

    public static function server_json_cache_remember(
        string $category,
        string $key,
        ?int $ttlSeconds,
        callable $loader,
        array $tags = [],
    ): mixed {
        $ttl = ScopedServerJsonCacheInfrastructureOperations01::server_json_cache_effective_ttl(
            $category,
            $ttlSeconds,
        );
        $scopedKey = ScopedServerJsonCacheInfrastructureOperations01::server_json_cache_scoped_key(
            $category,
            $key,
            $tags,
        );
        $cached = self::server_json_cache_get($category, $scopedKey, $ttl);
        if ($cached !== null) {
            return $cached;
        }
        if (!self::server_json_cache_read_allowed()) {
            return $loader();
        }
        $file = ScopedServerJsonCacheInfrastructureOperations01::server_json_cache_file($category, $scopedKey);
        $lock = @fopen($file . '.lock', 'c');
        $locked = false;
        if (is_resource($lock)) {
            @chmod($file . '.lock', 0640);
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
                unset($GLOBALS['PRONTOO_SERVER_JSON_CACHE_MEMORY'][$file]);
                $cached = self::server_json_cache_get($category, $scopedKey, $ttl);
                if ($cached !== null) {
                    return $cached;
                }
                $started = microtime(true);
                $value = $loader();
                self::server_json_cache_metric('build', $category, (int) round((microtime(true) - $started) * 1000));
                return self::server_json_cache_set(
                    $category,
                    $scopedKey,
                    $value,
                    $ttl,
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
        self::server_json_cache_metric('lock_bypass', $category);
        return $loader();
    }

    public static function server_json_cache_stats(): array
    {
        $stats = $GLOBALS['PRONTOO_SERVER_JSON_CACHE_STATS'] ?? [];
        return is_array($stats) ? $stats : [];
    }

    public static function server_json_cache_context_key(
        int $uid,
        string $scope,
        int $clinicId,
        int $ucId,
        string $role,
    ): string {
        return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key('ctx', [
            'uid' => $uid,
            'scope' => $scope,
            'clinic_id' => $clinicId,
            'uc_id' => $ucId,
            'role' => $role,
            'user_auth_generation' => (string) ($_SESSION['user_auth_generation'] ?? ''),
            'version' => defined('PRONTOO_VERSION') ? PRONTOO_VERSION : '',
            'schema' => defined('PRONTOO_SCHEMA_REV') ? PRONTOO_SCHEMA_REV : '',
        ]);
    }

    public static function server_json_cache_apply_context_session(array $ctx): void
    {
        if (($ctx['scope'] ?? '') === 'global') {
            $_SESSION['scope'] = 'global';
            unset(
                $_SESSION['clinic_id'],
                $_SESSION['role_code'],
                $_SESSION['uc_id'],
                $_SESSION['effective_roles'],
            );
        } elseif (($ctx['scope'] ?? '') === 'clinic') {
            $_SESSION['scope'] = 'clinic';
            $_SESSION['clinic_id'] = (int) ($ctx['clinic_id'] ?? 0);
            $_SESSION['role_code'] = (string) ($ctx['role'] ?? '');
            $_SESSION['effective_roles'] = [(string) ($ctx['role'] ?? '')];
            $ucId = (int) ($ctx['uc_id'] ?? 0);
            if ($ucId > 0) {
                $_SESSION['uc_id'] = $ucId;
            }
        }
        if (is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'app_apply_request_timezone']) && !empty($ctx['timezone'])) {
            \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_apply_request_timezone((string) $ctx['timezone']);
        }
    }

    private static function server_json_cache_metric(string $event, string $category, int $durationMs = 0): void
    {
        if (!isset($GLOBALS['PRONTOO_SERVER_JSON_CACHE_STATS']) || !is_array($GLOBALS['PRONTOO_SERVER_JSON_CACHE_STATS'])) {
            $GLOBALS['PRONTOO_SERVER_JSON_CACHE_STATS'] = [];
        }
        $category = preg_replace('/[^a-z0-9_\-]/i', '_', $category) ?: 'general';
        $event = preg_replace('/[^a-z0-9_\-]/i', '_', $event) ?: 'event';
        $GLOBALS['PRONTOO_SERVER_JSON_CACHE_STATS'][$category][$event] =
            (int) ($GLOBALS['PRONTOO_SERVER_JSON_CACHE_STATS'][$category][$event] ?? 0) + 1;
        if ($durationMs > 0) {
            $GLOBALS['PRONTOO_SERVER_JSON_CACHE_STATS'][$category]['build_ms'] =
                (int) ($GLOBALS['PRONTOO_SERVER_JSON_CACHE_STATS'][$category]['build_ms'] ?? 0) + $durationMs;
        }
    }
}
