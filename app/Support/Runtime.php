<?php
declare(strict_types=1);

function prontoo_min_php_version(): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_min_php_version
     * Responsabilidade: Implementa a responsabilidade “prontoo min php version” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_php_runtime_ok`, `prontoo_php_runtime_message`.
     * Dependências chamadas: `defined`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return defined("PRONTOO_MIN_PHP_VERSION")
        ? (string) PRONTOO_MIN_PHP_VERSION
        : "8.4.0";
}

function prontoo_php_runtime_ok(?string $version = null): bool
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_php_runtime_ok
     * Responsabilidade: Implementa a responsabilidade “prontoo php runtime ok” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `install_environment_checks`, `prontoo_cron_preflight_once`.
     * Dependências chamadas: `version_compare`, `prontoo_min_php_version`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return version_compare(
        $version ?? PHP_VERSION,
        prontoo_min_php_version(),
        ">=",
    );
}

function prontoo_php_runtime_message(?string $version = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_php_runtime_message
     * Responsabilidade: Implementa a responsabilidade “prontoo php runtime message” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `install_environment_checks`, `prontoo_cron_preflight_once`.
     * Dependências chamadas: `prontoo_min_php_version`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $version = $version ?? PHP_VERSION;
    return "PHP " .
        $version .
        " detectado; requisito mínimo PHP " .
        prontoo_min_php_version() .
        ".";
}

function prontoo_ini_size_to_bytes(mixed $value): ?int
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_ini_size_to_bytes
     * Responsabilidade: Implementa a responsabilidade “prontoo ini size to bytes” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_memory_limit_meets`.
     * Dependências chamadas: `trim`, `preg_match`, `strtolower`, `round`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
    /*
     * GUIA DE MANUTENÇÃO — prontoo_memory_limit_meets
     * Responsabilidade: Implementa a responsabilidade “prontoo memory limit meets” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `install_environment_checks`, `prontoo_cron_preflight_once`.
     * Dependências chamadas: `prontoo_ini_size_to_bytes`, `ini_get`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $bytes = prontoo_ini_size_to_bytes($memoryLimit ?? ini_get("memory_limit"));
    return $bytes === null || $bytes >= $minimumBytes;
}

function prontoo_memory_limit_label(mixed $memoryLimit = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_memory_limit_label
     * Responsabilidade: Monta a representação de interface associada a “prontoo memory limit label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `install_environment_checks`, `prontoo_cron_preflight_once`.
     * Dependências chamadas: `ini_get`, `trim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
    /*
     * GUIA DE MANUTENÇÃO — prontoo_runtime_attempt
     * Responsabilidade: Implementa a responsabilidade “prontoo runtime attempt” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_fs_mkdir`, `prontoo_fs_read`, `prontoo_fs_write`, `prontoo_fs_unlink`, `prontoo_fs_chmod`, `prontoo_fs_rename`, `prontoo_fs_fileperms`, `prontoo_fs_move_upload`.
     * Dependências chamadas: `set_error_handler`, `ErrorException`, `error_log`, `->getMessage`, `restore_error_handler`.
     * Classes ou serviços instanciados: `ErrorException`.
     * Efeitos colaterais: gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $previous = set_error_handler(
        static function (
            int $severity,
            string $message,
            string $file,
            int $line,
        ): void {
            /*
             * GUIA DE MANUTENÇÃO — closure@app/Support/Runtime.php:88
             * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de serviços transversais de suporte.
             * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
             * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
             * Dependências chamadas: `ErrorException`.
             * Classes ou serviços instanciados: `ErrorException`.
             * Efeitos colaterais: pode interromper o fluxo por exceção.
             * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
             */
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
    /*
     * GUIA DE MANUTENÇÃO — prontoo_fs_mkdir
     * Responsabilidade: Implementa a responsabilidade “prontoo fs mkdir” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `install_environment_checks`, `prontoo_schema_boot_marker_path`.
     * Dependências chamadas: `is_dir`, `prontoo_runtime_attempt`, `mkdir`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (is_dir($directory)) {
        return true;
    }
    return (bool) prontoo_runtime_attempt(
        static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `mkdir`. Efeitos: acessa o sistema de arquivos. */ fn(): bool => mkdir($directory, $mode, $recursive),
        false,
        "criação de diretório {$directory}",
        $logFailure,
    );
}

