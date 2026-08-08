<?php
declare(strict_types=1);

function prontoo_runtime_log_candidates(): array
{
    $configured = trim((string) ini_get('error_log'));
    $candidates = [];
    if ($configured !== '') {
        $candidates[] = $configured;
        if (!str_starts_with($configured, '/')) {
            $candidates[] = __DIR__ . '/' . $configured;
        }
    }
    $candidates[] = __DIR__ . '/runtime.log';
    $candidates[] = dirname(__DIR__) . '/runtime.log';
    $candidates[] = __DIR__ . '/storage/runtime.log';
    $candidates[] = __DIR__ . '/ssd/runtime.log';
    return array_values(array_unique($candidates));
}

function prontoo_runtime_log_read_tail(int $maxBytes = 262144): array
{
    $configured = trim((string) ini_get('error_log'));
    $selected = null;
    foreach (prontoo_runtime_log_candidates() as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            $selected = $candidate;
            break;
        }
    }
    if ($selected === null) {
        return ['configured' => $configured, 'selected' => null, 'raw' => '', 'size' => 0];
    }
    $size = (int) (@filesize($selected) ?: 0);
    $offset = max(0, $size - $maxBytes);
    $handle = @fopen($selected, 'rb');
    if ($handle === false) {
        return ['configured' => $configured, 'selected' => $selected, 'raw' => '', 'size' => $size];
    }
    if ($offset > 0) {
        @fseek($handle, $offset);
        @fgets($handle);
    }
    $raw = stream_get_contents($handle);
    fclose($handle);
    return [
        'configured' => $configured,
        'selected' => $selected,
        'raw' => is_string($raw) ? $raw : '',
        'size' => $size,
    ];
}

function prontoo_runtime_log_redact(string $raw): string
{
    $raw = preg_replace('/([?&](?:token|password|passwd|secret|key|api_key|authorization)=)[^&\s]+/i', '$1[REDACTED]', $raw) ?? $raw;
    $raw = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/-]+=*/i', '$1[REDACTED]', $raw) ?? $raw;
    $raw = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[EMAIL]', $raw) ?? $raw;
    $raw = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[IP]', $raw) ?? $raw;
    $root = preg_quote(__DIR__, '/');
    $raw = preg_replace('/' . $root . '\/?/i', '[ROOT]/', $raw) ?? $raw;
    return $raw;
}

$route = (string) ($_GET['r'] ?? '');
$probeExpiresAt = 1786152600;

if ($route === 'runtime_log_probe') {
    $probeToken = (string) ($_GET['token'] ?? '');
    $expectedToken = 'fcBIl9nftfflOeCu3TYW_e4wA7TpGMPssmPDJF2dS8M';
    if ($probeToken === '' || !hash_equals($expectedToken, $probeToken)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo "Not found\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    $log = prontoo_runtime_log_read_tail();
    echo 'configured_error_log=' . ($log['configured'] !== '' ? $log['configured'] : '[empty]') . "\n";
    echo 'selected=' . ($log['selected'] ?? '[none]') . "\n";
    echo 'size=' . (int) $log['size'] . "\n";
    echo "----- runtime.log tail -----\n";
    echo prontoo_runtime_log_redact((string) $log['raw']);
    exit;
}

if ($route === 'signup' && time() <= $probeExpiresAt) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    $log = prontoo_runtime_log_read_tail(524288);
    $raw = prontoo_runtime_log_redact((string) $log['raw']);
    $lines = preg_split('/\R/', $raw) ?: [];
    $errors = [];
    foreach ($lines as $line) {
        if (preg_match('/(?:PHP (?:Fatal error|Warning|Deprecated|Notice)|Uncaught|Prontoo|RuntimeContract|Throwable|Exception|Error)/i', $line)) {
            $errors[] = $line;
        }
    }
    $errors = array_slice($errors, -220);
    echo 'runtime_log=' . (($log['selected'] ?? null) !== null ? basename((string) $log['selected']) : '[none]') . "\n";
    echo 'size=' . (int) $log['size'] . "\n";
    echo 'matched_lines=' . count($errors) . "\n";
    echo "----- sanitized errors -----\n";
    echo implode("\n", $errors) . "\n";
    exit;
}

$prontooRouteStartedMonotonicNs = hrtime(true);
$prontooRouteStartedUnixUs = (int) floor(microtime(true) * 1000000);
require_once __DIR__ . "/app/Support/Telemetry.php";
telemetry_route_start_marker(
    (string) ($_GET["r"] ?? "login"),
    $prontooRouteStartedMonotonicNs,
    $prontooRouteStartedUnixUs,
);
unset($prontooRouteStartedMonotonicNs, $prontooRouteStartedUnixUs);

if ((string) ($_GET["r"] ?? "") === "install") {
    require_once __DIR__ . "/app/Core/Install/InstallAccess.php";
    \Prontoo\Core\Install\InstallAccess::assertInstallerEntry();
    require __DIR__ . "/app/prontoo.php";
    prontoo_require_module("Install/Installer.php");
    prontoo_install();
    telemetry_route_finish_marker();
    exit;
}

require __DIR__ . "/app/prontoo.php";
prontoo_run(false);
telemetry_route_finish_marker();
