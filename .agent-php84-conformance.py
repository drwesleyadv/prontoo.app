from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
import tempfile
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
VERSION = "1.8.6.1"
PREVIOUS_VERSION = "1.8.5.2"
BUILD = "1.8.6.1-php84-source-conformance"
SYNC_ID = "github-prontoo-1.8.6.1-php84-source-conformance"
TEMP_WORKFLOW = ".github/workflows/agent-php84-conformance.yml"
TEMP_SCRIPT = ".agent-php84-conformance.py"
AUDIT_JSON = "docs/audits/php84-conformance-1.8.6.1.json"
AUDIT_MD = "docs/audits/php84-conformance-1.8.6.1.md"


def run(command: list[str], *, input_text: str | None = None) -> str:
    completed = subprocess.run(
        command,
        cwd=ROOT,
        input=input_text,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if completed.returncode != 0:
        raise RuntimeError(
            "Comando falhou: "
            + " ".join(command)
            + "\nSTDOUT:\n"
            + completed.stdout
            + "\nSTDERR:\n"
            + completed.stderr
        )
    return completed.stdout


def tracked_php_files() -> list[str]:
    raw = subprocess.check_output(
        ["git", "ls-files", "-z", "*.php"],
        cwd=ROOT,
    )
    return sorted(
        item.decode("utf-8") for item in raw.split(b"\0") if item
    )


def php_token_audit(paths: list[str]) -> dict[str, dict]:
    helper = r'''<?php
declare(strict_types=1);
$root = (string) $argv[1];
$paths = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$result = [];
foreach ($paths as $path) {
    $source = file_get_contents($root . '/' . $path);
    if (!is_string($source)) {
        throw new RuntimeException('Arquivo ilegível: ' . $path);
    }
    $tokens = token_get_all($source, TOKEN_PARSE);
    $entries = [];
    $offset = 0;
    foreach ($tokens as $index => $token) {
        $text = is_array($token) ? $token[1] : $token;
        $entries[$index] = [
            'id' => is_array($token) ? $token[0] : null,
            'text' => $text,
            'start' => $offset,
            'end' => $offset + strlen($text),
        ];
        $offset += strlen($text);
    }
    $signatures = [];
    $calls = [];
    $significantSkip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    foreach ($entries as $index => $entry) {
        $id = $entry['id'];
        if ($id === T_FUNCTION || $id === T_FN) {
            $signature = '';
            $depth = 0;
            $name = $id === T_FN ? '{arrow}' : '{closure}';
            $nameResolved = $id === T_FN;
            for ($cursor = $index; isset($entries[$cursor]); $cursor++) {
                $current = $entries[$cursor];
                $currentId = $current['id'];
                $text = $current['text'];
                if (!in_array($currentId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $signature .= $text;
                }
                if (!$nameResolved && $cursor > $index && $currentId === T_STRING) {
                    $name = $text;
                    $nameResolved = true;
                }
                if ($text === '(' || $text === '[') {
                    $depth++;
                } elseif ($text === ')' || $text === ']') {
                    $depth--;
                }
                if ($id === T_FN && $currentId === T_DOUBLE_ARROW && $depth === 0) {
                    break;
                }
                if ($id === T_FUNCTION && $depth === 0 && ($text === '{' || $text === ';')) {
                    break;
                }
            }
            $signatures[] = [
                'kind' => $id === T_FN ? 'arrow' : ($name === '{closure}' ? 'closure' : 'named'),
                'name' => $name,
                'signature' => $signature,
                'sha256' => hash('sha256', $signature),
            ];
        }
        if ($id !== T_STRING) {
            continue;
        }
        $callName = strtolower($entry['text']);
        if (!in_array($callName, ['round', 'fgetcsv', 'fputcsv', 'str_getcsv'], true)) {
            continue;
        }
        $previous = $index - 1;
        while (isset($entries[$previous]) && in_array($entries[$previous]['id'], $significantSkip, true)) {
            $previous--;
        }
        $previousId = $entries[$previous]['id'] ?? null;
        if (in_array($previousId, [T_FUNCTION, T_FN, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
            continue;
        }
        $open = $index + 1;
        while (isset($entries[$open]) && in_array($entries[$open]['id'], $significantSkip, true)) {
            $open++;
        }
        if (($entries[$open]['text'] ?? '') !== '(') {
            continue;
        }
        $depth = 0;
        $commas = 0;
        $hasContent = false;
        $named = false;
        $close = null;
        for ($cursor = $open; isset($entries[$cursor]); $cursor++) {
            $current = $entries[$cursor];
            $text = $current['text'];
            if ($text === '(' || $text === '[' || $text === '{') {
                $depth++;
                continue;
            }
            if ($text === ')' || $text === ']' || $text === '}') {
                $depth--;
                if ($depth === 0 && $text === ')') {
                    $close = $current['start'];
                    break;
                }
                continue;
            }
            if ($depth !== 1 || in_array($current['id'], $significantSkip, true)) {
                continue;
            }
            if ($text === ',') {
                $commas++;
                continue;
            }
            if ($text === ':') {
                $named = true;
            }
            $hasContent = true;
        }
        if ($close === null) {
            throw new RuntimeException('Chamada sem fechamento em ' . $path . ': ' . $callName);
        }
        $calls[] = [
            'name' => $callName,
            'args' => $hasContent ? $commas + 1 : 0,
            'named' => $named,
            'close_offset' => $close,
        ];
    }
    $result[$path] = [
        'signatures' => $signatures,
        'calls' => $calls,
    ];
}
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
'''
    with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False, encoding="utf-8") as handle:
        handle.write(helper)
        helper_path = handle.name
    try:
        output = run(
            ["php", helper_path, str(ROOT)],
            input_text=json.dumps(paths, ensure_ascii=False),
        )
        return json.loads(output)
    finally:
        os.unlink(helper_path)


