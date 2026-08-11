<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AdminPages;

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

final class AdminPagesRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function admin_scope_guard_stats(int $hours = 24): array
    
    {
    
        $hours = max(1, min(24 * 30, $hours));
        try {
            $row = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.admin_pages.01.admin_scope_guard_stats.01', [], ['hours' => $hours]) ?: [];
        } catch (Throwable $e) {
            return [
                "total" => 0,
                "policy" => 0,
                "actionable" => 0,
                "objective" => 0,
                "review" => 0,
                "patterns" => 0,
            ];
        }
        $actionable = (int) ($row["actionable_total"] ?? 0);
        $objective = min($actionable, (int) ($row["objective_total"] ?? 0));
        return [
            "total" => (int) ($row["total"] ?? 0),
            "policy" => (int) ($row["policy_total"] ?? 0),
            "actionable" => $actionable,
            "objective" => $objective,
            "review" => max(0, $actionable - $objective),
            "patterns" => (int) ($row["actionable_patterns"] ?? 0),
        ];
    
    }

    public static function admin_scope_guard_groups(
        int $hours = 24,
        int $limit = 30,
        bool $includePolicy = false,
    ): array 
    {
    
        $hours = max(1, min(24 * 30, $hours));
        $limit = max(1, min(80, $limit));
        try {
            return \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.01.admin_scope_guard_groups.01', [], ['hours' => $hours, 'includePolicy' => $includePolicy, 'limit' => $limit])->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo scope evidence] " . $e->getMessage());
            return [];
        }
    
    }

    public static function admin_scope_evidence_html(array $row, bool $compact = false): string
    
    {
    
        $key = (string) ($row["violation_key"] ?? "");
        $definition = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_scope_guard_definition($key);
        $payload = is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::class, 'scope_violation_detail_decode'])
            ? \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_violation_detail_decode($row["details"] ?? "")
            : ["reason" => (string) ($row["details"] ?? "")];
        $clinic = mb_trim((string) ($row["clinic_name"] ?? ""));
        if ($clinic === "") {
            $clinic = "Consultório #" . (int) ($row["clinic_id"] ?? 0);
        }
        $actor = mb_trim((string) ($row["user_name"] ?? ""));
        $actor = $actor !== "" ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($actor) : "usuário não identificado";
        $context = array_values(
            array_filter([
                mb_trim((string) ($payload["method"] ?? "")),
                mb_trim((string) ($row["route"] ?? "")),
                mb_trim((string) ($payload["action"] ?? "")),
                mb_trim((string) ($payload["operation"] ?? "")),
                mb_trim((string) ($payload["table"] ?? "")),
            ]),
        );
        $fingerprint = substr((string) ($row["sql_fingerprint"] ?? ""), 0, 16);
        $shape = mb_trim((string) ($payload["sql_shape"] ?? ""));
        $recordedReason = mb_trim((string) ($payload["reason"] ?? ""));
        $html =
            '<details class="scope-evidence"><summary>' .
            ($compact ? "Como e por que foi bloqueado" : "Ver prova técnica e contexto seguro") .
            '</summary><div class="scope-evidence-grid"><div><b>Como foi detectado</b><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $definition["detection"]) .
            '</span></div><div><b>Por que exige atenção</b><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $definition["risk"]) .
            '</span></div><div><b>Resultado observado</b><span>Bloqueado antes da execução SQL; este registro não confirma acesso cruzado.</span></div><div><b>Próximo passo</b><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $definition["next"]) .
            "</span></div></div>";
        if ($recordedReason !== "") {
            $html .= '<p class="scope-recorded-reason"><b>Motivo registrado:</b> ' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($recordedReason) .
                "</p>";
        }
        $html .=
            '<div class="scope-evidence-meta"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($clinic) .
            '</span><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($actor . " · " . ((string) ($row["role_code"] ?? "cargo não informado"))) .
            '</span><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($context ? implode(" · ", $context) : "contexto legado reduzido") .
            '</span><span>Fingerprint ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fingerprint !== "" ? $fingerprint : "indisponível") .
            '</span><span>' .
            (int) ($row["occurrences"] ?? 1) .
            " ocorrência(s)</span></div>";
        if (!$compact && $shape !== "") {
            $html .=
                '<div class="scope-sql-shape"><b>Forma sanitizada do SQL</b><code>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($shape) .
                "</code></div>";
        }
        return $html . "</details>";
    
    }

    public static function admin_scope_guard_timeline_item(array $row): array
    
    {
    
        $definition = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_scope_guard_definition(
            (string) ($row["violation_key"] ?? ""),
        );
        $clinic = mb_trim((string) ($row["clinic_name"] ?? ""));
        if ($clinic === "") {
            $clinic = "Consultório #" . (int) ($row["clinic_id"] ?? 0);
        }
        $occurrences = max(1, (int) ($row["occurrences"] ?? 1));
        $routeName = mb_trim((string) ($row["route"] ?? ""));
        return [
            "icon" => (string) $definition["icon"],
            "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) ($row["last_at"] ?? "")),
            "title" =>
                (string) $definition["label"] .
                ($routeName !== "" ? " · " . $routeName : ""),
            "body" =>
                (string) $definition["cause"] .
                " A operação foi interrompida antes da execução SQL.",
            "meta" =>
                $clinic .
                " · " .
                $occurrences .
                " ocorrência(s) entre " .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) ($row["first_at"] ?? "")) .
                " e " .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) ($row["last_at"] ?? "")),
            "html" => \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_evidence_html($row),
            "class" => "scope-event scope-" . (string) $definition["tier"],
        ];
    
    }

    public static function platform_backend_selftest(array $preloaded = []): array
    
    {
    
        $checks = [];
        $ok = true;
        try {
            $dbOk = (string) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.01.platform_backend_selftest.01', [], []) === "1";
        } catch (Throwable $e) {
            $dbOk = false;
        }
        $checks["database"] = $dbOk;
        $ok = $ok && $dbOk;
        $storage = \Prontoo\Infrastructure\AdminPages\AdminPagesInfrastructureOperations01::platform_storage_status();
        $checks["storage"] = (bool) $storage["ok"];
        $checks["storage_free_bytes"] = $storage["free_bytes"];
        $ok = $ok && (bool) $storage["ok"];
        $checks["open_errors"] = array_key_exists("open_errors", $preloaded)
            ? (int) $preloaded["open_errors"]
            : (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("platform_selftest_open_errors", 45, 'read.admin_pages.01.platform_backend_selftest.01', [], []);
        $checks["login_locks"] = array_key_exists("login_locks", $preloaded)
            ? (int) $preloaded["login_locks"]
            : (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("platform_selftest_login_locks", 45, 'read.admin_pages.01.platform_backend_selftest.02', [], []);
        $checks["scope_alerts_24h"] = array_key_exists("scope_alerts_24h", $preloaded)
            ? (int) $preloaded["scope_alerts_24h"]
            : (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("platform_selftest_scope_actionable_24h_v2_" . \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_id(), 45, 'read.admin_pages.01.platform_backend_selftest.03', [], []);
        $scopeLogic = class_exists("\\Prontoo\\Core\\Database\\SqlScopeGuard")
            ? \Prontoo\Core\Database\SqlScopeGuard::logicSelfTest()
            : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["class_missing"]];
        $checks["scope_guard_logic"] = $scopeLogic;
        $ok = $ok && !empty($scopeLogic["ok"]);
        $scopeContext = is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::class, 'scope_guard_context_selftest'])
            ? \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::scope_guard_context_selftest()
            : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["function_missing"]];
        $checks["scope_guard_context"] = $scopeContext;
        $ok = $ok && !empty($scopeContext["ok"]);
        $checks["integrity_alerts"] = (int) \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cache_remember(
            "platform_selftest_integrity_alerts_" . \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_id(),
            60,
            static function (): int {
    
                $alerts = 0;
                try {
                    foreach (\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light(["scope" => "model_excluded"], [], 50) as $row) {
                        if (!\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::verify_audit_row($row)) {
                            $alerts++;
                        }
                    }
                } catch (Throwable $e) {
                    return 0;
                }
                return $alerts;
            },
        );
        $checks["audit_chain"] = is_callable([\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::class, 'audit_chain_integrity_status'])
            ? \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_chain_integrity_status(240)
            : ["ok" => false, "sequence_ok" => false, "head_ok" => false, "checked" => 0];
        $ok = $ok && !empty($checks["audit_chain"]["ok"]);
        $versionContract = function_exists("prontoo_version_contract_status")
            ? prontoo_version_contract_status()
            : ["ok" => true, "version" => PRONTOO_VERSION, "issues" => []];
        $checks["version"] = (string) ($versionContract["version"] ?? PRONTOO_VERSION);
        $checks["version_contract"] = $versionContract;
        $ok = $ok && !empty($versionContract["ok"]);
        $checks["ok"] = $ok;
        return $checks;
    
    }

    public static function platform_login_loaded_audit(
        array $checks,
        bool $autoLogin = false,
    ): void 
    {
    
        try {
            $bucket = \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("login_loaded_audit");
            if (!\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit($bucket, 1, 300)) {
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("login_carregado", "login", null, [
                    "autoteste_ok" => !empty($checks["ok"]),
                    "auto_login" => $autoLogin ? 1 : 0,
                    "audit_body" =>
                        "Página de login carregada com a inicialização concluída.",
                ]);
            }
            $actions = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::platform_autotest_actions($checks);
            if (
                $actions &&
                !\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("login_autotest_alert"),
                    1,
                    300,
                )
            ) {
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("autoteste_aviso", "plataforma", null, [
                    "acoes_recomendadas" => $actions,
                    "audit_body" =>
                        "A inicialização encontrou sinais que devem aparecer como ações recomendadas para o Desenvolvedor.",
                ]);
            }
        } catch (Throwable $e) {
            error_log("[Prontoo login autotest audit] " . $e->getMessage());
        }
    
    }

    public static function stat_link_card(
        string $label,
        mixed $value,
        string $iconName,
        string $note = "",
        string $route = "",
    ): string 
    {
    
        $inner =
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
            "<div><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($value) .
            "</b><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span>" .
            ($note ? "<small>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($note) . "</small>" : "") .
            "</div>";
        return $route !== ""
            ? '<a class="stat-card stat-link" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route) .
                    '">' .
                    $inner .
                    "</a>"
            : '<article class="stat-card">' . $inner . "</article>";
    
    }

    public static function admin_quick_links(): string
    
    {
    
        $links = [
            [
                "admin_painel",
                "Desenvolvedor",
                "Observação e ação técnica sobre a plataforma.",
                "space_dashboard",
            ],
            [
                "admin_clinics",
                "Consultórios",
                "Clientes, assinatura, status e operação.",
                "home_health",
            ],
            [
                "admin_health",
                "Incidentes",
                "Erros abertos, integridade, segurança e operação.",
                "crisis_alert",
            ],
            [
                "admin_performance",
                "Performance",
                "Tempo médio por rota nas últimas 24 horas.",
                "speed",
            ],
            [
                "admin_alerts",
                "Avisos",
                "Mensagens restritas ao Desenvolvedor Prontoo.",
                "campaign",
            ],
            [
                "admin_maintenance",
                "Manutenção",
                "Manutenção programada e parâmetros globais da plataforma.",
                "construction",
            ],
        ];
        $h = '<div class="admin-grid admin-grid-context">';
        foreach ($links as $l) {
            $h .=
                '<a class="admin-tile" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($l[0]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($l[3]) .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($l[1]) .
                "</b><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($l[2]) .
                "</span></a>";
        }
        return $h . "</div>";
    
    }

    public static function admin_global_compact_pill(
        string $label,
        string $value,
        string $iconName,
        string $note = "",
        string $route = "",
    ): string 
    {
    
        $inner =
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) .
            "</b><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span>" .
            ($note !== "" ? "<small>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($note) . "</small>" : "");
        return $route !== ""
            ? '<a class="global-compact-pill" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route) .
                    '">' .
                    $inner .
                    "</a>"
            : '<span class="global-compact-pill">' . $inner . "</span>";
    
    }
}
