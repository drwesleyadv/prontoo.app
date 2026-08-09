<?php
declare(strict_types=1);

namespace Prontoo\Presentation\AuditActivity;

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

final class AuditActivityPresentationOperations01
{
    private function __construct()
    {
    }

    public static function audit_money_text(
        array $ctx,
        array $keys = ["valor", "amount", "amount_cents", "target_cents"],
    ): string 
    {
    
        foreach ($keys as $key) {
            if (!array_key_exists((string) $key, $ctx)) {
                continue;
            }
            $v = $ctx[(string) $key];
            if (
                is_int($v) ||
                is_float($v) ||
                (is_string($v) && preg_match('/^-?\\d+$/', (string) $v))
            ) {
                return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $v);
            }
            $text = mb_trim((string) $v);
            if ($text !== "") {
                return $text;
            }
        }
        return "";
    
    }

    public static function activity_money_from_ctx(array $ctx): string
    
    {
    
        foreach (
            ["valor", "amount_cents", "amount", "target_cents", "price_cents"]
            as $k
        ) {
            if (array_key_exists($k, $ctx) && is_numeric($ctx[$k])) {
                return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $ctx[$k]);
            }
        }
        return "";
    
    }
}
