<?php
declare(strict_types=1);
namespace Pro\Wallet;

use Pro\Core\Config;
use Pro\Core\HttpClient;
use Pro\Core\Storage;

final class SolanaWallet
{
    private const USDC_MINT = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';
    private const STAKE_PROGRAM = 'Stake11111111111111111111111111111111111111';

    public static function balances(string $address): array
    {
        if ($address === '') return self::unavailable();
        $now = time();
        $cachePath = 'cache/runtime/solana-wallet-' . hash('sha256', $address) . '.json';
        $cached = Storage::read($cachePath, []);
        $fetchedAt = (int)($cached['fetched_at'] ?? 0);
        $freshFor = (int)Config::get('solana_wallet_cache_seconds', 20);
        if ($fetchedAt > 0 && $now - $fetchedAt <= $freshFor) {
            $cached['wallet_status'] = 'cached';
            return $cached;
        }

        $live = self::fetch($address);
        if ($live !== null) {
            $live['wallet_status'] = 'live';
            $live['wallet_updated_at'] = $now;
            $live['fetched_at'] = $now;
            Storage::write($cachePath, $live);
            return $live;
        }

        $staleFor = (int)Config::get('solana_wallet_stale_seconds', 300);
        if ($fetchedAt > 0 && $now - $fetchedAt <= $staleFor) {
            $cached['wallet_status'] = 'stale';
            return $cached;
        }
        return self::unavailable();
    }

    private static function fetch(string $address): ?array
    {
        $rpc = (string)Config::get('solana_rpc');
        $responses = HttpClient::postJson($rpc, [
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'getBalance',
                'params' => [$address],
            ],
            [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'getTokenAccountsByOwner',
                'params' => [
                    $address,
                    ['mint' => self::USDC_MINT],
                    ['encoding' => 'jsonParsed'],
                ],
            ],
            self::stakeRequest($address),
        ]);
        if (!is_array($responses)) return null;

        $byId = [];
        foreach ($responses as $response) {
            if (is_array($response) && isset($response['id'])) $byId[(int)$response['id']] = $response;
        }

        $lamports = $byId[1]['result']['value'] ?? null;
        $accounts = $byId[2]['result']['value'] ?? null;
        $stakeRows = $byId[3]['result'] ?? null;
        if (!is_numeric($lamports) || !is_array($accounts) || !is_array($stakeRows)) return null;

        $out = [
            'sol_balance' => ((int)$lamports) / 1e9,
            'usdc_balance' => 0.0,
            'stake_balance' => 0.0,
        ];
        foreach ($accounts as $account) {
            $info = $account['account']['data']['parsed']['info'] ?? [];
            $amount = $info['tokenAmount']['uiAmount'] ?? null;
            if (is_numeric($amount)) $out['usdc_balance'] += (float)$amount;
        }

        $stakeLamports = 0;
        foreach ($stakeRows as $row) {
            if (!is_array($row)) continue;
            $amount = $row['account']['lamports'] ?? null;
            if (is_numeric($amount)) $stakeLamports += (int)$amount;
        }
        $out['stake_balance'] = $stakeLamports / 1e9;
        return $out;
    }

    private static function stakeRequest(string $address): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'getProgramAccounts',
            'params' => [
                self::STAKE_PROGRAM,
                [
                    'encoding' => 'base64',
                    'dataSlice' => ['offset' => 0, 'length' => 0],
                    'filters' => [
                        ['memcmp' => ['offset' => 44, 'bytes' => $address]],
                    ],
                ],
            ],
        ];
    }

    private static function unavailable(): array
    {
        return [
            'sol_balance' => 0.0,
            'usdc_balance' => 0.0,
            'stake_balance' => 0.0,
            'wallet_status' => 'unavailable',
        ];
    }
}
