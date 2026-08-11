<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class InstallInstallerSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.install_installer.02.prontoo_install.01' => (
                "INSERT INTO pi_users (person_id,name,email,password_hash,is_global_admin,active,created_at) VALUES (?,?,?,?,1,1,NOW())"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
