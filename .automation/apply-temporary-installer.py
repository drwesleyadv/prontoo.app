#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.21.8"
PREVIOUS_VERSION = "1.7.21.7"
BUILD = "1.7.21.8-temporary-public-installer"
PACKAGE_TYPE = "temporary_public_installer_window"
START_UNIX = 1784670941
END_UNIX = 1784685341
START_ISO = "2026-07-21T21:55:41Z"
END_ISO = "2026-07-22T01:55:41Z"
START_LOCAL = "21/07/2026 17:55:41 America/Cuiaba"
END_LOCAL = "21/07/2026 21:55:41 America/Cuiaba"
SCHEMA_HASH = "28515f46af3081e5ac0dded5723e6d2d7ceddec11e9c6f782975f31931a69d33"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1 trecho, encontrado {count}")
    return content.replace(old, new, 1)


def replace_regex(content: str, pattern: str, replacement: str, label: str) -> str:
    updated, count = re.subn(pattern, replacement, content, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f"{label}: padrão não encontrado uma única vez")
    return updated


htaccess = read(".htaccess")
htaccess = replace_regex(
    htaccess,
    r'<Files "install\.php">\s*Require local\s*</Files>',
    '''# Janela pública temporária do instalador.
# O controle efetivo permanece em app/Core/Install/InstallAccess.php e expira
# automaticamente em 2026-07-22T01:55:41Z (21/07/2026 21:55:41 em Cuiabá).
<Files "install.php">
    Require all granted
</Files>''',
    ".htaccess installer gate",
)
write(".htaccess", htaccess)

access = read("app/Core/Install/InstallAccess.php")
access = replace_once(
    access,
    "    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1'];\n",
    "    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1'];\n"
    "    public const TEMPORARY_PUBLIC_HOST = 'prontoo.app';\n"
    f"    public const TEMPORARY_PUBLIC_WINDOW_START_UNIX = {START_UNIX};\n"
    f"    public const TEMPORARY_PUBLIC_WINDOW_END_UNIX = {END_UNIX};\n",
    "InstallAccess constants",
)

