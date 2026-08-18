#!/usr/bin/env python3
import hashlib
import json
import re
import shutil
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

ROOT = Path(__file__).resolve().parents[1]
OLD_VERSION = "1.8.18.6"
NEW_VERSION = "1.8.18.7"


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    file = ROOT / path
    text = file.read_text()
    actual = text.count(old)
    if actual != count:
        raise RuntimeError(f"{path}: expected {count} occurrence(s), found {actual}: {old[:100]!r}")
    file.write_text(text.replace(old, new, count))


def patch_telemetry_summary() -> None:
    path = "app/Infrastructure/SupportTelemetry/SupportTelemetryInfrastructureOperations02.php"
    replace(
        path,
        '''        $empty = [\n            "pages" => 0,\n            "records" => 0,\n            "landing" => 0,\n        ];''',
        '''        $empty = [\n            "pages" => 0,\n            "page_load_samples" => 0,\n            "page_load_duration_ns" => 0,\n            "page_load_average_ms" => null,\n            "records" => 0,\n            "landing" => 0,\n        ];''',
    )
    replace(
        path,
        '''            if ($target === "current") {\n                $current["pages"]++;\n                if ((string) ($event["rota"] ?? "") === "landing") {\n                    $current["landing"]++;\n                }\n                continue;\n            }\n            $previous["pages"]++;\n            if ((string) ($event["rota"] ?? "") === "landing") {\n                $previous["landing"]++;\n            }\n        }\n        $recordsVariation = null;''',
        '''            if ($target === "current") {\n                $current["pages"]++;\n                if (!empty($event["speed_observed"])) {\n                    $current["page_load_samples"]++;\n                    $current["page_load_duration_ns"] += max(0, (int) ($event["duracao_ns"] ?? 0));\n                }\n                if ((string) ($event["rota"] ?? "") === "landing") {\n                    $current["landing"]++;\n                }\n                continue;\n            }\n            $previous["pages"]++;\n            if (!empty($event["speed_observed"])) {\n                $previous["page_load_samples"]++;\n                $previous["page_load_duration_ns"] += max(0, (int) ($event["duracao_ns"] ?? 0));\n            }\n            if ((string) ($event["rota"] ?? "") === "landing") {\n                $previous["landing"]++;\n            }\n        }\n        foreach (["current", "previous"] as $periodKey) {\n            $period =& ${$periodKey};\n            $samples = max(0, (int) ($period["page_load_samples"] ?? 0));\n            $durationNs = max(0, (int) ($period["page_load_duration_ns"] ?? 0));\n            $period["page_load_average_ms"] = $samples > 0\n                ? ($durationNs / $samples) / 1000000\n                : null;\n            unset($period["page_load_samples"], $period["page_load_duration_ns"]);\n            unset($period);\n        }\n        $recordsVariation = null;''',
    )
    replace(
        path,
        '''                "pages_pct" => \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations01::telemetry_percentage_variation(\n                    $current["pages"],\n                    $previous["pages"],\n                ),\n                "records_pct" => $recordsVariation,''',
        '''                "pages_pct" => \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations01::telemetry_percentage_variation(\n                    $current["pages"],\n                    $previous["pages"],\n                ),\n                "page_load_average_ms_pct" => \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations01::telemetry_nullable_percentage_variation(\n                    isset($current["page_load_average_ms"]) ? (float) $current["page_load_average_ms"] : null,\n                    isset($previous["page_load_average_ms"]) ? (float) $previous["page_load_average_ms"] : null,\n                ),\n                "records_pct" => $recordsVariation,''',
    )


