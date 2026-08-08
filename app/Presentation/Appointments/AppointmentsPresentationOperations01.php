<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Appointments;

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

final class AppointmentsPresentationOperations01
{
    private function __construct()
    {
    }

    public static function appointment_journey_steps_html(array $vm): string
    
    {
    
        $html = '<span class="journey-ux-rail" aria-label="Jornada do paciente">';
        foreach ($vm["steps"] ?? [] as $step) {
            $state = (string) ($step["state"] ?? "upcoming");
            $html .=
                '<span class="journey-ux-step is-' .
                e($state) .
                '" title="' .
                e((string) $step["label"]) .
                '"><span class="journey-ux-dot">' .
                icon((string) $step["icon"]) .
                '</span><span class="journey-ux-step-label">' .
                e((string) $step["label"]) .
                "</span></span>";
        }
        return $html . "</span>";
    
    }

    public static function appointment_journey_fact_html(
        string $iconName,
        string $label,
        string $value,
    ): string 
    {
    
        $value = trim($value);
        if ($value === "") {
            $value = "—";
        }
        return '<span class="journey-ux-fact">' .
            icon($iconName) .
            "<b>" .
            e($label) .
            "</b><em>" .
            e($value) .
            "</em></span>";
    
    }

    public static function procedure_option_label(array $p): string
    
    {
    
        $parts = [mb_trim((string) $p["title"])];
        $dur = (int) ($p["duration_minutes"] ?? 0);
        if ($dur > 0) {
            $parts[] = $dur . " min";
        }
        $price = (int) ($p["price_cents"] ?? 0);
        if ($price > 0) {
            $parts[] = money_br($price);
        }
        $pay = mb_trim((string) ($p["payment_methods"] ?? ""));
        if ($pay !== "") {
            $parts[] = $pay;
        }
        return implode(" · ", array_filter($parts));
    
    }

    public static function agenda_crown_label_for_view(
        string $view,
        string $day,
        string $doctorName,
    ): string 
    {
    
        $name = first_name($doctorName);
        return match ($view) {
            "semanal" => agenda_week_range_label($day) . " de " . $name,
            "mensal" => agenda_month_name_br($day) . " de " . $name,
            default => agenda_day_short_label($day) . " de " . $name,
        };
    
    }
}
