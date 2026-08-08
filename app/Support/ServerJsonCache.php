<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function server_json_cache_enabled(): bool
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_enabled();
}

function server_json_cache_root(): string
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_root();
}

function server_json_cache_category_dir(string $category): string
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_category_dir($category);
}

function server_json_cache_generation_file(string $category): string
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_generation_file($category);
}

function server_json_cache_generation(string $category): int
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_generation($category);
}

function server_json_cache_bump_generation(string $category): int
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_bump_generation($category);
}

function server_json_cache_ttl(string $category): int
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl($category);
}

function server_json_cache_safe_key(string $namespace, mixed $keyParts): string
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key($namespace, $keyParts);
}

function server_json_cache_file(string $category, string $key): string
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_file($category, $key);
}

function server_json_cache_request_is_read(): bool
{
    return \Prontoo\Presentation\ServerJsonCache\ServerJsonCachePresentationOperations01::server_json_cache_request_is_read();
}

function server_json_cache_read_allowed(): bool
{
    return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_read_allowed();
}

function server_json_cache_write_allowed(): bool
{
    return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_write_allowed();
}

function server_json_cache_memory_get(string $file, bool &$found): mixed
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_memory_get($file, $found);
}

function server_json_cache_memory_set(string $file, mixed $value): void
{
    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_memory_set($file, $value);
}

function server_json_cache_memory_forget_prefix(string $prefix): void
{
    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_memory_forget_prefix($prefix);
}

function server_json_cache_get(
    string $category,
    string $key,
    ?int $ttlSeconds = null,
): mixed {
    return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_get($category, $key, $ttlSeconds);
}

function server_json_cache_set(
    string $category,
    string $key,
    mixed $value,
    ?int $ttlSeconds = null,
    array $tags = [],
): mixed {
    return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_set($category, $key, $value, $ttlSeconds, $tags);
}

function server_json_cache_remember(
    string $category,
    string $key,
    ?int $ttlSeconds,
    callable $loader,
    array $tags = [],
): mixed {
    return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember($category, $key, $ttlSeconds, $loader, $tags);
}

function server_json_cache_rrmdir(string $dir): void
{
    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_rrmdir($dir);
}

function server_json_cache_clear_categories(array $categories): void
{
    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories($categories);
}

function server_json_cache_all_categories(): array
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_all_categories();
}

function server_json_cache_clear_all_runtime(): void
{
    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_all_runtime();
}

function server_json_cache_clear_all_json_files(): int
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_all_json_files();
}

function server_json_cache_write_categories(string $route, string $act): array
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_write_categories($route, $act);
}

function server_json_cache_invalidate_for_write(
    string $route = "",
    string $act = "",
): void {
    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_invalidate_for_write($route, $act);
}

function server_json_cache_register_deferred_invalidation(): void
{
    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_register_deferred_invalidation();
}
function server_json_cache_schedule_invalidation_for_write(
    string $route = "",
    string $act = "",
): void {
    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_schedule_invalidation_for_write($route, $act);
}

function server_json_cache_context_key(
    int $uid,
    string $scope,
    int $clinicId,
    int $ucId,
    string $role,
): string {
    return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_context_key($uid, $scope, $clinicId, $ucId, $role);
}

function server_json_cache_sanitize_context(array $ctx): array
{
    return \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_sanitize_context($ctx);
}

function server_json_cache_apply_context_session(array $ctx): void
{
    \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_apply_context_session($ctx);
}
