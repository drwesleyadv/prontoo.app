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
        $out = [];
        $rpc = (string)Config::get('solana_rpc');

        $b = HttpClient::postJson($rpc, [
            'jsonrpc'=>'2.0',
            'id'=>1,
            'method'=>'getBalance',
            'params'=>[$address]
        ]);
        if (isset($b['result']['value']) && is_numeric($b['result']['value'])) {
            $out['sol_balance'] = ((int)$b['result']['value']) / 1e9;
        }

        $t = HttpClient::postJson($rpc, [
            'jsonrpc'=>'2.0',
            'id'=>1,
            'method'=>'getTokenAccountsByOwner',
            'params'=>[
                $address,
                ['mint'=>'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v'],
                ['encoding'=>'jsonParsed']
            ]
        ]);
        if (isset($t['result']['value']) && is_array($t['result']['value'])) {
            $usdc = 0.0;
            foreach ($t['result']['value'] as $a) {
                $info = $a['account']['data']['parsed']['info'] ?? [];
                $usdc += (float)($info['tokenAmount']['uiAmount'] ?? 0);
            }
            $out['usdc_balance'] = $usdc;
        }

        return $out;
    }
}