def patch_card_renderer() -> None:
    path = "app/Presentation/AdminPages/AdminPagesPresentationOperations01.php"
    replace(
        path,
        '''    public static function admin_metric_comparison_card_html(\n        string $label,\n        int $value,\n        string $icon,\n        ?float $variation,\n        string $note,\n    ): string {\n        return '<article class="stat-card telemetry-kpi-card">' .\n            \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::icon($icon) .\n            '<div><div class="telemetry-kpi-value"><b>' .\n            \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::n($value) .\n            '</b>' .''',
        '''    public static function admin_metric_comparison_card_html(\n        string $label,\n        int|float|null $value,\n        string $icon,\n        ?float $variation,\n        string $note,\n        string $valueType = "count",\n    ): string {\n        $displayValue = $valueType === "ms"\n            ? ($value === null ? "—" : self::admin_metric_duration_label((float) $value))\n            : \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::n((int) ($value ?? 0));\n        return '<article class="stat-card telemetry-kpi-card">' .\n            \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::icon($icon) .\n            '<div><div class="telemetry-kpi-value"><b>' .\n            \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e($displayValue) .\n            '</b>' .''',
    )


def patch_metrics_presentation() -> None:
    path = "app/Presentation/AdminPages/AdminPagesPresentationOperations03.php"
    replace(
        path,
        '''        return '<section class="admin-performance-screen admin-metrics-screen"><div class="three admin-performance-stats admin-metrics-kpis" aria-label="Métricas comparativas dos últimos 10 dias">' .\n            \\Prontoo\\Presentation\\AdminPages\\AdminPagesPresentationOperations01::admin_metric_comparison_card_html(\n                "Páginas",\n                max(0, (int) ($current["pages"] ?? 0)),\n                "web",\n                $variation("pages_pct"),\n                "carregamentos nos últimos 10 dias",\n            ) .\n            \\Prontoo\\Presentation\\AdminPages\\AdminPagesPresentationOperations01::admin_metric_comparison_card_html(\n                "Registros",''',
        '''        return '<section class="admin-performance-screen admin-metrics-screen"><div class="wide global-telemetry-grid admin-performance-stats admin-metrics-kpis" aria-label="Métricas comparativas dos últimos 10 dias">' .\n            \\Prontoo\\Presentation\\AdminPages\\AdminPagesPresentationOperations01::admin_metric_comparison_card_html(\n                "Páginas",\n                max(0, (int) ($current["pages"] ?? 0)),\n                "web",\n                $variation("pages_pct"),\n                "carregamentos nos últimos 10 dias",\n            ) .\n            \\Prontoo\\Presentation\\AdminPages\\AdminPagesPresentationOperations01::admin_metric_comparison_card_html(\n                "Tempo médio",\n                isset($current["page_load_average_ms"]) && is_numeric($current["page_load_average_ms"])\n                    ? (float) $current["page_load_average_ms"]\n                    : null,\n                "speed",\n                $variation("page_load_average_ms_pct"),\n                "carregamento médio das páginas nos últimos 10 dias",\n                "ms",\n            ) .\n            \\Prontoo\\Presentation\\AdminPages\\AdminPagesPresentationOperations01::admin_metric_comparison_card_html(\n                "Registros",''',
    )


