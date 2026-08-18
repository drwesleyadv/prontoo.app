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
            "admin_routes" => "admin_performance",
            "admin_onboarding",
            "admin_operations",
            "admin_payment_proof",
            "admin_users",
            "admin_people",
            "admin_alerts",
            "admin_global_notices",
            "admin_audit"
                => "admin_clinics",
            "admin_maintenance",
            "admin_settings"
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

    public static function admin_metric_variation_badge(?float $variation): string
    {
        if ($variation === null) {
            return '<span class="telemetry-kpi-trend is-neutral is-pending" title="Aguardando período anterior comparável" aria-label="Aguardando período anterior comparável">⌛</span>';
        }
        if (abs($variation) < 0.05) {
            return '<span class="telemetry-kpi-trend is-neutral" title="Sem variação em relação aos 10 dias anteriores" aria-label="Sem variação em relação aos 10 dias anteriores">0%</span>';
        }
        $positive = $variation > 0;
        $compact = number_format(abs($variation), 1, ",", ".");
        $compact = preg_replace('/,0$/', "", $compact) ?: "0";
        $direction = $positive ? "▲" : "▼";
        $description = ($positive ? "Alta de " : "Queda de ") .
            $compact .
            "% em relação aos 10 dias anteriores";
        return '<span class="telemetry-kpi-trend ' .
            ($positive ? "is-positive" : "is-negative") .
            '" title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($description) .
            '" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($description) .
            '"><span class="telemetry-kpi-trend-icon" aria-hidden="true">' .
            $direction .
            '</span><span class="telemetry-kpi-trend-rate">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($compact . "%") .
            "</span></span>";
    }

    public static function admin_metric_comparison_card_html(
        string $label,
        int $value,
        string $icon,
        ?float $variation,
        string $note,
    ): string {
        return '<article class="stat-card telemetry-kpi-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($icon) .
            '<div><div class="telemetry-kpi-value"><b>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($value) .
            '</b>' .
            self::admin_metric_variation_badge($variation) .
            '</div><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            '</span><small>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($note) .
            '</small></div></article>';
    }

    public static function admin_route_display_label(string $route): string
    {
        $route = strtolower(mb_trim($route));
        if (str_starts_with($route, "landing_")) {
            return "Recurso da Landing Page";
        }
        return match ($route) {
            "landing" => "Landing Page",
            "install" => "Instalação",
            "home" => "Início",
            "status" => "Status público",
            "login" => "Acesso ao sistema",
            "login_telemetry_wave" => "Telemetria da tela de acesso",
            "login_autotest" => "Verificação da tela de acesso",
            "mfa" => "Verificação em duas etapas",
            "mobile_web_access" => "Acesso pelo celular",
            "goal_status" => "Andamento da meta",
            "signup" => "Cadastro de consultório",
            "logout" => "Saída do sistema",
            "switch" => "Troca de ambiente",
            "profile" => "Perfil",
            "global_reauth" => "Reautenticação do Desenvolvedor",
            "onboarding" => "Configuração inicial",
            "painel" => "Painel do consultório",
            "operations" => "Fluxo operacional",
            "maestro" => "Rotinas automatizadas",
            "leads" => "Interessados",
            "appointments" => "Agenda",
            "patients" => "Lista de pacientes",
            "patient" => "Prontuário do paciente",
            "patient_lookup" => "Busca de paciente",
            "lead_lookup" => "Busca de interessado",
            "lead_patient_lookup" => "Busca de paciente para interessado",
            "patient_suggest" => "Sugestão de paciente",
            "person_lookup" => "Busca de pessoa",
            "counterparty_lookup" => "Busca de contraparte financeira",
            "counterparty_suggest" => "Sugestão de contraparte financeira",
            "procedures" => "Procedimentos",
            "financial" => "Financeiro",
            "creditors" => "Credores",
            "tasks" => "Tarefas",
            "documents" => "Documentos",
            "document_view" => "Visualização de documento",
            "document_print" => "Impressão de documento",
            "document_pdf" => "Emissão de PDF",
            "document_pdf_file" => "Arquivo PDF",
            "notices" => "Avisos do consultório",
            "users" => "Colaboradores",
            "user" => "Cadastro de colaborador",
            "permissions" => "Permissões",
            "audit" => "Atividades do consultório",
            "settings" => "Configuração do consultório",
            "admin_administration" => "Administração",
            "admin_clinics" => "Consultórios",
            "admin_onboarding" => "Onboarding dos consultórios",
            "admin_users", "admin_people" => "Usuários da plataforma",
            "admin_operations" => "Operação e financeiro",
            "admin_global_notices" => "Avisos aos consultórios",
            "admin_alerts" => "Mensagens do Desenvolvedor",
            "admin_maintenance" => "Manutenção",
            "admin_performance" => "Métricas",
            "admin_routes" => "Rotas da aplicação",
            "admin_settings" => "Configuração",
            "admin_payment_proof" => "Comprovante de pagamento",
            "admin_audit" => "Auditoria",
            default => "Rota não identificada",
        };
    }

    public static function admin_global_operation_specs(string $current, string $parent, string $alertView): array
    {
        return match ($parent) {
            "admin_clinics" => [
                ["admin_clinics", "Consultórios", "home_health"],
                ["admin_people", "Usuários", "groups"],
                ["admin_alerts", "Mensagens", "mail"],
                ["admin_global_notices", "Avisos", "notifications_active"],
                ["admin_audit", "Auditoria", "history"],
            ],
            "admin_performance" => [
                ["admin_performance", "Métricas", "monitoring"],
                ["admin_routes", "Rotas", "route"],
            ],
            "admin_administration" => [
                ["admin_maintenance", "Manutenção", "construction"],
                ["admin_settings", "Configuração", "settings"],
            ],
            default => [],
        };
    }


}
