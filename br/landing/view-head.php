<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?php echo br_h($brLandingTitle); ?></title>
  <meta name="description" content="<?php echo br_h($brLandingDescription); ?>">
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
  <meta name="author" content="Prontoo">
  <meta name="theme-color" content="#f4f7f4">
  <meta name="application-name" content="Prontoo">
  <meta name="apple-mobile-web-app-title" content="Prontoo">
  <link rel="canonical" href="<?php echo br_h($brLandingCanonical); ?>">
  <link rel="alternate" hreflang="pt-BR" href="<?php echo br_h($brLandingCanonical); ?>">
  <link rel="alternate" hreflang="x-default" href="<?php echo br_h($brLandingCanonical); ?>">
  <link rel="icon" href="../public/assets/favicon-<?php echo br_h($brLandingAssetRevision); ?>.ico">
  <link rel="preload" as="image" href="../public/assets/prontoo-mark-<?php echo br_h($brLandingAssetRevision); ?>.png">
  <link rel="preload" as="image" href="images/prontoo-rotina.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,-25..200&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/landing.css?v=<?= br_h(BR_LANDING_VERSION) ?>">

  <meta property="og:type" content="website">
  <meta property="og:locale" content="pt_BR">
  <meta property="og:site_name" content="Prontoo">
  <meta property="og:title" content="<?php echo br_h($brLandingTitle); ?>">
  <meta property="og:description" content="<?php echo br_h($brLandingDescription); ?>">
  <meta property="og:url" content="<?php echo br_h($brLandingCanonical); ?>">
  <meta property="og:image" content="<?php echo br_h($brLandingLogo); ?>">

  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?php echo br_h($brLandingTitle); ?>">
  <meta name="twitter:description" content="<?php echo br_h($brLandingDescription); ?>">
  <meta name="twitter:image" content="<?php echo br_h($brLandingLogo); ?>">

  <script type="application/ld+json"><?php echo json_encode(
      $brLandingStructuredData,
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
  ); ?></script>
</head>
<body class="br-page">
  <a class="br-skip" href="#conteudo">Pular para o conteúdo</a>

  <header class="br-topbar" role="banner">
    <div class="br-shell br-topbar__inner">
      <a class="br-brand" href="#inicio" aria-label="Prontoo — início">
        <img src="../public/assets/prontoo-mark-<?php echo br_h($brLandingAssetRevision); ?>.png" alt="" class="br-brand__mark" width="40" height="40">
        <span class="br-brand__name">Prontoo</span>
      </a>
      <nav class="br-nav" aria-label="Navegação principal">
        <a href="#como-funciona">Como funciona</a>
        <a href="#recursos">Recursos</a>
        <a href="#equipe">Para sua equipe</a>
      </nav>
      <div class="br-topbar__actions">
        <a class="br-login" href="../index.php">Entrar</a>
        <a class="br-btn br-btn--small" href="../index.php?r=signup">Criar consultório</a>
      </div>
    </div>
  </header>

