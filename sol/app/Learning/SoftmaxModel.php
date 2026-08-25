<?php
declare(strict_types=1);
namespace Pro\Learning;

final class SoftmaxModel
{
    private array $classes=[-1,0,1]; private array $W=[]; private array $B=[]; private int $d;
    public function __construct(int $d){$this->d=max(1,$d); foreach($this->classes as $c){$this->W[$c]=array_fill(0,$this->d,0.0);$this->B[$c]=0.0;}}
    public function fit(array $X,array $Y,int $epochs=450,float $lr=0.045,float $lambda=0.003): void { $n=count($Y); if(!$n)return; $counts=array_count_values($Y); $cw=[]; foreach($this->classes as $c)$cw[$c]=$n/(3*max(1,($counts[$c]??0))); for($e=0;$e<$epochs;$e++){ $gW=[];$gB=[]; foreach($this->classes as $c){$gW[$c]=array_fill(0,$this->d,0.0);$gB[$c]=0.0;} for($i=0;$i<$n;$i++){ $p=$this->predict($X[$i]); $y=$Y[$i]; $weight=min(3.5,$cw[$y]??1); foreach($this->classes as $c){ $target=$c===$y?1.0:0.0; $err=($p[$c]-$target)*$weight; for($j=0;$j<$this->d;$j++)$gW[$c][$j]+=$err*($X[$i][$j]??0)+$lambda*$this->W[$c][$j]; $gB[$c]+=$err; } } foreach($this->classes as $c){ for($j=0;$j<$this->d;$j++)$this->W[$c][$j]-=$lr*$gW[$c][$j]/$n; $this->B[$c]-=$lr*$gB[$c]/$n; } } }
    public function predict(array $x): array { $s=[]; foreach($this->classes as $c){$z=$this->B[$c]; for($j=0;$j<$this->d;$j++)$z+=$this->W[$c][$j]*($x[$j]??0); $s[$c]=$z;} $m=max($s); $sum=0;$p=[]; foreach($this->classes as $c){$p[$c]=exp($s[$c]-$m);$sum+=$p[$c];} if($sum<=0)return [-1=>0,0=>1,1=>0]; foreach($this->classes as $c)$p[$c]/=$sum; return $p; }
    public function pack(): array { return ['d'=>$this->d,'W'=>$this->W,'B'=>$this->B]; }
    public static function unpack(array $a): self { $m=new self((int)($a['d']??1)); $m->W=$a['W']??$m->W; $m->B=$a['B']??$m->B; return $m; }
}
