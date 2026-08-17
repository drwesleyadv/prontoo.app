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

    public static function platform_backend_selftest(array $preloaded = []): array
    {
        $snapshot = self::platform_health_snapshot($preloaded);
        return (array) ($snapshot['checks'] ?? []);
    }

    public static function platform_health_snapshot(array $preloaded = []): array
    {
        return \Prontoo\Infrastructure\AdminPages\AdminPagesInfrastructureOperations01::platform_health_exchange(
            static function (array $context) use ($preloaded): array {
                $raw = [
                    'observed_at' => gmdate('c'),
                    'storage' => (array) ($context['storage'] ?? []),
                    'performance' => (array) ($context['performance'] ?? []),
                    'maestro' => (array) ($context['maestro'] ?? []),
                    'database' => null,
                    'open_errors' => self::platform_health_operational_count(
                        $preloaded,
                        'open_errors',
                        'platform_selftest_open_errors',
                        'read.admin_pages.01.platform_backend_selftest.01',
                    ),
                    'login_locks' => self::platform_health_operational_count(
                        $preloaded,
                        'login_locks',
                        'platform_selftest_login_locks',
                        'read.admin_pages.01.platform_backend_selftest.02',
                    ),
                    'scope_alerts_24h' => self::platform_health_operational_count(
                        $preloaded,
                        'scope_alerts_24h',
                        'platform_selftest_scope_actionable_24h_v2_' . \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(),
                        'read.admin_pages.01.platform_backend_selftest.03',
                    ),
                ];
                try {
                    $raw['database'] = (string) \Prontoo\Runtime\Operational\OperationalComposition::administration()
                        ->scalar('operational.admin_pages.01.platform_backend_selftest.01', [], []) === '1';
                } catch (Throwable $error) {
                    $raw['database'] = null;
                }
                try {
                    $raw['scope_logic'] = class_exists(\Prontoo\Core\Database\SqlScopeGuard::class)
                        ? \Prontoo\Core\Database\SqlScopeGuard::logicSelfTest()
                        : ['ok' => false, 'unknown' => true, 'failed' => ['class_missing']];
                } catch (Throwable $error) {
                    $raw['scope_logic'] = ['ok' => false, 'unknown' => true, 'failed' => ['unavailable']];
                }
                try {
                    $raw['scope_context'] = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::scope_guard_context_selftest();
                } catch (Throwable $error) {
                    $raw['scope_context'] = ['ok' => false, 'unknown' => true, 'failed' => ['unavailable']];
                }
                try {
                    $raw['audit_chain'] = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_chain_integrity_status(240);
                } catch (Throwable $error) {
                    $raw['audit_chain'] = ['ok' => false, 'unknown' => true, 'checked' => 0];
                }
                $raw['integrity_alerts'] = null;
                if ((int) (($raw['audit_chain']['checked'] ?? 0)) > 0) {
                    try {
                        $raw['integrity_alerts'] = 0;
                        foreach (\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light(['scope' => 'model_excluded'], [], 50) as $row) {
                            if (!\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::verify_audit_row($row)) {
                                $raw['integrity_alerts']++;
                            }
                        }
                    } catch (Throwable $error) {
                        $raw['integrity_alerts'] = null;
                    }
                }
                try {
                    $raw['mutation_invariant'] = \Prontoo\Core\Invariant\InvariantKernel::logicSelfTest();
                } catch (Throwable $error) {
                    $raw['mutation_invariant'] = ['ok' => false, 'unknown' => true];
                }
                if (function_exists('prontoo_version_contract_status')) {
                    try {
                        $raw['version_contract'] = prontoo_version_contract_status();
                    } catch (Throwable $error) {
                        $raw['version_contract'] = ['ok' => false, 'unknown' => true, 'issues' => ['unavailable']];
                    }
                } else {
                    $raw['version_contract'] = ['ok' => false, 'unknown' => true, 'issues' => ['function_missing']];
                }
                $raw['version'] = (string) ($raw['version_contract']['version'] ?? PRONTOO_VERSION);
                return \Prontoo\Domain\Health\HealthEvidenceBuilder::build(
                    $raw,
                    (array) ($context['previous'] ?? []),
                );
            },
        );
    }

    private static function platform_health_operational_count(
        array $preloaded,
        string $key,
        string $cacheKey,
        string $queryId,
    ): ?int {
        if (array_key_exists($key, $preloaded)) {
            return max(0, (int) $preloaded[$key]);
        }
        try {
            return max(0, (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val(
                $cacheKey,
                45,
                $queryId,
                [],
                [],
            ));
        } catch (Throwable $error) {
            return null;
        }
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
