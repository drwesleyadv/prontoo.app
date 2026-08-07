<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Patients;

use Prontoo\Presentation\Auth\OnboardingTipView;
use Prontoo\Presentation\Patients\PatientContactView;
use Prontoo\Presentation\Patients\PatientTabView;

final class PatientViewComposition
{
    private function __construct()
    {
    }

    public static function tabIconPicker(array $options, string $current): string
    {
        return PatientTabView::iconPicker(
            $options,
            $current,
            static fn(string $value): string => \e($value),
            static fn(string $name): string => \icon($name),
        );
    }

    public static function onboardingTip(
        array $tip,
        string $key,
        string $return,
        string $csrfField,
    ): string {
        return OnboardingTipView::render(
            $tip,
            $key,
            $return,
            $csrfField,
            static fn(string $value): string => \e($value),
            static fn(string $name): string => \icon($name),
        );
    }

    public static function contactEditForm(array $patient, int $clinicId): string
    {
        return PatientContactView::editForm(
            $patient,
            $clinicId,
            static fn(string $label, string $iconName): string => \action_summary_label($label, $iconName),
            static fn(): string => \csrf_field(),
            static fn(string $name, string $type, mixed $value, string $attributes): string => \input(
                $name,
                $type,
                $value,
                $attributes,
            ),
            static fn(string $label, string $control): string => \form_row($label, $control),
            static fn(int $cid, array $value): string => \patient_address_fields($cid, $value),
            static fn(string $label): string => \form_actions($label),
        );
    }
}
