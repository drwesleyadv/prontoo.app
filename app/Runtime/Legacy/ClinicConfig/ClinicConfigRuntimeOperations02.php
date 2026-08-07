<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\ClinicConfig;

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

final class ClinicConfigRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function clinic_choice_card(array $r): string
    
    {
    
        $place = trim(
            (string) ($r["address_city"] ?? "") .
                (!empty($r["address_state"]) ? " / " . $r["address_state"] : ""),
        );
        $clinic = mb_trim((string) ($r["display_name"] ?? "Consultório"));
        $roleCode = (string) ($r["role_code"] ?? "");
        $role = role_label_for($roleCode, (int) $r["clinic_id"]);
        $ico = function_exists("role_icon")
            ? role_icon($roleCode, (int) $r["clinic_id"])
            : "badge";
        return '<button class="clinic-choice credential-choice" type="submit" name="clinic_role_id" value="' .
            (int) $r["id"] .
            '"><span class="credential-icon">' .
            icon($ico) .
            '</span><span class="credential-main"><span class="credential-role">' .
            e($role) .
            '</span><span class="credential-context"><span>' .
            e($clinic !== "" ? $clinic : "Consultório") .
            "</span><small>" .
            e($place !== "" ? $place : "Cidade não informada") .
            '</small></span></span><span class="credential-enter">' .
            icon("login") .
            "</span></button>";
    
    }

    public static function clinic_options_global(): array
    
    {
    
        $loader = function (): array {
    
            $rows = q(
                "SELECT id,display_name FROM pi_clinics ORDER BY id DESC LIMIT 500",
            )->fetchAll();
            $options = ["" => "Sem consultório específico"];
            foreach ($rows as $row) {
                $options[(int) $row["id"]] = (string) $row["display_name"];
            }
            return $options;
        };
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "clinic",
                server_json_cache_safe_key("global_options", [PRONTOO_SCHEMA_REV]),
                server_json_cache_ttl("clinic"),
                $loader,
                ["table:pi_clinics"],
            );
        }
        return $loader();
    
    }
}
