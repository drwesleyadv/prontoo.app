<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AdminPagesSqlCatalog07
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.admin_pages.07.admin_clinic_detail_page.01' => (
                "SELECT c.*,ou.name AS owner_name,ou.email AS owner_email,ou.active AS owner_active,ou.last_login_at AS owner_last_login_at,ou.created_at AS owner_created_at,op.full_name AS owner_person_name,op.cpf AS owner_cpf,op.birth_date AS owner_birth_date,op.phone AS owner_phone,op.email AS owner_person_email,op.address AS owner_address,op.address_number AS owner_address_number,op.address_neighborhood AS owner_address_neighborhood,op.address_complement AS owner_address_complement,op.address_city AS owner_address_city,op.address_state AS owner_address_state,mu.name AS manager_name,mu.email AS manager_email FROM pi_clinics c JOIN pi_users ou ON ou.id=c.owner_user_id JOIN pi_persons op ON op.id=ou.person_id LEFT JOIN pi_users mu ON mu.id=c.manager_user_id WHERE c.id=?"
            ),
            'operational.admin_pages.07.admin_clinic_detail_page.02' => (
                "SELECT COUNT(*) FROM pi_user_roles WHERE clinic_id=? AND active=1"
            ),
            'operational.admin_pages.07.admin_clinic_detail_page.03' => (
                "SELECT COUNT(*) FROM pi_user_roles WHERE clinic_id=? AND role_code='medico' AND active=1"
            ),
            'operational.admin_pages.07.admin_clinic_detail_page.04' => (
                "SELECT role_code FROM pi_user_roles WHERE clinic_id=? AND user_id=? AND active=1 ORDER BY role_code"
            ),
            'operational.admin_pages.07.admin_clinic_people_counts_by_cpf.01' => (
                "SELECT ur.clinic_id,COUNT(DISTINCT CASE WHEN ur.role_code='medico' THEN NULLIF(p.cpf,'') END) AS professionals,COUNT(DISTINCT NULLIF(p.cpf,'')) AS collaborators FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 JOIN pi_persons p ON p.id=u.person_id WHERE ur.clinic_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ") AND ur.active=1 GROUP BY ur.clinic_id"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
