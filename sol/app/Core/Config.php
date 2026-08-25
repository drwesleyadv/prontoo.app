<?php
declare(strict_types=1);
namespace Pro\Core;

final class Config
{
    private static string $root;
    private static array $values = [];

    public static function boot(string $root): void
    {
        self::$root = rtrim($root, '/');
        self::$values = [
            'symbol' => 'SOLUSDT',
            'binance_klines' => 'https://api.binance.com/api/v3/klines',
            'solana_rpc' => 'https://api.mainnet-beta.solana.com',
            'jupiter_portfolio_url' => 'https://api.jup.ag/portfolio/v1/positions',
            'jupiter_portfolio_cache_seconds' => 25,
            'jupiter_portfolio_stale_seconds' => 900,
            'max_30m_candles' => 120000,
            'feature_window' => 48,
            'prediction_horizon_30m' => 1,
            'min_train_rows' => 240,
            'walk_windows' => 6,
            'trade_fee' => 0.001,
            'slippage' => 0.0007,
            'decision_threshold' => 0.57,
            'decision_margin' => 0.12,
            'min_policy_f1' => 0.34,
            'theoretical_position_fraction' => 1.0,
            'solusdt_first_ts' => strtotime('2020-08-11 00:00:00'),
            'historical_fetch_chunk_seconds' => 2592000,
            'replay_blocks_per_run' => 900,
            'replay_max_seconds' => 18,
            'replay_policy_stride_blocks' => 720,
            'wallet_lookup_limit_per_minute' => 12,
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$values[$key] ?? $default;
    }

    public static function root(): string
    {
        return self::$root;
    }

    public static function path(string $relative): string
    {
        return self::$root . '/' . ltrim($relative, '/');
    }
}
