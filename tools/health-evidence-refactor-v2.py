from pathlib import Path
import re
import shutil

root = Path('.')

def replace_once(path, pattern, replacement, flags=0):
    p = root / path
    text = p.read_text()
    compiled = re.compile(pattern, flags)
    updated, count = compiled.subn(lambda m: replacement, text, count=1)
    if count != 1:
        raise SystemExit(f'replace failed {path} count={count}')
    p.write_text(updated)

runtime_health = r'''    public static function platform_backend_selftest(array $preloaded = []): array
    {
        $snapshot = self::platform_health_snapshot($preloaded);
        return (array) ($snapshot['checks'] ?? []);
    }

    public static function platform_health_snapshot(array $preloaded = []): array
    {
        return \Prontoo\Infrastructure\AdminPages\AdminPagesInfrastructureOperations01::platform_health_exchange(
            static function (array $context) use ($preloaded): array {
                $raw = [
                    'observed_at' => gmdate('c'),
                    'storage' => (array) ($context['storage'] ?? []),
                    'performance' => (array) ($context['performance'] ?? []),
                    'maestro' => (array) ($context['maestro'] ?? []),
                    'database' => null,
                    'open_errors' => self::platform_health_operational_count(
                        $preloaded,
                        'open_errors',
                        'platform_selftest_open_errors',
                        'read.admin_pages.01.platform_backend_selftest.01',
                    ),
                    'login_locks' => self::platform_health_operational_count(
                        $preloaded,
                        'login_locks',
                        'platform_selftest_login_locks',
                        'read.admin_pages.01.platform_backend_selftest.02',
                    ),
                    'scope_alerts_24h' => self::platform_health_operational_count(
                        $preloaded,
                        'scope_alerts_24h',
                        'platform_selftest_scope_actionable_24h_v2_' . \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(),
                        'read.admin_pages.01.platform_backend_selftest.03',
                    ),
                ];
                try {
                    $raw['database'] = (string) \Prontoo\Runtime\Operational\OperationalComposition::administration()
                        ->scalar('operational.admin_pages.01.platform_backend_selftest.01', [], []) === '1';
                } catch (Throwable $error) {
                    $raw['database'] = null;
                }
                try {
                    $raw['scope_logic'] = class_exists(\Prontoo\Core\Database\SqlScopeGuard::class)
                        ? \Prontoo\Core\Database\SqlScopeGuard::logicSelfTest()
                        : ['ok' => false, 'unknown' => true, 'failed' => ['class_missing']];
                } catch (Throwable $error) {
                    $raw['scope_logic'] = ['ok' => false, 'unknown' => true, 'failed' => ['unavailable']];
                }
                try {
                    $raw['scope_context'] = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::scope_guard_context_selftest();
                } catch (Throwable $error) {
                    $raw['scope_context'] = ['ok' => false, 'unknown' => true, 'failed' => ['unavailable']];
                }
                try {
                    $raw['audit_chain'] = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_chain_integrity_status(240);
                } catch (Throwable $error) {
                    $raw['audit_chain'] = ['ok' => false, 'unknown' => true, 'checked' => 0];
                }
                $raw['integrity_alerts'] = null;
                if ((int) (($raw['audit_chain']['checked'] ?? 0)) > 0) {
                    try {
                        $raw['integrity_alerts'] = 0;
                        foreach (\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light(['scope' => 'model_excluded'], [], 50) as $row) {
                            if (!\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::verify_audit_row($row)) {
                                $raw['integrity_alerts']++;
                            }
                        }
                    } catch (Throwable $error) {
                        $raw['integrity_alerts'] = null;
                    }
                }
                try {
                    $raw['mutation_invariant'] = \Prontoo\Core\Invariant\InvariantKernel::logicSelfTest();
                } catch (Throwable $error) {
                    $raw['mutation_invariant'] = ['ok' => false, 'unknown' => true];
                }
                if (function_exists('prontoo_version_contract_status')) {
                    try {
                        $raw['version_contract'] = prontoo_version_contract_status();
                    } catch (Throwable $error) {
                        $raw['version_contract'] = ['ok' => false, 'unknown' => true, 'issues' => ['unavailable']];
                    }
                } else {
                    $raw['version_contract'] = ['ok' => false, 'unknown' => true, 'issues' => ['function_missing']];
                }
                $raw['version'] = (string) ($raw['version_contract']['version'] ?? PRONTOO_VERSION);
                return \Prontoo\Domain\Health\HealthEvidenceBuilder::build(
                    $raw,
                    (array) ($context['previous'] ?? []),
                );
            },
        );
    }

    private static function platform_health_operational_count(
        array $preloaded,
        string $key,
        string $cacheKey,
        string $queryId,
    ): ?int {
        if (array_key_exists($key, $preloaded)) {
            return max(0, (int) $preloaded[$key]);
        }
        try {
            return max(0, (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val(
                $cacheKey,
                45,
                $queryId,
                [],
                [],
            ));
        } catch (Throwable $error) {
            return null;
        }
    }

'''

