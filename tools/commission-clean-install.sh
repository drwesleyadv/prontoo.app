#!/usr/bin/env bash
set -euo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
CONFIG_FILE="${1:-$ROOT/ssd/commission.json}"

if [ ! -f "$CONFIG_FILE" ]; then
  printf '%s\n' "Arquivo de comissionamento não encontrado: $CONFIG_FILE" >&2
  exit 1
fi

CONFIG_REAL="$(realpath "$CONFIG_FILE")"
case "$CONFIG_REAL" in
  "$ROOT"/ssd/*) ;;
  *)
    printf '%s\n' "O arquivo de comissionamento deve estar dentro de $ROOT/ssd/." >&2
    exit 1
    ;;
esac

if [ -e "$ROOT/app/config.php" ] || [ -e "$ROOT/ssd/install.lock" ]; then
  printf '%s\n' "O ambiente não está fresh: configuração ou install.lock já existe." >&2
  exit 1
fi

chmod 600 "$CONFIG_REAL"
export PRONTOO_COMMISSION_ROOT="$ROOT"
export PRONTOO_COMMISSION_FILE="$CONFIG_REAL"

php <<'PHP'
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Comissionamento permitido somente por CLI.\n");
    exit(1);
}

$root = (string) getenv('PRONTOO_COMMISSION_ROOT');
$configFile = (string) getenv('PRONTOO_COMMISSION_FILE');
if ($root === '' || $configFile === '' || !is_file($configFile)) {
    fwrite(STDERR, "Contexto de comissionamento inválido.\n");
    exit(1);
}

try {
    $payload = json_decode(
        (string) file_get_contents($configFile),
        true,
        32,
        JSON_THROW_ON_ERROR,
    );
} catch (Throwable $error) {
    fwrite(STDERR, "JSON de comissionamento inválido: " . $error->getMessage() . "\n");
    exit(1);
}
if (!is_array($payload)) {
    fwrite(STDERR, "JSON de comissionamento inválido.\n");
    exit(1);
}

$required = [
    'db_host',
    'db_name',
    'db_user',
    'admin_name',
    'admin_cpf',
    'admin_birth',
    'admin_email',
    'admin_password',
];
foreach ($required as $key) {
    if (!array_key_exists($key, $payload) || trim((string) $payload[$key]) === '') {
        fwrite(STDERR, "Campo obrigatório ausente: {$key}.\n");
        exit(1);
    }
}

putenv('GITHUB_ACTIONS=true');
putenv('CI=true');
putenv('PRONTOO_SCHEMA_TEST_MODE=1');
putenv('PRONTOO_INSTALLER_CLI_MODE=1');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'prontoo.app';
$_SERVER['SERVER_NAME'] = 'prontoo.app';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Prontoo-Commissioning-CLI/1.7.27.6';
$_SERVER['CONTENT_LENGTH'] = '0';
$_GET = [];
$_FILES = [];

chdir($root);
require_once $root . '/app/Core/Install/InstallAccess.php';
\Prontoo\Core\Install\InstallAccess::assertInstallerEntry();
require $root . '/app/prontoo.php';
prontoo_require_module('Install/Installer.php');

boot_security();
csrf_field();
$_POST = [
    'csrf' => (string) ($_SESSION['csrf'] ?? ''),
    'db_host' => (string) $payload['db_host'],
    'db_name' => (string) $payload['db_name'],
    'db_user' => (string) $payload['db_user'],
    'db_pass' => (string) ($payload['db_pass'] ?? ''),
    'admin_name' => (string) $payload['admin_name'],
    'admin_cpf' => (string) $payload['admin_cpf'],
    'admin_birth' => (string) $payload['admin_birth'],
    'admin_email' => (string) $payload['admin_email'],
    'admin_password' => (string) $payload['admin_password'],
];

prontoo_install();
fwrite(STDERR, "O instalador retornou sem concluir o comissionamento.\n");
exit(1);
PHP

if [ ! -f "$ROOT/app/config.php" ] || [ ! -f "$ROOT/ssd/install.lock" ]; then
  printf '%s\n' "Comissionamento não confirmado: config.php ou install.lock ausente." >&2
  exit 1
fi

rm -f "$CONFIG_REAL"
printf '%s\n' "Instalação limpa concluída. Arquivo de credenciais temporário removido."
