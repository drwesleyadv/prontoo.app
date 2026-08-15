from pathlib import Path
import base64
import re
import zlib

root = Path('.')

def replace_once(path, pattern, replacement, flags=0):
    p = root / path
    text = p.read_text()
    updated, count = re.subn(pattern, replacement, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f"replace failed {path} count={count}")
    p.write_text(updated)

def inflate(encoded):
    return zlib.decompress(base64.b64decode(encoded)).decode()

(root / 'app/Infrastructure/Health').mkdir(parents=True, exist_ok=True)
(root / 'app/Runtime/Health').mkdir(parents=True, exist_ok=True)
def inflate_chunks(prefix, count):
    encoded = ''.join((root / f'tools/.health-evidence/{prefix}.{i}.part').read_text().strip() for i in range(1, count + 1))
    return inflate(encoded)

(root / 'app/Infrastructure/Health').mkdir(parents=True, exist_ok=True)
(root / 'app/Runtime/Health').mkdir(parents=True, exist_ok=True)
(root / 'app/Infrastructure/Health/HealthEvidenceStore.php').write_text(inflate_chunks('store', 1))
(root / 'app/Runtime/Health/HealthEvidenceRegistry.php').write_text(inflate_chunks('registry', 4))
(root / 'tools/health-evidence-contract-check').write_text(inflate_chunks('contract', 1))

replace_once(
    'app/Runtime/AdminPages/AdminPagesRuntimeOperations01.php',
    r'    public static function platform_backend_selftest\(array \$preloaded = \[\]\): array.*?    public static function platform_login_loaded_audit\(',
    '    public static function platform_backend_selftest(array $preloaded = []): array\n    {\n        $snapshot = \\Prontoo\\Runtime\\Health\\HealthEvidenceRegistry::snapshot($preloaded);\n        return (array) ($snapshot["checks"] ?? []);\n    }\n\n    public static function platform_health_snapshot(array $preloaded = []): array\n    {\n        return \\Prontoo\\Runtime\\Health\\HealthEvidenceRegistry::snapshot($preloaded);\n    }\n\n    public static function platform_login_loaded_audit(',
    flags=re.S,
)

replace_once(
    'app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php',
    r'        if \(empty\(\$checks\["database"\]\)\) \{.*?        if \(\$openErrors > 0\) \{',
    '        $scopeLogicOk = !empty(($checks["scope_guard_logic"] ?? [])["ok"]);\n        $scopeContextOk = !empty(($checks["scope_guard_context"] ?? [])["ok"]);\n        foreach (\\Prontoo\\Runtime\\Health\\HealthEvidenceRegistry::structuralActions($health) as $healthAction) {\n            $actions[] = $healthAction;\n        }\n        if ($openErrors > 0) {',
    flags=re.S,
)

replace_once(
    'app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php',
    r'            \$targetRoute = match \(\(string\) \(\$action\["time"\] \?\? ""\)\) \{(.*?)            \};',
    '            $targetRoute = mb_trim((string) ($action["route"] ?? ""));\n            if ($targetRoute === "") {\n                $targetRoute = match ((string) ($action["time"] ?? "")) {\\1            };\n            }',
    flags=re.S,
)

p = root / 'app/Presentation/AdminPages/AdminPagesPresentationOperations03.php'
text = p.read_text()
old = '        $stateCopy = match ($state) {\n            "Crítico" => "Existe uma condição estrutural que exige intervenção técnica.",\n            "Atenção" => "Há decisões ou exceções que justificam sua revisão.",\n            default => "Nada exige intervenção neste momento.",\n        };'
new = '        $hasUnknownEvidence = false;\n        foreach ((array) ($health["dimensions"] ?? []) as $dimension) {\n            if ((string) (($dimension["state"] ?? "")) === "unknown") {\n                $hasUnknownEvidence = true;\n                break;\n            }\n        }\n        $stateCopy = match ($state) {\n            "Crítico" => "Existe uma condição estrutural que exige intervenção técnica.",\n            "Atenção" => $hasUnknownEvidence\n                ? "Uma ou mais evidências de saúde precisam ser renovadas antes de considerar a plataforma normal."\n                : "Há decisões ou exceções que justificam sua revisão.",\n            default => "Nada exige intervenção neste momento.",\n        };'
if old not in text:
    raise SystemExit('presentation state copy anchor missing')
text = text.replace(old, new, 1)
old = '        $updatedAt = mb_trim((string) ($health["updated_at"] ?? ""));'
new = '        $updatedAt = mb_trim((string) ($health["updated_at"] ?? ""));\n        $freshnessLabel = mb_trim((string) ($health["freshness_label"] ?? ""));'
if old not in text:
    raise SystemExit('presentation updatedAt anchor missing')
text = text.replace(old, new, 1)
old = '                ($updatedAt !== "" ? \'<small>Atualizado em \' . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e($updatedAt) . \'</small>\' : "") .'
new = '                ($freshnessLabel !== ""\n                    ? \'<small>\' . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e(ucfirst($freshnessLabel)) . \'</small>\'\n                    : ($updatedAt !== "" ? \'<small>Atualizado em \' . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e($updatedAt) . \'</small>\' : "")) .'
if old not in text:
    raise SystemExit('presentation timestamp anchor missing')
