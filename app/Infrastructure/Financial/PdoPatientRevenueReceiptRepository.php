<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use PDO;
use Prontoo\Application\Financial\PatientRevenueReceiptPort;
use RuntimeException;
use Throwable;

final class PdoPatientRevenueReceiptRepository implements PatientRevenueReceiptPort
{
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
            $patient = self::one(
                $pdo,
                "SELECT id FROM pi_patients WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE",
                [$patientId, $clinicId],
            );
            if (!$patient) {
                throw new RuntimeException('Paciente não encontrado no consultório atual.');
            }
            $revenue = self::one(
                $pdo,
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
            $existingMovementId = (int) (self::value(
                $pdo,
                "SELECT id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='patient_revenue' AND source_id=? AND movement_type='receipt' AND status='confirmed' ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$clinicId, $revenueId],
            ) ?: 0);
            $sessionId = null;
            $destinationId = 0;
            if ($existingMovementId <= 0) {
                if ($role === 'recepcionista') {
                    $session = \Prontoo\Core\Architecture\OperationGateway::invoke('financial_require_open_session', $clinicId, $userId);
                    $destinationId = (int) ($session['location_id'] ?? 0);
                    $sessionId = (int) ($session['id'] ?? 0);
                } else {
                    $destinationId = \Prontoo\Core\Architecture\OperationGateway::invoke('financial_ensure_admin_safe', $clinicId, $userId);
                }
                $existingMovementId = \Prontoo\Core\Architecture\OperationGateway::invoke(
                    'financial_create_movement',
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
            self::execute(
                $pdo,
                "UPDATE pi_financial_revenues SET status='efetivada', account_id=NULL, received_at=COALESCE(received_at,NOW()), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=? AND status='prevista'",
                [$userId, $revenueId, $clinicId, $patientId],
            );
            if (!empty($revenue['appointment_id'])) {
                self::execute(
                    $pdo,
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

    private static function one(PDO $pdo, string $sql, array $params): ?array
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function value(PDO $pdo, string $sql, array $params): mixed
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();
        return $value === false ? null : $value;
    }

    private static function execute(PDO $pdo, string $sql, array $params): void
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
    }
}
