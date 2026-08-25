<?php
declare(strict_types=1);
namespace Pro\Learning;

final class CalibrationMap
{
    private array $bins=[];
    public function fit(array $probabilities, array $labels): void
    {
        $this->bins=[]; foreach([-1,0,1] as $cls) for($b=0;$b<10;$b++) $this->bins[$cls][$b]=['n'=>0,'hit'=>0];
        foreach($probabilities as $i=>$p){ foreach([-1,0,1] as $cls){ $val=max(0,min(0.999,(float)($p[$cls]??0))); $b=(int)floor($val*10); $this->bins[$cls][$b]['n']++; if(($labels[$i]??null)===$cls)$this->bins[$cls][$b]['hit']++; } }
    }
    public function calibrate(array $p): array
    {
        $out=[]; foreach([-1,0,1] as $cls){ $val=max(0,min(0.999,(float)($p[$cls]??0))); $b=(int)floor($val*10); $cell=$this->bins[$cls][$b]??['n'=>0,'hit'=>0]; $n=$cell['n']; $hit=$cell['hit']; $prior=$val; $emp=($hit+2*$prior)/($n+2); $shrink=min(1.0,$n/80); $out[$cls]=$prior*(1-$shrink)+$emp*$shrink; }
        $sum=array_sum($out); if($sum<=0)return [-1=>0,0=>1,1=>0]; foreach($out as $k=>$v)$out[$k]=$v/$sum; return $out;
    }
    public function quality(array $probabilities, array $labels): array
    {
        $brier=0;$log=0;$n=max(1,count($labels)); $eceNum=0;$eceDen=0;
        foreach($probabilities as $i=>$p){ $c=$labels[$i]??0; foreach([-1,0,1] as $cls){$y=$cls===$c?1:0; $q=max(1e-6,min(1-1e-6,(float)($p[$cls]??0))); $brier+=($q-$y)**2; if($y)$log-=log($q);} $best=array_keys($p,max($p))[0]??0; $conf=max($p); $eceNum += abs(($best===$c?1:0)-$conf); $eceDen++; }
        return ['brier_score'=>round($brier/$n,4),'log_loss'=>round($log/$n,4),'ece'=>round($eceNum/max(1,$eceDen),4)];
    }
    public function pack(): array { return ['bins'=>$this->bins]; }
    public static function unpack(array $a): self { $c=new self; $c->bins=$a['bins']??[]; return $c; }
}
