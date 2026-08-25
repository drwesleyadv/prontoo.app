<?php
declare(strict_types=1);
namespace Pro\Features;

final class MarketRegime
{
    public static function detect(array $features): array
    {
        $adx=(float)($features['adx'] ?? 0); $width=(float)($features['bb_width'] ?? 0); $spread=(float)($features['ema_spread'] ?? 0); $vol=(float)($features['range_atr'] ?? 0);
        $trend = abs($spread) > 0.002 && $adx > 0.18 ? ($spread > 0 ? 'up' : 'down') : 'neutral';
        $regime = 'balanced';
        if ($adx < 0.16 && $width < 0.025) $regime = 'consolidation';
        elseif ($vol > 0.025 || $width > 0.08) $regime = 'high_volatility';
        elseif ($trend !== 'neutral') $regime = 'trend';
        return ['trend'=>$trend,'regime'=>$regime,'risk'=>$vol>0.025?'high':($vol<0.01?'low':'medium')];
    }
}
