<?php
declare(strict_types=1);

namespace Prontoo\Presentation\UsersPermissions;

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

final class UsersPermissionsPresentationOperations01
{
    private function __construct()
    {
    }

    public static function role_checkbox_group(
        string $name,
        array $options,
        array $selected,
    ): string 
    {
    
        $field = str_ends_with($name, "[]") ? $name : $name . "[]";
        $h =
            '<div class="role-check-grid" role="group" aria-label="Cargos do colaborador">';
        foreach ($options as $code => $label) {
            $checked = in_array((string) $code, $selected, true) ? " checked" : "";
            $h .=
                '<label class="role-check"><input type="checkbox" name="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($field) .
                '" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $code) .
                '"' .
                $checked .
                "><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                "</span></label>";
        }
        return $h .
            "</div><small>Escolha de 1 a 4 cargos. O colaborador poderá entrar em apenas um Ambiente por vez.</small>";
    
    }
}
