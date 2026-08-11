<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class PatientsSqlCatalog04
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.patients.04.page_patient_suggest.01' => (
                "SELECT pp.id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,pp.created_at,pp.updated_at,pp.registration_needs_update,p.full_name,p.birth_date,p.cpf,(SELECT COUNT(*) FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1) guardian_count " .
                                PatientDirectorySql::metrics($todayStart, $todayEnd) .
                                " FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 AND pp.deleted_at IS NULL" .
                                PatientDirectorySql::search($searchMode) .
                                " ORDER BY p.full_name ASC, pp.id DESC LIMIT " .
                                (int) $limit
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
