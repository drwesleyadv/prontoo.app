<?php
declare(strict_types=1);
namespace Pro\Features;

final class FeaturePipeline
{
    public static function row(array $window): array
    {
        $c=array_map('floatval', array_column($window,'c'));
        $h=array_map('floatval', array_column($window,'h'));
        $l=array_map('floatval', array_column($window,'l'));
        $v=array_map('floatval', array_column($window,'v'));
        $n=count($c);
        $last=$n?(float)end($c):0.0;
        $first=$n?(float)$c[0]:$last;
        $ret1=$n>1&&$c[$n-2]>0?($last/$c[$n-2]-1):0.0;
        $ret3=$n>3&&$c[$n-4]>0?($last/$c[$n-4]-1):0.0;
        $ret12=$n>12&&$c[$n-13]>0?($last/$c[$n-13]-1):0.0;
        $ema9=TechnicalIndicators::ema($c,9);
        $ema21=TechnicalIndicators::ema($c,21);
        $bb=TechnicalIndicators::bollinger($c);
        $atr=TechnicalIndicators::atr($h,$l,$c);
        $volSlice=array_slice($v,-20);
        $volAvg=array_sum($volSlice)/max(1,count($volSlice));
        $volLast=$n?(float)end($v):0.0;
        return [
            'return_1'=>$ret1,
            'return_3'=>$ret3,
            'return_12'=>$ret12,
            'range_atr'=>$last>0?$atr/$last:0.0,
            'rsi'=>TechnicalIndicators::rsi($c)/100.0,
            'ema_spread'=>$last>0?(((float)(end($ema9)?:0))-((float)(end($ema21)?:0)))/$last:0.0,
            'bb_position'=>$bb['pos'],
            'bb_width'=>$bb['width'],
            'adx'=>TechnicalIndicators::adx($h,$l,$c)/100.0,
            'volume_ratio'=>$volAvg>0?$volLast/$volAvg:1.0,
            'momentum_quality'=>$first>0?($last/$first-1):0.0,
        ];
    }

    public static function latest(array $c30, int $window): ?array
    {
        $n=count($c30);
        if($n<$window)return null;
        $slice=array_slice($c30,$n-$window,$window);
        $row=self::row($slice);
        $last=$c30[$n-1];
        return [
            'x'=>array_values($row),
            'names'=>array_keys($row),
            'meta'=>[
                't'=>(int)($last['t']??time()),
                'decision_t'=>(int)($last['t']??time()),
                'reference_candle_t'=>(int)($last['t']??time()),
                'close'=>(float)($last['c']??0.0),
                'decision_close'=>(float)($last['c']??0.0),
                'execution_t'=>time(),
                'execution_price'=>(float)($last['c']??0.0),
                'features'=>$row,
            ],
        ];
    }

    public static function dataset(array $c30, int $window, int $horizon, callable $labeler): array
    {
        $X=[]; $Y=[]; $meta=[];
        $n=count($c30);
        $horizon=max(1,$horizon);
        for($i=$window-1; $i+$horizon<$n; $i++){
            $slice=array_slice($c30,$i-$window+1,$window);
            $row=self::row($slice);
            $label=$labeler($c30,$i,$horizon,$row);
            $execIdx=min($i+1,$n-1);
            $exitIdx=min($i+$horizon,$n-1);
            $X[]=array_values($row);
            $Y[]=$label;
            $meta[]=[
                't'=>(int)($c30[$i]['t']??0),
                'decision_t'=>(int)($c30[$i]['t']??0),
                'reference_candle_t'=>(int)($c30[$i]['t']??0),
                'close'=>(float)($c30[$i]['c']??0.0),
                'decision_close'=>(float)($c30[$i]['c']??0.0),
                'execution_t'=>(int)($c30[$execIdx]['t']??0),
                'execution_price'=>(float)($c30[$execIdx]['o']??($c30[$execIdx]['c']??0.0)),
                'exit_t'=>(int)($c30[$exitIdx]['t']??0),
                'exit_price'=>(float)($c30[$exitIdx]['c']??0.0),
                'features'=>$row,
            ];
        }
        $names=[];
        if($n>0){ $names=array_keys(self::row(array_slice($c30,0,min($window,$n)))); }
        return ['X'=>$X,'Y'=>$Y,'meta'=>$meta,'names'=>$names];
    }
}