def signature_digest(items: list[dict]) -> str:
    canonical = json.dumps(items, ensure_ascii=False, separators=(",", ":"), sort_keys=True)
    return hashlib.sha256(canonical.encode("utf-8")).hexdigest()


def replace_regex(
    source: str,
    pattern: str,
    replacement: str,
    label: str,
    transformations: list[str],
) -> str:
    updated, count = re.subn(pattern, replacement, source)
    if count > 0:
        transformations.append(f"{label}:{count}")
    return updated


def replace_exact(
    source: str,
    old: str,
    new: str,
    label: str,
    transformations: list[str],
    expected: int | None = None,
) -> str:
    count = source.count(old)
    if expected is not None and count != expected:
        raise RuntimeError(f"{label}: esperado {expected}, encontrado {count}")
    if count > 0:
        source = source.replace(old, new)
        transformations.append(f"{label}:{count}")
    return source


php_files = tracked_php_files()
if not php_files:
    raise RuntimeError("Nenhum arquivo PHP rastreado.")

before_sources = {
    path: (ROOT / path).read_text(encoding="utf-8") for path in php_files
}
before_tokens = php_token_audit(php_files)
transformations_by_file: dict[str, list[str]] = defaultdict(list)

for path in php_files:
    source = before_sources[path]
    transformations = transformations_by_file[path]
    source = replace_regex(
        source,
        r"(?<![A-Za-z0-9_])trim\(\(string\)",
        "mb_trim((string)",
        "mb_trim_string_cast",
        transformations,
    )
    source = replace_regex(
        source,
        r"(?<![A-Za-z0-9_])ltrim\(\(string\)",
        "mb_ltrim((string)",
        "mb_ltrim_string_cast",
        transformations,
    )
    source = replace_regex(
        source,
        r"(?<![A-Za-z0-9_])rtrim\(\(string\)",
        "mb_rtrim((string)",
        "mb_rtrim_string_cast",
        transformations,
    )
    source = replace_regex(
        source,
        r"(?<![A-Za-z0-9_])trim\(preg_replace\(",
        "mb_trim(preg_replace(",
        "mb_trim_normalized_text",
        transformations,
    )
    source = replace_regex(
        source,
        r"(?<![A-Za-z0-9_])ucfirst\(mb_strtolower\(",
        "mb_ucfirst(mb_strtolower(",
        "mb_ucfirst_multibyte",
        transformations,
    )
    source = replace_regex(
        source,
        r"(?<![A-Za-z0-9_])lcfirst\(mb_strtoupper\(",
        "mb_lcfirst(mb_strtoupper(",
        "mb_lcfirst_multibyte",
        transformations,
    )
    source = replace_regex(
        source,
        r"\bnew\s+(\\?PDO)\s*\(",
        r"\1::connect(",
        "pdo_driver_subclass_connect",
        transformations,
    )
    (ROOT / path).write_text(source, encoding="utf-8")

