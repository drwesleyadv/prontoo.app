<?php
declare(strict_types=1);
namespace Pro\Learning;
use Pro\Decision\DecisionPolicy;
use Pro\Features\MarketRegime;
use Pro\Simulation\TheoreticalWallet;

final class WalkForwardBacktester
{
    public static function validate(array $X, array $Y, array $meta, int $windows=6): array
    {
        $n=count($Y);
        if($n<180)return self::empty();

        $testSize=max(36,(int)floor($n/($windows+3)));
        $start=max(120,$n-$testSize*$windows);
        $pred=[]; $truth=[]; $evalMeta=[];

        for($cut=$start; $cut<$n; $cut+=$testSize){
            $testEnd=min($n,$cut+$testSize);
            $trainCount=$cut;
            $calSize=max(36,min(180,(int)floor($trainCount*0.20)));
            $modelEnd=$trainCount-$calSize;
            if($modelEnd<90 || $testEnd<=$cut)continue;

            $modelX=array_slice($X,0,$modelEnd);
            $modelY=array_slice($Y,0,$modelEnd);
            $calX=array_slice($X,$modelEnd,$calSize);
            $calY=array_slice($Y,$modelEnd,$calSize);
            $testX=array_slice($X,$cut,$testEnd-$cut);
            $testY=array_slice($Y,$cut,$testEnd-$cut);

            $scaler=new FeatureScaler;
            $scaler->fit($modelX);
            $modelN=$scaler->transform($modelX);
            $calN=$scaler->transform($calX);
            $testN=$scaler->transform($testX);

            $model=new SoftmaxModel(count($modelN[0]??[0]));
            $model->fit($modelN,$modelY,320,0.04,0.004);

            $calProb=[];
            foreach($calN as $row){ $calProb[]=$model->predict($row); }
            $calibration=new CalibrationMap;
            $calibration->fit($calProb,$calY);

            foreach($testN as $i=>$row){
                $pred[]=$calibration->calibrate($model->predict($row));
                $truth[]=$testY[$i];
                $evalMeta[]=$meta[$cut+$i]??[];
            }
        }

        if(count($truth)<60)return self::empty();

        $split=max(30,(int)floor(count($truth)*0.60));
        if($split>=count($truth)-20)$split=max(1,count($truth)-30);
        $selection=[
            'pred'=>array_slice($pred,0,$split),
            'truth'=>array_slice($truth,0,$split),
            'meta'=>array_slice($evalMeta,0,$split),
        ];
        $report=[
            'pred'=>array_slice($pred,$split),
            'truth'=>array_slice($truth,$split),
            'meta'=>array_slice($evalMeta,$split),
        ];
        if(count($report['truth'])<20){ $report=$selection; }

        $optimized=self::optimizePolicy($selection['pred'],$selection['truth'],$selection['meta']);
        $policy=$optimized['optimized_policy'];
        $reported=self::evaluatePolicy($report['pred'],$report['truth'],$report['meta'],$policy);
        $classification=self::classificationMetrics($report['pred'],$report['truth']);
        $quality=(new CalibrationMap)->quality($report['pred'],$report['truth']);

        $beats=(bool)(
            ($reported['beats_cash']??false)
            && ($reported['beats_buy_hold']??false)
            && ((int)($reported['closed_trades']??0)>=1)
        );

        return array_merge($quality,$classification,[
            'beats_baseline'=>$beats,
            'optimized_policy'=>$policy,
            'policy_precision'=>round((float)$reported['policy_precision'],4),
            'policy_recall'=>round((float)$reported['policy_recall'],4),
            'policy_f1'=>round((float)$reported['policy_f1'],4),
            'policy_backtest'=>[
                'net_return'=>round((float)$reported['net_return'],2),
                'buy_hold_return'=>round((float)$reported['buy_hold_return'],2),
                'max_drawdown'=>round((float)$reported['max_drawdown'],2),
                'num_trades'=>(int)$reported['num_trades'],
                'closed_trades'=>(int)$reported['closed_trades'],
                'win_rate'=>round((float)$reported['win_rate'],2),
                'sharpe'=>round((float)$reported['sharpe'],2),
                'beats_cash'=>(bool)$reported['beats_cash'],
                'beats_buy_hold'=>(bool)$reported['beats_buy_hold'],
            ],
            'policy_selection_backtest'=>$optimized['selection_backtest'],
            'walk_forward_windows'=>$windows,
            'sample_size'=>count($report['truth']),
            'selection_sample_size'=>count($selection['truth']),
            'accuracy'=>$classification['accuracy'],
        ]);
    }

