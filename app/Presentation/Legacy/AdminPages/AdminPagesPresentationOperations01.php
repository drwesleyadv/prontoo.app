<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Legacy\AdminPages;

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

    public static function admin_scope_guard_definition(string $key): array
    
    {
    
        $definitions = [
            "write_in_read_only" => [
                "tier" => "policy",
                "icon" => "lock_clock",
                "label" => "Escrita bloqueada pela assinatura",
                "cause" =>
                    "O consultório estava em Somente Leitura e a ação não integra a lista mínima permitida.",
                "risk" =>
                    "É um bloqueio de política comercial, não uma evidência de tentativa de cruzar consultórios.",
                "detection" =>
                    "A política de assinatura recusou a escrita antes da preparação do SQL.",
                "next" =>
                    "Verifique a situação da assinatura ou a lista de ações permitidas em Somente Leitura.",
            ],
            "write_without_clinic_scope" => [
                "tier" => "objective",
                "icon" => "shield_lock",
                "label" => "Escrita sem escopo explícito",
                "cause" =>
                    "A escrita mencionou uma tabela operacional sem demonstrar a coluna clinic_id exigida.",
                "risk" =>
                    "Sem o invariante de consultório, a instrução poderia alcançar linhas fora do contexto ativo.",
                "detection" =>
                    "O guardião reconheceu a tabela como isolada e não encontrou uma prova de escopo válida.",
                "next" =>
                    "Inclua clinic_id e vincule seu valor ao consultório da sessão.",
            ],
            "write_without_where" => [
                "tier" => "objective",
                "icon" => "gpp_bad",
                "label" => "UPDATE/DELETE sem WHERE",
                "cause" =>
                    "Uma instrução de alteração ou exclusão não continha cláusula WHERE de nível principal.",
                "risk" =>
                    "A ausência de filtro permitiria atingir todas as linhas da tabela operacional.",
                "detection" =>
                    "A estrutura do SQL foi analisada antes do PDO e não apresentou WHERE aplicável.",
                "next" =>
                    "Limite a instrução por identificador e clinic_id do consultório ativo.",
            ],
            "write_without_clinic_where" => [
                "tier" => "objective",
                "icon" => "gpp_bad",
                "label" => "WHERE sem clinic_id",
                "cause" =>
                    "O UPDATE/DELETE tinha WHERE, mas o filtro não continha clinic_id.",
                "risk" =>
                    "Um identificador global ou reutilizado não constitui, sozinho, uma fronteira entre consultórios.",
                "detection" =>
                    "O analisador isolou o WHERE principal e não encontrou a coluna de escopo.",
                "next" =>
                    "Adicione clinic_id=? ao predicado e use o valor da sessão ativa.",
            ],
            "write_changes_clinic_scope" => [
                "tier" => "objective",
                "icon" => "move_down",
                "label" => "Alteração de clinic_id bloqueada",
                "cause" =>
                    "O UPDATE tentou alterar a coluna que define o proprietário do registro.",
                "risk" =>
                    "Mover uma linha por UPDATE poderia transferir sua visibilidade para outro consultório.",
                "detection" =>
                    "A lista SET foi separada do WHERE e continha atribuição direta a clinic_id.",
                "next" =>
                    "Não altere clinic_id em rotinas clínicas; trate eventual migração em processo administrativo dedicado e auditado.",
            ],
            "write_mismatched_clinic_where" => [
                "tier" => "objective",
                "icon" => "domain_disabled",
                "label" => "clinic_id divergente no WHERE",
                "cause" =>
                    "O valor demonstrável do clinic_id no filtro era diferente do consultório da sessão.",
                "risk" =>
                    "Se executada, a instrução teria como alvo explícito outro escopo de consultório.",
                "detection" =>
                    "O valor literal ou parâmetro posicional foi comparado ao clinic_id da sessão antes do SQL.",
                "next" =>
                    "Rastreie a origem do parâmetro e derive o escopo somente da sessão validada.",
            ],
            "write_unproved_clinic_where" => [
                "tier" => "review",
                "icon" => "rule",
                "label" => "Prova do WHERE insuficiente",
                "cause" =>
                    "O analisador não conseguiu demonstrar que todos os ramos booleanos do WHERE exigem o consultório ativo.",
                "risk" =>
                    "Pode ser um formato SQL complexo legítimo ou um ramo com OR que escape do escopo; o registro, sozinho, não distingue os dois casos.",
                "detection" =>
                    "A prova conservadora exige clinic_id ativo em toda alternativa lógica alcançável.",
                "next" =>
                    "Simplifique o predicado ou repita a condição de clinic_id em todos os ramos do OR.",
            ],
            "insert_without_clinic_column" => [
                "tier" => "objective",
                "icon" => "playlist_remove",
                "label" => "INSERT sem coluna clinic_id",
                "cause" =>
                    "A lista de colunas da nova linha não continha clinic_id.",
                "risk" =>
                    "A linha poderia ficar sem proprietário verificável ou depender de comportamento implícito.",
                "detection" =>
                    "As colunas do INSERT/REPLACE foram analisadas antes da execução.",
                "next" =>
                    "Grave clinic_id explicitamente com o valor da sessão.",
            ],
            "insert_mismatched_clinic_value" => [
                "tier" => "objective",
                "icon" => "domain_disabled",
                "label" => "clinic_id divergente no INSERT",
                "cause" =>
                    "Ao menos uma linha do INSERT/REPLACE recebeu clinic_id diferente do consultório ativo.",
                "risk" =>
                    "A nova linha seria criada diretamente no escopo de outro consultório.",
                "detection" =>
                    "Todas as tuplas VALUES foram avaliadas, inclusive inserções em lote.",
                "next" =>
                    "Use o clinic_id da sessão em todas as linhas do lote.",
            ],
            "insert_unproved_clinic_value" => [
                "tier" => "review",
                "icon" => "rule",
                "label" => "Prova do INSERT insuficiente",
                "cause" =>
                    "O formato do INSERT/REPLACE não permitiu provar que todas as linhas usam o consultório ativo.",
                "risk" =>
                    "Pode ser incompatibilidade do formato SQL, parâmetro ausente ou atribuição não demonstrável; não confirma travessia.",
                "detection" =>
                    "A prova verifica cada tupla VALUES e a eventual atualização de clinic_id no ON DUPLICATE KEY.",
                "next" =>
                    "Use lista explícita de colunas e VALUES posicionais demonstráveis.",
            ],
            "entity_outside_clinic" => [
                "tier" => "objective",
                "icon" => "block",
                "label" => "Objeto fora do escopo ativo",
                "cause" =>
                    "O identificador solicitado não foi encontrado dentro do consultório ativo.",
                "risk" =>
                    "O identificador pode estar incorreto, removido ou pertencer a outro contexto; nenhum dado externo foi devolvido.",
                "detection" =>
                    "A busca obrigatória combinou id e clinic_id e falhou fechada com HTTP 403.",
                "next" =>
                    "Revise a origem do identificador e descarte links ou formulários desatualizados.",
            ],
            "user_outside_clinic" => [
                "tier" => "objective",
                "icon" => "person_off",
                "label" => "Colaborador fora do escopo ativo",
                "cause" =>
                    "O colaborador informado não possui vínculo válido com o consultório ativo.",
                "risk" =>
                    "Aceitar o vínculo permitiria associar uma ação clínica a uma identidade de outro contexto.",
                "detection" =>
                    "A associação usuário-consultório foi validada antes da operação e falhou fechada.",
                "next" =>
                    "Atualize a seleção de colaboradores e confirme o vínculo ativo antes de reenviar.",
            ],
        ];
        return $definitions[$key] ?? [
            "tier" => "review",
            "icon" => "policy",
            "label" => "Evento de escopo não classificado",
            "cause" =>
                "A versão atual ainda não possui uma explicação específica para esta chave de proteção.",
            "risk" =>
                "O evento foi bloqueado, mas precisa de revisão de código antes de receber uma conclusão.",
            "detection" => "O guardião registrou a chave técnica " . $key . ".",
            "next" => "Classifique a nova chave e revise o fingerprint correspondente.",
        ];
    
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
            "admin_stats", "admin_operations" => "admin_painel",
            "admin_onboarding",
            "admin_users",
            "admin_people",
            "admin_payment_proof"
                => "admin_clinics",
            "admin_errors",
            "admin_diagnostics",
            "admin_integrity",
            "admin_security",
            "admin_audit"
                => "admin_health",
            "admin_global_notices",
            "admin_deleted",
            "admin_settings"
                => "admin_maintenance",
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
            return admin_metric_duration_label($value, false);
        }
        return number_format($value, 0, ",", ".");
    
    }

    public static function admin_metric_value_compact(float $value, string $mode): string
    
    {
        if ($mode === "ms") {
            return admin_metric_duration_label($value, true);
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
}
