<?php
declare(strict_types=1);
namespace Pro\Decision;

final class AiPayload
{
    public static function insufficient(int $rows): array
    {
        return ['timestamp'=>time(),'acao'=>'manter','prob_compra'=>0.0,'prob_venda'=>0.0,'prob_manter'=>1.0,'probabilidade_reversao'=>0.0,'probabilidade_percentual'=>0.0,'metricas'=>['acuracia'=>0,'precisao'=>0,'f1_score'=>0],'explicacao'=>['resumo'=>'Aguardando dados suficientes para análise temporal honesta.','detalhes'=>'Amostras disponíveis: '.$rows],'aviso_experimental'=>'Scores operacionais calibrados, não recomendação financeira.','scientific_context'=>['values_are'=>'calibrated_operational_scores','not_probability_of_price_direction'=>true,'sample_size'=>$rows]];
    }
    public static function fromDecision(array $decision,array $raw,array $cal,array $validation,array $regime,array $meta): array
    {
        $buy=max(0,min(1,$decision['buy'])); $sell=max(0,min(1,$decision['sell'])); $hold=max(0,min(1,$decision['hold'])); $sum=$buy+$sell+$hold; if($sum<=0){$buy=$sell=0;$hold=1;$sum=1;} $buy/=$sum;$sell/=$sum;$hold/=$sum;
        $acao=$decision['action']; $max=max($buy,$sell,$hold); $summary = self::summary($acao,$decision,$validation,$regime);
        return [
            'timestamp'=>time(), 'acao'=>$acao,
            'prob_compra'=>round($buy,4), 'prob_venda'=>round($sell,4), 'prob_manter'=>round($hold,4),
            'probabilidade_reversao'=>round(max($buy,$sell),4), 'probabilidade_percentual'=>round(max($buy,$sell,$hold)*100,1),
            'trend_atual'=>$regime['trend']??'neutral', 'contexto'=>$regime['regime']??'balanced',
            'metricas'=>['acuracia'=>round((float)($validation['accuracy']??0),4),'precisao'=>round((float)($validation['policy_precision']??0),4),'f1_score'=>round((float)($validation['policy_f1']??0),4),'backtest_percent'=>round(max(0.0,min(100.0,(float)($validation['policy_backtest']['win_rate']??0))),2),'backtest_drawdown'=>round((float)($validation['policy_backtest']['max_drawdown']??0),2),'backtest_trades'=>(int)($validation['policy_backtest']['num_trades']??0),'backtest_net_return'=>round((float)($validation['policy_backtest']['net_return']??0),2)],
            'explicacao'=>['resumo'=>$summary,'detalhes'=>'Valores exibidos são scores operacionais calibrados; Manter significa ausência de vantagem estatística suficiente.','bloqueios'=>$decision['blocked_by']??[]],
            'raw_model_scores'=>['comprar'=>round((float)($raw[1]??0),4),'vender'=>round((float)($raw[-1]??0),4),'manter'=>round((float)($raw[0]??0),4)],
            'calibrated_scores'=>['comprar'=>round((float)($cal[1]??0),4),'vender'=>round((float)($cal[-1]??0),4),'manter'=>round((float)($cal[0]??0),4)],
            'scientific_context'=>['values_are'=>'calibrated_operational_scores','not_probability_of_price_direction'=>true,'baseline_passed'=>(bool)($validation['beats_baseline']??false),'sample_size'=>(int)($validation['sample_size']??0),'walk_forward_windows'=>(int)($validation['walk_forward_windows']??0),'brier_score'=>$validation['brier_score']??null,'log_loss'=>$validation['log_loss']??null,'ece'=>$validation['ece']??null,'optimized_policy'=>$validation['optimized_policy']??null,'policy_backtest'=>$validation['policy_backtest']??null],
            'aviso_experimental'=>'Scores operacionais; não constituem recomendação financeira.'
        ];
    }
    public static function learning(array $progress, int $rows): array
    {
        $pct = round((float)($progress['progress_percent'] ?? 0.0), 2);
        $cursor = (string)($progress['cursor_label'] ?? 'aguardando histórico');
        $target = (string)($progress['target_label'] ?? 'data atual');
        $isCurrent = (bool)($progress['is_current'] ?? false);
        $strength = $isCurrent ? 'Atual' : 'Aprendendo';
        $resumo = $isCurrent ? "Base histórica sincronizada até {$cursor}." : "Aprendendo histórico: base processada até {$cursor} de {$target} ({$pct}%).";
        $wallet = $progress['theoretical_wallet'] ?? [];
        $partialBacktest = $progress['partial_backtest'] ?? [];
        $calibration = $progress['calibration_state'] ?? [];
        $decisions = $progress['decision_stats'] ?? [];
        $activePolicy = $progress['active_policy'] ?? [];
        $learningPhase = (string)($progress['learning_phase'] ?? '');
        $backtestWinRate = is_array($partialBacktest) && array_key_exists('win_rate', $partialBacktest) ? max(0.0, min(100.0, (float)$partialBacktest['win_rate'])) : 0.0;
        $backtestNetReturn = is_array($partialBacktest) && array_key_exists('net_return', $partialBacktest) ? (float)$partialBacktest['net_return'] : 0.0;
        $backtestDrawdown = is_array($partialBacktest) && array_key_exists('max_drawdown', $partialBacktest) ? (float)$partialBacktest['max_drawdown'] : 0.0;
        $backtestTrades = is_array($partialBacktest) && array_key_exists('num_trades', $partialBacktest) ? (int)$partialBacktest['num_trades'] : 0;
        return [
            'timestamp'=>time(),'acao'=>'manter','prob_compra'=>0.0,'prob_venda'=>0.0,'prob_manter'=>1.0,'probabilidade_reversao'=>0.0,'probabilidade_percentual'=>100.0,'trend_atual'=>'neutral','contexto'=>'historical_learning',
            'metricas'=>['acuracia'=>0,'precisao'=>0,'f1_score'=>0,'backtest_percent'=>round($backtestWinRate, 2),'backtest_drawdown'=>round($backtestDrawdown, 2),'backtest_net_return'=>round($backtestNetReturn, 2),'backtest_trades'=>$backtestTrades,'teorica_percent'=>round((float)($wallet['variacao_percentual'] ?? 0), 2),'teorica_drawdown'=>round((float)($wallet['max_drawdown'] ?? 0), 2),'teorica_trades'=>(int)($wallet['num_trades'] ?? 0),'teorica_win_rate'=>round((float)($wallet['win_rate'] ?? 0), 2),'matured_decisions'=>(int)($decisions['matured_decisions'] ?? $calibration['matured_action_decisions'] ?? 0),'matured_win_rate'=>round((float)($calibration['win_rate'] ?? 0), 2),'policy_threshold'=>isset($activePolicy['threshold']) ? round((float)$activePolicy['threshold'] * 100, 1) : null,'policy_margin'=>isset($activePolicy['margin']) ? round((float)$activePolicy['margin'] * 100, 1) : null,'position_fraction'=>isset($activePolicy['position_fraction']) ? round((float)$activePolicy['position_fraction'] * 100, 1) : null],
            'explicacao'=>['resumo'=>$resumo,'detalhes'=>'Durante o replay histórico, a recomendação permanece em Manter porque a IA ainda não alcançou o último candle fechado. O processamento respeita a ordem temporal e não usa dados futuros para simular aprendizado.','bloqueios'=>['replay histórico em andamento']],
            'learning_progress'=>$progress,'ai_status'=>$strength,
            'scientific_context'=>['values_are'=>'historical_replay_status','not_probability_of_price_direction'=>true,'recommendation_blocked_until_replay_is_current'=>!$isCurrent,'sample_size'=>$rows,'temporal_protocol'=>'progressive_30m_operational_replay_decision_execution_matured_feedback_policy_recalibration','partial_backtest'=>$partialBacktest,'learning_phase'=>$learningPhase,'active_policy'=>$activePolicy,'theoretical_wallet'=>$wallet,'calibration_state'=>$calibration,'decision_stats'=>$decisions],
            'aviso_experimental'=>'Scores operacionais; não constituem recomendação financeira.'
        ];
    }

    private static function summary(string $acao,array $decision,array $validation,array $regime): string
    { $a=['comprar'=>'Comprar','vender'=>'Vender','manter'=>'Manter'][$acao]??'Manter'; $f1=round(100*((float)($validation['policy_f1']??0))); $base=($validation['beats_baseline']??false)?'supera baseline':'não supera baseline'; $reg=$regime['regime']??'balanced'; $blocked=$decision['blocked_by']??[]; $b=$blocked?(' Bloqueios: '.implode(', ',$blocked).'.'):''; return "$a. Política walk-forward com F1 {$f1}%, {$base}, regime {$reg}.{$b}"; }
}