    private static function optimizePolicy(array $pred,array $truth,array $meta): array
    {
        $best=null;
        $fallback=null;
        foreach([0.38,0.42,0.46,0.50,0.54,0.58,0.62] as $threshold){
            foreach([0.04,0.07,0.10,0.13,0.16] as $margin){
                foreach([0.35,0.50,0.70,1.0] as $fraction){
                    $policy=DecisionPolicy::policy(['threshold'=>$threshold,'margin'=>$margin,'min_f1'=>0.0,'position_fraction'=>$fraction]);
                    $metrics=self::evaluatePolicy($pred,$truth,$meta,$policy);
                    $utility=(float)$metrics['net_return'] - 0.35*(float)$metrics['max_drawdown'] + 25.0*(float)$metrics['policy_f1'];
                    $candidate=['policy'=>$policy,'metrics'=>$metrics,'utility'=>$utility];
                    if($fallback===null || $utility>$fallback['utility'])$fallback=$candidate;
                    if((int)$metrics['num_trades']<3)continue;
                    if($best===null || $utility>$best['utility'])$best=$candidate;
                }
            }
        }
        $chosen=$best??$fallback??['policy'=>DecisionPolicy::policy([]),'metrics'=>TheoreticalWallet::emptyBacktest(),'utility'=>0.0];
        return ['optimized_policy'=>$chosen['policy'],'selection_backtest'=>$chosen['metrics']];
    }

    private static function evaluatePolicy(array $pred,array $truth,array $meta,array $policy): array
    {
        $actions=[]; $tp=0;$fp=0;$fn=0;
        foreach($pred as $i=>$p){
            $m=$meta[$i]??[];
            $regime=MarketRegime::detect($m['features']??[]);
            $buy=(float)($p[1]??0);$sell=(float)($p[-1]??0);
            $max=max($buy,$sell);$side=$buy>=$sell?'comprar':'vender';$margin=abs($buy-$sell);
            $blocked=[];
            if($max<$policy['threshold'])$blocked[]='score insuficiente';
            if($margin<$policy['margin'])$blocked[]='margem curta';
            if(($regime['risk']??'medium')==='high' && empty($policy['allow_high_risk']))$blocked[]='risco elevado';
            $action=$blocked?'manter':$side;
            $actions[]=$action;
            $actual=(int)($truth[$i]??0);
            $predLabel=$action==='comprar'?1:($action==='vender'?-1:0);
            if($predLabel!==0){ if($predLabel===$actual)$tp++; else $fp++; }
            elseif($actual!==0)$fn++;
        }
        $precision=$tp/max(1,$tp+$fp);$recall=$tp/max(1,$tp+$fn);$f1=($precision+$recall)>0?2*$precision*$recall/($precision+$recall):0.0;
        $sim=TheoreticalWallet::backtest($actions,$meta,$policy);
        return array_merge($sim,['policy_precision'=>$precision,'policy_recall'=>$recall,'policy_f1'=>$f1]);
    }

    private static function classificationMetrics(array $pred,array $truth): array
    {
        $correct=0;$conf=[];
        foreach($pred as $i=>$p){$cls=array_keys($p,max($p))[0]??0; if($cls===($truth[$i]??0))$correct++; $conf[]=$cls;}
        return ['accuracy'=>count($truth)?$correct/count($truth):0,'predicted_classes'=>$conf];
    }

    private static function empty(): array
    {
        return ['brier_score'=>1,'log_loss'=>9,'ece'=>1,'accuracy'=>0,'policy_precision'=>0,'policy_recall'=>0,'policy_f1'=>0,'beats_baseline'=>false,'optimized_policy'=>DecisionPolicy::policy([]),'policy_backtest'=>TheoreticalWallet::emptyBacktest(),'policy_selection_backtest'=>TheoreticalWallet::emptyBacktest(),'walk_forward_windows'=>0,'sample_size'=>0,'selection_sample_size'=>0];
    }
}
