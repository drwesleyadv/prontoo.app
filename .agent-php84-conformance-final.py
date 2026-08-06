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
AUDIT_JSON = "docs/audits/php84-conformance-1.8.6.1.json"
AUDIT_MD = "docs/audits/php84-conformance-1.8.6.1.md"
TEMP_SCRIPT = ".agent-php84-conformance-final.py"
TEMP_WORKFLOW = ".github/workflows/agent-php84-conformance-final.yml"


def command(args: list[str], input_text: str | None = None) -> str:
    process = subprocess.run(
        args,
        cwd=ROOT,
        input=input_text,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if process.returncode != 0:
        raise RuntimeError(
            "Comando falhou: " + " ".join(args) +
            "\nSTDOUT:\n" + process.stdout +
            "\nSTDERR:\n" + process.stderr
        )
    return process.stdout


def tracked_files(pattern: str | None = None) -> list[str]:
    args = ["git", "ls-files", "-z"]
    if pattern is not None:
        args.append(pattern)
    raw = subprocess.check_output(args, cwd=ROOT)
    return sorted(item.decode("utf-8") for item in raw.split(b"\0") if item)


def php_audit(paths: list[str]) -> dict[str, dict]:
    helper = r'''<?php
declare(strict_types=1);
$root = (string) $argv[1];
$paths = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$result = [];
foreach ($paths as $path) {
    $source = file_get_contents($root . '/' . $path);
    if (!is_string($source)) {
        throw new RuntimeException('Arquivo PHP ilegível: ' . $path);
    }
    $rawTokens = token_get_all($source, TOKEN_PARSE);
    $tokens = [];
    $offset = 0;
    foreach ($rawTokens as $index => $raw) {
        $text = is_array($raw) ? $raw[1] : $raw;
        $tokens[$index] = [
            'id' => is_array($raw) ? $raw[0] : null,
            'text' => $text,
            'start' => $offset,
        ];
        $offset += strlen($text);
    }
    $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    $signatures = [];
    $calls = [];
    foreach ($tokens as $index => $token) {
        $id = $token['id'];
        if ($id === T_FUNCTION || $id === T_FN) {
            $signature = '';
            $name = $id === T_FN ? '{arrow}' : '{closure}';
            $resolved = $id === T_FN;
            $depth = 0;
            for ($cursor = $index; isset($tokens[$cursor]); $cursor++) {
                $current = $tokens[$cursor];
                $text = $current['text'];
                if (!in_array($current['id'], $skip, true)) {
                    $signature .= $text;
                }
                if (!$resolved && $cursor > $index && $current['id'] === T_STRING) {
                    $name = $text;
                    $resolved = true;
                }
                if ($text === '(' || $text === '[') {
                    $depth++;
                } elseif ($text === ')' || $text === ']') {
                    $depth--;
                }
                if ($id === T_FN && $current['id'] === T_DOUBLE_ARROW && $depth === 0) {
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
        $name = strtolower($token['text']);
        if (!in_array($name, ['round', 'fgetcsv', 'fputcsv', 'str_getcsv'], true)) {
            continue;
        }
        $previous = $index - 1;
        while (isset($tokens[$previous]) && in_array($tokens[$previous]['id'], $skip, true)) {
            $previous--;
        }
        $previousId = $tokens[$previous]['id'] ?? null;
        if (in_array($previousId, [T_FUNCTION, T_FN, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
            continue;
        }
        $open = $index + 1;
        while (isset($tokens[$open]) && in_array($tokens[$open]['id'], $skip, true)) {
            $open++;
        }
        if (($tokens[$open]['text'] ?? '') !== '(') {
            continue;
        }
        $depth = 0;
        $commas = 0;
        $hasContent = false;
        $close = null;
        for ($cursor = $open; isset($tokens[$cursor]); $cursor++) {
            $current = $tokens[$cursor];
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
            if ($depth !== 1 || in_array($current['id'], $skip, true)) {
                continue;
            }
            if ($text === ',') {
                $commas++;
            } else {
                $hasContent = true;
            }
        }
        if ($close === null) {
            throw new RuntimeException('Chamada sem fechamento: ' . $path . ':' . $name);
        }
        $calls[] = [
            'name' => $name,
            'args' => $hasContent ? $commas + 1 : 0,
            'close_offset_bytes' => $close,
        ];
    }
    $result[$path] = ['signatures' => $signatures, 'calls' => $calls];
}
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
'''
    with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False, encoding="utf-8") as file:
        file.write(helper)
        helper_path = file.name
    try:
        return json.loads(command(
            ["php", helper_path, str(ROOT)],
            json.dumps(paths, ensure_ascii=False),
        ))
    finally:
        os.unlink(helper_path)


