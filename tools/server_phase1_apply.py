from pathlib import Path
import json
import re
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.1"
PREVIOUS = "1.7.29.6"
BUILD = "1.7.30.1-server-phase1-performance-budgets"
NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
NOW_UNIX = int(time.time())


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(path: str, old: str, new: str) -> None:
    target = ROOT / path
    source = target.read_text(encoding="utf-8")
    count = source.count(old)
    if count != 1:
        raise SystemExit(f"{path}: esperado 1 trecho, encontrado {count}")
    target.write_text(source.replace(old, new, 1), encoding="utf-8")


def update_json(path: str, values: dict) -> None:
    target = ROOT / path
    data = json.loads(target.read_text(encoding="utf-8"))
    data.update(values)
    target.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")


write("app/Core/Performance/PerformanceBudget.php", """<?php
declare(strict_types=1);

namespace Prontoo\\Core\\Performance;

use InvalidArgumentException;

final class PerformanceBudget
{
    private const METRICS = [
        'max_elapsed_ms',
        'max_query_ms',
        'max_queries',
        'max_wide_selects',
        'max_module_files',
        'max_module_bytes',
    ];

    public static function resolve(array $contract, string $route): array
    {
        $defaults = isset($contract['defaults']) && is_array($contract['defaults'])
            ? $contract['defaults']
            : [];
        $routes = isset($contract['routes']) && is_array($contract['routes'])
            ? $contract['routes']
            : [];
        $specific = isset($routes[$route]) && is_array($routes[$route])
            ? $routes[$route]
            : [];
        return self::normalize(array_replace($defaults, $specific));
    }

    public static function evaluate(array $contract, string $route, array $event): array
    {
        $budget = self::resolve($contract, $route);
        $mapping = [
            'max_elapsed_ms' => 'elapsed_ms',
            'max_query_ms' => 'query_ms',
            'max_queries' => 'queries',
            'max_wide_selects' => 'wide_selects',
            'max_module_files' => 'module_files',
            'max_module_bytes' => 'module_bytes',
        ];
        $breaches = [];
        foreach ($mapping as $limitKey => $eventKey) {
            $actual = max(0, (float) ($event[$eventKey] ?? 0));
            $limit = (float) $budget[$limitKey];
            if ($actual > $limit) {
                $breaches[$eventKey] = [
                    'actual' => $actual,
                    'limit' => $limit,
                    'ratio' => round($limit > 0 ? $actual / $limit : 0, 4),
                ];
            }
        }
        return [
            'route' => $route,
            'ok' => $breaches === [],
            'budget' => $budget,
            'breaches' => $breaches,
        ];
    }

    public static function normalize(array $budget): array
    {
        $normalized = [];
        foreach (self::METRICS as $metric) {
            if (!array_key_exists($metric, $budget) || !is_numeric($budget[$metric])) {
                throw new InvalidArgumentException('Orçamento de desempenho incompleto: ' . $metric);
            }
            $value = (float) $budget[$metric];
            if ($value < 0) {
                throw new InvalidArgumentException('Orçamento de desempenho negativo: ' . $metric);
            }
            $normalized[$metric] = in_array($metric, ['max_elapsed_ms', 'max_query_ms'], true)
                ? round($value, 3)
                : (int) round($value);
        }
        return $normalized;
    }
}
""")

budgets = {
    "schema": "prontoo-performance-budgets-v1",
    "version": VERSION,
    "policy": "budgets_are_guardrails_and_must_be_tightened_from_production_baselines_without_weakening_security_or_integrity",
    "defaults": {
        "max_elapsed_ms": 2500,
        "max_query_ms": 900,
        "max_queries": 120,
        "max_wide_selects": 4,
        "max_module_files": 40,
        "max_module_bytes": 2500000,
    },
    "routes": {
        "login": {"max_elapsed_ms": 1200, "max_query_ms": 350, "max_queries": 35, "max_wide_selects": 1, "max_module_files": 24, "max_module_bytes": 1400000},
        "home": {"max_elapsed_ms": 1800, "max_query_ms": 650, "max_queries": 85, "max_wide_selects": 2, "max_module_files": 38, "max_module_bytes": 2300000},
        "appointments": {"max_elapsed_ms": 2200, "max_query_ms": 800, "max_queries": 110, "max_wide_selects": 2, "max_module_files": 40, "max_module_bytes": 2500000},
        "patients": {"max_elapsed_ms": 1800, "max_query_ms": 600, "max_queries": 80, "max_wide_selects": 2, "max_module_files": 36, "max_module_bytes": 2200000},
        "patient": {"max_elapsed_ms": 2400, "max_query_ms": 900, "max_queries": 120, "max_wide_selects": 3, "max_module_files": 42, "max_module_bytes": 2700000},
        "financial": {"max_elapsed_ms": 2500, "max_query_ms": 950, "max_queries": 125, "max_wide_selects": 3, "max_module_files": 42, "max_module_bytes": 2700000},
    },
}
write("app/performance.budgets.json", json.dumps(budgets, ensure_ascii=False, indent=4) + "\n")

