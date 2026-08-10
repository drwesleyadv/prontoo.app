<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use Prontoo\Application\Financial\PatientRevenueReceiptPort;
use Prontoo\Application\Financial\PatientRevenueSettlementPort;
use RuntimeException;
use Throwable;

final class PdoPatientRevenueReceiptRepository implements PatientRevenueReceiptPort
{
    public function __construct(private PatientRevenueSettlementPort $settlement)
    {
    }

    public function receive(
        int $clinicId,
        int $patientId,
        int $revenueId,
        int $userId,
        string $role,
    ): array {
        $pdo = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $patient = $this->one(
                "SELECT id FROM pi_patients WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE",
                [$patientId, $clinicId],
            );
            if (!$patient) {
                throw new RuntimeException('Paciente não encontrado no consultório atual.');
            }
            $revenue = $this->one(
                "SELECT id,appointment_id,amount_cents,title,payment_method,status FROM pi_financial_revenues WHERE id=? AND clinic_id=? AND patient_link_id=? LIMIT 1 FOR UPDATE",
                [$revenueId, $clinicId, $patientId],
            );
            if (!$revenue || (string) ($revenue['status'] ?? '') !== 'prevista') {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return [
                    'status' => 'not_pending',
                    'revenue_id' => $revenueId,
                    'movement_id' => 0,
                    'amount_cents' => 0,
                    'title' => '',
                ];
            }
            $existingMovementId = (int) ($this->val(
                "SELECT id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='patient_revenue' AND source_id=? AND movement_type='receipt' AND status='confirmed' ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$clinicId, $revenueId],
            ) ?: 0);
            $sessionId = null;
            $destinationId = 0;
            if ($existingMovementId <= 0) {
                if ($role === 'recepcionista') {
                    $session = $this->settlement->requireOpenSession($clinicId, $userId);
                    $destinationId = (int) ($session['location_id'] ?? 0);
                    $sessionId = (int) ($session['id'] ?? 0);
                } else {
                    $destinationId = $this->settlement->ensureAdminSafe($clinicId, $userId);
                }
                $existingMovementId = $this->settlement->createMovement(
                    $clinicId,
                    'receipt',
                    (int) $revenue['amount_cents'],
                    null,
                    $destinationId,
                    $sessionId,
                    $userId,
                    (string) ($revenue['title'] ?: 'Recebimento do paciente'),
                    (string) ($revenue['payment_method'] ?? ''),
                    'Recebimento registrado pela ficha do paciente.',
                    'confirmed',
                    'patient_revenue',
                    $revenueId,
                );
            }
            $this->q(
                "UPDATE pi_financial_revenues SET status='efetivada', account_id=NULL, received_at=COALESCE(received_at,NOW()), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=? AND status='prevista'",
                [$userId, $revenueId, $clinicId, $patientId],
            );
            if (!empty($revenue['appointment_id'])) {
                $this->q(
                    "UPDATE pi_appointments SET payment_status='efetivada', payment_confirmed_at=COALESCE(payment_confirmed_at,NOW()), revenue_id=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?",
                    [$revenueId, (int) $revenue['appointment_id'], $clinicId, $patientId],
                );
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'status' => 'received',
                'revenue_id' => $revenueId,
                'movement_id' => $existingMovementId,
                'amount_cents' => (int) $revenue['amount_cents'],
                'title' => (string) ($revenue['title'] ?? ''),
            ];
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
    private function one(string $sql, array $params = []): ?array
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return is_array($row) ? $row : null;
    }

    private function val(string $sql, array $params = []): mixed
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        return $value === false ? null : $value;
    }

    private function q(string $sql, array $params = []): \PDOStatement
    {
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

}
