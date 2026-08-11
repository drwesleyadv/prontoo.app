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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
            '</title><meta name="theme-color" content="#334155"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="prontoo-version" content="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(PRONTOO_VERSION) .
            '"><meta name="csrf-token" content="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::csrf()) .
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
                ? \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_technical_report($e, $context, $checks)
                : "";
        if ($report !== "") {
            \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_write_failure_log($report);
        }
        echo \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_head("Instalar Prontoo");
        echo '<section class="auth widebox"><h1>Instalar Prontoo</h1><p class="flash bad">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($message) .
            "</p>" .
            \Prontoo\Presentation\InstallInstaller\InstallInstallerPresentationOperations01::install_checks_html($checks);
        if ($report !== "") {
            echo '<details class="install-technical" open><summary>Diagnóstico técnico para copiar e colar no ChatGPT</summary><p>Este bloco não inclui a senha do banco nem o secret da aplicação.</p><textarea readonly spellcheck="false" onclick="this.select()">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($report) .
                "</textarea></details>";
        }
        echo '<p><a class="ghost" href="install.php">Voltar</a></p></section>';
        echo \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_tail();
    
    }

    public static function install_state_page(string $state): void
    
    {
    
        echo \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_head("Instalar Prontoo");
        if ($state === "installed") {
            echo '<section class="auth widebox"><h1>Prontoo já configurado</h1><p>Esta instalação já possui configuração e trava de instalação concluída.</p><p><a class="primary" href="/">Abrir</a></p></section>';
        } elseif ($state === "config_without_lock") {
            echo '<section class="auth widebox"><h1>Instalação incompleta</h1><p class="flash bad">O arquivo app/config.php existe, mas ssd/install.lock não foi encontrado. Para uma instalação limpa, use outro banco vazio e remova app/config.php apenas se esta configuração parcial puder ser descartada.</p></section>';
        } else {
            echo '<section class="auth widebox"><h1>Instalação incompleta</h1><p class="flash bad">ssd/install.lock existe, mas app/config.php não foi encontrado. Remova o lock apenas se esta instalação puder ser refeita do zero.</p></section>';
        }
        echo \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_tail();
    
    }

    public static function install_form(array $checks): void
    
    {
    
        $disabled = \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_environment_has_blocker($checks)
            ? ' disabled aria-disabled="true"'
            : "";
        echo \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_head("Instalar Prontoo");
        echo '<section class="auth widebox"><h1>Instalar Prontoo</h1><p>Instalação de produção em prontoo.app. Informe um banco de dados vazio; o instalador criará a estrutura inicial e o Desenvolvedor.</p>';
        echo \Prontoo\Presentation\InstallInstaller\InstallInstallerPresentationOperations01::install_checks_html($checks);
        if ($disabled !== "") {
            echo '<p class="flash bad">Corrija os itens obrigatórios da pré-checagem antes de enviar o formulário.</p>';
        }
        echo '<form method="post" class="compact">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<h2>Banco de dados</h2><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Host", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("db_host", "text", "localhost", "required")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Banco vazio", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("db_name", "text", "", "required")) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Usuário do banco", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("db_user", "text", "", "required")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Senha do banco", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("db_pass", "password")) .
            '</div><h2>Desenvolvedor</h2><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome do Desenvolvedor",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("admin_name", "text", "", "required"),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("admin_cpf", "text", "", 'required inputmode="numeric"'),
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Nascimento", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("admin_birth", "date", "", "required")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("E-mail", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("admin_email", "email", "", "required")) .
            "</div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Senha do Desenvolvedor",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "admin_password",
                    "password",
                    "",
                    'required minlength="8" maxlength="128" data-password-strength data-password-toggle',
                ),
            ) .
            '<button type="submit" class="primary wide"' .
            $disabled .
            ">Instalar e criar Desenvolvedor</button></form></section>";
        echo \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_tail();
    
    }

    public static function prontoo_install(): void
    
    {
    
        try {
            \Prontoo\Core\Install\InstallAccess::assertInstallerEntry();
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::boot_security();
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::guard_request();
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::headers_secure(true);
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            $state = \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_state();
            if ($state !== "fresh") {
                \Prontoo\Core\Install\InstallAccess::denyPublicAccess();
            }
            if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_form(\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_environment_checks(false));
                return;
            }
            $checks = \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_environment_checks(true);
            if (\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_environment_has_blocker($checks)) {
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error(
                    "O ambiente ainda não atende aos requisitos mínimos para instalar.",
                    $checks,
                    new RuntimeException("Pré-checagem obrigatória falhou."),
                );
                return;
            }
            if (!hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"] ?? "")) {
                http_response_code(403);
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error("Sessão expirada. Volte e tente novamente.");
                return;
            }
            $host = \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_value((string) ($_POST["db_host"] ?? ""), 180);
            $db = \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_value((string) ($_POST["db_name"] ?? ""), 120);
            $user = \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_value((string) ($_POST["db_user"] ?? ""), 120);
            $pass = (string) ($_POST["db_pass"] ?? "");
            $adminName = \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_value((string) ($_POST["admin_name"] ?? ""), 180);
            $adminEmail = filter_var(
                mb_trim((string) ($_POST["admin_email"] ?? "")),
                FILTER_VALIDATE_EMAIL,
            );
            $adminBirth = mb_trim((string) ($_POST["admin_birth"] ?? ""));
            $adminPass = (string) ($_POST["admin_password"] ?? "");
            $adminCpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_POST["admin_cpf"] ?? ""));
            $installContext = [
                "db_host" => $host,
                "db_name" => $db,
                "db_user" => $user,
                "db_pass" => $pass,
            ];
            if (!$adminEmail) {
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error("Informe um e-mail válido para o Desenvolvedor.");
                return;
            }
            if (!\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($adminBirth)) {
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error("Nascimento inválido.");
                return;
            }
            if (!\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_ok($adminPass)) {
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error(
                    "A senha do Desenvolvedor precisa ter entre 8 e 128 caracteres e não pode ser uma senha comum.",
                );
                return;
            }
            if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($adminCpf)) {
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error("Este CPF não existe.");
                return;
            }
            try {
                $probe = \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_database_probe($installContext, true);
                $tbl = (int) ($probe['table_count'] ?? 0);
                if ((int) $tbl > 0) {
                    \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error(
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
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error(
                    \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_database_error_message($e),
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
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_prepare_writable_paths();
                if (\Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_write(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg_file(), $code) === false) {
                    throw new RuntimeException(
                        "Não foi possível gravar app/config.php.",
                    );
                }
                $cfgWritten = true;
                \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_chmod(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg_file(), 0640);
                \Prontoo\Core\Database\SchemaMutationLock::runForInstaller(
                    static function (): void {
    
                        \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::prepare_fresh_database();
                    },
                );
                $schemaInstalled = true;
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::runtime_self_check();
                if (class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity")) {
                    \Prontoo\Infrastructure\Integrity\PiIntegrity::bootIndexAutotest(5000);
                }
                if (class_exists("\\Prontoo\\Infrastructure\\Database\\SeqContract")) {
                    \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::assert_runtime_sequence_contract();
                }
                $uid = \Prontoo\Runtime\Operational\OperationalComposition::installationAccount()
                    ->createInitialDeveloper(
                        $adminName,
                        (string) $adminEmail,
                        \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_hash_secure($adminPass),
                        static fn(): int => \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::upsert_person(
                            $adminName,
                            $adminCpf,
                            $adminBirth,
                        ),
                        static fn(): bool => \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::runtime_database_in_transaction(),
                    );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("usuario_salvo", "usuario", $uid, [
                    "nome" => $adminName,
                    "perfil" => "Desenvolvedor",
                ]);
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_prepare_writable_paths();
                if (
                    \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_write(
                        \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("install.lock"),
                        gmdate("c"),
                    ) === false
                ) {
                    throw new RuntimeException(
                        "Não foi possível gravar ssd/install.lock.",
                    );
                }
                \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_chmod(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("install.lock"), 0640);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Desenvolvedor criado. Entre com CPF e senha.");
                header("Location: /?r=login");
                exit();
            } catch (Throwable $e) {
                error_log("[Prontoo install] " . $e->getMessage());
                if ($cfgWritten && !is_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("install.lock"))) {
                    if ($schemaInstalled && \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
                        try {
                            \Prontoo\Core\Database\SchemaMutationLock::runForInstaller(
                                static function (): void {
    
                                    $GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"] = true;
                                    try {
                                    \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::revert_failed_commissioning();
                                        \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_unlink(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_lock_file());
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
                    \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_unlink(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg_file());
                }
                \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error(
                    \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_safe_failure_message($e),
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
                    \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error(
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
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_fail($e);
        }
    
    }
}
