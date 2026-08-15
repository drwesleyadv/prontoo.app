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
(root / 'app/Infrastructure/Health/HealthEvidenceStore.php').write_text(inflate('eNq1WFtv2zYUfvevYAGjkgDFS4J0K9ymadaoi9fUNmwPbeEaAiPRtlZZEkjKhbfmv+/worvsJF2rF0nkufGc71yklxfJOun4xAsxJSbjNPC4y3cJYecn1otOJ8IbwhLsETSmccTj+PMgWlIMhKnHU0o+XxMc8jVQpoyg2ZrGX/FtSOB9GUQ4RCCXMaSInG3gk8gjUx5T0vm3g+BKaLDFnCAvjhhH7y8/urPJ5XA6mA1GQ/f3TzNnis7Rs9Oz0+fPX7QwvHOccYlDEJ+eHYP2MukyjTwexBFyXcklTDctSaKMuNP06W0YeIhxzOGWc1GC/RuQw4CpjzCleFfiFVc3wXwNqhkJl/1+KGnHsGRaL3KaYInMJwFzl0FITMlgWSUR4qIEPBqh+aLgusufON3VyLsQtdgnPij+m8WRq15NGcRoZSGhyV0RLg7NScSZVmuDrJTY6NnJqY3+nIKfZ9eT0QcXHpzJZDQpGV2yCkyXRzcztRa6KEzoV61GHubeGpk5HlCXUBrT+onlohvGK9OYa3yhtcQKIhos0v0LZKCelnH0Cs70njCGV8S02o1tuvBwiL/SgBMdY3lK1GURTtg65jbSCwkFk+KUgbvnCwDCbRyHdRz4Ac1hAM/EA6TvGihQ+yAvgZy7yskE9z5MLHHIyANhAV4rw0K9mhUaSZefsLElQfHX0Jm+uRw7V/A0eDO6ctC3+sb05nJ6DRmqN8YTZzb7BLfBcJatVaFVVVWL3UOySNLxTQJkiryHjB689wQ+ABebXRL4dQbhdJkMSVpOBmCzc2/Z6Gb05p3rfLTQ+fm58nc9FuJ6nUZhEH2R3DU1+8NVDZkU4603sa9tOP717LjF5CevKRHlN7O0tWb8QJuU33GSkMifURyxQOSGqBoa+XaBmfa0E3XlJ1UBmaGPLQOtWXNXbQ71UlBK2z5SpbSW5Vr45z0NcZomSUz52ziNfCxENleqHKOEULnMjk/6fQa64UiuiLZpKDcY+nj3mF5OmcO2NyqUyKNfMmcfKUE9UT6MB2nmBVy+X31JiFQdPkx3o44qzbIYt5fprBUDgSq66OlT9OT15ku2ACn527Nj1SfVZpn6sSU6y/VccK0fgGwPh6FIEHO+F1XESyEFdmPhBW9Xfz+EKDmC2chgmsXNIAbB3sl5xFg0DvUT7NivX/m1zXfF8CEKgHRRifYeZDRLWa2V570dDINaywEu2zjw612dbEXDyDtTgVNnq8eqokBmkqohzkWcy+GhFUBtx6+2xEaWFSxLmKixqLWZJsy03XVlXWgVpDYbKEr7sX2/pWdBoFT6mVJPe7tq6cXinLa2rYfG12PXGd3Y6O3gxnEvx2NneAVWZB16XxNrppwSW+uv2fiVRqTa5kR/fWS50wi4H1dtnw0SGcVYeQAbe74JuphDJPOB38z0zY34lhG6Jb6LubFAFxdotYHWQ0zDM8q9soB2WQF4IqBysWLK3FiF8S0ODaglCbQIwNwGnkvqs7PMDeExojQbhmW32lilWRSz4aIF18rFFX4/2BAIA8ROCRHOE7APYPR9Jcq/3m5kgDzefNE4oLgKqUZzLs5PAToO7JplT5StnAPfon5w67CknP8evlaAHHIfC1bwgd7uO7X3OMcpnh/ktdy473GZYv5f/tIpAC6Zd8XfEFsaj7pLGm/gxuNGospkFtsykQ0Dffsm6Mpv+S6stxVGUQ6DKD04pOuM3RsEb0022BBBNBLVwXtqhjwqilZve9ISpkrNkCjALZ+GhvCG2pZ+aRIEvtpuC7YhXKC2pSdbxMdaePyAcOniqH3ywGmxVvazaVFW//b+z4J/RMeUfUs8V1pFeaAMIm5KakvGW/K9zLp327+tR0wCojWyzIqss8n+OPhjOJo47tD54N4MhvJzXK5P3w2gjb4fwye5XG8xWP/RkbKt7zBG8rssDDyipdjoSB23/meupH3/ABBsklAMJPkAoE0rzwTVKeCuc9f5DyhE1kI='))
(root / 'app/Runtime/Health/HealthEvidenceRegistry.php').write_text(inflate('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[...]'))
(root / 'tools/health-evidence-contract-check').write_text(inflate('eNq1VutO2zAU/t+n8BCS06m0gBCTYN1UQSU6IVo15cdUOss4p8QjtTPbLWSMp9mj7MV2nKYXrgWm9YeT+Pg71+8c9+PnNE5LEYiEGwisM1I45rIUbH2rvF8qySEJOkcdFjY6LfKuXidUJJKWyU2J4C92LmUGbKqVBSZ0BMHO5g7ivBCupQu8kttSad1o7UidRNIoPoKAscNWlzEUrhv4MZYGIpT2cxzlaVrrjpWTI6gdAU9cXDyaExmBEtCFC4muZlX0nVYWoJYaGo6CsXBj8wQ2dNrAfeDMWiMaSdXhF2CXXgthOwXDncRIN7f+Eb+7jBdGq9qIA/qti/3BfmmIXnIRk2CRH27J+lAmMEu+L807aZnfC6YJrhJao7hOz80O+t/wykgHQdg7bHa7FUL7cZ6VDSjSMiDcoJ2JJnxsQTnYI3NF+PQUaLaPi8reqa7/uMUao6PTqmAhcyKpizLxcHYBDrmBOpWzS46+rsyeKtbX7jXqX0cIb4L7sr0lgldRx1vSEzATCVf/0dju3Jgn2UsNPSBkriAGcWnnPYqyHMql8tCiUBWy1qf6kiK/uPNq0Qv/MeQy8c+xulT6StHBWrnyvBo64YmMmI8owVVe09UQvyvVWLqsyoWTExxIXHGTvQCKKcOGG3GkQ9VM07gSRa3j5zKRP6FnuLISw6WrIVMq8qQh8hI9jsh5nvuFpXBaV4tmdbkhD6xOtp5wsMD6sQQq6s0RT5jK+Y7HH++8vT2reGpj/URoMw4/p+BlIXvSPaMFi9LIa3owLembdIDQJgqRydE4geggEwl4RTht/SiVinFjeBYMeWIxgwXlKwQDWMzS1XPU+4MNqIlUQo/SBJymDybo0t3obafIETbiTsQBrWFOLSaKTRUJ1/92pgY3m5Xt7c3bs/6Zfd9fO7ve/jDQl8ULbtU/4eIdrVlstDnl3uJ2BMR78Oe3JsovKd7qBHMSc0P4OZgV4SwstU97FfLdYiRowf81KK53HBCk/ilPa3EFpjqRIst36T3HNmahbCDji+PYeA5sfvxl46bALdqHaZVkLJa+WbIlbwYV8iVsn7DTk2Z40Og0D/GtddA+bJJf9wXhcSM8aoYzQafb7PW+4qN10ivfyc9fpJAdTA=='))

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

for temp in [root / '.github/workflows/health-evidence-runner.yml', root / 'tools/health-evidence-refactor.py']:
    if temp.exists():
        temp.unlink()
