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
$configured = trim((string) ini_get('error_log'));
$candidates = [];
if ($configured !== '') {
    $candidates[] = $configured;
    if (!str_starts_with($configured, '/')) {
        $candidates[] = $root . '/' . $configured;
    }
}
$candidates[] = $root . '/runtime.log';
$candidates[] = $root . '/ssd/runtime.log';
$candidates[] = $root . '/storage/runtime.log';

$selected = null;
foreach (array_values(array_unique($candidates)) as $candidate) {
    if (is_file($candidate) && is_readable($candidate)) {
        $selected = $candidate;
        break;
    }
}

if ($selected === null) {
    echo "runtime_log=[none]\n";
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

$todayUtc = gmdate('d-M-Y');
$todayIso = gmdate('Y-m-d');
$lines = preg_split('/\R/', $raw) ?: [];
$today = [];
foreach ($lines as $line) {
    if (str_contains($line, $todayUtc) || str_contains($line, $todayIso)) {
        $today[] = $line;
    }
}
$raw = implode("\n", $today);

$raw = preg_replace('/([?&](?:token|password|passwd|secret|key|api_key|authorization)=)[^&\s]+/i', '$1[REDACTED]', $raw) ?? $raw;
$raw = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/-]+=*/i', '$1[REDACTED]', $raw) ?? $raw;
$raw = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[EMAIL]', $raw) ?? $raw;
$raw = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[IP]', $raw) ?? $raw;
$raw = preg_replace('/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/', '[CPF]', $raw) ?? $raw;
$raw = str_replace($root, '[ROOT]', $raw);

echo 'expires_utc=' . gmdate('c', PRONTOO_RUNTIME_LOG_PUBLIC_EXPIRES_AT) . "\n";
echo 'runtime_log=' . basename($selected) . "\n";
echo 'size=' . $size . "\n";
echo 'today_lines=' . count($today) . "\n";
echo "----- today sanitized -----\n";
echo $raw . "\n";
