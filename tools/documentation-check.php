<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$required = [
    'README.md',
    'CHANGELOG.md',
    'CONTRIBUTING.md',
    'SECURITY.md',
    'docs/index.md',
    'docs/glossary.md',
    'docs/architecture/overview.md',
    'docs/architecture/layers.md',
    'docs/architecture/dependencies.md',
    'docs/architecture/c4-context.md',
    'docs/architecture/c4-containers.md',
    'docs/architecture/data-flow.md',
    'docs/adr/0001-layered-architecture.md',
    'docs/adr/0002-tenant-isolation.md',
    'docs/adr/0003-action-ledger.md',
    'docs/adr/0004-json-cache-policy.md',
    'docs/domain/appointments.md',
    'docs/domain/patients.md',
    'docs/domain/financial.md',
    'docs/domain/documents.md',
    'docs/domain/tasks.md',
    'docs/domain/maestro.md',
    'docs/security/threat-model.md',
    'docs/security/authentication.md',
    'docs/security/authorization.md',
    'docs/security/tenant-isolation.md',
    'docs/security/audit-chain.md',
    'docs/operations/installation.md',
    'docs/operations/deployment.md',
    'docs/operations/rollback.md',
    'docs/operations/backup-restore.md',
    'docs/operations/incident-response.md',
    'docs/operations/maestro-runbook.md',
    'docs/database/schema-overview.md',
    'docs/database/invariants.md',
    'docs/database/migrations-policy.md',
    'docs/database/financial-integrity.md',
    'docs/testing/strategy.md',
    'docs/testing/regression-suite.md',
    'docs/testing/property-tests.md',
];
$errors = [];
foreach ($required as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $errors[] = 'Arquivo ausente: ' . $relative;
        continue;
    }
    $content = mb_trim((string) file_get_contents($path));
    if ($content === '' || !str_starts_with($content, '#')) {
        $errors[] = 'Documento inválido: ' . $relative;
    }
}
$markdown = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_contains($path, '/vendor/') || str_contains($path, '/node_modules/') || str_contains($path, '/ssd/')) {
        continue;
    }
    $markdown[] = $path;
}
foreach ($markdown as $path) {
    $content = (string) file_get_contents($path);
    preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $content, $matches);
    foreach ($matches[1] as $target) {
        $target = mb_trim((string) $target);
        if ($target === '' ||
            str_starts_with($target, '#') ||
            preg_match('~^[a-z][a-z0-9+.-]*://~i', $target) ||
            str_starts_with($target, 'mailto:')) {
            continue;
        }
        $target = rawurldecode(explode('#', $target, 2)[0]);
        if ($target === '') {
            continue;
        }
        $candidate = str_starts_with($target, '/')
            ? $root . $target
            : dirname($path) . '/' . $target;
        $resolved = realpath($candidate);
        if ($resolved === false) {
            $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', str_replace('\\', '/', $candidate)), '/');
            $errors[] = 'Link local inválido em ' . ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/') . ': ' . $relative;
        }
    }
}
foreach (glob($root . '/docs/adr/*.md') ?: [] as $adr) {
    $content = (string) file_get_contents($adr);
    if (!preg_match('/\*\*Status:\*\*\s+(aceito|substituído|rejeitado)/u', $content)) {
        $errors[] = 'ADR sem status válido: ' . basename($adr);
    }
    if (!preg_match('/\*\*Data:\*\*\s+\d{4}-\d{2}-\d{2}/', $content)) {
        $errors[] = 'ADR sem data válida: ' . basename($adr);
    }
}
if ($errors !== []) {
    fwrite(STDERR, implode("\n", array_values(array_unique($errors))) . "\n");
    exit(1);
}
echo json_encode([
    'ok' => true,
    'required_documents' => count($required),
    'markdown_files' => count($markdown),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
