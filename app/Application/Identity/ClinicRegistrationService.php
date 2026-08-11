<?php
declare(strict_types=1);

namespace Prontoo\Application\Identity;

use Closure;
use RuntimeException;

final class ClinicRegistrationService
{
    public function __construct(private IdentityDataService $data)
    {
    }

    public function register(
        array $registration,
        Closure $upsertPerson,
        Closure $lockPersonIdentity,
        Closure $passwordMatches,
        Closure $passwordAllowed,
        Closure $passwordHash,
        Closure $counterIncrement,
        Closure $ensureTrial,
        Closure $seedPermissions,
        Closure $seedClinicRoles,
        Closure $audit,
    ): array {
        return (array) $this->data->atomic(function () use (
            $registration,
            $upsertPerson,
            $lockPersonIdentity,
            $passwordMatches,
            $passwordAllowed,
            $passwordHash,
            $counterIncrement,
            $ensureTrial,
            $seedPermissions,
            $seedClinicRoles,
            $audit,
        ): array {
            $personId = (int) $upsertPerson(
                (string) $registration['doctor_name'],
                (string) $registration['cpf'],
                (string) $registration['birth_date'],
            );
            $lockPersonIdentity($personId);
            $existing = $this->data->row(
                'identity.auth04.page_signup.01',
                [$personId],
            );
            if ($existing !== null) {
                if (!(int) ($existing['active'] ?? 0)) {
                    throw new RuntimeException('Usuário existente está inativo.');
                }
                if (!(bool) $passwordMatches(
                    (string) $registration['password'],
                    (string) ($existing['password_hash'] ?? ''),
                )) {
                    throw new RuntimeException('__signup_credentials__');
                }
                $userId = (int) $existing['id'];
                $email = (string) $registration['email'];
                if ($email !== '' && empty($existing['email'])) {
                    $this->data->result(
                        'identity.auth04.page_signup.02',
                        [$email, $userId],
                    );
                }
            } else {
                if (!(bool) $passwordAllowed((string) $registration['password'])) {
                    throw new RuntimeException('__signup_password__');
                }
                $this->data->result(
                    'identity.auth04.page_signup.03',
                    [
                        $personId,
                        (string) $registration['doctor_name'],
                        (string) $registration['email'] !== ''
                            ? (string) $registration['email']
                            : null,
                        (string) $passwordHash((string) $registration['password']),
                    ],
                );
                $userId = $this->data->lastInsertId();
                $counterIncrement('users_total');
            }
            $this->data->result(
                'identity.auth04.page_signup.04',
                [
                    (string) $registration['legal_type'],
                    (string) $registration['legal_name'],
                    (string) $registration['document'],
                    (string) $registration['display_name'],
                    (string) $registration['phone'],
                    (string) $registration['profession'],
                    $userId,
                    $userId,
                    (string) $registration['accent_color'],
                    (string) $registration['address_line'],
                    (string) $registration['state'],
                    (string) $registration['city'],
                    (int) $registration['city_ibge'],
                    (string) $registration['timezone'],
                    (int) $registration['trial_start'],
                    (int) $registration['trial_start'],
                    (int) $registration['trial_end'],
                    (int) $registration['monthly_price_cents'],
                    (int) $registration['trial_start'],
                ],
            );
            $clinicId = $this->data->lastInsertId();
            $ensureTrial($clinicId, false);
            $counterIncrement('clinics_total');
            $this->data->result(
                'identity.auth04.page_signup.05',
                [$userId, $clinicId, 'gerente'],
            );
            $this->data->result(
                'identity.auth04.page_signup.06',
                [$userId, $clinicId, 'medico'],
            );
            $managerRoleId = (int) ($this->data->scalar(
                'identity.auth04.page_signup.07',
                [$userId, $clinicId],
            ) ?? 0);
            if ($managerRoleId <= 0) {
                throw new RuntimeException(
                    'Não foi possível definir o ambiente Administrativo inicial.',
                );
            }
            $ownerRows = $this->data->result(
                'identity.auth04.page_signup.08',
                [$userId, $clinicId],
            )->fetchAll();
            $ownerRoles = array_values(array_unique(array_map(
                static fn(array $row): string => (string) (array_values($row)[0] ?? ''),
                $ownerRows,
            )));
            sort($ownerRoles);
            if ($ownerRoles !== ['gerente', 'medico']) {
                throw new RuntimeException(
                    'Não foi possível registrar os ambientes Administrativo e Profissional do responsável.',
                );
            }
            $seedPermissions($clinicId);
            $seedClinicRoles($clinicId);
            $this->data->result(
                'identity.auth04.page_signup.09',
                [(string) $registration['profession'], $clinicId],
            );
            $audit('consultorio_criado', 'consultorio', $clinicId, [
                'clinic_id' => $clinicId,
                'nome' => (string) $registration['display_name'],
                'owner_user_id' => $userId,
                'cidade' => (string) $registration['city'],
                'uf' => (string) $registration['state'],
                'timezone' => (string) $registration['timezone'],
                'accent_color' => (string) $registration['accent_color'],
                'onboarding_done' => 1,
            ]);
            return [$userId, $managerRoleId];
        });
    }
}
