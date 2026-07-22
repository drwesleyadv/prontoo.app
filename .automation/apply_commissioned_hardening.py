#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OLD_VERSION = "1.7.22.2"
NEW_VERSION = "1.7.22.3"
GENERATED_AT = "2026-07-22T13:21:09Z"
GENERATED_AT_UNIX = 1784726469
BUILD = "1.7.22.3-commissioned-hardening-lockdown"
PACKAGE_TYPE = "commissioned_hardening_lockdown"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(source: str, pattern: str, replacement: str, label: str, flags: int = 0) -> str:
    updated, count = re.subn(pattern, lambda _m: replacement, source, count=1, flags=flags)
    if count != 1:
        raise RuntimeError(f"Substituição não aplicada exatamente uma vez: {label} ({count})")
    return updated


# 1. Bloqueio canônico do instalador: nenhum contexto HTTP é autorizado.
install_access = read("app/Core/Install/InstallAccess.php")
install_access = re.sub(
    r"\n\s*public const TEMPORARY_PUBLIC_HOST = '[^']+';"
    r"\n\s*public const TEMPORARY_PUBLIC_WINDOW_START_UNIX = \d+;"
    r"\n\s*public const TEMPORARY_PUBLIC_WINDOW_END_UNIX = \d+;",
    "",
    install_access,
    count=1,
)
installer_method = r'''    public static function isInstallerExecutionAllowed(?array $server = null, ?int $now = null): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::isInstallerExecutionAllowed
         * Responsabilidade: Decide se o código de instalação pode executar após o comissionamento. Nenhuma requisição HTTP — pública, local ou encaminhada — é aceita. A única exceção é a certificação CLI do GitHub Actions, que exige quatro marcadores simultâneos e explícitos.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.InstallAccess::assertInstallerEntry`.
         * Dependências chamadas: `getenv`.
         * Estado externo lido: `PHP_SAPI` e variáveis de ambiente exclusivas da certificação.
         * Efeitos colaterais: nenhum; apenas produz uma decisão fail-closed.
         * Cuidado 1: Não reintroduza autorização por host, relógio, localhost ou cabeçalhos encaminhados. Depois do comissionamento, HTTP deve permanecer invariavelmente bloqueado.
         * Cuidado 2: A certificação exige simultaneamente `GITHUB_ACTIONS=true`, `CI=true`, `PRONTOO_SCHEMA_TEST_MODE=1` e `PRONTOO_INSTALLER_CLI_MODE=1`; nenhum marcador isolado deve abrir a instalação.
         */
        if (PHP_SAPI !== 'cli' || $server !== null) {
            return false;
        }
        return (string) getenv('GITHUB_ACTIONS') === 'true' &&
            (string) getenv('CI') === 'true' &&
            (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' &&
            (string) getenv('PRONTOO_INSTALLER_CLI_MODE') === '1';
    }

'''
install_access = replace_once(
    install_access,
    r"    public static function isInstallerExecutionAllowed\(\?array \$server = null, \?int \$now = null\): bool\n"
    r"    \{.*?\n    \}\n\n(?=    public static function assertInstallerEntry)",
    installer_method,
    "InstallAccess::isInstallerExecutionAllowed",
    re.S,
)
assert_method = r'''    public static function assertInstallerEntry(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::assertInstallerEntry
         * Responsabilidade: Protege o primeiro ponto executável de install.php após o comissionamento e encerra qualquer acesso HTTP. Somente a certificação CLI integralmente marcada pode ultrapassar esta guarda.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `install.php`, `prontoo_install`.
         * Dependências chamadas: `self::isInstallerExecutionAllowed`, `self::denyPublicAccess`.
         * Estado externo lido: contexto HTTP ou CLI por meio de `isInstallerExecutionAllowed`.
         * Efeitos colaterais: pode encerrar a requisição antes do bootstrap da aplicação.
         * Cuidado 1: Esta guarda deve continuar antes de `app/prontoo.php`; movê-la para depois do bootstrap expõe trabalho e diagnóstico desnecessários.
         * Cuidado 2: Não crie bypass por localhost. O acesso ao instalador foi encerrado porque a instalação de produção já foi concluída.
         */
        if (!self::isInstallerExecutionAllowed()) {
            self::denyPublicAccess();
        }
    }

'''
install_access = replace_once(
    install_access,
    r"    public static function assertInstallerEntry\(\): void\n"
    r"    \{.*?\n    \}\n\n(?=    public static function denyPublicAccess)",
    assert_method,
    "InstallAccess::assertInstallerEntry",
    re.S,
)
write("app/Core/Install/InstallAccess.php", install_access)

