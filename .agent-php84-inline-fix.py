from __future__ import annotations

import hashlib
import json
import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parent
self_path = Path(__file__).resolve()
subprocess.run(
    [sys.executable, str(root / ".agent-php84-architecture-fix.py")],
    cwd=root,
    check=True,
)

guard_path = root / "app/Core/Runtime/Php84Runtime.php"
if not guard_path.is_file():
    raise RuntimeError("Guarda intermediária PHP 8.4 não encontrada")
guard_path.unlink()

gate = '''if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
    $prontooPhpRuntimeMessage = "Prontoo exige exclusivamente PHP 8.4. Runtime atual: " . PHP_VERSION . ".";
    error_log("[Prontoo PHP runtime] " . $prontooPhpRuntimeMessage);
    if (PHP_SAPI === "cli") {
        fwrite(STDERR, $prontooPhpRuntimeMessage . PHP_EOL);
    } else {
        if (!headers_sent()) {
            http_response_code(503);
            header("Content-Type: text/plain; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Retry-After: 300");
        }
        echo $prontooPhpRuntimeMessage;
    }
    unset($prontooPhpRuntimeMessage);
    exit(1);
}
'''

entry_replacements = {
    "app/prontoo.php": 'require_once __DIR__ . "/Core/Runtime/Php84Runtime.php";\n',
    "br/index.php": 'require_once dirname(__DIR__) . "/app/Core/Runtime/Php84Runtime.php";\n',
    "cron/maestro.php": 'require_once dirname(__DIR__) . "/app/Core/Runtime/Php84Runtime.php";\n',
}
for relative, require_line in entry_replacements.items():
    path = root / relative
    source = path.read_text(encoding="utf-8")
    if source.count(require_line) != 1:
        raise RuntimeError(f"Guarda intermediária não localizada em {relative}")
    path.write_text(source.replace(require_line, gate, 1), encoding="utf-8")

checker = r'''<?php
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

$tracked = [];
exec("git -C " . escapeshellarg($root) . " ls-files", $tracked, $gitStatus);
if ($gitStatus !== 0) {
    $failures[] = "Não foi possível enumerar os arquivos rastreados.";
}
$handlerFiles = [];
foreach ($tracked as $path) {
    if (basename($path) !== ".htaccess" || !is_file($root . "/" . $path)) {
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
    $failures[] = "O .htaccess raiz não fixa .php, .php8 e .phtml no handler ea-php84.";
}
if ($handlerFiles === []) {
    $failures[] = "Nenhum .htaccess rastreado foi encontrado para auditoria.";
}

$entryPaths = ["app/prontoo.php", "br/index.php", "cron/maestro.php"];
foreach ($entryPaths as $path) {
    $source = $read($path);
    foreach ([
        "PHP_MAJOR_VERSION !== 8",
        "PHP_MINOR_VERSION !== 4",
        "Prontoo exige exclusivamente PHP 8.4",
        "http_response_code(503)",
        "exit(1)",
    ] as $token) {
        if (!str_contains($source, $token)) {
            $failures[] = "Gate PHP 8.4 incompleto em " . $path . ": " . $token;
        }
    }
    $gatePosition = strpos($source, "PHP_MAJOR_VERSION !== 8");
    $requirePosition = preg_match('/\brequire(?:_once)?\b/', $source, $match, PREG_OFFSET_CAPTURE)
        ? (int) $match[0][1]
        : null;
    if ($gatePosition === false || ($requirePosition !== null && $gatePosition > $requirePosition)) {
        $failures[] = "Gate PHP 8.4 deve anteceder o primeiro require em " . $path;
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
        preg_match('/\.ya?ml$/', $path) === 1 &&
        is_file($root . "/" . $path),
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

$architectureWorkflow = $read(".github/workflows/architecture.yml");
if (!str_contains($architectureWorkflow, "php tools/php84-runtime-contract-check")) {
    $failures[] = "A auditoria PHP 8.4 não está integrada ao contrato de arquitetura.";
}
$cronRunbook = $read("docs/operations/maestro-runbook.md");
if (!str_contains($cronRunbook, "/opt/cpanel/ea-php84/root/usr/bin/php")) {
    $failures[] = "O runbook do Maestro não fixa o binário CLI do PHP 8.4.";
}

if ($failures !== []) {
    fwrite(STDERR, "Contrato exclusivo PHP 8.4 falhou:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Contrato exclusivo PHP 8.4 validado.\n";
'''
checker_path = root / "tools/php84-runtime-contract-check"
checker_path.write_text(checker, encoding="utf-8")

self_path.unlink()

manifest_path = root / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).split(b"\0")
candidates = {raw.decode("utf-8") for raw in tracked if raw}
for obsolete in [
    "app/Support/Php84Runtime.php",
    "app/Core/Runtime/Php84Runtime.php",
    "tools/php84-runtime-contract-check.php",
    ".agent-php84-release.py",
    ".agent-php84-fix.py",
    ".agent-php84-architecture-fix.py",
    ".agent-php84-inline-fix.py",
    ".github/workflows/agent-php84-release.yml",
]:
    candidates.discard(obsolete)
candidates.add("tools/php84-runtime-contract-check")
files: dict[str, str] = {}
total = 0
for relative in sorted(candidates):
    if relative == "app/update.manifest.json":
        continue
    absolute = root / relative
    if not absolute.is_file():
        continue
    data = absolute.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["files"] = files
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

print(json.dumps({
    "ok": True,
    "runtime_gate": "inline-entry-boundary",
    "php_files_added": 0,
    "checker": "tools/php84-runtime-contract-check",
}, ensure_ascii=False))
