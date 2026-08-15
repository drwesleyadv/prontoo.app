<?php
declare(strict_types=1);

namespace Prontoo\Domain\Health;

final class HealthEvidenceBuilder
{
    private function __construct()
    {
    }

    public static function build(array $raw, ?array $previous = null): array
    {
        $now = (string) ($raw['observed_at'] ?? gmdate('c'));
        $signals = [];
        $signals[] = self::booleanSignal('availability.database', 'availability', $raw['database'] ?? null, $now, 90, true);
        $storage = (array) ($raw['storage'] ?? []);
        $signals[] = HealthEvidencePolicy::signal(
            'availability.storage',
            'availability',
            self::booleanState(array_key_exists('ok', $storage) ? (bool) $storage['ok'] : null),
            $now,
            90,
            $storage['free_bytes'] ?? null,
            [],
            'runtime',
            true,
        );
        $openErrors = self::nullableInt($raw['open_errors'] ?? null);
        $signals[] = HealthEvidencePolicy::signal(
            'availability.runtime_errors',
            'availability',
            $openErrors === null
                ? HealthEvidencePolicy::STATE_UNKNOWN
                : ($openErrors > 0 ? HealthEvidencePolicy::STATE_ATTENTION : HealthEvidencePolicy::STATE_OK),
            $now,
            90,
            $openErrors,
        );

        $scopeLogic = (array) ($raw['scope_logic'] ?? []);
        $scopeContext = (array) ($raw['scope_context'] ?? []);
        $signals[] = HealthEvidencePolicy::signal(
            'security.scope_logic',
            'security',
            self::resultState($scopeLogic),
            $now,
            300,
            null,
            $scopeLogic,
            'runtime',
            true,
        );
        $signals[] = HealthEvidencePolicy::signal(
            'security.scope_context',
            'security',
            self::resultState($scopeContext),
            $now,
            300,
            null,
            $scopeContext,
            'runtime',
            true,
        );
        foreach ([
            'login_locks' => 'security.login_locks',
            'scope_alerts_24h' => 'security.scope_alerts',
        ] as $rawKey => $id) {
            $count = self::nullableInt($raw[$rawKey] ?? null);
            $signals[] = HealthEvidencePolicy::signal(
                $id,
                'security',
                $count === null
                    ? HealthEvidencePolicy::STATE_UNKNOWN
                    : ($count > 0 ? HealthEvidencePolicy::STATE_ATTENTION : HealthEvidencePolicy::STATE_OK),
                $now,
                90,
                $count,
            );
        }

        $auditChain = (array) ($raw['audit_chain'] ?? []);
        $signals[] = HealthEvidencePolicy::signal(
            'integrity.audit_chain',
            'integrity',
            self::resultState($auditChain),
            $now,
            300,
            $auditChain['checked'] ?? 0,
            $auditChain,
            'runtime',
            true,
        );
        $integrityAlerts = self::nullableInt($raw['integrity_alerts'] ?? null);
        $signals[] = HealthEvidencePolicy::signal(
            'integrity.audit_sample',
            'integrity',
            $integrityAlerts === null
                ? HealthEvidencePolicy::STATE_UNKNOWN
                : ($integrityAlerts > 0 ? HealthEvidencePolicy::STATE_FAIL : HealthEvidencePolicy::STATE_OK),
            $now,
            300,
            $integrityAlerts,
            [],
            'runtime',
            true,
        );
        $mutation = (array) ($raw['mutation_invariant'] ?? []);
        $signals[] = HealthEvidencePolicy::signal(
            'integrity.mutation_invariant',
            'integrity',
            self::resultState($mutation),
            $now,
            300,
            null,
            $mutation,
            'runtime',
            true,
        );
        $version = (array) ($raw['version_contract'] ?? []);
        $signals[] = HealthEvidencePolicy::signal(
            'integrity.version_contract',
            'integrity',
            self::resultState($version),
            $now,
            300,
            $version['version'] ?? ($raw['version'] ?? ''),
            $version,
            'runtime',
            true,
        );

        $signals[] = self::performanceSignal((array) ($raw['performance'] ?? []), $now);
        foreach (self::continuitySignals((array) ($raw['maestro'] ?? []), $now) as $signal) {
            $signals[] = $signal;
        }

        $checks = [
            'database' => ($raw['database'] ?? null) === true,
            'storage' => ($storage['ok'] ?? null) === true,
            'storage_free_bytes' => $storage['free_bytes'] ?? null,
            'open_errors' => max(0, (int) ($openErrors ?? 0)),
            'login_locks' => max(0, (int) (self::nullableInt($raw['login_locks'] ?? null) ?? 0)),
            'scope_alerts_24h' => max(0, (int) (self::nullableInt($raw['scope_alerts_24h'] ?? null) ?? 0)),
            'scope_guard_logic' => $scopeLogic,
            'scope_guard_context' => $scopeContext,
            'integrity_alerts' => $integrityAlerts,
            'audit_chain' => $auditChain,
            'version' => (string) ($version['version'] ?? ($raw['version'] ?? '')),
            'version_contract' => $version,
        ];
        $snapshot = HealthEvidencePolicy::snapshot($signals, $previous);
        $checks['ok'] = (string) ($snapshot['state_code'] ?? '') === HealthEvidencePolicy::STATE_OK;
        return HealthEvidencePolicy::withCompatibility($snapshot, $checks);
    }

