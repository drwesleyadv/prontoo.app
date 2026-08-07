<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Legacy\Maestro;

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

final class MaestroPresentationOperations01
{
    private function __construct()
    {
    }

    public static function maestro_actor_id(): ?int
    
    {
    
        return isset($_SESSION["uid"]) ? (int) $_SESSION["uid"] : null;
    
    }

    public static function maestro_trigger_option_html(
        array $catalog,
        string $selected = "appointment_before_start",
    ): string 
    {
    
        $h = '<select name="trigger_event" required data-maestro-trigger>';
        foreach ($catalog as $key => $it) {
            $sel = $key === $selected ? " selected" : "";
            $h .=
                '<option value="' .
                e($key) .
                '"' .
                $sel .
                ' data-module="' .
                e((string) ($it["module"] ?? "")) .
                '" data-amount="' .
                (int) ($it["amount_default"] ?? 1) .
                '" data-unit="' .
                e((string) ($it["unit"] ?? "days")) .
                '" data-amount-label="' .
                e((string) ($it["amount_label"] ?? "Prazo")) .
                '" data-name="' .
                e((string) ($it["default_name"] ?? $it["label"])) .
                '" data-title="' .
                e((string) ($it["default_title"] ?? "")) .
                '" data-description="' .
                e((string) ($it["default_description"] ?? "")) .
                '" data-role="' .
                e((string) ($it["default_target_role"] ?? "recepcionista")) .
                '" data-priority="' .
                (int) ($it["default_priority"] ?? 50) .
                '" data-action="' .
                e((string) ($it["default_action"] ?? "create_task")) .
                '">' .
                e((string) $it["label"]) .
                "</option>";
        }
        return $h . "</select>";
    
    }
}
