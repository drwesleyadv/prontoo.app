<?php
declare(strict_types=1);

namespace Prontoo\Application\Identity;

use Closure;

final class UserCredentialService
{
    public function __construct(private IdentityDataService $data)
    {
    }

    public function updateProfile(
        int $userId,
        int $personId,
        string $name,
        string $email,
        Closure $audit,
    ): void {
        $this->data->atomic(function () use (
            $userId,
            $personId,
            $name,
            $email,
            $audit,
        ): void {
            $this->data->result(
                'identity.auth06.page_profile.03',
                [$name, $personId],
            );
            $this->data->result(
                'identity.auth06.page_profile.04',
                [$name, $email !== '' ? $email : null, $userId],
            );
            $audit('usuario_proprio_atualizado', 'usuario', $userId, [
                'target_name' => $name,
                'audit_body' =>
                    'O próprio usuário atualizou nome e e-mail cadastrais. CPF e nascimento permanecem imutáveis.',
            ]);
        });
    }

    public function changePassword(
        int $userId,
        string $newPassword,
        string $targetName,
        Closure $passwordHash,
        Closure $rotateAuthGeneration,
        Closure $retirePersistentDevices,
        Closure $audit,
    ): void {
        $this->data->atomic(function () use (
            $userId,
            $newPassword,
            $targetName,
            $passwordHash,
            $rotateAuthGeneration,
            $retirePersistentDevices,
            $audit,
        ): void {
            $this->data->result(
                'identity.auth06.page_profile.05',
                [(string) $passwordHash($newPassword), $userId],
            );
            $rotateAuthGeneration($userId);
            $retirePersistentDevices($userId);
            $audit('senha_redefinida', 'usuario', $userId, [
                'target_name' => $targetName,
                'audit_body' =>
                    'O próprio usuário alterou a senha; todas as sessões anteriores foram revogadas.',
            ]);
        });
    }
}
