<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Pro\Core\Config;
use Pro\Core\Storage;
use Pro\Decision\AiPayload;
use Pro\Learning\HistoricalReplay;
use Pro\Market\BinanceCandles;
use Pro\Market\CandleRepository;

$state = Storage::read('cache/runtime/learn-state.json', []);
$next = (int)($state['next_ts'] ?? Config::get('solusdt_first_ts'));
$target = time();
$started = microtime(true);
$blocks = 0;
$chunkSeconds = (int)Config::get('historical_fetch_chunk_seconds', 2592000);
$history = CandleRepository::thirtyMinutes();

while ($next < $target && microtime(true) - $started < 24) {
    $to = min($next + $chunkSeconds, $target);
    $batch = BinanceCandles::fetch30m($next, $to, true);
    if ($batch) $history = CandleRepository::merge30m($history, $batch);
    $next = $to;
    $blocks++;
    Storage::write('cache/runtime/learn-state.json', [
        'next_ts' => $next,
        'next_label' => date('Y-m-d H:i:s', $next),
        'target_ts' => $target,
        'target_label' => date('Y-m-d H:i:s', $target),
        'updated_at' => time(),
    ]);
}

if ($blocks > 0) CandleRepository::saveThirtyMinutes($history);
$progress = HistoricalReplay::run($history, (int)Config::get('replay_blocks_per_run', 900), max(4, 28 - (int)(microtime(true) - $started)));
if (!HistoricalReplay::isCurrentFor($history)) {
    Storage::write('cache/previsao.json', AiPayload::learning($progress, (int)($progress['processed_30m_blocks'] ?? 0)));
}

echo 'Coleta histórica até ' . date('Y-m-d H:i:s', $next) . ' (' . $blocks . ' bloco(s)); replay até ' . ($progress['cursor_label'] ?? 'aguardando') . "\n";
