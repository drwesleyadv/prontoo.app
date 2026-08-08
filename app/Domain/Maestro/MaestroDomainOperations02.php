<?php
declare(strict_types=1);

namespace Prontoo\Domain\Maestro;

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

final class MaestroDomainOperations02
{
    private function __construct()
    {
    }

    public static function maestro_module_label(string $module): string
    
    {
    
        return [
            "appointments" => "Agenda",
            "leads" => "Interessados",
            "patients" => "Pacientes",
            "tasks" => "Tarefas",
            "documents" => "Documentos",
            "financial" => "Financeiro",
            "notices" => "Comunicados",
            "security" => "Segurança",
        ][$module] ?? $module;
    
    }

    public static function maestro_json(array $data): string
    
    {
    
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false ? $json : "{}";
    
    }

    public static function maestro_decode_json(mixed $raw): array
    
    {
    
        if (is_array($raw)) {
            return $raw;
        }
        $txt = mb_trim((string) ($raw ?? ""));
        if ($txt === "") {
            return [];
        }
        $v = json_decode($txt, true);
        return is_array($v) ? $v : [];
    
    }

    public static function maestro_text(string $s, int $max = 180): string
    
    {
    
        $s = trim(strip_tags($s));
        $s = preg_replace("/\s+/u", " ", $s) ?: "";
        return mb_substr($s, 0, $max);
    
    }

    public static function maestro_template(string $s, int $max = 900): string
    
    {
    
        $s = trim(strip_tags($s));
        return mb_substr($s, 0, $max);
    
    }

    public static function maestro_days(mixed $v, int $default = 1): int
    
    {
    
        $n = (int) $v;
        return max(0, min(365, $n > 0 || $v === "0" ? $n : $default));
    
    }

    public static function maestro_amount(mixed $v, int $default = 1, string $unit = "days"): int
    
    {
    
        $n = (int) $v;
        if (!($n > 0 || $v === "0")) {
            $n = $default;
        }
        $max = match ($unit) {
            "minutes" => 1440,
            "hours" => 720,
            default => 365,
        };
        return max(0, min($max, $n));
    
    }

    public static function maestro_priority(mixed $v): int
    
    {
    
        return max(1, min(100, (int) $v));
    
    }

    public static function maestro_apply_placeholders(string $template, array $vars): string
    
    {
    
        return preg_replace_callback(
            "/\{\{\s*([a-z0-9_]+)\s*\}\}/iu",
            function ($m) use ($vars) {
    
                $k = mb_strtolower((string) $m[1]);
                return (string) ($vars[$k] ?? "");
            },
            $template,
        ) ?? $template;
    
    }

    public static function maestro_module_options(array $catalog): array
    
    {
    
        $out = [];
        foreach ($catalog as $it) {
            $module = (string) ($it["module"] ?? "");
            if ($module !== "" && !isset($out[$module])) {
                $out[$module] = maestro_module_label($module);
            }
        }
        return $out;
    
    }

    public static function maestro_duration_label(int $ms): string
    
    {
    
        if ($ms <= 0) {
            return "0 ms";
        }
        if ($ms < 1000) {
            return $ms . " ms";
        }
        $seconds = $ms / 1000;
        if ($seconds < 60) {
            return number_format($seconds, 1, ",", ".") . " s";
        }
        $minutes = floor($seconds / 60);
        $rest = (int) round($seconds - $minutes * 60, 0, \RoundingMode::HalfAwayFromZero);
        return (int) $minutes . " min " . $rest . " s";
    
    }

    public static function maestro_unit_label(
        string $unit,
        int $amount,
        bool $short = false,
    ): string 
    {
    
        if ($short) {
            return $unit === "minutes" ? "min" : ($unit === "hours" ? "h" : "d");
        }
        if ($unit === "minutes") {
            return $amount === 1 ? "minuto" : "minutos";
        }
        if ($unit === "hours") {
            return $amount === 1 ? "hora" : "horas";
        }
        return $amount === 1 ? "dia" : "dias";
    
    }

    public static function maestro_module_icon(string $module): string
    
    {
    
        return [
            "appointments" => "calendar_month",
            "leads" => "person_search",
            "patients" => "patient_list",
            "tasks" => "task_alt",
            "documents" => "description",
            "financial" => "account_balance_wallet",
            "notices" => "campaign",
            "security" => "shield_lock",
        ][$module] ?? "auto_awesome";
    
    }

    public static function maestro_action_icon(string $action): string
    
    {
    
        return $action === "create_notice" ? "campaign" : "assignment_turned_in";
    
    }

    public static function maestro_match_base(array $vars, array $extra = []): array
    
    {
    
        return $extra + ["variables" => $vars];
    
    }

    public static function maestro_routine_key(array $rule): string
    
    {
    
        return "rule:" .
            (int) $rule["id"] .
            ":" .
            (string) $rule["trigger_event"] .
            ":" .
            (string) $rule["action_type"];
    
    }

    public static function maestro_ewma_observation(
        ?float $previous,
        float $observation,
        bool $skipped = false,
        float $alpha = 0.25,
    ): ?float 
    {
    
        if ($skipped) {
            return $previous;
        }
        $alpha = max(0.0, min(1.0, $alpha));
        return $previous === null
            ? $observation
            : ($previous * (1 - $alpha)) + ($observation * $alpha);
    
    }

    public static function maestro_supervised_remaining_ms(float $deadline): int
    
    {
        return max(0, (int) floor(($deadline - microtime(true)) * 1000));
    
    }

    public static function maestro_supervised_execution_key(string $entity, string $id): string
    
    {
        return $entity . "\x1f" . $id;
    
    }

    public static function maestro_supervised_execution_retry_state(?array $row): array
    
    {
        if (!$row) {
            return ["attempt" => 0, "next_at" => 0, "terminal" => false];
        }
        $message = (string) ($row["message"] ?? "");
        $attempt = preg_match('/(?:^|;)attempt:(\d+)/', $message, $match) === 1
            ? max(0, (int) ($match[1] ?? 0))
            : ((string) ($row["status"] ?? "") === "error" ? 1 : 0);
        $nextAt = preg_match('/(?:^|;)next:(\d+)/', $message, $match) === 1
            ? max(0, (int) ($match[1] ?? 0))
            : 0;
        return [
            "attempt" => $attempt,
            "next_at" => $nextAt,
            "terminal" => str_starts_with($message, "terminal;") || $attempt >= 5,
        ];
    
    }

    public static function maestro_supervised_retry_delay_seconds(int $attempt): int
    
    {
        $schedule = [600, 1800, 3600, 10800, 21600];
        return $schedule[max(0, min(count($schedule) - 1, $attempt - 1))];
    
    }
}
