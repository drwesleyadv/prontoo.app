<?php
declare(strict_types=1);
namespace Pro\Decision;
use Pro\Core\Config;

final class DecisionPolicy
{
    public static function decide(array $calibrated, array $regime, array $validation, ?array $policy = null): array
    {
        $policy = self::policy($policy ?: ($validation['optimized_policy'] ?? []));
        $maturedActions=(int)($policy['matured_action_decisions'] ?? ($validation['policy_backtest']['closed_trades'] ?? 0));
        $exploration=(bool)($policy['exploration_mode'] ?? false);
        $baselineRequired=(bool)($policy['baseline_required'] ?? true);
        $buy=(float)($calibrated[1]??0); $sell=(float)($calibrated[-1]??0); $hold=(float)($calibrated[0]??0);
        $maxAction=max($buy,$sell); $side=$buy>=$sell?'comprar':'vender'; $margin=abs($buy-$sell);
        $policyF1=(float)($validation['policy_f1']??0); $baselineOk=(bool)($validation['beats_baseline']??false);
        $blocked=[];
        if($maxAction<$policy['threshold'])$blocked[]='score insuficiente';
        if($margin<$policy['margin'])$blocked[]='margem curta';
        if(!$exploration && $policyF1<$policy['min_f1'])$blocked[]='validação fraca';
        if($baselineRequired && !$baselineOk && $maturedActions >= (int)$policy['min_actions_for_baseline'])$blocked[]='não superou baseline';
        if(($regime['risk']??'medium')==='high' && empty($policy['allow_high_risk']))$blocked[]='risco elevado';
        $action=$blocked ? 'manter' : $side;
        $opportunityBuy=self::score($buy,$margin,$regime,$validation,'comprar');
        $opportunitySell=self::score($sell,$margin,$regime,$validation,'vender');
        if($action==='manter') $prudence=max($hold, 1-max($opportunityBuy,$opportunitySell));
        else $prudence=max(0.05, $hold*0.55);
        $sum=$opportunityBuy+$opportunitySell+$prudence; if($sum<=0)$sum=1;
        return ['action'=>$action,'buy'=>$opportunityBuy/$sum,'sell'=>$opportunitySell/$sum,'hold'=>$prudence/$sum,'blocked_by'=>$blocked,'margin'=>$margin,'policy'=>$policy];
    }

    public static function policy(array $candidate = []): array
    {
        return [
            'threshold'=>(float)($candidate['threshold'] ?? Config::get('decision_threshold')),
            'margin'=>(float)($candidate['margin'] ?? Config::get('decision_margin')),
            'min_f1'=>(float)($candidate['min_f1'] ?? Config::get('min_policy_f1')),
            'position_fraction'=>(float)($candidate['position_fraction'] ?? Config::get('theoretical_position_fraction',1.0)),
            'allow_high_risk'=>(bool)($candidate['allow_high_risk'] ?? false),
            'exploration_mode'=>(bool)($candidate['exploration_mode'] ?? false),
            'baseline_required'=>(bool)($candidate['baseline_required'] ?? true),
            'min_actions_for_baseline'=>(int)($candidate['min_actions_for_baseline'] ?? 5000),
            'matured_action_decisions'=>(int)($candidate['matured_action_decisions'] ?? 0),
            'adaptive_stage'=>(string)($candidate['adaptive_stage'] ?? 'standard'),
        ];
    }

