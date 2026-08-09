<?php
declare(strict_types=1);

namespace Prontoo\Domain\AuditActivity;

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

final class ActivityValuePolicy
{
    private function __construct()
    {
    }

    public static function activity_text_value(mixed $v): string
    
    {
    
        if (is_array($v)) {
            return trim(implode(", ", array_filter(array_map("strval", $v))));
        }
        return mb_trim((string) $v);
    
    }

    public static function activity_clean_name(string $value, string $fallback = ""): string
    
    {
    
        $value = mb_trim(preg_replace("/\s+/", " ", $value));
        return $value !== "" ? $value : $fallback;
    
    }

    public static function activity_status_from_ctx(array $ctx): string
    
    {
    
        $s = \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value(
            $ctx["status"] ?? ($ctx["novo_status"] ?? ($ctx["active"] ?? "")),
        );
        if ($s === "") {
            return "";
        }
        $l = mb_strtolower($s);
        return match ($l) {
            "1", "true", "sim", "ativo", "ativa" => "ativa",
            "0", "false", "nao", "não", "inativo", "inativa" => "inativa",
            "prevista" => "prevista",
            "paga" => "paga",
            "recebida" => "recebida",
            "cancelada" => "cancelada",
            default => str_replace("_", " ", $s),
        };
    
    }
}
