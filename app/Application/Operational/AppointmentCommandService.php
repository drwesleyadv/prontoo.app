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

    public function removeBlock(
        int $clinicId,
        int $blockId,
        int $userId,
        string $reason,
    ): bool {
        return (bool) $this->data->atomic(function () use (
            $clinicId,
            $blockId,
            $userId,
            $reason,
        ): bool {
            $block = $this->data->row(
                'operational.appointments.05.page_appointments.27',
                [$blockId, $clinicId],
            );
            if (!$block) {
                return false;
            }
            $this->data->result(
                'operational.appointments.05.page_appointments.28',
                [$userId, trim($reason), $blockId, $clinicId],
            );
            return true;
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

    public function createAppointmentBatch(
        int $clinicId,
        int $patientId,
        int $doctorUserId,
        array $appointments,
        int $userId,
        Closure $durationMessage,
        Closure $workHoursMessage,
        Closure $conflictMessage,
        Closure $paymentContext,
        Closure $syncFinancial,
    ): array {
        if ($appointments === []) {
            throw new RuntimeException('Informe ao menos um agendamento.');
        }
        return (array) $this->data->atomic(function () use (
            $clinicId,
            $patientId,
            $doctorUserId,
            $appointments,
            $userId,
            $durationMessage,
            $workHoursMessage,
            $conflictMessage,
            $paymentContext,
            $syncFinancial,
        ): array {
            $this->data->result(
                'operational.appointments.05.page_appointments.35',
                [$clinicId],
            );
            $ids = [];
            foreach (array_values($appointments) as $index => $appointment) {
                $startAt = trim((string) ($appointment['start_at'] ?? ''));
                $endAt = trim((string) ($appointment['end_at'] ?? ''));
                $procedureId = (int) ($appointment['procedure_id'] ?? 0);
                $notes = trim((string) ($appointment['notes'] ?? ''));
                $prefix = $index > 0 ? 'Recorrência ' . $index . ': ' : '';
                if ($startAt === '' || $endAt === '' || $procedureId <= 0) {
                    throw new RuntimeException($prefix . 'Informe procedimento e Data/Hora válidos.');
                }
                $procedure = $this->data->row(
                    'operational.appointments.05.page_appointments.36',
                    [$procedureId, $clinicId],
                );
                if (!$procedure) {
                    throw new RuntimeException(
                        $prefix . 'O procedimento selecionado não está mais disponível. Escolha outro procedimento cadastrado.',
                    );
                }
                $hoursConflict = (string) $workHoursMessage(
                    $clinicId,
                    $doctorUserId,
                    $startAt,
                    $endAt,
                );
                if ($hoursConflict !== '') {
                    throw new RuntimeException($prefix . $hoursConflict);
                }
                $durationConflict = (string) $durationMessage(
                    $clinicId,
                    $procedureId,
                    $startAt,
                    $endAt,
                );
                if ($durationConflict !== '') {
                    throw new RuntimeException($prefix . $durationConflict);
                }
                $conflict = (string) $conflictMessage(
                    $clinicId,
                    $doctorUserId,
                    $startAt,
                    $endAt,
                    $index > 0 ? 'recorrência do agendamento' : 'agendamento',
                    0,
                    0,
                );
                if ($conflict !== '') {
                    throw new RuntimeException($prefix . $conflict);
                }
                [
                    $paid,
                    $method,
                    $amountCents,
                    $paymentDestination,
                    $paymentError,
                ] = (array) $paymentContext($clinicId, $procedureId, $index);
                if ($paymentError) {
                    throw new RuntimeException($prefix . (string) $paymentError);
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
                        $notes,
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
                $ids[] = $appointmentId;
            }
            return $ids;
        });
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
        $ids = $this->createAppointmentBatch(
            $clinicId,
            $patientId,
            $doctorUserId,
            [[
                'start_at' => $startAt,
                'end_at' => $endAt,
                'procedure_id' => $procedureId,
                'notes' => $notes,
            ]],
            $userId,
            $durationMessage,
            static fn(...$arguments): string => '',
            $conflictMessage,
            static function (int $batchClinicId, int $batchProcedureId, int $index) use ($paymentContext): array {
                return (array) $paymentContext($batchClinicId, $batchProcedureId);
            },
            $syncFinancial,
        );
        return (int) ($ids[0] ?? 0);
    }
}
