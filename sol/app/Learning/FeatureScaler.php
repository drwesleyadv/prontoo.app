<?php
declare(strict_types=1);
namespace Pro\Learning;

final class FeatureScaler
{
    public array $mean=[]; public array $scale=[];
    public function fit(array $X): void { $n=count($X); if(!$n)return; $d=count($X[0]); $this->mean=array_fill(0,$d,0.0); $this->scale=array_fill(0,$d,1.0); foreach($X as $r)for($j=0;$j<$d;$j++)$this->mean[$j]+=(float)($r[$j]??0); for($j=0;$j<$d;$j++)$this->mean[$j]/=$n; $var=array_fill(0,$d,0.0); foreach($X as $r)for($j=0;$j<$d;$j++)$var[$j]+=(((float)($r[$j]??0))-$this->mean[$j])**2; for($j=0;$j<$d;$j++){ $s=sqrt($var[$j]/max(1,$n)); $this->scale[$j]=$s>1e-12?$s:1.0; } }
    public function transform(array $X): array { $out=[]; foreach($X as $r){$nr=[]; for($j=0;$j<count($r);$j++)$nr[] = (((float)$r[$j]-($this->mean[$j]??0))/($this->scale[$j]??1)); $out[]=$nr;} return $out; }
    public function pack(): array { return ['mean'=>$this->mean,'scale'=>$this->scale]; }
    public static function unpack(array $a): self { $s=new self; $s->mean=$a['mean']??[]; $s->scale=$a['scale']??[]; return $s; }
}
