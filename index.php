<?php
declare(strict_types=1);

if ((string) ($_GET['r'] ?? '') === 'runtime_log_probe') {
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

    $selected = null;
    foreach (array_values(array_unique($candidates)) as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            $selected = $candidate;
            break;
        }
    }

    echo "configured_error_log=" . ($configured !== '' ? $configured : '[empty]') . "\n";
    if ($selected === null) {
        echo "selected=[none]\n";
        echo "candidates:\n";
        foreach (array_values(array_unique($candidates)) as $candidate) {
            echo '- ' . $candidate . ' exists=' . (is_file($candidate) ? 'yes' : 'no') . ' readable=' . (is_readable($candidate) ? 'yes' : 'no') . "\n";
        }
        exit;
    }

    $size = (int) (@filesize($selected) ?: 0);
    $maxBytes = 262144;
    $offset = max(0, $size - $maxBytes);
    $handle = @fopen($selected, 'rb');
    if ($handle === false) {
        http_response_code(500);
        echo "selected=" . $selected . "\nread_failed=yes\n";
        exit;
    }
    if ($offset > 0) {
        @fseek($handle, $offset);
        @fgets($handle);
    }
    $raw = stream_get_contents($handle);
    fclose($handle);
    if (!is_string($raw)) {
        $raw = '';
    }

    $raw = preg_replace('/([?&](?:token|password|passwd|secret|key|api_key|authorization)=)[^&\s]+/i', '$1[REDACTED]', $raw) ?? $raw;
    $raw = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/-]+=*/i', '$1[REDACTED]', $raw) ?? $raw;

    echo "selected=" . $selected . "\n";
    echo "size=" . $size . "\n";
    echo "tail_bytes=" . strlen($raw) . "\n";
    echo "----- runtime.log tail -----\n";
    echo $raw;
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
