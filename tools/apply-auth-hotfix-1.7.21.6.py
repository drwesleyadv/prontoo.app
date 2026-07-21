#!/usr/bin/env python3
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path.cwd()
VERSION = "1.7.21.6"
PREVIOUS = "1.7.21.5"
BUILD = "1.7.21.6-authorization-scope-hotfix"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"Marcador {label} esperado uma vez; encontrado {count}")
    return text.replace(old, new, 1)


path = "app/Admin/AdminPages.php"
text = read(path)
old = '''    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $id = (int) ($_POST["id"] ?? 0);
        $note = trim((string) ($_POST["notes"] ?? ""));
        q("UPDATE pi_error_events SET resolved_at=NOW(), notes=? WHERE id=?", [
            $note,
            $id,
        ]);
        audit("erro_marcado_resolvido", "erro", $id);
        flash("Erro marcado como resolvido.");
        redirect("admin_errors");
    }
'''
new = '''    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        if ($act !== "resolve_incident") {
            throw new ProntooHttpError(400, "Ação de incidente inválida.");
        }
        $id = (int) ($_POST["id"] ?? 0);
        if ($id <= 0) {
            flash("Incidente não informado.", "bad");
            redirect("admin_errors");
        }
        $note = trim((string) ($_POST["notes"] ?? ""));
        $updated = q(
            "UPDATE pi_error_events SET resolved_at=NOW(), notes=? WHERE id=? AND resolved_at IS NULL",
            [$note, $id],
        )->rowCount();
        if ($updated < 1) {
            flash("Incidente não encontrado ou já resolvido.", "bad");
            redirect("admin_errors");
        }
        audit("erro_marcado_resolvido", "erro", $id);
        flash("Erro marcado como resolvido.");
        redirect("admin_errors");
    }
'''
text = replace_once(text, old, new, "handler de resolução de incidente")
old = '''                '</summary><form method="post" class="compact">' .
                csrf_field() .
                '<input type="hidden" name="id" value="' .
'''
new = '''                '</summary><form method="post" class="compact">' .
                csrf_field() .
                '<input type="hidden" name="act" value="resolve_incident">' .
                '<input type="hidden" name="id" value="' .
'''
text = replace_once(text, old, new, "token do formulário de incidente")
old = '''    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        q("DELETE FROM pi_login_locks WHERE id=?", [(int) $_POST["id"]]);
        audit("bloqueio_login_removido", "seguranca", (int) $_POST["id"]);
        flash("Bloqueio removido.");
        redirect("admin_security");
    }
'''
new = '''    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        if ($act !== "release_login_lock") {
            throw new ProntooHttpError(400, "Ação de segurança inválida.");
        }
        $id = (int) ($_POST["id"] ?? 0);
        if ($id <= 0) {
            flash("Bloqueio não informado.", "bad");
            redirect("admin_security");
        }
        $removed = q("DELETE FROM pi_login_locks WHERE id=?", [$id])->rowCount();
        if ($removed < 1) {
            flash("Bloqueio não encontrado ou já liberado.", "bad");
            redirect("admin_security");
        }
        audit("bloqueio_login_removido", "seguranca", $id);
        flash("Bloqueio removido.");
        redirect("admin_security");
    }
'''
text = replace_once(text, old, new, "handler de liberação de bloqueio")
old = '''            csrf_field() .
            '<input type="hidden" name="id" value="' .
            (int) $l["id"] .
'''
new = '''            csrf_field() .
            '<input type="hidden" name="act" value="release_login_lock">' .
            '<input type="hidden" name="id" value="' .
            (int) $l["id"] .
'''
text = replace_once(text, old, new, "token do formulário de segurança")
write(path, text)

