<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$reconcilerPath = $root . '/tools/release-contract-reconcile';
$reconciler = (string) file_get_contents($reconcilerPath);
$legacyInsertion = <<<'PHP'
    return $prefix . PHP_EOL . $entry . PHP_EOL . substr($source, strlen($prefix));
PHP;
$deterministicInsertion = <<<'PHP'
    return $prefix . PHP_EOL . $entry . substr($source, strlen($prefix));
PHP;
$insertionCount = substr_count($reconciler, $legacyInsertion);
if ($insertionCount !== 1) {
    throw new RuntimeException('Inserção não idempotente do changelog não foi localizada exatamente uma vez: ' . $insertionCount);
}
$reconciler = str_replace($legacyInsertion, $deterministicInsertion, $reconciler);
file_put_contents($reconcilerPath, $reconciler);

$architecturePath = $root . '/.github/workflows/architecture.yml';
$architecture = (string) file_get_contents($architecturePath);
$needle = "          php tools/version-asset-contract-check.php\n          php tools/php84-runtime-contract-check\n";
$replacement = $needle . "          php tools/release-contract-reconcile --check\n";
$count = substr_count($architecture, $needle);
if ($count !== 1) {
    throw new RuntimeException('Ponto de inserção do contrato determinístico não foi localizado exatamente uma vez: ' . $count);
}
$architecture = str_replace($needle, $replacement, $architecture);
file_put_contents($architecturePath, $architecture);

$documentation = <<<'YAML'
name: Documentation Contract

on:
  pull_request:
  push:
    branches:
      - prontoo

permissions:
  contents: read

jobs:
  validate:
    runs-on: ubuntu-latest
    timeout-minutes: 15
    steps:
      - name: Checkout
        uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
          tools: none
      - name: Deterministic release contract
        shell: bash
        run: |
          set -euo pipefail
          php tools/release-contract-reconcile --check
      - name: Validate documentation contract
        shell: bash
        run: |
          set -euo pipefail
          while IFS= read -r -d '' file; do
            php -l "$file" >/dev/null
          done < <(find . \
            -path './.git' -prune -o \
            -path './ssd' -prune -o \
            -path './vendor' -prune -o \
            -path './node_modules' -prune -o \
            -name '*.php' -print0)
          php tools/documentation-check.php
          php tools/code-comment-check.php
          php tools/security-regression-check.php
YAML;
file_put_contents($root . '/.github/workflows/documentation.yml', $documentation . PHP_EOL);

if (!unlink(__FILE__)) {
    throw new RuntimeException('Não foi possível remover o helper transitório da reconciliação determinística.');
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/release-contract-reconcile') . ' --write';
passthru($command, $exitCode);
if ($exitCode !== 0) {
    throw new RuntimeException('Reconciliação determinística inicial falhou com código ' . $exitCode . '.');
}
