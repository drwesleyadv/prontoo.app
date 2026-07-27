from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re

ROOT = Path('.')
VERSION = '1.7.27.7'
PREVIOUS = '1.7.27.6'
NOW = datetime.now(timezone.utc).replace(microsecond=0)
GENERATED_AT = NOW.isoformat().replace('+00:00', 'Z')
GENERATED_UNIX = int(NOW.timestamp())
SYNC_ID = 'hostoo-clean-install-commissioning-' + NOW.strftime('%Y%m%dT%H%M%SZ')


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding='utf-8')


# Runtime authorization: CI certification or one-time HTTPS commissioning.
path = 'app/Core/Install/InstallAccess.php'
source = read(path)
pattern = re.compile(
    r'    public static function isInstallerExecutionAllowed\(\?array \$server = null, \?int \$now = null\): bool\n'
    r'    \{.*?\n    \}\n\n    public static function assertInstallerEntry\(\): void',
    re.S,
)
replacement = '''    public static function isInstallerExecutionAllowed(?array $server = null, ?int $now = null): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::isInstallerExecutionAllowed
         * Responsabilidade: Autoriza somente a certificação CLI integralmente marcada ou, durante a release temporária 1.7.27.7, uma janela HTTP de comissionamento com HTTPS, host canônico, estado fresh e token externo de uso único.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.InstallAccess::assertInstallerEntry`, `tools/install-security-check.php`.
         * Dependências chamadas: `getenv`, `self::requestHostFrom`, `dirname`, `is_file`, `is_readable`, `file_get_contents`, `preg_match`, `hash_equals`.
         * Estado externo lido: `PHP_SAPI`, `$_SERVER`, `$_GET`, `$_POST` e o arquivo não versionado `ssd/install.token`.
         * Efeitos colaterais: nenhum; apenas produz uma decisão fail-closed.
         * Cuidado 1: A janela pública nunca pode funcionar sem HTTPS, host `prontoo.app`, ausência simultânea de `app/config.php` e `ssd/install.lock`, e token forte correspondente.
         * Cuidado 2: A certificação exige simultaneamente `GITHUB_ACTIONS=true`, `CI=true`, `PRONTOO_SCHEMA_TEST_MODE=1` e `PRONTOO_INSTALLER_CLI_MODE=1`.
         * Cuidado 3: Os campos `PRONTOO_TEST_*` são aceitos somente em CLI integralmente marcada para provas do CI.
         */
        $ciMarked = PHP_SAPI === 'cli' &&
            (string) getenv('GITHUB_ACTIONS') === 'true' &&
            (string) getenv('CI') === 'true' &&
            (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' &&
            (string) getenv('PRONTOO_INSTALLER_CLI_MODE') === '1';

        if ($server === null && PHP_SAPI === 'cli') {
            return $ciMarked;
        }
        if ($server !== null && !$ciMarked) {
            return false;
        }

        $request = $server ?? $_SERVER;
        $method = strtoupper(trim((string) ($request['REQUEST_METHOD'] ?? 'GET')));
        if (!in_array($method, ['GET', 'POST'], true)) {
            return false;
        }
        $https = strtolower(trim((string) ($request['HTTPS'] ?? '')));
        if (!in_array($https, ['on', '1'], true) &&
            (string) ($request['SERVER_PORT'] ?? '') !== '443') {
            return false;
        }
        if (self::requestHostFrom($request) !== 'prontoo.app') {
            return false;
        }

        $root = dirname(__DIR__, 3);
        if ($server !== null) {
            $candidateRoot = trim((string) ($request['PRONTOO_TEST_ROOT'] ?? ''));
            if ($candidateRoot === '') {
                return false;
            }
            $root = $candidateRoot;
        }
        if (is_file($root . '/app/config.php') ||
            is_file($root . '/ssd/install.lock')) {
            return false;
        }

        $tokenFile = $root . '/ssd/install.token';
        if (!is_file($tokenFile) || !is_readable($tokenFile)) {
            return false;
        }
        $expected = trim((string) @file_get_contents($tokenFile));
        if (!preg_match('/\\A[A-Za-z0-9_-]{48,128}\\z/', $expected)) {
            return false;
        }

        if ($server !== null) {
            $get = (array) ($request['PRONTOO_TEST_GET'] ?? []);
            $post = (array) ($request['PRONTOO_TEST_POST'] ?? []);
        } else {
            $get = $_GET;
            $post = $_POST;
        }
        $provided = '';
        foreach ([
            $request['HTTP_X_PRONTOO_INSTALL_TOKEN'] ?? '',
            $post['install_token'] ?? '',
            $get['token'] ?? '',
        ] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                $provided = $candidate;
                break;
            }
        }
        return $provided !== '' && hash_equals($expected, $provided);
    }

    public static function assertInstallerEntry(): void'''
