from pathlib import Path
import json

root = Path('.')

version_path = root / 'version.json'
version = json.loads(version_path.read_text())
version.update({
    'version': '1.8.9.1',
    'release': '1.8.9.1',
    'generated_at_unix': 1786317300,
    'generated_at': '2026-08-09T23:15:00+00:00',
    'updated_at': '2026-08-09T23:15:00+00:00',
    'build': '1.8.9.1-consolidation-audit',
    'previous_version': '1.8.7.8',
    'release_date': '2026-08-09',
    'logic_changes': False,
    'documentation_changes': True,
    'database_changes': False,
    'schema_changes': False,
    'visual_changes': False,
    'functional_equivalence_policy': 'post_zero_legacy_consolidation_no_runtime_behavior_change',
    'notes': 'Auditoria de consolidação pós-zero-legacy: corrige metadata ativa, documentação e higiene operacional sem alterar runtime, schema, dados ou interface.',
    'deployment_sync_id': 'github-prontoo-1.8.9.1-consolidation-audit',
    'deployment_sync_requested_at': '2026-08-09T23:15:00+00:00',
    'rewrite_scope': 'post_zero_legacy_metadata_ci_and_repository_hygiene',
    'changelog': {
        'title': 'Auditoria de consolidação pós-zero-legacy',
        'items': [
            'alinha componentes arquiteturais ativos aos paths nativos após a remoção das fachadas globais',
            'transforma a ausência de fronteiras de compatibilidade e resíduos temporários em gate permanente de arquitetura',
            'corrige a documentação de estado atual para distinguir rastreabilidade histórica de compatibilidade executável',
            'encerra PRs técnicos de inventário e migração já superseded pela materialização zero-legacy',
            'preserva referências históricas somente nos mapas explícitos de migração, auditorias e contratos de baseline',
            'não altera banco, schema, comportamento funcional, layout ou assets públicos',
        ],
    },
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

manifest_path = root / 'app/architecture.manifest.json'
manifest = json.loads(manifest_path.read_text())
manifest['server_evolution_phase_two_components'] = [
    'app/Infrastructure/ServerJsonCache/ServerJsonCacheInfrastructureOperations01.php',
    'app/Runtime/ServerJsonCache/ServerJsonCacheRuntimeOperations01.php',
    'app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations01.php',
    'app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations02.php',
    'app/Runtime/SupportTelemetry/SupportTelemetryRuntimeOperations01.php',
]
manifest['server_evolution_phase_three_components'] = [
    'app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations01.php',
    'app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations02.php',
    'app/Runtime/SupportTelemetry/SupportTelemetryRuntimeOperations01.php',
]
manifest['developer_dashboard_observability_components'] = [
    'app/Runtime/AdminPages/AdminPagesRuntimeOperations02.php',
    'app/Runtime/AdminPages/AdminPagesRuntimeOperations03.php',
    'app/Presentation/AdminPages/AdminPagesPresentationOperations03.php',
    'app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations01.php',
    'app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations02.php',
    'app/Runtime/SupportTelemetry/SupportTelemetryRuntimeOperations01.php',
    'public/assets/design-system.css',
]
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

reconcile_path = root / 'tools/release-contract-reconcile'
reconcile = reconcile_path.read_text()
old = "$architecture['native_migration_policy'] = 'effective_native_unit_count_must_not_decrease_after_consolidation_1_8_7_1_and_compatibility_boundary_ceiling_51_must_not_increase';"
new = "$architecture['native_migration_policy'] = 'effective_native_unit_count_must_not_decrease_and_current_non_native_ceiling_must_not_increase';"
if old not in reconcile:
    raise SystemExit('native migration policy anchor not found')
reconcile_path.write_text(reconcile.replace(old, new, 1))

check_path = root / 'tools/architecture-check.php'
check = check_path.read_text()
anchor = '\n$result = [\n'
if anchor not in check:
    raise SystemExit('architecture result anchor not found')
gate = r'''
$consolidationManifest = json_decode(
    (string) file_get_contents($root . '/app/architecture.manifest.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$assert(($consolidationManifest['compatibility_boundaries'] ?? null) === [], 'consolidation_compatibility_boundaries_not_empty');
$removedLegacyFiles = array_fill_keys(array_map('strval', (array) ($consolidationManifest['removed_legacy_files'] ?? [])), true);
foreach ($consolidationManifest as $key => $paths) {
    if (!is_string($key) || !str_ends_with($key, '_components') || !is_array($paths)) {
        continue;
    }
    foreach ($paths as $path) {
        if (!is_string($path) || $path === '') {
            $failures[] = 'consolidation_component_invalid:' . $key;
            continue;
        }
        $assert(is_file($root . '/' . $path), 'consolidation_component_missing:' . $key . ':' . $path);
        $assert(!isset($removedLegacyFiles[$path]), 'consolidation_component_removed_legacy:' . $key . ':' . $path);
    }
}
foreach ([
    '.github/workflows/zero-legacy-migration.yml',
    '.github/workflows/zero-legacy-final-readonly.yml',
    '.github/workflows/zero-legacy-final-push.yml',
    '.github/workflows/zero-legacy-public-finalizer.yml',
    '.github/workflows/zero-legacy-materializer-scheduled.yml',
    '.zero-legacy-materialize-trigger',
    'tools/zero-legacy-final-generate.py',
    'tools/zero-legacy-root-entrypoints-fix.py',
    'tools/sitecustomize.py',
    'zero-legacy-final-failure.log',
] as $path) {
    $assert(!is_file($root . '/' . $path), 'consolidation_temporary_artifact:' . $path);
}
$appIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($appIterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $assert(!str_contains($relative, '/Legacy/'), 'consolidation_legacy_path:' . $relative);
}
'''
check_path.write_text(check.replace(anchor, '\n' + gate + anchor, 1))

docs_index_path = root / 'docs/index.md'
docs_index = docs_index_path.read_text()
if 'CONSOLIDATION-AUDIT-1.8.9.1.md' not in docs_index:
    docs_index = docs_index.replace(
        '- [View de contato do paciente](architecture/patient-contact-view.md)\n',
        '- [View de contato do paciente](architecture/patient-contact-view.md)\n- [Auditoria de consolidação pós-zero-legacy](architecture/CONSOLIDATION-AUDIT-1.8.9.1.md)\n',
        1,
    )
docs_index = docs_index.replace('`app/version.json`', '`version.json`')
docs_index_path.write_text(docs_index)

overview_path = root / 'docs/architecture/overview.md'
overview = overview_path.read_text()
overview = overview.replace(
    '- preservar compatibilidade enquanto unidades históricas são classificadas e decompostas;',
    '- preservar rastreabilidade histórica sem reintroduzir compatibilidade executável;',
)
legacy_heading = '## Compatibilidade `Legacy`'
if legacy_heading in overview:
    start = overview.index(legacy_heading)
    end = overview.index('## Pontos de entrada', start)
    replacement = '''## Histórico de migração\n\nA árvore atual não possui camada, namespace ou fachada executável `Legacy`. Os nomes de origem removidos sobrevivem apenas como metadados históricos declarados nos mapas de migração de `version.json`, em `removed_legacy_files`, nas auditorias de baseline e nos contratos que resolvem origem histórica para destino nativo.\n\nEssas referências não participam do dispatch, bootstrap ou composição do runtime. Componentes arquiteturais ativos devem apontar exclusivamente para arquivos existentes e não podem apontar para itens de `removed_legacy_files`; `tools/architecture-check.php` verifica essa condição.\n\nA migração permanece monotônica: o número efetivo de unidades nativas não pode diminuir e o teto corrente de entrypoints e ferramentas procedurais não nativos não pode aumentar.\n\n'''
    overview = overview[:start] + replacement + overview[end:]
overview = overview.replace('5. compatibilidade controlada;', '5. rastreabilidade histórica sem compatibilidade executável;')
overview_path.write_text(overview)

audit_path = root / 'docs/architecture/CONSOLIDATION-AUDIT-1.8.9.1.md'
audit_path.write_text('''# Auditoria de consolidação — 1.8.9.1\n\n## Escopo\n\nRevisão da árvore canônica após a materialização zero-legacy para alinhar código executável, manifests, documentação, contratos e backlog técnico.\n\n## Resultado\n\nO zero-legacy executável permanece confirmado: `compatibility_boundaries` está vazio, as fachadas globais removidas não existem na árvore e não há paths PHP ativos sob `/Legacy/`.\n\n## Achados corrigidos\n\n1. Metadata arquitetural ativa ainda apontava para `app/Support/ServerJsonCache.php`, `app/Support/Telemetry.php` e `app/Admin/AdminPages.php`; os componentes agora apontam para unidades nativas existentes.\n2. O reconciliador ainda materializava o teto histórico 51; a política agora referencia o teto corrente canônico.\n3. Faltava gate explícito para componentes ativos, `compatibility_boundaries`, artefatos temporários e paths `/Legacy/`; o Architecture Contract agora cobre essas condições.\n4. Documentação de estado atual misturava história e compatibilidade executável; o texto foi consolidado para refletir zero-legacy.\n5. Os PRs técnicos #196, #201, #202 e #203 foram encerrados sem merge por terem sido superseded pela materialização #229. O PR #182 permanece separado por conter hardening funcional.\n6. `ChangeLog.txt` duplicava o `CHANGELOG.md` canônico e foi removido.\n\n## Referências históricas preservadas\n\nPaths removidos permanecem válidos apenas em `php84_baseline_path_migrations`, `architecture_source_path_migrations`, `removed_legacy_files`, auditorias históricas e contratos de resolução de origem histórica. Não são runtime legacy.\n\n## Invariantes\n\n- zero fronteiras executáveis de compatibilidade;\n- 100% de classificação arquitetural;\n- mínimo de 278 unidades nativas;\n- máximo corrente de 21 arquivos não nativos;\n- PHP 8.4;\n- SOLID estrito;\n- segurança, assets, release contract, documentação e lint obrigatórios;\n- nenhuma mudança de banco, schema, comportamento funcional, layout ou assets nesta auditoria.\n''')
