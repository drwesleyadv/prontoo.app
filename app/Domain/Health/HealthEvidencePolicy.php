<?php
declare(strict_types=1);

namespace Prontoo\Domain\Health;

final class HealthEvidencePolicy
{
    public const SCHEMA = 'prontoo.health-evidence.v1';
    public const STATE_OK = 'ok';
    public const STATE_ATTENTION = 'attention';
    public const STATE_FAIL = 'fail';
    public const STATE_UNKNOWN = 'unknown';

    private function __construct()
    {
    }

    public static function signal(
        string $id,
        string $dimension,
        string $state,
        string $observedAt,
        int $ttlSeconds,
        mixed $value = null,
        array $evidence = [],
        string $source = 'runtime',
        bool $essential = false,
    ): array {
        $observedTs = strtotime($observedAt);
        $observedTs = $observedTs === false ? time() : $observedTs;
        $state = self::state($state);
        return [
            'id' => $id,
            'dimension' => $dimension,
            'state' => $state,
            'observed_at' => gmdate('c', $observedTs),
            'valid_until' => gmdate('c', $observedTs + max(1, $ttlSeconds)),
            'value' => $value,
            'evidence' => $evidence,
            'source' => $source,
            'essential' => $essential,
        ];
    }

    public static function snapshot(
        array $signals,
        ?array $previous = null,
        ?int $now = null,
    ): array {
        $now ??= time();
        $indexed = [];
        foreach ($signals as $signal) {
            if (!is_array($signal) || mb_trim((string) ($signal['id'] ?? '')) === '') {
                continue;
            }
            $signal = self::materializeSignalFreshness($signal, $now);
            $indexed[(string) $signal['id']] = $signal;
        }

        if (isset($indexed['performance.runtime'])) {
            $indexed['performance.runtime'] = self::stabilizeTransient(
                $indexed['performance.runtime'],
                is_array($previous) ? (($previous['signals']['performance.runtime'] ?? null)) : null,
            );
        }

        $dimensions = [];
        foreach ([
            'availability' => 'Disponibilidade',
            'integrity' => 'Integridade',
            'security' => 'Isolamento e segurança',
            'performance' => 'Desempenho',
            'continuity' => 'Continuidade',
        ] as $id => $label) {
            $dimensions[$id] = self::dimension($id, $label, $indexed);
        }

        $globalState = self::globalState($dimensions, $indexed);
        $observedAt = self::latestObservedAt($indexed);
        return [
            'schema' => self::SCHEMA,
            'state' => match ($globalState) {
                self::STATE_FAIL => 'Crítico',
                self::STATE_ATTENTION, self::STATE_UNKNOWN => 'Atenção',
                default => 'Operacional',
            },
            'state_code' => $globalState,
            'critical' => $globalState === self::STATE_FAIL,
            'observed_at' => $observedAt,
            'freshness_label' => self::freshnessLabel($observedAt, $now),
            'signals' => $indexed,
            'dimensions' => $dimensions,
            'updated_at' => $observedAt !== '' ? date('d/m/Y, H\hi', strtotime($observedAt) ?: $now) : '',
            'problems' => self::problemLabels($indexed),
        ];
    }

    public static function withCompatibility(array $snapshot, array $checks): array
    {
        $snapshot['checks'] = $checks;
        $snapshot['counts'] = [
            'open_errors' => (int) ($checks['open_errors'] ?? 0),
            'login_locks' => (int) ($checks['login_locks'] ?? 0),
            'scope_alerts_24h' => (int) ($checks['scope_alerts_24h'] ?? 0),
        ];
        $snapshot['components'] = [
            'database' => self::legacyOk($snapshot, 'availability.database'),
            'storage' => self::legacyOk($snapshot, 'availability.storage'),
            'integrity' => self::legacyOk($snapshot, 'integrity.audit_chain')
                && self::legacyOk($snapshot, 'integrity.mutation_invariant'),
            'security' => self::legacyOk($snapshot, 'security.scope_logic')
                && self::legacyOk($snapshot, 'security.scope_context')
                && (int) ($checks['login_locks'] ?? 0) === 0
                && (int) ($checks['scope_alerts_24h'] ?? 0) === 0,
            'version' => self::legacyOk($snapshot, 'integrity.version_contract'),
        ];
        return $snapshot;
    }