function prontoo_fs_read(
    string $path,
    bool $logFailure = true,
): ?string {
    /*
     * GUIA DE MANUTENÇÃO — prontoo_fs_read
     * Responsabilidade: Localiza, carrega ou resolve os dados de “prontoo fs read” para consumo pelas camadas superiores.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_schema_promote_release_contract`, `ensure_runtime_schema_minimum`, `prontoo_schema_boot_marker_valid`.
     * Dependências chamadas: `is_file`, `prontoo_runtime_attempt`, `file_get_contents`, `is_string`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!is_file($path)) {
        return null;
    }
    $value = prontoo_runtime_attempt(
        static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `file_get_contents`. Efeitos: acessa o sistema de arquivos. */ fn(): string|false => file_get_contents($path),
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
    /*
     * GUIA DE MANUTENÇÃO — prontoo_fs_write
     * Responsabilidade: Valida e executa a mutação “prontoo fs write”, preservando as invariantes do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_schema_promote_release_contract`, `install_environment_checks`, `install_write_failure_log`, `install_prepare_writable_paths`, `prontoo_install`, `prontoo_schema_boot_mark_ok`.
     * Dependências chamadas: `prontoo_runtime_attempt`, `file_put_contents`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return prontoo_runtime_attempt(
        static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `file_put_contents`. Efeitos: acessa o sistema de arquivos. */ fn(): int|false => file_put_contents($path, $contents, $flags),
        false,
        "gravação de {$path}",
        $logFailure,
    );
}

function prontoo_fs_unlink(string $path, bool $logFailure = true): bool
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_fs_unlink
     * Responsabilidade: Implementa a responsabilidade “prontoo fs unlink” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_schema_promote_release_contract`, `install_fresh_schema`, `install_environment_checks`, `prontoo_install`, `closure@app/Install/Installer.php:872`.
     * Dependências chamadas: `file_exists`, `is_link`, `prontoo_runtime_attempt`, `unlink`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!file_exists($path) && !is_link($path)) {
        return true;
    }
    return (bool) prontoo_runtime_attempt(
        static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `unlink`. Efeitos: acessa o sistema de arquivos. */ fn(): bool => unlink($path),
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
    /*
     * GUIA DE MANUTENÇÃO — prontoo_fs_chmod
     * Responsabilidade: Implementa a responsabilidade “prontoo fs chmod” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_schema_promote_release_contract`, `schema_mark_ready`, `install_write_failure_log`, `prontoo_install`.
     * Dependências chamadas: `file_exists`, `prontoo_runtime_attempt`, `chmod`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!file_exists($path)) {
        return false;
    }
    return (bool) prontoo_runtime_attempt(
        static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `chmod`. Efeitos: transformação local sem efeito externo detectado. */ fn(): bool => chmod($path, $mode),
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
    /*
     * GUIA DE MANUTENÇÃO — prontoo_fs_rename
     * Responsabilidade: Implementa a responsabilidade “prontoo fs rename” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_schema_promote_release_contract`.
     * Dependências chamadas: `prontoo_runtime_attempt`, `rename`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return (bool) prontoo_runtime_attempt(
        static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `rename`. Efeitos: acessa o sistema de arquivos. */ fn(): bool => rename($source, $destination),
        false,
        "renomeação de {$source} para {$destination}",
        $logFailure,
    );
}

function prontoo_fs_fileperms(
    string $path,
    bool $logFailure = false,
): int|false {
    /*
     * GUIA DE MANUTENÇÃO — prontoo_fs_fileperms
     * Responsabilidade: Implementa a responsabilidade “prontoo fs fileperms” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: `install_path_mode`.
     * Dependências chamadas: `prontoo_runtime_attempt`, `fileperms`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return prontoo_runtime_attempt(
        static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `fileperms`. Efeitos: transformação local sem efeito externo detectado. */ fn(): int|false => fileperms($path),
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
    /*
     * GUIA DE MANUTENÇÃO — prontoo_fs_move_upload
     * Responsabilidade: Implementa a responsabilidade “prontoo fs move upload” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Runtime.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `prontoo_runtime_attempt`, `move_uploaded_file`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return (bool) prontoo_runtime_attempt(
        static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `move_uploaded_file`. Efeitos: transformação local sem efeito externo detectado. */ fn(): bool => move_uploaded_file($source, $destination),
        false,
        "movimentação de upload para {$destination}",
        $logFailure,
    );
}
