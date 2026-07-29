<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$now = gmdate('c');

foreach (['version.json', 'app/update.manifest.json'] as $relative) {
    $path = $root . '/' . $relative;
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Contrato JSON inválido: ' . $relative);
    }
    if ($relative === 'version.json') {
        $data['functional_equivalence_policy'] = 'preserves_1_7_29_1_runtime_behavior';
        $data['updated_at'] = $now;
    } else {
        $data['build'] = '1.7.29.2-phase1-lean-refactor';
        $data['package_type'] = 'incremental_refactor';
        $data['generated_at'] = $now;
        $data['updated_at'] = $now;
        $data['deployment_sync_id'] = 'github-phase1-lean-refactor-1-7-29-2';
        $data['deployment_sync_requested_at'] = $now;
    }
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
    );
}

echo json_encode(['ok' => true, 'version' => '1.7.29.2'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