replace_once(
    'app/Runtime/AdminPages/AdminPagesRuntimeOperations01.php',
    r'    public static function platform_backend_selftest\(array \$preloaded = \[\]\): array.*?(?=    public static function platform_login_loaded_audit\()',
    runtime_health,
    re.S,
)

replace_once(
    'app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php',
    r'        if \(empty\(\$checks\["database"\]\)\) \{.*?(?=        if \(\$openErrors > 0\) \{)',
    '''        $scopeLogicOk = !empty(($checks["scope_guard_logic"] ?? [])["ok"]);\n        $scopeContextOk = !empty(($checks["scope_guard_context"] ?? [])["ok"]);\n        foreach (\\Prontoo\\Domain\\Health\\HealthEvidencePolicy::structuralActions($health) as $healthAction) {\n            $actions[] = $healthAction;\n        }\n''',
    re.S,
)

p = root / 'app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php'
text = p.read_text()
old = '''            $targetRoute = match ((string) ($action["time"] ?? "")) {'''
if old in text:
    text = text.replace(old, '''            $targetRoute = mb_trim((string) ($action["route"] ?? ""));\n            if ($targetRoute === "") {\n                $targetRoute = match ((string) ($action["time"] ?? "")) {''', 1)
    marker = '''                default => "",\n            };'''
    if marker not in text:
        raise SystemExit('target route closing anchor missing')
    text = text.replace(marker, '''                default => "",\n                };\n            }''', 1)
p.write_text(text)

p = root / 'app/Presentation/AdminPages/AdminPagesPresentationOperations03.php'
text = p.read_text()
old = '''        $stateCopy = match ($state) {\n            "Crítico" => "Existe uma condição estrutural que exige intervenção técnica.",\n            "Atenção" => "Há decisões ou exceções que justificam sua revisão.",\n            default => "Nada exige intervenção neste momento.",\n        };'''
new = '''        $hasUnknownEvidence = false;\n        foreach ((array) ($health["dimensions"] ?? []) as $dimension) {\n            if ((string) ($dimension["state"] ?? "") === "unknown") {\n                $hasUnknownEvidence = true;\n                break;\n            }\n        }\n        $stateCopy = match ($state) {\n            "Crítico" => "Existe uma condição estrutural que exige intervenção técnica.",\n            "Atenção" => $hasUnknownEvidence\n                ? "Uma ou mais evidências de saúde precisam ser renovadas antes de considerar a plataforma normal."\n                : "Há decisões ou exceções que justificam sua revisão.",\n            default => "Nada exige intervenção neste momento.",\n        };'''
if old not in text:
    raise SystemExit('presentation state copy anchor missing')
text = text.replace(old, new, 1)
old = '''        $updatedAt = mb_trim((string) ($health["updated_at"] ?? ""));'''
new = '''        $updatedAt = mb_trim((string) ($health["updated_at"] ?? ""));\n        $freshnessLabel = mb_trim((string) ($health["freshness_label"] ?? ""));'''
if old not in text:
    raise SystemExit('presentation updatedAt anchor missing')
text = text.replace(old, new, 1)
old = '''                ($updatedAt !== "" ? '<small>Atualizado em ' . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e($updatedAt) . '</small>' : "") .'''
new = '''                ($freshnessLabel !== ""\n                    ? '<small>' . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e(ucfirst($freshnessLabel)) . '</small>'\n                    : ($updatedAt !== "" ? '<small>Atualizado em ' . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e($updatedAt) . '</small>' : "")) .'''
if old not in text:
    raise SystemExit('presentation timestamp anchor missing')
text = text.replace(old, new, 1)
p.write_text(text)

