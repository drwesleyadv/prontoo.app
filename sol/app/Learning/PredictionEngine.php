<?php
declare(strict_types=1);
namespace Pro\Learning;
use Pro\Core\Config;
use Pro\Core\Storage;
use Pro\Features\FeaturePipeline;
use Pro\Features\MarketRegime;
use Pro\Decision\DecisionPolicy;
use Pro\Decision\AiPayload;
use Pro\Simulation\TheoreticalWallet;

final class PredictionEngine
{
    public static function run(array $c30): array
    {
        $window=(int)Config::get('feature_window');
        $horizon=max(1,(int)Config::get('prediction_horizon_30m'));
        $ds=FeaturePipeline::dataset($c30,$window,$horizon,[OperationalLabeler::class,'label']);
        $latest=FeaturePipeline::latest($c30,$window);

        if($latest===null || count($ds['Y']) < (int)Config::get('min_train_rows')){
            $payload=AiPayload::insufficient(count($ds['Y']));
            Storage::write('cache/previsao.json',$payload);
            return $payload;
        }

        $X=$ds['X']; $Y=$ds['Y']; $meta=$ds['meta'];
        $validation=WalkForwardBacktester::validate($X,$Y,$meta,(int)Config::get('walk_windows'));
        $feedback=Storage::read('cache/runtime/calibration-state.json',[]);
        $teo=Storage::read('cache/teorica.json',[]);
        if(isset($teo['trades_fechados']))$feedback['closed_trades']=(int)$teo['trades_fechados'];
        if(isset($teo['win_rate']))$feedback['win_rate']=(float)$teo['win_rate'];
        if(isset($teo['max_drawdown']))$feedback['max_drawdown']=(float)$teo['max_drawdown'];
        $policy=DecisionPolicy::adaptivePolicy($validation['optimized_policy']??[],$feedback);
        $validation['adaptive_feedback']=$feedback;
        $validation['adaptive_policy']=$policy;

        $n=count($Y);
        $calSize=max(48,min(240,(int)floor($n*0.20)));
        $modelEnd=max(1,$n-$calSize);
        if($modelEnd<80){
            $payload=AiPayload::insufficient($n);
            Storage::write('cache/previsao.json',$payload);
            return $payload;
        }

        $modelX=array_slice($X,0,$modelEnd);
        $modelY=array_slice($Y,0,$modelEnd);
        $calX=array_slice($X,$modelEnd);
        $calY=array_slice($Y,$modelEnd);

        $scaler=new FeatureScaler;
        $scaler->fit($modelX);
        $modelN=$scaler->transform($modelX);
        $calN=$scaler->transform($calX);
        $latestN=$scaler->transform([$latest['x']])[0];

        $model=new SoftmaxModel(count($latestN));
        $model->fit($modelN,$modelY,420,0.045,0.003);

        $calProb=[];
        foreach($calN as $row){ $calProb[]=$model->predict($row); }
        $calibration=new CalibrationMap;
        $calibration->fit($calProb,$calY);

        $raw=$model->predict($latestN);
        $cal=$calibration->calibrate($raw);
        $regime=MarketRegime::detect($latest['meta']['features']??[]);
        $decision=DecisionPolicy::decide($cal,$regime,$validation,$policy);
        $payload=AiPayload::fromDecision($decision,$raw,$cal,$validation,$regime,$latest['meta']);

        Storage::write('cache/model/latest-model.json',[
            'created_at'=>time(),
            'model'=>$model->pack(),
            'scaler'=>$scaler->pack(),
            'calibration'=>$calibration->pack(),
            'validation'=>$validation,
            'active_policy'=>$policy,
            'feature_names'=>$ds['names'],
            'training_protocol'=>'model_train_plus_separate_recent_calibration_holdout',
        ]);

        $hist=Storage::read('cache/runtime/prediction-history.json',[]);
        $last=end($hist) ?: null;
        $now=time();
        $referenceT=(int)($latest['meta']['reference_candle_t']??$now);
        $event=[
            'id'=>hash('sha256',$referenceT.'|'.$now.'|'.$payload['acao'].'|'.($latest['meta']['execution_price']??0)),
            'timestamp'=>$now,
            'decision_t'=>(int)($latest['meta']['decision_t']??$referenceT),
            'reference_candle_t'=>$referenceT,
            'execution_t'=>(int)($latest['meta']['execution_t']??$now),
            'execution_price'=>(float)($latest['meta']['execution_price']??($latest['meta']['close']??0.0)),
            'acao'=>$payload['acao'],
            'prob_compra'=>$payload['prob_compra'],
            'prob_venda'=>$payload['prob_venda'],
            'prob_manter'=>$payload['prob_manter'],
            'close'=>(float)($latest['meta']['close']??0.0),
            'policy'=>$policy,
            'blocked_by'=>$decision['blocked_by']??[],
            'scientific_context'=>[
                'scores_are'=>'calibrated_operational_scores',
                'execution_price_is_immutable'=>true,
                'recommendation_uses_latest_unlabeled_window'=>true,
            ],
        ];

        $shouldRecord=true;
        if(is_array($last)){
            $sameReference=((int)($last['reference_candle_t']??0)===$referenceT);
            $sameAction=((string)($last['acao']??'')===$payload['acao']);
            if($sameReference && $sameAction){ $shouldRecord=false; }
        }
        if($shouldRecord){ $hist[]=$event; }
        $hist=array_slice($hist,-1500);
        Storage::write('cache/runtime/prediction-history.json',$hist);

        $teorica=Storage::read('cache/teorica.json', []);
        $ledgerVersion='immutable_signal_execution_v3';
        $signalCount=count($hist);
        $needsTheoretical=empty($teorica)
            || (($teorica['ledger_version'] ?? '') !== $ledgerVersion)
            || ((int)($teorica['source_signal_count'] ?? -1) !== $signalCount);
        if($needsTheoretical){
            $teorica=TheoreticalWallet::simulate($hist,$c30,$policy);
            if(is_array($teorica))$teorica['source_signal_count']=$signalCount;
            Storage::write('cache/teorica.json',$teorica ?: []);
        }

        $payload['teorica_resumo']=$teorica ? [
            'variacao_percentual'=>$teorica['variacao_percentual'] ?? 0,
            'num_trades'=>$teorica['num_trades'] ?? 0,
            'trades_fechados'=>$teorica['trades_fechados'] ?? 0,
            'win_rate'=>$teorica['win_rate'] ?? 0,
            'max_drawdown'=>$teorica['max_drawdown'] ?? 0,
            'ledger_version'=>$teorica['ledger_version']??null,
        ] : null;
        $payload['scientific_context']['operational_chain']='historical_replay_operational_wallet_plus_latest_unlabeled_prediction_to_out_of_sample_policy_backtest';
        Storage::write('cache/previsao.json',$payload);
        return $payload;
    }
}