execution_block = r'''    public static function isLocalExecution\(\): bool
    \{
.*?
    \}

    public static function assertLocalEntry\(\): void
    \{
.*?
    \}
'''
execution_replacement = f'''    public static function isInstallerExecutionAllowed(?array $server = null, ?int $now = null): bool
    {{
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::isInstallerExecutionAllowed
         * Responsabilidade: Decide se o instalador pode executar no contexto atual. CLI continua autorizado para certificação; HTTP local continua autorizado; HTTP público só é aceito no host HTTPS exato prontoo.app durante a janela UTC fixa de quatro horas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SchemaMutationLock::mayOpenInstallerWindow`, `Core.Install.InstallAccess::assertInstallerEntry`.
         * Dependências chamadas: `self::isLocalServer`, `time`, `self::requestHostFrom`, `strtolower`, `trim`, `explode`, `in_array`.
         * Estado externo lido: `$_SERVER`, relógio Unix do servidor e `PHP_SAPI`.
         * Efeitos colaterais: nenhum; apenas produz uma decisão fail-closed para o ponto de entrada e para a janela estrutural.
         * Cuidado 1: Não prolongue a janela alterando apenas a interface. Os dois timestamps formam o contrato real e o limite final é exclusivo: no segundo {END_UNIX} o acesso público já deve falhar.
         * Cuidado 2: A exceção pública exige simultaneamente host `prontoo.app` e HTTPS. Acesso local mantém as validações contra cabeçalhos encaminhados.
         */
        if (PHP_SAPI === 'cli' && $server === null) {{
            return true;
        }}
        $server ??= $_SERVER;
        if (self::isLocalServer($server)) {{
            return true;
        }}
        $now ??= time();
        if ($now < self::TEMPORARY_PUBLIC_WINDOW_START_UNIX ||
            $now >= self::TEMPORARY_PUBLIC_WINDOW_END_UNIX) {{
            return false;
        }}
        if (self::requestHostFrom($server) !== self::TEMPORARY_PUBLIC_HOST) {{
            return false;
        }}
        $https = strtolower(trim((string) ($server['HTTPS'] ?? '')));
        $forwardedProtoParts = explode(',', strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')));
        $forwardedProto = trim((string) ($forwardedProtoParts[0] ?? ''));
        $serverPort = (int) ($server['SERVER_PORT'] ?? 0);
        return in_array($https, ['on', '1'], true) ||
            $serverPort === 443 ||
            $forwardedProto === 'https';
    }}

    public static function assertInstallerEntry(): void
    {{
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::assertInstallerEntry
         * Responsabilidade: Protege o primeiro ponto executável de install.php e encerra com 404 qualquer acesso que não seja CLI, local legítimo ou a exceção pública temporária ainda vigente.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `install.php`.
         * Dependências chamadas: `self::isInstallerExecutionAllowed`, `self::denyPublicAccess`.
         * Estado externo lido: contexto HTTP ou CLI por meio de `isInstallerExecutionAllowed`.
         * Efeitos colaterais: pode encerrar a requisição antes do bootstrap da aplicação.
         * Cuidado 1: Esta guarda deve continuar antes de `app/prontoo.php`; movê-la para depois do bootstrap expõe trabalho e diagnóstico desnecessários a requisições recusadas.
         */
        if (!self::isInstallerExecutionAllowed()) {{
            self::denyPublicAccess();
        }}
    }}
'''
access = replace_regex(access, execution_block, execution_replacement, "InstallAccess execution methods")
access = access.replace(
    "Chamadores detectados: `Core.Database.SchemaMutationLock::mayOpenInstallerWindow`, `Core.Install.InstallAccess::isLocalExecution`, `prontoo_run`.",
    "Chamadores detectados: `prontoo_run`.",
)
access = access.replace(
    "Chamadores detectados: `Core.Install.InstallAccess::assertLocalEntry`, `prontoo_install`.",
    "Chamadores detectados: `Core.Install.InstallAccess::assertInstallerEntry`, `prontoo_install`.",
)
write("app/Core/Install/InstallAccess.php", access)

install_entry = read("install.php")
install_entry = replace_once(
    install_entry,
    r"\Prontoo\Core\Install\InstallAccess::assertLocalEntry();",
    r"\Prontoo\Core\Install\InstallAccess::assertInstallerEntry();",
    "install.php guard",
)
write("install.php", install_entry)

lock = read("app/Core/Database/SchemaMutationLock.php")
lock = lock.replace(
    "A janela estrutural só pode ser aberta pelo instalador local ou pela certificação controlada.",
    "A janela estrutural só pode ser aberta pelo instalador autorizado ou pela certificação controlada.",
)
lock = lock.replace(
    "Dependências chamadas: `getenv`, `InstallAccess::isLocalHttpRequest`.",
    "Dependências chamadas: `getenv`, `InstallAccess::isInstallerExecutionAllowed`.",
)
lock = lock.replace(
    "Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.",
    "Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer no instalador autorizado — local ou dentro da janela pública temporária — ou no CI controlado.",
)
lock = replace_once(
    lock,
    "        return InstallAccess::isLocalHttpRequest();",
    "        return InstallAccess::isInstallerExecutionAllowed($_SERVER);",
    "SchemaMutationLock public window",
)
write("app/Core/Database/SchemaMutationLock.php", lock)

