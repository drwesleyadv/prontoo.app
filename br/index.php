<?php

const BR_LANDING_ORIGIN = "https://prontoo.app";
const BR_LANDING_VERSION_FALLBACK = "1.7.17.2";
function br_landing_release_metadata(): array
{
    static $metadata = null;
    if (is_array($metadata)) {
        return $metadata;
    }
    $metadata = [];
    $file = dirname(__DIR__) . "/version.json";
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($json)) {
            $metadata = $json;
        }
    }
    return $metadata;
}
$brLandingReleaseMetadata = br_landing_release_metadata();
$brLandingVersion = trim((string) ($brLandingReleaseMetadata["version"] ?? ""));
$brLandingAssetRevision = trim((string) ($brLandingReleaseMetadata["asset_version"] ?? ""));
if (!preg_match('/^1\.\d{1,2}\.\d{1,2}\.\d+$/', $brLandingVersion)) {
    $brLandingVersion = BR_LANDING_VERSION_FALLBACK;
}
if (!preg_match('/^1\.\d{1,2}\.\d{1,2}\.\d+$/', $brLandingAssetRevision)) {
    $brLandingAssetRevision = $brLandingVersion;
}
define("BR_LANDING_VERSION", $brLandingVersion);
unset($brLandingReleaseMetadata, $brLandingVersion);
$brLandingRequestStartedAt = microtime(true);

function br_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function br_landing_url(string $path = ""): string
{
    return rtrim(BR_LANDING_ORIGIN, "/") . "/" . ltrim($path, "/");
}

