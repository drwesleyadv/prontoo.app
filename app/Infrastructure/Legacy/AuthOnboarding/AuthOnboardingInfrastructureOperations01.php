<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Legacy\AuthOnboarding;

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

final class AuthOnboardingInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function onboarding_tips_ensure_schema(): void
    
    {
    
        static $validated = false;
        if ($validated || !has_cfg()) {
            return;
        }
        if (!db_table_exists("pi_user_onboarding_tips")) {
            throw new RuntimeException(
                "Schema incompleto: onboarding de usuário indisponível.",
            );
        }
        $validated = true;
    
    }
}
