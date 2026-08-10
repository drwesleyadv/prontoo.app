<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Patients;

use PDO;
use Throwable;

final class PatientTabReadRepository
{
    public static function activeTabs(int $clinicId, int $patientId): array
    {
        $statement = self::pdo()->prepare(
            "SELECT id,label,icon_name,sort_order,created_at FROM pi_patient_tabs WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY sort_order ASC,id ASC",
        );
        $statement->execute([$clinicId, $patientId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function labelById(int $tabId): string
    {
        try {
            $statement = self::pdo()->prepare("SELECT label FROM pi_patient_tabs WHERE id=? LIMIT 1");
            $statement->execute([$tabId]);
            $label = $statement->fetchColumn();
            return $label === false ? '' : (string) $label;
        } catch (Throwable) {
            return '';
        }
    }

    private static function pdo(): PDO
    {
        return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
    }
}