p = root / 'cron/maestro.php'
text = p.read_text()
old = '''        $payload = [\n            "ok" => false,\n            "stage" => "preflight",\n            "preflight" => $preflight,\n        ];'''
new = '''        $healthCanary = \\Prontoo\\Infrastructure\\Health\\HealthCanary::run();\n        $payload = [\n            "ok" => false,\n            "status" => "failed",\n            "stage" => "preflight",\n            "preflight" => $preflight,\n            "health_canary" => $healthCanary,\n        ];'''
if old not in text:
    raise SystemExit('cron preflight anchor missing')
text = text.replace(old, new, 1)
old = '''    $recordSnapshot = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations03::telemetry_database_record_snapshot_capture();'''
new = '''    $healthCanary = \\Prontoo\\Infrastructure\\Health\\HealthCanary::run();\n    $recordSnapshot = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations03::telemetry_database_record_snapshot_capture();'''
if old not in text:
    raise SystemExit('cron canary anchor missing')
text = text.replace(old, new, 1)
old = '''        (bool) ($piResult["ok"] ?? false) &&\n        (bool) ($maintenance["ok"] ?? false);'''
new = '''        (bool) ($piResult["ok"] ?? false) &&\n        (bool) ($maintenance["ok"] ?? false) &&\n        (bool) ($healthCanary["ok"] ?? false);'''
if old not in text:
    raise SystemExit('cron ok anchor missing')
text = text.replace(old, new, 1)
old = '''        "maintenance" => $maintenance,\n    ];'''
new = '''        "maintenance" => $maintenance,\n        "health_canary" => $healthCanary,\n    ];'''
if old not in text:
    raise SystemExit('cron cycle anchor missing')
text = text.replace(old, new, 1)
old = '''            "maintenance" => $maintenance,\n        ] + $rules,'''
new = '''            "maintenance" => $maintenance,\n            "health_canary" => $healthCanary,\n        ] + $rules,'''
if old not in text:
    raise SystemExit('cron output anchor missing')
text = text.replace(old, new, 1)
p.write_text(text)

p = root / 'tools/quality-gate'
text = p.read_text()
anchor = "$run('global-audit-contract', 'tools/global-audit-contract-check');\n"
if "health-evidence-contract" not in text:
    if anchor not in text:
        raise SystemExit('quality gate anchor missing')
    text = text.replace(anchor, anchor + "$run('health-evidence-contract', 'tools/health-evidence-contract-check');\n", 1)
p.write_text(text)

p = root / 'docs/operations/developer-control-center.md'
text = p.read_text()
if '## Evidência de saúde' not in text:
    text = text.rstrip() + '''\n\n## Evidência de saúde\n\nA saúde da plataforma não é inferida por um único semáforo. O estado global é derivado de cinco dimensões independentes: Disponibilidade, Integridade, Isolamento e segurança, Desempenho e Continuidade.\n\nCada sinal assume exatamente um dos estados `ok`, `attention`, `fail` ou `unknown` e carrega horário de observação e validade. Evidência expirada ou uma verificação que não pôde ser concluída vira `unknown`; ausência de medição nunca é convertida em estado saudável.\n\nA telemetria de requisições funciona como sensor passivo. Degradações transitórias de falha ou latência só promovem alerta quando persistem em observações consecutivas, e a recuperação também precisa se estabilizar. O Maestro funciona como sensor ativo: cada ciclo executa um canário de banco, storage, invariantes de mutação e isolamento e renova a evidência de continuidade.\n\nO histórico persistente registra somente transições de estado de plataforma, dimensão ou sinal. Leituras repetidas em `ok` não geram novos eventos. A Visão geral continua minimalista e exibe apenas o estado, a idade da evidência e as exceções que exigem intervenção.\n'''
p.write_text(text + ('\n' if not text.endswith('\n') else ''))

runtime_registry = root / 'app/Runtime/Health/HealthEvidenceRegistry.php'
if runtime_registry.exists():
    runtime_registry.unlink()
for temp in [
    root / '.github/workflows/health-evidence-runner.yml',
    root / 'tools/health-evidence-refactor.py',
    root / 'tools/health-evidence-refactor-v2.py',
    root / 'tools/.health-evidence',
]:
    if temp.is_dir():
        shutil.rmtree(temp)
    elif temp.exists():
        temp.unlink()
