<?php
declare(strict_types=1);

namespace Prontoo\Runtime\UiComponents;

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

final class UiComponentsRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function form_submit_icon(string $label): string
    
    {
    
        $l = mb_strtolower($label);
        if (str_contains($l, "salvar")) {
            return "save";
        }
        if (str_contains($l, "excluir") || str_contains($l, "remover")) {
            return "delete";
        }
        if (str_contains($l, "cancelar") || str_contains($l, "descartar")) {
            return "close";
        }
        if (str_contains($l, "agendar") || str_contains($l, "consulta")) {
            return "event_available";
        }
        if (str_contains($l, "bloquear")) {
            return "event_busy";
        }
        if (
            str_contains($l, "publicar") ||
            str_contains($l, "comunicado") ||
            str_contains($l, "aviso")
        ) {
            return "campaign";
        }
        if (str_contains($l, "emitir") || str_contains($l, "documento")) {
            return "description";
        }
        if (str_contains($l, "preencher")) {
            return "sync";
        }
        if (
            str_contains($l, "depositar") ||
            str_contains($l, "pagamento") ||
            str_contains($l, "entrada")
        ) {
            return "payments";
        }
        if (str_contains($l, "registrar")) {
            return "sticky_note_2";
        }
        if (
            str_contains($l, "cadastrar") ||
            str_contains($l, "criar") ||
            str_contains($l, "adicionar")
        ) {
            return "add_circle";
        }
        if (str_contains($l, "confirmar")) {
            return "check_circle";
        }
        if (is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::class, 'prontoo_icon_for_route_label'])) {
            $byLabel = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for_route_label("", $label, [], "");
            if ($byLabel !== "") {
                return $byLabel;
            }
        }
        return "task_alt";
    
    }

    public static function form_actions(string $submitLabel, string $class = "primary"): string
    
    {
    
        return '<div class="form-actions">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::cancel_button() .
            '<button type="submit" class="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($class) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_submit_icon($submitLabel)) .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($submitLabel) .
            "</span></button></div>";
    
    }

    public static function action_panel(
        string $label,
        string $formHtml,
        string $variant = "primary",
        string $hint = "",
    ): string 
    {
    
        $controlRole = match ($variant) {
            'primary' => 'primary',
            'danger', 'danger-soft' => 'danger',
            default => 'secondary',
        };
        $controlExtra = match ($variant) {
            'danger-soft' => 'pagehead-control--danger-soft',
            'primary', 'ghost', 'secondary', 'danger' => '',
            default => $variant,
        };
        return '<details class="action-panel"><summary class="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                \Prontoo\Presentation\UiComponents\PageHeadControlPresentationOperations01::classes($controlRole, $controlExtra),
            ) .
            '">' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label($label) .
            ($hint !== "" ? "<small>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($hint) . "</small>" : "") .
            "</summary>" .
            $formHtml .
            "</details>";
    
    }
}
