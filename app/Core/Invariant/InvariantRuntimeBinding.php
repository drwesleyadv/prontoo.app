<?php
declare(strict_types=1);

namespace Prontoo\Core\Invariant;

final class InvariantRuntimeBinding
{
    private static ?InvariantRuntimePort $port = null;

    private function __construct()
    {
    }

    public static function configure(InvariantRuntimePort $port): void
    {
        self::$port = $port;
    }

    public static function port(): ?InvariantRuntimePort
    {
        return self::$port;
    }
}
