<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Auth {
    final class OnboardingTipView
    {
        public static function render(
            array $tip,
            string $key,
            string $return,
            string $csrfField,
            callable $escape,
            callable $icon,
        ): string {
            return '<section class="onboarding-tip-card" role="note"><div class="onboarding-tip-main"><div class="onboarding-tip-head"><span class="onboarding-tip-icon">' .
                $icon((string) ($tip["icon"] ?? "")) .
                "</span><strong>" .
                $escape((string) ($tip["title"] ?? "")) .
                '</strong></div><p class="onboarding-tip-body">' .
                $escape((string) ($tip["body"] ?? "")) .
                '</p></div><form method="post" action="' .
                $escape($return) .
                '" class="onboarding-tip-action">' .
                $csrfField .
                '<input type="hidden" name="act" value="onboarding_tip_dismiss"><input type="hidden" name="tip_key" value="' .
                $escape($key) .
                '"><input type="hidden" name="return_to" value="' .
                $escape($return) .
                '"><button type="submit" class="ghost small onboarding-tip-button">Entendi</button></form></section>';
        }
    }
}