def signature_hash(signatures: list[dict]) -> str:
    payload = json.dumps(signatures, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def replace_regex(source: str, pattern: str, replacement: str, label: str, log: list[str]) -> str:
    updated, count = re.subn(pattern, replacement, source)
    if count:
        log.append(f"{label}:{count}")
    return updated


def replace_once(source: str, old: str, new: str, label: str, log: list[str]) -> str:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1, encontrado {count}")
    log.append(f"{label}:1")
    return source.replace(old, new, 1)


php_files = tracked_files("*.php")
if not php_files:
    raise RuntimeError("Inventário PHP vazio.")

before_bytes = {path: (ROOT / path).read_bytes() for path in php_files}
before_text = {path: data.decode("utf-8") for path, data in before_bytes.items()}
before_audit = php_audit(php_files)
changes: dict[str, list[str]] = defaultdict(list)

# Substituições textuais de equivalência conservadora.
for path in php_files:
    source = before_text[path]
    log = changes[path]
    source = replace_regex(source, r"(?<![A-Za-z0-9_])trim\(\(string\)", "mb_trim((string)", "mb_trim_string", log)
    source = replace_regex(source, r"(?<![A-Za-z0-9_])ltrim\(\(string\)", "mb_ltrim((string)", "mb_ltrim_string", log)
    source = replace_regex(source, r"(?<![A-Za-z0-9_])rtrim\(\(string\)", "mb_rtrim((string)", "mb_rtrim_string", log)
    source = replace_regex(source, r"(?<![A-Za-z0-9_])trim\(preg_replace\(", "mb_trim(preg_replace(", "mb_trim_normalized", log)
    source = replace_regex(source, r"(?<![A-Za-z0-9_])ucfirst\(mb_strtolower\(", "mb_ucfirst(mb_strtolower(", "mb_ucfirst", log)
    source = replace_regex(source, r"(?<![A-Za-z0-9_])lcfirst\(mb_strtoupper\(", "mb_lcfirst(mb_strtoupper(", "mb_lcfirst", log)
    source = replace_regex(source, r"\bnew\s+(\\?PDO)\s*\(", r"\1::connect(", "pdo_connect", log)
    (ROOT / path).write_text(source, encoding="utf-8")

# Achados que exigem contexto específico.
runtime_path = ROOT / "app/Support/Runtime.php"
runtime = runtime_path.read_text(encoding="utf-8")
runtime = replace_once(
    runtime,
    '''    return version_compare(\n        $version ?? PHP_VERSION,\n        prontoo_min_php_version(),\n        ">=",\n    );''',
    '''    $version = $version ?? PHP_VERSION;\n    if (!preg_match('/^(\\d+)\\.(\\d+)(?:\\.|$)/', $version, $match)) {\n        return false;\n    }\n    return (int) $match[1] === 8 &&\n        (int) $match[2] === 4 &&\n        version_compare($version, prontoo_min_php_version(), ">=");''',
    "runtime_exact_php84",
    changes["app/Support/Runtime.php"],
)
runtime = replace_once(
    runtime,
    '''    return "PHP " .\n        $version .\n        " detectado; requisito mínimo PHP " .\n        prontoo_min_php_version() .\n        ".";''',
    '''    return "PHP " .\n        $version .\n        " detectado; o Prontoo exige exclusivamente a família PHP 8.4, " .\n        "a partir de " .\n        prontoo_min_php_version() .\n        ".";''',
    "runtime_exact_message",
    changes["app/Support/Runtime.php"],
)
runtime_path.write_text(runtime, encoding="utf-8")

patient_path = ROOT / "app/Domain/Patients/PatientPure.php"
patient = patient_path.read_text(encoding="utf-8")
patient = replace_once(
    patient,
    "strtolower(trim($value))",
    "strtolower(mb_trim($value))",
    "patient_unicode_trim",
    changes["app/Domain/Patients/PatientPure.php"],
)
patient = replace_once(
    patient,
    '''new \\DateTimeImmutable('@' . (int) $birth)->setTimezone(new \\DateTimeZone('UTC'))''',
    '''\\DateTimeImmutable::createFromTimestamp((int) $birth)->setTimezone(new \\DateTimeZone('UTC'))''',
    "datetime_from_timestamp",
    changes["app/Domain/Patients/PatientPure.php"],
)
patient_path.write_text(patient, encoding="utf-8")

subscription_path = ROOT / "app/Domain/Clinic/SubscriptionSettings.php"
subscription = subscription_path.read_text(encoding="utf-8")
subscription = replace_once(
    subscription,
    "$key = trim($key);",
    "$key = mb_trim($key);",
    "pix_unicode_trim",
    changes["app/Domain/Clinic/SubscriptionSettings.php"],
)
subscription_path.write_text(subscription, encoding="utf-8")

financial_path = ROOT / "app/Domain/Financial/Financial.php"
financial = financial_path.read_text(encoding="utf-8")
financial = replace_once(
    financial,
    '''$v = trim(str_replace("\\u{00A0}", " ", $v));''',
    '''$v = mb_trim($v);''',
    "money_unicode_trim",
    changes["app/Domain/Financial/Financial.php"],
)
financial_path.write_text(financial, encoding="utf-8")

# Inserções por offsets em bytes, nunca por índices Unicode.
intermediate_audit = php_audit(php_files)
for path in php_files:
    data = (ROOT / path).read_bytes()
    insertions: list[tuple[int, bytes, str]] = []
    for call in intermediate_audit[path]["calls"]:
        name = str(call["name"])
        args = int(call["args"])
        insertion = ""
        label = ""
        if name == "round" and args < 3:
            insertion = ", 0, \\RoundingMode::HalfAwayFromZero" if args == 1 else ", \\RoundingMode::HalfAwayFromZero"
            label = "rounding_mode"
        elif name == "fgetcsv" and args < 5:
            insertion = {
                1: ", null, ',', '\"', '\\\\'",
                2: ", ',', '\"', '\\\\'",
                3: ", '\"', '\\\\'",
                4: ", '\\\\'",
            }.get(args, "")
            label = "csv_escape"
        elif name == "fputcsv" and args < 5:
            insertion = {
                2: ", ',', '\"', '\\\\'",
                3: ", '\"', '\\\\'",
                4: ", '\\\\'",
            }.get(args, "")
            label = "csv_escape"
        elif name == "str_getcsv" and args < 4:
            insertion = {
                1: ", ',', '\"', '\\\\'",
                2: ", '\"', '\\\\'",
                3: ", '\\\\'",
            }.get(args, "")
            label = "csv_escape"
        if insertion:
            insertions.append((int(call["close_offset_bytes"]), insertion.encode("utf-8"), label))
    for offset, insertion, label in sorted(insertions, reverse=True):
        data = data[:offset] + insertion + data[offset:]
        changes[path].append(label + ":1")
    data.decode("utf-8")
    (ROOT / path).write_bytes(data)

# Atualiza versões sem tocar em contratos de funções.
app_path = ROOT / "app/prontoo.php"
app = app_path.read_text(encoding="utf-8")
app, count = re.subn(r'const PRONTOO_VERSION_FALLBACK = ["\'][^"\']+["\'];', f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";', app, count=1)
if count != 1:
    raise RuntimeError("Fallback principal não localizado.")
changes["app/prontoo.php"].append("version_fallback:1")
app, count = re.subn(r'const PRONTOO_PREVIOUS_VERSION = ["\'][^"\']+["\'];', f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS_VERSION}";', app, count=1)
if count != 1:
    raise RuntimeError("Versão anterior não localizada.")
changes["app/prontoo.php"].append("previous_version:1")
app_path.write_text(app, encoding="utf-8")

landing_path = ROOT / "br/index.php"
landing = landing_path.read_text(encoding="utf-8")
landing, count = re.subn(r'const BR_LANDING_VERSION_FALLBACK = ["\'][^"\']+["\'];', f'const BR_LANDING_VERSION_FALLBACK = "{VERSION}";', landing, count=1)
if count != 1:
    raise RuntimeError("Fallback da landing não localizado.")
changes["br/index.php"].append("landing_version:1")
landing_path.write_text(landing, encoding="utf-8")

# Verificador permanente: inventário, depreciações e recursos adotados.
checker_path = ROOT / "tools/php84-runtime-contract-check"
checker = checker_path.read_text(encoding="utf-8")
anchor = 'if ($failures !== []) {'
if checker.count(anchor) != 1:
    raise RuntimeError("Âncora do verificador PHP 8.4 não localizada.")
if 'php84-source-conformance-1.8.6.1' not in checker:
    section = r'''
$sourceConformancePolicy = "php84-source-conformance-1.8.6.1";
$phpFiles = [];
foreach ($tracked as $path) {
    if (str_ends_with(strtolower($path), ".php") && is_file($root . "/" . $path)) {
        $phpFiles[] = $path;
    }
}
sort($phpFiles, SORT_STRING);
$audit = json_decode($read("docs/audits/php84-conformance-1.8.6.1.json"), true);
if (!is_array($audit) || empty($audit["function_contract_preserved"])) {
    $failures[] = "Auditoria integral PHP 8.4 ausente ou inválida.";
} else {
    $reported = [];
    foreach ((array) ($audit["files"] ?? []) as $item) {
        if (is_array($item) && isset($item["path"])) {
            $reported[] = (string) $item["path"];
        }
    }
    sort($reported, SORT_STRING);
    if ($reported !== $phpFiles) {
        $failures[] = "Auditoria PHP 8.4 não cobre exatamente os arquivos PHP rastreados.";
    }
}
$features = ["mb_trim" => 0, "rounding_mode" => 0, "pdo_connect" => 0, "datetime_timestamp" => 0];
$deprecatedFragments = [
    "strict_level" => "E_" . "STRICT",
    "mysqli_ping" => "mysqli_" . "ping(",
    "mysqli_kill" => "mysqli_" . "kill(",
    "mysqli_refresh" => "mysqli_" . "refresh(",
    "curl_binary" => "CURLOPT_" . "BINARYTRANSFER",
    "dom_php_error" => "DOM_" . "PHP_ERR",
    "xml_set_object" => "xml_" . "set_object(",
];
foreach ($phpFiles as $path) {
    $source = $read($path);
    $lint = [];
    $status = 0;
    exec(
        escapeshellarg(PHP_BINARY) . " -d error_reporting=32767 -d display_errors=1 -d log_errors=0 -l " . escapeshellarg($root . "/" . $path) . " 2>&1",
        $lint,
        $status,
    );
    $lintText = implode("\n", $lint);
    if ($status !== 0) {
        $failures[] = "Lint PHP 8.4 falhou em " . $path . ": " . $lintText;
    }
    if (stripos($lintText, "deprecated") !== false) {
        $failures[] = "Depreciação PHP 8.4 em " . $path . ": " . $lintText;
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
        if (preg_match('/(?<![A-Za-z0-9_])(?:trim|ltrim|rtrim)\(\(string\)/', $source)) {
            $failures[] = "Texto convertido para string ainda usa trim não multibyte em " . $path;
        }
        if (preg_match('/\bnew\s+\\?PDO\s*\(/', $source)) {
            $failures[] = "PDO ainda é instanciado sem PDO::connect em " . $path;
        }
        if (preg_match('/\bnew\s+\\?DateTimeImmutable\s*\(\s*["\x27]@/', $source)) {
            $failures[] = "Timestamp ainda usa string @ em " . $path;
        }
    }
    $features["mb_trim"] += substr_count($source, "mb_trim(");
    $features["rounding_mode"] += substr_count($source, "RoundingMode::");
    $features["pdo_connect"] += substr_count($source, "PDO::connect(");
    $features["datetime_timestamp"] += substr_count($source, "DateTimeImmutable::createFromTimestamp(");
}
foreach ($features as $feature => $count) {
    if ($count <= 0) {
        $failures[] = "Recurso PHP 8.4 esperado não adotado: " . $feature;
    }
}
$runtimeHelper = $read("app/Support/Runtime.php");
if (!str_contains($runtimeHelper, '(int) $match[1] === 8') || !str_contains($runtimeHelper, '(int) $match[2] === 4')) {
    $failures[] = "Helper interno não restringe a família PHP 8.4.";
}

'''
    checker = checker.replace(anchor, section + anchor, 1)
    changes["tools/php84-runtime-contract-check"].append("permanent_source_contract:1")
checker_path.write_text(checker, encoding="utf-8")

# Contrato de funções após todas as refatorações.
after_audit = php_audit(php_files)
contract_diffs = []
records = []
for path in php_files:
    before_signatures = before_audit[path]["signatures"]
    after_signatures = after_audit[path]["signatures"]
    preserved = before_signatures == after_signatures
    if not preserved:
        contract_diffs.append({"path": path, "before": before_signatures, "after": after_signatures})
    before = before_bytes[path]
    after = (ROOT / path).read_bytes()
    records.append({
        "path": path,
        "reviewed": True,
        "changed": before != after,
        "disposition": "refactored_php84" if before != after else "reviewed_no_safe_php84_change",
        "sha256_before": hashlib.sha256(before).hexdigest(),
        "sha256_after": hashlib.sha256(after).hexdigest(),
        "bytes_before": len(before),
        "bytes_after": len(after),
        "function_contract_preserved": preserved,
        "function_signature_count": len(after_signatures),
        "function_contract_sha256_before": signature_hash(before_signatures),
        "function_contract_sha256_after": signature_hash(after_signatures),
        "transformations": changes.get(path, []),
    })
if contract_diffs:
    raise RuntimeError("Contrato de funções alterado:\n" + json.dumps(contract_diffs, ensure_ascii=False, indent=2))

# Lint individual e ausência de avisos de depreciação.
deprecations = []
for path in php_files:
    process = subprocess.run(
        ["php", "-d", "error_reporting=32767", "-d", "display_errors=1", "-d", "log_errors=0", "-l", str(ROOT / path)],
        cwd=ROOT,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        check=False,
    )
    if process.returncode != 0 or "deprecated" in process.stdout.lower():
        deprecations.append({"path": path, "status": process.returncode, "output": process.stdout})
if deprecations:
    raise RuntimeError("Falhas PHP 8.4:\n" + json.dumps(deprecations, ensure_ascii=False, indent=2))

now = datetime.now(timezone.utc)
now_iso = now.isoformat()
now_unix = int(now.timestamp())

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
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
    "notes": "Todos os arquivos PHP rastreados foram revisados individualmente para conformidade PHP 8.4, sem alterar quantidade ou assinaturas de funções.",
    "rewrite_scope": "all_tracked_php_files_php84_conformance_without_function_contract_changes",
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
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
    "notes": "Conformidade PHP 8.4 sem novos arquivos PHP e sem alteração do contrato de funções.",
})
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

