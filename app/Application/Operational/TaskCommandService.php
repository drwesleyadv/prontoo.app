<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

use Closure;

final class TaskCommandService
{
    public function __construct(private OperationalUseCaseService $data)
    {
    }

    public function createWorkflowTask(
        int $clinicId,
        string $title,
        string $targetScope,
        ?string $targetRole,
        ?int $targetUserId,
        ?int $assignedTo,
        ?string $dueAt,
        string $description,
        ?int $patientLinkId,
        ?int $appointmentId,
        string $sourceEvent,
        string $sourceEntity,
        string $sourceEntityId,
        ?int $createdByUserId,
        Closure $taskEvent,
    ): array {
        $existing = (int) ($this->data->scalar(
            'operational.tasks_notices.02.create_workflow_task.02',
            [$clinicId, $sourceEvent, $sourceEntity, $sourceEntityId],
        ) ?? 0);
        if ($existing > 0) {
            return ['task_id' => $existing, 'created' => false];
        }
        $taskId = (int) $this->data->atomic(function () use (
            $clinicId,
            $title,
            $targetScope,
            $targetRole,
            $targetUserId,
            $assignedTo,
            $dueAt,
            $description,
            $patientLinkId,
            $appointmentId,
            $sourceEvent,
            $sourceEntity,
            $sourceEntityId,
            $createdByUserId,
        ): int {
            $this->data->result(
                'operational.tasks_notices.02.create_workflow_task.03',
                [
                    $clinicId,
                    $title,
                    $targetScope,
                    $targetRole,
                    $targetUserId,
                    $assignedTo,
                    $dueAt,
                    $createdByUserId,
                ],
            );
            $newTaskId = $this->data->lastInsertId();
            $this->data->result(
                'operational.tasks_notices.02.create_workflow_task.04',
                [
                    $newTaskId,
                    $clinicId,
                    $description,
                    $patientLinkId,
                    $appointmentId,
                    $sourceEvent,
                    $sourceEntity,
                    $sourceEntityId,
                ],
            );
            return $newTaskId;
        });
        $taskEvent(
            $clinicId,
            $taskId,
            'criada',
            $createdByUserId,
            null,
            'aberta',
            'Tarefa automática criada pelo fluxo operacional.',
        );
        return ['task_id' => $taskId, 'created' => true];
    }
}