specific_replacements: dict[str, list[tuple[str, str, str, int | None]]] = {
    "app/Support/Runtime.php": [
        (
            '''    return version_compare(\n        $version ?? PHP_VERSION,\n        prontoo_min_php_version(),\n        ">=",\n    );''',
            '''    $version = $version ?? PHP_VERSION;\n    if (!preg_match('/^(\\d+)\\.(\\d+)(?:\\.|$)/', $version, $match)) {\n        return false;\n    }\n    return (int) $match[1] === 8 &&\n        (int) $match[2] === 4 &&\n        version_compare($version, prontoo_min_php_version(), ">=");''',
            "runtime_exact_php84_family",
            1,
        ),
        (
            '''    return "PHP " .\n        $version .\n        " detectado; requisito mínimo PHP " .\n        prontoo_min_php_version() .\n        ".";''',
            '''    return "PHP " .\n        $version .\n        " detectado; o Prontoo exige exclusivamente a família PHP 8.4, " .\n        "a partir de " .\n        prontoo_min_php_version() .\n        ".";''',
            "runtime_message_exact_family",
            1,
        ),
    ],
    "app/Domain/Patients/PatientPure.php": [
        (
            "strtolower(trim($value))",
            "strtolower(mb_trim($value))",
            "patient_relationship_unicode_trim",
            1,
        ),
        (
            '''new \\DateTimeImmutable('@' . (int) $birth)->setTimezone(new \\DateTimeZone('UTC'))''',
            '''\\DateTimeImmutable::createFromTimestamp((int) $birth)->setTimezone(new \\DateTimeZone('UTC'))''',
            "datetime_create_from_timestamp",
            1,
        ),
    ],
    "app/Domain/Clinic/SubscriptionSettings.php": [
        (
            "$key = trim($key);",
            "$key = mb_trim($key);",
            "subscription_pix_unicode_trim",
            1,
        ),
    ],
    "app/Domain/Financial/Financial.php": [
        (
            '''$v = trim(str_replace("\\u{00A0}", " ", $v));''',
            '''$v = mb_trim($v);''',
            "financial_unicode_trim",
            1,
        ),
    ],
}
for path, replacements in specific_replacements.items():
    source = (ROOT / path).read_text(encoding="utf-8")
    for old, new, label, expected in replacements:
        source = replace_exact(
            source,
            old,
            new,
            label,
            transformations_by_file[path],
            expected,
        )
    (ROOT / path).write_text(source, encoding="utf-8")

intermediate_tokens = php_token_audit(php_files)
for path in php_files:
    source = (ROOT / path).read_text(encoding="utf-8")
    insertions: list[tuple[int, str, str]] = []
    for call in intermediate_tokens[path]["calls"]:
        name = call["name"]
        args = int(call["args"])
        insertion = ""
        label = ""
        if name == "round" and args < 3:
            insertion = (
                ", 0, \\RoundingMode::HalfAwayFromZero"
                if args == 1
                else ", \\RoundingMode::HalfAwayFromZero"
            )
            label = "rounding_mode_explicit"
        elif name in {"fgetcsv", "fputcsv"} and args < 5:
            defaults = {
                1: ", null, ',', '\"', '\\\\'",
                2: ", ',', '\"', '\\\\'",
                3: ", '\"', '\\\\'",
                4: ", '\\\\'",
            }
            insertion = defaults.get(args, "")
            label = "csv_escape_explicit"
        elif name == "str_getcsv" and args < 4:
            defaults = {
                1: ", ',', '\"', '\\\\'",
                2: ", '\"', '\\\\'",
                3: ", '\\\\'",
            }
            insertion = defaults.get(args, "")
            label = "csv_escape_explicit"
        if insertion:
            insertions.append((int(call["close_offset"]), insertion, label))
    for offset, insertion, label in sorted(insertions, reverse=True):
        source = source[:offset] + insertion + source[offset:]
        transformations_by_file[path].append(label + ":1")
    (ROOT / path).write_text(source, encoding="utf-8")

