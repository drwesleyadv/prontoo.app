<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Patients;

final class PatientContactView
{
    public static function editForm(
        array $patient,
        int $clinicId,
        callable $actionLabel,
        callable $csrfField,
        callable $input,
        callable $formRow,
        callable $addressFields,
        callable $formActions,
    ): string {
        return '<details class="patient-edit patient-contact-edit"><summary class="primary small cmdlike">' .
            $actionLabel('Atualizar contato', 'contact_phone') .
            '</summary><form method="post" class="compact patient-record-form">' .
            $csrfField() .
            '<input type="hidden" name="act" value="update_patient_contact">' .
            '<div class="two">' .
            $formRow(
                'Telefone',
                $input(
                    'phone',
                    'text',
                    $patient['phone'] ?? '',
                    'required inputmode="tel"',
                ),
            ) .
            $formRow(
                'E-mail',
                $input('email', 'email', $patient['email'] ?? '', 'required'),
            ) .
            '</div>' .
            $addressFields($clinicId, $patient) .
            $formActions('Salvar contato') .
            '</form></details>';
    }
}
