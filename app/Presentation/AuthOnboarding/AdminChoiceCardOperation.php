<?php
declare(strict_types=1);

namespace Prontoo\Presentation\AuthOnboarding;

final class AdminChoiceCardOperation
{
    private function __construct()
    {
    }

    public static function render(): string
    {
        return '<button class="clinic-choice credential-choice admin-choice" type="submit" name="act" value="choose_admin"><span class="credential-icon app-brandmark-inline" data-app-brandmark><img class="auth-brandmark-favicon app-brandmark-img" src="/public/assets/app-icon-' .
            \e(PRONTOO_ASSET_REV) .
            '.png" alt="" aria-hidden="true"></span><span class="credential-main"><span class="credential-role">Desenvolvedor</span><span class="credential-context"><span>Painel do Desenvolvedor</span><small>Gerenciamento técnico da plataforma</small></span></span><span class="credential-enter">' .
            \icon('login') .
            '</span></button>';
    }
}