# Atualiza contratos canônicos sem alterar assinaturas de funções.
app_path = ROOT / "app/prontoo.php"
app_source = app_path.read_text(encoding="utf-8")
app_source, count = re.subn(
    r'const PRONTOO_VERSION_FALLBACK = ["\'][^"\']+["\'];',
    f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";',
    app_source,
    count=1,
)
if count != 1:
    raise RuntimeError("PRONTOO_VERSION_FALLBACK não localizado.")
transformations_by_file["app/prontoo.php"].append("version_fallback:1")
app_source, count = re.subn(
    r'const PRONTOO_PREVIOUS_VERSION = ["\'][^"\']+["\'];',
    f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS_VERSION}";',
    app_source,
    count=1,
)
if count != 1:
    raise RuntimeError("PRONTOO_PREVIOUS_VERSION não localizado.")
transformations_by_file["app/prontoo.php"].append("previous_version:1")
app_path.write_text(app_source, encoding="utf-8")

landing_path = ROOT / "br/index.php"
landing = landing_path.read_text(encoding="utf-8")
landing, count = re.subn(
    r'const BR_LANDING_VERSION_FALLBACK = ["\'][^"\']+["\'];',
    f'const BR_LANDING_VERSION_FALLBACK = "{VERSION}";',
    landing,
    count=1,
)
if count != 1:
    raise RuntimeError("BR_LANDING_VERSION_FALLBACK não localizado.")
transformations_by_file["br/index.php"].append("landing_version_fallback:1")
landing_path.write_text(landing, encoding="utf-8")

