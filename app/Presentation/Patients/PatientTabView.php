<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Patients {
    final class PatientTabView
    {
        public static function iconPicker(
            array $options,
            string $current,
            callable $escape,
            callable $icon,
        ): string {
            $html = '<div class="visual-option-grid patient-health-icon-grid patient-tab-icon-symbol-grid" role="radiogroup" aria-label="Ícone da aba">';
            foreach ($options as $key => $label) {
                $key = (string) $key;
                $label = (string) $label;
                $checked = $key === $current ? " checked" : "";
                $html .=
                    '<label class="visual-option patient-health-icon-choice patient-health-icon-only" title="' .
                    $escape($label) .
                    '" aria-label="' .
                    $escape($label) .
                    '"><input type="radio" name="tab_icon" value="' .
                    $escape($key) .
                    '"' .
                    $checked .
                    ' aria-label="' .
                    $escape($label) .
                    '"><span class="patient-tab-icon-symbol">' .
                    $icon($key) .
                    '</span><span class="sr-only">' .
                    $escape($label) .
                    "</span></label>";
            }
            return $html . "</div>";
        }
    }
}

namespace {
    function prontoo_patient_tab_icon_picker(array $options, string $current): string
    {
        return \Prontoo\Presentation\Patients\PatientTabView::iconPicker(
            $options,
            $current,
            static fn(string $value): string => e($value),
            static fn(string $name): string => icon($name),
        );
    }
}
