<?php
declare(strict_types=1);
namespace Pro\Features;

final class TechnicalIndicators
{
    public static function ema(array $x, int $p): array { if(!$x) return []; $k=2/($p+1); $e=(float)$x[0]; $r=[$e]; for($i=1;$i<count($x);$i++){ $e=(float)$x[$i]*$k+$e*(1-$k); $r[]=$e; } return $r; }
    public static function sma(array $x, int $p): array { $r=[]; for($i=0;$i<count($x);$i++){ $s=array_slice($x,max(0,$i-$p+1),min($p,$i+1)); $r[]=array_sum($s)/max(1,count($s)); } return $r; }
    public static function rsi(array $c, int $p=14): float { $n=count($c); if($n<$p+1)return 50.0; $g=0;$l=0; for($i=$n-$p;$i<$n;$i++){ $d=$c[$i]-$c[$i-1]; if($d>=0)$g+=$d; else $l-=$d; } if($l==0)return 100.0; return 100 - 100/(1+$g/$l); }
    public static function atr(array $h,array $l,array $c,int $p=14): float { $tr=[]; for($i=1;$i<count($c);$i++) $tr[]=max($h[$i]-$l[$i], abs($h[$i]-$c[$i-1]), abs($l[$i]-$c[$i-1])); $s=array_slice($tr,-$p); return $s?array_sum($s)/count($s):0.0; }
    public static function stdev(array $x): float { $n=count($x); if(!$n)return 0; $m=array_sum($x)/$n; $v=0; foreach($x as $a)$v+=($a-$m)**2; return sqrt($v/$n); }
    public static function bollinger(array $c, int $p=20, float $m=2.0): array { $s=array_slice($c,-$p); if(!$s){return ['upper'=>0,'middle'=>0,'lower'=>0,'width'=>0,'pos'=>0.5];} $avg=array_sum($s)/count($s); $sd=self::stdev($s); $u=$avg+$m*$sd; $lo=$avg-$m*$sd; $last=(float)end($c); return ['upper'=>$u,'middle'=>$avg,'lower'=>$lo,'width'=>$avg?($u-$lo)/$avg:0,'pos'=>($u-$lo)?($last-$lo)/($u-$lo):0.5]; }
    public static function adx(array $h,array $l,array $c,int $p=14): float { $n=count($c); if($n<$p+1)return 0; $tr=$plus=$minus=[]; for($i=1;$i<$n;$i++){ $tr[]=max($h[$i]-$l[$i],abs($h[$i]-$c[$i-1]),abs($l[$i]-$c[$i-1])); $up=$h[$i]-$h[$i-1]; $dn=$l[$i-1]-$l[$i]; $plus[]=($up>$dn&&$up>0)?$up:0; $minus[]=($dn>$up&&$dn>0)?$dn:0; } $atr=array_sum(array_slice($tr,-$p))/$p; if($atr<=0)return 0; $pdi=(array_sum(array_slice($plus,-$p))/$atr)*100; $mdi=(array_sum(array_slice($minus,-$p))/$atr)*100; return ($pdi+$mdi)>0 ? abs($pdi-$mdi)/($pdi+$mdi)*100 : 0; }
}
