<?php
declare(strict_types=1);

$replaceOnce = static function (string $path, string $old, string $new, string $label): void {
    $content = (string) file_get_contents($path);
    if (substr_count($content, $old) !== 1) {
        throw new RuntimeException($label . ' target count != 1');
    }
    file_put_contents($path, str_replace($old, $new, $content));
};

$replaceOnce(
    __DIR__ . '/../app/Runtime/AdminPages/AdminPagesRuntimeOperations03.php',
    <<<'OLD'
                '<div><div class="telemetry-kpi-value">' .
                $trend .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($value) .
                "</b></div><span>" .
OLD,
    <<<'NEW'
                '<div><div class="telemetry-kpi-value">' .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($value) .
                "</b>" .
                $trend .
                "</div><span>" .
NEW,
    'runtime KPI value/trend',
);

$replaceOnce(
    __DIR__ . '/../app/Presentation/AdminPages/AdminPagesPresentationOperations03.php',
    <<<'OLD'
        if ($variation === null) {
            return "";
        }
OLD,
    <<<'NEW'
        if ($variation === null) {
            return '<span class="telemetry-kpi-trend is-neutral is-pending" title="Aguardando base comparável" aria-label="Aguardando base comparável">⌛</span>';
        }
NEW,
    'presentation missing-base',
);

$contractPath = __DIR__ . '/telemetry-json-source-contract-check';
$contract = (string) file_get_contents($contractPath);
$oldContract = <<<'OLD'
$assert(
    str_contains($neutralTrend, 'is-neutral') &&
    str_contains($neutralTrend, '0%') &&
    !str_contains($neutralTrend, '▲') &&
    !str_contains($neutralTrend, '▼') &&
    $missingTrend === '',
    'variação neutra/não comparável deve permanecer minimalista',
);
OLD;
$newContract = <<<'NEW'
$assert(
    str_contains($neutralTrend, 'is-neutral') &&
    str_contains($neutralTrend, '0%') &&
    !str_contains($neutralTrend, '▲') &&
    !str_contains($neutralTrend, '▼'),
    'variação neutra deve permanecer minimalista',
);
$assert(
    str_contains($missingTrend, 'is-pending') &&
    str_contains($missingTrend, '⌛') &&
    !str_contains($missingTrend, '%') &&
    !str_contains($missingTrend, '▲') &&
    !str_contains($missingTrend, '▼'),
    'ausência de base comparável deve exibir somente ampulheta',
);
$valueMarkupPos = strpos($admin3, 'UiComponentsPresentationOperations01::n($value)');
$trendMarkupPos = $valueMarkupPos === false ? false : strpos($admin3, '$trend .', $valueMarkupPos);
$assert(
    $valueMarkupPos !== false &&
    $trendMarkupPos !== false &&
    $trendMarkupPos > $valueMarkupPos,
    'ordem visual dos KPIs deve ser Valor, Triângulo e Percentual',
);
NEW;
if (substr_count($contract, $oldContract) !== 1) {
    throw new RuntimeException('contract neutral/missing target count != 1');
}
$contract = str_replace($oldContract, $newContract, $contract);
$oldUnset = 'unset($root, $assert, $admin2, $admin3, $admin6, $admin9, $login, $presentation3, $speedGeometry, $css, $loadAreaPos, $responseAreaPos, $fillOrderPos, $requestWavePos, $recordWavePos, $positiveTrend, $negativeTrend, $neutralTrend, $missingTrend, $trendCssToken, $visualToken, $opacityToken, $token, $source);';
$newUnset = 'unset($root, $assert, $admin2, $admin3, $admin6, $admin9, $login, $presentation3, $speedGeometry, $css, $loadAreaPos, $responseAreaPos, $fillOrderPos, $requestWavePos, $recordWavePos, $positiveTrend, $negativeTrend, $neutralTrend, $missingTrend, $valueMarkupPos, $trendMarkupPos, $trendCssToken, $visualToken, $opacityToken, $token, $source);';
if (substr_count($contract, $oldUnset) !== 1) {
    throw new RuntimeException('contract unset target count != 1');
}
file_put_contents($contractPath, str_replace($oldUnset, $newUnset, $contract));
