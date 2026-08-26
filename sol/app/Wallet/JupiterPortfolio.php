<?php
declare(strict_types=1);
namespace Pro\Wallet;

use Pro\Core\Config;
use Pro\Core\HttpClient;
use Pro\Core\Storage;

final class JupiterPortfolio
{
    public static function leverage(string $address): array
    {
        if ($address === '') return self::unavailable();
        $now = time();
        $cachePath = 'cache/runtime/jupiter-perps-' . hash('sha256', $address) . '.json';
        $cached = Storage::read($cachePath, []);
        $fetchedAt = (int)($cached['fetched_at'] ?? 0);
        $freshFor = (int)Config::get('jupiter_perps_cache_seconds', 25);
        if ($fetchedAt > 0 && $now - $fetchedAt <= $freshFor) {
            $cached['perps_status'] = 'cached';
            return $cached;
        }

        $base = (string)Config::get('jupiter_perps_url');
        $query = http_build_query(['walletAddress' => $address], '', '&', PHP_QUERY_RFC3986);
        $raw = HttpClient::get($base . '?' . $query, 8, 1, ['x-client-platform: sol-dashboard']);
        if (is_string($raw)) {
            $payload = json_decode($raw, true);
            if (is_array($payload) && isset($payload['dataList']) && is_array($payload['dataList'])) {
                $summary = self::normalize($payload['dataList'], $now);
                Storage::write($cachePath, $summary);
                return $summary;
            }
        }

        $staleFor = (int)Config::get('jupiter_perps_stale_seconds', 900);
        if ($fetchedAt > 0 && $now - $fetchedAt <= $staleFor) {
            $cached['perps_status'] = 'stale';
            return $cached;
        }
        return self::unavailable();
    }

    private static function normalize(array $rows, int $now): array
    {
        $equity = 0.0;
        $pnl = 0.0;
        $notional = 0.0;
        $collateral = 0.0;
        $fees = 0.0;
        $solEntryWeighted = 0.0;
        $solEntryWeight = 0.0;
        $updatedAt = 0;
        $positions = [];

        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $asset = strtoupper((string)($row['asset'] ?? ''));
            $positionPnl = self::microUsd($row['pnlAfterFeesUsd'] ?? 0);
            $positionNotional = self::microUsd($row['sizeUsd'] ?? 0);
            $positionCollateral = self::microUsd($row['collateralUsd'] ?? 0);
            $positionEquity = $positionCollateral + $positionPnl;
            $positionFees = self::microUsd($row['totalFeesUsd'] ?? 0);
            $entryPrice = self::microUsd($row['entryPriceUsd'] ?? 0);
            $markPrice = self::microUsd($row['markPriceUsd'] ?? 0);
            $liquidationPrice = self::microUsd($row['liquidationPriceUsd'] ?? 0);
            $rowUpdatedAt = self::timestamp($row['updatedTime'] ?? 0);
            $updatedAt = max($updatedAt, $rowUpdatedAt);

            $equity += $positionEquity;
            $pnl += $positionPnl;
            $notional += $positionNotional;
            $collateral += $positionCollateral;
            $fees += $positionFees;

            if ($asset === 'SOL' && $entryPrice > 0 && $positionNotional > 0) {
                $solEntryWeighted += $entryPrice * $positionNotional;
                $solEntryWeight += $positionNotional;
            }

            $positions[] = [
                'position_pubkey' => (string)($row['positionPubkey'] ?? ''),
                'asset' => $asset,
                'side' => strtolower((string)($row['side'] ?? '')),
                'leverage' => self::number($row['leverage'] ?? 0),
                'equity_usd' => $positionEquity,
                'collateral_usd' => $positionCollateral,
                'notional_usd' => $positionNotional,
                'pnl_usd' => $positionPnl,
                'pnl_percent' => self::number($row['pnlAfterFeesPct'] ?? 0),
                'fees_usd' => $positionFees,
                'entry_price' => $entryPrice,
                'mark_price' => $markPrice,
                'liquidation_price' => $liquidationPrice,
                'updated_at' => $rowUpdatedAt,
            ];
        }

        return [
            'perps_status' => 'live',
            'perps_equity_usd' => $equity,
            'perps_unrealized_pnl_usd' => $pnl,
            'perps_notional_usd' => $notional,
            'perps_collateral_usd' => $collateral,
            'perps_fees_usd' => $fees,
            'perps_position_count' => count($positions),
            'perps_average_entry_price_usd' => $solEntryWeight > 0 ? $solEntryWeighted / $solEntryWeight : 0.0,
            'perps_positions' => $positions,
            'perps_updated_at' => $updatedAt > 0 ? $updatedAt : $now,
            'fetched_at' => $now,
        ];
    }

    private static function unavailable(): array
    {
        return ['perps_status' => 'unavailable'];
    }

    private static function microUsd(mixed $value): float
    {
        return is_numeric($value) ? ((float)$value) / 1e6 : 0.0;
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float)$value : 0.0;
    }

    private static function timestamp(mixed $value): int
    {
        if (!is_numeric($value)) return 0;
        $timestamp = (int)$value;
        return $timestamp > 20000000000 ? intdiv($timestamp, 1000) : $timestamp;
    }
}