def patch_contracts() -> None:
    path = "tools/telemetry-json-source-contract-check"
    replace(path, 'foreach (["Páginas", "Registros", "Landing"] as $label) {', 'foreach (["Páginas", "Tempo médio", "Registros", "Landing"] as $label) {')
    replace(path, "'gráficos de Velocidade e Volume devem aparecer depois dos três cards de Métricas',", "'gráficos de Velocidade e Volume devem aparecer depois dos quatro cards de Métricas',")
    marker = '''$assert(\n    ($metricSummary['window_mode'] ?? '') === 'rolling_20d_10x10' &&\n    ($metricCurrent['pages'] ?? -1) === 2 &&\n    ($metricPrevious['pages'] ?? -1) === 2 &&\n    ($metricCurrent['records'] ?? -1) === 9 &&\n    ($metricPrevious['records'] ?? -1) === 5 &&\n    ($metricCurrent['landing'] ?? -1) === 1 &&\n    ($metricPrevious['landing'] ?? -1) === 1 &&\n    abs((float) ($metricVariations['pages_pct'] ?? 999) - 0.0) < 0.000001 &&\n    abs((float) ($metricVariations['records_pct'] ?? 999) - 80.0) < 0.000001 &&\n    abs((float) ($metricVariations['landing_pct'] ?? 999) - 0.0) < 0.000001,\n    'comparação 10x10 de Páginas, Registros e Landing divergente',\n);'''
    addition = marker + '''\n$averageSummary = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations02::telemetry_developer_metrics_summary_from_events(\n    [\n        ['evento_id' => 'avg-prev-1', 'fim_unix_us' => $nowUs - 11 * $dayUs, 'rota' => 'patient', 'speed_observed' => true, 'duracao_ns' => 100000000],\n        ['evento_id' => 'avg-prev-2', 'fim_unix_us' => $nowUs - 12 * $dayUs, 'rota' => 'landing', 'speed_observed' => true, 'duracao_ns' => 300000000],\n        ['evento_id' => 'avg-current-1', 'fim_unix_us' => $nowUs - 1 * $dayUs, 'rota' => 'patient', 'speed_observed' => true, 'duracao_ns' => 200000000],\n        ['evento_id' => 'avg-current-2', 'fim_unix_us' => $nowUs - 2 * $dayUs, 'rota' => 'landing', 'speed_observed' => true, 'duracao_ns' => 400000000],\n        ['evento_id' => 'avg-current-unobserved', 'fim_unix_us' => $nowUs - 3 * $dayUs, 'rota' => 'patient', 'speed_observed' => false, 'duracao_ns' => 999000000],\n    ],\n    $nowUs,\n    ["current_total" => 0, "previous_total" => 0, "variation_pct" => 0.0],\n);\n$averageCurrent = (array) ($averageSummary['current'] ?? []);\n$averagePrevious = (array) ($averageSummary['previous'] ?? []);\n$averageVariations = (array) ($averageSummary['variations'] ?? []);\n$assert(\n    abs((float) ($averageCurrent['page_load_average_ms'] ?? -1) - 300.0) < 0.000001 &&\n    abs((float) ($averagePrevious['page_load_average_ms'] ?? -1) - 200.0) < 0.000001 &&\n    abs((float) ($averageVariations['page_load_average_ms_pct'] ?? -999) - 50.0) < 0.000001,\n    'Tempo médio deve usar apenas page loads com speed_observed na comparação 10x10',\n);'''
    replace(path, marker, addition)

    ux = "tools/presentation-ux-contract.mjs"
    replace(
        ux,
        '''    markup: `<body class="scope-global"><main id="conteudo"><section id="telemetry-layout-target" class="three admin-performance-stats admin-metrics-kpis">${telemetryCards(3)}</section></main></body>`,\n    expectations: [\n      { width: 1280, columns: 3, rows: 1 },\n      { width: 390, columns: 1, rows: 3 }\n    ]''',
        '''    markup: `<body class="scope-global"><main id="conteudo"><section id="telemetry-layout-target" class="wide global-telemetry-grid admin-performance-stats admin-metrics-kpis">${telemetryCards(4)}</section></main></body>`,\n    expectations: [\n      { width: 1280, columns: 4, rows: 1 },\n      { width: 390, columns: 2, rows: 2 }\n    ]''',
    )
    replace(ux, "process.stdout.write('presentation-ux-contract: developer metrics 1280=3x1 390=1x3\\n');", "process.stdout.write('presentation-ux-contract: developer metrics 1280=4x1 390=2x2\\n');")