# 2. Janela de DDL: somente certificação CLI do GitHub Actions.
schema_lock = read("app/Core/Database/SchemaMutationLock.php")
schema_lock = schema_lock.replace("\nuse Prontoo\\Core\\Install\\InstallAccess;\n", "\n", 1)
schema_lock = schema_lock.replace(
    "O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer no instalador autorizado — local ou dentro da janela pública temporária — ou no CI controlado.",
    "O `schema.sql` é congelado em runtime; após o comissionamento, a janela estrutural existe somente na certificação CLI do GitHub Actions com todos os marcadores exigidos.",
)
may_open = r'''    private static function mayOpenInstallerWindow(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaMutationLock::mayOpenInstallerWindow
         * Responsabilidade: Autoriza a abertura transitória da estrutura exclusivamente na certificação CLI do GitHub Actions. HTTP, localhost e variáveis parciais permanecem bloqueados.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SchemaMutationLock::runForInstaller`.
         * Dependências chamadas: `getenv`.
         * Estado externo lido: `PHP_SAPI` e os quatro marcadores da certificação.
         * Efeitos colaterais: nenhum; apenas produz uma decisão fail-closed.
         * Cuidado 1: Não reintroduza `PRONTOO_ALLOW_LOCAL_INSTALL`, autorização HTTP ou bypass por localhost.
         * Cuidado 2: A condição deve continuar exigindo `GITHUB_ACTIONS=true`, `CI=true`, `PRONTOO_SCHEMA_TEST_MODE=1` e `PRONTOO_INSTALLER_CLI_MODE=1` ao mesmo tempo.
         */
        return PHP_SAPI === 'cli' &&
            (string) getenv('GITHUB_ACTIONS') === 'true' &&
            (string) getenv('CI') === 'true' &&
            (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' &&
            (string) getenv('PRONTOO_INSTALLER_CLI_MODE') === '1';
    }
'''
schema_lock = replace_once(
    schema_lock,
    r"    private static function mayOpenInstallerWindow\(\): bool\n    \{.*?\n    \}\n(?=\})",
    may_open,
    "SchemaMutationLock::mayOpenInstallerWindow",
    re.S,
)
write("app/Core/Database/SchemaMutationLock.php", schema_lock)

# 3. Runtime sem configuração nunca redireciona ao instalador.
runner = read("app/Runtime/Runner.php")
old_no_config = '''        if (!has_cfg() && !$installMode && !$publicHome) {
            if (is_file(storage_path("install.lock"))) {
                throw new ProntooHttpError(
                    503,
                    "Instalação existente detectada, mas app/config.php não foi encontrado. Não execute o instalador: restaure o arquivo de configuração da instalação atual.",
                );
            }
            if (\\Prontoo\\Core\\Install\\InstallAccess::isInstallerExecutionAllowed()) {
                if (!headers_sent()) {
                    header("Location: /install.php", true, 302);
                }
                exit();
            }
            throw new ProntooHttpError(
                503,
                "Configuração ausente. O instalador não é exposto publicamente; restaure app/config.php ou acesse o servidor diretamente pelo localhost.",
            );
        }
'''
new_no_config = '''        if (!has_cfg() && !$installMode && !$publicHome) {
            throw new ProntooHttpError(
                503,
                is_file(storage_path("install.lock"))
                    ? "Instalação existente detectada, mas app/config.php não foi encontrado. O instalador está bloqueado: restaure o arquivo de configuração da instalação atual."
                    : "Configuração ausente. A instalação desta publicação está encerrada e não pode ser iniciada por HTTP; restaure app/config.php a partir do ambiente comissionado.",
            );
        }
'''
if old_no_config not in runner:
    raise RuntimeError("Bloco de fallback sem configuração não encontrado")