path = "app/Application/Authorization/ActionCatalog.php"
text = read(path)
old = '''            'admin_alerts' => ['create', 'read'],
            'admin_maintenance' => ['save_settings', 'regenerate_footer_seq_alphabet', 'save_maintenance'],
            'admin_deleted' => ['restore_patient', 'restore_care'],
'''
new = '''            'admin_alerts' => ['create', 'read'],
            'admin_errors' => ['resolve_incident'],
            'admin_security' => ['release_login_lock'],
            'admin_maintenance' => ['save_settings', 'regenerate_footer_seq_alphabet', 'save_maintenance'],
            'admin_deleted' => ['restore_patient', 'restore_care'],
'''
text = replace_once(text, old, new, "contratos globais de incidente e segurança")
old = '''        $cases['global_notice_toggle_producer_declared'] = self::resolve('admin_global_notices', ['act' => 'toggle'])?->producers === ['Domain/Tasks/TasksNotices.php'];
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
'''
new = '''        $cases['global_notice_toggle_producer_declared'] = self::resolve('admin_global_notices', ['act' => 'toggle'])?->producers === ['Domain/Tasks/TasksNotices.php'];
        $incident = self::resolve('admin_errors', ['act' => 'resolve_incident']);
        $security = self::resolve('admin_security', ['act' => 'release_login_lock']);
        $cases['incident_resolution_exact_global_contract'] = $incident?->scope === 'global' &&
            $incident?->policy === 'global_admin' &&
            $incident?->required === ['admin:*'];
        $cases['login_lock_release_exact_global_contract'] = $security?->scope === 'global' &&
            $security?->policy === 'global_admin' &&
            $security?->required === ['admin:*'];
        $cases['all_global_contracts_are_developer_only'] = array_reduce(
            self::all(),
            static fn(bool $ok, ActionContract $contract): bool => $ok &&
                ($contract->scope !== 'global' ||
                    ($contract->policy === 'global_admin' && $contract->required === ['admin:*'])),
            true,
        );
        $cases['clinic_contracts_never_request_global_admin'] = array_reduce(
            self::all(),
            static fn(bool $ok, ActionContract $contract): bool => $ok &&
                ($contract->scope !== 'clinic' || !in_array('admin:*', $contract->required, true)),
            true,
        );
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
'''
text = replace_once(text, old, new, "autotestes do catálogo por escopo")
write(path, text)

