<?php
declare(strict_types=1);
namespace Pro\Market;

use Pro\Core\Config;
use Pro\Core\Storage;

final class CandleRepository
{
    public static function thirtyMinutes(): array
    {
        $rows = Storage::read('cache/mercado_30m.json', []);
        return is_array($rows) ? $rows : [];
    }

    public static function saveThirtyMinutes(array $candles): void
    {
        $limit = (int)Config::get('max_30m_candles', 120000);
        Storage::write('cache/mercado_30m.json', array_slice($candles, -$limit));
    }

    public static function merge30m(array $base, array $incoming): array
    {
        $base = self::normalizeSorted($base);
        $incoming = self::normalizeSorted($incoming);
        if (!$base) return $incoming;
        if (!$incoming) return $base;

        $out = [];
        $i = 0;
        $j = 0;
        $aCount = count($base);
        $bCount = count($incoming);
        while ($i < $aCount || $j < $bCount) {
            if ($i >= $aCount) {
                $out[] = $incoming[$j++];
                continue;
            }
            if ($j >= $bCount) {
                $out[] = $base[$i++];
                continue;
            }
            $at = $base[$i]['t'];
            $bt = $incoming[$j]['t'];
            if ($at < $bt) {
                $out[] = $base[$i++];
            } elseif ($bt < $at) {
                $out[] = $incoming[$j++];
            } else {
                $out[] = $incoming[$j++];
                $i++;
            }
        }
        return $out;
    }

    private static function normalizeSorted(array $rows): array
    {
        $out = [];
        $lastTs = -1;
        $ordered = true;
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['t'], $row['o'], $row['h'], $row['l'], $row['c'])) continue;
            $clean = [
                't' => (int)$row['t'],
                'o' => (float)$row['o'],
                'h' => (float)$row['h'],
                'l' => (float)$row['l'],
                'c' => (float)$row['c'],
                'v' => (float)($row['v'] ?? 0.0),
            ];
            if ($clean['t'] < $lastTs) $ordered = false;
            if ($out && $clean['t'] === $lastTs) $out[count($out) - 1] = $clean;
            else $out[] = $clean;
            $lastTs = $clean['t'];
        }
        if ($ordered) return $out;
        $map = [];
        foreach ($out as $row) $map[$row['t']] = $row;
        ksort($map, SORT_NUMERIC);
        return array_values($map);
    }
}
