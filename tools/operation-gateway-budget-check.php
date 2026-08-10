<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$contract = json_decode(
    (string) file_get_contents($root . '/app/architecture.gateway-budget.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
if (!is_array($contract) || ($contract['schema'] ?? '') !== 'prontoo-operation-gateway-budget-v1') {
    throw new RuntimeException('Contrato do OperationGateway inválido.');
}
$maximum = (int) ($contract['max_invoke_calls'] ?? -1);
if ($maximum < 0) {
    throw new RuntimeException('Teto do OperationGateway inválido.');
}

$total = 0;
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    $count = substr_count($source, 'OperationGateway::invoke(');
    if ($count <= 0) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $files[$relative] = $count;
    $total += $count;
}
arsort($files, SORT_NUMERIC);
$result = [
    'ok' => $total <= $maximum,
    'policy' => (string) ($contract['policy'] ?? ''),
    'invoke_calls' => $total,
    'max_invoke_calls' => $maximum,
    'files_with_invocations' => count($files),
    'top_files' => array_slice($files, 0, 12, true),
];
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($result['ok'] ? 0 : 1);
