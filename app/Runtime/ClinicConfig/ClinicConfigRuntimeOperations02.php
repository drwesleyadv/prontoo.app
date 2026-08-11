<?php
declare(strict_types=1);

namespace Prontoo\Runtime\ClinicConfig;

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
        $role = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($roleCode, (int) $r["clinic_id"]);
        $ico = is_callable([\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::class, 'role_icon'])
            ? \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_icon($roleCode, (int) $r["clinic_id"])
            : "badge";
        return '<button class="clinic-choice credential-choice" type="submit" name="clinic_role_id" value="' .
            (int) $r["id"] .
            '"><span class="credential-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
            '</span><span class="credential-main"><span class="credential-role">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($role) .
            '</span><span class="credential-context"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($clinic !== "" ? $clinic : "Consultório") .
            "</span><small>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($place !== "" ? $place : "Cidade não informada") .
            '</small></span></span><span class="credential-enter">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("login") .
            "</span></button>";
    
    }

    public static function clinic_options_global(): array
    
    {
    
        $loader = function (): array {
    
            $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.clinic_config.02.clinic_options_global.01', [], [])->fetchAll();
            $options = ["" => "Sem consultório específico"];
            foreach ($rows as $row) {
                $options[(int) $row["id"]] = (string) $row["display_name"];
            }
            return $options;
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "clinic",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("global_options", [PRONTOO_SCHEMA_REV]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("clinic"),
                $loader,
                ["table:pi_clinics"],
            );
        }
        return $loader();
    
    }
}