runner = runner.replace(old_no_config, new_no_config, 1)
runner = runner.replace(
    "* Dependências chamadas: `boot_security`, `guard_request`, `route`, `headers_secure`, `has_cfg`, `is_file`, `storage_path`, `ProntooHttpError`, `.Core.Install.InstallAccess::isInstallerExecutionAllowed`, `headers_sent`, `header`, `prontoo_boot_database_for_route` e mais 29.",
    "* Dependências chamadas: `boot_security`, `guard_request`, `route`, `headers_secure`, `has_cfg`, `is_file`, `storage_path`, `ProntooHttpError`, `prontoo_boot_database_for_route` e demais serviços de autorização, cache, auditoria e renderização.",
    1,
)
write("app/Runtime/Runner.php", runner)

# 4. Bloqueio no servidor web antes do PHP.
htaccess = read(".htaccess")
old_htaccess = '''# Janela pública temporária do instalador.
# O controle efetivo permanece em app/Core/Install/InstallAccess.php e expira
# automaticamente em 2026-07-22T16:06:54Z (22/07/2026 12:06:54 em Cuiabá).
<Files "install.php">
    Require all granted
</Files>
'''
new_htaccess = '''# Instalação concluída e comissionada.
# O instalador permanece no pacote apenas para rastreabilidade e certificação,
# mas não pode ser servido pelo Apache/LiteSpeed em produção.
<Files "install.php">
    Require all denied
</Files>
'''
if old_htaccess not in htaccess:
    raise RuntimeError("Regra pública temporária do .htaccess não encontrada")
write(".htaccess", htaccess.replace(old_htaccess, new_htaccess, 1))

# 5. Microajuste visual: Network Ping no painel do Desenvolvedor.
components = read("app/Ui/Components.php")
needle = '''    $current = route();
    $params = $_GET;
'''
replacement = '''    $current = route();
    $params = $_GET;
    if ($current === "admin_painel") {
        return "network_ping";
    }
'''
if needle not in components:
    raise RuntimeError("Ponto canônico do ícone do PageHead não encontrado")
write("app/Ui/Components.php", components.replace(needle, replacement, 1))

# 6. O schema-check precisa dos quatro marcadores simultâneos.
schema_check = read("tools/schema-check.php")
marker_block = "putenv('CI=true');\nputenv('PRONTOO_SCHEMA_TEST_MODE=1');\n"
replacement_markers = (
    "putenv('GITHUB_ACTIONS=true');\n"
    "putenv('CI=true');\n"
    "putenv('PRONTOO_SCHEMA_TEST_MODE=1');\n"
    "putenv('PRONTOO_INSTALLER_CLI_MODE=1');\n"
)
if marker_block not in schema_check:
    raise RuntimeError("Marcadores do schema-check não encontrados")
write("tools/schema-check.php", schema_check.replace(marker_block, replacement_markers, 1))