path = "app/Infrastructure/Authorization/RuntimeCapabilityProvider.php"
text = read(path)
old = '''        $tasks = new ActionContract(
            'tasks',
            'done',
            'clinic',
            ['tasks:edit'],
            ['tasks:edit'],
            [],
            'matrix',
            'tasks:edit',
            'Domain/Tasks/TasksNotices.php',
        );
        $cases = [];
'''
new = '''        $tasks = new ActionContract(
            'tasks',
            'done',
            'clinic',
            ['tasks:edit'],
            ['tasks:edit'],
            [],
            'matrix',
            'tasks:edit',
            'Domain/Tasks/TasksNotices.php',
        );
        $cashOpen = new ActionContract(
            'financial',
            'cash_open',
            'clinic',
            ['financial:edit'],
            ['financial:cashier'],
            [],
            'financial_operational',
            'financial:edit',
            'Domain/Financial/Financial.php',
        );
        $adminReceive = new ActionContract(
            'financial',
            'admin_receive',
            'clinic',
            ['financial:edit'],
            ['financial:edit'],
            [],
            'matrix',
            'financial:edit',
            'Domain/Financial/Financial.php',
        );
        $maestro = new ActionContract(
            'maestro',
            'save_rule',
            'clinic',
            ['maestro:add'],
            ['maestro:add'],
            [],
            'manager_only',
            'maestro:add',
            'Domain/Maestro/Maestro.php',
        );
        $cases = [];
'''
text = replace_once(text, old, new, "contratos do autoteste de cargos")
old = '''        $cases['secondary_role_capability_allowed'] = $provider->grants($tasks, 'tasks:edit', [
            'scope' => 'clinic',
            'clinic_id' => 18,
            'user' => ['id' => 4],
            'role' => 'medico',
            'effective_roles' => ['medico', 'assistente'],
            'live_active' => true,
            'live_roles' => ['medico', 'assistente'],
        ]);
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
'''
new = '''        $cases['secondary_role_capability_allowed'] = $provider->grants($tasks, 'tasks:edit', [
            'scope' => 'clinic',
            'clinic_id' => 18,
            'user' => ['id' => 4],
            'role' => 'medico',
            'effective_roles' => ['medico', 'assistente'],
            'live_active' => true,
            'live_roles' => ['medico', 'assistente'],
        ]);
        $cases['clinic_manager_never_grants_global_admin'] = !$provider->grants($global, 'admin:*', [
            'scope' => 'clinic',
            'clinic_id' => 19,
            'user' => ['id' => 5, 'is_global_admin' => 0],
            'live_active' => true,
            'live_global_admin' => false,
            'live_roles' => ['gerente'],
        ]);
        $cases['global_admin_never_inherits_clinic_capability'] = !$provider->grants($patient, 'patients:edit', [
            'scope' => 'global',
            'user' => ['id' => 6, 'is_global_admin' => 1],
            'live_active' => true,
            'live_global_admin' => true,
            'live_roles' => [],
        ]);
        $cases['reception_cashier_exact_action_allowed'] = $provider->grants($cashOpen, 'financial:edit', [
            'scope' => 'clinic',
            'clinic_id' => 20,
            'user' => ['id' => 7],
            'live_active' => true,
            'live_roles' => ['recepcionista'],
        ]);
        $cases['reception_admin_financial_action_denied'] = !$provider->grants($adminReceive, 'financial:edit', [
            'scope' => 'clinic',
            'clinic_id' => 20,
            'user' => ['id' => 7],
            'live_active' => true,
            'live_roles' => ['recepcionista'],
        ]);
        $cases['manager_only_policy_allows_manager'] = $provider->grants($maestro, 'maestro:add', [
            'scope' => 'clinic',
            'clinic_id' => 21,
            'user' => ['id' => 8],
            'live_active' => true,
            'live_roles' => ['gerente'],
        ]);
        $cases['manager_only_policy_denies_professional'] = !$provider->grants($maestro, 'maestro:add', [
            'scope' => 'clinic',
            'clinic_id' => 21,
            'user' => ['id' => 9],
            'live_active' => true,
            'live_roles' => ['medico'],
        ]);
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
'''
text = replace_once(text, old, new, "casos de escopo por cargo")
write(path, text)

