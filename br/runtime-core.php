<?php

const BR_LANDING_ORIGIN = "https://prontoo.app";
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
