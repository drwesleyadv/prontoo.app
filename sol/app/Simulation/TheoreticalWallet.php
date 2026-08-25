<?php
declare(strict_types=1);
namespace Pro\Simulation;

use Pro\Core\Config;

final class TheoreticalWallet
{
    private const INITIAL_USD = 1000.0;

    public static function simulate(array $signals, array $candles, ?array $policy = null): ?array
    {
        if (!$signals) return null;
        $state = self::state($policy);
        $equity = [self::INITIAL_USD];
        $trades = [];
        $mark = self::lastPrice($candles);
        foreach ($signals as $signal) {
            $price = self::eventPrice($signal, (float)($signal['close'] ?? $mark));
            if ($price <= 0) continue;
            $trade = self::apply($state, (string)($signal['acao'] ?? 'manter'), $price, [
                'timestamp'=>(int)($signal['timestamp'] ?? time()),
                'decision_t'=>(int)($signal['decision_t'] ?? $signal['reference_candle_t'] ?? 0),
                'execution_t'=>(int)($signal['execution_t'] ?? $signal['timestamp'] ?? time()),
                'source'=>'recorded_signal_price',
            ]);
            if ($trade) $trades[] = $trade;
            self::mark($state, $equity, $price);
        }
        $summary = self::summary($state, $trades, $equity, $mark > 0 ? $mark : $state['last_price']);
        $summary['metodo'] = 'Ledger imutável com custos, slippage e execução no preço registrado do sinal.';
        return $summary;
    }

    public static function backtest(array $actions, array $meta, array $policy = []): array
    {
        if (!$actions || !$meta) return self::emptyBacktest();
        $state = self::state($policy);
        $equity = [self::INITIAL_USD];
        $trades = [];
        $first = 0.0;
        $last = 0.0;
        foreach ($actions as $i => $action) {
            $m = $meta[$i] ?? [];
            $price = (float)($m['execution_price'] ?? $m['close'] ?? 0);
            if ($price <= 0) continue;
            if ($first <= 0) $first = $price;
            $last = (float)($m['exit_price'] ?? $m['close'] ?? $price);
            $trade = self::apply($state, (string)$action, $price, [
                'timestamp'=>(int)($m['execution_t'] ?? $m['t'] ?? 0),
                'decision_t'=>(int)($m['decision_t'] ?? $m['t'] ?? 0),
                'execution_t'=>(int)($m['execution_t'] ?? $m['t'] ?? 0),
                'source'=>'walk_forward_next_candle_execution',
            ]);
            if ($trade) $trades[] = $trade;
            self::mark($state, $equity, $last > 0 ? $last : $price);
        }
        $summary = self::summary($state, $trades, $equity, $last);
        $net = ((float)$summary['saldo_atual_usd'] - self::INITIAL_USD) / self::INITIAL_USD * 100;
        $buyHold = 0.0;
        if ($first > 0 && $last > 0) {
            $entry = $first * (1 + (float)Config::get('slippage'));
            $exit = $last * (1 - (float)Config::get('slippage') - (float)Config::get('trade_fee'));
            $buyHold = ($exit - $entry) / $entry * 100;
        }
        return [
            'final_usd'=>round((float)$summary['saldo_atual_usd'],2),
            'net_return'=>round($net,2),
            'buy_hold_return'=>round($buyHold,2),
            'max_drawdown'=>(float)$summary['max_drawdown'],
            'num_trades'=>(int)$summary['num_trades'],
            'closed_trades'=>(int)$summary['trades_fechados'],
            'win_rate'=>(float)$summary['win_rate'],
            'sharpe'=>(float)$summary['sharpe_ratio'],
            'beats_cash'=>$net > 0,
            'beats_buy_hold'=>$net > $buyHold,
        ];
    }

    public static function emptyBacktest(): array
    {
        return ['final_usd'=>self::INITIAL_USD,'net_return'=>0.0,'buy_hold_return'=>0.0,'max_drawdown'=>0.0,'num_trades'=>0,'closed_trades'=>0,'win_rate'=>0.0,'sharpe'=>0.0,'beats_cash'=>false,'beats_buy_hold'=>false];
    }

    private static function state(?array $policy): array
    {
        return [
            'usd'=>self::INITIAL_USD,
            'sol'=>0.0,
            'position'=>'USD',
            'avg_cost'=>0.0,
            'invested'=>0.0,
            'last_price'=>0.0,
            'fraction'=>max(0.05,min(1.0,(float)($policy['position_fraction'] ?? Config::get('theoretical_position_fraction',1.0)))),
            'fee'=>(float)Config::get('trade_fee'),
            'slippage'=>(float)Config::get('slippage'),
        ];
    }