    public static function structuralActions(array $snapshot): array
    {
        $actions = [];
        $dimensions = (array) ($snapshot['dimensions'] ?? []);
        $map = [
            'availability' => ['Confiabilidade', 'A disponibilidade da plataforma exige verificação.', 'health_and_safety', 'admin_health'],
            'integrity' => ['Integridade', 'Uma evidência de integridade falhou ou não pôde ser renovada.', 'verified_user', 'admin_integrity'],
            'security' => ['Isolamento e segurança', 'Uma fronteira de segurança precisa de revisão.', 'shield_lock', 'admin_security'],
            'continuity' => ['Continuidade', 'O heartbeat ou o canário do Maestro não está confiável.', 'monitor_heart', 'admin_health'],
            'performance' => ['Desempenho', 'A degradação persistiu por janelas consecutivas.', 'speed', 'admin_performance'],
        ];
        foreach ($map as $id => [$title, $body, $icon, $route]) {
            $state = (string) (($dimensions[$id]['state'] ?? self::STATE_OK));
            if (!in_array($state, [self::STATE_ATTENTION, self::STATE_FAIL, self::STATE_UNKNOWN], true)) {
                continue;
            }
            if ($id === 'performance' && $state === self::STATE_UNKNOWN) {
                continue;
            }
            $actions[] = [
                'icon' => $icon,
                'title' => $title,
                'body' => $body,
                'time' => $id === 'performance' ? 'Desempenho' : 'Confiabilidade',
                'route' => $route,
            ];
        }
        return $actions;
    }

    public static function state(string $state): string
    {
        return in_array($state, [
            self::STATE_OK,
            self::STATE_ATTENTION,
            self::STATE_FAIL,
            self::STATE_UNKNOWN,
        ], true) ? $state : self::STATE_UNKNOWN;
    }

    private static function materializeSignalFreshness(array $signal, int $now): array
    {
        $state = self::state((string) ($signal['state'] ?? self::STATE_UNKNOWN));
        $validUntil = strtotime((string) ($signal['valid_until'] ?? ''));
        if ($validUntil === false || $validUntil < $now) {
            $signal['state_before_expiry'] = $state;
            $signal['state'] = self::STATE_UNKNOWN;
            $signal['stale'] = true;
        } else {
            $signal['state'] = $state;
            $signal['stale'] = false;
        }
        return $signal;
    }

    private static function stabilizeTransient(array $current, mixed $previous): array
    {
        $candidate = self::state((string) ($current['state'] ?? self::STATE_UNKNOWN));
        $previous = is_array($previous) ? $previous : [];
        $previousStable = self::state((string) ($previous['stable_state'] ?? ($previous['state'] ?? self::STATE_UNKNOWN)));
        $previousCandidate = self::state((string) ($previous['candidate_state'] ?? $previousStable));
        $streak = (int) ($previous['candidate_streak'] ?? 0);

        if ($candidate === self::STATE_UNKNOWN) {
            $current['candidate_state'] = $candidate;
            $current['candidate_streak'] = 0;
            $current['stable_state'] = self::STATE_UNKNOWN;
            $current['state'] = self::STATE_UNKNOWN;
            return $current;
        }

        $streak = $candidate === $previousCandidate ? $streak + 1 : 1;
        $stable = $previousStable;
        if ($candidate === self::STATE_FAIL || $candidate === self::STATE_ATTENTION) {
            if ($streak >= 2) {
                $stable = $candidate;
            }
        } elseif ($candidate === self::STATE_OK) {
            if ($previousStable === self::STATE_UNKNOWN || $streak >= 2) {
                $stable = self::STATE_OK;
            }
        }

        $current['candidate_state'] = $candidate;
        $current['candidate_streak'] = $streak;
        $current['stable_state'] = $stable;
        $current['state'] = $stable;
        return $current;
    }

