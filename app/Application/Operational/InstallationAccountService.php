<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

use Closure;
use RuntimeException;

final class InstallationAccountService
{
    public function __construct(private OperationalUseCaseService $data)
    {
    }

    public function createInitialDeveloper(
        string $name,
        string $email,
        string $passwordHash,
        Closure $upsertPerson,
        Closure $transactionIsActive,
    ): int {
        return (int) $this->data->atomic(function () use (
            $name,
            $email,
            $passwordHash,
            $upsertPerson,
            $transactionIsActive,
        ): int {
            $personId = (int) $upsertPerson();
            if ($personId <= 0) {
                throw new RuntimeException('Não foi possível preparar a identidade do Desenvolvedor.');
            }
            $this->data->result(
                'operational.install_installer.02.prontoo_install.01',
                [$personId, $name, $email, $passwordHash],
            );
            if (!(bool) $transactionIsActive()) {
                throw new RuntimeException(
                    'Transação de instalação encerrada antes do commit; verifique DDL executado por provas PI durante INSERT de domínio.',
                );
            }
            return $this->data->lastInsertId();
        });
    }
}
