<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

use Closure;
use RuntimeException;

final class AppointmentCommandService
{
    public function __construct(private OperationalUseCaseService $data)
    {
    }

    public function createBlock(
        int $clinicId,
        ?int $doctorUserId,
        string $startAt,
        string $endAt,
        string $reason,
        int $createdByUserId,
        Closure $conflictMessage,
    ): int {
        return (int) $this->data->atomic(function () use (
            $clinicId,
            $doctorUserId,
            $startAt,
            $endAt,
            $reason,
            $createdByUserId,
            $conflictMessage,
        ): int {
            $this->data->result(
                'operational.appointments.05.page_appointments.22',
                [$clinicId],
            );
            $conflict = (string) $conflictMessage(
                $clinicId,
                $doctorUserId,
                $startAt,
                $endAt,
                'bloqueio',
                0,
                0,
            );
            if ($conflict !== '') {
                throw new RuntimeException($conflict);
            }
            $this->data->result(
                'operational.appointments.05.page_appointments.23',
                [
                    $clinicId,
                    $doctorUserId,
                    $startAt,
                    $endAt,
                    $reason,
                    $createdByUserId,
                ],
            );
            return $this->data->lastInsertId();
        });
    }

    public function updateBlock(
        int $clinicId,
        int $blockId,
        ?int $doctorUserId,
        string $startAt,
        string $endAt,
        string $reason,
        Closure $conflictMessage,
    ): void {
        $this->data->atomic(function () use (
            $clinicId,
            $blockId,
            $doctorUserId,
            $startAt,
            $endAt,
            $reason,
            $conflictMessage,
        ): void {
            $this->data->result(
                'operational.appointments.05.page_appointments.25',
                [$clinicId],
            );
            $conflict = (string) $conflictMessage(
                $clinicId,
                $doctorUserId,
                $startAt,
                $endAt,
                'alteração do bloqueio',
                0,
                $blockId,
            );
            if ($conflict !== '') {
                throw new RuntimeException($conflict);
            }
            $this->data->result(
                'operational.appointments.05.page_appointments.26',
                [$doctorUserId, $startAt, $endAt, $reason, $blockId, $clinicId],
            );
        });
    }

    public function updateAppointment(
        int $clinicId,
        int $appointmentId,
        int $doctorUserId,
        int $patientId,
        string $startAt,
        string $endAt,
        int $procedureId,
        string $reasonSelection,
        string $notes,
        string $changeReason,
        int $userId,
        Closure $lockedGuardMessage,
        Closure $conflictMessage,
        Closure $paymentContext,
        Closure $syncFinancial,
    ): void {
        $this->data->atomic(function () use (
            $clinicId,
            $appointmentId,
            $doctorUserId,
            $patientId,
            $startAt,
            $endAt,
            $procedureId,
            $reasonSelection,
            $notes,
            $changeReason,
            $userId,
            $lockedGuardMessage,
            $conflictMessage,
            $paymentContext,
            $syncFinancial,
        ): void {
            $this->data->result(
                'operational.appointments.05.page_appointments.30',
                [$clinicId],
            );
            $locked = $this->data->row(
                'operational.appointments.05.page_appointments.31',
                [$appointmentId, $clinicId],
            );
            $guard = (string) $lockedGuardMessage($locked);
            if ($guard !== '') {
                throw new RuntimeException($guard);
            }
            $conflict = (string) $conflictMessage(
                $clinicId,
                $doctorUserId,
                $startAt,
                $endAt,
                'alteração do agendamento',
                $appointmentId,
                0,
            );
            if ($conflict !== '') {
                throw new RuntimeException($conflict);
            }
            [
                $paid,
                $method,
                $amountCents,
                $paymentDestination,
                $paymentError,
            ] = (array) $paymentContext($clinicId, $procedureId);
            if ($paymentError) {
                throw new RuntimeException((string) $paymentError);
            }
            $this->data->result(
                'operational.appointments.05.page_appointments.32',
                [
                    $patientId,
                    $doctorUserId,
                    $startAt,
                    $endAt,
                    $procedureId,
                    $this->procedureReason($clinicId, $reasonSelection),
                    trim($notes),
                    trim($changeReason),
                    $amountCents,
                    $method ?: null,
                    $paid ? 'efetivada' : 'prevista',
                    $paid ? 1 : 0,
                    $appointmentId,
                    $clinicId,
                ],
            );
            $syncFinancial(
                $clinicId,
                $appointmentId,
                $userId,
                $paymentDestination,
            );
        });
    }

    private function procedureReason(int $clinicId, string $selection): string
    {
        $selection = mb_trim($selection);
        if (str_starts_with($selection, 'procedure:')) {
            $procedureId = (int) substr($selection, 10);
            $procedure = $this->data->row(
                'operational.appointments.03.procedure_reason_from_post.01',
                [$procedureId, $clinicId],
            );
            if ($procedure) {
                return (string) $procedure['title'];
            }
        }
        return $selection === 'Outro' ? '' : $selection;
    }

    public function createAppointment(
        int $clinicId,
        int $patientId,
        int $doctorUserId,
        string $startAt,
        string $endAt,
        int $procedureId,
        string $notes,
        int $userId,
        Closure $durationMessage,
        Closure $conflictMessage,
        Closure $paymentContext,
        Closure $syncFinancial,
    ): int {
        return (int) $this->data->atomic(function () use (
            $clinicId,
            $patientId,
            $doctorUserId,
            $startAt,
            $endAt,
            $procedureId,
            $notes,
            $userId,
            $durationMessage,
            $conflictMessage,
            $paymentContext,
            $syncFinancial,
        ): int {
            $this->data->result(
                'operational.appointments.05.page_appointments.35',
                [$clinicId],
            );
            $procedure = $this->data->row(
                'operational.appointments.05.page_appointments.36',
                [$procedureId, $clinicId],
            );
            if (!$procedure) {
                throw new RuntimeException(
                    'O procedimento selecionado não está mais disponível. Escolha outro procedimento cadastrado.',
                );
            }
            $durationConflict = (string) $durationMessage(
                $clinicId,
                $procedureId,
                $startAt,
                $endAt,
            );
            if ($durationConflict !== '') {
                throw new RuntimeException($durationConflict);
            }
            $conflict = (string) $conflictMessage(
                $clinicId,
                $doctorUserId,
                $startAt,
                $endAt,
                'agendamento',
                0,
                0,
            );
            if ($conflict !== '') {
                throw new RuntimeException($conflict);
            }
            [
                $paid,
                $method,
                $amountCents,
                $paymentDestination,
                $paymentError,
            ] = (array) $paymentContext($clinicId, $procedureId);
            if ($paymentError) {
                throw new RuntimeException((string) $paymentError);
            }
            $this->data->result(
                'operational.appointments.05.page_appointments.37',
                [
                    $clinicId,
                    $patientId,
                    $doctorUserId,
                    $startAt,
                    $endAt,
                    $procedureId,
                    (string) $procedure['title'],
                    trim($notes),
                    $amountCents,
                    $method ?: null,
                    $paid ? 'efetivada' : 'prevista',
                    $paid ? 1 : 0,
                    $userId,
                ],
            );
            $appointmentId = $this->data->lastInsertId();
            $syncFinancial(
                $clinicId,
                $appointmentId,
                $userId,
                $paymentDestination,
            );
            return $appointmentId;
        });
    }
}