# 7. Teste permanente de hardening pós-comissionamento.
security_check = r'''<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/Core/Install/InstallAccess.php';
require_once $root . '/app/Core/Database/SchemaMutationLock.php';
require_once $root . '/app/Database/DatabaseSchema.php';

use Prontoo\Core\Database\SchemaMutationLock;
use Prontoo\Core\Install\InstallAccess;

$errors = [];
$originalServer = $_SERVER;
$originalEnv = [];
foreach ([
    'GITHUB_ACTIONS',
    'CI',
    'PRONTOO_SCHEMA_TEST_MODE',
    'PRONTOO_INSTALLER_CLI_MODE',
    'PRONTOO_ALLOW_LOCAL_INSTALL',
] as $name) {
    $value = getenv($name);
    $originalEnv[$name] = $value === false ? null : (string) $value;
    putenv($name);
}

$publicServer = [
    'REMOTE_ADDR' => '203.0.113.10',
    'HTTP_HOST' => 'prontoo.app',
    'HTTPS' => 'on',
    'SERVER_PORT' => '443',
];
$localServer = [
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_HOST' => 'localhost',
    'SERVER_PORT' => '80',
];
if (InstallAccess::isInstallerExecutionAllowed($publicServer)) {
    $errors[] = 'public_http_installer_allowed';
}
if (InstallAccess::isInstallerExecutionAllowed($localServer)) {
    $errors[] = 'local_http_installer_allowed';
}
if (InstallAccess::isInstallerExecutionAllowed()) {
    $errors[] = 'unmarked_cli_installer_allowed';
}

putenv('CI=true');
if (InstallAccess::isInstallerExecutionAllowed()) {
    $errors[] = 'partial_cli_installer_allowed_ci_only';
}
putenv('GITHUB_ACTIONS=true');
putenv('PRONTOO_SCHEMA_TEST_MODE=1');
if (InstallAccess::isInstallerExecutionAllowed()) {
    $errors[] = 'partial_cli_installer_allowed_without_installer_mode';
}
putenv('PRONTOO_INSTALLER_CLI_MODE=1');
if (!InstallAccess::isInstallerExecutionAllowed()) {
    $errors[] = 'fully_marked_github_actions_cli_denied';
}

$opened = false;
try {
    $opened = SchemaMutationLock::runForInstaller(static function (): bool {
        /*
         * GUIA DE MANUTENÇÃO — closure@tools/install-security-check.php:62
         * Responsabilidade: Confirma que a janela estrutural só abre no contexto integral da certificação e que o nonce interno é válido durante o callback.
         * Local arquitetural: tools/install-security-check.php (ferramentas de certificação e manutenção).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `SchemaMutationLock::isActive`, `db_reject_runtime_ddl`.
         * Efeitos colaterais: executa somente uma prova controlada, sem persistir estrutura ou dados.
         * Cuidado 1: Mantenha esta closure única para preservar o inventário documental da baseline.
         */
        if (!SchemaMutationLock::isActive()) {
            return false;
        }
        db_reject_runtime_ddl('CREATE TABLE pi_test (id int)');
        return true;
    });
} catch (Throwable $error) {
    $errors[] = 'fully_marked_schema_window:' . $error->getMessage();
}
if (!$opened || SchemaMutationLock::isActive()) {
    $errors[] = 'schema_window_lifecycle';
}

putenv('PRONTOO_INSTALLER_CLI_MODE');
$blockedPartial = false;
try {
    SchemaMutationLock::runForInstaller('strlen');
} catch (RuntimeException $error) {
    $blockedPartial = str_contains($error->getMessage(), 'janela estrutural');
}
if (!$blockedPartial) {
    $errors[] = 'partial_schema_markers_not_blocked';
}

$ddlBlocked = false;
try {
    db_reject_runtime_ddl('ALTER TABLE pi_meta ADD COLUMN forbidden int');
} catch (RuntimeException $error) {
    $ddlBlocked = str_contains($error->getMessage(), 'estrutura do banco está congelada');
}
if (!$ddlBlocked) {
    $errors[] = 'runtime_ddl_not_blocked';
}

$installAccessSource = (string) file_get_contents($root . '/app/Core/Install/InstallAccess.php');
foreach ([
    'TEMPORARY_PUBLIC_HOST',
    'TEMPORARY_PUBLIC_WINDOW_START_UNIX',
    'TEMPORARY_PUBLIC_WINDOW_END_UNIX',
] as $legacy) {
    if (str_contains($installAccessSource, $legacy)) {
        $errors[] = 'legacy_public_window_symbol:' . $legacy;
    }
}
foreach ([
    "getenv('GITHUB_ACTIONS')",
    "getenv('CI')",
    "getenv('PRONTOO_SCHEMA_TEST_MODE')",
    "getenv('PRONTOO_INSTALLER_CLI_MODE')",
] as $required) {
    if (!str_contains($installAccessSource, $required)) {
        $errors[] = 'installer_marker_missing:' . $required;
    }
}

$schemaLockSource = (string) file_get_contents($root . '/app/Core/Database/SchemaMutationLock.php');
if (str_contains($schemaLockSource, 'PRONTOO_ALLOW_LOCAL_INSTALL')) {
    $errors[] = 'legacy_local_install_bypass';
}
if (str_contains($schemaLockSource, 'InstallAccess::isInstallerExecutionAllowed') ||
    str_contains($schemaLockSource, 'use Prontoo\\Core\\Install\\InstallAccess')) {
    $errors[] = 'schema_lock_depends_on_http_install_access';
}

$installEntry = (string) file_get_contents($root . '/install.php');
$guardPos = strpos($installEntry, 'InstallAccess::assertInstallerEntry');
$bootstrapPos = strpos($installEntry, 'app/prontoo.php');
if ($guardPos === false || $bootstrapPos === false || $guardPos > $bootstrapPos) {
    $errors[] = 'install_entry_guard_order';
}

$installer = (string) file_get_contents($root . '/app/Install/Installer.php');
if (str_contains($installer, 'assertLocalEntry') ||
    !str_contains($installer, 'InstallAccess::assertInstallerEntry')) {
    $errors[] = 'installer_internal_guard';
}

$runtime = (string) file_get_contents($root . '/app/Runtime/Runner.php');
if (str_contains($runtime, 'Location: /install.php') ||
    str_contains($runtime, 'InstallAccess::isInstallerExecutionAllowed')) {
    $errors[] = 'runtime_install_fallback_present';
}

$htaccess = (string) file_get_contents($root . '/.htaccess');
if (!preg_match('/<Files\s+"install\.php">\s*(?:#[^\n]*\s*)*Require\s+all\s+denied\s*<\/Files>/s', $htaccess) ||
    preg_match('/<Files\s+"install\.php">\s*Require\s+all\s+granted\s*<\/Files>/s', $htaccess)) {
    $errors[] = 'webserver_install_not_denied';
}

$components = (string) file_get_contents($root . '/app/Ui/Components.php');
if (!preg_match('/\$current\s*===\s*"admin_painel"\)\s*\{\s*return\s+"network_ping";/s', $components)) {
    $errors[] = 'developer_panel_network_ping_icon';
}

$forbiddenAssignments = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo ||
        !$file->isFile() ||
        strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace(
        str_replace('\\', '/', $root) . '/',
        '',
        str_replace('\\', '/', $file->getPathname()),
    );
    $content = (string) file_get_contents($file->getPathname());
    if ($relative !== 'app/Core/Database/SchemaMutationLock.php' &&
        preg_match('/PRONTOO_SCHEMA_INSTALLING["\']?\]\s*=/', $content)) {
        $forbiddenAssignments[] = $relative;
    }
}
if ($forbiddenAssignments !== []) {
    $errors[] = 'schema_flag_assignment_outside_lock:' . implode(',', $forbiddenAssignments);
}

$_SERVER = $originalServer;
foreach ($originalEnv as $name => $value) {
    if ($value === null) {
        putenv($name);
    } else {
        putenv($name . '=' . $value);
    }
}

$errors = array_values(array_unique($errors));
$result = [
    'ok' => $errors === [],
    'policy' => 'commissioned-installation-lockdown-v1',
    'public_installer' => false,
    'local_http_installer' => false,
    'runtime_install_redirect' => false,
    'schema_mutation' => 'github-actions-cli-four-markers-only',
    'webserver_install_denied' => true,
    'developer_panel_icon' => 'network_ping',
    'schema_frozen' => true,
    'errors' => $errors,
];
echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
), PHP_EOL;
exit($errors === [] ? 0 : 1);
'''
write("tools/install-security-check.php", security_check)

