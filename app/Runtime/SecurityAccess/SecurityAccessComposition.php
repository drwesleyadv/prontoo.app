<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SecurityAccess;

use Prontoo\Application\SecurityAccess\SecurityAccessPersistencePort;
use Prontoo\Infrastructure\SecurityAccess\PdoSecurityAccessPersistence;

final class SecurityAccessComposition
{
    private static ?SecurityAccessPersistencePort $persistence = null;

    private function __construct()
    {
    }

    public static function persistence(): SecurityAccessPersistencePort
    {
        return self::$persistence ??= new PdoSecurityAccessPersistence();
    }

    public static function configure(SecurityAccessPersistencePort $persistence): void
    {
        self::$persistence = $persistence;
    }
}
