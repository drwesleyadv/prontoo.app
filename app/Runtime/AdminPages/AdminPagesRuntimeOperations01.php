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
