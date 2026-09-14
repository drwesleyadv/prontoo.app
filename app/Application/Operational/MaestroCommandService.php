<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

use Closure;
use Prontoo\Domain\Maestro\MaestroDomainOperations02;

final class MaestroCommandService
{
    public function __construct(private OperationalUseCaseService $data)
    {
    }

    public function grantRuntimeAccess(): void
    {
        $this->data->atomic(function (): void {
            $this->data->result('operational.maestro.01.maestro_grant_runtime_access.01');
            $this->data->result('operational.maestro.01.maestro_grant_runtime_access.02');
            foreach (['view', 'add', 'edit', 'delete'] as $operation) {
                $this->data->result(
                    'operational.maestro.01.maestro_grant_runtime_access.03',
                    [],
                    ['op' => $operation],
                );
            }
            $this->data->result('operational.maestro.01.maestro_grant_runtime_access.04');
        });
    }

    public function updateStats(
        string $key,
        float $duration,
        int $created,
        float $score,
        bool $skipped = false,
    ): void {
        $this->data->atomic(function () use (
            $key,
            $duration,
            $created,
            $score,
            $skipped,
        ): void {
            $previous = $this->data->row(
                'operational.maestro.04.maestro_stats_update.01',
                [$key],
            );
            if ($skipped) {
                $this->data->result(
                    $previous
                        ? 'operational.maestro.04.maestro_stats_update.02'
                        : 'operational.maestro.04.maestro_stats_update.03',
                    $previous ? [$score, $key] : [$key, $score],
                );
                return;
            }
            $hasObservation = $previous !== null && (int) ($previous['run_count'] ?? 0) > 0;
            $durationAverage = (float) MaestroDomainOperations02::maestro_ewma_observation(
                $hasObservation ? (float) $previous['ewma_duration_ms'] : null,
                max(0.0, $duration),
            );
            $yieldAverage = (float) MaestroDomainOperations02::maestro_ewma_observation(
                $hasObservation ? (float) $previous['ewma_yield'] : null,
                max(0, $created),
            );
            $this->data->result(
                $previous
                    ? 'operational.maestro.04.maestro_stats_update.04'
                    : 'operational.maestro.04.maestro_stats_update.05',
                $previous
                    ? [$durationAverage, $yieldAverage, $score, $key]
                    : [$key, $durationAverage, $yieldAverage, $score],
            );
        });
    }

    public function recordJobRun(float $startedAt, array $result): void
    {
        $this->data->result(
            'operational.maestro.05.maestro_supervised_record_job_run.01',
            [
                $startedAt,
                (int) ($result['duration_ms'] ?? 0),
                (int) ($result['rules_seen'] ?? 0),
                (int) ($result['rules_run'] ?? 0),
                (int) ($result['actions_created'] ?? 0),
                (int) ($result['deferred'] ?? 0),
                (int) ($result['errors'] ?? 0),
                !empty($result['success']) ? 1 : 0,
                (float) ($result['load_score'] ?? 0),
                mb_substr((string) ($result['note'] ?? ''), 0, 255),
            ],
        );
    }

    public function createClaimedAction(
        array $rule,
        array $match,
        int $clinicId,
        string $source,
        string $sourceId,
        string $actionKey,
        Closure $createAction,
    ): void {
        $this->data->atomic(function () use (
            $rule,
            $match,
            $clinicId,
            $source,
            $sourceId,
            $actionKey,
            $createAction,
        ): void {
            $created = (array) $createAction($rule, $match);
            $this->data->result(
                'operational.maestro.04.maestro_run_rule_scoped.03',
                [
                    $created['entity'] ?? null,
                    $created['id'] ?? null,
                    (int) ($rule['id'] ?? 0),
                    $source,
                    $sourceId,
                    $actionKey,
                    $clinicId,
                ],
            );
        });
    }

    public function createSupervisedAction(
        int $clinicId,
        array $rule,
        array $match,
        int $ruleId,
        string $actionKey,
        Closure $createAction,
    ): void {
        $this->data->atomic(function () use (
            $clinicId,
            $rule,
            $match,
            $ruleId,
            $actionKey,
            $createAction,
        ): void {
            $created = (array) $createAction($clinicId, $rule, $match);
            $this->data->result(
                'operational.maestro.05.maestro_supervised_run_rule.02',
                [
                    $created['entity'] ?? null,
                    $created['id'] ?? null,
                    $clinicId,
                    $ruleId,
                    (string) ($match['source_entity'] ?? 'registro'),
                    (string) ($match['source_entity_id'] ?? '0'),
                    $actionKey,
                ],
            );
        });
    }
}
