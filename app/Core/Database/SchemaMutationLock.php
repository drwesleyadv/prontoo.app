<?php
declare(strict_types=1);

namespace Prontoo\Core\Database;

use Prontoo\Core\Install\InstallAccess;

final class SchemaMutationLock
{
    private static int $depth = 0;
    private static ?string $nonce = null;

    private function __construct() {}

    public static function runForInstaller(callable $callback): mixed
    {
        if (!self::mayOpenInstallerWindow()) {
            throw new \RuntimeException('A janela estrutural só pode ser aberta pelo instalador local ou pela certificação controlada.');
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
        if (PHP_SAPI === 'cli') {
            return (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' ||
                (string) getenv('PRONTOO_ALLOW_LOCAL_INSTALL') === '1';
        }
        return InstallAccess::isLocalHttpRequest();
    }
}