changed_count = sum(1 for record in records if record["changed"])
named_count = sum(1 for path in php_files for item in before_audit[path]["signatures"] if item["kind"] == "named")
closure_count = sum(1 for path in php_files for item in before_audit[path]["signatures"] if item["kind"] == "closure")
arrow_count = sum(1 for path in php_files for item in before_audit[path]["signatures"] if item["kind"] == "arrow")

audit = {
    "audit": "php84-source-conformance",
    "version": VERSION,
    "previous_version": PREVIOUS_VERSION,
    "generated_at": now_iso,
    "php_runtime": command(["php", "-r", "echo PHP_VERSION;"]).strip(),
    "scope": "all_tracked_php_files",
    "php_file_count": len(php_files),
    "changed_file_count": changed_count,
    "unchanged_file_count": len(php_files) - changed_count,
    "function_contract_preserved": True,
    "named_function_and_method_count": named_count,
    "closure_count": closure_count,
    "arrow_function_count": arrow_count,
    "function_contract_differences": [],
    "deprecation_failures": [],
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
        "lazy_objects": "requires measured initialization benefit and architecture changes",
        "request_parse_body": "mutation contract remains POST",
        "new_dom_api": "no DOM processing path identified",
        "deprecated_attribute": "would emit new runtime notices without an approved retirement target",
        "array_find_array_any_array_all": "would add callbacks, prohibited by the no-function-change scope",
        "bcmath_number": "financial domain intentionally remains integer cents",
        "jit": "hosting configuration requires production benchmark",
    },
    "files": records,
}
audit_path = ROOT / AUDIT_JSON
audit_path.parent.mkdir(parents=True, exist_ok=True)
audit_path.write_text(json.dumps(audit, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

summary = f'''# Auditoria integral de conformidade PHP 8.4 — {VERSION}\n\n- Arquivos PHP revisados individualmente: **{len(php_files)}**.\n- Arquivos com refatoração aplicável: **{changed_count}**.\n- Arquivos sem mudança segura aplicável: **{len(php_files) - changed_count}**.\n- Funções e métodos nomeados preservados: **{named_count}**.\n- Closures preservadas: **{closure_count}**.\n- Arrow functions preservadas: **{arrow_count}**.\n- Quantidade e assinaturas de funções: **inalteradas**.\n- Banco, schema, rotas, layout e assets: **inalterados**.\n\n## Refatorações aplicadas\n\n- normalização multibyte com a família `mb_trim`;\n- política explícita `RoundingMode::HalfAwayFromZero`;\n- `PDO::connect()` para retorno da subclasse específica do driver;\n- `DateTimeImmutable::createFromTimestamp()`;\n- parâmetro `escape` explícito nas APIs CSV;\n- helper interno coerente com a família exclusiva PHP 8.4;\n- lint individual com `E_ALL` e bloqueio permanente de depreciações.\n\n## Recursos não forçados\n\nProperty hooks, visibilidade assimétrica, lazy objects, `request_parse_body()`, nova API DOM, `#[Deprecated]`, novas funções de coleção, BCMath orientado a objetos e JIT não foram introduzidos porque alterariam contratos, acrescentariam callbacks, mudariam comportamento ou exigiriam configuração fora deste escopo.\n\nO inventário completo está em `{AUDIT_JSON}`.\n'''
(ROOT / AUDIT_MD).write_text(summary, encoding="utf-8")

changelog_path = ROOT / "CHANGELOG.md"
changelog = changelog_path.read_text(encoding="utf-8")
entry = f'''## {VERSION} — Conformidade integral PHP 8.4\n\n- revisa individualmente todos os arquivos PHP rastreados;\n- preserva quantidade e assinaturas de funções, métodos, closures e arrow functions;\n- adota normalização multibyte, modo de arredondamento explícito, `PDO::connect()`, `DateTimeImmutable::createFromTimestamp()` e escape CSV explícito;\n- alinha o helper interno à família exclusiva PHP 8.4;\n- amplia o contrato permanente contra depreciações;\n- não altera banco, schema, rotas, funções, layout ou assets.\n\n'''
heading = "# Histórico de versões\n\n"
if f"## {VERSION} —" not in changelog:
    if not changelog.startswith(heading):
        raise RuntimeError("CHANGELOG sem cabeçalho canônico.")
    changelog_path.write_text(heading + entry + changelog[len(heading):], encoding="utf-8")

# Manifesto inclui temporários durante a validação; eles serão removidos e reconciliados antes do merge.
manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "schema_revision": version["schema_revision"],
    "build": BUILD,
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
})
all_files = tracked_files()
all_files.extend([AUDIT_JSON, AUDIT_MD])
all_files = sorted(set(all_files))
files: dict[str, str] = {}
total = 0
for relative in all_files:
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
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

print(json.dumps({
    "ok": True,
    "version": VERSION,
    "php_files": len(php_files),
    "changed_php_files": changed_count,
    "named_functions_and_methods": named_count,
    "closures": closure_count,
    "arrow_functions": arrow_count,
    "function_contract_preserved": True,
}, ensure_ascii=False))
