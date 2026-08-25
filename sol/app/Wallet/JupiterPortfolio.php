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
        if ($address === '') return ['perps_status' => 'unavailable'];
        $now = time();
        $cachePath = 'cache/runtime/jupiter-portfolio-' . hash('sha256', $address) . '.json';
        $cached = Storage::read($cachePath, []);
        $fetchedAt = (int)($cached['fetched_at'] ?? 0);
        $freshFor = (int)Config::get('jupiter_portfolio_cache_seconds', 25);
        if ($fetchedAt > 0 && $now - $fetchedAt <= $freshFor) {
            $cached['perps_status'] = 'cached';
            return $cached;
        }

        $base = (string)Config::get('jupiter_portfolio_url');
        $base = preg_replace('#/+$#u', '', $base) ?? $base;
        $raw = HttpClient::get($base . '/' . rawurlencode($address), 8, 1);
        if (is_string($raw)) {
            $payload = json_decode($raw, true);
            if (is_array($payload) && isset($payload['elements']) && is_array($payload['elements'])) {
                $summary = self::normalize($payload, $now);
                Storage::write($cachePath, $summary);
                return $summary;
            }
        }

        $staleFor = (int)Config::get('jupiter_portfolio_stale_seconds', 900);
        if ($fetchedAt > 0 && $now - $fetchedAt <= $staleFor) {
            $cached['perps_status'] = 'stale';
            return $cached;
        }
        return ['perps_status' => 'unavailable'];
    }

    private static function normalize(array $payload, int $now): array
    {
        $equity = 0.0;
        $pnl = 0.0;
        $notional = 0.0;
        $collateral = 0.0;
        $positions = [];
        foreach ($payload['elements'] as $element) {
            if (!is_array($element) || ($element['type'] ?? '') !== 'leverage') continue;
            $data = is_array($element['data'] ?? null) ? $element['data'] : [];
            $equity += self::number($data['value'] ?? $element['value'] ?? 0);
            $platform = (string)($element['platformId'] ?? 'jupiter');
            $isolated = is_array($data['isolated'] ?? null) ? $data['isolated'] : [];
            $isolatedRows = is_array($isolated['positions'] ?? null) ? $isolated['positions'] : [];
            foreach ($isolatedRows as $row) {
                if (!is_array($row)) continue;
                $collateral += self::number($row['collateralValue'] ?? 0);
                self::appendPosition($positions, $row, $platform, 'isolated', $pnl, $notional);
            }
            $cross = is_array($data['cross'] ?? null) ? $data['cross'] : [];
            $collateral += self::number($cross['collateralValue'] ?? 0);
            $crossRows = is_array($cross['positions'] ?? null) ? $cross['positions'] : [];
            foreach ($crossRows as $row) {
                if (!is_array($row)) continue;
                self::appendPosition($positions, $row, $platform, 'cross', $pnl, $notional);
            }
        }
        $sourceMs = self::number($payload['date'] ?? 0);
        return [
            'perps_status' => 'live',
            'perps_equity_usd' => $equity,
            'perps_unrealized_pnl_usd' => $pnl,
            'perps_notional_usd' => $notional,
            'perps_collateral_usd' => $collateral,
            'perps_position_count' => count($positions),
            'perps_positions' => $positions,
            'perps_updated_at' => $sourceMs > 0 ? (int)floor($sourceMs / 1000) : $now,
            'fetched_at' => $now,
        ];
    }

    private static function appendPosition(array &$positions, array $row, string $platform, string $marginMode, float &$pnl, float &$notional): void
    {
        $positionPnl = self::number($row['pnlValue'] ?? 0);
        $sizeValue = self::number($row['sizeValue'] ?? 0);
        $pnl += $positionPnl;
        $notional += $sizeValue;
        $positions[] = [
            'platform' => $platform,
            'margin_mode' => $marginMode,
            'name' => (string)($row['name'] ?? ''),
            'address' => (string)($row['address'] ?? ''),
            'side' => (string)($row['side'] ?? ''),
            'equity_usd' => self::number($row['value'] ?? 0),
            'collateral_usd' => $marginMode === 'isolated' ? self::number($row['collateralValue'] ?? 0) : null,
            'notional_usd' => $sizeValue,
            'pnl_usd' => $positionPnl,
            'entry_price' => self::number($row['entryPrice'] ?? 0),
            'mark_price' => self::number($row['markPrice'] ?? 0),
            'liquidation_price' => self::number($row['liquidationPrice'] ?? 0),
            'leverage' => self::number($row['leverage'] ?? 0),
        ];
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float)$value : 0.0;
    }
}
