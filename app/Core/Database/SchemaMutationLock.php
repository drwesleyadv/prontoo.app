<?php
declare(strict_types=1);

namespace Prontoo\Core\Database;

final class SchemaMutationLock
{
    private const PUBLIC_INSTALL_WINDOW_START_UNIX = 1785186607;
    private const PUBLIC_INSTALL_WINDOW_END_UNIX = 1785190207;

    private static int $depth = 0;
    private static ?string $nonce = null;

    private function __construct() {
        








    }

    public static function runForInstaller(callable $callback): mixed
    {
        










        if (!self::mayOpenInstallerWindow()) {
            throw new \RuntimeException('A janela estrutural só pode ser aberta pelo instalador autorizado ou pela certificação controlada.');
        }
        if (self::$depth !== 0 || self::$nonce !== null) {
            throw new \RuntimeException('Janela estrutural já está ativa.');
        }
        $nonce = bin2hex(random_bytes(32));
        self::$depth = 1;
        self::$nonce = $nonce;
        $GLOBALS['PRONTOO_SCHEMA_MUTATION_NONCE'] = $nonce;
        $GLOBALS['PRONTOO_SCHEMA_INSTALLING'] = true;
        try {
            return $callback();
        } finally {
            unset($GLOBALS['PRONTOO_SCHEMA_INSTALLING'], $GLOBALS['PRONTOO_SCHEMA_MUTATION_NONCE']);
            self::$nonce = null;
            self::$depth = 0;
        }
    }

    public static function isActive(): bool
    {
        









        $provided = (string) ($GLOBALS['PRONTOO_SCHEMA_MUTATION_NONCE'] ?? '');
        return self::$depth === 1 &&
            self::$nonce !== null &&
            $provided !== '' &&
            hash_equals(self::$nonce, $provided);
    }

    public static function assertActive(): void
    {
        









        if (!self::isActive()) {
            throw new \RuntimeException('A estrutura do banco está congelada fora da janela privada do instalador.');
        }
    }

    private static function mayOpenInstallerWindow(): bool
    {
        










        if (
            PHP_SAPI === 'cli' &&
            (string) getenv('GITHUB_ACTIONS') === 'true' &&
            (string) getenv('CI') === 'true' &&
            (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' &&
            (string) getenv('PRONTOO_INSTALLER_CLI_MODE') === '1'
        ) {
            return true;
        }

        if (PHP_SAPI === 'cli') {
            return false;
        }

        $now = time();
        if ($now < self::PUBLIC_INSTALL_WINDOW_START_UNIX ||
            $now >= self::PUBLIC_INSTALL_WINDOW_END_UNIX) {
            return false;
        }

        if (strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) !== 'POST') {
            return false;
        }
        if ((string) ($_GET['r'] ?? '') !== 'install') {
            return false;
        }

        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        if (!in_array($https, ['on', '1'], true) &&
            (string) ($_SERVER['SERVER_PORT'] ?? '') !== '443') {
            return false;
        }

        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host) ?? '';
        if ($host !== 'prontoo.app') {
            return false;
        }

        $root = dirname(__DIR__, 3);
        return !is_file($root . '/ssd/install.lock');
    }
}
