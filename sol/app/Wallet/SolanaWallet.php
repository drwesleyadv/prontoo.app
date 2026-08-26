<?php
declare(strict_types=1);
namespace Pro\Wallet;

use Pro\Core\Config;
use Pro\Core\HttpClient;

final class SolanaWallet
{
    private const USDC_MINT = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';
    private const STAKE_PROGRAM = 'Stake11111111111111111111111111111111111111';

    public static function balances(string $address): array
    {
        if ($address === '') return [];
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
            self::stakeRequest(3, $address, 12),
            self::stakeRequest(4, $address, 44),
        ]);
        if (!is_array($responses)) return [];
        $byId = [];
        foreach ($responses as $response) {
            if (is_array($response) && isset($response['id'])) $byId[(int)$response['id']] = $response;
        }

        $out = ['sol_balance' => 0.0, 'usdc_balance' => 0.0, 'stake_balance' => 0.0];
        $lamports = $byId[1]['result']['value'] ?? null;
        if (is_numeric($lamports)) $out['sol_balance'] = ((int)$lamports) / 1e9;

        $accounts = $byId[2]['result']['value'] ?? null;
        if (is_array($accounts)) {
            foreach ($accounts as $account) {
                $info = $account['account']['data']['parsed']['info'] ?? [];
                $out['usdc_balance'] += (float)($info['tokenAmount']['uiAmount'] ?? 0);
            }
        }

        $stakeAccounts = [];
        foreach ([3, 4] as $id) {
            $rows = $byId[$id]['result'] ?? null;
            if (!is_array($rows)) continue;
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $pubkey = (string)($row['pubkey'] ?? '');
                $stakeLamports = $row['account']['lamports'] ?? null;
                if ($pubkey !== '' && is_numeric($stakeLamports)) $stakeAccounts[$pubkey] = (int)$stakeLamports;
            }
        }
        if ($stakeAccounts) $out['stake_balance'] = array_sum($stakeAccounts) / 1e9;
        return $out;
    }

    private static function stakeRequest(int $id, string $address, int $offset): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => 'getProgramAccounts',
            'params' => [
                self::STAKE_PROGRAM,
                [
                    'encoding' => 'base64',
                    'dataSlice' => ['offset' => 0, 'length' => 0],
                    'filters' => [
                        ['memcmp' => ['offset' => $offset, 'bytes' => $address]],
                    ],
                ],
            ],
        ];
    }
}