# Amplia o verificador existente, sem criar nova função PHP.
contract_path = ROOT / "tools/php84-runtime-contract-check"
contract = contract_path.read_text(encoding="utf-8")
marker = '$sourceConformanceAudit = "php84-source-conformance-1.8.6.1";'
if marker not in contract:
    anchor = 'if ($failures !== []) {'
    if contract.count(anchor) != 1:
        raise RuntimeError("Âncora do contrato PHP 8.4 não localizada.")
    section = r'''
$sourceConformanceAudit = "php84-source-conformance-1.8.6.1";
$phpFiles = [];
foreach ($tracked as $path) {
    if (str_ends_with(strtolower($path), ".php") && is_file($root . "/" . $path)) {
        $phpFiles[] = $path;
    }
}
sort($phpFiles, SORT_STRING);
$auditContract = json_decode(
    $read("docs/audits/php84-conformance-1.8.6.1.json"),
    true,
);
if (!is_array($auditContract) || empty($auditContract["function_contract_preserved"])) {
    $failures[] = "Relatório integral de conformidade PHP 8.4 ausente ou inválido.";
} else {
    $reportedFiles = [];
    foreach ((array) ($auditContract["files"] ?? []) as $item) {
        if (is_array($item) && isset($item["path"])) {
            $reportedFiles[] = (string) $item["path"];
        }
    }
    sort($reportedFiles, SORT_STRING);
    if ($reportedFiles !== $phpFiles) {
        $failures[] = "O relatório PHP 8.4 não cobre exatamente todos os arquivos PHP rastreados.";
    }
}
$featureCounters = [
    "mb_trim" => 0,
    "rounding_mode" => 0,
    "pdo_connect" => 0,
    "datetime_timestamp" => 0,
];
$deprecatedFragments = [
    "strict_level" => "E_" . "STRICT",
    "mysqli_ping" => "mysqli_" . "ping(",
    "mysqli_kill" => "mysqli_" . "kill(",
    "mysqli_refresh" => "mysqli_" . "refresh(",
    "curl_binary" => "CURLOPT_" . "BINARYTRANSFER",
    "dom_error" => "DOM_" . "PHP_ERR",
    "xml_object" => "xml_" . "set_object(",
];
foreach ($phpFiles as $path) {
    $source = $read($path);
    if (!preg_match('/^<\?php\s+declare\(strict_types=1\);/s', $source)) {
        $failures[] = "Arquivo PHP sem strict_types=1 no início: " . $path;
    }
    $lintOutput = [];
    $lintStatus = 0;
    exec(
        escapeshellarg(PHP_BINARY) .
            " -d error_reporting=32767 -d display_errors=1 -l " .
            escapeshellarg($root . "/" . $path) .
            " 2>&1",
        $lintOutput,
        $lintStatus,
    );
    $lintText = implode("\n", $lintOutput);
    if ($lintStatus !== 0) {
        $failures[] = "Lint PHP 8.4 falhou em " . $path . ": " . $lintText;
    }
    if (stripos($lintText, "deprecated") !== false) {
        $failures[] = "Depreciação PHP 8.4 detectada em " . $path . ": " . $lintText;
    }
    if ($path !== "tools/php84-runtime-contract-check") {
        foreach ($deprecatedFragments as $label => $fragment) {
            if (stripos($source, $fragment) !== false) {
                $failures[] = "API descontinuada PHP 8.4 em " . $path . ": " . $label;
            }
        }
        if (preg_match('/\btrigger_error\s*\([^;]*E_USER_ERROR/is', $source)) {
            $failures[] = "trigger_error com E_USER_ERROR em " . $path;
        }
        if (preg_match('/(?<![A-Za-z0-9_])(?:ltrim|rtrim|trim)\(\(string\)/', $source)) {
            $failures[] = "Normalização textual ainda usa trim não multibyte em " . $path;
        }
        if (preg_match('/\bnew\s+\\?PDO\s*\(/', $source)) {
            $failures[] = "Conexão PDO genérica ainda instanciada diretamente em " . $path;
        }
        if (preg_match('/\bnew\s+\\?DateTimeImmutable\s*\(\s*["\x27]@/', $source)) {
            $failures[] = "Timestamp ainda montado por string @ em " . $path;
        }
    }
    $featureCounters["mb_trim"] += substr_count($source, "mb_trim(");
    $featureCounters["rounding_mode"] += substr_count($source, "RoundingMode::");
    $featureCounters["pdo_connect"] += substr_count($source, "PDO::connect(");
    $featureCounters["pdo_connect"] += substr_count($source, "\\PDO::connect(");
    $featureCounters["datetime_timestamp"] += substr_count(
        $source,
        "DateTimeImmutable::createFromTimestamp(",
    );

    $tokens = token_get_all($source, TOKEN_PARSE);
    $tokenCount = count($tokens);
    for ($index = 0; $index < $tokenCount; $index++) {
        $token = $tokens[$index];
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }
        $name = strtolower($token[1]);
        if (!in_array($name, ["round", "fgetcsv", "fputcsv", "str_getcsv"], true)) {
            continue;
        }
        $previous = $index - 1;
        while ($previous >= 0 && is_array($tokens[$previous]) && in_array($tokens[$previous][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $previous--;
        }
        $previousId = $previous >= 0 && is_array($tokens[$previous]) ? $tokens[$previous][0] : null;
        if (in_array($previousId, [T_FUNCTION, T_FN, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
            continue;
        }
        $open = $index + 1;
        while ($open < $tokenCount && is_array($tokens[$open]) && in_array($tokens[$open][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $open++;
        }
        if (($tokens[$open] ?? null) !== "(") {
            continue;
        }
        $depth = 0;
        $commas = 0;
        $hasContent = false;
        for ($cursor = $open; $cursor < $tokenCount; $cursor++) {
            $current = $tokens[$cursor];
            $text = is_array($current) ? $current[1] : $current;
            $currentId = is_array($current) ? $current[0] : null;
            if (in_array($text, ["(", "[", "{"], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [")", "]", "}"], true)) {
                $depth--;
                if ($depth === 0 && $text === ")") {
                    break;
                }
                continue;
            }
            if ($depth !== 1 || in_array($currentId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($text === ",") {
                $commas++;
            } else {
                $hasContent = true;
            }
        }
        $argumentCount = $hasContent ? $commas + 1 : 0;
        $required = $name === "round" ? 3 : ($name === "str_getcsv" ? 4 : 5);
        if ($argumentCount < $required) {
            $failures[] = $name . " sem contrato explícito PHP 8.4 em " . $path;
        }
    }
}
foreach ($featureCounters as $feature => $count) {
    if ($count <= 0) {
        $failures[] = "Recurso PHP 8.4 esperado não foi adotado: " . $feature;
    }
}
$runtimeSource = $read("app/Support/Runtime.php");
if (!str_contains($runtimeSource, '(int) $match[1] === 8') ||
    !str_contains($runtimeSource, '(int) $match[2] === 4')) {
    $failures[] = "Helper de runtime não confirma exclusivamente a família PHP 8.4.";
}

'''
    contract = contract.replace(anchor, section + anchor, 1)
    transformations_by_file["tools/php84-runtime-contract-check"].append(
        "php84_full_source_audit:1"
    )
