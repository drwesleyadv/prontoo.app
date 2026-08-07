<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Legacy\Dashboards;

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

final class DashboardsPresentationOperations01
{
    private function __construct()
    {
    }

    public static function manager_count_business_days(string $from, string $to): int
    
    {
    
        $start = strtotime($from . " 00:00:00");
        $end = strtotime($to . " 00:00:00");
        if (!$start || !$end || $end < $start) {
            return 0;
        }
        $n = 0;
        for ($t = $start; $t <= $end; $t += 86400) {
            $w = (int) date("N", $t);
            if ($w >= 1 && $w <= 5) {
                $n++;
            }
        }
        return $n;
    
    }

    public static function manager_percent_label(float $v): string
    
    {
    
        return number_format($v, 1, ",", ".") . "%";
    
    }

    public static function manager_dashboard_card(
        string $label,
        string $value,
        string $iconName,
        string $note = "",
        string $class = "",
    ): string 
    {
    
        return '<article class="stat-card manager-stat ' .
            e($class) .
            '">' .
            icon($iconName) .
            "<div><b>" .
            e($value) .
            "</b><span>" .
            e($label) .
            "</span>" .
            ($note !== "" ? "<small>" . e($note) . "</small>" : "") .
            "</div></article>";
    
    }
}
