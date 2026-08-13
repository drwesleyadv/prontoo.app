<?php
declare(strict_types=1);

namespace Prontoo\Presentation\UiComponents;

final class PageHeadControlPresentationOperations01
{
    private const ROLES = ['nav', 'primary', 'secondary', 'danger'];

    private function __construct()
    {
    }

    public static function classes(string $role, string $extra = '', bool $active = false): string
    {
        $role = in_array($role, self::ROLES, true) ? $role : 'secondary';
        $tokens = ['pagehead-control', 'pagehead-control--' . $role];
        foreach (preg_split('/\s+/', mb_trim($extra)) ?: [] as $token) {
            if ($token !== '' && !in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }
        if ($active && !in_array('is-active', $tokens, true)) {
            $tokens[] = 'is-active';
        }
        return implode(' ', $tokens);
    }

    public static function link(
        string $label,
        string $iconName,
        string $href,
        string $role = 'nav',
        bool $active = false,
        string $extraClass = '',
    ): string {
        $class = self::classes($role, $extraClass, $active);
        return '<a class="' . UiComponentsPresentationOperations01::e($class) .
            '" href="' . UiComponentsPresentationOperations01::e($href) .
            '" title="' . UiComponentsPresentationOperations01::e($label) . '"' .
            ($active ? ' aria-current="page"' : '') . '>' .
            UiComponentsPresentationOperations01::icon($iconName) .
            '<span>' . UiComponentsPresentationOperations01::e($label) . '</span></a>';
    }

    public static function pageHead(
        string $title,
        string $iconName,
        string $operations = '',
        string $actions = '',
    ): string {
        $hasOperations = mb_trim($operations) !== '';
        $hasActions = mb_trim($actions) !== '';
        return '<section class="pagehead' .
            ($hasOperations ? ' has-operations' : '') .
            ($hasActions ? ' has-actions' : '') .
            '" aria-label="Operações da tela"><div class="pagehead-copy"><h1><span class="pagehead-icon">' .
            UiComponentsPresentationOperations01::icon($iconName) .
            '</span><span>' . UiComponentsPresentationOperations01::e($title) .
            '</span></h1></div>' . $operations .
            ($hasActions
                ? '<div class="pagehead-controls pagehead-controls--actions" aria-label="Operações rápidas">' . $actions . '</div>'
                : '') .
            '</section>';
    }
}
