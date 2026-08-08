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

final class InstallInstallerRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function install_head(string $title): string
    
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

    public static function install_tail(): string
    
    {
    
        return "</main></body></html>";
    
    }

    public static function install_error(
        string $message,
        array $checks = [],
        ?Throwable $e = null,
        array $context = [],
    ): void 
    {
    
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

    public static function install_state_page(string $state): void
    
    {
    
        echo install_head("Instalar Prontoo");
        if ($state === "installed") {
            echo '<section class="auth widebox"><h1>Prontoo já configurado</h1><p>Esta instalação já possui configuração e trava de instalação concluída.</p><p><a class="primary" href="/">Abrir</a></p></section>';
        } elseif ($state === "config_without_lock") {
            echo '<section class="auth widebox"><h1>Instalação incompleta</h1><p class="flash bad">O arquivo app/config.php existe, mas ssd/install.lock não foi encontrado. Para uma instalação limpa, use outro banco vazio e remova app/config.php apenas se esta configuração parcial puder ser descartada.</p></section>';
        } else {
            echo '<section class="auth widebox"><h1>Instalação incompleta</h1><p class="flash bad">ssd/install.lock existe, mas app/config.php não foi encontrado. Remova o lock apenas se esta instalação puder ser refeita do zero.</p></section>';
        }
        echo install_tail();
    
    }

    public static function install_form(array $checks): void
    
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
                    'required minlength="8" maxlength="128" data-password-strength data-password-toggle',
                ),
            ) .
            '<button type="submit" class="primary wide"' .
            $disabled .
            ">Instalar e criar Desenvolvedor</button></form></section>";
        echo install_tail();
    
    }

    public static function prontoo_install(): void
    
    {
    
        try {
            \Prontoo\Core\Install\InstallAccess::assertInstallerEntry();
            boot_security();
            guard_request();
            headers_secure(true);
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            $state = install_state();
            if ($state !== "fresh") {
                \Prontoo\Core\Install\InstallAccess::denyPublicAccess();
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
                mb_trim((string) ($_POST["admin_email"] ?? "")),
                FILTER_VALIDATE_EMAIL,
            );
            $adminBirth = mb_trim((string) ($_POST["admin_birth"] ?? ""));
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
                    "A senha do Desenvolvedor precisa ter entre 8 e 128 caracteres e não pode ser uma senha comum.",
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
                \Prontoo\Core\Database\SchemaMutationLock::runForInstaller(
                    static function (): void {
    
                        install_fresh_schema();
                    },
                );
                $schemaInstalled = true;
                runtime_self_check();
                if (class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity")) {
                    \Prontoo\Infrastructure\Integrity\PiIntegrity::bootIndexAutotest(5000);
                }
                if (class_exists("\\Prontoo\\Infrastructure\\Database\\SeqContract")) {
                    \Prontoo\Infrastructure\Database\SeqContract::assert(pdo());
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
                        "Não foi possível gravar ssd/install.lock.",
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
                            \Prontoo\Core\Database\SchemaMutationLock::runForInstaller(
                                static function (): void {
    
                                    $GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"] = true;
                                    try {
                                        schema_cleanup_failed_install(prontoo_schema_table_names());
                                        prontoo_fs_unlink(schema_lock_file());
                                    } finally {
                                        unset($GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"]);
                                    }
                                },
                            );
                        } catch (Throwable $cleanupError) {
                            error_log(
                                "[Prontoo install cleanup] " .
                                    $cleanupError->getMessage(),
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
}
