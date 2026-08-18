<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\ServerJsonCache;

use \Throwable;

final class ScopedServerJsonCacheInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function server_json_cache_effective_ttl(string $category, ?int $requested = null): int
    {
        $requested = $requested ?? ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl($category);
        return match ($category) {
            'agenda', 'recepcao' => max($requested, 300),
            'financial', 'gavetas' => max($requested, 600),
            'warm', 'kpi', 'cards', 'dashboard' => max($requested, 300),
            'clinic', 'catalog', 'work_hours' => max($requested, 3600),
            'templates' => max($requested, 7200),
            'lookup', 'auxiliary' => max($requested, 1800),
            'cold' => max($requested, 3600),
            default => $requested,
        };
    }

    public static function server_json_cache_file(string $category, string $key): string
    {
        $key = preg_replace('/[^a-z0-9_\-\.]/i', '_', $key) ?: 'cache';
        $categoryDir = ServerJsonCacheInfrastructureOperations01::server_json_cache_category_dir($category);
        $generationDir = $categoryDir . '/generation-' . sprintf(
            '%020d',
            ServerJsonCacheInfrastructureOperations01::server_json_cache_generation($category),
        );
        $shard = substr(hash('sha256', $key), 0, 2);
        $shardDir = $generationDir . '/' . $shard;
        if (!is_dir($shardDir) && !@mkdir($shardDir, 0750, true) && !is_dir($shardDir)) {
            return $shardDir . '/' . $key . '.json';
        }
        \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($generationDir);
        return $shardDir . '/' . $key . '.json';
    }

    public static function server_json_cache_scoped_key(string $category, string $key, array $tags = []): string
    {
        $clinicId = self::server_json_cache_clinic_id($tags);
        if ($clinicId <= 0) {
            return $key;
        }
        $domain = self::server_json_cache_domain_from_tags($category, $tags);
        $segment = self::server_json_cache_segment_from_tags($domain, $tags);
        [$domainGeneration, $segmentGeneration] = self::server_json_cache_scope_generations(
            $clinicId,
            $domain,
            $segment,
        );
        return ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key('scoped', [
            'key' => $key,
            'clinic_id' => $clinicId,
            'domain' => $domain,
            'domain_generation' => $domainGeneration,
            'segment' => $segment,
            'segment_generation' => $segmentGeneration,
        ]);
    }

    public static function server_json_cache_schedule_invalidation_for_write(
        string $route,
        string $act,
        array $post = [],
        int $clinicId = 0,
    ): void {
        if ($route === 'logout') {
            return;
        }
        $clinicId = $clinicId > 0 ? $clinicId : (int) ($_SESSION['clinic_id'] ?? 0);
        $dependencies = self::server_json_cache_write_dependencies($route, $act, $post, $clinicId);
        if ($dependencies === []) {
            return;
        }
        $pending = $GLOBALS['PRONTOO_SCOPED_CACHE_INVALIDATE_AFTER_WRITE'] ?? [];
        if (!is_array($pending)) {
            $pending = [];
        }
        foreach ($dependencies as $dependency) {
            $pending[self::server_json_cache_dependency_token($dependency)] = $dependency;
        }
        $GLOBALS['PRONTOO_SCOPED_CACHE_INVALIDATE_AFTER_WRITE'] = $pending;
        self::server_json_cache_register_flush_callbacks();
    }

    public static function server_json_cache_flush_pending_invalidation(): void
    {
        if (!empty($GLOBALS['PRONTOO_SCOPED_CACHE_INVALIDATE_FLUSHING'])) {
            return;
        }
        $pending = $GLOBALS['PRONTOO_SCOPED_CACHE_INVALIDATE_AFTER_WRITE'] ?? [];
        if (!is_array($pending) || $pending === []) {
            return;
        }
        $GLOBALS['PRONTOO_SCOPED_CACHE_INVALIDATE_FLUSHING'] = true;
        $GLOBALS['PRONTOO_SCOPED_CACHE_INVALIDATE_AFTER_WRITE'] = [];
        try {
            foreach ($pending as $dependency) {
                if (!is_array($dependency)) {
                    continue;
                }
                $clinicId = (int) ($dependency['clinic_id'] ?? 0);
                $domain = self::server_json_cache_normalize_domain((string) ($dependency['domain'] ?? ''));
                $segment = trim((string) ($dependency['segment'] ?? ''));
                if ($domain === '') {
                    continue;
                }
                $storageCategory = self::server_json_cache_storage_category($domain);
                if ($clinicId <= 0) {
                    ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories([$storageCategory]);
                    continue;
                }
                if (!self::server_json_cache_bump_scope($clinicId, $domain, $segment)) {
                    ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories([$storageCategory]);
                }
            }
        } finally {
            $GLOBALS['PRONTOO_SCOPED_CACHE_INVALIDATE_FLUSHING'] = false;
        }
    }

    public static function server_json_cache_invalidate_domains(int $clinicId, array $domains): void
    {
        foreach (array_values(array_unique(array_map('strval', $domains))) as $domain) {
            $domain = self::server_json_cache_normalize_domain($domain);
            if ($domain === '') {
                continue;
            }
            $storageCategory = self::server_json_cache_storage_category($domain);
            if ($clinicId > 0) {
                if (!self::server_json_cache_bump_scope($clinicId, $domain, '')) {
                    ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories([$storageCategory]);
                }
            } else {
                ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories([$storageCategory]);
            }
        }
    }

    public static function server_json_cache_gc(int $maxAgeSeconds = 86400, int $maxFiles = 5000): array
    {
        $root = ServerJsonCacheInfrastructureOperations01::server_json_cache_root();
        $cutoff = time() - max(3600, $maxAgeSeconds);
        $scanned = 0;
        $deleted = 0;
        if (!is_dir($root)) {
            return ['scanned' => 0, 'deleted' => 0];
        }
        $stack = [$root];
        while ($stack !== [] && $scanned < max(100, $maxFiles)) {
            $dir = array_pop($stack);
            $items = @scandir($dir);
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === '.htaccess') {
                    continue;
                }
                $path = $dir . '/' . $item;
                if (is_dir($path) && !is_link($path)) {
                    if (!str_contains($path, '/scopes/')) {
                        $stack[] = $path;
                    }
                    continue;
                }
                if (!is_file($path)) {
                    continue;
                }
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (!in_array($extension, ['json', 'lock'], true) && !str_contains($item, '.tmp.')) {
                    continue;
                }
                $scanned++;
                $mtime = @filemtime($path);
                if ($mtime !== false && $mtime < $cutoff && @unlink($path)) {
                    $deleted++;
                }
                if ($scanned >= $maxFiles) {
                    break 2;
                }
            }
        }
        return ['scanned' => $scanned, 'deleted' => $deleted];
    }

    private static function server_json_cache_register_flush_callbacks(): void
    {
        if (empty($GLOBALS['PRONTOO_SCOPED_CACHE_HEADER_CALLBACK_REGISTERED']) && PHP_SAPI !== 'cli' && function_exists('header_register_callback')) {
            $GLOBALS['PRONTOO_SCOPED_CACHE_HEADER_CALLBACK_REGISTERED'] = true;
            header_register_callback(static function (): void {
                self::server_json_cache_flush_pending_invalidation();
            });
        }
        if (!empty($GLOBALS['PRONTOO_SCOPED_CACHE_SHUTDOWN_REGISTERED'])) {
            return;
        }
        $GLOBALS['PRONTOO_SCOPED_CACHE_SHUTDOWN_REGISTERED'] = true;
        register_shutdown_function(static function (): void {
            self::server_json_cache_flush_pending_invalidation();
        });
    }

    private static function server_json_cache_write_dependencies(
        string $route,
        string $act,
        array $post,
        int $clinicId,
    ): array {
        $route = strtolower(trim($route));
        $act = strtolower(trim($act));
        $domains = match ($route) {
            'appointments' => ['agenda', 'recepcao', 'financial', 'gavetas', 'dashboard'],
            'financial' => ['financial', 'gavetas', 'dashboard'],
            'patient', 'patients' => ['lookup', 'auxiliary', 'agenda', 'dashboard'],
            'leads' => ['lookup', 'auxiliary', 'dashboard'],
            'procedures' => ['catalog', 'templates'],
            'documents' => ['templates', 'catalog', 'lookup', 'auxiliary'],
            'tasks', 'notices', 'audit' => ['lookup', 'auxiliary', 'dashboard'],
            'login', 'switch', 'profile' => ['context', 'cmdbar', 'permissions', 'clinic'],
            'signup', 'onboarding', 'users', 'user', 'permissions', 'settings',
            'admin_users', 'admin_people', 'admin_clinics', 'admin_onboarding',
            'admin_settings', 'admin_maintenance', 'admin_payment_proof' => [
                'context', 'cmdbar', 'permissions', 'clinic', 'catalog',
                'work_hours', 'lookup', 'auxiliary', 'meta', 'dashboard',
            ],
            default => ['hot', 'agenda', 'recepcao', 'financial', 'gavetas', 'warm', 'kpi', 'cards', 'dashboard'],
        };
        if ($route === 'appointments' && in_array($act, [
            'finish_consultation', 'finish_checkout', 'no_show', 'cancel', 'cancel_appointment',
            'receive', 'payment', 'settle',
        ], true)) {
            $domains = array_merge($domains, ['financial', 'gavetas']);
        }
        if (str_contains($act, 'role') || str_contains($act, 'permission')) {
            $domains = array_merge($domains, ['context', 'cmdbar', 'permissions', 'clinic', 'work_hours']);
        }
        $domains = array_values(array_unique($domains));
        $agendaDays = $route === 'appointments' ? self::server_json_cache_post_days($post) : [];
        $financialMonths = in_array($route, ['appointments', 'financial'], true)
            ? self::server_json_cache_post_months($post)
            : [];
        $dependencies = [];
        foreach ($domains as $domain) {
            $dependencies[] = ['clinic_id' => $clinicId, 'domain' => $domain, 'segment' => ''];
        }
        if ($route === 'appointments') {
            if ($agendaDays === []) {
                $dependencies[] = ['clinic_id' => $clinicId, 'domain' => 'agenda_day', 'segment' => ''];
            } else {
                foreach ($agendaDays as $day) {
                    $dependencies[] = ['clinic_id' => $clinicId, 'domain' => 'agenda_day', 'segment' => $day];
                }
            }
        }
        if (in_array($route, ['appointments', 'financial'], true) && array_intersect($domains, ['financial', 'gavetas']) !== []) {
            if ($financialMonths === []) {
                $dependencies[] = ['clinic_id' => $clinicId, 'domain' => 'financial_month', 'segment' => ''];
            } else {
                foreach ($financialMonths as $month) {
                    $dependencies[] = ['clinic_id' => $clinicId, 'domain' => 'financial_month', 'segment' => $month];
                }
            }
        }
        return $dependencies;
    }

    private static function server_json_cache_post_days(array $post): array
    {
        $days = [];
        array_walk_recursive($post, static function (mixed $value) use (&$days): void {
            if (!is_scalar($value)) {
                return;
            }
            if (preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', (string) $value, $matches) > 0) {
                foreach ($matches[0] as $day) {
                    $days[$day] = true;
                }
            }
        });
        return array_keys($days);
    }

    private static function server_json_cache_post_months(array $post): array
    {
        $months = [];
        array_walk_recursive($post, static function (mixed $value) use (&$months): void {
            if (!is_scalar($value)) {
                return;
            }
            if (preg_match_all('/\b\d{4}-\d{2}\b/', (string) $value, $matches) > 0) {
                foreach ($matches[0] as $month) {
                    $months[$month] = true;
                }
            }
        });
        return array_keys($months);
    }

    private static function server_json_cache_domain_from_tags(string $category, array $tags): string
    {
        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if (preg_match('/^cache-domain:([a-z0-9_\-]+)$/i', $tag, $match) === 1) {
                return self::server_json_cache_normalize_domain($match[1]);
            }
            if (in_array($category, ['agenda', 'recepcao'], true) && preg_match('/^(?:agenda-day|day):\d{4}-\d{2}-\d{2}$/', $tag) === 1) {
                return 'agenda_day';
            }
            if (in_array($category, ['financial', 'gavetas'], true) && preg_match('/^(?:financial-month|month|goal):\d{4}-\d{2}$/', $tag) === 1) {
                return 'financial_month';
            }
        }
        return self::server_json_cache_normalize_domain($category);
    }

    private static function server_json_cache_segment_from_tags(string $domain, array $tags): string
    {
        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if ($domain === 'agenda_day' && preg_match('/^(?:agenda-day|day):(\d{4}-\d{2}-\d{2})$/', $tag, $match) === 1) {
                return $match[1];
            }
            if ($domain === 'financial_month' && preg_match('/^(?:financial-month|month|goal):(\d{4}-\d{2})$/', $tag, $match) === 1) {
                return $match[1];
            }
        }
        return '';
    }

    private static function server_json_cache_clinic_id(array $tags): int
    {
        foreach ($tags as $tag) {
            if (preg_match('/^clinic:(\d+)$/', (string) $tag, $match) === 1) {
                return max(0, (int) $match[1]);
            }
        }
        return max(0, (int) ($_SESSION['clinic_id'] ?? 0));
    }

    private static function server_json_cache_scope_generations(int $clinicId, string $domain, string $segment): array
    {
        $manifest = self::server_json_cache_scope_manifest($clinicId);
        $domainGeneration = max(1, (int) ($manifest['domains'][$domain] ?? 1));
        $segmentGeneration = $segment !== ''
            ? max(1, (int) ($manifest['segments'][$domain][$segment] ?? 1))
            : 1;
        return [$domainGeneration, $segmentGeneration];
    }

    private static function server_json_cache_bump_scope(int $clinicId, string $domain, string $segment): bool
    {
        $dir = self::server_json_cache_scope_dir($clinicId);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return false;
        }
        \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
        $lock = @fopen($dir . '/versions.lock', 'c');
        if (!is_resource($lock)) {
            return false;
        }
        try {
            if (!@flock($lock, LOCK_EX)) {
                return false;
            }
            $manifest = self::server_json_cache_scope_manifest($clinicId, true);
            if ($segment === '') {
                $current = max(1, (int) ($manifest['domains'][$domain] ?? 1));
                $manifest['domains'][$domain] = self::server_json_cache_next_generation($current);
                $manifest['segments'][$domain] = [];
            } else {
                $manifest['domains'][$domain] = max(1, (int) ($manifest['domains'][$domain] ?? 1));
                $current = max(1, (int) ($manifest['segments'][$domain][$segment] ?? 1));
                $manifest['segments'][$domain][$segment] = self::server_json_cache_next_generation($current);
            }
            $manifest['updated_at'] = time();
            if (!self::server_json_cache_write_scope_manifest($clinicId, $manifest)) {
                return false;
            }
            if (!isset($GLOBALS['PRONTOO_SCOPED_CACHE_MANIFESTS']) || !is_array($GLOBALS['PRONTOO_SCOPED_CACHE_MANIFESTS'])) {
                $GLOBALS['PRONTOO_SCOPED_CACHE_MANIFESTS'] = [];
            }
            $GLOBALS['PRONTOO_SCOPED_CACHE_MANIFESTS'][$clinicId] = $manifest;
            return true;
        } catch (Throwable $error) {
            error_log('[Prontoo scoped cache generation] ' . $error->getMessage());
            return false;
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    private static function server_json_cache_scope_manifest(int $clinicId, bool $fresh = false): array
    {
        $cached = $GLOBALS['PRONTOO_SCOPED_CACHE_MANIFESTS'][$clinicId] ?? null;
        if (!$fresh && is_array($cached)) {
            return $cached;
        }
        $manifest = ['version' => 1, 'domains' => [], 'segments' => [], 'updated_at' => 0];
        $raw = @file_get_contents(self::server_json_cache_scope_file($clinicId));
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $manifest['version'] = 1;
                $manifest['domains'] = is_array($decoded['domains'] ?? null) ? $decoded['domains'] : [];
                $manifest['segments'] = is_array($decoded['segments'] ?? null) ? $decoded['segments'] : [];
                $manifest['updated_at'] = max(0, (int) ($decoded['updated_at'] ?? 0));
            }
        }
        if (!isset($GLOBALS['PRONTOO_SCOPED_CACHE_MANIFESTS']) || !is_array($GLOBALS['PRONTOO_SCOPED_CACHE_MANIFESTS'])) {
            $GLOBALS['PRONTOO_SCOPED_CACHE_MANIFESTS'] = [];
        }
        $GLOBALS['PRONTOO_SCOPED_CACHE_MANIFESTS'][$clinicId] = $manifest;
        return $manifest;
    }

    private static function server_json_cache_write_scope_manifest(int $clinicId, array $manifest): bool
    {
        $dir = self::server_json_cache_scope_dir($clinicId);
        $file = self::server_json_cache_scope_file($clinicId);
        $encoded = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return false;
        }
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable) {
            $suffix = (string) getmypid();
        }
        $tmp = $file . '.tmp.' . getmypid() . '.' . $suffix;
        if (@file_put_contents($tmp, $encoded, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        @chmod($file, 0640);
        return true;
    }

    private static function server_json_cache_scope_dir(int $clinicId): string
    {
        return ServerJsonCacheInfrastructureOperations01::server_json_cache_root() . '/scopes/clinic-' . max(0, $clinicId);
    }

    private static function server_json_cache_scope_file(int $clinicId): string
    {
        return self::server_json_cache_scope_dir($clinicId) . '/versions.json';
    }

    private static function server_json_cache_next_generation(int $current): int
    {
        return $current >= PHP_INT_MAX - 1 ? 1 : $current + 1;
    }

    private static function server_json_cache_normalize_domain(string $domain): string
    {
        return preg_replace('/[^a-z0-9_\-]/i', '_', strtolower(trim($domain))) ?: '';
    }

    private static function server_json_cache_storage_category(string $domain): string
    {
        return match ($domain) {
            'agenda_day' => 'agenda',
            'financial_month' => 'financial',
            default => $domain,
        };
    }

    private static function server_json_cache_dependency_token(array $dependency): string
    {
        return implode(':', [
            (int) ($dependency['clinic_id'] ?? 0),
            self::server_json_cache_normalize_domain((string) ($dependency['domain'] ?? '')),
            trim((string) ($dependency['segment'] ?? '')),
        ]);
    }
}
