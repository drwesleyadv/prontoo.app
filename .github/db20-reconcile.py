from pathlib import Path
import json
from datetime import datetime
from zoneinfo import ZoneInfo

def replace_once(path, old, new):
    p = Path(path)
    s = p.read_text()
    if s.count(old) != 1:
        raise SystemExit(f"replacement mismatch {path}: {s.count(old)}")
    p.write_text(s.replace(old, new, 1))

replace_once(
    'app/Presentation/AuthOnboarding/AuthOnboardingPresentationOperations01.php',
    '''        $pageLoadColor = $public ? "#347963" : ($secondaryColor !== "" ? $secondaryColor : "#347963");\n        $databaseQueryColor = $public ? "#1f6f56" : ($primaryColor !== "" ? $primaryColor : "#1f6f56");''',
    '''        $sharedColor = $public ? "#347963" : ($secondaryColor !== "" ? $secondaryColor : "#347963");''',
)
replace_once(
    'app/Presentation/AuthOnboarding/AuthOnboardingPresentationOperations01.php',
    '''::e($pageLoadColor)''',
    '''::e($sharedColor)''',
)
replace_once(
    'app/Presentation/AuthOnboarding/AuthOnboardingPresentationOperations01.php',
    '''::e($databaseQueryColor)''',
    '''::e($sharedColor)''',
)

path = Path('tools/telemetry-json-source-contract-check')
s = path.read_text()
old = '''$metricSummary = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations02::telemetry_developer_metrics_summary_from_events(\n    $metricEvents,\n    $nowUs,\n);'''
new = '''$metricSummary = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations02::telemetry_developer_metrics_summary_from_events(\n    $metricEvents,\n    $nowUs,\n    ["current_total" => 9, "previous_total" => 5, "variation_pct" => 80.0],\n);'''
if old not in s: raise SystemExit('metrics test call not found')
s = s.replace(old, new, 1)
s = s.replace("($metricCurrent['database_operations'] ?? -1) === 9", "($metricCurrent['records'] ?? -1) === 9", 1)
s = s.replace("($metricPrevious['database_operations'] ?? -1) === 5", "($metricPrevious['records'] ?? -1) === 5", 1)
s = s.replace("($metricVariations['database_operations_pct'] ?? 999)", "($metricVariations['records_pct'] ?? 999)", 1)
start = s.index('$footerPageLoads = \\Prontoo\\Presentation\\AuthOnboarding')
end = s.index('$historicalWave = \\Prontoo\\Presentation\\AuthOnboarding', start)
footer_data = r'''$footerRecordSamples = [];
$footerWindowStart = intdiv($chartNowUs, 1000000) - 20 * 86400;
for ($index = 0; $index < 20; $index++) {
    $footerRecordSamples[] = [
        'captured_at_unix' => $footerWindowStart + $index * 86400 + 300,
        'total_records' => 1000 + $index,
        'delta_records' => $index + 1,
    ];
}
$footerRecordSeries = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations03::telemetry_database_record_series_from_samples(
    $footerRecordSamples,
    intdiv($chartNowUs, 1000000),
);
$footerPageLoads = \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::footer_telemetry_line_values(array_slice($volumePages, -20));
$footerDatabaseQueries = \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::footer_telemetry_line_values($footerRecordSeries);
$assert(
    count($footerPageLoads) === 20 &&
    count($footerDatabaseQueries) === 20 &&
    (int) $footerPageLoads[18] === 2 &&
    (int) $footerDatabaseQueries[19] === 20,
    'rodapé deve usar 20 dias equivalentes e alimentar Consulta com deltas de database.json',
);
'''
s = s[:start] + footer_data + s[end:]
s = s.replace(
    '''str_contains($publicMountains, 'class="footer-telemetry-wave-path is-page-loads" fill="#347963"') &&\n    str_contains($publicMountains, 'class="footer-telemetry-wave-path is-database-queries" fill="#1f6f56"')''',
    '''str_contains($publicMountains, 'class="footer-telemetry-wave-path is-page-loads" fill="#347963"') &&\n    str_contains($publicMountains, 'class="footer-telemetry-wave-path is-database-queries" fill="#347963"')''',
    1,
)
s = s.replace(
    "'áreas públicas devem restaurar a identidade visual histórica sem reabrir refresh público'",
    "'áreas públicas do rodapé devem usar o mesmo tom com sobreposição a 50%'",
    1,
)
s = s.replace(
    '''str_contains($clinicMountains, 'class="footer-telemetry-wave-path is-page-loads" fill="#445566"') &&\n    str_contains($clinicMountains, 'class="footer-telemetry-wave-path is-database-queries" fill="#112233"')''',
    '''str_contains($clinicMountains, 'class="footer-telemetry-wave-path is-page-loads" fill="#445566"') &&\n    str_contains($clinicMountains, 'class="footer-telemetry-wave-path is-database-queries" fill="#445566"')''',
    1,
)
s = s.replace(
    "'áreas autenticadas devem preservar a ordem histórica principal/forte com as cores do consultório'",
    "'áreas autenticadas do rodapé devem usar o mesmo tom principal do consultório'",
    1,
)
old_source = '''$assert(\n    str_contains($login, 'telemetry_volume_series_30d("page_load")') &&\n    str_contains($login, 'telemetry_volume_series_30d("database_queries")') &&\n    str_contains($login, '"page_loads" =>') &&\n    str_contains($login, '"database_queries" =>') &&\n    !str_contains($login, 'telemetry_route_requests_series_20d()') &&\n    !str_contains($login, 'telemetry_sequence_records_series_20d()'),\n    'linhas do rodapé devem reutilizar exatamente as duas séries de Volume',\n);'''
new_source = '''$assert(\n    str_contains($login, '$nowUnix = time();') &&\n    str_contains($login, 'telemetry_volume_series_30d(') &&\n    str_contains($login, '$nowUnix * 1000000') &&\n    str_contains($login, 'telemetry_database_record_series_20d($nowUnix)') &&\n    !str_contains($login, 'telemetry_volume_series_30d("database_queries")') &&\n    str_contains($login, '"page_loads" =>') &&\n    str_contains($login, '"database_queries" =>'),\n    'rodapé deve alinhar 20 dias de carregamentos com a variação líquida de registros de database.json',\n);'''
if old_source not in s: raise SystemExit('footer source contract not found')
s = s.replace(old_source, new_source, 1)
s = s.replace(
    '$volumePages, $volumeQueries, $footerPageLoads, $footerDatabaseQueries,',
    '$volumePages, $volumeQueries, $footerRecordSamples, $footerWindowStart, $footerRecordSeries, $footerPageLoads, $footerDatabaseQueries,',
    1,
)
path.write_text(s)

