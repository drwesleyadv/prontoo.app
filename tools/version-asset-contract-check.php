<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$raw = @file_get_contents($root . '/version.json');
$metadata = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($metadata)) {
    throw new RuntimeException('version.json inválido.');
}
$assetRevision = trim((string) ($metadata['asset_version'] ?? ''));
if (!preg_match('/^1\.\d{1,2}\.\d{1,2}\.\d+$/', $assetRevision)) {
    throw new RuntimeException('asset_version inválido.');
}
$app = @file_get_contents($root . '/app/prontoo.php');
if (!is_string($app) ||
    !preg_match('/PRONTOO_ASSET_REV_FALLBACK\s*=\s*["\x27]([^"\x27]+)["\x27]/', $app, $match) ||
    trim((string) ($match[1] ?? '')) !== $assetRevision) {
    throw new RuntimeException('Fallback de assets divergente.');
}
$assets = [
    'app-icon-' . $assetRevision . '.png',
    'prontoo-mark-' . $assetRevision . '.png',
    'favicon-' . $assetRevision . '.png',
    'favicon-' . $assetRevision . '.ico',
    'pix-' . $assetRevision . '.svg',
];
foreach ($assets as $assetFile) {
    if (!is_file($root . '/public/assets/' . $assetFile)) {
        throw new RuntimeException('Asset público ausente: ' . $assetFile);
    }
}
$css = @file_get_contents($root . '/public/assets/design-system.css');
if (!is_string($css) || !str_contains($css, 'pix-' . $assetRevision . '.svg')) {
    throw new RuntimeException('CSS diverge do asset_version.');
}
echo json_encode([
    'ok' => true,
    'asset_version' => $assetRevision,
    'assets_verified' => count($assets),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
