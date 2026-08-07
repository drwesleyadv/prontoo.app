<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function security_ip_in_cidr(string $ip, string $cidr): bool
{
    return \Prontoo\Infrastructure\Legacy\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_ip_in_cidr($ip, $cidr);
}
function security_trusted_proxy_request(): bool
{
    return \Prontoo\Runtime\Legacy\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_trusted_proxy_request();
}
function security_https_active(): bool
{
    return \Prontoo\Runtime\Legacy\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_https_active();
}

function security_disable_runtime_error_display(): void
{
    \Prontoo\Infrastructure\Legacy\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_disable_runtime_error_display();
}

function privacy_sanitize_text(string $text, int $limit = 900): string
{
    return \Prontoo\Infrastructure\Legacy\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_text($text, $limit);
}

function privacy_sanitize_error_message(Throwable $e, int $limit = 900): string
{
    return \Prontoo\Infrastructure\Legacy\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($e, $limit);
}

function privacy_log_file_label(string $file): string
{
    return \Prontoo\Infrastructure\Legacy\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::privacy_log_file_label($file);
}

function security_storage_deny_file(string $dir): void
{
    \Prontoo\Infrastructure\Legacy\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
}
