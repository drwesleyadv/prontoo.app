<?php
declare(strict_types=1);

namespace Prontoo\Domain\Leads;

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

final class LeadsDomainOperations01
{
    private function __construct()
    {
    }

    public static function lead_stage_options(): array
    
    {
    
        return [
            "em_aberto" => "Em Aberto",
            "aguarda_retorno" => "Aguarda retorno",
            "convertido" => "Convertido",
            "arquivado" => "Arquivado",
        ];
    
    }

    public static function lead_stage_icons(): array
    
    {
    
        return [
            "em_aberto" => "chat_bubble",
            "aguarda_retorno" => "schedule",
            "convertido" => "person_add",
            "arquivado" => "archive",
        ];
    
    }

    public static function lead_stage_normalize(?string $stage): string
    
    {
    
        $candidate = mb_trim((string) $stage);
        return array_key_exists($candidate, self::lead_stage_options())
            ? $candidate
            : "em_aberto";
    
    }

    public static function lead_active_stages(): array
    
    {
    
        return ["em_aberto", "aguarda_retorno"];
    
    }

}
