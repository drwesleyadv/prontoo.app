<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Pro\Core\Config;
use Pro\Core\Storage;
use Pro\Wallet\SolanaWallet;

function jsonResponse(array $payload, int $status = 200, string $cacheControl = 'no-store, max-age=0'): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: ' . $cacheControl);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiFilesEtag(array $files): string
{
    $parts = [];
    foreach ($files as $relative) {
        $path = Config::path($relative);
        $parts[] = $relative . ':' . (is_file($path) ? (string)filemtime($path) . ':' . (string)filesize($path) : '0:0');
    }
    return '"' . sha1(implode('|', $parts)) . '"';
}

function enforceWalletRateLimit(): void
{
    $limit = (int)Config::get('wallet_lookup_limit_per_minute', 12);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = hash('sha256', $ip);
    $relative = 'cache/runtime/wallet-rate-' . $key . '.json';
    $row = Storage::read($relative, []);
    $minute = intdiv(time(), 60);
    $count = (int)($row['minute'] ?? -1) === $minute ? (int)($row['count'] ?? 0) : 0;
    if ($count >= $limit) jsonResponse(['ok' => false, 'error' => 'rate_limited'], 429);
    Storage::write($relative, ['minute' => $minute, 'count' => $count + 1]);
}

$api = (string)($_GET['api'] ?? '');
if ($api === 'ai' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $files = ['cache/previsao.json', 'cache/teorica.json', 'cache/runtime/learning-progress.json'];
    $etag = apiFilesEtag($files);
    header('ETag: ' . $etag);
    header('Cache-Control: no-cache, max-age=0');
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }
    jsonResponse([
        'ok' => true,
        'prediction' => Storage::read($files[0], []),
        'theoretical' => Storage::read($files[1], []),
        'learning' => Storage::read($files[2], []),
        'server_time' => time(),
    ], 200, 'no-cache, max-age=0');
}

if ($api === 'wallet' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceWalletRateLimit();
    $payload = json_decode((string)file_get_contents('php://input'), true);
    $address = trim((string)(is_array($payload) ? ($payload['address'] ?? '') : ''));
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address)) jsonResponse(['ok' => false, 'error' => 'invalid_address'], 400);
    $balances = SolanaWallet::balances($address);
    jsonResponse(['ok' => true, 'balances' => $balances, 'updated_at' => time()]);
}

$assetVersion = max(array_map(static fn(string $file): int => (int)@filemtime(__DIR__ . '/' . $file), ['css/style.css','js/app-core.js','js/app-market.js','js/app-chart.js','js/app-ui.js']));
?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no"><meta name="robots" content="noindex,nofollow,noarchive"><title>SOL/USDT – Painel</title><link rel="icon" id="favicon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet"><link rel="stylesheet" href="css/style.css?v=<?= $assetVersion ?>"></head><body>
<div class="container"><div class="avatar-wrap"><div class="avatar" id="avatar" title="Editar carteira"><canvas id="avatarCanvas" width="36" height="36"></canvas></div></div>
<div class="row-cards"><div class="card"><span class="label">Preço</span><span class="value" id="currentPrice">$ --</span><span class="change" id="change24h">--</span></div><div class="card"><span class="label">Volume</span><span class="value" id="volume24hValue">--</span><span class="change" id="volumeHourlyChange">--</span></div><div class="card"><span class="label">Total</span><span class="value" id="carteiraUsdValue">$ --</span><span class="change" id="carteiraUsdChange">--</span></div><div class="card" id="pnlCard"><span class="label" id="pnlLabel">Lucro</span><span class="value" id="pnlValue">$ --</span><span class="change" id="pnlChange">--</span></div></div>
<div class="card analysis-card"><div class="bar-item"><div class="bar-track" id="priceBarTrack"><div class="bar-pointer" id="priceBarPointer" style="left:50%"></div></div><div class="bar-labels"><span id="priceMinValue">--</span><span id="priceMaxValue">--</span></div></div><div class="bar-item"><div class="bar-track" id="volumeBarTrack"><div class="bar-pointer" id="volumeBarPointer" style="left:50%"></div></div><div class="bar-labels"><span id="volumeMinValue">--</span><span id="volumeMaxValue">--</span></div></div><div class="bar-item"><div class="bar-track" id="rsiBarTrack"><div class="rsi-zones"><div class="rsi-zone-1"></div><div class="rsi-zone-2"></div><div class="rsi-zone-3"></div></div><div class="rsi-ticks"><div class="rsi-tick" style="left:20%"></div><div class="rsi-tick" style="left:80%"></div></div><div class="bar-pointer" id="rsiBarPointer" style="left:50%"></div></div><div class="bar-labels"><span id="rsiBarValueNum">50,0</span><span id="rsiBarValueLabel">Neutro</span></div></div></div>
<div class="chart-card" id="chartCard"><canvas id="candleCanvas"></canvas></div>
<div class="ai-card-v2" id="aiAnalysisCard" style="display:none"><div class="ai-v2-header"><div class="ai-v2-action"><span class="ai-v2-icon" id="aiActionIcon">⏺</span><span class="ai-v2-title" id="aiDecisionTitle">Manter</span></div><span class="ai-v2-strength" id="aiSignalStrength">Neutro</span></div><div class="ai-v2-confidence"><div class="ai-v2-confidence-bar"><div class="ai-v2-confidence-fill" id="aiConfidenceFill"></div></div><span class="ai-v2-confidence-text" id="aiConfidenceText">0%</span></div><div class="ai-v2-probabilities" id="aiProbabilities"></div><div class="ai-v2-learning" id="aiLearningInfo" style="display:none"></div><div class="ai-v2-metrics-grid" id="aiMetricsGrid"></div></div>
</div>
<div class="modal-carteira" id="carteiraModal"><div class="modal-content"><h2>Editar Carteira</h2><div class="field"><label>Integralizado (R$)</label><input id="inInteg" type="text" inputmode="decimal" value="0,00"></div><div class="field"><label>Carteira</label><input id="inEndereco" type="text" placeholder="Endereço Solana..."></div><div class="field"><label>Preço médio (USD) – opcional</label><input id="inPrecoMedio" type="text" inputmode="decimal" placeholder="Detectado automaticamente"></div><div class="field"><label>Saldo Stake (SOL) – opcional</label><input id="inStake" type="text" inputmode="decimal" placeholder="0,00"></div><div class="btn-row"><button class="btn save" id="saveCarteiraBtn">Salvar</button><button class="btn" id="closeCarteiraBtn">Cancelar</button></div><div class="note" id="carteiraNote"></div></div></div>
<script src="js/app-core.js?v=<?= $assetVersion ?>"></script><script src="js/app-market.js?v=<?= $assetVersion ?>"></script><script src="js/app-chart.js?v=<?= $assetVersion ?>"></script><script src="js/app-ui.js?v=<?= $assetVersion ?>"></script></body></html>