text = text.replace(old, new, 1)
p.write_text(text)

p = root / 'cron/maestro.php'
text = p.read_text()
old = '    $recordSnapshot = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations03::telemetry_database_record_snapshot_capture();'
new = '    $healthCanary = \\Prontoo\\Runtime\\Health\\HealthEvidenceRegistry::runActiveCanary();\n    $recordSnapshot = \\Prontoo\\Infrastructure\\SupportTelemetry\\SupportTelemetryInfrastructureOperations03::telemetry_database_record_snapshot_capture();'
if old not in text:
    raise SystemExit('cron canary anchor missing')
text = text.replace(old, new, 1)
old = '    $ok = (bool) ($rules["success"] ?? false) &&\n        (bool) ($deferredWork["success"] ?? false) &&\n        (bool) ($piResult["ok"] ?? false) &&\n        (bool) ($maintenance["ok"] ?? false);'
new = '    $ok = (bool) ($rules["success"] ?? false) &&\n        (bool) ($deferredWork["success"] ?? false) &&\n        (bool) ($piResult["ok"] ?? false) &&\n        (bool) ($maintenance["ok"] ?? false) &&\n        (bool) ($healthCanary["ok"] ?? false);'
if old not in text:
    raise SystemExit('cron ok anchor missing')
text = text.replace(old, new, 1)
old = '        "maintenance" => $maintenance,\n    ];\n    prontoo_cron_cycle_state_write($cycle);'
new = '        "maintenance" => $maintenance,\n        "health_canary" => $healthCanary,\n    ];\n    prontoo_cron_cycle_state_write($cycle);\n    \\Prontoo\\Runtime\\Health\\HealthEvidenceRegistry::recordScheduledCycle($cycle, $healthCanary);'
if old not in text:
    raise SystemExit('cron cycle anchor missing')
text = text.replace(old, new, 1)
old = '            "maintenance" => $maintenance,\n        ] + $rules,'
new = '            "maintenance" => $maintenance,\n            "health_canary" => $healthCanary,\n        ] + $rules,'
if old not in text:
    raise SystemExit('cron output anchor missing')
text = text.replace(old, new, 1)
old = '        prontoo_cron_cycle_state_write($payload + [\n            "version" => PRONTOO_VERSION,\n            "finished_at_utc" => gmdate("c"),\n        ]);'
new = '        $failedCycle = $payload + [\n            "status" => "failed",\n            "version" => PRONTOO_VERSION,\n            "finished_at_utc" => gmdate("c"),\n        ];\n        prontoo_cron_cycle_state_write($failedCycle);\n        \\Prontoo\\Runtime\\Health\\HealthEvidenceRegistry::recordScheduledCycle($failedCycle, [\n            "ok" => false,\n            "checked_at" => gmdate("c"),\n            "duration_ms" => 0,\n            "checks" => ["preflight" => false],\n        ]);'
if old not in text:
    raise SystemExit('cron preflight failure anchor missing')
text = text.replace(old, new, 1)
p.write_text(text)

p = root / 'tools/quality-gate'
text = p.read_text()
anchor = "$run('global-audit-contract', 'tools/global-audit-contract-check');\n"
insert = anchor + "$run('health-evidence-contract', 'tools/health-evidence-contract-check');\n"
if anchor not in text:
    raise SystemExit('quality gate anchor missing')
text = text.replace(anchor, insert, 1)
p.write_text(text)

p = root / 'docs/operations/developer-control-center.md'
text = p.read_text().rstrip() + '\n\n## Evidência de saúde\n\nA saúde da plataforma não é inferida por um único semáforo. O estado global é derivado de cinco dimensões independentes: Disponibilidade, Integridade, Isolamento e segurança, Desempenho e Continuidade.\n\nCada sinal assume exatamente um dos estados `ok`, `attention`, `fail` ou `unknown` e carrega horário de observação e validade. Evidência expirada ou uma verificação que não pôde ser concluída vira `unknown`; ausência de medição nunca é convertida em estado saudável.\n\nA telemetria de requisições funciona como sensor passivo. Degradações transitórias de falha ou latência só promovem alerta quando persistem em observações consecutivas, e a recuperação também precisa se estabilizar. O Maestro funciona como sensor ativo: cada ciclo executa um canário de banco, storage, invariantes de mutação e isolamento e renova a evidência de continuidade.\n\nO histórico persistente registra somente transições de estado de plataforma, dimensão ou sinal. Leituras repetidas em `ok` não geram novos eventos. A Visão geral continua minimalista e exibe apenas o estado, a idade da evidência e as exceções que exigem intervenção.\n'
p.write_text(text + '\n')

for temp in [root / '.github/workflows/health-evidence-runner.yml', root / 'tools/health-evidence-refactor.py', root / 'tools/.health-evidence']:
    if temp.is_dir():
        import shutil
        shutil.rmtree(temp)
    elif temp.exists():
        temp.unlink()
