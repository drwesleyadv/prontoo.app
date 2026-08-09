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
            static fn(string $value): string => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value),
            static fn(string $name): string => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($name),
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
            static fn(string $value): string => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value),
            static fn(string $name): string => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($name),
        );
    }

    public static function contactEditForm(array $patient, int $clinicId): string
    {
        return PatientContactView::editForm(
            $patient,
            $clinicId,
            static fn(string $label, string $iconName): string => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label($label, $iconName),
            static fn(): string => \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field(),
            static fn(string $name, string $type, mixed $value, string $attributes): string => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                $name,
                $type,
                $value,
                $attributes,
            ),
            static fn(string $label, string $control): string => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row($label, $control),
            static fn(int $cid, array $value): string => \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_address_fields($cid, $value),
            static fn(string $label): string => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions($label),
        );
    }
}