# 8. Versionamento e contratos públicos.
prontoo = read("app/prontoo.php")
prontoo = replace_once(
    prontoo,
    r'const PRONTOO_VERSION_FALLBACK = "[^"]+";',
    f'const PRONTOO_VERSION_FALLBACK = "{NEW_VERSION}";',
    "PRONTOO_VERSION_FALLBACK",
)
prontoo = replace_once(
    prontoo,
    r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";',
    f'const PRONTOO_PREVIOUS_VERSION = "{OLD_VERSION}";',
    "PRONTOO_PREVIOUS_VERSION",
)
write("app/prontoo.php", prontoo)

landing = read("br/index.php")
landing = replace_once(
    landing,
    r'const BR_LANDING_VERSION_FALLBACK = "[^"]+";',
    f'const BR_LANDING_VERSION_FALLBACK = "{NEW_VERSION}";',
    "BR_LANDING_VERSION_FALLBACK",
)
write("br/index.php", landing)

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update(
    {
        "version": NEW_VERSION,
        "release": NEW_VERSION,
        "generated_at_unix": GENERATED_AT_UNIX,
        "generated_at": GENERATED_AT,
        "updated_at": GENERATED_AT,
        "build": BUILD,
        "package_type": PACKAGE_TYPE,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "previous_version": OLD_VERSION,
        "documentation_changes": True,
        "notes": "Hardening pós-comissionamento: bloqueia o instalador em HTTP, remove o fallback de instalação, restringe a janela estrutural à certificação CLI integral do GitHub Actions e aplica Network Ping ao painel do Desenvolvedor.",
        "installer_locked": True,
        "installer_access_policy": "http_disabled_github_actions_cli_certification_only",
        "schema_mutation_policy": "runtime_frozen_github_actions_cli_four_markers_only",
    }
)
version.pop("temporary_public_installer", None)
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