source, count = pattern.subn(lambda _: replacement, source, count=1)
if count != 1:
    raise RuntimeError('Guarda de instalação não encontrada.')
source = source.replace(
    'Responsabilidade: Protege o primeiro ponto executável de install.php após o comissionamento e encerra qualquer acesso HTTP. Somente a certificação CLI integralmente marcada pode ultrapassar esta guarda.',
    'Responsabilidade: Protege o primeiro ponto executável de install.php. A release temporária 1.7.27.7 aceita somente a janela HTTPS com token e estado fresh; após configuração ou lock, somente a certificação CLI integralmente marcada permanece possível.',
)
source = source.replace(
    'Cuidado 2: Não crie bypass por localhost. O acesso ao instalador foi encerrado porque a instalação de produção já foi concluída.',
    'Cuidado 2: Não crie bypass por localhost, IP, relógio ou cabeçalhos encaminhados; a única janela HTTP autorizada é a validação explícita do token one-time em estado fresh.',
)
write(path, source)

# Apache/LiteSpeed delegates install.php to the runtime gate during this release.
path = '.htaccess'
source = read(path)
source, count = re.subn(
    r'# Instalação concluída e comissionada\.\n'
    r'# O instalador permanece no pacote apenas para rastreabilidade e certificação,\n'
    r'# mas não pode ser servido pelo Apache/LiteSpeed em produção\.\n'
    r'<Files "install\.php">\n\s*Require all denied\n</Files>\n',
    '# Janela temporária de comissionamento 1.7.27.7.\n'
    '# install.php permanece protegido no runtime por HTTPS, host canônico,\n'
    '# estado fresh e token one-time em ssd/install.token.\n',
    source,
    count=1,
)
if count != 1:
    raise RuntimeError('Bloqueio do install.php não encontrado no .htaccess.')
write(path, source)

# Preserve the token on POST and erase it after successful commissioning.
path = 'app/Install/Installer.php'
source = read(path)
old = '''    echo '<form method="post" class="compact">' .
        csrf_field() .
        '<h2>Banco de dados</h2><div class="two">' .'''
new = '''    echo '<form method="post" class="compact">' .
        csrf_field() .
        '<input type="hidden" name="install_token" value="' .
        h((string) ($_POST["install_token"] ?? $_GET["token"] ?? "")) .
        '">' .
        '<h2>Banco de dados</h2><div class="two">' .'''
if old not in source:
    raise RuntimeError('Formulário do instalador não encontrado.')
source = source.replace(old, new, 1)
anchor = '            prontoo_fs_chmod(storage_path("install.lock"), 0640);\n'
if anchor not in source:
    raise RuntimeError('Finalização do install.lock não encontrada.')
source = source.replace(
    anchor,
    anchor + '            prontoo_fs_unlink(storage_path("install.token"), false);\n',
    1,
)
write(path, source)

# Security regression checks for the temporary window.
path = 'tools/install-security-check.php'
source = read(path)
anchor = """if (!InstallAccess::isInstallerExecutionAllowed()) {
    $errors[] = 'fully_marked_github_actions_cli_denied';
}
"""
test = r'''

$temporaryRoot = sys_get_temp_dir() . '/prontoo-commissioning-' . getmypid();
@mkdir($temporaryRoot . '/app', 0700, true);
@mkdir($temporaryRoot . '/ssd', 0700, true);
$temporaryToken = str_repeat('a', 64);
file_put_contents($temporaryRoot . '/ssd/install.token', $temporaryToken, LOCK_EX);
$commissioningServer = [
    'REMOTE_ADDR' => '203.0.113.10',
    'HTTP_HOST' => 'prontoo.app',
    'HTTPS' => 'on',
    'SERVER_PORT' => '443',
    'REQUEST_METHOD' => 'GET',
    'PRONTOO_TEST_ROOT' => $temporaryRoot,
    'PRONTOO_TEST_GET' => ['token' => $temporaryToken],
];
if (!InstallAccess::isInstallerExecutionAllowed($commissioningServer)) {
    $errors[] = 'fresh_https_token_commissioning_denied';
}
$wrongTokenServer = $commissioningServer;
$wrongTokenServer['PRONTOO_TEST_GET'] = ['token' => str_repeat('b', 64)];
if (InstallAccess::isInstallerExecutionAllowed($wrongTokenServer)) {
    $errors[] = 'wrong_commissioning_token_allowed';
}
file_put_contents($temporaryRoot . '/app/config.php', '<?php return [];');
if (InstallAccess::isInstallerExecutionAllowed($commissioningServer)) {
    $errors[] = 'configured_commissioning_allowed';
}
@unlink($temporaryRoot . '/app/config.php');
file_put_contents($temporaryRoot . '/ssd/install.lock', 'installed');
if (InstallAccess::isInstallerExecutionAllowed($commissioningServer)) {
    $errors[] = 'locked_commissioning_allowed';
}
@unlink($temporaryRoot . '/ssd/install.lock');
@unlink($temporaryRoot . '/ssd/install.token');
@rmdir($temporaryRoot . '/app');
@rmdir($temporaryRoot . '/ssd');
@rmdir($temporaryRoot);
'''
if anchor not in source:
    raise RuntimeError('Âncora do teste de certificação não encontrada.')
