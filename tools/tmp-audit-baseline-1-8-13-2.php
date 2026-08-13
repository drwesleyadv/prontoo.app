<?php
declare(strict_types=1);

$replaceOnce = static function (string $path, string $old, string $new): void {
    $source = (string) file_get_contents($path);
    if (substr_count($source, $old) !== 1) {
        fwrite(STDERR, "Contract drift: {$path}\n");
        exit(1);
    }
    $updated = str_replace($old, $new, $source, $count);
    if ($count !== 1 || file_put_contents($path, $updated, LOCK_EX) === false) {
        fwrite(STDERR, "Write failed: {$path}\n");
        exit(1);
    }
};

$replaceOnce(
    'app/Runtime/AdminPages/AdminPagesRuntimeOperations03.php',
    'stats-grid admin-overview-kpis two wide',
    'two wide global-telemetry-grid',
);

$replaceOnce('README.md', '1.8.11.2', '1.8.13.2');
$replaceOnce(
    'README.md',
    '- `docs/index.md`: mapa de toda a documentação.',
    "- `AGENTS.md`: guardrails operacionais para IA agêntica e automações;\n- `docs/index.md`: mapa de toda a documentação.",
);
$replaceOnce(
    'CONTRIBUTING.md',
    '## Ambiente',
    "## Antes de alterar\n\nHumanos e agentes devem ler `AGENTS.md`. Ele reúne a ordem de autoridade, invariantes arquiteturais, regras de tenant, segurança, banco, Presentation, release e Definition of Done.\n\n## Ambiente",
);
$replaceOnce(
    'docs/index.md',
    '## Leitura recomendada',
    "## Entrada para agentes\n\nQualquer IA agêntica ou automação que pretenda editar o repositório deve começar por `../AGENTS.md`. Esse documento reúne guardrails operacionais, ordem de autoridade, regras de tenant, segurança, banco, Presentation, release e Definition of Done. A baseline vigente está registrada em `audits/BASELINE-1.8.13.2.md`.\n\n## Leitura recomendada",
);

$versionPath = 'version.json';
$version = json_decode((string) file_get_contents($versionPath), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($version) || ($version['version'] ?? '') !== '1.8.13.1' || ($version['release'] ?? '') !== '1.8.13.1') {
    fwrite(STDERR, "Canonical version drift\n");
    exit(1);
}
$timestamp = '2026-08-13T09:30:00-04:00';
$version['version'] = '1.8.13.2';
$version['release'] = '1.8.13.2';
$version['generated_at_unix'] = (new DateTimeImmutable($timestamp))->getTimestamp();
$version['generated_at'] = $timestamp;
$version['updated_at'] = $timestamp;
$version['build'] = '1.8.13.2-audited-baseline-agentic-guardrails';
$version['database_changes'] = false;
$version['schema_changes'] = false;
$version['logic_changes'] = true;
$version['visual_changes'] = true;
$version['documentation_changes'] = true;
$version['functional_equivalence_policy'] = 'intentional_visual_fix_telemetry_two_by_two_without_business_logic_change';
$version['version_policy'] = 'version_json_is_unique_canonical_source_and_release_version_bump_is_explicit';
$version['version_format'] = '1.month.day.sequence';
$version['version_sequence_policy'] = 'sequence_starts_at_1_and_resets_each_publication_day';
$version['release_version_policy'] = 'explicit_date_sequence_generator_v1';
$version['release_version_generator'] = 'tools/release-version';
$version['release_version_last_transition'] = '1.8.13.1->1.8.13.2';
$version['notes'] = 'Baseline auditada: corrige a grade 2×2 dos quatro KPIs globais sem alterar CSS, cálculos ou fontes e consolida guardrails operacionais para IA agêntica.';
$version['changelog'] = [
    'title' => 'Baseline auditada e guardrails para agentes',
    'items' => [
        'corrige a composição dos quatro KPIs de telemetria para 2 colunas × 2 linhas em viewport ampla, preservando a queda responsiva para uma coluna',
        'remove do wrapper interno a combinação de classes cujo auto-fit anulava a intenção da grade two, sem alterar CSS, cálculos ou fontes de telemetria',
        'atualiza README, índice documental e guia de contribuição para a baseline vigente',
        'adiciona AGENTS.md com ordem de autoridade, invariantes de arquitetura, tenant, segurança, banco, Presentation, release e checklist de merge para IA agêntica',
        'mantém banco e schema inalterados',
    ],
];
$version['previous_version'] = '1.8.13.1';
$version['deployment_sync_id'] = 'github-prontoo-1.8.13.2-audited-baseline-agentic-guardrails';
$version['deployment_sync_requested_at'] = $timestamp;
$version['release_reconciliation_policy'] = 'version_json_canonical_explicit_bump_then_deterministic_reconcile_ci_read_only';
$version['rewrite_scope'] = 'admin_status_telemetry_layout_and_agentic_documentation_only';
$version['release_date'] = '2026-08-13';
file_put_contents(
    $versionPath,
    json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL,
    LOCK_EX,
);
