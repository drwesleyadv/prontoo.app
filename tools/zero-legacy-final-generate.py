from pathlib import Path
import subprocess

root = Path(__file__).resolve().parents[1]
php = r'''<?php
require 'app/Core/Architecture/LayerMap.php';
use Prontoo\Core\Architecture\LayerMap;
$items=[];
foreach (LayerMap::phpFiles(getcwd()) as $file) {
    $root=str_replace('\\','/',getcwd());
    $path=str_replace('\\','/',$file);
    $rel=ltrim(str_replace($root,'',$path),'/');
    if (!LayerMap::isNativePath($rel)) $items[]=$rel;
}
sort($items,SORT_STRING);
fwrite(STDOUT, json_encode(['count'=>count($items),'files'=>$items], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
?>'''
result = subprocess.run(['php'], input=php, text=True, cwd=root, capture_output=True)
print(result.stdout)
if result.stderr:
    print(result.stderr)
raise SystemExit('inventory-only')
