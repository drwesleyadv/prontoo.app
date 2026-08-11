<?php
declare(strict_types=1);

namespace Prontoo\Application\Identity;

use Closure;
use RuntimeException;

final class UserPermissionService
{
    public function __construct(private IdentityDataService $data)
    {
    }

    public function syncRoles(
        int $clinicId,
        int $userId,
        array $roles,
        array $manageableRoles,
        bool $propagate,
        Closure $assertMinimumRoles,
        Closure $enableRoles,
        Closure $activeRoles,
        Closure $propagatePermissions,
    ): array {
        if ($clinicId <= 0 || $userId <= 0) {
            throw new RuntimeException('Colaborador ou consultório não informado.');
        }
        $roles = array_values(array_intersect(
            array_keys($manageableRoles),
            array_values(array_unique(array_map('strval', $roles))),
        ));
        if (count($roles) < 1 || count($roles) > count($manageableRoles)) {
            throw new RuntimeException(
                'Escolha de 1 a ' . count($manageableRoles) . ' cargos para o colaborador.',
            );
        }
        $assertMinimumRoles($clinicId, $userId, $roles);
        $this->data->atomic(function () use (
            $clinicId,
            $userId,
            $roles,
            $enableRoles,
            $activeRoles,
        ): void {
            $enableRoles($clinicId, $roles, 'roles_sync');
            $ownerFlag = (int) ($this->data->scalar(
                'identity.permissions02.sync_user_roles_for_clinic.01',
                [$userId, $clinicId],
            ) ?? 0);
            $this->data->result(
                'identity.permissions02.sync_user_roles_for_clinic.02',
                array_merge([$userId, $clinicId], $roles),
                ['role_count' => count($roles)],
            );
            foreach ($roles as $role) {
                $this->data->result(
                    'identity.permissions02.sync_user_roles_for_clinic.03',
                    [$userId, $clinicId, $role, $ownerFlag],
                );
            }
            $actual = array_values(array_map('strval', (array) $activeRoles($clinicId, $userId)));
            $expected = $roles;
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                throw new RuntimeException(
                    'Não foi possível aplicar todos os cargos selecionados ao colaborador.',
                );
            }
        });
        if ($propagate) {
            $propagatePermissions($clinicId, $userId, 'roles_sync');
        }
        return array_values(array_map('strval', (array) $activeRoles($clinicId, $userId)));
    }

    public function createTeamMember(
        int $clinicId,
        array $payload,
        Closure $selectedRoles,
        Closure $saveTeamMember,
        Closure $saveWorkHours,
        Closure $audit,
    ): array {
        return (array) $this->data->atomic(function () use (
            $clinicId,
            $payload,
            $selectedRoles,
            $saveTeamMember,
            $saveWorkHours,
            $audit,
        ): array {
            $roles = array_values((array) $selectedRoles($payload, $clinicId));
            $userId = (int) $saveTeamMember($clinicId, $payload);
            if ($userId > 0 && in_array('medico', $roles, true)) {
                $saveWorkHours($clinicId, $userId, $payload);
            }
            $audit('usuario_salvo', 'usuario', $userId, [
                'perfil' => implode(',', $roles),
                'clinic_id' => $clinicId,
            ]);
            return [$roles, $userId];
        });
    }

    public function updateTeamMember(
        int $clinicId,
        int $userId,
        int $personId,
        string $name,
        string $email,
        array $roles,
        array $payload,
        array $commonProfile,
        Closure $updateCommonProfile,
        Closure $refreshSignature,
        Closure $synchronizeRoles,
        Closure $saveWorkHours,
        Closure $propagatePermissions,
        Closure $audit,
    ): array {
        $activeRoles = (array) $this->data->atomic(function () use (
            $clinicId,
            $userId,
            $personId,
            $name,
            $email,
            $roles,
            $payload,
            $commonProfile,
            $updateCommonProfile,
            $refreshSignature,
            $synchronizeRoles,
            $saveWorkHours,
            $audit,
        ): array {
            $this->data->result(
                'identity.permissions05.page_user.03',
                [$name, $personId],
            );
            $updateCommonProfile($personId, $commonProfile);
            $refreshSignature($personId);
            $this->data->result(
                'identity.permissions05.page_user.04',
                [$name, $email, $userId],
            );
            $currentRoles = array_values((array) $synchronizeRoles(
                $clinicId,
                $userId,
                $roles,
                false,
            ));
            if (in_array('medico', $roles, true)) {
                $saveWorkHours($clinicId, $userId, $payload);
            }
            $audit('usuario_salvo', 'usuario', $userId, [
                'perfil' => implode(',', $currentRoles),
                'clinic_id' => $clinicId,
                'audit_body' =>
                    'Cadastro e cargos do colaborador atualizados em uma única transação.',
            ]);
            return $currentRoles;
        });
        $propagatePermissions($clinicId, $userId, 'roles_sync');
        return $activeRoles;
    }
}