write("docs/performance/performance-budgets.md", """# Orçamentos de desempenho

## Objetivo

A Fase 1 da evolução de eficiência institui limites versionados para custo de requisição sem alterar o comportamento operacional. Os limites são guardrails de engenharia e devem ser apertados apenas com base em observações reais de produção.

## Métricas

Cada rota possui limites para tempo total, tempo de banco, número de consultas, consultas amplas, módulos carregados e bytes de módulos. A ausência de configuração específica utiliza os limites padrão.

## Regras de conformidade

- a redução de orçamento não pode enfraquecer autorização, isolamento, auditoria ou integridade transacional;
- o contrato não recebe dados clínicos, CPF, conteúdo livre ou parâmetros SQL;
- alterações no contrato exigem revisão, caracterização e registro de versão;
- uma regressão deve ser corrigida na origem, não ocultada com aumento automático dos limites;
- limites de produção devem usar percentis e volume mínimo antes de bloquear publicação.

## Operação

`app/performance.budgets.json` é a fonte versionada. `Prontoo\\Core\\Performance\\PerformanceBudget` resolve e avalia os limites. O contrato é caracterizado em `tools/architecture-check.php`.
""")

replace_once(
    "app/Core/Architecture/LayerMap.php",
    "        'app/Core/Architecture/',\n        'app/Core/Install/InstallAccess.php',",
    "        'app/Core/Architecture/',\n        'app/Core/Performance/',\n        'app/Core/Install/InstallAccess.php',",
)
replace_once(
    "app/Support/ModuleLoader.php",
    "        'Support/ServerJsonCache.php',\n        'Support/Telemetry.php',",
    "        'Support/ServerJsonCache.php',\n        'Core/Performance/PerformanceBudget.php',\n        'Support/Telemetry.php',",
)

architecture_check = ROOT / "tools/architecture-check.php"
source = architecture_check.read_text(encoding="utf-8")
marker = "$result = [\n"
if source.count(marker) != 1:
    raise SystemExit("Marcador final do architecture-check.php divergente")
block = r'''require_once $root . '/app/Core/Performance/PerformanceBudget.php';

$serverPhaseOneFailures = [];
$performanceContract = json_decode(
    (string) file_get_contents($root . '/app/performance.budgets.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
if (!is_array($performanceContract) || ($performanceContract['schema'] ?? '') !== 'prontoo-performance-budgets-v1') {
    $serverPhaseOneFailures[] = 'performance_budget_schema';
} else {
    $underBudget = \Prontoo\Core\Performance\PerformanceBudget::evaluate(
        $performanceContract,
        'patients',
        [
            'elapsed_ms' => 400,
            'query_ms' => 120,
            'queries' => 20,
            'wide_selects' => 0,
            'module_files' => 20,
            'module_bytes' => 900000,
        ],
    );
    $overBudget = \Prontoo\Core\Performance\PerformanceBudget::evaluate(
        $performanceContract,
        'patients',
        [
            'elapsed_ms' => 5000,
            'query_ms' => 2000,
            'queries' => 300,
            'wide_selects' => 8,
            'module_files' => 90,
            'module_bytes' => 5000000,
        ],
    );
    $fallbackBudget = \Prontoo\Core\Performance\PerformanceBudget::resolve(
        $performanceContract,
        'unknown_route',
    );
    $normalizedDefault = \Prontoo\Core\Performance\PerformanceBudget::normalize(
        (array) ($performanceContract['defaults'] ?? []),
    );
    if (empty($underBudget['ok'])) {
        $serverPhaseOneFailures[] = 'performance_budget_under_limit';
    }
    if (!empty($overBudget['ok']) || count((array) ($overBudget['breaches'] ?? [])) !== 6) {
        $serverPhaseOneFailures[] = 'performance_budget_breach_detection';
    }
    if ($fallbackBudget !== $normalizedDefault) {
        $serverPhaseOneFailures[] = 'performance_budget_fallback';
    }
}
$serverPhaseOneCharacterization = [
    'ok' => $serverPhaseOneFailures === [],
    'failed' => $serverPhaseOneFailures,
];
'''
source = source.replace(marker, block + marker, 1)
source = source.replace(
    "        !empty($phaseFiveCharacterization['ok']),",
    "        !empty($phaseFiveCharacterization['ok']) &&\n        !empty($serverPhaseOneCharacterization['ok']),",
    1,
)
source = source.replace(
    "    'phase_five_characterization' => $phaseFiveCharacterization,",
    "    'phase_five_characterization' => $phaseFiveCharacterization,\n    'server_phase_one_characterization' => $serverPhaseOneCharacterization,",
    1,
)
architecture_check.write_text(source, encoding="utf-8")