path = Path('tools/version-asset-contract-check.php')
s = path.read_text()
old = '''    'footer_telemetry_wave_path(',\n    ' C ',\n    '$public ? "#347963"',\n    '$public ? "#1f6f56"',\n    'viewBox="0 0 1000 250"','''
new = '''    'footer_telemetry_wave_path(',\n    ' C ',\n    '$sharedColor = $public ? "#347963"',\n    'viewBox="0 0 1000 250"','''
if old not in s: raise SystemExit('version footer contract not found')
s = s.replace(old, new, 1)
marker = "    'telemetry_database_record_series_30d',\n"
if marker in s and "    'telemetry_database_record_series_20d',\n" not in s:
    s = s.replace(marker, marker + "    'telemetry_database_record_series_20d',\n", 1)
path.write_text(s)

replace_once(
    'AGENTS.md',
    'Cada card compara os 10 dias móveis recentes aos 10 imediatamente anteriores. Páginas conta page loads canônicos, Registros soma `database_query_count` e Landing conta a rota canônica `landing`. SQL, parâmetros, resultados e dados clínicos não são persistidos.',
    'Cada card compara os 10 dias móveis recentes aos 10 imediatamente anteriores. Páginas conta page loads canônicos; Registros soma a variação líquida do estoque de linhas persistida em `ssd/telemetry/database.json`, calculada pelo Maestro como `total atual - total imediatamente anterior` sobre todas as tabelas-base; Landing conta a rota canônica `landing`. O primeiro total observado é apenas saldo inicial e não entra no crescimento. Deltas negativos são preservados. SQL, parâmetros, resultados e dados clínicos não são persistidos.',
)
ag = Path('AGENTS.md')
s = ag.read_text()
start = s.index('Todas as áreas HTML exibem no rodapé duas ondas decorativas', s.index('### Contrato atual da telemetria global'))
end = s.index('\n\nO detalhamento pertence', start)
paragraph = 'Todas as áreas HTML exibem no rodapé duas ondas decorativas de 20 intervalos consecutivos de 24 horas. Carregamento de Páginas usa os 20 intervalos finais da série canônica `page_load`; Consulta usa, no mesmo timeframe, a variação líquida de registros de `ssd/telemetry/database.json`, e não `database_query_count`. A apresentação preserva a identidade histórica: viewport `1000×250`, normalização conjunta pelo maior valor das duas séries, curvas Bézier cúbicas contínuas entre as amostras, fechamento até o fundo do SVG, altura visual de `15vh` limitada a `64–180px` e máscara vertical de `transparent` para `#000` aos 34%. As duas áreas usam exatamente o mesmo tom principal — `#347963` em superfícies públicas e a cor principal do consultório nas autenticadas — e `opacity: 0.5` em cada uma; a sobreposição, portanto, evidencia cruzamentos e mudanças de dominância pela densidade resultante. O HTML público contém somente coordenadas normalizadas, sem contagens absolutas nem refresh público. `login_telemetry_wave` permanece neutralizada e `footer_telemetry_wave` permanece privada; o cliente não busca nem redesenha a geometria do rodapé.'
ag.write_text(s[:start] + paragraph + s[end:])

