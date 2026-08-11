<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

use Closure;
use RuntimeException;

final class FinancialRevenueService
{
    public function __construct(
        private FinancialDataService $data,
        private FinancialMovementService $movements,
    ) {
    }

    public function cancelAppointmentRevenue(
        int $clinicId,
        int $appointmentId,
        int $userId,
        string $reason,
        Closure $audit,
    ): void {
        $this->data->atomic(function () use (
            $clinicId,
            $appointmentId,
            $userId,
            $reason,
            $audit,
        ): void {
            $revenue = $this->data->row(
                'financial.05.cancel_appointment_revenue.01',
                [$clinicId, $appointmentId],
            );
            if (!$revenue || (string) ($revenue['status'] ?? '') !== 'prevista') {
                return;
            }
            $this->data->result(
                'financial.05.cancel_appointment_revenue.02',
                [$userId, (int) $revenue['id'], $clinicId],
            );
            $this->data->result(
                'financial.05.cancel_appointment_revenue.03',
                [$reason, $clinicId, $appointmentId],
            );
            $audit(
                'receita_prevista_cancelada',
                'financeiro',
                (int) $revenue['id'],
                [
                    'appointment_id' => $appointmentId,
                    'motivo' => $reason,
                    'audit_body' =>
                        'Receita prevista do agendamento foi cancelada por cancelamento, ausência ou remarcação sem cobrança.',
                ],
            );
        });
    }

    public function receiveByAdministrator(
        int $clinicId,
        int $userId,
        int $revenueId,
        string $method,
        int $destinationLocationId,
        string $notes,
        Closure $validateMovement,
        Closure $defaultMovementTitle,
        Closure $audit,
    ): int {
        return (int) $this->data->atomic(function () use (
            $clinicId,
            $userId,
            $revenueId,
            $method,
            $destinationLocationId,
            $notes,
            $validateMovement,
            $defaultMovementTitle,
            $audit,
        ): int {
            $revenue = $this->data->row(
                'financial.05.admin_receive_expected_revenue.01',
                [$revenueId, $clinicId],
            );
            if (!$revenue) {
                throw new RuntimeException(
                    'Selecione uma pendência de agendamento ainda não recebida.',
                );
            }
            $amountCents = (int) $revenue['amount_cents'];
            $appointmentId = (int) $revenue['appointment_id'];
            $destination = $this->data->row(
                'financial.05.admin_receive_expected_revenue.02',
                [$destinationLocationId, $clinicId],
            );
            if (!$destination) {
                throw new RuntimeException('Destino financeiro inválido.');
            }
            $accountId = (string) $destination['location_type'] === 'bank_account'
                ? ((int) ($destination['account_id'] ?? 0) ?: null)
                : null;
            $title = 'Recebimento · ' . trim(
                (string) ($revenue['procedure_title']
                    ?: $revenue['title']
                    ?: 'Atendimento agendado'),
            );
            $movementNotes =
                'Administrador recebeu pendência de agendamento em Cofre/Banco; não movimenta Gaveta.' .
                (trim($notes) !== '' ? ' ' . trim($notes) : '');
            $existing = $this->data->row(
                'financial.05.admin_receive_expected_revenue.03',
                [$clinicId, $appointmentId],
            );
            if ($existing) {
                $movementId = (int) $existing['id'];
                $this->movements->update(
                    $clinicId,
                    $movementId,
                    'receipt',
                    $amountCents,
                    null,
                    $destinationLocationId,
                    null,
                    $userId,
                    $title,
                    $method,
                    $movementNotes,
                    'confirmed',
                    $validateMovement,
                );
            } else {
                $movementId = $this->movements->create(
                    $clinicId,
                    'receipt',
                    $amountCents,
                    null,
                    $destinationLocationId,
                    null,
                    $userId,
                    $title,
                    $method,
                    $movementNotes,
                    'confirmed',
                    'appointment',
                    $appointmentId,
                    $validateMovement,
                    $defaultMovementTitle,
                );
            }
            $this->data->result(
                'financial.05.admin_receive_expected_revenue.04',
                [$method, $accountId, $userId, $revenueId, $clinicId],
            );
            $this->data->result(
                'financial.05.admin_receive_expected_revenue.05',
                [$method, $amountCents, $revenueId, $appointmentId, $clinicId],
            );
            $audit(
                'recebimento_administrativo_pendencia_agendamento',
                'financeiro',
                $revenueId,
                [
                    'appointment_id' => $appointmentId,
                    'movement_id' => $movementId,
                    'valor' => $amountCents,
                    'forma' => $method,
                    'destino' => $destinationLocationId,
                    'audit_body' =>
                        'Administrador recebeu pendência financeira vinculada ao agendamento, evitando receita avulsa duplicada.',
                ],
            );
            return $movementId;
        });
    }

