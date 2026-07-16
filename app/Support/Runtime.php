<?php
declare(strict_types=1);

function prontoo_min_php_version(): string
{
    return defined("PRONTOO_MIN_PHP_VERSION")
        ? (string) PRONTOO_MIN_PHP_VERSION
        : "8.4.0";
}

function prontoo_php_runtime_ok(?string $version = null): bool
{
    return version_compare(
        $version ?? PHP_VERSION,
        prontoo_min_php_version(),
        ">=",
    );
}

function prontoo_php_runtime_message(?string $version = null): string
{
    $version = $version ?? PHP_VERSION;
    return "PHP " .
        $version .
        " detectado; requisito mínimo PHP " .
        prontoo_min_php_version() .
        ".";
}

function prontoo_ini_size_to_bytes(mixed $value): ?int
{
    if ($value === null || $value === false) {
        return null;
    }

    $raw = trim((string) $value);
    if ($raw === "") {
        return null;
    }

    if ($raw === "-1") {
        return null;
    }

    if (!preg_match('/^(-?\d+(?:\.\d+)?)\s*([kmg])?b?$/i', $raw, $match)) {
        return (int) $raw;
    }

    $number = (float) $match[1];
    $unit = strtolower((string) ($match[2] ?? ""));
    $multiplier = match ($unit) {
        "g" => 1024 * 1024 * 1024,
        "m" => 1024 * 1024,
        "k" => 1024,
        default => 1,
    };

    return (int) round($number * $multiplier);
}

function prontoo_memory_limit_meets(
    int $minimumBytes,
    mixed $memoryLimit = null,
): bool {
    $bytes = prontoo_ini_size_to_bytes($memoryLimit ?? ini_get("memory_limit"));
    return $bytes === null || $bytes >= $minimumBytes;
}

function prontoo_memory_limit_label(mixed $memoryLimit = null): string
{
    $raw = $memoryLimit ?? ini_get("memory_limit");
    $raw = trim((string) $raw);
    return $raw !== "" ? $raw : "não informado";
}

/**
 * Executa uma operação de sistema de arquivos sem ocultar avisos do PHP.
 * Falhas recuperáveis retornam o valor de fallback e são registradas quando
 * solicitado pelo chamador.
 */
function prontoo_runtime_attempt(
    callable $operation,
    mixed $fallback = false,
    string $label = "operação de arquivo",
    bool $logFailure = true,
): mixed {
    $previous = set_error_handler(
        static function (
            int $severity,
            string $message,
            string $file,
            int $line,
        ): void {
            throw new ErrorException($message, 0, $severity, $file, $line);
        },
    );
    try {
        return $operation();
    } catch (Throwable $e) {
        if ($logFailure) {
            error_log("[Prontoo runtime] {$label}: " . $e->getMessage());
        }
        return $fallback;
    } finally {
        restore_error_handler();
    }
}

function prontoo_fs_mkdir(
    string $directory,
    int $mode = 0750,
    bool $recursive = true,
    bool $logFailure = true,
): bool {
    if (is_dir($directory)) {
        return true;
    }
    return (bool) prontoo_runtime_attempt(
        static fn(): bool => mkdir($directory, $mode, $recursive),
        false,
        "criação de diretório {$directory}",
        $logFailure,
    );
}

function prontoo_fs_read(
    string $path,
    bool $logFailure = true,
): ?string {
    if (!is_file($path)) {
        return null;
    }
    $value = prontoo_runtime_attempt(
        static fn(): string|false => file_get_contents($path),
        false,
        "leitura de {$path}",
        $logFailure,
    );
    return is_string($value) ? $value : null;
}

function prontoo_fs_write(
    string $path,
    string $contents,
    int $flags = LOCK_EX,
    bool $logFailure = true,
): int|false {
    return prontoo_runtime_attempt(
        static fn(): int|false => file_put_contents($path, $contents, $flags),
        false,
        "gravação de {$path}",
        $logFailure,
    );
}

function prontoo_fs_unlink(string $path, bool $logFailure = true): bool
{
    if (!file_exists($path) && !is_link($path)) {
        return true;
    }
    return (bool) prontoo_runtime_attempt(
        static fn(): bool => unlink($path),
        false,
        "exclusão de {$path}",
        $logFailure,
    );
}

function prontoo_fs_chmod(
    string $path,
    int $mode,
    bool $logFailure = true,
): bool {
    if (!file_exists($path)) {
        return false;
    }
    return (bool) prontoo_runtime_attempt(
        static fn(): bool => chmod($path, $mode),
        false,
        "permissão de {$path}",
        $logFailure,
    );
}

function prontoo_fs_rename(
    string $source,
    string $destination,
    bool $logFailure = true,
): bool {
    return (bool) prontoo_runtime_attempt(
        static fn(): bool => rename($source, $destination),
        false,
        "renomeação de {$source} para {$destination}",
        $logFailure,
    );
}

function prontoo_fs_fileperms(
    string $path,
    bool $logFailure = false,
): int|false {
    return prontoo_runtime_attempt(
        static fn(): int|false => fileperms($path),
        false,
        "leitura de permissões de {$path}",
        $logFailure,
    );
}

function prontoo_fs_move_upload(
    string $source,
    string $destination,
    bool $logFailure = true,
): bool {
    return (bool) prontoo_runtime_attempt(
        static fn(): bool => move_uploaded_file($source, $destination),
        false,
        "movimentação de upload para {$destination}",
        $logFailure,
    );
}
