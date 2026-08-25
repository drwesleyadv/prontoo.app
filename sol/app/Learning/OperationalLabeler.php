<?php
declare(strict_types=1);
namespace Pro\Learning;
use Pro\Core\Config;

final class OperationalLabeler
{
    public static function label(array $candles, int $decisionIndex, int $horizon, array $features): int
    {
        $horizon=max(1,$horizon);
        $execIndex=min($decisionIndex+1,count($candles)-1);
        $exitIndex=min($decisionIndex+$horizon,count($candles)-1);
        $entry=(float)($candles[$execIndex]['o']??($candles[$execIndex]['c']??0.0));
        $future=(float)($candles[$exitIndex]['c']??0.0);
        if($entry<=0.0 || $future<=0.0)return 0;
        $ret=($future/$entry)-1.0;
        $roundTripCost=(float)Config::get('trade_fee')*2.0 + (float)Config::get('slippage')*2.0;
        $noise=max(0.0015, min(0.012, (float)($features['range_atr'] ?? 0.0)*0.8));
        $barrier=$roundTripCost+$noise;
        if($ret>$barrier)return 1;
        if($ret<-$barrier)return -1;
        return 0;
    }
}
