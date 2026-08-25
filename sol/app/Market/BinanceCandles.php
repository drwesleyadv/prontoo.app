<?php
declare(strict_types=1);
namespace Pro\Market;

use Pro\Core\Config;
use Pro\Core\HttpClient;

final class BinanceCandles
{
    private const INTERVAL = '30m';
    private const STEP_MS = 1800000;

    public static function fetch30m(int $fromTs, int $toTs, bool $closedOnly = true): array
    {
        $fromMs = max(0, $fromTs * 1000);
        $toMs = max($fromMs, $toTs * 1000);
        $nowMs = time() * 1000;
        $all = [];

        for ($page = 0; $page < 120 && $fromMs <= $toMs; $page++) {
            $url = (string)Config::get('binance_klines') . '?' . http_build_query([
                'symbol' => Config::get('symbol'),
                'interval' => self::INTERVAL,
                'limit' => 1000,
                'startTime' => $fromMs,
                'endTime' => $toMs,
            ]);
            $raw = HttpClient::get($url, 20, 3);
            if ($raw === null) break;
            $rows = json_decode($raw, true);
            if (!is_array($rows) || !$rows) break;

            foreach ($rows as $row) {
                if (!is_array($row) || count($row) < 7) continue;
                $openMs = (int)$row[0];
                $closeMs = (int)$row[6];
                if ($closedOnly && $closeMs >= $nowMs) continue;
                $all[] = [
                    't' => intdiv($openMs, 1000),
                    'o' => (float)$row[1],
                    'h' => (float)$row[2],
                    'l' => (float)$row[3],
                    'c' => (float)$row[4],
                    'v' => (float)$row[5],
                ];
            }

            $lastOpen = (int)$rows[count($rows) - 1][0];
            $next = $lastOpen + self::STEP_MS;
            if ($next <= $fromMs) break;
            $fromMs = $next;
            if (count($rows) < 1000) break;
            usleep(80000);
        }

        return $all;
    }
}
