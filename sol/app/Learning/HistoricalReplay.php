<?php
declare(strict_types=1);
namespace Pro\Learning;

use Pro\Core\Config;
use Pro\Core\Storage;
use Pro\Decision\DecisionPolicy;
use Pro\Features\FeaturePipeline;
use Pro\Features\MarketRegime;

final class HistoricalReplay
{
    private const FILE = 'cache/runtime/learning-progress.json';
    private const MODEL = 'cache/model/replay-policy.json';
    private const INITIAL_USD = 1000.0;

    public static function run(array $candles, ?int $maxBlocks = null, ?int $maxSeconds = null): array
    {
        $candles = self::clean($candles);
        $window = (int)Config::get('feature_window', 48);
        $horizon = max(1, (int)Config::get('prediction_horizon_30m', 1));
        $target = count($candles) - 1 - $horizon;
        if ($target < $window) return self::save(self::emptyProgress($candles));

        $state = self::progress();
        $cursor = max($window, min((int)($state['next_index'] ?? $window), $target));
        $limit = max(1, $maxBlocks ?? (int)Config::get('replay_blocks_per_run', 900));
        $seconds = max(1, $maxSeconds ?? (int)Config::get('replay_max_seconds', 18));
        $started = microtime(true);
        $processed = 0;
        $first = $window;
        $total = max(1, $target - $first + 1);
        $stride = max(120, (int)Config::get('replay_policy_stride_blocks', 720));
        $bundle = Storage::read(self::MODEL, []);
        $lastTrain = (int)($state['last_policy_update_index'] ?? 0);

        while ($cursor <= $target && $processed < $limit && microtime(true) - $started < $seconds) {
            if (!$bundle || $cursor - $lastTrain >= $stride || $cursor === $target) {
                $trained = self::train(array_slice($candles, 0, $cursor + 1), $window, $horizon, $cursor, $target, $first);
                if ($trained) {
                    $bundle = $trained;
                    $lastTrain = $cursor;
                    Storage::write(self::MODEL, $bundle);
                }
            }
            $cursor++;
            $processed++;
        }

        $doneIndex = min($target, max($first, $cursor - 1));
        $done = max(0, $doneIndex - $first + 1);
        $pct = min(100.0, max(0.0, $done / $total * 100.0));
        $progress = [
            'status' => $doneIndex >= $target ? 'current' : 'learning',
            'is_current' => $doneIndex >= $target,
            'cursor_index' => $doneIndex,
            'next_index' => min($target, $cursor),
            'cursor_ts' => (int)$candles[$doneIndex]['t'],
            'cursor_label' => self::label((int)$candles[$doneIndex]['t']),
            'target_index' => $target,
            'target_ts' => (int)$candles[$target]['t'],
            'target_label' => self::label((int)$candles[$target]['t']),
            'progress_percent' => round($pct, 4),
            'processed_30m_blocks' => $done,
            'processed_this_run' => $processed,
            'total_30m_blocks' => $total,
            'last_policy_update_index' => $lastTrain,
            'last_policy_update_ts' => (int)($bundle['historical_cursor_ts'] ?? 0),
            'last_policy_update_label' => !empty($bundle['historical_cursor_ts']) ? self::label((int)$bundle['historical_cursor_ts']) : null,
            'last_policy_rows' => (int)($bundle['rows'] ?? 0),
            'last_policy_validation' => $bundle['validation'] ?? null,
            'active_policy' => $bundle['active_policy'] ?? null,
            'partial_backtest' => $bundle['validation']['policy_backtest'] ?? null,
            'learning_phase' => $pct < 50 ? 'phase_1_exploratory' : 'phase_2_adaptive_rigor',
            'updated_at' => time(),
        ];
        return self::save($progress);
    }

    public static function progress(): array
    {
        $value = Storage::read(self::FILE, []);
        return is_array($value) ? $value : [];
    }

    public static function isCurrentFor(array $candles): bool
    {
        $candles = self::clean($candles);
        if (!$candles) return false;
        $progress = self::progress();
        if (!($progress['is_current'] ?? false)) return false;
        $horizon = max(1, (int)Config::get('prediction_horizon_30m', 1));
        $target = count($candles) - 1 - $horizon;
        return $target >= 0 && (int)($progress['cursor_ts'] ?? 0) >= (int)$candles[$target]['t'];
    }

    private static function train(array $candles, int $window, int $horizon, int $cursor, int $target, int $first): ?array
    {
        $dataset = FeaturePipeline::dataset($candles, $window, $horizon, [OperationalLabeler::class, 'label']);
        $rows = count($dataset['Y']);
        if ($rows < (int)Config::get('min_train_rows', 240)) return null;
        $validation = WalkForwardBacktester::validate($dataset['X'], $dataset['Y'], $dataset['meta'], (int)Config::get('walk_windows', 6));
        $pct = ($cursor - $first + 1) / max(1, $target - $first + 1) * 100.0;
        $policy = DecisionPolicy::adaptivePolicy($validation['optimized_policy'] ?? [], [
            'replay_progress_percent' => $pct,
            'closed_trades' => (int)($validation['policy_backtest']['closed_trades'] ?? 0),
            'win_rate' => (float)($validation['policy_backtest']['win_rate'] ?? 0),
            'max_drawdown' => (float)($validation['policy_backtest']['max_drawdown'] ?? 0),
        ]);
        $validation['adaptive_policy'] = $policy;
        return [
            'created_at' => time(),
            'historical_cursor_index' => $cursor,
            'historical_cursor_ts' => (int)$candles[count($candles) - 1]['t'],
            'rows' => $rows,
            'validation' => $validation,
            'active_policy' => $policy,
            'feature_names' => $dataset['names'],
            'protocol' => 'progressive_prefix_walk_forward',
        ];
    }

    private static function clean(array $candles): array
    {
        $map = [];
        $ordered = true;
        $last = -1;
        foreach ($candles as $row) {
            if (!is_array($row) || !isset($row['t'], $row['o'], $row['h'], $row['l'], $row['c'])) continue;
            $t = (int)$row['t'];
            if ($t < $last) $ordered = false;
            $map[$t] = ['t'=>$t,'o'=>(float)$row['o'],'h'=>(float)$row['h'],'l'=>(float)$row['l'],'c'=>(float)$row['c'],'v'=>(float)($row['v'] ?? 0)];
            $last = $t;
        }
        if (!$ordered) ksort($map, SORT_NUMERIC);
        return array_values($map);
    }

    private static function emptyProgress(array $candles): array
    {
        $last = $candles ? (int)$candles[count($candles) - 1]['t'] : time();
        return ['status'=>'waiting_history','is_current'=>false,'cursor_index'=>0,'next_index'=>(int)Config::get('feature_window',48),'cursor_ts'=>0,'cursor_label'=>'aguardando candles suficientes','target_ts'=>$last,'target_label'=>self::label($last),'progress_percent'=>0.0,'processed_30m_blocks'=>0,'total_30m_blocks'=>0,'updated_at'=>time()];
    }

    private static function save(array $progress): array
    {
        Storage::write(self::FILE, $progress);
        return $progress;
    }

    private static function label(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }
}