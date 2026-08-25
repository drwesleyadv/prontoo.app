<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="referrer" content="no-referrer">
<title>SOL/USDT — Painel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="shell">
<header class="topbar"><div><span class="eyebrow">SOLANA</span><h1>SOL/USDT</h1></div><div class="status"><span id="dot" class="dot"></span><span id="statusText">Conectando</span></div></header>
<section class="hero"><div><span class="label">Preço agora</span><strong id="price" class="hero-price">$ --</strong><span id="source" class="muted">Binance</span></div><div id="change24" class="hero-change neutral">--</div></section>
<section class="grid">
<article class="card"><span class="label">Máxima 24h</span><strong id="high24">$ --</strong></article>
<article class="card"><span class="label">Mínima 24h</span><strong id="low24">$ --</strong></article>
<article class="card"><span class="label">Volume 24h</span><strong id="volume24">--</strong></article>
<article class="card"><span class="label">Preço em BRL</span><strong id="priceBrl">R$ --</strong></article>
</section>
<section class="panel"><div class="panel-head"><div><span class="label">Últimas 2 horas</span><h2>Movimento do preço</h2></div><span id="rangeText" class="muted">--</span></div><canvas id="chart" height="310"></canvas></section>
<section class="grid indicators">
<article class="card wide"><div class="row"><span class="label">Posição no intervalo</span><span id="rangeValue">--</span></div><div class="track"><i id="rangePointer"></i></div><div class="bounds"><span id="rangeMin">--</span><span id="rangeMax">--</span></div></article>
<article class="card"><span class="label">RSI 14</span><strong id="rsi">--</strong><span id="rsiLabel" class="muted">--</span></article>
<article class="card"><span class="label">Spread</span><strong id="spread">--</strong><span class="muted">bid / ask</span></article>
</section>
<footer>Uso pessoal · dados públicos de mercado · sem integração com o Prontoo</footer>
</main>
<script src="js/app.js"></script>
</body>
</html>
