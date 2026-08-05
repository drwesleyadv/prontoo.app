<?php
declare(strict_types=1);

const PRONTOO_INFO_ACCESS_KEY = '0072545af6696485b38fd8f1abd27614095f2c262f8c2eb3';

function info_fail(int $status = 404): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
    echo "Not found\n";
    exit;
}

$host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
$host = preg_replace('/:\d+$/', '', $host) ?? '';
$key = (string) ($_GET['key'] ?? '');

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' ||
    $host !== 'prontoo.app' ||
    !hash_equals(PRONTOO_INFO_ACCESS_KEY, $key)
) {
    info_fail();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

function info_disabled_functions(): array
{
    $raw = trim((string) ini_get('disable_functions'));
    if ($raw === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

function info_exec_available(): bool
{
    return function_exists('exec') && !in_array('exec', info_disabled_functions(), true);
}

function info_cli_probe(string $path): array
{
    $result = [
        'path' => $path,
        'realpath' => realpath($path) ?: null,
        'exists' => is_file($path),
        'executable' => is_executable($path),
        'tested' => false,
        'exit_code' => null,
        'version' => null,
        'version_id' => null,
        'sapi' => null,
        'binary' => null,
        'ini' => null,
        'compatible_with_prontoo' => false,
        'error' => null,
    ];

    if (!$result['exists'] || !$result['executable']) {
        return $result;
    }
    if (!info_exec_available()) {
        $result['error'] = 'exec indisponível no PHP web';
        return $result;
    }

    $probe = <<<'PHP_PROBE'
echo json_encode([
    'version' => PHP_VERSION,
    'version_id' => PHP_VERSION_ID,
    'sapi' => PHP_SAPI,
    'binary' => PHP_BINARY,
    'ini' => php_ini_loaded_file() ?: null,
], JSON_UNESCAPED_SLASHES);
PHP_PROBE;

    $output = [];
    $exitCode = 1;
    $command = escapeshellarg($path) .
        ' -d display_errors=0 -r ' .
        escapeshellarg($probe) .
        ' 2>&1';

    @exec($command, $output, $exitCode);
    $result['tested'] = true;
    $result['exit_code'] = $exitCode;

    $decoded = null;
    foreach (array_reverse(array_slice($output, -10)) as $line) {
        $candidate = json_decode(trim((string) $line), true);
        if (is_array($candidate)) {
            $decoded = $candidate;
            break;
        }
    }

    if (!is_array($decoded)) {
        $result['error'] = mb_substr(trim(implode("\n", $output)), 0, 500) ?: 'sem saída válida';
        return $result;
    }

    $result['version'] = (string) ($decoded['version'] ?? '');
    $result['version_id'] = (int) ($decoded['version_id'] ?? 0);
    $result['sapi'] = (string) ($decoded['sapi'] ?? '');
    $result['binary'] = (string) ($decoded['binary'] ?? '');
    $result['ini'] = isset($decoded['ini']) ? (string) $decoded['ini'] : null;
    $result['compatible_with_prontoo'] =
        $exitCode === 0 &&
        $result['sapi'] === 'cli' &&
        version_compare($result['version'], '8.4.0', '>=');

    return $result;
}

$candidates = [
    PHP_BINARY,
    PHP_BINDIR . '/php',
    '/usr/local/bin/php',
    '/usr/bin/php',
    '/opt/cpanel/ea-php84/root/usr/bin/php',
    '/usr/local/lsws/lsphp84/bin/php',
    '/usr/local/lsws/lsphp84/bin/lsphp',
    '/usr/local/php84/bin/php',
    '/opt/php84/bin/php',
];

$pathValue = trim((string) getenv('PATH'));
if ($pathValue !== '') {
    foreach (explode(PATH_SEPARATOR, $pathValue) as $dir) {
        $dir = rtrim(trim($dir), '/');
        if ($dir === '') {
            continue;
        }
        foreach (['php', 'php84', 'php8.4', 'ea-php84', 'lsphp', 'lsphp84'] as $name) {
            $candidates[] = $dir . '/' . $name;
        }
    }
}

$candidates = array_values(array_unique(array_filter(
    $candidates,
    static fn(mixed $path): bool => is_string($path) && str_starts_with($path, '/'),
)));

$probes = array_map('info_cli_probe', $candidates);
$compatible = array_values(array_filter(
    $probes,
    static fn(array $probe): bool => !empty($probe['compatible_with_prontoo']),
));

$recommended = $compatible[0]['realpath'] ?? ($compatible[0]['path'] ?? null);
$cronScript = __DIR__ . '/cron/maestro.php';
$cronLog = __DIR__ . '/ssd/logs/maestro-cron.log';
$recommendedCommand = is_string($recommended) && $recommended !== ''
    ? escapeshellarg($recommended) . ' ' . escapeshellarg($cronScript) .
        ' >> ' . escapeshellarg($cronLog) . ' 2>&1'
    : null;

$payload = [
    'ok' => true,
    'generated_at_utc' => gmdate('c'),
    'purpose' => 'Diagnóstico restrito do PHP web e dos executáveis PHP CLI disponíveis para o cron do Maestro.',
    'web_runtime' => [
        'version' => PHP_VERSION,
        'version_id' => PHP_VERSION_ID,
        'sapi' => PHP_SAPI,
        'binary' => PHP_BINARY,
        'bindir' => PHP_BINDIR,
        'loaded_ini' => php_ini_loaded_file() ?: null,
        'scanned_ini' => php_ini_scanned_files() ?: null,
        'server_software' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''),
        'repository_root' => __DIR__,
    ],
    'probe_capability' => [
        'exec_available' => info_exec_available(),
        'disabled_functions' => info_disabled_functions(),
        'path_present' => $pathValue !== '',
    ],
    'cli_candidates' => $probes,
    'compatible_cli_candidates' => $compatible,
    'recommended_cron_command' => $recommendedCommand,
    'current_command_candidate' => array_values(array_filter(
        $probes,
        static fn(array $probe): bool => $probe['path'] === '/usr/local/bin/php',
    ))[0] ?? null,
    'next_step' => $recommendedCommand !== null
        ? 'Use recommended_cron_command no painel da Hostoo e remova info.php após a confirmação.'
        : 'Nenhum PHP CLI 8.4 compatível foi confirmado. Consulte a Hostoo sobre o caminho absoluto do PHP 8.4 CLI.',
];

echo json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_PRETTY_PRINT |
    JSON_INVALID_UTF8_SUBSTITUTE,
) . "\n";
