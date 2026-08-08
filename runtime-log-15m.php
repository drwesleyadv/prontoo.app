<?php
declare(strict_types=1);

const PRONTOO_RUNTIME_LOG_PUBLIC_EXPIRES_AT = 1786149071;

if (time() > PRONTOO_RUNTIME_LOG_PUBLIC_EXPIRES_AT) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo "Not found\n";
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$root = __DIR__;
$home = dirname($root);
$configured = trim((string) ini_get('error_log'));
$candidates = [];
if ($configured !== '') {
    $candidates[] = $configured;
    if (!str_starts_with($configured, '/')) {
        $candidates[] = $root . '/' . $configured;
    }
}
foreach ([
    $root . '/runtime.log',
    $root . '/app/runtime.log',
    $root . '/logs/runtime.log',
    $root . '/ssd/runtime.log',
    $root . '/ssd/logs/runtime.log',
    $root . '/storage/runtime.log',
    $home . '/runtime.log',
    $home . '/logs/runtime.log',
    $home . '/logs/php_error.log',
    $home . '/logs/error_log',
] as $candidate) {
    $candidates[] = $candidate;
}
foreach ([
    $root . '/*.log',
    $root . '/logs/*.log',
    $root . '/app/*.log',
    $root . '/ssd/*.log',
    $root . '/ssd/logs/*.log',
    $home . '/*.log',
    $home . '/logs/*.log',
] as $pattern) {
    foreach ((glob($pattern) ?: []) as $candidate) {
        $candidates[] = $candidate;
    }
}

$selected = null;
$readable = [];
foreach (array_values(array_unique($candidates)) as $candidate) {
    if (is_file($candidate) && is_readable($candidate)) {
        $readable[] = $candidate;
        if ($selected === null && (basename($candidate) === 'runtime.log' || str_contains(strtolower(basename($candidate)), 'runtime'))) {
            $selected = $candidate;
        }
    }
}
if ($selected === null && $readable !== []) {
    usort($readable, static fn(string $a, string $b): int => ((int) @filemtime($b)) <=> ((int) @filemtime($a)));
    $selected = $readable[0];
}

if ($selected === null) {
    echo "runtime_log=[none]\n";
    echo 'configured=' . ($configured !== '' ? basename($configured) : '[empty]') . "\n";
    echo "readable_logs=0\n";
    exit;
}

$size = (int) (@filesize($selected) ?: 0);
$maxBytes = 786432;
$offset = max(0, $size - $maxBytes);
$handle = @fopen($selected, 'rb');
if ($handle === false) {
    http_response_code(500);
    echo "runtime_log=[unreadable]\n";
    exit;
}
if ($offset > 0) {
    @fseek($handle, $offset);
    @fgets($handle);
}
$raw = stream_get_contents($handle);
fclose($handle);
$raw = is_string($raw) ? $raw : '';

$zone = new DateTimeZone('America/Cuiaba');
$todayLocal = (new DateTimeImmutable('now', $zone))->format('Y-m-d');
$lines = preg_split('/\R/', $raw) ?: [];
$today = [];
$includeContinuation = false;
foreach ($lines as $line) {
    $stamp = null;
    if (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2} UTC)\]/', $line, $m) === 1) {
        $stamp = DateTimeImmutable::createFromFormat('d-M-Y H:i:s T', $m[1], new DateTimeZone('UTC')) ?: null;
    } elseif (preg_match('/\[(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})\]/', $line, $m) === 1) {
        try {
            $stamp = new DateTimeImmutable($m[1]);
        } catch (Throwable) {
            $stamp = null;
        }
    }
    if ($stamp instanceof DateTimeImmutable) {
        $includeContinuation = $stamp->setTimezone($zone)->format('Y-m-d') === $todayLocal;
    }
    if ($includeContinuation) {
        $today[] = $line;
    }
}
$raw = implode("\n", $today);

$raw = preg_replace('/([?&](?:token|password|passwd|secret|key|api_key|authorization)=)[^&\s]+/i', '$1[REDACTED]', $raw) ?? $raw;
$raw = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/-]+=*/i', '$1[REDACTED]', $raw) ?? $raw;
$raw = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[EMAIL]', $raw) ?? $raw;
$raw = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[IP]', $raw) ?? $raw;
$raw = preg_replace('/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/', '[CPF]', $raw) ?? $raw;
$raw = str_replace([$root, $home], ['[ROOT]', '[HOME]'], $raw);

echo 'expires_utc=' . gmdate('c', PRONTOO_RUNTIME_LOG_PUBLIC_EXPIRES_AT) . "\n";
echo 'runtime_log=' . basename($selected) . "\n";
echo 'size=' . $size . "\n";
echo 'readable_logs=' . count($readable) . "\n";
echo 'local_date=' . $todayLocal . "\n";
echo 'today_lines=' . count($today) . "\n";
echo "----- today sanitized -----\n";
echo $raw . "\n";