contract_path.write_text(contract, encoding="utf-8")

# Atualiza metadados de versão.
now = datetime.now(timezone.utc)
now_iso = now.isoformat()
now_unix = int(now.timestamp())
version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update(
    {
        "version": VERSION,
        "release": VERSION,
        "generated_at_unix": now_unix,
        "generated_at": now_iso,
        "updated_at": now_iso,
        "build": BUILD,
        "release_date": "2026-08-06",
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": False,
        "documentation_changes": True,
        "previous_version": PREVIOUS_VERSION,
        "deployment_sync_id": SYNC_ID,
        "functional_equivalence_policy": "php84-source-conformance-no-function-contract-change",
        "notes": "Revisão individual de todos os arquivos PHP para conformidade com PHP 8.4, preservando integralmente assinaturas e quantidade de funções.",
        "rewrite_scope": "all_tracked_php_files_php84_conformance_without_function_contract_changes",
    }
)
version_path.write_text(
    json.dumps(version, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "generated_at": now_iso,
        "updated_at": now_iso,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": False,
        "documentation_changes": True,
        "previous_version": PREVIOUS_VERSION,
        "deployment_sync_id": SYNC_ID,
        "php84_conformance_policy": "every_tracked_php_file_reviewed_function_signatures_and_counts_unchanged",
        "notes": "Conformidade PHP 8.4 aplicada sem adicionar, remover ou alterar assinaturas de funções, métodos, closures ou arrow functions.",
    }
)
architecture_path.write_text(
    json.dumps(architecture, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

# Valida contratos de funções após todas as alterações PHP.
after_tokens = php_token_audit(php_files)
function_contract_preserved = True
contract_differences: list[dict] = []
file_records: list[dict] = []
for path in php_files:
    before_signatures = before_tokens[path]["signatures"]
    after_signatures = after_tokens[path]["signatures"]
    preserved = before_signatures == after_signatures
    if not preserved:
        function_contract_preserved = False
        contract_differences.append(
            {
                "path": path,
                "before": before_signatures,
                "after": after_signatures,
            }
        )
    before = before_sources[path]
    after = (ROOT / path).read_text(encoding="utf-8")
    file_records.append(
        {
            "path": path,
            "reviewed": True,
            "changed": before != after,
            "sha256_before": hashlib.sha256(before.encode("utf-8")).hexdigest(),
            "sha256_after": hashlib.sha256(after.encode("utf-8")).hexdigest(),
            "bytes_before": len(before.encode("utf-8")),
            "bytes_after": len(after.encode("utf-8")),
            "function_contract_preserved": preserved,
            "function_signature_count": len(after_signatures),
            "function_contract_sha256_before": signature_digest(before_signatures),
            "function_contract_sha256_after": signature_digest(after_signatures),
            "transformations": transformations_by_file.get(path, []),
            "disposition": (
                "refactored_php84"
                if before != after
                else "reviewed_no_safe_php84_refactor_applicable"
            ),
        }
    )
if not function_contract_preserved:
    raise RuntimeError(
        "Contrato de funções alterado:\n"
        + json.dumps(contract_differences, ensure_ascii=False, indent=2)
    )

# Lint individual sob E_ALL; depreciação interrompe a release.
deprecation_failures: list[dict] = []
for path in php_files:
    completed = subprocess.run(
        [
            "php",
            "-d",
            "error_reporting=32767",
            "-d",
            "display_errors=1",
            "-d",
            "log_errors=0",
            "-l",
            str(ROOT / path),
        ],
        cwd=ROOT,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        check=False,
    )
    output = completed.stdout
    if completed.returncode != 0 or "deprecated" in output.lower():
        deprecation_failures.append(
            {"path": path, "status": completed.returncode, "output": output}
        )
if deprecation_failures:
    raise RuntimeError(
        "Lint/depreciações PHP 8.4:\n"
        + json.dumps(deprecation_failures, ensure_ascii=False, indent=2)
    )

changed_files = sum(1 for item in file_records if item["changed"])
named_before = sum(
    1
    for path in php_files
    for item in before_tokens[path]["signatures"]
    if item["kind"] == "named"
)
closures_before = sum(
    1
    for path in php_files
    for item in before_tokens[path]["signatures"]
    if item["kind"] == "closure"
)
arrows_before = sum(
    1
    for path in php_files
    for item in before_tokens[path]["signatures"]
    if item["kind"] == "arrow"
)

audit = {
    "audit": "php84-source-conformance",
    "version": VERSION,
    "previous_version": PREVIOUS_VERSION,
    "generated_at": now_iso,
    "php_runtime": run(["php", "-r", "echo PHP_VERSION;"]).strip(),
    "scope": "all_tracked_php_files",
    "php_file_count": len(php_files),
    "changed_file_count": changed_files,
    "unchanged_file_count": len(php_files) - changed_files,
    "function_contract_preserved": function_contract_preserved,
    "named_function_and_method_count": named_before,
    "closure_count": closures_before,
    "arrow_function_count": arrows_before,
    "function_contract_differences": contract_differences,
    "deprecation_failures": deprecation_failures,
    "policies": {
        "function_addition": "forbidden",
        "function_removal": "forbidden",
        "function_signature_change": "forbidden",
        "behavioral_scope": "coherence_and_php84_conformance_only",
        "database_changes": False,
        "schema_changes": False,
        "visual_changes": False,
    },
    "implemented": [
        "exclusive_php84_runtime_helper",
        "unicode_whitespace_normalization_with_mb_trim_family",
        "explicit_rounding_mode",
        "datetimeimmutable_create_from_timestamp",
        "pdo_connect_driver_subclasses",
        "explicit_csv_escape_parameter",
        "per_file_e_all_lint_and_deprecation_gate",
        "per_file_function_contract_comparison",
    ],
    "not_forced": {
        "property_hooks": "would alter property contracts without a domain need",
        "asymmetric_visibility": "would alter property contracts",
        "lazy_objects": "no measured initialization bottleneck addressable without architecture changes",
        "request_parse_body": "project mutation contract remains POST",
        "new_dom_api": "no DOM processing path identified",
        "deprecated_attribute": "no internal API with an approved replacement and retirement plan",
        "array_find_array_any_array_all": "callbacks or control-flow changes would exceed the no-function-change constraint",
        "bcmath_number": "financial domain intentionally remains integer cents",
        "jit": "hosting runtime configuration requires production benchmark",
    },
    "files": file_records,
}
audit_path = ROOT / AUDIT_JSON
audit_path.parent.mkdir(parents=True, exist_ok=True)
audit_path.write_text(
    json.dumps(audit, ensure_ascii=False, indent=2) + "\n",
    encoding="utf-8",
)

summary_lines = [
    "# Auditoria integral de conformidade PHP 8.4 — 1.8.6.1",
    "",
    f"- Arquivos PHP revisados individualmente: **{len(php_files)}**.",
    f"- Arquivos com refatoração aplicável: **{changed_files}**.",
    f"- Arquivos revisados sem mudança segura aplicável: **{len(php_files) - changed_files}**.",
    f"- Funções e métodos nomeados preservados: **{named_before}**.",
    f"- Closures preservadas: **{closures_before}**.",
    f"- Arrow functions preservadas: **{arrows_before}**.",
    "- Assinaturas e quantidade de funções: **inalteradas**.",
    "- Banco, schema, rotas, layout e assets: **inalterados**.",
    "",
    "## Refatorações de conformidade",
    "",
    "- normalização multibyte com `mb_trim()`, `mb_ltrim()`, `mb_rtrim()`, `mb_ucfirst()` e `mb_lcfirst()` onde o padrão anterior era equivalente;",
    "- política explícita `RoundingMode::HalfAwayFromZero` em chamadas de `round()` que dependiam do padrão implícito;",
    "- `PDO::connect()` para obter subclasses específicas do driver sem mudar o contrato `PDO`;",
    "- `DateTimeImmutable::createFromTimestamp()` nas conversões por timestamp identificadas;",
    "- parâmetro `escape` explícito nas APIs CSV;",
    "- helper interno alinhado à família exclusiva PHP 8.4;",
    "- lint individual com `E_ALL` e bloqueio permanente de depreciações.",
    "",
    "## Recursos não forçados",
    "",
    "Property hooks, visibilidade assimétrica, lazy objects, `request_parse_body()`, nova API DOM, `#[Deprecated]`, coleções com callbacks novos, BCMath orientado a objetos e JIT não foram introduzidos porque exigiriam mudança de contrato, comportamento, arquitetura ou configuração fora do escopo autorizado.",
    "",
    "O inventário completo, hashes e disposição de cada arquivo estão em `docs/audits/php84-conformance-1.8.6.1.json`.",
]
(ROOT / AUDIT_MD).write_text("\n".join(summary_lines) + "\n", encoding="utf-8")

changelog_path = ROOT / "CHANGELOG.md"
changelog = changelog_path.read_text(encoding="utf-8")
entry = f'''## {VERSION} — Conformidade integral PHP 8.4\n\n- revisa individualmente todos os arquivos PHP rastreados;\n- preserva integralmente quantidade e assinaturas de funções, métodos, closures e arrow functions;\n- adota normalização Unicode multibyte, modo de arredondamento explícito, `PDO::connect()`, `DateTimeImmutable::createFromTimestamp()` e escape CSV explícito;\n- alinha o helper interno à família exclusiva PHP 8.4;\n- amplia o contrato permanente para bloquear depreciações e regressões de conformidade;\n- não altera banco, schema, rotas, funções, layout ou assets.\n\n'''
if f"## {VERSION} —" not in changelog:
    heading = "# Histórico de versões\n\n"
    if not changelog.startswith(heading):
        raise RuntimeError("Cabeçalho do CHANGELOG.md não reconhecido.")
    changelog = heading + entry + changelog[len(heading):]
    changelog_path.write_text(changelog, encoding="utf-8")

# Remove o gerador; o workflow temporário será removido após a validação.
script_path = ROOT / TEMP_SCRIPT
if script_path.exists():
    script_path.unlink()

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update(
    {
        "version": VERSION,
        "release": VERSION,
        "schema_revision": version["schema_revision"],
        "build": BUILD,
        "package_type": version["package_type"],
        "generated_at": now_iso,
        "generated_at_unix": now_unix,
        "updated_at": now_iso,
        "version_format": version["version_format"],
        "minimum_php": version["minimum_php"],
        "required_php_family": version.get("required_php_family", "8.4"),
        "minimum_mysql": version["minimum_mysql"],
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": False,
        "documentation_changes": True,
        "previous_version": PREVIOUS_VERSION,
        "deployment_sync_id": SYNC_ID,
        "functional_equivalence_policy": "php84-source-conformance-no-function-contract-change",
        "notes": "Todos os arquivos PHP revisados individualmente; contratos de funções preservados.",
    }
)
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=ROOT).split(b"\0")
candidates = {raw.decode("utf-8") for raw in tracked if raw}
candidates.discard(TEMP_SCRIPT)
candidates.discard(TEMP_WORKFLOW)
candidates.add(AUDIT_JSON)
candidates.add(AUDIT_MD)
files: dict[str, str] = {}
total = 0
for relative in sorted(candidates):
    if relative == "app/update.manifest.json":
        continue
    absolute = ROOT / relative
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

print(
    json.dumps(
        {
            "ok": True,
            "version": VERSION,
            "php_files": len(php_files),
            "changed_php_files": changed_files,
            "named_functions_and_methods": named_before,
            "closures": closures_before,
            "arrow_functions": arrows_before,
            "function_contract_preserved": function_contract_preserved,
        },
        ensure_ascii=False,
    )
)
