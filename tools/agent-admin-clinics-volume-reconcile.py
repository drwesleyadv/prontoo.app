#!/usr/bin/env python3
from pathlib import Path
from datetime import datetime
from zoneinfo import ZoneInfo
import hashlib
import json
import subprocess

root = Path('.')
old_version = '1.8.18.2'
new_version = '1.8.18.3'
workflow_path = Path('.github/workflows/agent-admin-clinics-volume-reconcile.yml')
script_path = Path('tools/agent-admin-clinics-volume-reconcile.py')


def read(path: str) -> str:
    return (root / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (root / path).write_text(content, encoding='utf-8')


def replace_exact(source: str, old: str, new: str, count: int, label: str) -> str:
    actual = source.count(old)
    if actual != count:
        raise SystemExit(f'{label}: esperado {count}, encontrado {actual}')
    return source.replace(old, new, count)


runtime08_path = 'app/Runtime/AdminPages/AdminPagesRuntimeOperations08.php'
runtime08 = read(runtime08_path)
runtime08 = replace_exact(
    runtime08,
    '$view = (string) ($_GET["view"] ?? "all");',
    '$view = (string) ($_GET["view"] ?? "active");',
    1,
    'default Runtime de Consultórios',
)
write(runtime08_path, runtime08)

presentation_path = 'app/Presentation/AdminPages/AdminPagesPresentationOperations03.php'
presentation = read(presentation_path)
presentation = replace_exact(
    presentation,
    '$view = preg_replace("/[^a-z_]/", "", $view) ?: "all";',
    '$view = preg_replace("/[^a-z_]/", "", $view) ?: "active";',
    2,
    'fallback de view de Consultórios',
)
presentation = replace_exact(
    presentation,
    '''        if (!in_array($view, ["all", "active", "expiring", "readonly"], true)) {
            $view = "all";
        }''',
    '''        if (!in_array($view, ["all", "active", "expiring", "readonly"], true)) {
            $view = "active";
        }''',
    2,
    'fallback de view inválida de Consultórios',
)
presentation = replace_exact(
    presentation,
    '$clear = $search !== "" || $view !== "all"',
    '$clear = $search !== "" || $view !== "active"',
    1,
    'estado do botão Limpar de Consultórios',
)
presentation = replace_exact(
    presentation,
    '(string) $href("admin_clinics", $filterKey === "all" ? [] : ["view" => $filterKey]),',
    '(string) $href("admin_clinics", $filterKey === "active" ? [] : ["view" => $filterKey]),',
    1,
    'URL canônica dos filtros de Consultórios',
)
write(presentation_path, presentation)

telemetry_path = 'app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations02.php'
telemetry = read(telemetry_path)
start = telemetry.find('    public static function telemetry_volume_series_30d(')
end = telemetry.find('    public static function telemetry_latency_series_24h(', start)
if start < 0 or end <= start:
    raise SystemExit('bloco canônico de Volume não localizado')
volume_block = '''    public static function telemetry_volume_series_30d(
        string $metric,
        ?int $nowUnixUs = null,
    ): array {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        return self::telemetry_volume_series_from_events(
            $metric,
            \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations01::telemetry_read_events(max(1, $nowUnixUs)),
            max(1, $nowUnixUs),
            30,
            15,
        );
    }

    public static function telemetry_volume_series_30d_from_events(
        string $metric,
        array $events,
        int $nowUnixUs,
    ): array {
        return self::telemetry_volume_series_from_events(
            $metric,
            $events,
            $nowUnixUs,
            30,
            15,
        );
    }

    public static function telemetry_volume_series_20d(
        string $metric,
        ?int $nowUnixUs = null,
    ): array {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        return self::telemetry_volume_series_from_events(
            $metric,
            \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations01::telemetry_read_events(max(1, $nowUnixUs)),
            max(1, $nowUnixUs),
            20,
            10,
        );
    }

    public static function telemetry_volume_series_20d_from_events(
        string $metric,
        array $events,
        int $nowUnixUs,
    ): array {
        return self::telemetry_volume_series_from_events(
            $metric,
            $events,
            $nowUnixUs,
            20,
            10,
        );
    }

    private static function telemetry_volume_series_from_events(
        string $metric,
        array $events,
        int $nowUnixUs,
        int $bucketCount,
        int $previousBucketCount,
    ): array {
        $metric = in_array($metric, ["page_load", "database_queries"], true)
            ? $metric
            : "page_load";
        $bucketUs = 86400 * 1000000;
        $bucketCount = max(1, $bucketCount);
        $previousBucketCount = max(0, min($bucketCount, $previousBucketCount));
        $windowEndUs = max(1, $nowUnixUs);
        $windowStartUs = $windowEndUs - $bucketCount * $bucketUs;
        $timezone = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
        $buckets = [];
        for ($index = 0; $index < $bucketCount; $index++) {
            $startUs = $windowStartUs + $index * $bucketUs;
            $endUs = $startUs + $bucketUs;
            $start = (new DateTimeImmutable("@" . intdiv($startUs, 1000000)))->setTimezone($timezone);
            $end = (new DateTimeImmutable("@" . intdiv($endUs, 1000000)))->setTimezone($timezone);
            $buckets[$index] = [
                "ts" => intdiv($startUs, 1000000),
                "label" => \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations01::telemetry_day_axis_label($end),
                "tooltip" => $start->format("d/m H:i") . " → " . $end->format("d/m H:i"),
                "value" => 0,
                "observed" => true,
                "period" => $index < $previousBucketCount ? "previous" : "current",
            ];
        }
        foreach ($events as $event) {
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($finishedUs < $windowStartUs || $finishedUs >= $windowEndUs) {
                continue;
            }
            $index = intdiv($finishedUs - $windowStartUs, $bucketUs);
            if ($index < 0 || $index >= $bucketCount) {
                continue;
            }
            if ($metric === "page_load") {
                $buckets[$index]["value"]++;
                continue;
            }
            if (empty($event["database_query_instrumented"])) {
                continue;
            }
            $buckets[$index]["value"] += max(0, (int) ($event["database_query_count"] ?? 0));
        }
        return array_values($buckets);
    }


'''
telemetry = telemetry[:start] + volume_block + telemetry[end:]
write(telemetry_path, telemetry)

admin2_path = 'app/Runtime/AdminPages/AdminPagesRuntimeOperations02.php'
admin2 = read(admin2_path)
admin2 = replace_exact(
    admin2,
    '''    public static function admin_global_volume_series_30d(string $metric): array
    {
        return \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations02::telemetry_volume_series_30d($metric);
    }''',
    '''    public static function admin_global_volume_series_20d(string $metric): array
    {
        return \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations02::telemetry_volume_series_20d($metric);
    }''',
    1,
    'wrapper de Volume',
)
admin2 = replace_exact(
    admin2,
    '$pageLoads30d = \\Prontoo\\Runtime\\AdminPages\\AdminPagesRuntimeOperations02::admin_global_volume_series_30d("page_load");',
    '$pageLoads20d = \\Prontoo\\Runtime\\AdminPages\\AdminPagesRuntimeOperations02::admin_global_volume_series_20d("page_load");',
    1,
    'série de Carregamento de Páginas do Volume',
)
admin2 = replace_exact(
    admin2,
    '$databaseQueries30d = \\Prontoo\\Runtime\\AdminPages\\AdminPagesRuntimeOperations02::admin_global_volume_series_30d("database_queries");',
    '$databaseRecords20d = \\Prontoo\\Runtime\\AdminPages\\AdminPagesRuntimeOperations02::admin_global_sequence_series_20d();',
    1,
    'série Consulta do Volume',
)
admin2 = replace_exact(
    admin2,
    'data-chart-window="velocity-24h-volume-30d"',
    'data-chart-window="velocity-24h-volume-20d"',
    1,
    'janela declarada dos gráficos',
)
admin2 = replace_exact(
    admin2,
    '"Volume", $pageLoads30d, $databaseQueries30d, "monitoring",',
    '"Volume", $pageLoads20d, $databaseRecords20d, "monitoring",',
    1,
    'fontes do gráfico Volume',
)
admin2 = replace_exact(
    admin2,
    '"overall_title" => "Carregamentos nos últimos 30 dias"',
    '"overall_title" => "Carregamentos nos últimos 20 dias"',
    1,
    'título global do Volume',
)
admin2 = replace_exact(
    admin2,
    '"summary_lead" => "cada ponto representa um intervalo completo de 24 horas; verde escuro é o volume de Carregamento de Páginas e verde claro é o volume de Consulta."',
    '"summary_lead" => "cada ponto representa um intervalo completo de 24 horas; verde escuro é o volume de Carregamento de Páginas e verde claro é Consulta, alimentada pela mesma variação líquida de Registros usada no card Registros."',
    1,
    'descrição acessível do Volume',
)
write(admin2_path, admin2)

contract_path = 'tools/telemetry-json-source-contract-check'
contract = read(contract_path)
old_source_assert = '''$assert(
    str_contains($admin2, 'admin_global_metric_series_24h("route")') &&
    str_contains($admin2, 'admin_global_metric_series_24h("database")') &&
    str_contains($admin2, 'admin_global_volume_series_30d("page_load")') &&
    str_contains($admin2, 'admin_global_volume_series_30d("database_queries")'),
    'gráficos devem usar as séries canônicas de latência e volume',
);'''
new_source_assert = '''$assert(
    str_contains($admin2, 'admin_global_metric_series_24h("route")') &&
    str_contains($admin2, 'admin_global_metric_series_24h("database")') &&
    str_contains($admin2, 'admin_global_volume_series_20d("page_load")') &&
    str_contains($admin2, 'admin_global_sequence_series_20d()') &&
    !str_contains($admin2, 'admin_global_volume_series_20d("database_queries")'),
    'Volume deve usar page_load e alimentar Consulta pela mesma série database.json do card Registros',
);'''
contract = replace_exact(contract, old_source_assert, new_source_assert, 1, 'contrato das fontes dos gráficos')
old_window_assert = '''$assert(
    str_contains($admin2, '"primary_label" => "Carregamento de Páginas"') &&
    str_contains($admin2, '"secondary_label" => "Consulta"') &&
    !str_contains($admin2, 'admin_global_volume_series_24h') &&
    str_contains($admin2, '"recent_points" => 1') &&
    str_contains($admin2, '"middle_points" => 7') &&
    str_contains($admin2, '"overall_title" => "Carregamentos nos últimos 30 dias"'),
    'Velocidade deve usar 24 horas e Volume deve usar 30 intervalos diários',
);'''
new_window_assert = '''$assert(
    str_contains($admin2, '"primary_label" => "Carregamento de Páginas"') &&
    str_contains($admin2, '"secondary_label" => "Consulta"') &&
    !str_contains($admin2, 'admin_global_volume_series_30d') &&
    str_contains($admin2, '"recent_points" => 1') &&
    str_contains($admin2, '"middle_points" => 7') &&
    str_contains($admin2, '"overall_title" => "Carregamentos nos últimos 20 dias"') &&
    str_contains($admin2, 'velocity-24h-volume-20d'),
    'Velocidade deve usar 24 horas e Volume deve alinhar 20 intervalos diários com Registros',
);'''
contract = replace_exact(contract, old_window_assert, new_window_assert, 1, 'contrato da janela do Volume')
volume_test_start = contract.find('$volumeDayUs = 86400 * 1000000;')
volume_test_end = contract.find('$footerRecordSamples = [];', volume_test_start)
if volume_test_start < 0 or volume_test_end <= volume_test_start:
    raise SystemExit('teste canônico do Volume não localizado')
new_volume_test = '''$volumeDayUs = 86400 * 1000000;
$volumeEvents = [
    [
        'fim_unix_us' => $chartNowUs - 2 * $volumeDayUs + 1000000,
        'database_query_count' => 2,
        'database_query_instrumented' => true,
    ],
    [
        'fim_unix_us' => $chartNowUs - 2 * $volumeDayUs + 2000000,
        'database_query_count' => 1,
        'database_query_instrumented' => true,
    ],
    [
        'fim_unix_us' => $chartNowUs,
        'database_query_count' => 99,
        'database_query_instrumented' => true,
    ],
];
$volumePages = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations02::telemetry_volume_series_20d_from_events('page_load', $volumeEvents, $chartNowUs);
$volumeRecordSamples = [];
$volumeRecordWindowStart = intdiv($chartNowUs, 1000000) - 20 * 86400;
for ($index = 0; $index < 20; $index++) {
    $volumeRecordSamples[] = [
        'captured_at_unix' => $volumeRecordWindowStart + $index * 86400 + 300,
        'total_records' => 1000 + $index,
        'delta_records' => $index + 1,
    ];
}
$volumeQueries = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations03::telemetry_database_record_series_from_samples(
    $volumeRecordSamples,
    intdiv($chartNowUs, 1000000),
);
foreach ([$volumePages, $volumeQueries] as $dailySeries) {
    $assert(count($dailySeries) === 20, 'Volume deve conter exatamente 20 pontos');
    for ($index = 1; $index < count($dailySeries); $index++) {
        $assert(
            (int) $dailySeries[$index]['ts'] - (int) $dailySeries[$index - 1]['ts'] === 86400,
            'cada ponto de Volume deve representar um intervalo de 24 horas',
        );
    }
    $assert(
        ($dailySeries[0]['period'] ?? '') === 'previous' &&
        ($dailySeries[10]['period'] ?? '') === 'current',
        'Volume deve preservar os dois períodos de 10 dias',
    );
}
$assert(
    ($volumePages[17]['value'] ?? -1) == 0 &&
    ($volumePages[19]['value'] ?? -1) == 0 &&
    !empty($volumePages[17]['observed']) &&
    !empty($volumePages[19]['observed']),
    'intervalos de page_load sem eventos devem permanecer como pontos explícitos em zero',
);
$assert(
    (int) $volumePages[18]['value'] === 2 &&
    (int) $volumeQueries[19]['value'] === 20,
    'Volume deve alinhar Carregamento de Páginas e Consulta, com Consulta alimentada pelos deltas de database.json',
);
'''
contract = contract[:volume_test_start] + new_volume_test + contract[volume_test_end:]
contract = contract.replace(
    '$volumeDayUs, $volumeEvents, $volumePages, $volumeQueries, $footerRecordSamples,',
    '$volumeDayUs, $volumeEvents, $volumePages, $volumeQueries, $volumeRecordSamples, $volumeRecordWindowStart, $footerRecordSamples,',
    1,
)
write(contract_path, contract)

telemetry_doc_path = 'docs/performance/telemetry.md'
telemetry_doc = read(telemetry_doc_path)
telemetry_doc = replace_exact(
    telemetry_doc,
    '`Volume` usa 30 intervalos consecutivos de 24 horas imediatamente anteriores ao timestamp da leitura. Cada um dos 30 pontos contém as quantidades totais de seu intervalo: Carregamento de Páginas usa `#1f6f56` com opacidade `0.5` e Consulta usa `#347963` com opacidade `0.5`.',
    '`Volume` usa 20 intervalos consecutivos de 24 horas imediatamente anteriores ao timestamp da leitura, alinhados à retenção canônica de `database.json`. Carregamento de Páginas conta os page loads do mesmo intervalo; Consulta usa a variação líquida de registros persistida em `ssd/telemetry/database.json`, exatamente a mesma fonte do card Registros. Carregamento de Páginas usa `#1f6f56` com opacidade `0.5` e Consulta usa `#347963` com opacidade `0.5`.',
    1,
    'documentação da fonte de Volume',
)
telemetry_doc = replace_exact(
    telemetry_doc,
    'Velocidade mantém um ponto para cada minuto e Volume mantém um ponto para cada intervalo de 24 horas, inclusive quando não há eventos. Nesse caso, as duas séries recebem valor zero.',
    'Velocidade mantém um ponto para cada minuto e Volume mantém um ponto para cada um dos 20 intervalos de 24 horas. Carregamento de Páginas recebe zero quando não há eventos; Consulta preserva a observabilidade própria de `database.json`, sem inventar amostras ausentes.',
    1,
    'documentação dos pontos de Volume',
)
telemetry_doc = replace_exact(
    telemetry_doc,
    'Volume de Carregamento de Páginas conta todos os `page_load` canônicos da janela. Volume de Consulta soma `database_query_count` apenas onde a instrumentação está presente. A série de estoque persistida em `database.json` representa `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro; essa série é a fonte canônica do card Registros e da área Consulta do rodapé.',
    'Volume de Carregamento de Páginas conta todos os `page_load` canônicos da janela de 20 dias. Volume de Consulta usa a série de estoque persistida em `database.json`, que representa `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro. Essa é exatamente a mesma fonte canônica do card Registros e da área Consulta do rodapé; `database_query_count` permanece restrito à telemetria de velocidade e diagnóstico de consultas, não ao Volume de Consulta.',
    1,
    'interpretação do Volume',
)
write(telemetry_doc_path, telemetry_doc)

control_doc_path = 'docs/operations/developer-control-center.md'
control_doc = read(control_doc_path)
control_doc = replace_exact(
    control_doc,
    'Velocidade usa os 1.440 minutos completos das últimas 24 horas e uma marca por hora no eixo inferior; Volume usa 30 pontos, um para cada intervalo de 24 horas dos últimos 30 dias.',
    'Velocidade usa os 1.440 minutos completos das últimas 24 horas e uma marca por hora no eixo inferior; Volume usa 20 pontos, um para cada intervalo de 24 horas dos últimos 20 dias, e a série Consulta usa a mesma variação líquida de `database.json` do card Registros.',
    1,
    'contrato operacional do Volume',
)
control_doc = replace_exact(
    control_doc,
    '- A PageHead de Consultórios contém, nesta ordem, **Consultórios**, **Usuários**, **Mensagens**, **Avisos** e **Auditoria**.',
    '- A PageHead de Consultórios contém, nesta ordem, **Consultórios**, **Usuários**, **Mensagens**, **Avisos** e **Auditoria**. A lista abre em **Ativos** por padrão; **Todos** permanece um filtro explícito, e limpar busca/filtro retorna ao estado Ativos.',
    1,
    'contrato do filtro inicial de Consultórios',
)
write(control_doc_path, control_doc)

agents_path = 'AGENTS.md'
agents = read(agents_path)
agents = replace_exact(
    agents,
    'Depois dos três cards, Métricas preserva os gráficos canônicos Velocidade e Volume. Velocidade usa os 1.440 minutos completos das últimas 24 horas, com um ponto por minuto e 24 marcas horárias centralizadas. Volume usa 30 intervalos consecutivos de 24 horas, com exatamente 30 pontos nos últimos 30 dias. Nos dois gráficos, Carregamento de Páginas é a série primária, Consulta é a série secundária, intervalos sem eventos são pontos explícitos em zero e a geometria liga os valores consecutivos por segmentos retos. As séries são exibidas somente pelas áreas inferiores preenchidas, sem contornos ou pontos terminais visíveis. Em Velocidade, Carregamento de Páginas usa `#05391f` e Consulta usa `#ddf6e8`, ambas sem transparência; Volume preserva sua paleta e transparência próprias. Velocidade compara durações médias e Volume compara quantidades. Os gráficos atualizam a cada minuto e não substituem nem incorporam a tabela da tela Rotas.',
    'Depois dos três cards, Métricas preserva os gráficos canônicos Velocidade e Volume. Velocidade usa os 1.440 minutos completos das últimas 24 horas, com um ponto por minuto e 24 marcas horárias centralizadas. Volume usa 20 intervalos consecutivos de 24 horas, com exatamente 20 pontos alinhados à retenção de `ssd/telemetry/database.json`. Carregamento de Páginas é a série primária; Consulta é a série secundária e, em Volume, usa exatamente a mesma variação líquida de registros do card Registros, nunca `database_query_count`. A geometria liga os valores consecutivos por segmentos retos. As séries são exibidas somente pelas áreas inferiores preenchidas, sem contornos ou pontos terminais visíveis. Em Velocidade, Carregamento de Páginas usa `#05391f` e Consulta usa `#ddf6e8`, ambas sem transparência; Volume preserva sua paleta e transparência próprias. Velocidade compara durações médias e Volume compara quantidades. Os gráficos atualizam a cada minuto e não substituem nem incorporam a tabela da tela Rotas.',
    1,
    'guardrail canônico de telemetria',
)
write(agents_path, agents)

excluded = {'.git', 'ssd', 'vendor', 'node_modules'}
for path in root.rglob('*'):
    if not path.is_file() or any(part in excluded for part in path.parts) or path in {workflow_path, script_path}:
        continue
    try:
        text = path.read_text(encoding='utf-8')
    except (UnicodeDecodeError, OSError):
        continue
    if old_version in text:
        path.write_text(text.replace(old_version, new_version), encoding='utf-8')

version_path = root / 'version.json'
version = json.loads(version_path.read_text(encoding='utf-8'))
if version.get('version') != new_version or version.get('release') != new_version:
    raise SystemExit('bump textual não convergiu para a nova versão')
now = datetime.now(ZoneInfo('America/Cuiaba')).replace(microsecond=0)
iso = now.isoformat()
version.update({
    'version': new_version,
    'release': new_version,
    'asset_version': new_version,
    'generated_at_unix': int(now.timestamp()),
    'generated_at': iso,
    'updated_at': iso,
    'build': '1.8.18.3-admin-clinics-active-volume-record-source',
    'package_type': 'incremental_refinement',
    'database_changes': False,
    'schema_changes': False,
    'logic_changes': True,
    'visual_changes': True,
    'documentation_changes': True,
    'functional_equivalence_policy': 'developer_clinics_default_active_and_volume_consulta_database_record_source_without_schema_change',
    'notes': 'Consultórios abre em Ativos por padrão; Volume alinha 20 dias e alimenta Consulta pela mesma variação líquida de database.json do card Registros.',
    'deployment_sync_id': 'github-prontoo-1.8.18.3-admin-clinics-active-volume-record-source',
    'deployment_sync_requested_at': iso,
    'release_date': now.date().isoformat(),
    'changelog': {
        'title': 'Filtro Ativos inicial e Volume alinhado a Registros',
        'items': [
            'define Ativos como filtro inicial da lista global de Consultórios do Desenvolvedor',
            'mantém Todos como filtro explícito e faz Limpar retornar ao estado padrão Ativos',
            'faz a série Consulta do gráfico Volume usar a mesma variação líquida de database.json do card Registros',
            'alinha as duas séries de Volume em 20 intervalos consecutivos de 24 horas, sem misturar janelas temporais diferentes',
            'preserva a ordenação dos consultórios por login recente, a regra Vencendo de 10 dias e o schema atual'
        ]
    }
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')

for name in ['app-icon', 'favicon', 'pix', 'prontoo-mark']:
    pass
asset_pairs = [
    (f'public/assets/app-icon-{old_version}.png', f'public/assets/app-icon-{new_version}.png'),
    (f'public/assets/favicon-{old_version}.ico', f'public/assets/favicon-{new_version}.ico'),
    (f'public/assets/favicon-{old_version}.png', f'public/assets/favicon-{new_version}.png'),
    (f'public/assets/pix-{old_version}.svg', f'public/assets/pix-{new_version}.svg'),
    (f'public/assets/prontoo-mark-{old_version}.png', f'public/assets/prontoo-mark-{new_version}.png'),
]
for old_path, new_path in asset_pairs:
    old = root / old_path
    new = root / new_path
    if not old.is_file():
        raise SystemExit(f'asset anterior ausente: {old_path}')
    new.write_bytes(old.read_bytes())
    old.unlink()

manifest_path = root / 'app/Presentation/Styles/styles.manifest.json'
source_root = root / 'app/Presentation/Styles'
manifest = json.loads(manifest_path.read_text(encoding='utf-8'))
old_literal = old_version.encode()
new_literal = new_version.encode()
source_entries = {entry['path']: entry for entry in manifest['sources']}
for rel, entry in source_entries.items():
    old_source = subprocess.check_output(['git', 'show', f'origin/prontoo:app/Presentation/Styles/{rel}'])
    new_source = (source_root / rel).read_bytes()
    if old_source == new_source:
        continue
    if old_source.replace(old_literal, new_literal) != new_source:
        raise SystemExit(f'mudança CSS fora da troca de versão em {rel}')
    entry['sha256'] = hashlib.sha256(new_source).hexdigest()
    entry['bytes'] = len(new_source)
    entry['important_count'] = new_source.count(b'!important')
    entry['route_scope_count'] = new_source.count(b'body[data-route=')
    new_offset = 0
    reconstructed = bytearray()
    for segment in [seg for seg in manifest['sequence'] if seg['source'] == rel]:
        old_offset = int(segment['offset'])
        old_bytes = int(segment['bytes'])
        old_slice = old_source[old_offset:old_offset + old_bytes]
        new_slice = old_slice.replace(old_literal, new_literal)
        segment['offset'] = new_offset
        segment['bytes'] = len(new_slice)
        segment['sha256'] = hashlib.sha256(new_slice).hexdigest()
        segment['important_count'] = new_slice.count(b'!important')
        segment['route_scope_count'] = new_slice.count(b'body[data-route=')
        reconstructed.extend(new_slice)
        new_offset += len(new_slice)
    if bytes(reconstructed) != new_source:
        raise SystemExit(f'sequência CSS não reconstrói {rel}')

source_data = {entry['path']: (source_root / entry['path']).read_bytes() for entry in manifest['sources']}
built = bytearray()
next_offsets = {path: 0 for path in source_data}
for segment in manifest['sequence']:
    source = segment['source']
    offset = int(segment['offset'])
    size = int(segment['bytes'])
    if offset != next_offsets[source]:
        raise SystemExit(f'cobertura CSS descontínua em {source}')
    chunk = source_data[source][offset:offset + size]
    if len(chunk) != size or hashlib.sha256(chunk).hexdigest() != segment['sha256']:
        raise SystemExit(f'hash CSS divergente em {source}')
    built.extend(chunk)
    next_offsets[source] += size
for source, data in source_data.items():
    if next_offsets[source] != len(data):
        raise SystemExit(f'fonte CSS não integralmente coberta: {source}')
manifest['artifact_contract']['sha256'] = hashlib.sha256(bytes(built)).hexdigest()
manifest['artifact_contract']['bytes'] = len(built)
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')
(root / manifest['artifact']).write_bytes(bytes(built))

visual_path = root / 'app/presentation.visual-contract.json'
visual = json.loads(visual_path.read_text(encoding='utf-8'))
visual['version'] = new_version
visual['delivery']['current_sha256'] = hashlib.sha256(bytes(built)).hexdigest()
visual['delivery']['current_bytes'] = len(built)
visual_path.write_text(json.dumps(visual, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')
