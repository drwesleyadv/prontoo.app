<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Patients;

use Prontoo\Application\Patients\PatientContactCommandPort;
use RuntimeException;
use Throwable;

final class PdoPatientContactCommandRepository implements PatientContactCommandPort
{
    public function update(
        int $clinicId,
        int $patientId,
        int $userId,
        array $contact,
    ): array {
        $pdo = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $patient = self::one(
                "SELECT id FROM pi_patients WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE",
                [$patientId, $clinicId],
            );
            if (!$patient) {
                throw new RuntimeException('Paciente não encontrado no consultório atual.');
            }
            self::q(
                "UPDATE pi_patients SET phone=?,email=?,address=?,address_zip=?,address_number=?,address_neighborhood=?,address_complement=?,address_state=?,address_city=?,address_city_ibge=?,registration_needs_update=0,updated_at=NOW() WHERE id=? AND clinic_id=? AND active=1",
                [
                    $contact['phone'],
                    $contact['email'],
                    $contact['address'],
                    $contact['address_zip'],
                    $contact['address_number'],
                    $contact['address_neighborhood'],
                    $contact['address_complement'],
                    $contact['address_state'],
                    $contact['address_city'],
                    $contact['address_city_ibge'],
                    $patientId,
                    $clinicId,
                ],
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return ['status' => 'updated', 'patient_id' => $patientId];
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
    private static function one(string $sql, array $params = []): ?array
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return is_array($row) ? $row : null;
    }

    private static function q(string $sql, array $params = []): \PDOStatement
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

}