auth_tool = r'''<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Invariant/Canonical.php';
require __DIR__ . '/../app/Core/Invariant/Decision.php';
require __DIR__ . '/../app/Domain/Authorization/ActionContract.php';
require __DIR__ . '/../app/Application/Authorization/CapabilityProvider.php';
require __DIR__ . '/../app/Application/Authorization/ActionCatalog.php';
require __DIR__ . '/../app/Application/Authorization/AuthorizationService.php';
require __DIR__ . '/../app/Infrastructure/Authorization/RuntimeCapabilityProvider.php';

use Prontoo\Application\Authorization\ActionCatalog;
use Prontoo\Application\Authorization\AuthorizationService;
use Prontoo\Infrastructure\Authorization\RuntimeCapabilityProvider;

$checks = [];
$record = static function (string $name, bool $ok, array $evidence = []) use (&$checks): void {
    $checks[$name] = ['ok' => $ok, 'evidence' => $evidence];
};

$catalog = ActionCatalog::logicSelfTest();
$provider = RuntimeCapabilityProvider::logicSelfTest();
$record('catalog_logic', !empty($catalog['ok']), $catalog);
$record('runtime_provider_logic', !empty($provider['ok']), $provider);

$expectedGlobal = [
    'admin_painel' => ['goal', 'confirm_subscription_payment', 'reject_subscription_payment'],
    'admin_clinics' => ['activate_subscription', 'billing', 'deactivate_subscription', 'default_billing', 'toggle'],
    'admin_alerts' => ['create', 'read'],
    'admin_errors' => ['resolve_incident'],
    'admin_security' => ['release_login_lock'],
    'admin_maintenance' => ['save_settings', 'regenerate_footer_seq_alphabet', 'save_maintenance'],
    'admin_deleted' => ['restore_patient', 'restore_care'],
    'admin_global_notices' => ['create', 'toggle'],
];
foreach ($expectedGlobal as $route => $actions) {
    foreach ($actions as $action) {
        $contract = ActionCatalog::resolve($route, ['act' => $action]);
        $record(
            'global_contract:' . $route . ':' . $action,
            $contract !== null && $contract->scope === 'global' &&
                $contract->policy === 'global_admin' &&
                $contract->required === ['admin:*'],
            $contract === null ? [] : [
                'scope' => $contract->scope,
                'policy' => $contract->policy,
                'required' => $contract->required,
            ],
        );
    }
}

$adminSource = (string) file_get_contents(__DIR__ . '/../app/Admin/AdminPages.php');
$record('incident_form_has_exact_action', str_contains($adminSource, 'name="act" value="resolve_incident"'));
$record('security_form_has_exact_action', str_contains($adminSource, 'name="act" value="release_login_lock"'));
$record('incident_handler_rejects_unknown_action', str_contains($adminSource, '$act !== "resolve_incident"'));
$record('security_handler_rejects_unknown_action', str_contains($adminSource, '$act !== "release_login_lock"'));

$runtime = new RuntimeCapabilityProvider(
    static fn(array $context): array => [
        'active' => !empty($context['active']),
        'global_admin' => !empty($context['global_admin']),
        'roles' => (array) ($context['roles'] ?? []),
    ],
    static fn(string $role, int $clinicId): array => match ($role) {
        'medico' => ['patients' => ['edit' => true], 'appointments' => ['edit' => true]],
        'assistente' => ['tasks' => ['edit' => true]],
        'recepcionista' => ['appointments' => ['edit' => true]],
        default => [],
    },
);
$service = new AuthorizationService($runtime);
$globalAdmin = [
    'scope' => 'global',
    'user' => ['id' => 1, 'is_global_admin' => 1],
    'active' => true,
    'global_admin' => true,
    'roles' => [],
];
$clinicContexts = [
    'gerente' => ['scope' => 'clinic', 'clinic_id' => 10, 'user' => ['id' => 2], 'active' => true, 'roles' => ['gerente']],
    'medico' => ['scope' => 'clinic', 'clinic_id' => 10, 'user' => ['id' => 3], 'active' => true, 'roles' => ['medico']],
    'assistente' => ['scope' => 'clinic', 'clinic_id' => 10, 'user' => ['id' => 4], 'active' => true, 'roles' => ['assistente']],
    'recepcionista' => ['scope' => 'clinic', 'clinic_id' => 10, 'user' => ['id' => 5], 'active' => true, 'roles' => ['recepcionista']],
];

$incidentAllowed = $service->evaluate('admin_errors', 'POST', ['act' => 'resolve_incident'], $globalAdmin);
$record('developer_can_resolve_incident', $incidentAllowed->allowed, $incidentAllowed->evidence);
foreach ($clinicContexts as $role => $context) {
    $decision = $service->evaluate('admin_errors', 'POST', ['act' => 'resolve_incident'], $context);
    $record('clinic_role_denied_global_incident:' . $role, !$decision->allowed, $decision->evidence);
}
$staleGlobal = $globalAdmin;
$staleGlobal['user']['id'] = 11;
$staleGlobal['global_admin'] = false;
$decision = $service->evaluate('admin_errors', 'POST', ['act' => 'resolve_incident'], $staleGlobal);
$record('stale_global_credential_denied', !$decision->allowed, $decision->evidence);

$managerClinic = $service->evaluate('maestro', 'POST', ['act' => 'save_rule'], $clinicContexts['gerente']);
$doctorClinic = $service->evaluate('maestro', 'POST', ['act' => 'save_rule'], $clinicContexts['medico']);
$assistantTask = $service->evaluate('tasks', 'POST', ['act' => 'done'], $clinicContexts['assistente']);
$doctorTask = $service->evaluate('tasks', 'POST', ['act' => 'done'], $clinicContexts['medico']);
$receptionCash = $service->evaluate('financial', 'POST', ['act' => 'cash_open'], $clinicContexts['recepcionista']);
$receptionAdminFinance = $service->evaluate('financial', 'POST', ['act' => 'admin_receive'], $clinicContexts['recepcionista']);
$record('manager_only_allows_manager', $managerClinic->allowed, $managerClinic->evidence);
$record('manager_only_denies_professional', !$doctorClinic->allowed, $doctorClinic->evidence);
$record('assistant_task_scope_allowed', $assistantTask->allowed, $assistantTask->evidence);
$record('professional_without_task_role_denied', !$doctorTask->allowed, $doctorTask->evidence);
$record('reception_exact_cashier_action_allowed', $receptionCash->allowed, $receptionCash->evidence);
$record('reception_admin_financial_action_denied', !$receptionAdminFinance->allowed, $receptionAdminFinance->evidence);

$failed = array_keys(array_filter($checks, static fn(array $check): bool => !$check['ok']));
$result = [
    'ok' => $failed === [],
    'version' => '1.7.21.6',
    'checks' => count($checks),
    'failed' => $failed,
    'details' => $checks,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failed === [] ? 0 : 1);
'''
write("tools/authorization-scope-check.php", auth_tool)