source = source.replace(anchor, anchor + test, 1)
source, count = re.subn(
    r"if \(!preg_match\('/<Files\\s\+\"install\\\.php\">.*?\$errors\[\] = 'webserver_install_not_denied';\n\}",
    """if (preg_match('/<Files\\s+\"install\\.php\">\\s*(?:#[^\\n]*\\s*)*Require\\s+all\\s+denied\\s*<\\/Files>/s', $htaccess) ||
    !str_contains($htaccess, 'Janela temporária de comissionamento 1.7.27.7')) {
    $errors[] = 'webserver_commissioning_window_contract';
}""",
    source,
    count=1,
    flags=re.S,
)
if count != 1:
    raise RuntimeError('Contrato antigo do .htaccess não encontrado no teste.')
legacy = """foreach ([
    'TEMPORARY_PUBLIC_HOST',
    'TEMPORARY_PUBLIC_WINDOW_START_UNIX',
    'TEMPORARY_PUBLIC_WINDOW_END_UNIX',
] as $legacy) {
    if (str_contains($installAccessSource, $legacy)) {
        $errors[] = 'legacy_public_window_symbol:' . $legacy;
    }
}
"""
required = """foreach (['ssd/install.token', 'hash_equals', 'PRONTOO_TEST_ROOT'] as $requiredCommissioningContract) {
    if (!str_contains($installAccessSource, $requiredCommissioningContract)) {
        $errors[] = 'commissioning_contract_missing:' . $requiredCommissioningContract;
    }
}
"""
if legacy not in source:
    raise RuntimeError('Âncora de símbolos legados não encontrada.')
source = source.replace(legacy, legacy + required, 1)
write(path, source)

# Canonical PHP fallbacks.
path = 'app/prontoo.php'
source = read(path)
source = source.replace('const PRONTOO_VERSION_FALLBACK = "1.7.27.6";', 'const PRONTOO_VERSION_FALLBACK = "1.7.27.7";', 1)
source = source.replace('const PRONTOO_PREVIOUS_VERSION = "1.7.27.5";', 'const PRONTOO_PREVIOUS_VERSION = "1.7.27.6";', 1)
write(path, source)

path = 'br/index.php'
source = read(path)
source = source.replace('const BR_LANDING_VERSION_FALLBACK = "1.7.27.6";', 'const BR_LANDING_VERSION_FALLBACK = "1.7.27.7";', 1)
write(path, source)

# Changelog.
path = 'ChangeLog.txt'
source = read(path)
if not source.startswith('Prontoo 1.7.27.6'):
    raise RuntimeError('Topo inesperado do ChangeLog.txt.')
entry = '''Prontoo 1.7.27.7 — janela temporária para instalação limpa

- Abre `install.php` somente em HTTPS no host canônico, enquanto `app/config.php` e `ssd/install.lock` não existem e o token forte enviado corresponde a `ssd/install.token`.
- Mantém o token fora do Git e da área pública; após a instalação, grava `ssd/install.lock` e remove `ssd/install.token` para fechar definitivamente a janela.
- Preserva banco totalmente vazio, validações do ambiente, CSRF, criação transacional do Desenvolvedor, Guardião, schema e políticas de segurança.
- Acrescenta prova regressiva para token incorreto, ambiente configurado, instalação bloqueada e certificação CLI do GitHub Actions.
- Não altera interface operacional, schema, banco de dados ou regras de negócio da plataforma.
- Release temporária de comissionamento; após a instalação limpa, o alvo de regressão é a baseline 1.7.27.6.

'''
write(path, entry + source)

