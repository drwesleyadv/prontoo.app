<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Auth;

use Prontoo\Infrastructure\Auth\PdoLoginThrottle;
use Throwable;

final class LoginThrottle
{
    private function __construct()
    {
    }

    public static function waitSeconds(string $cpf): int
    {
        try {
            return PdoLoginThrottle::waitSeconds(login_bucket_keys($cpf));
        } catch (Throwable $error) {
            self::unavailable($error);
        }
    }

    public static function registerFailure(string $cpf): int
    {
        try {
            return PdoLoginThrottle::registerFailure(login_bucket_keys($cpf));
        } catch (Throwable $error) {
            self::unavailable($error);
        }
    }

    public static function clear(string $cpf): void
    {
        try {
            PdoLoginThrottle::clear(login_bucket_keys($cpf));
        } catch (Throwable $error) {
            self::unavailable($error);
        }
    }

    private static function unavailable(Throwable $error): never
    {
        error_log('[Prontoo login throttle unavailable] ' . $error->getMessage());
        throw new \ProntooHttpError(
            503,
            'A autenticação está temporariamente indisponível. Tente novamente em instantes.',
        );
    }
}
