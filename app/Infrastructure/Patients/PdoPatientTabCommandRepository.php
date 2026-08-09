<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Patients;

use Prontoo\Application\Patients\PatientTabCommandPort;
use RuntimeException;
use Throwable;

final class PdoPatientTabCommandRepository implements PatientTabCommandPort
{
    public function createIfAbsent(
        int $clinicId,
        int $patientId,
        string $label,
        string $iconName,
        int $userId,
    ): array {
        $pdo = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $patient = \Prontoo\Core\Architecture\OperationGateway::invoke('one', 
                "SELECT id FROM pi_patients WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE",
                [$patientId, $clinicId],
            );
            if (!$patient) {
                throw new RuntimeException('Paciente não encontrado no consultório atual.');
            }
            $existing = \Prontoo\Core\Architecture\OperationGateway::invoke('one', 
                "SELECT id,sort_order FROM pi_patient_tabs WHERE clinic_id=? AND patient_link_id=? AND label=? AND active=1 LIMIT 1 FOR UPDATE",
                [$clinicId, $patientId, $label],
            );
            if ($existing) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return [
                    'status' => 'duplicate',
                    'id' => (int) ($existing['id'] ?? 0),
                    'sort_order' => (int) ($existing['sort_order'] ?? 0),
                ];
            }
            $rows = \Prontoo\Core\Architecture\OperationGateway::invoke('q', 
                "SELECT sort_order FROM pi_patient_tabs WHERE clinic_id=? AND patient_link_id=? FOR UPDATE",
                [$clinicId, $patientId],
            )->fetchAll();
            $maxSortOrder = 0;
            foreach ($rows as $row) {
                $maxSortOrder = max($maxSortOrder, (int) ($row['sort_order'] ?? 0));
            }
            $nextSortOrder = $maxSortOrder + 10;
            \Prontoo\Core\Architecture\OperationGateway::invoke('q', 
                "INSERT INTO pi_patient_tabs (clinic_id,patient_link_id,label,icon_name,sort_order,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())",
                [$clinicId, $patientId, $label, $iconName, $nextSortOrder, $userId],
            );
            $tabId = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'status' => 'created',
                'id' => $tabId,
                'sort_order' => $nextSortOrder,
            ];
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