    private static function dimension(string $id, string $label, array $signals): array
    {
        $states = [];
        $unknown = 0;
        foreach ($signals as $signal) {
            if ((string) ($signal['dimension'] ?? '') !== $id) {
                continue;
            }
            $state = self::state((string) ($signal['state'] ?? self::STATE_UNKNOWN));
            $states[] = $state;
            $unknown += $state === self::STATE_UNKNOWN ? 1 : 0;
        }
        $state = self::STATE_OK;
        foreach ($states as $candidate) {
            if (self::stateRank($candidate) > self::stateRank($state)) {
                $state = $candidate;
            }
        }
        return [
            'id' => $id,
            'label' => $label,
            'state' => $states === [] ? self::STATE_UNKNOWN : $state,
            'signals' => count($states),
            'unknown' => $unknown,
        ];
    }

    private static function globalState(array $dimensions, array $signals): string
    {
        foreach ($signals as $signal) {
            if (!empty($signal['essential']) && (string) ($signal['state'] ?? '') === self::STATE_FAIL) {
                return self::STATE_FAIL;
            }
        }
        foreach (['availability', 'integrity', 'security', 'continuity'] as $id) {
            $state = (string) (($dimensions[$id]['state'] ?? self::STATE_UNKNOWN));
            if ($state === self::STATE_FAIL) {
                return self::STATE_FAIL;
            }
        }
        foreach (['availability', 'integrity', 'security', 'continuity'] as $id) {
            $state = (string) (($dimensions[$id]['state'] ?? self::STATE_UNKNOWN));
            if (in_array($state, [self::STATE_ATTENTION, self::STATE_UNKNOWN], true)) {
                return self::STATE_ATTENTION;
            }
        }
        $performance = (string) (($dimensions['performance']['state'] ?? self::STATE_UNKNOWN));
        return in_array($performance, [self::STATE_ATTENTION, self::STATE_FAIL], true)
            ? self::STATE_ATTENTION
            : self::STATE_OK;
    }

    private static function problemLabels(array $signals): array
    {
        $labels = [];
        foreach ($signals as $signal) {
            $state = (string) ($signal['state'] ?? self::STATE_UNKNOWN);
            if (!in_array($state, [self::STATE_ATTENTION, self::STATE_FAIL, self::STATE_UNKNOWN], true)) {
                continue;
            }
            if ((string) ($signal['dimension'] ?? '') === 'performance' && $state === self::STATE_UNKNOWN) {
                continue;
            }
            $labels[] = (string) ($signal['id'] ?? 'health.unknown');
        }
        return array_values(array_unique($labels));
    }

    private static function latestObservedAt(array $signals): string
    {
        $latest = 0;
        foreach ($signals as $signal) {
            $ts = strtotime((string) ($signal['observed_at'] ?? ''));
            $latest = $ts !== false ? max($latest, $ts) : $latest;
        }
        return $latest > 0 ? gmdate('c', $latest) : '';
    }

    private static function freshnessLabel(string $observedAt, int $now): string
    {
        $ts = strtotime($observedAt);
        if ($ts === false) {
            return 'evidência indisponível';
        }
        $age = max(0, $now - $ts);
        if ($age < 5) {
            return 'verificado agora';
        }
        if ($age < 60) {
            return 'verificado há ' . $age . ' s';
        }
        if ($age < 3600) {
            return 'verificado há ' . (int) floor($age / 60) . ' min';
        }
        return 'verificado há ' . (int) floor($age / 3600) . ' h';
    }

    private static function stateRank(string $state): int
    {
        return match (self::state($state)) {
            self::STATE_FAIL => 4,
            self::STATE_UNKNOWN => 3,
            self::STATE_ATTENTION => 2,
            default => 1,
        };
    }

    private static function legacyOk(array $snapshot, string $signalId): bool
    {
        return (string) (($snapshot['signals'][$signalId]['state'] ?? self::STATE_UNKNOWN)) === self::STATE_OK;
    }
}
