<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function install_value(string $v, int $max = 255): string
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_value($v, $max);
}
function install_pdf_dir(): string
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_pdf_dir();
}
function install_state(): string
{
    return \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_state();
}
function install_environment_checks(bool $touchPaths = false): array
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_environment_checks($touchPaths);
}
function install_environment_has_blocker(array $checks): bool
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_environment_has_blocker($checks);
}
function install_yesno(bool $value): string
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_yesno($value);
}
function install_compact_text(string $value, int $limit = 1800): string
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_compact_text($value, $limit);
}
function install_path_mode(string $path): string
{
    return \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_path_mode($path);
}
function install_path_report(string $label, string $path): array
{
    return \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_path_report($label, $path);
}
function install_throwable_lines(Throwable $e): array
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_throwable_lines($e);
}
function install_mysql_dsn(string $host, string $db): string
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_mysql_dsn($host, $db);
}
function install_pdo_options(): array
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_pdo_options();
}
function install_open_database(array $context, bool $strictMode = false): PDO
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_open_database($context, $strictMode);
}
function install_database_error_code(Throwable $e): int
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_database_error_code($e);
}
function install_database_error_message(Throwable $e): string
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_database_error_message($e);
}
function install_database_hint_lines(Throwable $e, array $context = []): array
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_database_hint_lines($e, $context);
}
function install_database_probe_lines(array $context): array
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_database_probe_lines($context);
}
function install_technical_report(
    ?Throwable $e = null,
    array $context = [],
    array $checks = [],
): string {
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_technical_report($e, $context, $checks);
}
function install_write_failure_log(string $report): void
{
    \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_write_failure_log($report);
}
function install_checks_html(array $checks): string
{
    return \Prontoo\Presentation\InstallInstaller\InstallInstallerPresentationOperations01::install_checks_html($checks);
}
function install_prepare_writable_paths(): void
{
    \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_prepare_writable_paths();
}
function install_safe_failure_message(Throwable $e): string
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations01::install_safe_failure_message($e);
}
function install_head(string $title): string
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_head($title);
}
function install_tail(): string
{
    return \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_tail();
}
function install_error(
    string $message,
    array $checks = [],
    ?Throwable $e = null,
    array $context = [],
): void {
    \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_error($message, $checks, $e, $context);
}
function install_state_page(string $state): void
{
    \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_state_page($state);
}
function install_form(array $checks): void
{
    \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::install_form($checks);
}
function prontoo_install(): void
{
    \Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::prontoo_install();
}
