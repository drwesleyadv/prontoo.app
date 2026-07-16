<?php
declare(strict_types=1);
function install_value(string $v, int $max = 255): string
{
    $v = trim($v);
    if ($v === "" || strlen($v) > $max || preg_match('/[\x00-\x1F\x7F]/', $v)) {
        throw new RuntimeException("Valor de instalação inválido.");
    }
    return $v;
}
function install_pdf_dir(): string
{
    if (function_exists("document_pdf_dir")) {
        return document_pdf_dir();
    }
    return app_root() . "/pdfs";
}
function install_state(): string
{
    $cfg = has_cfg();
    $lock = is_file(storage_path("install.lock"));
    if ($cfg && $lock) {
        return "installed";
    }
    if ($cfg && !$lock) {
        return "config_without_lock";
    }
    if (!$cfg && $lock) {
        return "lock_without_config";
    }
    return "fresh";
}
function install_environment_checks(bool $touchPaths = false): array
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
        prontoo_php_runtime_ok(),
        "PHP",
        prontoo_php_runtime_message(),
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
    $memRaw = prontoo_memory_limit_label();
    $memOk = prontoo_memory_limit_meets(128 * 1024 * 1024);
    $add(
        "memory_limit",
        $memOk,
        "Memória",
        "memory_limit atual: " . $memRaw . ".",
        "warn",
    );
    $appDir = dirname(cfg_file());
    $paths = [
        "app/" => $appDir,
        "storage/" => storage_path(),
        "storage/cache/" => storage_path("cache"),
        "storage/telemetry/" => storage_path("telemetry"),
        "storage/tmp/" => storage_path("tmp"),
        "pdfs/" => install_pdf_dir(),
    ];
    foreach ($paths as $label => $dir) {
        if ($touchPaths && !is_dir($dir)) {
            prontoo_fs_mkdir($dir, 0750, true, false);
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
                prontoo_fs_write($test, "ok", LOCK_EX, false) !== false;
            prontoo_fs_unlink($test, false);
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
function install_environment_has_blocker(array $checks): bool
{
    foreach ($checks as $check) {
        if (($check["level"] ?? "error") === "error" && empty($check["ok"])) {
            return true;
        }
    }
    return false;
}
function install_yesno(bool $value): string
{
    return $value ? "sim" : "não";
}
function install_compact_text(string $value, int $limit = 1800): string
{
    $value = preg_replace("/\s+/", " ", trim($value)) ?? trim($value);
    if (strlen($value) <= $limit) {
        return $value;
    }
    return substr($value, 0, $limit) . "... [truncado]";
}
function install_path_mode(string $path): string
{
    $perms = prontoo_fs_fileperms($path);
    return $perms === false ? "n/d" : substr(sprintf("%o", $perms), -4);
}
function install_path_report(string $label, string $path): array
{
    $parent = dirname($path);
    return [
        "label" => $label,
        "path" => $path,
        "realpath" => realpath($path) ?: "n/d",
        "exists" => file_exists($path),
        "is_dir" => is_dir($path),
        "is_file" => is_file($path),
        "writable" => is_writable($path),
        "mode" => file_exists($path) ? install_path_mode($path) : "n/d",
        "parent" => $parent,
        "parent_exists" => is_dir($parent),
        "parent_writable" => is_writable($parent),
        "parent_mode" => is_dir($parent) ? install_path_mode($parent) : "n/d",
    ];
}
function install_throwable_lines(Throwable $e): array
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
function install_mysql_dsn(string $host, string $db): string
{
    return "mysql:host=" . $host . ";dbname=" . $db . ";charset=utf8mb4";
}
function install_pdo_options(): array
{
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
}
function install_open_database(array $context, bool $strictMode = false): PDO
{
    $pdo = new PDO(
        install_mysql_dsn(
            (string) ($context["db_host"] ?? ""),
            (string) ($context["db_name"] ?? ""),
        ),
        (string) ($context["db_user"] ?? ""),
        (string) ($context["db_pass"] ?? ""),
        install_pdo_options(),
    );
    db_assert_mysql_runtime($pdo);
    db_apply_mysql_session_contract($pdo, $strictMode);
    return $pdo;
}
function install_database_error_code(Throwable $e): int
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
function install_database_error_message(Throwable $e): string
{
    return match (install_database_error_code($e)) {
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
function install_database_hint_lines(Throwable $e, array $context = []): array
{
    $code = install_database_error_code($e);
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
function install_database_probe_lines(array $context): array
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
        $probe = install_open_database($context);
        $version = (string) $probe->query("SELECT VERSION()")->fetchColumn();
        $database = (string) $probe->query("SELECT DATABASE()")->fetchColumn();
        $tableCount = (int) $probe
            ->query(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()",
            )
            ->fetchColumn();
        $lines[] =
            "Probe DB: conexão OK; database=" .
            $database .
            "; mysql_version=" .
            $version .
            "; tabelas=" .
            $tableCount .
            ".";
        if ($tableCount > 0) {
            $stmt = $probe->query(
                "SELECT TABLE_NAME AS table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY TABLE_NAME LIMIT 30",
            );
            $names = [];
            foreach (
                (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) ?: [])
                as $r
            ) {
                $row = [];
                foreach ($r as $k => $v) {
                    if (is_string($k)) {
                        $row[strtolower($k)] = $v;
                    }
                }
                $name = trim((string) ($row["table_name"] ?? ""));
                if ($name !== "") {
                    $names[] = $name;
                }
            }
            $lines[] = "Primeiras tabelas detectadas: " . implode(", ", $names);
        }
    } catch (Throwable $dbError) {
        $lines[] = "Probe DB: falhou.";
        foreach (install_database_hint_lines($dbError, $context) as $line) {
            $lines[] = "DB Diagnóstico: " . $line;
        }
        foreach (install_throwable_lines($dbError) as $line) {
            $lines[] = "DB " . $line;
        }
    }
    return $lines;
}
function install_technical_report(
    ?Throwable $e = null,
    array $context = [],
    array $checks = [],
): string {
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
        install_yesno(extension_loaded("pdo")) .
        "; pdo_mysql=" .
        install_yesno(extension_loaded("pdo_mysql")) .
        "; json=" .
        install_yesno(extension_loaded("json")) .
        "; openssl=" .
        install_yesno(extension_loaded("openssl")) .
        "; hash=" .
        install_yesno(extension_loaded("hash")) .
        "; session=" .
        install_yesno(extension_loaded("session"));
    $lines[] = "app_root: " . app_root();
    $lines[] = "cfg_file: " . cfg_file();
    $lines[] = "install_state: " . install_state();
    $lines[] = "has_cfg: " . install_yesno(has_cfg());
    $lines[] =
        "install.lock exists: " .
        install_yesno(is_file(storage_path("install.lock")));
    foreach (
        [
            ["app/", dirname(cfg_file())],
            ["app/config.php", cfg_file()],
            ["storage/", storage_path()],
            ["storage/install.lock", storage_path("install.lock")],
            ["storage/cache/", storage_path("cache")],
            ["storage/telemetry/", storage_path("telemetry")],
            ["storage/tmp/", storage_path("tmp")],
            ["pdfs/ (rota pública)", install_pdf_dir()],
            ["storage/pdfs/", storage_path("pdfs")],
        ]
        as [$label, $path]
    ) {
        $r = install_path_report($label, $path);
        $lines[] =
            "PATH " .
            $r["label"] .
            ": path=" .
            $r["path"] .
            "; realpath=" .
            $r["realpath"] .
            "; exists=" .
            install_yesno((bool) $r["exists"]) .
            "; dir=" .
            install_yesno((bool) $r["is_dir"]) .
            "; file=" .
            install_yesno((bool) $r["is_file"]) .
            "; writable=" .
            install_yesno((bool) $r["writable"]) .
            "; mode=" .
            $r["mode"] .
            "; parent_exists=" .
            install_yesno((bool) $r["parent_exists"]) .
            "; parent_writable=" .
            install_yesno((bool) $r["parent_writable"]) .
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
    foreach (install_database_probe_lines($context) as $line) {
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
            "Último schema SQL: " . install_compact_text($lastSchemaSql, 2400);
    }
    if ($lastSql !== "") {
        $lines[] = "Último SQL lógico hash: " . hash("sha256", $lastSql);
        $lines[] = "Último SQL lógico: " . install_compact_text($lastSql, 1600);
    }
    if ($lastRuntimeSql !== "" && $lastRuntimeSql !== $lastSql) {
        $lines[] =
            "Último SQL runtime hash: " . hash("sha256", $lastRuntimeSql);
        $lines[] =
            "Último SQL runtime: " .
            install_compact_text($lastRuntimeSql, 1600);
    }
    if (array_key_exists("PRONTOO_INSTALL_LAST_PARAM_COUNT", $GLOBALS)) {
        $lines[] =
            "Último SQL param_count: " .
            (string) $GLOBALS["PRONTOO_INSTALL_LAST_PARAM_COUNT"];
    }
    if ($e instanceof Throwable) {
        $lines[] = "Falha capturada:";
        foreach (install_throwable_lines($e) as $line) {
            $lines[] = $line;
        }
    }
    $lines[] = "Fim do diagnóstico.";
    return implode("\n", $lines);
}
function install_write_failure_log(string $report): void
{
    $file = storage_path("install-error-" . gmdate("Ymd-His") . ".log");
    prontoo_fs_write($file, $report . "\n");
    prontoo_fs_chmod($file, 0640);
}
function install_checks_html(array $checks): string
{
    if (!$checks) {
        return "";
    }
    $out = '<div class="install-checks" aria-label="Pré-checagem do ambiente">';
    foreach ($checks as $check) {
        $class = !empty($check["ok"])
            ? "ok"
            : (($check["level"] ?? "error") === "warn"
                ? "warn"
                : "bad");
        $icon = !empty($check["ok"])
            ? "check_circle"
            : (($check["level"] ?? "error") === "warn"
                ? "warning"
                : "error");
        $out .=
            '<p class="install-check ' .
            $class .
            '">' .
            icon($icon) .
            "<span><b>" .
            e($check["label"]) .
            "</b><small>" .
            e($check["message"]) .
            "</small></span></p>";
    }
    return $out . "</div>";
}
function install_prepare_writable_paths(): void
{
    $checks = install_environment_checks(true);
    if (install_environment_has_blocker($checks)) {
        throw new RuntimeException(
            "Pré-checagem de ambiente bloqueou a instalação.",
        );
    }
    if (!is_file(storage_path(".htaccess"))) {
        prontoo_fs_write(
            storage_path(".htaccess"),
            "Deny from all\n",
        );
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file(storage_path("logs"));
        security_storage_deny_file(storage_path("pdfs"));
    }
}
function install_safe_failure_message(Throwable $e): string
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
        return "A instalação encontrou divergência diagnóstica de isolamento multi-consultório. Verifique storage/tenant_integrity.json e use banco vazio.";
    }
    return "A instalação não foi concluída. Revise os dados informados, o banco vazio e as permissões de escrita em app/, storage/ e pdfs/.";
}
function install_head(string $title): string
{
    return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>' .
        e($title) .
        '</title><meta name="theme-color" content="#334155"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="prontoo-version" content="' .
        e(PRONTOO_VERSION) .
        '"><meta name="csrf-token" content="' .
        e(csrf()) .
        '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" href="/public/assets/favicon-' .
        rawurlencode(PRONTOO_ASSET_REV) .
        '.png" type="image/png"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
        rawurlencode(
            defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
        ) .
        '"><script defer src="/public/assets/app.js?v=' .
        rawurlencode(
            defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
        ) .
        '&ui=install"></script></head><body class="public"><main>';
}
function install_tail(): string
{
    return "</main></body></html>";
}
function install_error(
    string $message,
    array $checks = [],
    ?Throwable $e = null,
    array $context = [],
): void {
    $report =
        $e instanceof Throwable || $context || $checks
            ? install_technical_report($e, $context, $checks)
            : "";
    if ($report !== "") {
        install_write_failure_log($report);
    }
    echo install_head("Instalar Prontoo");
    echo '<section class="auth widebox"><h1>Instalar Prontoo</h1><p class="flash bad">' .
        e($message) .
        "</p>" .
        install_checks_html($checks);
    if ($report !== "") {
        echo '<details class="install-technical" open><summary>Diagnóstico técnico para copiar e colar no ChatGPT</summary><p>Este bloco não inclui a senha do banco nem o secret da aplicação.</p><textarea readonly spellcheck="false" onclick="this.select()">' .
            e($report) .
            "</textarea></details>";
    }
    echo '<p><a class="ghost" href="install.php">Voltar</a></p></section>';
    echo install_tail();
}
function install_state_page(string $state): void
{
    echo install_head("Instalar Prontoo");
    if ($state === "installed") {
        echo '<section class="auth widebox"><h1>Prontoo já configurado</h1><p>Esta instalação já possui configuração e trava de instalação concluída.</p><p><a class="primary" href="/">Abrir</a></p></section>';
    } elseif ($state === "config_without_lock") {
        echo '<section class="auth widebox"><h1>Instalação incompleta</h1><p class="flash bad">O arquivo app/config.php existe, mas storage/install.lock não foi encontrado. Para uma instalação limpa, use outro banco vazio e remova app/config.php apenas se esta configuração parcial puder ser descartada.</p></section>';
    } else {
        echo '<section class="auth widebox"><h1>Instalação incompleta</h1><p class="flash bad">storage/install.lock existe, mas app/config.php não foi encontrado. Remova o lock apenas se esta instalação puder ser refeita do zero.</p></section>';
    }
    echo install_tail();
}
function install_form(array $checks): void
{
    $disabled = install_environment_has_blocker($checks)
        ? ' disabled aria-disabled="true"'
        : "";
    echo install_head("Instalar Prontoo");
    echo '<section class="auth widebox"><h1>Instalar Prontoo</h1><p>Instalação de produção em prontoo.app. Informe um banco de dados vazio; o instalador criará a estrutura inicial e o Desenvolvedor.</p>';
    echo install_checks_html($checks);
    if ($disabled !== "") {
        echo '<p class="flash bad">Corrija os itens obrigatórios da pré-checagem antes de enviar o formulário.</p>';
    }
    echo '<form method="post" class="compact">' .
        csrf_field() .
        '<h2>Banco de dados</h2><div class="two">' .
        form_row("Host", input("db_host", "text", "localhost", "required")) .
        form_row("Banco vazio", input("db_name", "text", "", "required")) .
        '</div><div class="two">' .
        form_row("Usuário do banco", input("db_user", "text", "", "required")) .
        form_row("Senha do banco", input("db_pass", "password")) .
        '</div><h2>Desenvolvedor</h2><div class="two">' .
        form_row(
            "Nome do Desenvolvedor",
            input("admin_name", "text", "", "required"),
        ) .
        form_row(
            "CPF",
            input("admin_cpf", "text", "", 'required inputmode="numeric"'),
        ) .
        '</div><div class="two">' .
        form_row("Nascimento", input("admin_birth", "date", "", "required")) .
        form_row("E-mail", input("admin_email", "email", "", "required")) .
        "</div>" .
        form_row(
            "Senha do Desenvolvedor",
            input(
                "admin_password",
                "password",
                "",
                'required minlength="8" data-password-strength data-password-toggle',
            ),
        ) .
        '<button type="submit" class="primary wide"' .
        $disabled .
        ">Instalar e criar Desenvolvedor</button></form></section>";
    echo install_tail();
}
function prontoo_install(): void
{
    try {
        boot_security();
        guard_request();
        headers_secure(true);
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        $state = install_state();
        if ($state !== "fresh") {
            install_state_page($state);
            return;
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
            install_form(install_environment_checks(false));
            return;
        }
        $checks = install_environment_checks(true);
        if (install_environment_has_blocker($checks)) {
            install_error(
                "O ambiente ainda não atende aos requisitos mínimos para instalar.",
                $checks,
                new RuntimeException("Pré-checagem obrigatória falhou."),
            );
            return;
        }
        if (!hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"] ?? "")) {
            http_response_code(403);
            install_error("Sessão expirada. Volte e tente novamente.");
            return;
        }
        $host = install_value((string) ($_POST["db_host"] ?? ""), 180);
        $db = install_value((string) ($_POST["db_name"] ?? ""), 120);
        $user = install_value((string) ($_POST["db_user"] ?? ""), 120);
        $pass = (string) ($_POST["db_pass"] ?? "");
        $adminName = install_value((string) ($_POST["admin_name"] ?? ""), 180);
        $adminEmail = filter_var(
            trim((string) ($_POST["admin_email"] ?? "")),
            FILTER_VALIDATE_EMAIL,
        );
        $adminBirth = trim((string) ($_POST["admin_birth"] ?? ""));
        $adminPass = (string) ($_POST["admin_password"] ?? "");
        $adminCpf = only_digits((string) ($_POST["admin_cpf"] ?? ""));
        $installContext = [
            "db_host" => $host,
            "db_name" => $db,
            "db_user" => $user,
            "db_pass" => $pass,
        ];
        if (!$adminEmail) {
            install_error("Informe um e-mail válido para o Desenvolvedor.");
            return;
        }
        if (!valid_birth_date($adminBirth)) {
            install_error("Nascimento inválido.");
            return;
        }
        if (!password_ok($adminPass)) {
            install_error(
                "A senha do Desenvolvedor precisa ter pelo menos 8 caracteres e dois tipos de caractere.",
            );
            return;
        }
        if (!valid_cpf($adminCpf)) {
            install_error("Este CPF não existe.");
            return;
        }
        try {
            $tmp = install_open_database($installContext, true);
            $tbl = $tmp
                ->query(
                    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()",
                )
                ->fetchColumn();
            if ((int) $tbl > 0) {
                install_error(
                    "Este instalador só deve ser usado com banco de dados totalmente vazio.",
                    [],
                    new RuntimeException(
                        "Banco de dados não está vazio: foram encontradas " .
                            (int) $tbl .
                            " tabela(s).",
                    ),
                    $installContext,
                );
                return;
            }
        } catch (Throwable $e) {
            error_log("[Prontoo install db] " . $e->getMessage());
            install_error(
                install_database_error_message($e),
                [],
                $e,
                $installContext,
            );
            return;
        }
        $secret = bin2hex(random_bytes(32));
        $code =
            "<?php\nreturn " .
            var_export(
                [
                    "db_host" => $host,
                    "db_name" => $db,
                    "db_user" => $user,
                    "db_pass" => $pass,
                    "installed_at" => gmdate("c"),
                    "secret" => $secret,
                ],
                true,
            ) .
            ";\n";
        $cfgWritten = false;
        $schemaInstalled = false;
        try {
            install_prepare_writable_paths();
            if (prontoo_fs_write(cfg_file(), $code) === false) {
                throw new RuntimeException(
                    "Não foi possível gravar app/config.php.",
                );
            }
            $cfgWritten = true;
            prontoo_fs_chmod(cfg_file(), 0640);
            install_fresh_schema();
            $schemaInstalled = true;
            runtime_self_check();
            if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                \Prontoo\Core\Integrity\PiIntegrity::bootIndexAutotest(5000);
            }
            if (class_exists("\\Prontoo\\Core\\Integrity\\PiSequence")) {
                \Prontoo\Core\Integrity\PiSequence::ensureCounterTable(pdo());
            }
            if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                \Prontoo\Core\Integrity\PiIntegrity::ensureGlobalSequence(
                    15000,
                );
            }
            db_begin_transaction();
            $pid = upsert_person($adminName, $adminCpf, $adminBirth);
            q(
                "INSERT INTO pi_users (person_id,name,email,password_hash,is_global_admin,active,created_at) VALUES (?,?,?,?,1,1,NOW())",
                [
                    $pid,
                    $adminName,
                    (string) $adminEmail,
                    password_hash_secure($adminPass),
                ],
            );
            $uid = db_last_insert_id();
            if (!pdo()->inTransaction()) {
                throw new RuntimeException(
                    "Transação de instalação encerrada antes do commit; verifique DDL executado por provas PI durante INSERT de domínio.",
                );
            }
            db_commit();
            audit("usuario_salvo", "usuario", $uid, [
                "nome" => $adminName,
                "perfil" => "Desenvolvedor",
            ]);
            install_prepare_writable_paths();
            if (
                prontoo_fs_write(
                    storage_path("install.lock"),
                    gmdate("c"),
                ) === false
            ) {
                throw new RuntimeException(
                    "Não foi possível gravar storage/install.lock.",
                );
            }
            prontoo_fs_chmod(storage_path("install.lock"), 0640);
            flash("Desenvolvedor criado. Entre com CPF e senha.");
            header("Location: /?r=login");
            exit();
        } catch (Throwable $e) {
            if (has_cfg()) {
                try {
                    if (pdo()->inTransaction()) {
                        db_rollback();
                    }
                } catch (Throwable $ignored) {
                    error_log(
                        "[Prontoo recoverable " .
                            __FUNCTION__ .
                            "] " .
                            $ignored->getMessage(),
                    );
                }
            }
            error_log("[Prontoo install] " . $e->getMessage());
            if ($cfgWritten && !is_file(storage_path("install.lock"))) {
                if ($schemaInstalled && has_cfg()) {
                    try {
                        $GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"] = true;
                        $GLOBALS["PRONTOO_SCHEMA_INSTALLING"] = true;
                        schema_cleanup_failed_install(
                            prontoo_schema_table_names(),
                        );
                        prontoo_fs_unlink(schema_lock_file());
                    } catch (Throwable $cleanupError) {
                        error_log(
                            "[Prontoo install cleanup] " .
                                $cleanupError->getMessage(),
                        );
                    } finally {
                        unset(
                            $GLOBALS["PRONTOO_SCHEMA_INSTALLING"],
                            $GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"],
                        );
                    }
                }
                $installContext["cleanup_note"] =
                    "A instalação incompleta foi revertida e a configuração temporária foi removida.";
                prontoo_fs_unlink(cfg_file());
            }
            install_error(
                install_safe_failure_message($e),
                [],
                $e,
                $installContext ?? [],
            );
            return;
        }
    } catch (Throwable $e) {
        error_log("[Prontoo install fatal] " . $e->getMessage());
        if (!headers_sent()) {
            try {
                install_error(
                    "Falha técnica antes de concluir o instalador.",
                    [],
                    $e,
                    $installContext ?? [],
                );
                return;
            } catch (Throwable $renderError) {
                error_log(
                    "[Prontoo install diagnostic render failed] " .
                        $renderError->getMessage(),
                );
            }
        }
        app_fail($e);
    }
}
