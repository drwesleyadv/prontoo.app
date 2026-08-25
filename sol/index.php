<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>SOL/USDT – Painel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="container">
<header class="topline">
<div class="avatar" id="avatar" title="Editar carteira"><canvas id="avatarCanvas" width="44" height="44"></canvas></div>
<div class="market-state"><span class="dot" id="marketDot"></span><span id="marketStatus">Conectando</span></div>
</header>
<section class="row-cards">
<div class="card metric-card"><span class="label">Preço</span><span class="value" id="currentPrice">$ --</span><span class="change" id="change24h">--</span></div>
<div class="card metric-card"><span class="label">Volume</span><span class="value" id="volume24hValue">--</span><span class="change" id="volumeHourlyChange">--</span></div>
<div class="card metric-card"><span class="label">Total</span><span class="value" id="carteiraUsdValue">$ --</span><span class="change" id="carteiraUsdChange">--</span></div>
<div class="card metric-card" id="pnlCard"><span class="label" id="pnlLabel">Lucro</span><span class="value" id="pnlValue">$ --</span><span class="change" id="pnlChange">--</span></div>
</section>
<section class="card analysis-card">
<div class="bar-item">
<div class="bar-head"><span>Preço · faixa 24h</span><strong id="priceBarValue">--</strong></div>
<div class="bar-track price-track" id="priceBarTrack"><div class="bar-pointer" id="priceBarPointer" style="left:50%"></div></div>
<div class="bar-labels"><span id="priceMinValue">--</span><span id="priceMaxValue">--</span></div>
</div>
<div class="bar-item">
<div class="bar-head"><span>Volume · 30 min</span><strong id="volumeBarValue">--</strong></div>
<div class="bar-track volume-track" id="volumeBarTrack"><div class="bar-pointer" id="volumeBarPointer" style="left:50%"></div></div>
<div class="bar-labels"><span id="volumeMinValue">--</span><span id="volumeMaxValue">--</span></div>
</div>
<div class="bar-item">
<div class="bar-head"><span>RSI · 14</span><strong id="rsiBarValueNum">50,0</strong></div>
<div class="bar-track" id="rsiBarTrack"><div class="rsi-zones"><div class="rsi-zone-1"></div><div class="rsi-zone-2"></div><div class="rsi-zone-3"></div></div><div class="rsi-ticks"><i style="left:30%"></i><i style="left:70%"></i></div><div class="bar-pointer" id="rsiBarPointer" style="left:50%"></div></div>
<div class="bar-labels"><span>Sobrevenda</span><span id="rsiBarValueLabel">Neutro</span><span>Sobrecompra</span></div>
</div>
</section>
<section class="chart-card" id="chartCard">
<div class="chart-head"><div><span class="chart-label">SOL/USDT</span><strong>Velas · 30 min</strong></div><div class="chart-meta" id="chartMeta">96 períodos</div></div>
<canvas id="candleCanvas"></canvas>
</section>
<section class="card footer-card"><span id="sourceLabel">Fonte: Binance · atualização ao vivo</span><span id="lastUpdate">--</span></section>
</main>
<div class="modal-carteira" id="carteiraModal" aria-hidden="true">
<div class="modal-content">
<h2>Carteira local</h2>
<p class="modal-intro">Os valores ficam somente neste navegador.</p>
<div class="field"><label>Capital integralizado (R$)</label><input id="inInteg" type="text" inputmode="decimal" placeholder="0,00"></div>
<div class="field"><label>SOL</label><input id="inSol" type="text" inputmode="decimal" placeholder="0,0000"></div>
<div class="field"><label>USDC / USDT</label><input id="inUsdc" type="text" inputmode="decimal" placeholder="0,00"></div>
<div class="field"><label>Preço médio SOL (USD)</label><input id="inPrecoMedio" type="text" inputmode="decimal" placeholder="Opcional"></div>
<div class="btn-row"><button class="btn save" id="saveCarteiraBtn">Salvar</button><button class="btn" id="closeCarteiraBtn">Cancelar</button></div>
<div class="note" id="carteiraNote"></div>
</div>
</div>
<script src="js/app.js"></script>
</body>
</html>
