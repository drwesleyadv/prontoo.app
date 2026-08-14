<?php
declare(strict_types=1);

namespace Prontoo\Core\Install;

final class InstallAccess
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1'];
    private const FORWARDED_HEADERS = [
        'HTTP_FORWARDED',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CF_CONNECTING_IP',
        'HTTP_TRUE_CLIENT_IP',
    ];
    private const PUBLIC_INSTALL_WINDOW_START_UNIX = 1785770400;
    private const PUBLIC_INSTALL_WINDOW_END_UNIX = 1785777600;

    private function __construct() {

    }

    public static function isLoopbackAddress(string $address): bool
    {

        $address = trim($address);
        if ($address === '::1') {
            return true;
        }
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        return str_starts_with($address, '127.');
    }

    public static function requestHostFrom(array $server): string
    {

        $host = strtolower(mb_trim((string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '')));
        if ($host === '') {
            return '';
        }
        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');
            return $end === false ? '' : substr($host, 1, $end - 1);
        }
        return preg_replace('/:\d+$/', '', $host) ?? '';
    }

    public static function requestHost(): string
    {

        return self::requestHostFrom($_SERVER);
    }

    public static function isLocalServer(array $server): bool
    {

        if (!self::isLoopbackAddress((string) ($server['REMOTE_ADDR'] ?? ''))) {
            return false;
        }
        foreach (self::FORWARDED_HEADERS as $header) {
            if (mb_trim((string) ($server[$header] ?? '')) !== '') {
                return false;
            }
        }
        return in_array(self::requestHostFrom($server), self::LOCAL_HOSTS, true);
    }

    public static function isLocalHttpRequest(): bool
    {

        return PHP_SAPI !== 'cli' && self::isLocalServer($_SERVER);
    }

    public static function isInstallerExecutionAllowed(?array $server = null, ?int $now = null): bool
    {

        if (PHP_SAPI === 'cli' && $server === null) {
            return (string) getenv('GITHUB_ACTIONS') === 'true' &&
                (string) getenv('CI') === 'true' &&
                (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' &&
                (string) getenv('PRONTOO_INSTALLER_CLI_MODE') === '1';
        }
        if (PHP_SAPI === 'cli' || $server !== null) {
            return false;
        }

        $now ??= time();
        if ($now < self::PUBLIC_INSTALL_WINDOW_START_UNIX ||
            $now >= self::PUBLIC_INSTALL_WINDOW_END_UNIX) {
            return false;
        }

        $request = $_SERVER;
        $method = strtoupper(mb_trim((string) ($request['REQUEST_METHOD'] ?? 'GET')));
        if (!in_array($method, ['GET', 'POST'], true)) {
            return false;
        }
        $https = strtolower(mb_trim((string) ($request['HTTPS'] ?? '')));
        if (!in_array($https, ['on', '1'], true) &&
            (string) ($request['SERVER_PORT'] ?? '') !== '443') {
            return false;
        }
        if (self::requestHostFrom($request) !== 'prontoo.app') {
            return false;
        }

        $root = dirname(__DIR__, 3);
        return !is_file($root . '/app/config.php') &&
            !is_file($root . '/ssd/install.lock');
    }

    public static function assertInstallerEntry(): void
    {

        if (!self::isInstallerExecutionAllowed()) {
            self::denyPublicAccess();
        }
    }

    public static function denyPublicAccess(): never
    {

        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
            header('X-Content-Type-Options: nosniff');
        }
        $document = dirname(__DIR__, 3) . '/public/errors/404.html';
        if (is_file($document)) {
            readfile($document);
        } else {
            echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Página não encontrada · Prontoo</title><body><main><h1>Esta página não está por aqui</h1><p><a href="/">Voltar ao Prontoo</a></p></main></body></html>';
        }
        exit;
    }
}