    private static function performanceSignal(array $context, string $now): array
    {
        if (empty($context['available'])) {
            return HealthEvidencePolicy::signal('performance.runtime', 'performance', HealthEvidencePolicy::STATE_UNKNOWN, $now, 1200, null, $context, 'telemetry');
        }
        $current = (array) ($context['current'] ?? []);
        $previous = (array) ($context['previous'] ?? []);
        $requests = (int) ($current['requests'] ?? 0);
        if ($requests === 0) {
            return HealthEvidencePolicy::signal('performance.runtime', 'performance', HealthEvidencePolicy::STATE_UNKNOWN, (string) ($context['observed_at'] ?? $now), 1200, null, $context, 'telemetry');
        }
        $state = HealthEvidencePolicy::STATE_OK;
        $failureRate = (float) ($current['failure_rate'] ?? 0.0);
        $failures = (int) ($current['failures'] ?? 0);
        if ($failures >= 3 && $failureRate >= 0.15) {
            $state = HealthEvidencePolicy::STATE_FAIL;
        } elseif ($failures >= 2 && $failureRate >= 0.05) {
            $state = HealthEvidencePolicy::STATE_ATTENTION;
        }
        $average = $current['average_ms'] ?? null;
        $previousAverage = $previous['average_ms'] ?? null;
        if ($requests >= 5 && (int) ($previous['requests'] ?? 0) >= 5 && is_numeric($average) && is_numeric($previousAverage)) {
            if ((float) $average >= 1500.0 && (float) $average >= (float) $previousAverage * 2.0) {
                $state = HealthEvidencePolicy::STATE_FAIL;
            } elseif ((float) $average >= 750.0 && (float) $average >= (float) $previousAverage * 1.5 && $state === HealthEvidencePolicy::STATE_OK) {
                $state = HealthEvidencePolicy::STATE_ATTENTION;
            }
        }
        return HealthEvidencePolicy::signal(
            'performance.runtime',
            'performance',
            $state,
            (string) ($context['observed_at'] ?? $now),
            1200,
            $current,
            ['previous' => $previous],
            'telemetry',
        );
    }

    private static function continuitySignals(array $context, string $now): array
    {
        if (empty($context['available']) || !is_array($context['state'] ?? null)) {
            return [
                HealthEvidencePolicy::signal('continuity.maestro', 'continuity', HealthEvidencePolicy::STATE_UNKNOWN, $now, 1200, null, $context, 'scheduled', true),
                HealthEvidencePolicy::signal('continuity.canary', 'continuity', HealthEvidencePolicy::STATE_UNKNOWN, $now, 1200, null, [], 'scheduled', true),
            ];
        }
        $cycle = (array) $context['state'];
        $finishedAt = mb_trim((string) ($cycle['finished_at_utc'] ?? ''));
        $finishedAt = $finishedAt !== '' ? $finishedAt : $now;
        $cycleState = match ((string) ($cycle['status'] ?? '')) {
            'healthy' => HealthEvidencePolicy::STATE_OK,
            'attention' => HealthEvidencePolicy::STATE_ATTENTION,
            'failed' => HealthEvidencePolicy::STATE_FAIL,
            default => HealthEvidencePolicy::STATE_UNKNOWN,
        };
        $canary = (array) ($cycle['health_canary'] ?? []);
        $canaryState = !array_key_exists('ok', $canary)
            ? HealthEvidencePolicy::STATE_UNKNOWN
            : (!empty($canary['ok']) ? HealthEvidencePolicy::STATE_OK : HealthEvidencePolicy::STATE_FAIL);
        return [
            HealthEvidencePolicy::signal('continuity.maestro', 'continuity', $cycleState, $finishedAt, 1200, $cycle['duration_ms'] ?? null, [], 'scheduled', true),
            HealthEvidencePolicy::signal(
                'continuity.canary',
                'continuity',
                $canaryState,
                (string) ($canary['checked_at'] ?? $finishedAt),
                1200,
                $canary['duration_ms'] ?? null,
                (array) ($canary['checks'] ?? []),
                'scheduled',
                true,
            ),
        ];
    }

    private static function booleanSignal(
        string $id,
        string $dimension,
        mixed $value,
        string $observedAt,
        int $ttl,
        bool $essential,
    ): array {
        return HealthEvidencePolicy::signal(
            $id,
            $dimension,
            self::booleanState(is_bool($value) ? $value : null),
            $observedAt,
            $ttl,
            $value,
            [],
            'runtime',
            $essential,
        );
    }

    private static function booleanState(?bool $value): string
    {
        return $value === null
            ? HealthEvidencePolicy::STATE_UNKNOWN
            : ($value ? HealthEvidencePolicy::STATE_OK : HealthEvidencePolicy::STATE_FAIL);
    }

    private static function resultState(array $result): string
    {
        if (!array_key_exists('ok', $result)) {
            return HealthEvidencePolicy::STATE_UNKNOWN;
        }
        if (!empty($result['unknown'])) {
            return HealthEvidencePolicy::STATE_UNKNOWN;
        }
        return !empty($result['ok']) ? HealthEvidencePolicy::STATE_OK : HealthEvidencePolicy::STATE_FAIL;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : max(0, (int) $value);
    }
}