replace_once(
    'docs/performance/telemetry.md',
    'Os cards de Métricas do Desenvolvedor usam duas janelas móveis contíguas de 10 dias. Páginas conta os carregamentos HTML concluídos, Registros soma `database_query_count` e Landing conta somente os carregamentos cuja rota canônica é `landing`. O percentual representa o período recente em relação aos 10 dias imediatamente anteriores; quando o período anterior é zero e o atual é positivo, a variação permanece indefinida.',
    'Os cards de Métricas do Desenvolvedor usam duas janelas móveis contíguas de 10 dias. Páginas conta os carregamentos HTML concluídos; Registros soma os deltas de `ssd/telemetry/database.json`, em que cada captura do Maestro mede exatamente o total de linhas de todas as tabelas-base e registra `total atual - total imediatamente anterior`; Landing conta somente os carregamentos cuja rota canônica é `landing`. O primeiro total é saldo inicial e não entra em Registros. Deltas negativos são preservados. O percentual representa o período recente em relação aos 10 dias imediatamente anteriores; quando não há cobertura completa ou o período anterior é zero e o atual é positivo, a variação permanece indefinida.',
)
p = Path('docs/performance/telemetry.md')
s = p.read_text()
start = s.index('## Áreas decorativas do rodapé')
end = s.index('\n## Tempo médio de consulta ao banco', start)
section = '''## Áreas decorativas do rodapé

Todas as áreas HTML renderizam no rodapé duas ondas preenchidas em um timeframe comum de 20 intervalos consecutivos de 24 horas. Carregamento de Páginas usa os 20 pontos finais de `page_load`; Consulta usa a série diária de variação líquida de registros de `ssd/telemetry/database.json`, e não a contagem de queries SQL. Nenhuma contagem absoluta ou array de telemetria é serializado no HTML público.

A apresentação preserva a identidade visual usada antes da supressão do Status público. Os valores são normalizados em conjunto pelo maior valor das duas séries em um SVG `1000×250`, com margem superior de `10` e inferior de `14`. Cada amostra é ligada à seguinte por uma Bézier cúbica cujos dois controles ficam no ponto médio horizontal, respectivamente nas alturas da amostra anterior e da atual. Isso produz a curva contínua característica, com tangentes horizontais nas amostras, sem substituir os dados por uma spline independente.

Cada caminho fecha até o fundo do SVG (`y=250`). A superfície fixa ocupa `15vh`, limitada a `64–180px`, usa `opacity: 0.5` em cada área, não possui stroke e recebe a máscara vertical histórica `transparent → #000`, integral aos 34% da altura.

As duas áreas usam exatamente a mesma cor. Em superfícies públicas, ambas usam `#347963`; em áreas autenticadas, ambas usam a cor principal do consultório ou do ambiente Desenvolvedor. Como cada área tem 50% de opacidade, a região compartilhada fica visualmente mais densa e as mudanças de qual série ocupa a posição superior ficam evidentes nos cruzamentos, sem depender de cores diferentes.

A fonte de dados e os controles de segurança permanecem atuais. `login_telemetry_wave` continua sendo apenas um tombstone público sem dados; `footer_telemetry_wave` permanece uma rota JSON privada e excluída da própria contagem de page loads; o renderer canônico do rodapé é de servidor e o JavaScript não busca nem recalcula sua geometria. A página pública Status não é restaurada.
'''
s = s[:start] + section + s[end:]
s = s.replace(
    'As três fontes mantêm retenção móvel de 31 dias. `views.json` e `speed.json` são higienizados no fechamento de page loads; `database.json` é higienizado durante a captura do Maestro. Dados mais antigos que 31 dias não participam das fontes canônicas.',
    '`views.json` e `speed.json` mantêm a retenção operacional vigente para as séries de page load e velocidade. `database.json` mantém 20 dias móveis de deltas, suficientes para os dois períodos comparativos de 10 dias, e é higienizado durante a captura do Maestro.',
    1,
)
s = s.replace(
    'A série de estoque persistida em `database.json` continua representando `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro; deltas negativos permanecem válidos e não são confundidos com o card Registros da tela Métricas.',
    'A série de estoque persistida em `database.json` representa `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro; essa série é a fonte canônica do card Registros e da área Consulta do rodapé. O saldo inicial é metadado de bootstrap e não entra nos totais móveis; deltas negativos permanecem válidos.',
    1,
)
p.write_text(s)

