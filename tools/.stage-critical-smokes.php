<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$versionPath = $root . '/version.json';
$version = json_decode((string) file_get_contents($versionPath), true, 512, JSON_THROW_ON_ERROR);
$version['version'] = '1.8.7.4';
$version['release'] = '1.8.7.4';
$version['previous_version'] = '1.8.7.3';
$version['generated_at_unix'] = 1786134600;
$version['generated_at'] = '2026-08-07T20:30:00+00:00';
$version['updated_at'] = '2026-08-07T20:30:00+00:00';
$version['build'] = '1.8.7.4-critical-runtime-smokes';
$version['logic_changes'] = false;
$version['documentation_changes'] = true;
$version['database_changes'] = false;
$version['schema_changes'] = false;
$version['visual_changes'] = false;
$version['functional_equivalence_policy'] = 'runtime-smoke-coverage-only-no-user-resource-or-feature-change';
$version['rewrite_scope'] = 'critical_runtime_smoke_matrix_for_auth_mfa_patient_agenda_and_financial';
$version['deployment_sync_id'] = 'github-prontoo-1.8.7.4-critical-runtime-smokes';
$version['deployment_sync_requested_at'] = '2026-08-07T20:30:00+00:00';
$version['release_date'] = '2026-08-07';
$version['notes'] = 'Amplia a validação dinâmica com uma matriz de smoke tests dos fluxos críticos em PHP 8.4 + MySQL 8, sem alterar recursos do usuário.';
$version['changelog'] = [
    'title' => 'Matriz de smoke tests de runtime crítico',
    'items' => [
        'valida vínculo clínico, resolução de credencial e rotação da geração de autenticação em banco real',
        'valida persistência criptografada do MFA e leitura isolada de Pacientes e Agenda por consultório',
        'valida recebimento financeiro transacional pelo Application Service e adapter PDO com movimento único',
        'preserva schema, dados de produção, interface, rotas e todos os recursos disponíveis ao usuário',
    ],
];
file_put_contents(
    $versionPath,
    json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);