# Version contract.
path = ROOT / 'version.json'
data = json.loads(path.read_text(encoding='utf-8'))
data.update({
    'version': VERSION,
    'release': VERSION,
    'generated_at_unix': GENERATED_UNIX,
    'generated_at': GENERATED_AT,
    'updated_at': GENERATED_AT,
    'build': '1.7.27.7-clean-install-commissioning',
    'package_type': 'temporary_clean_install_commissioning',
    'database_changes': False,
    'schema_changes': False,
    'logic_changes': True,
    'visual_changes': False,
    'functional_equivalence_policy': 'runtime_business_security_permissions_financial_workflows_database_schema_and_assets_remain_equivalent_to_1_7_27_6_except_for_the_temporary_fresh_install_commissioning_gate',
    'notes': 'Abre uma janela temporária e tokenizada para instalação limpa em HTTPS; após o comissionamento, a aplicação deve regressar para 1.7.27.6.',
    'previous_version': PREVIOUS,
    'documentation_changes': True,
    'installer_locked': False,
    'installer_access_policy': 'temporary_https_canonical_host_fresh_state_one_time_token_then_install_lock',
    'deployment_sync_id': SYNC_ID,
    'deployment_sync_requested_at': GENERATED_AT,
    'baseline_source': PREVIOUS,
    'temporary_commissioning_release': True,
    'install_token_file': 'ssd/install.token',
    'rollback_target': PREVIOUS,
    'post_install_regression_required': True,
})
for key in ('full_baseline_rewrite', 'rewrite_scope'):
    data.pop(key, None)
path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

# Architecture contract.
path = ROOT / 'app/architecture.manifest.json'
data = json.loads(path.read_text(encoding='utf-8'))
data.update({
    'version': VERSION,
    'database_changes': False,
    'schema_changes': False,
    'visual_changes': False,
    'logic_changes': True,
    'generated_at': GENERATED_AT,
    'updated_at': GENERATED_AT,
    'baseline_source': PREVIOUS,
    'clean_install_policy': 'fresh_zero_table_database_only_no_existing_database_drop',
    'installer_access_policy': 'temporary_https_canonical_host_fresh_state_one_time_token_then_install_lock',
    'temporary_commissioning_release': True,
    'install_token_file': 'ssd/install.token',
    'rollback_target': PREVIOUS,
    'post_install_regression_required': True,
})
data.pop('full_baseline_rewrite', None)
lockdown = dict(data.get('commissioned_installation_lockdown') or {})
lockdown.update({
    'http': 'temporary_token_window_only_before_config_and_install_lock_then_denied',
    'runtime_redirect': False,
    'localhost_bypass': False,
    'public_window': 'one_time_external_token_file_on_https_canonical_host',
    'schema_mutation': 'installer_window_or_github-actions-cli-four-markers-only',
})
data['commissioned_installation_lockdown'] = lockdown
path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

# Release manifest metadata and hashes.
path = ROOT / 'app/update.manifest.json'
data = json.loads(path.read_text(encoding='utf-8'))
data.update({
    'version': VERSION,
    'release': VERSION,
    'build': '1.7.27.7-clean-install-commissioning',
    'package_type': 'temporary_clean_install_commissioning',
    'generated_at': GENERATED_AT,
    'database_changes': False,
    'schema_changes': False,
    'logic_changes': True,
    'visual_changes': False,
    'documentation_changes': True,
    'installer_policy': 'temporary_https_canonical_host_fresh_state_one_time_token_then_install_lock',
    'previous_version': PREVIOUS,
    'updated_at': GENERATED_AT,
    'notes': 'Abre uma janela temporária e tokenizada para instalação limpa em HTTPS; após o comissionamento, a aplicação deve regressar para 1.7.27.6.',
    'deployment_sync_id': SYNC_ID,
    'deployment_sync_requested_at': GENERATED_AT,
    'baseline_source': PREVIOUS,
    'temporary_commissioning_release': True,
    'install_token_file': 'ssd/install.token',
    'rollback_target': PREVIOUS,
    'post_install_regression_required': True,
})
for key in ('full_baseline_rewrite', 'rewrite_scope'):
    data.pop(key, None)

# Remove generator artifacts before release hashing.
for transient in (
    ROOT / '.github/workflows/prepare-clean-install-1-7-27-7.yml',
    ROOT / 'tools/prepare-clean-install-1-7-27-7.py',
):
    if transient.exists():
        transient.unlink()

files = dict(data.get('files') or {})
for filename in files:
    file_path = ROOT / filename
    if not file_path.is_file():
        raise RuntimeError(f'Arquivo do manifesto ausente: {filename}')
    files[filename] = hashlib.sha256(file_path.read_bytes()).hexdigest()
data['files'] = files
data['file_count'] = len(files)
data['total_uncompressed_bytes'] = sum((ROOT / filename).stat().st_size for filename in files)
path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