security = read("tools/install-security-check.php")
security = replace_once(
    security,
    "$_SERVER = $originalServer;\n\nif (SchemaMutationLock::isActive()) {",
    f'''$_SERVER = $originalServer;

$publicServer = [
    'REMOTE_ADDR' => '203.0.113.10',
    'HTTP_HOST' => 'prontoo.app',
    'HTTPS' => 'on',
    'SERVER_PORT' => '443',
];
if (InstallAccess::TEMPORARY_PUBLIC_WINDOW_END_UNIX - InstallAccess::TEMPORARY_PUBLIC_WINDOW_START_UNIX !== 14400) {{
    $errors[] = 'public_window_not_exactly_four_hours';
}}
if (!InstallAccess::isInstallerExecutionAllowed($publicServer, InstallAccess::TEMPORARY_PUBLIC_WINDOW_START_UNIX)) {{
    $errors[] = 'public_window_start_not_allowed';
}}
if (!InstallAccess::isInstallerExecutionAllowed($publicServer, InstallAccess::TEMPORARY_PUBLIC_WINDOW_END_UNIX - 1)) {{
    $errors[] = 'public_window_last_second_not_allowed';
}}
if (InstallAccess::isInstallerExecutionAllowed($publicServer, InstallAccess::TEMPORARY_PUBLIC_WINDOW_END_UNIX)) {{
    $errors[] = 'public_window_expiry_not_closed';
}}
if (InstallAccess::isInstallerExecutionAllowed($publicServer, InstallAccess::TEMPORARY_PUBLIC_WINDOW_START_UNIX - 1)) {{
    $errors[] = 'public_window_before_start_allowed';
}}
$insecurePublicServer = $publicServer;
unset($insecurePublicServer['HTTPS']);
$insecurePublicServer['SERVER_PORT'] = '80';
if (InstallAccess::isInstallerExecutionAllowed($insecurePublicServer, InstallAccess::TEMPORARY_PUBLIC_WINDOW_START_UNIX)) {{
    $errors[] = 'public_window_insecure_http_allowed';
}}
$wrongHostServer = $publicServer;
$wrongHostServer['HTTP_HOST'] = 'example.test';
if (InstallAccess::isInstallerExecutionAllowed($wrongHostServer, InstallAccess::TEMPORARY_PUBLIC_WINDOW_START_UNIX)) {{
    $errors[] = 'public_window_wrong_host_allowed';
}}

if (SchemaMutationLock::isActive()) {{''',
    "install security temporal cases",
)
security = security.replace("InstallAccess::assertLocalEntry", "InstallAccess::assertInstallerEntry")
security = replace_regex(
    security,
    r"if \(!preg_match\('/<Files\\s\+\"install\\\.php\">\\s\*Require\\s\+local\\s\*<\\/Files>/s', \$htaccess\)\) \{\s*\$errors\[\] = 'webserver_local_only_rule';\s*\}",
    '''if (!preg_match('/<Files\\s+"install\\.php">\\s*(?:#[^\\n]*\\s*)*Require\\s+all\\s+granted\\s*<\\/Files>/s', $htaccess) ||
    preg_match('/<Files\\s+"install\\.php">\\s*Require\\s+local\\s*<\\/Files>/s', $htaccess)) {
    $errors[] = 'webserver_temporary_public_rule';
}''',
    "install security htaccess contract",
)
security = security.replace(
    "'policy' => 'localhost-installer-private-schema-window-v1',\n    'public_installer' => false,",
    f"'policy' => 'temporary-public-installer-window-v1',\n"
    "    'public_installer' => true,\n"
    f"    'public_host' => 'prontoo.app',\n"
    f"    'public_window_start_unix' => {START_UNIX},\n"
    f"    'public_window_end_unix' => {END_UNIX},\n"
    f"    'public_window_end_utc' => '{END_ISO}',",
)
write("tools/install-security-check.php", security)

prontoo = read("app/prontoo.php")
prontoo = replace_once(prontoo, 'const PRONTOO_VERSION_FALLBACK = "1.7.21.7";', 'const PRONTOO_VERSION_FALLBACK = "1.7.21.8";', "version fallback")
prontoo = replace_once(prontoo, 'const PRONTOO_PREVIOUS_VERSION = "1.7.21.4";', 'const PRONTOO_PREVIOUS_VERSION = "1.7.21.7";', "previous version fallback")
write("app/prontoo.php", prontoo)

landing = read("br/index.php")
landing = replace_once(landing, 'const BR_LANDING_VERSION_FALLBACK = "1.7.21.7";', 'const BR_LANDING_VERSION_FALLBACK = "1.7.21.8";', "landing version")
write("br/index.php", landing)

