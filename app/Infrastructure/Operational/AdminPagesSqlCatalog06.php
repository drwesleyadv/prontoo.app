<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AdminPagesSqlCatalog06
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.admin_pages.06.page_admin_people.01' => (
                "SELECT u.id,u.name,u.email,u.active,u.is_global_admin,p.cpf,p.birth_date,COUNT(ur.id) AS vinculos FROM pi_users u JOIN pi_persons p ON p.id=u.person_id LEFT JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.active=1 GROUP BY u.id,u.name,u.email,u.active,u.is_global_admin,p.cpf,p.birth_date ORDER BY u.name ASC LIMIT 200"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
