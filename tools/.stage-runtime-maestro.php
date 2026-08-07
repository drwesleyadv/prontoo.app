<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$cronPath = $root . '/cron/maestro.php';
$cron = (string) file_get_contents($cronPath);
$legacy = '\\Prontoo\\Core\\Integrity\\PiIntegrity';
$canonical = '\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity';
$occurrences = substr_count($cron, $legacy);
if ($occurrences !== 2 && $occurrences !== 0) {
    throw new RuntimeException('Quantidade inesperada de referências legadas de PiIntegrity no Maestro: ' . $occurrences);
}
if ($occurrences === 2) {
    $cron = str_replace($legacy, $canonical, $cron);
    file_put_contents($cronPath, $cron);
}
if (str_contains((string) file_get_contents($cronPath), $legacy)) {
    throw new RuntimeException('Referência legada de PiIntegrity permaneceu no Maestro.');
}

$versionPath = $root . '/version.json';
$version = json_decode((string) file_get_contents($versionPath), true, 512, JSON_THROW_ON_ERROR);
$version['version'] = '1.8.7.3';
$version['release'] = '1.8.7.3';
$version['previous_version'] = '1.8.7.2';
$version['generated_at_unix'] = 1786132800;
$version['generated_at'] = '2026-08-07T20:00:00+00:00';
$version['updated_at'] = '2026-08-07T20:00:00+00:00';
$version['build'] = '1.8.7.3-maestro-runtime-stability';
$version['logic_changes'] = true;
$version['documentation_changes'] = true;
$version['database_changes'] = false;
$version['schema_changes'] = false;
$version['visual_changes'] = false;
$version['functional_equivalence_policy'] = 'runtime-stability-only-no-user-resource-or-feature-change';
$version['rewrite_scope'] = 'maestro_integrity_namespace_alignment_and_runtime_smoke_gate';
$version['deployment_sync_id'] = 'github-prontoo-1.8.7.3-maestro-runtime-stability';
$version['deployment_sync_requested_at'] = '2026-08-07T20:00:00+00:00';
$version['release_date'] = '2026-08-07';
$version['notes'] = 'Estabilização do Maestro: alinha o flush de integridade à implementação canônica e adiciona cobertura executável do cron em PHP 8.4 + MySQL 8.';
$version['changelog'] = [
    'title' => 'Estabilização do runtime do Maestro',
    'items' => [
        'alinha o flush de integridade do Maestro à classe canônica Infrastructure\\Integrity\\PiIntegrity',
        'adiciona smoke test executável do cron Maestro em PHP 8.4 e MySQL 8 reais',
        'preserva regras, agenda, ações, schema, dados, interface e recursos disponíveis ao usuário',
    ],
];
file_put_contents(
    $versionPath,
    json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);