report = '''# Auditoria de autorização por escopo — Prontoo 1.7.21.6

## Causa raiz

O middleware de autorização é executado antes do handler da página. A tela de Incidentes enviava POST sem `act`; o catálogo, portanto, procurava `admin_errors::__default__`, que não existia. A decisão correta era `action_contract_missing`, mas a interface exibia a mensagem genérica de credencial insuficiente.

## Falhas confirmadas

1. `admin_errors`: o formulário “Marcar resolvido” não declarava uma ação exata e não havia contrato correspondente.
2. `admin_security`: o formulário “Liberar” bloqueio de login repetia o mesmo padrão e falharia pelo mesmo motivo.

## Correções

- ações explícitas `resolve_incident` e `release_login_lock`;
- contratos globais `global_admin` exigindo exclusivamente `admin:*`;
- handlers rejeitam ações desconhecidas e identificadores inválidos;
- updates/deletes retornam mensagem idempotente quando o registro já foi tratado;
- ferramenta permanente `tools/authorization-scope-check.php`.

## Diagnóstico ampliado por cargo

A matriz regressiva comprova:

- Desenvolvedor global ativo executa ações globais exatas;
- credencial global desativada ou sem `is_global_admin` é recusada;
- Administrativo, Profissional, Assistente e Recepção nunca herdam `admin:*`;
- Administrativo satisfaz políticas `manager_only` apenas no escopo do consultório;
- Assistente executa tarefas somente quando a capacidade está presente;
- Recepção executa apenas ações operacionais de caixa explicitamente permitidas;
- ações financeiras administrativas permanecem negadas à Recepção;
- Desenvolvedor global não herda capacidades clínicas sem vínculo de consultório.

## Cobertura dos demais POSTs

Os contratos explícitos das demais superfícies globais e clínicas já estavam presentes. Foram mantidos fail-closed: ação desconhecida ou ausente continua recusada, em vez de receber uma permissão genérica por rota.

## Banco e interface

- nenhuma alteração em banco, schema, tabela, coluna, índice ou constraint;
- `schema.sql` permanece byte a byte inalterado;
- apenas campos ocultos de ação foram adicionados aos formulários; não há mudança visual.
'''
write("AUTHORIZATION-AUDIT-1.7.21.6.md", report)