version_path = ROOT / "version.json"
version_data = json.loads(version_path.read_text(encoding="utf-8"))
version_data.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": START_UNIX,
    "generated_at": START_ISO,
    "updated_at": START_ISO,
    "build": BUILD,
    "package_type": PACKAGE_TYPE,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "temporary_public_installer": {
        "host": "prontoo.app",
        "https_required": True,
        "start_unix": START_UNIX,
        "end_unix": END_UNIX,
        "start_utc": START_ISO,
        "end_utc": END_ISO,
        "start_local": START_LOCAL,
        "end_local": END_LOCAL,
        "automatic_revocation": True,
    },
})
version_path.write_text(json.dumps(version_data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

arch_path = ROOT / "app/architecture.manifest.json"
arch = json.loads(arch_path.read_text(encoding="utf-8"))
arch["version"] = VERSION
arch["installer_access_policy"] = "localhost_or_https_prontoo_app_during_fixed_four_hour_window_then_local_only"
arch["schema_mutation_policy"] = "frozen_except_authorized_installer_window_or_ci"
arch["temporary_public_installer_window"] = {
    "host": "prontoo.app",
    "https_required": True,
    "start_unix": START_UNIX,
    "end_unix": END_UNIX,
    "end_utc": END_ISO,
    "automatic_revocation": True,
}
arch_path.write_text(json.dumps(arch, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

contract_path = ROOT / "app/Database/operational-schema.contract.json"
contract = json.loads(contract_path.read_text(encoding="utf-8"))
contract["version"] = VERSION
contract_path.write_text(json.dumps(contract, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

changelog = read("ChangeLog.txt")
entry = f'''Prontoo 1.7.21.8 — liberação pública temporária e autoexpirável do instalador

- Libera `install.php` publicamente apenas no host HTTPS exato `prontoo.app`.
- Janela fixa: {START_LOCAL} até {END_LOCAL}; duração exata de 14.400 segundos.
- A revogação é automática e fail-closed no núcleo `InstallAccess`: a partir de {END_ISO}, requisições públicas recebem 404 sem depender de cron ou nova publicação.
- Mantém acesso local e execução CLI para instalação e certificação.
- Faz `SchemaMutationLock` reutilizar a mesma decisão autorizadora, impedindo DDL fora do instalador local, da janela pública ainda vigente ou do CI controlado.
- Acrescenta testes determinísticos para início, último segundo, expiração, tentativa anterior ao início, HTTP sem TLS e host incorreto.
- Não altera tabelas, colunas, índices, constraints, dados, interface ou `schema.sql`.

'''
write("ChangeLog.txt", entry + changelog)

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "package_type": PACKAGE_TYPE,
    "generated_at": START_ISO,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "installer_policy": "localhost_or_https_prontoo_app_until_2026-07-22T01:55:41Z_then_local_only",
    "schema_mutation_policy": "frozen_except_authorized_installer_window_or_ci",
    "previous_version": PREVIOUS_VERSION,
    "updated_at": START_ISO,
    "notes": "Libera temporariamente o instalador público por quatro horas no host HTTPS prontoo.app e revoga automaticamente por timestamp fixo.",
    "temporary_public_installer": {
        "host": "prontoo.app",
        "start_unix": START_UNIX,
        "end_unix": END_UNIX,
        "end_utc": END_ISO,
        "automatic_revocation": True,
    },
})
files = manifest.get("files", {})
total = 0
for relative in list(files):
    path = ROOT / relative
    if not path.is_file():
        raise RuntimeError(f"Arquivo do manifesto ausente: {relative}")
    payload = path.read_bytes()
    files[relative] = hashlib.sha256(payload).hexdigest()
    total += len(payload)
manifest["files"] = files
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

if hashlib.sha256((ROOT / "app/Database/schema.sql").read_bytes()).hexdigest() != SCHEMA_HASH:
    raise RuntimeError("schema.sql foi alterado")

print(json.dumps({
    "version": VERSION,
    "window_start_utc": START_ISO,
    "window_end_utc": END_ISO,
    "window_seconds": END_UNIX - START_UNIX,
    "schema_unchanged": True,
    "manifest_files": len(files),
}, ensure_ascii=False, indent=2))