def patch_docs() -> None:
    replace(
        "AGENTS.md",
        '''Métricas do Desenvolvedor contém exatamente três cards comparativos: Páginas, Registros e Landing. Em viewport acima de 980 px aparecem em uma única linha com três colunas; em 980 px ou menos aparecem em uma coluna. Os cards são filhos diretos de `.admin-metrics-kpis`. Toda mudança nesse contrato deve passar `tools/presentation-ux-contract.mjs --check`, que verifica por computed style 1280 px (3×1) e 390 px (1×3).\n\nCada card compara os 10 dias móveis recentes aos 10 imediatamente anteriores. Páginas conta page loads canônicos; Registros soma a variação líquida do estoque de linhas persistida em `ssd/telemetry/database.json`, calculada pelo Maestro como `total atual - total imediatamente anterior` sobre todas as tabelas-base; Landing conta a rota canônica `landing`. O primeiro total observado é apenas saldo inicial e não entra no crescimento. Deltas negativos são preservados. SQL, parâmetros, resultados e dados clínicos não são persistidos.\n\nDepois dos três cards, Métricas preserva''',
        '''Métricas do Desenvolvedor contém exatamente quatro cards comparativos: Páginas, Tempo médio, Registros e Landing. Em viewport acima de 980 px aparecem em uma única linha com quatro colunas; em 980 px ou menos aparecem em grade de duas colunas por duas linhas. Os cards são filhos diretos de `.admin-metrics-kpis`. Toda mudança nesse contrato deve passar `tools/presentation-ux-contract.mjs --check`, que verifica por computed style 1280 px (4×1) e 390 px (2×2).\n\nCada card compara os 10 dias móveis recentes aos 10 imediatamente anteriores. Páginas conta page loads canônicos; Tempo médio calcula a duração média somente dos page loads com amostra observada em `ssd/telemetry/speed.json`; Registros soma a variação líquida do estoque de linhas persistida em `ssd/telemetry/database.json`, calculada pelo Maestro como `total atual - total imediatamente anterior` sobre todas as tabelas-base; Landing conta a rota canônica `landing`. O primeiro total observado é apenas saldo inicial e não entra no crescimento. Deltas negativos são preservados. SQL, parâmetros, resultados e dados clínicos não são persistidos.\n\nDepois dos quatro cards, Métricas preserva''',
    )
    replace(
        "docs/operations/developer-control-center.md",
        "1. **Métricas** — tela inicial, com os três comparativos essenciais de volume da plataforma.",
        "1. **Métricas** — tela inicial, com quatro comparativos essenciais de uso e desempenho da plataforma.",
    )
    replace(
        "docs/operations/developer-control-center.md",
        '''- Métricas abre com **Páginas**, **Registros** e **Landing**. Cada card compara os últimos 10 dias móveis com os 10 dias imediatamente anteriores.\n- Páginas conta carregamentos HTML concluídos; Registros soma a variação líquida de linhas registrada pelo Maestro em `ssd/telemetry/database.json`, excluindo o saldo inicial e preservando deltas negativos; Landing conta carregamentos da Landing Page.''',
        '''- Métricas abre com **Páginas**, **Tempo médio**, **Registros** e **Landing**. Cada card compara os últimos 10 dias móveis com os 10 dias imediatamente anteriores.\n- Páginas conta carregamentos HTML concluídos; Tempo médio calcula a duração média dos page loads com amostra observada em `ssd/telemetry/speed.json`; Registros soma a variação líquida de linhas registrada pelo Maestro em `ssd/telemetry/database.json`, excluindo o saldo inicial e preservando deltas negativos; Landing conta carregamentos da Landing Page.''',
    )
    replace(
        "docs/performance/telemetry.md",
        '''Os cards de Métricas do Desenvolvedor usam duas janelas móveis contíguas de 10 dias. Páginas conta os carregamentos HTML concluídos; Registros soma os deltas de `ssd/telemetry/database.json`, em que cada captura do Maestro mede exatamente o total de linhas de todas as tabelas-base e registra `total atual - total imediatamente anterior`; Landing conta somente os carregamentos cuja rota canônica é `landing`. O primeiro total é saldo inicial e não entra em Registros. Deltas negativos são preservados. O percentual representa o período recente em relação aos 10 dias imediatamente anteriores; quando não há cobertura completa ou o período anterior é zero e o atual é positivo, a variação permanece indefinida.\n\nLogo abaixo desses cards,''',
        '''Os quatro cards de Métricas do Desenvolvedor usam duas janelas móveis contíguas de 10 dias. Páginas conta os carregamentos HTML concluídos; Tempo médio usa somente page loads com amostra observada em `ssd/telemetry/speed.json` e calcula `soma das durações / quantidade de amostras`; Registros soma os deltas de `ssd/telemetry/database.json`, em que cada captura do Maestro mede exatamente o total de linhas de todas as tabelas-base e registra `total atual - total imediatamente anterior`; Landing conta somente os carregamentos cuja rota canônica é `landing`. O primeiro total é saldo inicial e não entra em Registros. Deltas negativos são preservados. O percentual representa o período recente em relação aos 10 dias imediatamente anteriores; quando não há período anterior comparável, a variação permanece indefinida.\n\nLogo abaixo desses quatro cards,''',
    )


