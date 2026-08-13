<?php
declare(strict_types=1);

function canonical_source(string $root, string|array $relative): string
{
    $paths = is_array($relative) ? $relative : [$relative];
    $chunks = [];
    foreach ($paths as $path) {
        $path = mb_ltrim(str_replace('\\', '/', (string) $path), '/');
        $file = mb_rtrim($root, '/') . '/' . $path;
        if ($path === '' || !is_file($file)) {
            throw new RuntimeException('Fonte canônica ausente: ' . $path);
        }
        $chunks[] = (string) file_get_contents($file);
    }
    return implode("\n", $chunks);
}
