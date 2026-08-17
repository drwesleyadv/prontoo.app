<?php
declare(strict_types=1);

namespace Prontoo\Presentation\AdminPages;

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

final class AdminPagesPresentationOperations01
{
    private function __construct()
    {
    }

    public static function platform_autotest_actions(array $checks): array
    
    {
    
        $actions = [];
        if (empty($checks["database"])) {
            $actions[] = "Banco de dados indisponível no autoteste do login.";
        }
        if (empty($checks["storage"])) {
            $actions[] = "Diretório persistente /ssd sem permissão de escrita.";
        }
        if ((int) ($checks["open_errors"] ?? 0) > 0) {
            $actions[] =
                (int) $checks["open_errors"] .
                " incidente(s) técnico(s) aberto(s).";
        }
        if ((int) ($checks["login_locks"] ?? 0) > 0) {
            $actions[] =
                (int) $checks["login_locks"] . " bloqueio(s) de login ativo(s).";
        }
        if ((int) ($checks["scope_alerts_24h"] ?? 0) > 0) {
            $actions[] =
                (int) $checks["scope_alerts_24h"] .
                " operação(ões) de escopo bloqueada(s) nas últimas 24 horas; nenhum acesso cruzado é confirmado por essa contagem.";
        }
        $scopeLogic = isset($checks["scope_guard_logic"]) && is_array($checks["scope_guard_logic"])
            ? $checks["scope_guard_logic"]
            : [];
        if (!$scopeLogic || empty($scopeLogic["ok"])) {
            $actions[] = "A prova lógica interna do guardião de escopo não concluiu todos os casos de segurança.";
        }
        $scopeContext = isset($checks["scope_guard_context"]) && is_array($checks["scope_guard_context"])
            ? $checks["scope_guard_context"]
            : [];
        if (!$scopeContext || empty($scopeContext["ok"])) {
            $actions[] = "O contrato determinístico entre contexto de sistema e consultório não concluiu todos os casos de segurança.";
        }
        if ((int) ($checks["integrity_alerts"] ?? 0) > 0) {
            $actions[] =
                (int) $checks["integrity_alerts"] .
                " registro(s) recente(s) com integridade inválida.";
        }
        $versionContract = isset($checks["version_contract"]) && is_array($checks["version_contract"])
            ? $checks["version_contract"]
            : [];
        if ($versionContract && empty($versionContract["ok"])) {
            $actions[] = "Divergência no contrato de versão: " .
                implode(", ", array_map("strval", (array) ($versionContract["issues"] ?? []))) .
                ".";
        }
        return $actions;
    
    }

    public static function admin_nav_parent(string $route): string

    {

        return match ($route) {
            "admin_onboarding", "admin_operations", "admin_payment_proof" => "admin_clinics",
            "admin_users",
            "admin_people",
            "admin_alerts",
            "admin_global_notices",
            "admin_maintenance",
            "admin_settings",
            "admin_audit"
                => "admin_administration",
            default => $route,
        };
    }

    public static function admin_global_timezone_options(): array
    
    {
    
        $priority = [
            "America/Cuiaba" => "Cuiabá / Mato Grosso",
            "America/Sao_Paulo" => "Brasília / São Paulo",
            "America/Campo_Grande" => "Campo Grande",
            "America/Manaus" => "Manaus",
            "America/Porto_Velho" => "Porto Velho",
            "America/Boa_Vista" => "Boa Vista",
            "America/Rio_Branco" => "Rio Branco",
            "America/Eirunepe" => "Eirunepé",
            "America/Noronha" => "Fernando de Noronha",
        ];
        $out = [];
        foreach ($priority as $tz => $label) {
            if (in_array($tz, timezone_identifiers_list(), true)) {
                $out[$tz] = $label . " — " . $tz;
            }
        }
        foreach (timezone_identifiers_list() as $tz) {
            if (!isset($out[$tz]) && str_starts_with($tz, "America/")) {
                $out[$tz] = $tz;
            }
        }
        return $out;
    
    }

    public static function human_bytes(float $bytes): string
    
    {
    
        $u = ["B", "KB", "MB", "GB", "TB"];
        $i = 0;
        while ($bytes >= 1024 && $i < count($u) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return number_format($bytes, $i ? 1 : 0, ",", ".") . " " . $u[$i];
    
    }

    public static function bool_status(
        bool $ok,
        string $okTxt = "OK",
        string $badTxt = "Atenção",
    ): string 
    {
    
        return $ok ? $okTxt : $badTxt;
    
    }

    public static function admin_metric_duration_label(
        float $milliseconds,
        bool $compact = false,
    ): string 
    {
    
        return number_format(max(0.0, $milliseconds), 2, ",", ".") . " ms";
    
    }

    public static function admin_metric_value_label(float $value, string $mode): string
    
    {
    
        if ($mode === "ms") {
            return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_duration_label($value, false);
        }
        return number_format($value, 0, ",", ".");
    
    }

    public static function admin_metric_value_compact(float $value, string $mode): string
    
    {
        if ($mode === "ms") {
            return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_duration_label($value, true);
        }
        return number_format($value, $value === floor($value) ? 0 : 1, ",", ".");
    
    }

    public static function admin_metric_recent_average(array $series, int $minutes = 5): float
    
    {
    
        $recent = array_slice($series, -max(1, $minutes));
        $durationNs = 0;
        $weightedCount = 0;
        $values = [];
        foreach ($recent as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = (float) ($row["value"] ?? 0);
            $samples = (int) ($row["samples"] ?? 0);
            $sumNs = (int) ($row["sum_ns"] ?? 0);
            if ($samples > 0 && $sumNs >= 0) {
                $durationNs += $sumNs;
                $weightedCount += $samples;
            } elseif ($value > 0) {
                $values[] = $value;
            }
        }
        if ($weightedCount > 0) {
            return round(($durationNs / $weightedCount) / 1000000, 6, \RoundingMode::HalfAwayFromZero);
        }
        return $values ? round(array_sum($values) / count($values), 6, \RoundingMode::HalfAwayFromZero) : 0.0;
    
    }

    public static function admin_global_operation_specs(string $current, string $parent, string $alertView): array
    {
        if (!in_array($alertView, ["received", "sent"], true)) {
            $alertView = "received";
        }
        if ($current === "admin_alerts") {
            return [
                ["admin_alerts", "Recebidos", "inbox", ["view" => "received"]],
                ["admin_alerts", "Enviados", "outbox", ["view" => "sent"]],
                ["admin_alerts", "Nova mensagem", "add_comment", ["view" => $alertView, "compose" => "1"]],
            ];
        }
        if ($current === "admin_maintenance") {
            return [["admin_maintenance", "Manutenção", "construction"]];
        }
        return match ($parent) {
            "admin_clinics" => [["admin_clinics", "Consultórios", "home_health"]],
            "admin_performance" => [["admin_performance", "Métricas", "monitoring"]],
            "admin_administration" => [["admin_administration", "Administração", "tune"]],
            default => [],
        };
    }


}