now = datetime.now(timezone.utc).replace(microsecond=0)
iso = now.isoformat().replace("+00:00", "Z")
unix = int(now.timestamp())

version = json.loads(read("version.json"))
version.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": unix,
    "generated_at": iso,
    "updated_at": iso,
    "build": BUILD,
    "package_type": "authorization_scope_hotfix",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "notes": "Corrige contratos exatos de Incidentes e Segurança e amplia a matriz regressiva de autorização por cargo.",
    "previous_version": PREVIOUS,
})
write("version.json", json.dumps(version, ensure_ascii=False, indent=2) + "\n")

path = "app/prontoo.php"
text = read(path)
text = replace_once(text, 'const PRONTOO_VERSION_FALLBACK = "1.7.21.5";', 'const PRONTOO_VERSION_FALLBACK = "1.7.21.6";', "fallback runtime")
write(path, text)

path = "br/index.php"
text = read(path)
text = replace_once(text, 'const BR_LANDING_VERSION_FALLBACK = "1.7.21.5";', 'const BR_LANDING_VERSION_FALLBACK = "1.7.21.6";', "fallback landing")
write(path, text)

architecture = json.loads(read("app/architecture.manifest.json"))
architecture["version"] = VERSION
architecture["authorization_scope_check"] = "tools/authorization-scope-check.php"
architecture["authorization_action_policy"] = "every_protected_post_uses_an_explicit_exact_action_contract_and_unknown_actions_fail_closed"
write("app/architecture.manifest.json", json.dumps(architecture, ensure_ascii=False, indent=2) + "\n")

operational = json.loads(read("app/Database/operational-schema.contract.json"))
operational["version"] = VERSION
write("app/Database/operational-schema.contract.json", json.dumps(operational, ensure_ascii=False, indent=2) + "\n")

changelog = read("ChangeLog.txt")
entry = '''Prontoo 1.7.21.6 — autorização por escopo e contratos exatos
- Corrige a ação de marcar Incidentes como resolvidos no Painel do Desenvolvedor.
- Corrige preventivamente a liberação de bloqueios de login na tela Segurança.
- Amplia testes de autorização para Desenvolvedor, Administrativo, Profissional, Assistente e Recepção.
- Preserva integralmente banco, schema r7 e 62 tabelas.

'''
if not changelog.startswith("Prontoo 1.7.21.6"):
    changelog = entry + changelog
write("ChangeLog.txt", changelog)

manifest = json.loads(read("app/update.manifest.json"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "package_type": "authorization_scope_hotfix",
    "generated_at": iso,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "previous_version": PREVIOUS,
    "updated_at": iso,
    "notes": "Contratos exatos para Incidentes e Segurança e auditoria regressiva de escopos por cargo.",
})
product_files = [
    "ChangeLog.txt",
    "AUTHORIZATION-AUDIT-1.7.21.6.md",
    "app/Admin/AdminPages.php",
    "app/Application/Authorization/ActionCatalog.php",
    "app/Database/operational-schema.contract.json",
    "app/Infrastructure/Authorization/RuntimeCapabilityProvider.php",
    "app/architecture.manifest.json",
    "app/prontoo.php",
    "br/index.php",
    "tools/authorization-scope-check.php",
    "version.json",
]
manifest["files"] = {
    item: hashlib.sha256((ROOT / item).read_bytes()).hexdigest()
    for item in product_files
}
manifest["file_count"] = len(product_files)
manifest["total_uncompressed_bytes"] = sum((ROOT / item).stat().st_size for item in product_files)
write("app/update.manifest.json", json.dumps(manifest, ensure_ascii=False, indent=2) + "\n")

print(json.dumps({"ok": True, "version": VERSION, "files": product_files}, ensure_ascii=False))
