<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function prontoo_min_php_version(): string
{
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_min_php_version();
}

function prontoo_php_runtime_ok(?string $version = null): bool
{
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_php_runtime_ok($version);
}

function prontoo_php_runtime_message(?string $version = null): string
{
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_php_runtime_message($version);
}

function prontoo_ini_size_to_bytes(mixed $value): ?int
{
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_ini_size_to_bytes($value);
}

function prontoo_memory_limit_meets(
    int $minimumBytes,
    mixed $memoryLimit = null,
): bool {
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_memory_limit_meets($minimumBytes, $memoryLimit);
}

function prontoo_memory_limit_label(mixed $memoryLimit = null): string
{
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_memory_limit_label($memoryLimit);
}

function prontoo_runtime_attempt(
    callable $operation,
    mixed $fallback = false,
    string $label = "operação de arquivo",
    bool $logFailure = true,
): mixed {
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_runtime_attempt($operation, $fallback, $label, $logFailure);
}

function prontoo_fs_mkdir(
    string $directory,
    int $mode = 0750,
    bool $recursive = true,
    bool $logFailure = true,
): bool {
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_mkdir($directory, $mode, $recursive, $logFailure);
}

function prontoo_fs_read(
    string $path,
    bool $logFailure = true,
): ?string {
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_read($path, $logFailure);
}

function prontoo_fs_write(
    string $path,
    string $contents,
    int $flags = LOCK_EX,
    bool $logFailure = true,
): int|false {
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_write($path, $contents, $flags, $logFailure);
}

function prontoo_fs_unlink(string $path, bool $logFailure = true): bool
{
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_unlink($path, $logFailure);
}

function prontoo_fs_chmod(
    string $path,
    int $mode,
    bool $logFailure = true,
): bool {
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_chmod($path, $mode, $logFailure);
}

function prontoo_fs_rename(
    string $source,
    string $destination,
    bool $logFailure = true,
): bool {
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_rename($source, $destination, $logFailure);
}

function prontoo_fs_fileperms(
    string $path,
    bool $logFailure = false,
): int|false {
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_fileperms($path, $logFailure);
}

function prontoo_fs_move_upload(
    string $source,
    string $destination,
    bool $logFailure = true,
): bool {
    return \Prontoo\Infrastructure\Legacy\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_move_upload($source, $destination, $logFailure);
}
