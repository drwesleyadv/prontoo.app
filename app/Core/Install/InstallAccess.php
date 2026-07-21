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

    private function __construct() {}

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
        $host = strtolower(trim((string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '')));
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
  if (trim((string) ($server[$header] ?? '')) !== '') {
      return false;
  }
        }
        return in_array(self::requestHostFrom($server), self::LOCAL_HOSTS, true);
    }

    public static function isLocalHttpRequest(): bool
    {
        return PHP_SAPI !== 'cli' && self::isLocalServer($_SERVER);
    }

    public static function isLocalExecution(): bool
    {
        return PHP_SAPI === 'cli' || self::isLocalHttpRequest();
    }

    public static function assertLocalEntry(): void
    {
        if (!self::isLocalExecution()) {
            self::denyPublicAccess();
        }
    }

    public static function denyPublicAccess(): never
    {
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }
        echo "Not Found\\n";
        exit;
    }
}
