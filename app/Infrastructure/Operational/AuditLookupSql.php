<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use InvalidArgumentException;

final class AuditLookupSql
{
    private const PROJECTIONS = [
        'users_name' => ['pi_users', 'id,name'],
        'users_status' => ['pi_users', 'id,name,active'],
        'clinics_name' => ['pi_clinics', 'id,display_name'],
        'clinics_environment' => ['pi_clinics', 'id,display_name,timezone,address_state,address_city,responsible_profession,clinic_icon,accent_color,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents,active'],
        'persons_name' => ['pi_persons', 'id,full_name'],
        'persons_identity' => ['pi_persons', 'id,full_name,cpf,birth_date'],
        'patients_person' => ['pi_patients', 'id,person_id'],
        'patients_scope' => ['pi_patients', 'id,person_id,clinic_id'],
    ];

    private function __construct()
    {
    }

    public static function statement(string $projection, bool $tenantScoped, int $itemCount): string
    {
        [$table, $columns] = self::PROJECTIONS[$projection]
            ?? throw new InvalidArgumentException('Projeção de lookup inválida.');
        $scope = $tenantScoped ? 'clinic_id=? AND ' : '';
        return "SELECT {$columns} FROM {$table} WHERE {$scope}id IN (" . OperationalSequenceSql::placeholders($itemCount) . ')';
    }
}
