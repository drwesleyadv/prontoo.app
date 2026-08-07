<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$replacements = [
    'app/Infrastructure/Legacy/DatabaseSchema/DatabaseSchemaInfrastructureOperations01.php' => [
        ['\\\\Prontoo\\\\Core\\\\Integrity\\\\PiIntegrity', '\\\\Prontoo\\\\Infrastructure\\\\Integrity\\\\PiIntegrity', 6],
    ],
    'app/Infrastructure/Legacy/DatabaseSchema/DatabaseSchemaInfrastructureOperations02.php' => [
        ['\\\\Prontoo\\\\Core\\\\Integrity\\\\PiIntegrity', '\\\\Prontoo\\\\Infrastructure\\\\Integrity\\\\PiIntegrity', 2],
    ],
    'app/Runtime/Legacy/AuditActivity/AuditActivityRuntimeOperations01.php' => [
        ['\\\\Prontoo\\\\Core\\\\Integrity\\\\AuditChain', '\\\\Prontoo\\\\Infrastructure\\\\Audit\\\\AuditChain', 1],
    ],
    'app/Runtime/Legacy/Financial/FinancialRuntimeOperations05.php' => [
        ['\\\\Prontoo\\\\Core\\\\Integrity\\\\PiIntegrity', '\\\\Prontoo\\\\Infrastructure\\\\Integrity\\\\PiIntegrity', 1],
    ],
    'app/Runtime/Legacy/InstallInstaller/InstallInstallerRuntimeOperations02.php' => [
        ['\\\\Prontoo\\\\Core\\\\Integrity\\\\PiIntegrity', '\\\\Prontoo\\\\Infrastructure\\\\Integrity\\\\PiIntegrity', 1],
    ],
    'app/Runtime/Legacy/SecurityAccess/SecurityAccessRuntimeOperations03.php' => [
        ['\\\\Prontoo\\\\Core\\\\Integrity\\\\PiIntegrity', '\\\\Prontoo\\\\Infrastructure\\\\Integrity\\\\PiIntegrity', 1],
    ],
];

foreach ($replacements as $relative => $rules) {
    $path = $root . '/' . $relative;
    $source = (string) file_get_contents($path);
    foreach ($rules as [$legacy, $canonical, $expected]) {
        $count = substr_count($source, $legacy);
        if ($count !== $expected && $count !== 0) {
            throw new RuntimeException(
                $relative . ': quantidade inesperada para ' . $legacy . ': ' . $count . ' (esperado ' . $expected . ')',
            );
        }
        if ($count === $expected) {
            $source = str_replace($legacy, $canonical, $source);
        }
        if (str_contains($source, $legacy)) {
            throw new RuntimeException($relative . ': referência legada permaneceu após substituição.');
        }
        if (substr_count($source, $canonical) < $expected) {
            throw new RuntimeException($relative . ': referência canônica esperada não foi materializada.');
        }
    }
    file_put_contents($path, $source);
}
