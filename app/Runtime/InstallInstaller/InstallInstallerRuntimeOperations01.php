<?php
declare(strict_types=1);

namespace Prontoo\Runtime\InstallInstaller;

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

final class InstallInstallerRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function install_value(string $v, int $max = 255): string
    
    {
    
        $v = trim($v);
        if ($v === "" || strlen($v) > $max || preg_match('/[\x00-\x1F\x7F]/', $v)) {
            throw new RuntimeException("Valor de instalação inválido.");
        }
        return $v;
    
    }

    public static function install_pdf_dir(): string
    
    {
    
        if (is_callable([\Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::class, 'document_pdf_dir'])) {
            return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_dir();
        }
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root() . "/pdfs";
    
    }

    public static function install_environment_checks(bool $touchPaths = false): array
    
    {
    
        $checks = [];
        $add = static function (
            string $key,
            bool $ok,
            string $label,
            string $message,
            string $level = "error",
        ) use (&$checks): void {
    
            $checks[] = [
                "key" => $key,
                "ok" => $ok,
                "label" => $label,
                "message" => $message,
                "level" => $level,
            ];
        };
        $add(
            "php_version",
            \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_php_runtime_ok(),
            "PHP",
            \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_php_runtime_message(),
        );
        foreach (
            ["pdo", "pdo_mysql", "json", "openssl", "date", "hash", "session"]
            as $ext
        ) {
            $add(
                "ext_" . $ext,
                extension_loaded($ext),
                "Extensão " . $ext,
                extension_loaded($ext) ? "Disponível." : "Ausente no PHP.",
            );
        }
        $memRaw = \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_memory_limit_label();
        $memOk = \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_memory_limit_meets(128 * 1024 * 1024);
        $add(
            "memory_limit",
            $memOk,
            "Memória",
            "memory_limit atual: " . $memRaw . ".",
            "warn",
        );
        $appDir = dirname(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg_file());
        $paths = [
            "app/" => $appDir,
            "ssd/" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path(),
            "ssd/cache/" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache"),
            "ssd/telemetry/" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("telemetry"),
            "ssd/tmp/" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("tmp"),
            "ssd/pdfs/" => \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_pdf_dir(),
        ];
        foreach ($paths as $label => $dir) {
            if ($touchPaths && !is_dir($dir)) {
                \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_mkdir($dir, 0750, true, false);
            }
            if (is_dir($dir)) {
                $ok = is_writable($dir);
            } else {
                $parent = dirname($dir);
                while ($parent !== "/" && !is_dir($parent)) {
                    $parent = dirname($parent);
                }
                $ok = is_dir($parent) && is_writable($parent);
            }
            if ($ok && $touchPaths) {
                $test = rtrim($dir, "/") . "/.prontoo_install_test_" . getmypid();
                $ok =
                    is_dir($dir) &&
                    \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_write($test, "ok", LOCK_EX, false) !== false;
                \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_unlink($test, false);
            }
            $add(
                "write_" . trim(str_replace("/", "_", $label), "_"),
                $ok,
                "Escrita em " . $label,
                $ok
                    ? "Permissão confirmada."
                    : "Sem permissão de escrita ou diretório ausente.",
            );
        }
        return $checks;
    
    }

    public static function install_environment_has_blocker(array $checks): bool
    
    {
    
        foreach ($checks as $check) {
            if (($check["level"] ?? "error") === "error" && empty($check["ok"])) {
                return true;
            }
        }
        return false;
    
    }

    public static function install_yesno(bool $value): string
    
    {
    
        return $value ? "sim" : "não";
    
    }

    public static function install_compact_text(string $value, int $limit = 1800): string
    
    {
    
        $value = preg_replace("/\s+/", " ", trim($value)) ?? trim($value);
        if (strlen($value) <= $limit) {
            return $value;
        }
        return substr($value, 0, $limit) . "... [truncado]";
    
    }

    public static function install_throwable_lines(Throwable $e): array
    
    {
    
        $lines = [];
        $i = 0;
        do {
            $prefix = $i === 0 ? "Exceção" : "Exceção anterior #" . $i;
            $lines[] = $prefix . ": " . get_class($e);
            $lines[] = "Código: " . (string) $e->getCode();
            $lines[] = "Mensagem: " . $e->getMessage();
            $lines[] = "Arquivo: " . $e->getFile() . ":" . $e->getLine();
            if ($e instanceof PDOException && is_array($e->errorInfo ?? null)) {
                $lines[] =
                    "PDO errorInfo: " .
                    json_encode(
                        $e->errorInfo,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    );
            }
            $trace = array_slice($e->getTrace(), 0, 8);
            foreach ($trace as $n => $frame) {
                $where =
                    ($frame["file"] ?? "[interno]") . ":" . ($frame["line"] ?? "0");
                $fn =
                    (($frame["class"] ?? "") !== ""
                        ? $frame["class"] . ($frame["type"] ?? "::")
                        : "") .
                    ($frame["function"] ?? "");
                $lines[] = "Trace " . $n . ": " . $where . " -> " . $fn . "()";
            }
            $e = $e->getPrevious();
            $i++;
        } while ($e instanceof Throwable && $i < 4);
        return $lines;
    
    }

    public static function install_mysql_dsn(string $host, string $db): string
    
    {
    
        return "mysql:host=" . $host . ";dbname=" . $db . ";charset=utf8mb4";
    
    }

    public static function install_pdo_options(): array
    
    {
    
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    
    }

    public static function install_open_database(array $context, bool $strictMode = false): PDO
    
    {
    
        return \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_open_database($context, $strictMode);
    
    }

    public static function install_database_error_code(Throwable $e): int
    
    {
    
        if (
            $e instanceof PDOException &&
            is_array($e->errorInfo ?? null) &&
            isset($e->errorInfo[1])
        ) {
            return (int) $e->errorInfo[1];
        }
        return is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
    
    }

    public static function install_database_error_message(Throwable $e): string
    
    {
    
        return match (\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_database_error_code($e)) {
            1045
                => "O MySQL recusou o usuário ou a senha informados. Confira o usuário completo do banco, a senha e se esse usuário está vinculado ao banco.",
            1044
                => "O usuário informado existe, mas não tem permissão para acessar o banco selecionado.",
            1049
                => "O banco de dados informado não existe ou não está acessível por esse usuário.",
            2002,
            2005
                => "Não foi possível alcançar o host do banco de dados informado.",
            default
                => "Não foi possível conectar ao banco vazio com os dados informados.",
        };
    
    }

    public static function install_database_hint_lines(Throwable $e, array $context = []): array
    
    {
    
        $code = \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_database_error_code($e);
        $lines = [];
        if ($code === 1045) {
            $lines[] =
                "Erro 1045: falha de autenticação. O instalador não chegou a criar tabelas nem gravar app/config.php.";
            $lines[] =
                "Verifique no painel da hospedagem se o usuário do banco foi criado, se a senha está correta e se o usuário foi adicionado ao banco com privilégios.";
            $lines[] =
                "Em cPanel/DirectAdmin, normalmente o nome real do banco e do usuário inclui prefixo da conta, por exemplo conta_banco e conta_usuario.";
        } elseif ($code === 1044) {
            $lines[] =
                "Erro 1044: usuário sem privilégio para o banco informado. Adicione o usuário ao banco e conceda permissões.";
        } elseif ($code === 1049) {
            $lines[] =
                "Erro 1049: banco ausente. Crie primeiro um banco vazio no painel da hospedagem e informe o nome completo.";
        } elseif ($code === 2002 || $code === 2005) {
            $lines[] =
                "Erro de host/socket. Confirme se o host deve ser localhost ou outro endereço fornecido pela hospedagem.";
        }
        return $lines;
    
    }

    public static function install_database_probe_lines(array $context): array
    
    {
    
        $lines = [];
        $host = (string) ($context["db_host"] ?? "");
        $db = (string) ($context["db_name"] ?? "");
        $user = (string) ($context["db_user"] ?? "");
        if ($host === "" || $db === "" || $user === "") {
            return $lines;
        }
        $lines[] =
            "Banco informado: host=" .
            $host .
            " db=" .
            $db .
            " user=" .
            $user .
            " senha=[omitida]";
        if (!extension_loaded("pdo_mysql")) {
            $lines[] = "Probe DB: pdo_mysql ausente; conexão não testada.";
            return $lines;
        }
        try {
            $probe = \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_database_probe($context);
            $version = (string) ($probe['version'] ?? '');
            $database = (string) ($probe['database'] ?? '');
            $tableCount = (int) ($probe['table_count'] ?? 0);
            $lines[] =
                "Probe DB: conexão OK; database=" .
                $database .
                "; mysql_version=" .
                $version .
                "; tabelas=" .
                $tableCount .
                ".";
            if ($tableCount > 0) {
                $names = array_values((array) ($probe['table_names'] ?? []));
                $lines[] = "Primeiras tabelas detectadas: " . implode(", ", $names);
            }
        } catch (Throwable $dbError) {
            $lines[] = "Probe DB: falhou.";
            foreach (\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_database_hint_lines($dbError, $context) as $line) {
                $lines[] = "DB Diagnóstico: " . $line;
            }
            foreach (\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_throwable_lines($dbError) as $line) {
                $lines[] = "DB " . $line;
            }
        }
        return $lines;
    
    }

    public static function install_technical_report(
        ?Throwable $e = null,
        array $context = [],
        array $checks = [],
    ): string 
    {
    
        $lines = [];
        $lines[] = "PRONTOO INSTALL DIAGNOSTIC";
        $lines[] =
            "Versão: " . (defined("PRONTOO_VERSION") ? PRONTOO_VERSION : "n/d");
        $lines[] =
            "Schema revision: " .
            (defined("PRONTOO_SCHEMA_REV") ? PRONTOO_SCHEMA_REV : "n/d");
        $lines[] = "Data UTC: " . gmdate("c");
        $lines[] =
            "Request method: " . (string) ($_SERVER["REQUEST_METHOD"] ?? "CLI");
        $lines[] = "PHP: " . PHP_VERSION . " / SAPI=" . PHP_SAPI;
        $lines[] = "memory_limit: " . (string) ini_get("memory_limit");
        $lines[] = "max_execution_time: " . (string) ini_get("max_execution_time");
        $lines[] = "display_errors: " . (string) ini_get("display_errors");
        $lines[] =
            "extensions: pdo=" .
            \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno(extension_loaded("pdo")) .
            "; pdo_mysql=" .
            \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno(extension_loaded("pdo_mysql")) .
            "; json=" .
            \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno(extension_loaded("json")) .
            "; openssl=" .
            \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno(extension_loaded("openssl")) .
            "; hash=" .
            \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno(extension_loaded("hash")) .
            "; session=" .
            \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno(extension_loaded("session"));
        $lines[] = "app_root: " . \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root();
        $lines[] = "cfg_file: " . \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg_file();
        $lines[] = "install_state: " . \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_state();
        $lines[] = "has_cfg: " . \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg());
        $lines[] =
            "install.lock exists: " .
            \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno(is_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("install.lock")));
        foreach (
            [
                ["app/", dirname(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg_file())],
                ["app/config.php", \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg_file()],
                ["ssd/", \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path()],
                ["ssd/install.lock", \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("install.lock")],
                ["ssd/cache/", \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache")],
                ["ssd/telemetry/", \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("telemetry")],
                ["ssd/tmp/", \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("tmp")],
                ["ssd/pdfs/ (persistência física)", \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_pdf_dir()],
                ["ssd/img/", \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("img")],
            ]
            as [$label, $path]
        ) {
            $r = \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_path_report($label, $path);
            $lines[] =
                "PATH " .
                $r["label"] .
                ": path=" .
                $r["path"] .
                "; realpath=" .
                $r["realpath"] .
                "; exists=" .
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno((bool) $r["exists"]) .
                "; dir=" .
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno((bool) $r["is_dir"]) .
                "; file=" .
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno((bool) $r["is_file"]) .
                "; writable=" .
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno((bool) $r["writable"]) .
                "; mode=" .
                $r["mode"] .
                "; parent_exists=" .
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno((bool) $r["parent_exists"]) .
                "; parent_writable=" .
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno((bool) $r["parent_writable"]) .
                "; parent_mode=" .
                $r["parent_mode"];
        }
        if ($checks) {
            $lines[] = "Pré-checagem:";
            foreach ($checks as $check) {
                $lines[] =
                    "- " .
                    ($check["ok"] ? "OK" : "FALHA") .
                    " [" .
                    ($check["level"] ?? "error") .
                    "] " .
                    $check["key"] .
                    ": " .
                    $check["label"] .
                    " — " .
                    $check["message"];
            }
        }
        if (!empty($context["cleanup_note"])) {
            $lines[] = "Cleanup: " . (string) $context["cleanup_note"];
        }
        foreach (\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_database_probe_lines($context) as $line) {
            $lines[] = $line;
        }
        $lastSql = (string) ($GLOBALS["PRONTOO_INSTALL_LAST_SQL"] ?? "");
        $lastRuntimeSql =
            (string) ($GLOBALS["PRONTOO_INSTALL_LAST_RUNTIME_SQL"] ?? "");
        $lastSchemaSql =
            (string) ($GLOBALS["PRONTOO_INSTALL_LAST_SCHEMA_SQL"] ?? "");
        if ($lastSchemaSql !== "") {
            $lines[] = "Último schema SQL hash: " . hash("sha256", $lastSchemaSql);
            $lines[] =
                "Último schema SQL: " . \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_compact_text($lastSchemaSql, 2400);
        }
        if ($lastSql !== "") {
            $lines[] = "Último SQL lógico hash: " . hash("sha256", $lastSql);
            $lines[] = "Último SQL lógico: " . \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_compact_text($lastSql, 1600);
        }
        if ($lastRuntimeSql !== "" && $lastRuntimeSql !== $lastSql) {
            $lines[] =
                "Último SQL runtime hash: " . hash("sha256", $lastRuntimeSql);
            $lines[] =
                "Último SQL runtime: " .
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_compact_text($lastRuntimeSql, 1600);
        }
        if (array_key_exists("PRONTOO_INSTALL_LAST_PARAM_COUNT", $GLOBALS)) {
            $lines[] =
                "Último SQL param_count: " .
                (string) $GLOBALS["PRONTOO_INSTALL_LAST_PARAM_COUNT"];
        }
        if ($e instanceof Throwable) {
            $lines[] = "Falha capturada:";
            foreach (\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_throwable_lines($e) as $line) {
                $lines[] = $line;
            }
        }
        $lines[] = "Fim do diagnóstico.";
        return implode("\n", $lines);
    
    }

    public static function install_prepare_writable_paths(): void
    
    {
    
        $checks = \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_environment_checks(true);
        if (\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_environment_has_blocker($checks)) {
            throw new RuntimeException(
                "Pré-checagem de ambiente bloqueou a instalação.",
            );
        }
        if (!is_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path(".htaccess"))) {
            \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_write(
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path(".htaccess"),
                "Deny from all\n",
            );
        }
        if (function_exists("security_storage_deny_file")) {
            \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("logs"));
            \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("pdfs"));
        }
    
    }

    public static function install_safe_failure_message(Throwable $e): string
    
    {
    
        $msg = trim($e->getMessage());
        if ($msg === "") {
            return "A instalação não foi concluída. Revise os dados informados e tente novamente.";
        }
        if (str_contains($msg, "Pré-checagem de ambiente")) {
            return "A instalação foi interrompida porque o ambiente não atende aos requisitos mínimos listados na tela.";
        }
        foreach (
            [
                "Diretório app ausente.",
                "O diretório app/ não permite gravar a configuração.",
            ]
            as $prefix
        ) {
            if (str_starts_with($msg, $prefix)) {
                return $msg;
            }
        }
        if (
            str_contains($msg, "ssd") ||
            str_contains($msg, "storage") ||
            str_contains($msg, "cache") ||
            str_contains($msg, "telemetry") ||
            str_contains($msg, "tmp") ||
            str_contains($msg, "arquivo de teste")
        ) {
            return $msg;
        }
        if (
            str_contains(
                $msg,
                "Tabela(s) com clinic_id fora do registro de isolamento",
            )
        ) {
            return "A instalação encontrou divergência diagnóstica de isolamento multi-consultório. Verifique ssd/tenant_integrity.json e use banco vazio.";
        }
        return "A instalação não foi concluída. Revise os dados informados, o banco vazio e as permissões de escrita em app/ e ssd/.";
    
    }
}
