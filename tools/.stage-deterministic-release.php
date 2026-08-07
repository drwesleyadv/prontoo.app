<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$reconciler = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/release-contract-reconcile');
passthru($reconciler . ' --write', $writeExit);
if ($writeExit !== 0) {
    throw new RuntimeException('Reconciliação determinística inicial falhou com código ' . $writeExit . '.');
}
passthru($reconciler . ' --check', $checkExit);
if ($checkExit !== 0) {
    throw new RuntimeException('Reconciliação determinística não atingiu ponto fixo.');
}

foreach (['.github/workflows/architecture.yml', '.github/workflows/documentation.yml'] as $relative) {
    $target = $root . '/' . $relative;
    $command = 'git show HEAD:' . escapeshellarg($relative);
    $content = shell_exec($command);
    if (!is_string($content) || $content === '') {
        throw new RuntimeException('Não foi possível restaurar workflow temporário antes do push: ' . $relative);
    }
    file_put_contents($target, $content);
}

if (!unlink(__FILE__)) {
    throw new RuntimeException('Não foi possível remover o helper transitório da reconciliação determinística.');
}
