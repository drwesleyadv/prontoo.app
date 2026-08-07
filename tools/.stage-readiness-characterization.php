<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$path = dirname(__DIR__) . '/tools/architecture-check.php';
$source = (string) file_get_contents($path);
$legacy = <<<'PHP'
foreach ([
    "['route_deep', 'post_password_login']",
    "'reason' => 'runtime_marker_fresh'",
    "self::runMaintenanceCycle('post_password_login', \$uid)",
    '\\runtime_self_check();',
] as $token) {
    $assert(str_contains($bootSource, $token), 'boot_missing:' . $token);
}
PHP;
$replacement = <<<'PHP'
foreach ([
    "self::runReadinessCycle('route_readiness')",
    "self::runMaintenanceCycle('forced_deep')",
    "'reason' => 'runtime_readiness_fresh'",
    "self::runReadinessCycle('post_password_login', \$uid)",
    'readinessMarkerPath()',
    'markReadinessOk($mode)',
    '\\runtime_self_check();',
] as $token) {
    $assert(str_contains($bootSource, $token), 'boot_missing:' . $token);
}
foreach ([
    "self::runMaintenanceCycle('post_password_login', \$uid)",
    "['route_deep', 'post_password_login']",
] as $token) {
    $assert(!str_contains($bootSource, $token), 'boot_forbidden:' . $token);
}
PHP;
$count = substr_count($source, $legacy);
if ($count !== 1) {
    throw new RuntimeException('Bloco legado de caracterização do boot não foi localizado exatamente uma vez: ' . $count);
}
$updated = str_replace($legacy, $replacement, $source);
file_put_contents($path, $updated);