changelog = ROOT / "CHANGELOG.md"
text = changelog.read_text(encoding="utf-8")
entry = """\n## 1.7.30.1 — Evolução do servidor, Fase 1: orçamentos de desempenho

- cria contrato versionado de custo por rota;
- adiciona avaliação determinística de tempo, consultas e módulos carregados;
- integra a caracterização ao contrato arquitetural existente;
- documenta regras de ajuste sem enfraquecer segurança ou integridade;
- não altera banco, schema, interface ou comportamento operacional.\n"""
if entry.strip() not in text:
    if not text.startswith("# Histórico de versões"):
        raise SystemExit("Cabeçalho do CHANGELOG.md divergente")
    changelog.write_text(text.replace("# Histórico de versões\n", "# Histórico de versões\n" + entry, 1), encoding="utf-8")

change_txt = ROOT / "ChangeLog.txt"
text = change_txt.read_text(encoding="utf-8")
entry = """Prontoo 1.7.30.1 — Evolução do servidor, Fase 1: orçamentos de desempenho

- Institui orçamentos versionados por rota e caracterização no contrato arquitetural.
- Mantém banco, schema, interface, permissões e comportamento operacional.
"""
if not text.startswith("Prontoo 1.7.30.1"):
    change_txt.write_text(entry + text, encoding="utf-8")

docs_index = ROOT / "docs/index.md"
text = docs_index.read_text(encoding="utf-8")
line = "- [Orçamentos de desempenho](performance/performance-budgets.md)\n"
if line not in text:
    docs_index.write_text(text.rstrip() + "\n" + line, encoding="utf-8")

for path, constant in [("app/prontoo.php", "PRONTOO_VERSION_FALLBACK"), ("br/index.php", "BR_LANDING_VERSION_FALLBACK")]:
    target = ROOT / path
    source = target.read_text(encoding="utf-8")
    pattern = rf"(const\s+{constant}\s*=\s*['\"])[^'\"]+(['\"]\s*;)"
    source, count = re.subn(pattern, rf"\g<1>{VERSION}\g<2>", source, count=1)
    if count != 1:
        raise SystemExit(f"Fallback não localizado: {path}")
    target.write_text(source, encoding="utf-8")

update_json("version.json", {
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "package_type": "incremental_refactor",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": False,
    "visual_changes": False,
    "functional_equivalence_policy": "preserves_1_7_29_6_runtime_behavior",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "notes": "Institui orçamentos de desempenho por rota e contrato de regressão no CI.",
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "performance_budget_governance",
    "deployment_sync_id": "github-server-phase1-performance-budgets-1-7-30-1",
    "deployment_sync_requested_at": NOW,
})
update_json("app/architecture.manifest.json", {
    "version": VERSION,
    "native_files_min": 30,
    "transitional_files_max": 80,
    "generated_at": NOW,
    "updated_at": NOW,
    "baseline_source": PREVIOUS,
    "server_evolution_phase_one_policy": "route_performance_budgets_are_versioned_validated_and_regression_checked",
    "server_evolution_phase_one_components": ["app/Core/Performance/PerformanceBudget.php", "app/performance.budgets.json"],
    "server_evolution_phase_one_check": "tools/architecture-check.php",
})
update_json("app/update.manifest.json", {
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "package_type": "incremental_refactor",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": False,
    "visual_changes": False,
    "functional_equivalence_policy": "preserves_1_7_29_6_runtime_behavior",
    "previous_version": PREVIOUS,
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "performance_budget_governance",
    "notes": "Institui orçamentos de desempenho por rota e contrato de regressão no CI.",
})