replace_once(
    'docs/operations/developer-control-center.md',
    '- Páginas conta carregamentos HTML concluídos; Registros soma as operações preparadas observadas no banco; Landing conta carregamentos da Landing Page.',
    '- Páginas conta carregamentos HTML concluídos; Registros soma a variação líquida de linhas registrada pelo Maestro em `ssd/telemetry/database.json`, excluindo o saldo inicial e preservando deltas negativos; Landing conta carregamentos da Landing Page.',
)
p = Path('docs/operations/developer-control-center.md')
s = p.read_text()
start = s.index('- Todas as áreas HTML exibem')
end = s.index('\n- Rotas mostra', start)
line = '- Todas as áreas HTML exibem duas ondas preenchidas no rodapé em um timeframe comum de 20 intervalos de 24 horas. Carregamento de Páginas usa os 20 pontos finais de `page_load`; Consulta usa a variação líquida de registros de `database.json`, não a contagem de queries. A identidade visual mantém SVG `1000×250`, normalização conjunta, curvas Bézier cúbicas contínuas, fechamento até o fundo, altura `15vh` limitada a `64–180px` e máscara vertical integral aos 34%. As duas áreas usam o mesmo tom principal (`#347963` em público ou a cor principal do consultório) com `opacity: 0.5` cada, de modo que sobreposição e cruzamentos evidenciem qual série está acima. A página Status continua suprimida, não há contagens públicas e o cliente não redesenha a geometria.'
p.write_text(s[:start] + line + s[end:])

version_path = Path('version.json')
version = json.loads(version_path.read_text())
if version.get('version') != '1.8.17.12' or version.get('release') != '1.8.17.12':
    raise SystemExit('canonical version moved before reconciliation')
now = datetime.now(ZoneInfo('America/Cuiaba')).replace(microsecond=0)
stamp = now.isoformat()
version.update({
    'version': '1.8.17.13',
    'release': '1.8.17.13',
    'generated_at_unix': int(now.timestamp()),
    'generated_at': stamp,
    'updated_at': stamp,
    'build': '1.8.17.13-database-records-20d-footer-overlap',
    'logic_changes': True,
    'visual_changes': True,
    'documentation_changes': True,
    'database_changes': False,
    'schema_changes': False,
    'functional_equivalence_policy': 'records_kpi_and_footer_use_maestro_database_deltas_20d_10x10_with_shared_half_opacity_footer_color',
    'notes': 'Registros passa a representar a variação líquida do total exato de linhas capturado pelo Maestro em database.json; o rodapé usa 20 dias equivalentes e duas áreas da mesma cor com 50% de opacidade.',
    'database_record_telemetry_policy': 'maestro_exact_total_snapshot_json_20d_delta_current_minus_previous_cuiaba_rolling_20d_10x10_initial_balance_excluded',
    'database_record_telemetry_path': 'ssd/telemetry/database.json',
    'deployment_sync_id': 'github-prontoo-1.8.17.13-database-records-20d-footer-overlap',
    'deployment_sync_requested_at': stamp,
    'release_date': '2026-08-17',
    'changelog': {
        'title': 'Registros por variação real e sobreposição no rodapé',
        'items': [
            'define Registros como a soma dos deltas do total exato de linhas capturado pelo Maestro, excluindo o saldo inicial',
            'mantém 20 dias móveis em database.json e compara os 10 dias recentes aos 10 imediatamente anteriores, preservando deltas negativos',
            'alinha o rodapé em 20 intervalos equivalentes, com Carregamento de Páginas e Consulta alimentada pela variação líquida de registros',
            'usa a mesma cor nas duas áreas do rodapé, com 50% de opacidade em cada uma para evidenciar sobreposição e cruzamentos',
            'mantém Volume com sua métrica própria de consultas SQL e não altera schema nem banco de dados',
        ],
    },
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + '\n')