    public function receiveAtCashier(
        int $clinicId,
        int $userId,
        int $revenueId,
        string $method,
        int $destinationLocationId,
        string $notes,
        array $openSession,
        Closure $officeDestinationBelongs,
        Closure $validateMovement,
        Closure $defaultMovementTitle,
        Closure $audit,
    ): int {
        return (int) $this->data->atomic(function () use (
            $clinicId,
            $userId,
            $revenueId,
            $method,
            $destinationLocationId,
            $notes,
            $openSession,
            $officeDestinationBelongs,
            $validateMovement,
            $defaultMovementTitle,
            $audit,
        ): int {
            $session = $this->data->row(
                'financial.10.receive_expected_appointment_revenue.01',
                [(int) $openSession['id'], $clinicId, $userId],
            );
            if (!$session) {
                throw new RuntimeException(
                    'Não há Gaveta aberta para registrar recebimento.',
                );
            }
            $revenue = $this->data->row(
                'financial.10.receive_expected_appointment_revenue.02',
                [$revenueId, $clinicId],
            );
            if (!$revenue) {
                throw new RuntimeException(
                    'Selecione uma receita prevista de Procedimento Agendado ainda não recebida.',
                );
            }
            $amountCents = (int) $revenue['amount_cents'];
            $appointmentId = (int) $revenue['appointment_id'];
            $toLocationId = (int) $session['location_id'];
            $sessionId = (int) $session['id'];
            $accountId = null;
            $extraNote =
                'Recebimento em dinheiro vinculado ao agendamento; compõe a conferência da Gaveta.';
            if ($method !== 'dinheiro') {
                if (!$officeDestinationBelongs($clinicId, $destinationLocationId)) {
                    throw new RuntimeException(
                        'Informe o Destino entre as contas do Consultório para recebimentos que não forem em Dinheiro.',
                    );
                }
                $destination = $this->data->row(
                    'financial.10.receive_expected_appointment_revenue.03',
                    [$destinationLocationId, $clinicId],
                );
                $toLocationId = $destinationLocationId;
                $sessionId = null;
                $accountId = $destination && (string) $destination['location_type'] === 'bank_account'
                    ? ((int) ($destination['account_id'] ?? 0) ?: null)
                    : null;
                $extraNote =
                    'Recebimento sem dinheiro físico vinculado ao agendamento; direcionado ao Consultório e fora da conferência da Gaveta.';
            }
            $title = 'Recebimento · ' . trim(
                (string) ($revenue['procedure_title']
                    ?: $revenue['title']
                    ?: 'Procedimento agendado'),
            );
            $cleanNotes = trim($notes);
            $movementNotes = $extraNote . ($cleanNotes !== '' ? ' ' . $cleanNotes : '');
            $movementStatus = $method === 'dinheiro' ? 'pending_review' : 'confirmed';
            $existing = $this->data->row(
                'financial.10.receive_expected_appointment_revenue.04',
                [$clinicId, $appointmentId],
            );
            if ($existing) {
                $movementId = (int) $existing['id'];
                $this->movements->update(
                    $clinicId,
                    $movementId,
                    'receipt',
                    $amountCents,
                    null,
                    $toLocationId,
                    $sessionId,
                    $userId,
                    $title,
                    $method,
                    $movementNotes,
                    $movementStatus,
                    $validateMovement,
                );
            } else {
                $movementId = $this->movements->create(
                    $clinicId,
                    'receipt',
                    $amountCents,
                    null,
                    $toLocationId,
                    $sessionId,
                    $userId,
                    $title,
                    $method,
                    $movementNotes,
                    $movementStatus,
                    'appointment',
                    $appointmentId,
                    $validateMovement,
                    $defaultMovementTitle,
                );
            }
            $this->data->result(
                'financial.10.receive_expected_appointment_revenue.05',
                [$method, $accountId, $userId, $revenueId, $clinicId],
            );
            $this->data->result(
                'financial.10.receive_expected_appointment_revenue.06',
                [$method, $amountCents, $revenueId, $appointmentId, $clinicId],
            );
            $audit(
                'recebimento_atendimento_agendado',
                'financeiro',
                $revenueId,
                [
                    'appointment_id' => $appointmentId,
                    'movement_id' => $movementId,
                    'valor' => $amountCents,
                    'forma' => $method,
                    'conta_na_gaveta' => $method === 'dinheiro',
                    'audit_body' =>
                        'Recepção registrou recebimento de receita prevista vinculada a Procedimento Agendado.',
                ],
            );
            return $movementId;
        });
    }
}
