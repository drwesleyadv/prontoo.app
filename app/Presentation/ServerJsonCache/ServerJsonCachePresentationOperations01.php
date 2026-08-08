<?php
declare(strict_types=1);

namespace Prontoo\Presentation\ServerJsonCache;

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

final class ServerJsonCachePresentationOperations01
{
    private function __construct()
    {
    }

    public static function server_json_cache_request_is_read(): bool
    
    {
    
        if (PHP_SAPI === "cli") {
            return false;
        }
        return strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")) === "GET";
    
    }
}
