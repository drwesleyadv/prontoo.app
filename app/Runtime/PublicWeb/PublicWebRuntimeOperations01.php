<?php
declare(strict_types=1);

namespace Prontoo\Runtime\PublicWeb;

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

final class PublicWebRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function page_mobile_web_access(): void
    
    {
    
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login", ["source" => "mobile_web"]);
    
    }
}