operational_path = ROOT / "app/Database/operational-schema.contract.json"
operational = json.loads(operational_path.read_text(encoding="utf-8"))
operational["version"] = NEW_VERSION
operational_path.write_text(json.dumps(operational, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update(
    {
        "version": NEW_VERSION,
        "installer_access_policy": "http_disabled_after_commissioning_github_actions_cli_certification_only",
        "schema_mutation_policy": "runtime_frozen_github_actions_cli_four_markers_only",
        "logic_changes": True,
        "visual_changes": True,
        "commissioned_installation_lockdown": {
            "http": "denied_at_webserver_and_php_entry",
            "runtime_redirect": False,
            "localhost_bypass": False,
            "public_window": False,
            "schema_mutation": "github-actions-cli-four-markers-only",
            "developer_panel_icon": "network_ping",
        },
    }
)
architecture.pop("temporary_public_installer_window", None)
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

# 9. Changelog único.
changelog = read("ChangeLog.txt")
entry = f'''Prontoo {NEW_VERSION} — hardening pós-comissionamento e Network Ping

- Confirma a instalação de produção como concluída e encerra a superfície de instalação por HTTP.
- Substitui a regra temporária `Require all granted` do `install.php` por `Require all denied` no Apache/LiteSpeed.
- Mantém uma segunda guarda fail-closed no primeiro ponto PHP: requisições públicas, localhost e cabeçalhos encaminhados não podem executar o instalador.
- Remove do runtime o redirecionamento automático para `/install.php` quando `app/config.php` estiver ausente; o sistema exige a restauração da configuração do ambiente comissionado.
- Remove os símbolos e timestamps da antiga janela pública temporária.
- Restringe `SchemaMutationLock` à certificação CLI do GitHub Actions, exigindo simultaneamente `GITHUB_ACTIONS=true`, `CI=true`, `PRONTOO_SCHEMA_TEST_MODE=1` e `PRONTOO_INSTALLER_CLI_MODE=1`.
- Elimina o bypass legado `PRONTOO_ALLOW_LOCAL_INSTALL` e impede que a autorização de DDL dependa de uma decisão HTTP.
- Reforça o teste permanente para bloquear instalação pública, instalação HTTP local, fallback do runtime, marcadores parciais, atribuição externa da flag estrutural e DDL em runtime.
- Preserva `schema.sql`, revisão r7, 62 tabelas, dados existentes e todos os contratos operacionais.
- No painel do Desenvolvedor, o ícone do PageHead passa a usar o Material Symbol `network_ping` (Network Ping), mantendo a classe visual e a biblioteca já carregada.
- Alteração de banco: não. Alteração de schema: não. Alteração visual: apenas o ícone Network Ping no painel do Desenvolvedor.

'''
write("ChangeLog.txt", entry + changelog)

# 10. Manifesto selado.
manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update(
    {
        "version": NEW_VERSION,
        "release": NEW_VERSION,
        "build": BUILD,
        "package_type": PACKAGE_TYPE,
        "generated_at": GENERATED_AT,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "documentation_changes": True,
        "installer_policy": "http_disabled_after_commissioning_github_actions_cli_certification_only",
        "schema_mutation_policy": "runtime_frozen_github_actions_cli_four_markers_only",
        "notes": "Bloqueia novamente instalação e DDL após o comissionamento e aplica o ícone Network Ping no painel do Desenvolvedor.",
    }
)
files = dict(manifest.get("files", {}))
for path in [
    ".htaccess",
    "ChangeLog.txt",
    "app/Core/Database/SchemaMutationLock.php",
    "app/Core/Install/InstallAccess.php",
    "app/Database/operational-schema.contract.json",
    "app/Runtime/Runner.php",
    "app/Ui/Components.php",
    "app/architecture.manifest.json",
    "app/prontoo.php",
    "br/index.php",
    "tools/install-security-check.php",
    "tools/schema-check.php",
    "version.json",
]:
    if ROOT.joinpath(path).is_file():
        files.setdefault(path, "")
sealed = {}
total_bytes = 0
for path in sorted(files):
    file_path = ROOT / path
    if not file_path.is_file():
        raise RuntimeError(f"Arquivo do manifesto ausente: {path}")
    data = file_path.read_bytes()
    sealed[path] = hashlib.sha256(data).hexdigest()
    total_bytes += len(data)
manifest["files"] = sealed
manifest["file_count"] = len(sealed)
manifest["total_uncompressed_bytes"] = total_bytes
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

# 11. Autoverificação.
checks = {
    "no_public_window_constants": all(
        token not in read("app/Core/Install/InstallAccess.php")
        for token in ["TEMPORARY_PUBLIC_HOST", "TEMPORARY_PUBLIC_WINDOW_START_UNIX", "TEMPORARY_PUBLIC_WINDOW_END_UNIX"]
    ),
    "webserver_denied": '<Files "install.php">\n    Require all denied\n</Files>' in read(".htaccess") and "Require all granted" not in read(".htaccess"),
    "runtime_redirect_removed": "Location: /install.php" not in read("app/Runtime/Runner.php") and "InstallAccess::isInstallerExecutionAllowed" not in read("app/Runtime/Runner.php"),
    "schema_ci_only": "PRONTOO_ALLOW_LOCAL_INSTALL" not in read("app/Core/Database/SchemaMutationLock.php") and "GITHUB_ACTIONS" in read("app/Core/Database/SchemaMutationLock.php") and "PRONTOO_INSTALLER_CLI_MODE" in read("app/Core/Database/SchemaMutationLock.php"),
    "network_ping": '$current === "admin_painel"' in read("app/Ui/Components.php") and 'return "network_ping";' in read("app/Ui/Components.php"),
    "version": json.loads(read("version.json"))["version"] == NEW_VERSION,
}
failed = [name for name, ok in checks.items() if not ok]
if failed:
    raise RuntimeError("Autoverificação falhou: " + ", ".join(failed))

print(json.dumps({"ok": True, "version": NEW_VERSION, "policy": "commissioned-installation-lockdown-v1", "checks": checks, "manifest_files": len(sealed), "manifest_bytes": total_bytes}, ensure_ascii=False, indent=2))
