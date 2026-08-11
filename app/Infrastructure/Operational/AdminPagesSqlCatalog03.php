<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AdminPagesSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.admin_pages.03.page_admin_deleted.01' => (
                "SELECT id,person_id,clinic_id FROM pi_patients WHERE id=? AND deleted_at IS NOT NULL"
            ),
            'operational.admin_pages.03.page_admin_deleted.02' => (
                "UPDATE pi_patients SET active=1,registration_needs_update=1,deleted_at=NULL,deleted_by=NULL,restored_at=NOW(),restored_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.admin_pages.03.page_admin_deleted.03' => (
                "SELECT id,patient_link_id,clinic_id FROM pi_care WHERE id=? AND deleted_at IS NOT NULL"
            ),
            'operational.admin_pages.03.page_admin_deleted.04' => (
                "UPDATE pi_care SET deleted_at=NULL,deleted_by=NULL,restored_at=NOW(),restored_by=? WHERE id=? AND clinic_id=?"
            ),
            'operational.admin_pages.03.page_admin_deleted.05' => (
                "SELECT id,person_id,clinic_id,deleted_at,deleted_by FROM pi_patients WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 80"
            ),
            'operational.admin_pages.03.page_admin_deleted.06' => (
                "SELECT id,patient_link_id,record_type,title,deleted_at,deleted_by FROM pi_care WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 80"
            ),
            'operational.admin_pages.03.page_admin_health.01' => (
                "SELECT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