def bump_release() -> None:
    version_path = ROOT / "version.json"
    data = json.loads(version_path.read_text())
    now = datetime.now(ZoneInfo("America/Cuiaba"))
    stamp = now.isoformat(timespec="seconds")
    data.update({
        "version": NEW_VERSION,
        "release": NEW_VERSION,
        "generated_at_unix": int(now.timestamp()),
        "generated_at": stamp,
        "updated_at": stamp,
        "build": "1.8.18.7-restore-page-load-average-metric",
        "asset_version": NEW_VERSION,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "documentation_changes": True,
        "functional_equivalence_policy": "developer_metrics_restores_page_load_average_card_from_existing_speed_telemetry_without_schema_change",
        "notes": "Restaura o card Tempo médio em Métricas usando a duração observada de page loads em speed.json, mantendo a comparação 10x10 e os gráficos existentes.",
        "deployment_sync_id": "github-prontoo-1.8.18.7-restore-page-load-average-metric",
        "deployment_sync_requested_at": stamp,
        "release_date": now.date().isoformat(),
        "changelog": {
            "title": "Restaura Tempo médio em Métricas",
            "items": [
                "restaura o quarto card Tempo médio na tela Métricas do Desenvolvedor",
                "calcula o valor somente com page loads cuja duração foi observada pela fonte canônica speed.json",
                "mantém a comparação móvel dos 10 dias recentes contra os 10 dias imediatamente anteriores",
                "restaura o grid responsivo de quatro KPIs em 4x1 no desktop e 2x2 no mobile",
                "adiciona contrato executável para impedir nova supressão do card e excluir eventos sem speed_observed da média",
                "preserva os gráficos Velocidade e Volume, o banco de dados e o schema sem novas coletas",
            ],
        },
    })
    version_path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n")

    replace("app/prontoo.php", f'const PRONTOO_VERSION_FALLBACK = "{OLD_VERSION}";', f'const PRONTOO_VERSION_FALLBACK = "{NEW_VERSION}";')
    replace("app/prontoo.php", f'const PRONTOO_ASSET_REV_FALLBACK = "{OLD_VERSION}";', f'const PRONTOO_ASSET_REV_FALLBACK = "{NEW_VERSION}";')

    visual_path = ROOT / "app/presentation.visual-contract.json"
    visual = json.loads(visual_path.read_text())
    visual["version"] = NEW_VERSION
    visual_path.write_text(json.dumps(visual, ensure_ascii=False, indent=4) + "\n")


def republish_assets() -> None:
    assets = ROOT / "public/assets"
    for old, new in [
        (f"app-icon-{OLD_VERSION}.png", f"app-icon-{NEW_VERSION}.png"),
        (f"prontoo-mark-{OLD_VERSION}.png", f"prontoo-mark-{NEW_VERSION}.png"),
        (f"favicon-{OLD_VERSION}.png", f"favicon-{NEW_VERSION}.png"),
        (f"favicon-{OLD_VERSION}.ico", f"favicon-{NEW_VERSION}.ico"),
        (f"pix-{OLD_VERSION}.svg", f"pix-{NEW_VERSION}.svg"),
    ]:
        src = assets / old
        dst = assets / new
        shutil.copyfile(src, dst)
        src.unlink()
    replace("design/styles/application.css", f"pix-{OLD_VERSION}.svg", f"pix-{NEW_VERSION}.svg")


def main() -> None:
    patch_telemetry_summary()
    patch_card_renderer()
    patch_metrics_presentation()
    patch_contracts()
    patch_docs()
    bump_release()
    republish_assets()
    print(json.dumps({"ok": True, "release": NEW_VERSION, "card": "Tempo médio", "source": "speed.json", "schema_changes": False}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