function br_landing_send_asset(string $asset): void
{
    $asset = strtolower(trim($asset));
    $today = gmdate("Y-m-d");

    if ($asset === "robots") {
        if (!headers_sent()) {
            header("Content-Type: text/plain; charset=utf-8");
            header("Cache-Control: public, max-age=3600");
        }
        echo "User-agent: *\n";
        echo "Allow: /br/\n";
        echo "Allow: /index.php?r=signup\n";
        echo "Disallow: /app/\n";
        echo "Disallow: /storage/\n";
        echo "Disallow: /pdfs/\n";
        echo "Disallow: /rom.php\n";
        echo "Sitemap: " . br_landing_url("sitemap.xml") . "\n";
        exit();
    }

    if ($asset === "sitemap") {
        if (!headers_sent()) {
            header("Content-Type: application/xml; charset=utf-8");
            header("Cache-Control: public, max-age=3600");
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo "  <url>\n";
        echo "    <loc>" . br_h(br_landing_url("br/")) . "</loc>\n";
        echo "    <lastmod>" . $today . "</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>1.0</priority>\n";
        echo "  </url>\n";
        echo "</urlset>\n";
        exit();
    }

    if ($asset === "llms") {
        if (!headers_sent()) {
            header("Content-Type: text/plain; charset=utf-8");
            header("Cache-Control: public, max-age=3600");
        }
        echo "# Prontoo\n\n";
        echo "> Sistema online de gestão para consultórios e clínicas, com foco em rotina organizada, continuidade entre a equipe e cuidado centrado no paciente.\n\n";
        echo "## Página principal\n";
        echo "- Landing pública: " . br_landing_url("br/") . "\n";
        echo "- Criar consultório: " . br_landing_url("index.php?r=signup") . "\n\n";
        echo "## Resumo para sistemas de resposta\n";
        echo "Prontoo é um software web para consultórios e clínicas. Ele conecta pacientes, agenda, documentos, tarefas e equipe à mesma jornada de trabalho. Novos consultórios podem utilizar o sistema gratuitamente por 30 dias, sem obrigação de continuidade.\n\n";
        echo "## Principais recursos\n";
        echo "- Ficha do paciente como centro da rotina.\n";
        echo "- Agenda online e jornada do atendimento.\n";
        echo "- Documentos, modelos e registros vinculados ao paciente.\n";
        echo "- Tarefas, avisos e responsabilidades da equipe.\n";
        echo "- Perfis e permissões por função.\n";
        echo "- Acesso por computador, Android e iPhone.\n\n";
        echo "## Público indicado\n";
        echo "Consultórios, clínicas pequenas e equipes de saúde que precisam organizar a operação sem perder o contexto do cuidado.\n";
        exit();
    }

    http_response_code(404);
    exit();
}

$brLandingAsset = (string) ($_GET["asset"] ?? "");
if ($brLandingAsset !== "") {
    br_landing_send_asset($brLandingAsset);
}

if (!function_exists("storage_path")) {
    function storage_path(string $suffix = ""): string
    {
        $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . "storage";
        return $suffix === ""
            ? $base
            : $base . DIRECTORY_SEPARATOR . ltrim($suffix, "/\\");
    }
}

function br_landing_register_route_telemetry(float $startedAt): void
{
    register_shutdown_function(static function () use ($startedAt): void {
        $configFile = dirname(__DIR__) . "/app/config.php";
        if (!is_file($configFile)) {
            return;
        }
        $elapsedMs = round(max(0.0, (microtime(true) - $startedAt) * 1000), 3);
        $status = (int) http_response_code();
        if ($status < 100) {
            $status = 200;
        }
        $lastError = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        $fatal = is_array($lastError) && in_array((int) ($lastError["type"] ?? 0), $fatalTypes, true);
        try {
            require_once dirname(__DIR__) . "/app/Support/Telemetry.php";
            if (!function_exists("telemetry_append_route_performance_metric")) {
                return;
            }
            telemetry_append_route_performance_metric([
                "ts" => time(),
                "route" => "landing",
                "queries" => 0,
                "elapsed_ms" => $elapsedMs,
                "query_ms" => 0.0,
                "success" => !$fatal && $status < 500 ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log("[Prontoo landing telemetry] " . $e->getMessage());
        }
    });
}

br_landing_register_route_telemetry($brLandingRequestStartedAt);

$brLandingCanonical = br_landing_url("br/");
$brLandingSignup = br_landing_url("index.php?r=signup");
$brLandingLogo = br_landing_url("public/assets/prontoo-mark-" . rawurlencode($brLandingAssetRevision) . ".png");
$brLandingDescription =
    "Organize pacientes, agenda, documentos, tarefas e equipe no mesmo fluxo. Experimente o Prontoo gratuitamente por 30 dias.";
$brLandingTitle = "Prontoo | Rotina organizada para o cuidado acontecer melhor";

$brLandingFaq = [
    [
        "q" => "O que é o Prontoo?",
        "a" =>
            "Prontoo é um sistema online que conecta pacientes, agenda, documentos, tarefas e equipe à mesma rotina de trabalho do consultório.",
    ],
    [
        "q" => "O período gratuito dura quanto tempo?",
        "a" =>
            "Novos consultórios podem utilizar os recursos do Prontoo gratuitamente por 30 dias, sem obrigação de continuidade.",
    ],
    [
        "q" => "Para quem o Prontoo é indicado?",
        "a" =>
            "O sistema foi pensado para consultórios, clínicas pequenas e equipes de saúde que precisam reduzir controles paralelos e preservar o contexto da jornada do paciente.",
    ],
    [
        "q" => "O Prontoo funciona no celular?",
        "a" =>
            "Sim. O Prontoo funciona pela web e pode ser acessado no computador, Android e iPhone, respeitando as permissões de cada perfil.",
    ],
    [
        "q" => "Como a equipe trabalha no mesmo fluxo?",
        "a" =>
            "Recepção, Triagem, Profissional e Administrativo atuam em ambientes próprios, enquanto as informações permanecem vinculadas ao mesmo paciente e à mesma rotina.",
    ],
    [
        "q" => "Como o Prontoo controla o acesso?",
        "a" =>
            "Cada colaborador acessa os recursos compatíveis com sua função. A gestão acompanha permissões, atividades e organização do consultório.",
    ],
];

$brLandingStructuredData = [
    "@context" => "https://schema.org",
    "@graph" => [
        [
            "@type" => "Organization",
            "@id" => br_landing_url("#organization"),
            "name" => "Prontoo",
            "url" => br_landing_url(),
            "logo" => [
                "@type" => "ImageObject",
                "url" => $brLandingLogo,
            ],
            "description" =>
                "Sistema online para organização operacional de consultórios e clínicas.",
        ],
        [
            "@type" => "WebSite",
            "@id" => br_landing_url("#website"),
            "url" => br_landing_url(),
            "name" => "Prontoo",
            "publisher" => ["@id" => br_landing_url("#organization")],
            "inLanguage" => "pt-BR",
        ],
        [
            "@type" => "WebPage",
            "@id" => $brLandingCanonical . "#webpage",
            "url" => $brLandingCanonical,
            "name" => $brLandingTitle,
            "description" => $brLandingDescription,
            "isPartOf" => ["@id" => br_landing_url("#website")],
            "about" => ["@id" => $brLandingCanonical . "#software"],
            "primaryImageOfPage" => [
                "@type" => "ImageObject",
                "url" => $brLandingLogo,
            ],
            "inLanguage" => "pt-BR",
            "dateModified" => "2026-07-14",
        ],
        [
            "@type" => "SoftwareApplication",
            "@id" => $brLandingCanonical . "#software",
            "name" => "Prontoo",
            "url" => $brLandingCanonical,
            "image" => $brLandingLogo,
            "description" => $brLandingDescription,
            "applicationCategory" => "BusinessApplication",
            "operatingSystem" => "Web",
            "audience" => [
                "@type" => "Audience",
                "audienceType" => "Consultórios, clínicas e equipes de saúde",
            ],
            "featureList" => [
                "Ficha do paciente centralizada",
                "Agenda online",
                "Documentos e modelos do consultório",
                "Tarefas e avisos internos",
                "Permissões por função",
                "Registro de atividades",
                "Jornada do paciente",
            ],
            "offers" => [
                "@type" => "Offer",
                "url" => $brLandingSignup,
                "category" => "Software de gestão clínica",
                "availability" => "https://schema.org/InStock",
                "description" => "Período gratuito de 30 dias para novos consultórios.",
            ],
        ],
        [
            "@type" => "FAQPage",
            "@id" => $brLandingCanonical . "#faq",
            "mainEntity" => array_map(
                static fn(array $item): array => [
                    "@type" => "Question",
                    "name" => $item["q"],
                    "acceptedAnswer" => [
                        "@type" => "Answer",
                        "text" => $item["a"],
                    ],
                ],
                $brLandingFaq,
            ),
        ],
    ],
];

?><!doctype html>
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

  <main id="conteudo">
    <section class="br-hero" id="inicio" aria-labelledby="hero-title">
      <div class="br-shell br-hero__grid">
        <div class="br-hero__copy">
          <p class="br-eyebrow"><span></span>Gestão clínica com continuidade</p>
          <h1 id="hero-title">A rotina do consultório organizada para o cuidado acontecer melhor.</h1>
          <p class="br-lead">O Prontoo reúne pacientes, agenda, documentos, tarefas e equipe em uma rotina clara, para que cada pessoa saiba o que precisa fazer e o cuidado permaneça no centro.</p>

          <div class="br-hero__actions" aria-label="Ações principais">
            <a class="br-btn" href="../index.php?r=signup">Criar consultório gratuitamente</a>
            <a class="br-btn br-btn--ghost" href="#como-funciona">Conhecer o Prontoo</a>
          </div>

          <div class="br-trial-note" aria-label="Condições do período gratuito">
            <strong>30 dias gratuitos</strong>
            <span>Recursos disponíveis para experimentar</span>
            <span>Sem obrigação de continuidade</span>
          </div>

          <div class="br-hero-points" aria-label="Principais áreas organizadas pelo Prontoo">
            <span><i class="material-symbols-rounded" aria-hidden="true">groups</i>Pacientes</span>
            <span><i class="material-symbols-rounded" aria-hidden="true">calendar_month</i>Agenda</span>
            <span><i class="material-symbols-rounded" aria-hidden="true">description</i>Documentos</span>
            <span><i class="material-symbols-rounded" aria-hidden="true">badge</i>Equipe</span>
          </div>
        </div>

        <div class="br-hero__visual">
          <div class="br-product-frame">
            <div class="br-product-frame__bar" aria-hidden="true"><span></span><span></span><span></span></div>
            <img src="images/prontoo-rotina.svg" alt="Visualização do Prontoo conectando pacientes, agenda, documentos e tarefas" width="900" height="620" fetchpriority="high">
          </div>
          <div class="br-floating-note br-floating-note--top"><span>01</span><strong>Uma rotina conectada</strong></div>
          <div class="br-floating-note br-floating-note--bottom"><span>✓</span><strong>Pronto para a próxima etapa</strong></div>
        </div>
      </div>
    </section>

    <section class="br-problem" aria-labelledby="problema-title">
      <div class="br-shell br-problem__grid">
        <div>
          <p class="br-eyebrow"><span></span>Menos partes soltas</p>
          <h2 id="problema-title">Seu consultório não precisa trabalhar em partes separadas.</h2>
        </div>
        <div class="br-problem__body">
          <p>Quando pacientes, agenda, documentos e tarefas ficam espalhados, a equipe perde tempo procurando informações, repetindo registros e tentando entender o que já foi feito. O Prontoo conecta essas etapas para que a rotina avance com mais clareza.</p>
          <div class="br-problem__panel" aria-label="Exemplos de partes que costumam ficar separadas">
            <strong><i class="material-symbols-rounded" aria-hidden="true">hub</i>Quando a rotina se fragmenta</strong>
            <ul>
              <li><i class="material-symbols-rounded" aria-hidden="true">person_search</i>Paciente sem contexto completo</li>
              <li><i class="material-symbols-rounded" aria-hidden="true">event_note</i>Agenda e documentos em controles distintos</li>
              <li><i class="material-symbols-rounded" aria-hidden="true">sync_alt</i>Tarefas sem continuidade entre perfis</li>
            </ul>
            <p>O Prontoo reúne essas etapas no mesmo fluxo.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="br-section" id="recursos" aria-labelledby="recursos-title">
      <div class="br-shell">
        <div class="br-section__head">
          <p class="br-eyebrow"><span></span>Proposta de valor</p>
          <h2 id="recursos-title">Tudo o que sustenta a rotina, no mesmo contexto.</h2>
          <p>Em vez de multiplicar controles, o Prontoo organiza as informações ao redor do paciente e do trabalho da equipe.</p>
        </div>
        <div class="br-value-grid">
          <article class="br-value-card"><span class="br-card-index">01</span><span class="br-card-icon material-symbols-rounded" aria-hidden="true">folder_shared</span><h3>Pacientes, agenda e documentos conectados</h3><p>As informações acompanham o paciente em vez de permanecerem espalhadas.</p></article>
          <article class="br-value-card"><span class="br-card-index">02</span><span class="br-card-icon material-symbols-rounded" aria-hidden="true">groups_2</span><h3>Equipe com papéis bem definidos</h3><p>Cada pessoa atua em sua área sem perder a continuidade do trabalho iniciado por outro perfil.</p></article>
          <article class="br-value-card"><span class="br-card-index">03</span><span class="br-card-icon material-symbols-rounded" aria-hidden="true">task_alt</span><h3>Tarefas e andamento visíveis</h3><p>O consultório acompanha prazos, pendências e atividades concluídas.</p></article>
          <article class="br-value-card"><span class="br-card-index">04</span><span class="br-card-icon material-symbols-rounded" aria-hidden="true">medical_information</span><h3>Prontuário como centro da jornada</h3><p>Cadastro, agenda, documentos e registros permanecem associados ao paciente certo.</p></article>
        </div>
      </div>
    </section>

    <section class="br-section br-section--dark" id="como-funciona" aria-labelledby="fluxo-title">
      <div class="br-shell br-showcase">
        <div class="br-showcase__copy">
          <p class="br-eyebrow br-eyebrow--light"><span></span>Continuidade operacional</p>
          <h2 id="fluxo-title">O que um começa, o outro continua.</h2>
          <p>A Recepção organiza a entrada. A Triagem prepara o contexto. O Profissional conduz o cuidado. O Administrativo acompanha a operação. Tudo segue conectado à mesma ficha do paciente.</p>
          <ol class="br-steps">
            <li><span class="material-symbols-rounded" aria-hidden="true">badge</span><div><strong>Recepção</strong><p>Cadastra, agenda e inicia a jornada.</p></div></li>
            <li><span class="material-symbols-rounded" aria-hidden="true">checklist</span><div><strong>Triagem</strong><p>Complementa informações e organiza pendências.</p></div></li>
            <li><span class="material-symbols-rounded" aria-hidden="true">medical_services</span><div><strong>Profissional</strong><p>Atende com contexto e mantém o cuidado documentado.</p></div></li>
            <li><span class="material-symbols-rounded" aria-hidden="true">admin_panel_settings</span><div><strong>Administrativo</strong><p>Acompanha equipe, tarefas, permissões e andamento.</p></div></li>
          </ol>
          <p class="br-showcase__closing">Cada etapa fica registrada e pronta para o próximo perfil seguir sem perder tempo nem contexto.</p>
        </div>
        <figure class="br-showcase__visual">
          <img src="images/prontoo-fluxo.svg" alt="Fluxo do Prontoo entre Recepção, Triagem, Profissional e Administrativo" width="900" height="620" loading="lazy">
        </figure>
      </div>
    </section>

    <section class="br-section" aria-labelledby="ficha-title">
      <div class="br-shell br-split br-split--visual-first">
        <figure class="br-panel-visual">
          <img src="images/prontoo-ficha.svg" alt="Ficha do paciente no Prontoo reunindo agenda, documentos e registros" width="900" height="620" loading="lazy">
        </figure>
        <div class="br-split__copy">
          <p class="br-eyebrow"><span></span>Ficha do paciente</p>
          <h2 id="ficha-title">O prontuário como ponto de encontro da rotina.</h2>
          <p>Cadastro, agenda, documentos, anotações e responsáveis ficam reunidos na ficha do paciente, reduzindo a dispersão das informações e facilitando a continuidade do cuidado.</p>
          <div class="br-check-list">
            <article><span class="material-symbols-rounded" aria-hidden="true">badge</span><div><strong>Cadastro organizado</strong><p>Os dados permanecem no mesmo contexto do atendimento.</p></div></article>
            <article><span class="material-symbols-rounded" aria-hidden="true">event_available</span><div><strong>Agenda vinculada</strong><p>O histórico de agendamentos acompanha a jornada do paciente.</p></div></article>
            <article><span class="material-symbols-rounded" aria-hidden="true">description</span><div><strong>Documentos e registros</strong><p>Tudo o que foi gerado permanece associado à pessoa certa.</p></div></article>
            <article><span class="material-symbols-rounded" aria-hidden="true">dashboard_customize</span><div><strong>Abas personalizadas</strong><p>O profissional pode criar novas áreas com nome e ícone próprios.</p></div></article>
          </div>
        </div>
      </div>
    </section>

    <section class="br-section br-section--soft" aria-labelledby="rotina-title">
      <div class="br-shell">
        <div class="br-section__head br-section__head--center">
          <p class="br-eyebrow"><span></span>Rotina conectada</p>
          <h2 id="rotina-title">Agenda, documentos, tarefas e atividades no mesmo ritmo do consultório.</h2>
        </div>
        <div class="br-routine-grid">
          <article><span class="material-symbols-rounded" aria-hidden="true">calendar_month</span><h3>Agenda bem distribuída</h3><p>Atendimentos, horários e bloqueios organizados com mais previsibilidade.</p></article>
          <article><span class="material-symbols-rounded" aria-hidden="true">article</span><h3>Documentos no momento certo</h3><p>Modelos próprios ajudam a gerar documentos com mais agilidade.</p></article>
          <article><span class="material-symbols-rounded" aria-hidden="true">assignment_turned_in</span><h3>Tarefas para a equipe</h3><p>Responsabilidades distribuídas, acompanhadas e comentadas.</p></article>
          <article><span class="material-symbols-rounded" aria-hidden="true">monitoring</span><h3>Andamento visível</h3><p>Pendências, prazos e etapas permanecem fáceis de acompanhar.</p></article>
        </div>
        <p class="br-routine-summary">Menos retrabalho. Mais clareza. Uma rotina mais consistente para a equipe e para o paciente.</p>
      </div>
    </section>

    <section class="br-section" id="equipe" aria-labelledby="equipe-title">
      <div class="br-shell">
        <div class="br-section__head">
          <p class="br-eyebrow"><span></span>Para sua equipe</p>
          <h2 id="equipe-title">Cada perfil encontra o que precisa para continuar o trabalho.</h2>
          <p>Os ambientes respeitam a função de cada pessoa sem quebrar a continuidade da jornada.</p>
        </div>

        <div class="br-role-switch">
          <input class="br-role-input" type="radio" name="role" id="role-recepcao" checked>
          <input class="br-role-input" type="radio" name="role" id="role-triagem">
          <input class="br-role-input" type="radio" name="role" id="role-profissional">
          <input class="br-role-input" type="radio" name="role" id="role-administrativo">

          <div class="br-role-nav" aria-label="Perfis do Prontoo">
            <label for="role-recepcao"><i class="material-symbols-rounded" aria-hidden="true">badge</i>Recepção</label>
            <label for="role-triagem"><i class="material-symbols-rounded" aria-hidden="true">checklist</i>Triagem</label>
            <label for="role-profissional"><i class="material-symbols-rounded" aria-hidden="true">medical_services</i>Profissional</label>
            <label for="role-administrativo"><i class="material-symbols-rounded" aria-hidden="true">admin_panel_settings</i>Administrativo</label>
          </div>

          <div class="br-role-panels">
            <article class="br-role-panel br-role-panel--recepcao"><span class="br-role-panel__number">01</span><div><p class="br-kicker">A porta de entrada do cuidado</p><h3>Recepção</h3><p>Organiza cadastro, agendamento, chegada e continuidade para a Triagem.</p></div><ul><li>Agenda e encaixes</li><li>Cadastro de pacientes</li><li>Chegada e jornada</li></ul></article>
            <article class="br-role-panel br-role-panel--triagem"><span class="br-role-panel__number">02</span><div><p class="br-kicker">Preparar melhor para cuidar melhor</p><h3>Triagem</h3><p>Complementa informações e prepara o contexto para o atendimento.</p></div><ul><li>Informações prévias</li><li>Pendências visíveis</li><li>Continuidade do fluxo</li></ul></article>
            <article class="br-role-panel br-role-panel--profissional"><span class="br-role-panel__number">03</span><div><p class="br-kicker">Mais contexto para conduzir o cuidado</p><h3>Profissional</h3><p>Encontra ficha, histórico, documentos e agenda no mesmo lugar.</p></div><ul><li>Prontuário central</li><li>Documentos e modelos</li><li>Agenda profissional</li></ul></article>
            <article class="br-role-panel br-role-panel--administrativo"><span class="br-role-panel__number">04</span><div><p class="br-kicker">Clareza sobre o andamento do consultório</p><h3>Administrativo</h3><p>Acompanha equipe, permissões, tarefas, documentos e rotina operacional.</p></div><ul><li>Equipe e cargos</li><li>Permissões</li><li>Visão operacional</li></ul></article>
          </div>
        </div>
      </div>
    </section>

    <section class="br-section br-section--compact" aria-labelledby="personalizacao-title">
      <div class="br-shell br-personalization">
        <div>
          <p class="br-eyebrow"><span></span>Seu ambiente</p>
          <h2 id="personalizacao-title">Um sistema que acompanha a forma como sua equipe trabalha.</h2>
          <p>O consultório pode personalizar nomes dos setores, cor de destaque e ícones, tornando o sistema mais reconhecível e coerente com sua organização.</p>
        </div>
        <div class="br-availability">
          <div class="br-device-stack" aria-hidden="true"><span><i class="material-symbols-rounded">desktop_windows</i>Web</span><span><i class="material-symbols-rounded">android</i>Android</span><span><i class="material-symbols-rounded">phone_iphone</i>iPhone</span></div>
          <div><strong>No computador, Android e iPhone.</strong><p>A rotina permanece acessível nos dispositivos em que o trabalho acontece.</p></div>
        </div>
      </div>
    </section>

    <section class="br-trial" aria-labelledby="trial-title">
      <div class="br-shell br-trial__box">
        <div class="br-trial__copy">
          <p class="br-eyebrow br-eyebrow--light"><span></span>Comece antes de decidir</p>
          <h2 id="trial-title">30 dias para organizar uma rotina real.</h2>
          <p>Crie seu consultório, conheça os recursos e experimente o Prontoo com sua equipe. O período gratuito não gera obrigação de continuidade.</p>
          <a class="br-btn br-btn--light" href="../index.php?r=signup">Criar consultório gratuitamente</a>
        </div>
        <div class="br-trial__facts" aria-label="Condições do período gratuito">
          <article><span class="material-symbols-rounded" aria-hidden="true">today</span><div><strong>30 dias gratuitos</strong><p>para novos consultórios</p></div></article>
          <article><span class="material-symbols-rounded" aria-hidden="true">verified</span><div><strong>Recursos disponíveis</strong><p>para experimentar a rotina</p></div></article>
          <article><span class="material-symbols-rounded" aria-hidden="true">do_not_disturb_on</span><div><strong>Sem obrigação</strong><p>de continuidade</p></div></article>
        </div>
      </div>
    </section>

    <section class="br-section br-section--faq" id="perguntas" aria-labelledby="faq-title">
      <div class="br-shell">
        <div class="br-section__head">
          <p class="br-eyebrow"><span></span>Perguntas frequentes</p>
          <h2 id="faq-title">O essencial para conhecer o Prontoo.</h2>
        </div>
        <div class="br-faq">
          <?php foreach ($brLandingFaq as $faqItem): ?>
            <details class="br-faq__item">
              <summary><?php echo br_h($faqItem["q"]); ?></summary>
              <p><?php echo br_h($faqItem["a"]); ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="br-final" aria-labelledby="final-title">
      <div class="br-shell">
        <div class="br-final__card">
          <div class="br-final__inner">
            <div>
              <p class="br-eyebrow br-eyebrow--light"><span></span>Prontoo</p>
              <h2 id="final-title">O cuidado precisa de rotina. A rotina precisa de clareza.</h2>
              <p>Reúna pacientes, agenda, documentos e equipe em um fluxo simples de acompanhar.</p>
              <div class="br-final__tags" aria-label="Condições do cadastro"><span>30 dias gratuitos</span><span>Recursos disponíveis</span><span>Sem obrigação de continuidade</span></div>
            </div>
            <a class="br-btn br-btn--light" href="../index.php?r=signup">Criar consultório gratuitamente</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="br-footer">
    <div class="br-shell br-footer__inner">
      <a class="br-brand br-brand--footer" href="#inicio" aria-label="Prontoo — voltar ao início">
        <img src="../public/assets/prontoo-mark-<?php echo br_h($brLandingAssetRevision); ?>.png" alt="" class="br-brand__mark" width="32" height="32">
        <span class="br-brand__name">Prontoo</span>
      </a>
      <p>Organize o consultório. Mantenha o cuidado no centro.</p>
    </div>
  </footer>
</body>
</html>