    private static function apply(array &$state, string $action, float $reference, array $event): ?array
    {
        $state['last_price'] = $reference;
        if ($action === 'comprar') {
            if ($state['position'] === 'SOL' || $state['usd'] <= 1) return null;
            $capital = $state['usd'] * $state['fraction'];
            $execution = $reference * (1 + $state['slippage']);
            $fee = $capital * $state['fee'];
            if ($capital + $fee > $state['usd']) {
                $capital = $state['usd'] / (1 + $state['fee']);
                $fee = $capital * $state['fee'];
            }
            $qty = $execution > 0 ? $capital / $execution : 0;
            if ($qty <= 0) return null;
            $state['usd'] -= $capital + $fee;
            $state['sol'] = $qty;
            $state['position'] = 'SOL';
            $state['avg_cost'] = $execution;
            $state['invested'] = $capital + $fee;
            return self::trade($event,'comprar',$execution,$reference,$qty,$fee,$state,null,null);
        }
        if ($action === 'vender') {
            if ($state['position'] !== 'SOL' || $state['sol'] <= 0.00000001) return null;
            $execution = $reference * (1 - $state['slippage']);
            $qty = $state['sol'];
            $gross = $qty * $execution;
            $fee = $gross * $state['fee'];
            $net = $gross - $fee;
            $invested = $state['invested'];
            $pnl = $net - $invested;
            $pct = $invested > 0 ? $pnl / $invested * 100 : 0;
            $state['usd'] += $net;
            $state['sol'] = 0.0;
            $state['position'] = 'USD';
            $state['avg_cost'] = 0.0;
            $state['invested'] = 0.0;
            return self::trade($event,'vender',$execution,$reference,$qty,$fee,$state,$pnl,$pct);
        }
        return null;
    }

    private static function trade(array $event,string $action,float $execution,float $reference,float $qty,float $fee,array $state,?float $pnl,?float $pct): array
    {
        return ['timestamp'=>$event['timestamp'],'decision_t'=>$event['decision_t'],'execution_t'=>$event['execution_t'],'acao'=>$action,'preco'=>round($execution,6),'preco_referencia'=>round($reference,6),'quantidade_sol'=>round($qty,8),'taxa_usd'=>round($fee,4),'saldo_usd'=>round($state['usd'],2),'saldo_sol'=>round($state['sol'],8),'pnl_usd'=>$pnl===null?null:round($pnl,2),'pnl_percentual'=>$pct===null?null:round($pct,2),'source'=>$event['source']];
    }

    private static function mark(array &$state, array &$equity, float $price): void
    {
        if ($price > 0) $state['last_price'] = $price;
        $equity[] = $state['usd'] + $state['sol'] * max(0.0,$state['last_price']);
    }

    private static function summary(array $state,array $trades,array $equity,float $mark): array
    {
        $mark = $mark > 0 ? $mark : $state['last_price'];
        $marked = $state['usd'] + $state['sol'] * $mark;
        $liquid = $state['usd'] + ($state['sol'] > 0 && $mark > 0 ? $state['sol']*$mark*(1-$state['slippage']-$state['fee']) : 0);
        $closed = array_values(array_filter($trades,static fn($t)=>($t['acao']??'')==='vender'));
        $wins = count(array_filter($closed,static fn($t)=>(float)($t['pnl_usd']??0)>0));
        return ['saldo_atual_usd'=>round($liquid,2),'patrimonio_marcado_usd'=>round($marked,2),'posicao'=>$state['sol']>0?'SOL':'USD','saldo_sol'=>round($state['sol'],8),'preco_medio'=>round($state['avg_cost'],6),'variacao_percentual'=>round(($liquid-self::INITIAL_USD)/self::INITIAL_USD*100,2),'operacoes'=>$trades,'num_trades'=>count($trades),'trades_fechados'=>count($closed),'win_rate'=>count($closed)?round($wins/count($closed)*100,2):0.0,'sharpe_ratio'=>self::sharpe($equity),'max_drawdown'=>round(self::drawdown($equity),2),'ledger_version'=>'immutable_signal_execution_v3'];
    }

    private static function eventPrice(array $signal,float $fallback): float
    {
        foreach (['execution_price','book_mid','close'] as $key) if ((float)($signal[$key]??0)>0) return (float)$signal[$key];
        return $fallback;
    }

    private static function lastPrice(array $candles): float
    {
        return $candles ? (float)($candles[count($candles)-1]['c'] ?? 0) : 0.0;
    }

    private static function drawdown(array $equity): float
    {
        $peak=self::INITIAL_USD;$dd=0.0;foreach($equity as $v){$v=(float)$v;$peak=max($peak,$v);if($peak>0)$dd=max($dd,($peak-$v)/$peak*100);}return $dd;
    }

    private static function sharpe(array $equity): float
    {
        if(count($equity)<3)return 0.0;$returns=[];for($i=1;$i<count($equity);$i++)if($equity[$i-1]>0)$returns[]=($equity[$i]-$equity[$i-1])/$equity[$i-1];if(count($returns)<2)return 0.0;$avg=array_sum($returns)/count($returns);$variance=0.0;foreach($returns as $r)$variance+=($r-$avg)**2;$std=sqrt($variance/count($returns));return $std>0?round($avg/$std*sqrt(365*48),2):0.0;
    }
}