<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$requiredFamily = "8.4";
$requiredMinimum = "8.4.0";

$read = static function (string $path) use ($root, &$failures): string {
    $absolute = $root . "/" . ltrim($path, "/");
    $content = @file_get_contents($absolute);
    if (!is_string($content)) {
        $failures[] = "Arquivo obrigatório ausente ou ilegível: " . $path;
        return "";
    }
    return $content;
};

$version = json_decode($read("version.json"), true);
$manifest = json_decode($read("app/update.manifest.json"), true);
if (!is_array($version) || !is_array($manifest)) {
    $failures[] = "Contratos JSON de versão inválidos.";
} else {
    foreach ([
        "version.json" => $version,
        "app/update.manifest.json" => $manifest,
    ] as $label => $contract) {
        if ((string) ($contract["minimum_php"] ?? "") !== $requiredMinimum) {
            $failures[] = $label . " deve declarar minimum_php=" . $requiredMinimum;
        }
        if ((string) ($contract["required_php_family"] ?? "") !== $requiredFamily) {
            $failures[] = $label . " deve declarar required_php_family=" . $requiredFamily;
        }
    }
}

$handlerFiles = [];
$tracked = [];
exec("git -C " . escapeshellarg($root) . " ls-files", $tracked, $gitStatus);
if ($gitStatus !== 0) {
    $failures[] = "Não foi possível enumerar os arquivos rastreados.";
}
foreach ($tracked as $path) {
    if (basename($path) !== ".htaccess") {
        continue;
    }
    $handlerFiles[] = $path;
    $source = $read($path);
    if (preg_match_all('/application\/x-httpd-ea-php(\d+)(?:___lsphp)?/i', $source, $matches)) {
        foreach ($matches[1] as $versionCode) {
            if ((string) $versionCode !== "84") {
                $failures[] = "Handler PHP divergente em " . $path . ": php" . $versionCode;
            }
        }
    }
}
$rootHtaccess = $read(".htaccess");
if (!str_contains($rootHtaccess, "AddHandler application/x-httpd-ea-php84___lsphp .php .php8 .phtml")) {
    $failures[] = "O .htaccess raiz não fixa todas as extensões públicas no handler ea-php84.";
}
if ($handlerFiles === []) {
    $failures[] = "Nenhum .htaccess rastreado foi encontrado para auditoria.";
}

$guardPath = "app/Support/Php84Runtime.php";
$guardSource = $read($guardPath);
foreach ([
    'PHP_MAJOR_VERSION === PRONTOO_REQUIRED_PHP_MAJOR',
    'PHP_MINOR_VERSION === PRONTOO_REQUIRED_PHP_MINOR',
    'define("PRONTOO_REQUIRED_PHP_FAMILY", "8.4")',
    'http_response_code(503)',
    'exit(1)',
] as $requiredToken) {
    if (!str_contains($guardSource, $requiredToken)) {
        $failures[] = "Contrato incompleto em " . $guardPath . ": " . $requiredToken;
    }
}

$entryContracts = [
    "app/prontoo.php" => 'require_once __DIR__ . "/Support/Php84Runtime.php";',
    "br/index.php" => 'require_once dirname(__DIR__) . "/app/Support/Php84Runtime.php";',
    "cron/maestro.php" => 'require_once dirname(__DIR__) . "/app/Support/Php84Runtime.php";',
];
foreach ($entryContracts as $path => $needle) {
    $source = $read($path);
    $position = strpos($source, $needle);
    if ($position === false) {
        $failures[] = "Entrada sem guarda PHP 8.4: " . $path;
        continue;
    }
    $firstOperationalRequire = preg_match('/\brequire(?:_once)?\b/', $source, $match, PREG_OFFSET_CAPTURE)
        ? (int) $match[0][1]
        : null;
    if ($firstOperationalRequire !== null && $position !== $firstOperationalRequire) {
        $failures[] = "A guarda PHP 8.4 deve ser o primeiro require em " . $path;
    }
}

foreach (["index.php", "install.php"] as $path) {
    $source = $read($path);
    if (!str_contains($source, "app/prontoo.php")) {
        $failures[] = "Entrada pública não encadeada ao runtime central: " . $path;
    }
}

$workflowPaths = array_values(array_filter(
    $tracked,
    static fn(string $path): bool => str_starts_with($path, ".github/workflows/") &&
        preg_match('/\.ya?ml$/', $path) === 1,
));
foreach ($workflowPaths as $path) {
    $source = $read($path);
    if (!preg_match_all('/php-version:\s*["\x27]?([^"\x27\s]+)["\x27]?/i', $source, $matches)) {
        continue;
    }
    foreach ($matches[1] as $declared) {
        if ((string) $declared !== $requiredFamily) {
            $failures[] = "Workflow " . $path . " usa php-version=" . $declared;
        }
    }
}

$cronSource = $read("docs/operations/maestro-runbook.md");
if (!str_contains($cronSource, "/opt/cpanel/ea-php84/root/usr/bin/php")) {
    $failures[] = "O runbook do Maestro não fixa o binário CLI do PHP 8.4.";
}

if ($failures !== []) {
    fwrite(STDERR, "Contrato exclusivo PHP 8.4 falhou:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Contrato exclusivo PHP 8.4 validado.\n";
