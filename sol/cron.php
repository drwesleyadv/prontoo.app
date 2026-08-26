<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Pro\Core\Log;
use Pro\Core\Storage;
use Pro\Decision\AiPayload;
use Pro\Learning\HistoricalReplay;
use Pro\Learning\PredictionEngine;
use Pro\Market\BinanceCandles;
use Pro\Market\CandleRepository;
use Pro\Wallet\JupiterPortfolio;
use Pro\Wallet\WalletState;

$wallet = WalletState::read();
$walletAddress = (string)($wallet['address'] ?? '');
if ($walletAddress !== '') {
    try {
        JupiterPortfolio::leverage($walletAddress);
    } catch (\Throwable $error) {
        Log::warn('cron perps cache: ' . $error->getMessage());
    }
}

$lock = Storage::acquireLock('cache/runtime/scientific-jobs.lock');
if ($lock === null) {
    Log::info('cron skip scientific job ativo');
    echo "SKIP\n";
    exit(0);
}

try {
    Log::info('cron inicio');
    $history = CandleRepository::thirtyMinutes();
    $lastTs = $history ? (int)($history[count($history) - 1]['t'] ?? 0) : 0;
    $fromTs = $lastTs > 0 ? max(0, $lastTs - 1800) : time() - 30 * 86400;
    $fresh = BinanceCandles::fetch30m($fromTs, time(), true);
    $history = CandleRepository::merge30m($history, $fresh);
    CandleRepository::saveThirtyMinutes($history);

    if ($history) {
        if (!HistoricalReplay::isCurrentFor($history)) {
            $progress = Storage::read('cache/runtime/learning-progress.json', []);
            Storage::write('cache/previsao.json', AiPayload::learning(is_array($progress) ? $progress : [], (int)($progress['processed_30m_blocks'] ?? 0)));
        } else {
            $latestTs = (int)($history[count($history) - 1]['t'] ?? 0);
            $marker = Storage::read('cache/runtime/last-prediction.json', []);
            $predictedTs = (int)($marker['candle_t'] ?? 0);
            if ($latestTs > $predictedTs && count($history) > 80) {
                PredictionEngine::run($history);
                Storage::write('cache/runtime/last-prediction.json', ['candle_t' => $latestTs, 'updated_at' => time()]);
            }
        }
    }

    Log::info('cron ok candles30m=' . count($history));
    echo "OK\n";
} finally {
    Storage::releaseLock($lock);
}