    public static function adaptivePolicy(array $candidate = [], array $feedback = []): array
    {
        $p = self::policy($candidate);
        $matured = (int)($feedback['matured_action_decisions'] ?? $feedback['closed_trades'] ?? 0);
        $winRate = (float)($feedback['win_rate'] ?? 0.0);
        $avg = (float)($feedback['avg_result_percent'] ?? 0.0);
        $drawdown = (float)($feedback['max_drawdown'] ?? 0.0);
        $progress = max(0.0, min(100.0, (float)($feedback['replay_progress_percent'] ?? 0.0)));
        $maturedBucket = intdiv(max(0, $matured), 100);
        $p['matured_action_decisions'] = $matured;
        $p['replay_progress_percent'] = round($progress, 4);
        $p['recalibration_bucket_100'] = $maturedBucket;

        if ($progress < 50.0) {
            $p['adaptive_stage'] = 'phase_1_exploratory_first_half';
            $p['exploration_mode'] = true;
            $p['baseline_required'] = false;
            $p['min_actions_for_baseline'] = PHP_INT_MAX;
            $p['threshold'] = min($p['threshold'], 0.30);
            $p['margin'] = min($p['margin'], 0.005);
            $p['min_f1'] = 0.0;
            $p['position_fraction'] = min(max($p['position_fraction'], 0.20), 0.35);
            $p['allow_high_risk'] = true;
            $p['phase_description'] = 'Primeira metade do replay: exploração permissiva para gerar o máximo possível de trades controlados.';
            return $p;
        }

        $phase = ($progress - 50.0) / 50.0;
        $baseThreshold = 0.36 + (0.24 * $phase);
        $baseMargin = 0.025 + (0.115 * $phase);
        $baseF1 = 0.02 + (0.20 * $phase);
        $baseFraction = max(0.25, 0.75 - (0.35 * $phase));
        $p['adaptive_stage'] = 'phase_2_adaptive_preparation';
        $p['exploration_mode'] = $matured < 1000 || $phase < 0.35;
        $p['baseline_required'] = ($progress >= 75.0 && $matured >= 500);
        $p['min_actions_for_baseline'] = 500;
        $p['threshold'] = max(min($p['threshold'], 0.72), $baseThreshold);
        $p['margin'] = max(min($p['margin'], 0.22), $baseMargin);
        $p['min_f1'] = max(0.0, min(max($p['min_f1'], $baseF1), 0.34));
        $p['position_fraction'] = min(max($p['position_fraction'], 0.20), $baseFraction);
        $p['allow_high_risk'] = $progress < 65.0 && $drawdown <= 18.0;

        if ($matured >= 100) {
            if ($winRate >= 55.0 && $avg > 0.0 && $drawdown <= 18.0) {
                $p['threshold'] = max(0.34, $p['threshold'] - 0.05);
                $p['margin'] = max(0.015, $p['margin'] - 0.025);
                $p['min_f1'] = max(0.0, $p['min_f1'] - 0.04);
                $p['position_fraction'] = min(0.85, $p['position_fraction'] + 0.10);
                $p['feedback_adjustment'] = 'less_restrictive_after_positive_100_bucket';
            } elseif ($winRate < 45.0 || $avg < 0.0 || $drawdown > 25.0) {
                $p['threshold'] = min(0.78, $p['threshold'] + 0.07);
                $p['margin'] = min(0.26, $p['margin'] + 0.04);
                $p['min_f1'] = min(0.40, $p['min_f1'] + 0.06);
                $p['position_fraction'] = max(0.10, $p['position_fraction'] - 0.12);
                $p['allow_high_risk'] = false;
                $p['feedback_adjustment'] = 'more_restrictive_after_negative_100_bucket';
            } else {
                $p['feedback_adjustment'] = 'neutral_100_bucket';
            }
        } else {
            $p['feedback_adjustment'] = 'insufficient_matured_actions_in_second_phase';
        }

        if ($progress >= 90.0 && $matured >= 1000) {
            $p['adaptive_stage'] = 'phase_2_final_live_preparation';
            $p['exploration_mode'] = false;
            $p['baseline_required'] = true;
            $p['threshold'] = max($p['threshold'], 0.58);
            $p['margin'] = max($p['margin'], 0.10);
            $p['min_f1'] = max($p['min_f1'], 0.18);
            $p['allow_high_risk'] = false;
        }

        $p['phase_description'] = 'Segunda metade do replay: rigor ajustado a cada 100 decisões operacionais amadurecidas.';
        return $p;
    }

    private static function score(float $p,float $margin,array $regime,array $validation,string $side): float
    {
        $quality=max(0,min(1,(float)($validation['policy_f1']??0)*1.6));
        $reg=0.75; $trend=$regime['trend']??'neutral';
        if($side==='comprar'&&$trend==='up')$reg=1.05;
        if($side==='vender'&&$trend==='down')$reg=1.05;
        if(($regime['regime']??'')==='consolidation')$reg*=0.85;
        if(($regime['risk']??'')==='high')$reg*=0.65;
        return max(0,$p*(0.55+0.45*$quality)*(0.75+min(0.25,$margin))*$reg);
    }
}
