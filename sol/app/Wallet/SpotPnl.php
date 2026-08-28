<?php
declare(strict_types=1);
namespace Pro\Wallet;

use Pro\Core\Config;
use Pro\Core\HttpClient;
use Pro\Core\Storage;

final class SpotPnl
{
    private const USDC_MINT = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';

    public static function snapshot(string $address): array
    {
        if ($address === '') return self::unavailable();
        $path = 'cache/runtime/solana-spot-pnl-' . hash('sha256', $address) . '.json';
        $cached = Storage::read($path, []);
        $now = time();
        $freshFor = (int)Config::get('spot_pnl_cache_seconds', 300);
        if (is_array($cached) && (int)($cached['fetched_at'] ?? 0) > 0 && $now - (int)$cached['fetched_at'] <= $freshFor) {
            $cached['spot_pnl_status'] = 'cached';
            return $cached;
        }

        $computed = self::compute($address);
        if ($computed !== null) {
            $computed['fetched_at'] = $now;
            Storage::write($path, $computed);
            return $computed;
        }
        if (is_array($cached) && $cached) {
            $cached['spot_pnl_status'] = 'stale';
            return $cached;
        }
        return self::unavailable();
    }

    private static function compute(string $address): ?array
    {
        $rpc = (string)Config::get('solana_rpc');
        $limit = (int)Config::get('spot_pnl_history_signatures', 500);
        $signatures = HttpClient::postJson($rpc, [[
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getSignaturesForAddress',
            'params' => [$address, ['limit' => max(1, min(1000, $limit)), 'commitment' => 'finalized']],
        ]], 12, []);
        $rows = $signatures[0]['result'] ?? null;
        if (!is_array($rows)) return null;

        $refs = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['signature']) && ($row['err'] ?? null) === null) $refs[] = ['signature' => (string)$row['signature'], 'block_time' => (int)($row['blockTime'] ?? 0)];
        }
        if (!$refs) return [
            'spot_pnl_status' => 'empty',
            'spot_realized_pnl_usd' => 0.0,
            'spot_unrealized_cost_usd' => 0.0,
            'spot_cost_basis_usd' => 0.0,
            'spot_position_sol' => 0.0,
            'spot_avg_cost_usd' => 0.0,
            'spot_swap_count' => 0,
            'spot_history_complete' => false,
        ];

        $transactions = [];
        foreach (array_chunk($refs, 50) as $chunk) {
            $batch = [];
            foreach ($chunk as $i => $ref) {
                $batch[] = [
                    'jsonrpc' => '2.0', 'id' => $i + 1,
                    'method' => 'getTransaction',
                    'params' => [$ref['signature'], ['encoding' => 'jsonParsed', 'commitment' => 'finalized', 'maxSupportedTransactionVersion' => 0]],
                ];
            }
            $responses = HttpClient::postJson($rpc, $batch, 15, []);
            if (!is_array($responses)) continue;
            foreach ($responses as $response) {
                if (is_array($response) && is_array($response['result'] ?? null)) $transactions[] = $response['result'];
            }
        }
        if (!$transactions) return null;

        usort($transactions, static fn(array $a, array $b): int => ((int)($a['blockTime'] ?? 0)) <=> ((int)($b['blockTime'] ?? 0)));
        $qty = 0.0;
        $cost = 0.0;
        $realized = 0.0;
        $swaps = 0;
        foreach ($transactions as $tx) {
            $event = self::swapEvent($tx, $address);
            if ($event === null) continue;
            $sol = $event['sol'];
            $usdc = $event['usdc'];
            $fee = $event['fee_sol'];
            if ($sol > 0 && $usdc < 0) {
                $received = $sol;
                $spent = -$usdc;
                if ($received <= 0 || $spent <= 0) continue;
                $price = $spent / $received;
                $feeCost = $fee > 0 ? $fee * $price : 0.0;
                $qty += $received;
                $cost += $spent + $feeCost;
                $swaps++;
            } elseif ($sol < 0 && $usdc > 0) {
                $sold = abs($sol) - $fee;
                $proceeds = $usdc;
                if ($sold <= 0 || $proceeds <= 0 || $qty <= 0) continue;
                $avg = $cost / $qty;
                $realized += $proceeds - ($sold * $avg);
                $qty = max(0.0, $qty - $sold);
                $cost = max(0.0, $cost - ($sold * $avg));
                $swaps++;
            }
        }

        return [
            'spot_pnl_status' => 'live',
            'spot_realized_pnl_usd' => $realized,
            'spot_unrealized_cost_usd' => $cost,
            'spot_cost_basis_usd' => $cost,
            'spot_position_sol' => $qty,
            'spot_avg_cost_usd' => $qty > 0 ? $cost / $qty : 0.0,
            'spot_swap_count' => $swaps,
            'spot_history_complete' => count($refs) < $limit,
            'spot_scanned_transactions' => count($refs),
        ];
    }

    private static function swapEvent(array $tx, string $address): ?array
    {
        $meta = $tx['meta'] ?? null;
        $message = $tx['transaction']['message'] ?? null;
        if (!is_array($meta) || !is_array($message) || ($meta['err'] ?? null) !== null) return null;
        $keys = $message['accountKeys'] ?? [];
        $walletIndex = null;
        foreach ($keys as $index => $key) {
            $pubkey = is_array($key) ? (string)($key['pubkey'] ?? '') : (string)$key;
            if ($pubkey === $address) { $walletIndex = (int)$index; break; }
        }
        if ($walletIndex === null) return null;
        $preBalances = $meta['preBalances'] ?? [];
        $postBalances = $meta['postBalances'] ?? [];
        if (!isset($preBalances[$walletIndex], $postBalances[$walletIndex])) return null;
        $sol = ((float)$postBalances[$walletIndex] - (float)$preBalances[$walletIndex]) / 1e9;
        $fee = ((float)($meta['fee'] ?? 0)) / 1e9;
        $preUsdc = self::tokenBalance($meta['preTokenBalances'] ?? [], $address, self::USDC_MINT);
        $postUsdc = self::tokenBalance($meta['postTokenBalances'] ?? [], $address, self::USDC_MINT);
        $usdc = $postUsdc - $preUsdc;
        if (abs($sol) < 1e-7 || abs($usdc) < 1e-5) return null;
        if (($sol > 0 && $usdc < 0) || ($sol < 0 && $usdc > 0)) return ['sol' => $sol + ($sol > 0 ? $fee : 0.0), 'usdc' => $usdc, 'fee_sol' => $fee];
        return null;
    }

    private static function tokenBalance(array $rows, string $owner, string $mint): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            if (!is_array($row) || (string)($row['owner'] ?? '') !== $owner || (string)($row['mint'] ?? '') !== $mint) continue;
            $amount = $row['uiTokenAmount']['uiAmountString'] ?? $row['uiTokenAmount']['uiAmount'] ?? null;
            if (is_numeric($amount)) $total += (float)$amount;
        }
        return $total;
    }

    private static function unavailable(): array
    {
        return [
            'spot_pnl_status' => 'unavailable',
            'spot_realized_pnl_usd' => 0.0,
            'spot_unrealized_cost_usd' => 0.0,
            'spot_cost_basis_usd' => 0.0,
            'spot_position_sol' => 0.0,
            'spot_avg_cost_usd' => 0.0,
            'spot_swap_count' => 0,
            'spot_history_complete' => false,
        ];
    }
}
