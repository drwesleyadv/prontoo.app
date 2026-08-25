<?php
declare(strict_types=1);
namespace Pro\Wallet;

use Pro\Core\Config;
use Pro\Core\HttpClient;

final class SolanaWallet
{
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
                    ['mint' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v'],
                    ['encoding' => 'jsonParsed'],
                ],
            ],
        ]);
        if (!is_array($responses)) return [];
        $byId = [];
        foreach ($responses as $response) {
            if (is_array($response) && isset($response['id'])) $byId[(int)$response['id']] = $response;
        }
        $out = [];
        $lamports = $byId[1]['result']['value'] ?? null;
        if (is_numeric($lamports)) $out['sol_balance'] = ((int)$lamports) / 1e9;
        $accounts = $byId[2]['result']['value'] ?? null;
        if (is_array($accounts)) {
            $usdc = 0.0;
            foreach ($accounts as $account) {
                $info = $account['account']['data']['parsed']['info'] ?? [];
                $usdc += (float)($info['tokenAmount']['uiAmount'] ?? 0);
            }
            $out['usdc_balance'] = $usdc;
        }
        return $out;
    }
}
