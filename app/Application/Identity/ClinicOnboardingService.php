<?php
declare(strict_types=1);

namespace Prontoo\Application\Identity;

use Closure;
use Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02;

final class ClinicOnboardingService
{
    public function __construct(private IdentityDataService $data)
    {
    }

    public function complete(
        array $settings,
        array $rolesEnabled,
        array $roleDefaults,
        array $roleLabels,
        array $defaultPermissions,
        array $actions,
        array $teamMember,
        Closure $saveTeamMember,
        Closure $audit,
    ): void {
        $this->data->atomic(function () use (
            $settings,
            $rolesEnabled,
            $roleDefaults,
            $roleLabels,
            $defaultPermissions,
            $actions,
            $teamMember,
            $saveTeamMember,
            $audit,
        ): void {
            $clinicId = (int) $settings['clinic_id'];
            $this->data->result(
                'identity.auth07.page_onboarding.02',
                [
                    (string) $settings['display_name'],
                    (string) $settings['phone'],
                    (string) $settings['profession'],
                    (string) $settings['clinic_icon'],
                    (string) $settings['accent_color'],
                    (string) $settings['address_line'],
                    (string) $settings['state'],
                    (string) $settings['city'],
                    (int) $settings['city_ibge'],
                    (string) $settings['timezone'],
                    (int) $settings['trial_start'],
                    (int) $settings['trial_end'],
                    $clinicId,
                ],
            );
            $position = 1;
            foreach ($roleDefaults as $role => $defaultLabel) {
                $label = $role === 'medico'
                    ? (string) $settings['profession']
                    : (trim((string) ($roleLabels[$role] ?? $defaultLabel)) ?: (string) $defaultLabel);
                $this->data->result(
                    'identity.auth07.page_onboarding.03',
                    [
                        $clinicId,
                        (string) $role,
                        $label,
                        ClinicConfigDomainOperations02::default_role_icon((string) $role),
                        (int) ($rolesEnabled[$role] ?? 0),
                        $position++,
                    ],
                );
            }
            foreach ($roleDefaults as $role => $_label) {
                foreach ($actions as $actionKey => $_action) {
                    $allow = 0;
                    if (!empty($rolesEnabled[$role])) {
                        $allow = in_array($actionKey, $defaultPermissions[$role] ?? [], true)
                            ? 1
                            : 0;
                    }
                    if ($role === 'gerente' && !empty($rolesEnabled[$role])) {
                        $allow = 1;
                    }
                    $this->data->result(
                        'identity.auth07.page_onboarding.04',
                        [$clinicId, (string) $role, (string) $actionKey, $allow],
                    );
                }
            }
            $newUserId = (int) $saveTeamMember($clinicId, $teamMember);
            if ($newUserId > 0) {
                $audit('usuario_salvo', 'usuario', $newUserId, [
                    'clinic_id' => $clinicId,
                    'origem' => 'wizard',
                ]);
            }
            $audit('onboarding_concluido', 'consultorio', $clinicId, [
                'clinic_id' => $clinicId,
                'clinic_icon' => (string) $settings['clinic_icon'],
                'accent_color' => (string) $settings['accent_color'],
                'audit_body' =>
                    'Onboarding concluído com identidade visual inicial. Permissões permaneceram com padrão do sistema para ajuste posterior.',
            ]);
        });
    }
}
