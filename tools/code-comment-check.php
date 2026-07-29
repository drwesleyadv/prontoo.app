<?php
declare(strict_types=1);

function cc_blank(string $value): string
{
    return str_repeat("\n", substr_count($value, "\n"));
}

function cc_c_like(string $source): string
{
    $length = strlen($source);
    $out = '';
    $state = 'normal';
    for ($i = 0; $i < $length; $i++) {
        $c = $source[$i];
        $n = $i + 1 < $length ? $source[$i + 1] : '';
        if ($state === 'line') {
            if ($c === "\n") {
                $out .= "\n";
                $state = 'normal';
            }
            continue;
        }
        if ($state === 'block') {
            if ($c === '*' && $n === '/') {
                $i++;
                $state = 'normal';
                continue;
            }
            if ($c === "\n") {
                $out .= "\n";
            }
            continue;
        }
        if ($state === 'single' || $state === 'double' || $state === 'backtick') {
            $out .= $c;
            if ($c === '\\' && $i + 1 < $length) {
                $out .= $source[++$i];
                continue;
            }
            $expected = $state === 'single' ? "'" : ($state === 'double' ? '"' : '`');
            if ($c === $expected) {
                $state = 'normal';
            }
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $out .= $c;
            $state = $c === "'" ? 'single' : ($c === '"' ? 'double' : 'backtick');
            continue;
        }
        if ($c === '/' && $n === '/') {
            $i++;
            $state = 'line';
            continue;
        }
        if ($c === '/' && $n === '*') {
            $i++;
            $state = 'block';
            continue;
        }
        $out .= $c;
    }
    return $out;
}

function cc_html(string $source): string
{
    $source = preg_replace_callback(
        '/<!--.*?-->/s',
        static fn(array $match): string => cc_blank($match[0]),
        $source,
    ) ?? $source;
    foreach (['script', 'style'] as $tag) {
        $pattern = '~(<' . $tag . '\b[^>]*>)(.*?)(</' . $tag . '\s*>)~is';
        $source = preg_replace_callback(
            $pattern,
            static fn(array $match): string => $match[1] . cc_c_like($match[2]) . $match[3],
            $source,
        ) ?? $source;
    }
    return $source;
}

function cc_php(string $source): string
{
    $out = '';
    foreach (token_get_all($source) as $token) {
        if (!is_array($token)) {
            $out .= $token;
            continue;
        }
        [$id, $text] = $token;
        if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
            $out .= cc_blank($text);
            continue;
        }
        $out .= $id === T_INLINE_HTML ? cc_html($text) : $text;
    }
    return $out;
}

function cc_hash(string $source, bool $preserveShebang): string
{
    $lines = preg_split('/(?<=\n)/', $source) ?: [];
    $out = '';
    foreach ($lines as $index => $line) {
        if ($preserveShebang && $index === 0 && str_starts_with($line, '#!')) {
            $out .= $line;
            continue;
        }
        $length = strlen($line);
        $single = false;
        $double = false;
        $escaped = false;
        $cut = null;
        for ($i = 0; $i < $length; $i++) {
            $c = $line[$i];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($c === '\\' && !$single) {
                $escaped = true;
                continue;
            }
            if ($c === "'" && !$double) {
                $single = !$single;
                continue;
            }
            if ($c === '"' && !$single) {
                $double = !$double;
                continue;
            }
            if ($c === '#' && !$single && !$double) {
                $previous = $i === 0 ? '' : $line[$i - 1];
                if ($i === 0 || ctype_space($previous)) {
                    $cut = $i;
                    break;
                }
            }
        }
        if ($cut === null) {
            $out .= $line;
            continue;
        }
        $prefix = rtrim(substr($line, 0, $cut), " \t");
        $out .= $prefix;
        if (str_ends_with($line, "\n")) {
            $out .= "\n";
        }
    }
    return $out;
}

function cc_sql(string $source): string
{
    $length = strlen($source);
    $out = '';
    $state = 'normal';
    for ($i = 0; $i < $length; $i++) {
        $c = $source[$i];
        $n = $i + 1 < $length ? $source[$i + 1] : '';
        if ($state === 'line') {
            if ($c === "\n") {
                $out .= "\n";
                $state = 'normal';
            }
            continue;
        }
        if ($state === 'block') {
            if ($c === '*' && $n === '/') {
                $i++;
                $state = 'normal';
                continue;
            }
            if ($c === "\n") {
                $out .= "\n";
            }
            continue;
        }
        if ($state === 'single' || $state === 'double' || $state === 'backtick') {
            $out .= $c;
            if ($c === '\\' && $i + 1 < $length) {
                $out .= $source[++$i];
                continue;
            }
            $expected = $state === 'single' ? "'" : ($state === 'double' ? '"' : '`');
            if ($c === $expected) {
                if (($state === 'single' || $state === 'double') && $n === $expected) {
                    $out .= $source[++$i];
                    continue;
                }
                $state = 'normal';
            }
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $out .= $c;
            $state = $c === "'" ? 'single' : ($c === '"' ? 'double' : 'backtick');
            continue;
        }
        if ($c === '/' && $n === '*') {
            $i++;
            $state = 'block';
            continue;
        }
        if ($c === '-' && $n === '-' && ($i + 2 >= $length || ctype_space($source[$i + 2]))) {
            $i++;
            $state = 'line';
            continue;
        }
        if ($c === '#') {
            $state = 'line';
            continue;
        }
        $out .= $c;
    }
    return $out;
}

function cc_transform(string $path, string $source): string
{
    $name = basename($path);
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($extension, ['php', 'phtml', 'inc'], true)) {
        return cc_php($source);
    }
    if (in_array($extension, ['js', 'mjs', 'cjs', 'css'], true)) {
        return cc_c_like($source);
    }
    if (in_array($extension, ['html', 'htm', 'xml'], true)) {
        return cc_html($source);
    }
    if ($extension === 'sql') {
        return cc_sql($source);
    }
    if (in_array($extension, ['yml', 'yaml'], true)) {
        return cc_hash($source, false);
    }
    if (in_array($extension, ['sh', 'bash'], true) || $name === '.htaccess') {
        return cc_hash($source, true);
    }
    return $source;
}

$root = dirname(__DIR__);
$excluded = ['/.git/', '/ssd/', '/vendor/', '/node_modules/', '/docs/'];
$violations = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    $normalized = '/' . ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
    $skip = false;
    foreach ($excluded as $fragment) {
        if (str_contains($normalized, $fragment)) {
            $skip = true;
            break;
        }
    }
    if ($skip || $normalized === '/tools/code-comment-check.php') {
        continue;
    }
    $source = (string) file_get_contents($path);
    if (cc_transform($normalized, $source) !== $source) {
        $violations[] = ltrim($normalized, '/');
    }
}
if ($violations !== []) {
    fwrite(STDERR, "Comentários de código encontrados:\n" . implode("\n", $violations) . "\n");
    exit(1);
}
echo json_encode(['ok' => true, 'policy' => 'no-code-comments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
