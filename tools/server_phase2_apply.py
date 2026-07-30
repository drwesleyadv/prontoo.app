from pathlib import Path
import json
import re
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.2"
PREVIOUS = "1.7.30.1"
BUILD = "1.7.30.2-server-phase2-cache-generations"
NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
NOW_UNIX = int(time.time())


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(path: str, old: str, new: str) -> None:
    target = ROOT / path
    source = target.read_text(encoding="utf-8")
    count = source.count(old)
    if count != 1:
        raise SystemExit(f"{path}: esperado 1 trecho, encontrado {count}")
    target.write_text(source.replace(old, new, 1), encoding="utf-8")


def update_json(path: str, values: dict) -> None:
    target = ROOT / path
    data = json.loads(target.read_text(encoding="utf-8"))
    data.update(values)
    target.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")


cache_path = ROOT / "app/Support/ServerJsonCache.php"
cache = cache_path.read_text(encoding="utf-8")
marker = "function server_json_cache_ttl(string $category): int\n"
if cache.count(marker) != 1:
    raise SystemExit("Marcador de TTL do cache divergente")
generation_functions = r'''function server_json_cache_generation_file(string $category): string
{

    return server_json_cache_category_dir($category) . "/generation.txt";
}

function server_json_cache_generation(string $category): int
{

    $category = preg_replace("/[^a-z0-9_\-]/i", "_", $category) ?: "general";
    $generations = $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"] ?? [];
    if (is_array($generations) && isset($generations[$category])) {
        return max(1, (int) $generations[$category]);
    }
    $raw = @file_get_contents(server_json_cache_generation_file($category));
    $generation = max(1, (int) trim(is_string($raw) ? $raw : "1"));
    if (!isset($GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"]) ||
        !is_array($GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"])) {
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"] = [];
    }
    $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"][$category] = $generation;
    return $generation;
}

function server_json_cache_bump_generation(string $category): int
{

    $category = preg_replace("/[^a-z0-9_\-]/i", "_", $category) ?: "general";
    $dir = server_json_cache_category_dir($category);
    $file = server_json_cache_generation_file($category);
    $handle = @fopen($file, "c+");
    if (!is_resource($handle)) {
        server_json_cache_rrmdir($dir);
        @mkdir($dir, 0750, true);
        @file_put_contents($file, "1\n", LOCK_EX);
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"][$category] = 1;
        server_json_cache_metric_add($category, "generation_fallback_clears");
        return 1;
    }
    try {
        if (!@flock($handle, LOCK_EX)) {
            throw new RuntimeException("Não foi possível bloquear a geração do cache.");
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $current = max(1, (int) trim(is_string($raw) ? $raw : "1"));
        $next = $current >= PHP_INT_MAX - 1 ? 1 : $current + 1;
        rewind($handle);
        ftruncate($handle, 0);
        if (fwrite($handle, (string) $next . "\n") === false || !fflush($handle)) {
            throw new RuntimeException("Não foi possível persistir a geração do cache.");
        }
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"][$category] = $next;
        server_json_cache_metric_add($category, "generation_bumps");
        return $next;
    } catch (Throwable $error) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
        server_json_cache_rrmdir($dir);
        @mkdir($dir, 0750, true);
        @file_put_contents($file, "1\n", LOCK_EX);
        $GLOBALS["PRONTOO_SERVER_JSON_CACHE_GENERATIONS"][$category] = 1;
        server_json_cache_metric_add($category, "generation_fallback_clears");
        error_log("[Prontoo cache generation] " . $error->getMessage());
        return 1;
    } finally {
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
}

'''
cache = cache.replace(marker, generation_functions + marker, 1)
old_file = '''function server_json_cache_file(string $category, string $key): string
{

    $key = preg_replace("/[^a-z0-9_\-\.]/i", "_", $key) ?: "cache";
    return server_json_cache_category_dir($category) . "/" . $key . ".json";
}
'''
new_file = '''function server_json_cache_file(string $category, string $key): string
{

    $key = preg_replace("/[^a-z0-9_\-\.]/i", "_", $key) ?: "cache";
    $categoryDir = server_json_cache_category_dir($category);
    $generationDir =
        $categoryDir .
        "/generation-" .
        sprintf("%020d", server_json_cache_generation($category));
    if (!is_dir($generationDir) &&
        !@mkdir($generationDir, 0750, true) &&
        !is_dir($generationDir)) {
        return $generationDir . "/" . $key . ".json";
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($generationDir);
    }
    return $generationDir . "/" . $key . ".json";
}
'''
if cache.count(old_file) != 1:
    raise SystemExit("Bloco de arquivo de cache divergente")
cache = cache.replace(old_file, new_file, 1)
old_clear = '''function server_json_cache_clear_categories(array $categories): void
{

    $categories = array_values(array_unique(array_map("strval", $categories)));
    if ($categories) {
        server_json_cache_metric_add("invalidation", "invalidations");
        server_json_cache_metric_add("invalidation", "invalidated_categories", count($categories));
    }
    foreach ($categories as $category) {
        server_json_cache_metric_add($category, "invalidations");
        $dir = server_json_cache_category_dir($category);
        server_json_cache_rrmdir($dir);
        server_json_cache_memory_forget_prefix($dir . "/");
    }
}
'''
new_clear = '''function server_json_cache_clear_categories(array $categories): void
{

    $categories = array_values(array_unique(array_map("strval", $categories)));
    if ($categories) {
        server_json_cache_metric_add("invalidation", "invalidations");
        server_json_cache_metric_add("invalidation", "invalidated_categories", count($categories));
    }
    foreach ($categories as $category) {
        server_json_cache_metric_add($category, "invalidations");
        $dir = server_json_cache_category_dir($category);
        server_json_cache_bump_generation($category);
        server_json_cache_memory_forget_prefix($dir . "/");
    }
}
'''
if cache.count(old_clear) != 1:
    raise SystemExit("Bloco de invalidação do cache divergente")
cache = cache.replace(old_clear, new_clear, 1)
cache_path.write_text(cache, encoding="utf-8")

replace_once(
    "app/Support/Telemetry.php",
    '        "invalidations" => 0,\n        "invalidated_categories" => 0,',
    '        "invalidations" => 0,\n        "invalidated_categories" => 0,\n        "generation_bumps" => 0,\n        "generation_fallback_clears" => 0,',
)

architecture_check = ROOT / "tools/architecture-check.php"
source = architecture_check.read_text(encoding="utf-8")
marker = "$result = [\n"
marker_index = source.rfind(marker)
if marker_index < 0:
    raise SystemExit("Marcador final do architecture-check.php divergente")
block = r'''$serverPhaseTwoFailures = [];
$cacheGenerationSource = (string) file_get_contents($root . '/app/Support/ServerJsonCache.php');
foreach ([
    'function server_json_cache_generation_file(',
    'function server_json_cache_generation(',
    'function server_json_cache_bump_generation(',
    '"/generation-"',
    'ftruncate($handle, 0)',
    'server_json_cache_bump_generation($category)',
    'generation_fallback_clears',
] as $requiredToken) {
    if (!str_contains($cacheGenerationSource, $requiredToken)) {
        $serverPhaseTwoFailures[] = 'cache_generation_missing:' . $requiredToken;
    }
}
$clearStart = strpos($cacheGenerationSource, 'function server_json_cache_clear_categories(');
$clearEnd = $clearStart === false
    ? false
    : strpos($cacheGenerationSource, 'function server_json_cache_all_categories(', $clearStart);
$clearSource = $clearStart !== false && $clearEnd !== false
    ? substr($cacheGenerationSource, $clearStart, $clearEnd - $clearStart)
    : '';
if ($clearSource === '' || str_contains($clearSource, 'server_json_cache_rrmdir($dir)')) {
    $serverPhaseTwoFailures[] = 'cache_invalidation_still_recursive';
}
if (str_contains($cacheGenerationSource, 'return server_json_cache_category_dir($category) . "/" . $key . ".json";')) {
    $serverPhaseTwoFailures[] = 'cache_key_without_generation';
}
$serverPhaseTwoCharacterization = [
    'ok' => $serverPhaseTwoFailures === [],
    'failed' => $serverPhaseTwoFailures,
];
'''
source = source[:marker_index] + block + source[marker_index:]
source = source.replace(
    "        !empty($serverPhaseOneCharacterization['ok']),",
    "        !empty($serverPhaseOneCharacterization['ok']) &&\n        !empty($serverPhaseTwoCharacterization['ok']),",
    1,
)
source = source.replace(
    "    'server_phase_one_characterization' => $serverPhaseOneCharacterization,",
    "    'server_phase_one_characterization' => $serverPhaseOneCharacterization,\n    'server_phase_two_characterization' => $serverPhaseTwoCharacterization,",
    1,
)
architecture_check.write_text(source, encoding="utf-8")

write("docs/performance/cache-generations.md", """# Invalidação de cache por geração

## Objetivo

A Fase 2 substitui a exclusão recursiva de categorias durante gravações por troca atômica de geração. A mutação deixa de percorrer e remover todos os arquivos da categoria no caminho da requisição.

## Funcionamento

Cada categoria mantém um contador persistente. A chave física inclui a geração atual. Uma invalidação incrementa o contador sob bloqueio exclusivo e limpa apenas a memória da requisição. Arquivos de gerações anteriores deixam de ser endereçáveis imediatamente.

## Falha segura

Se o contador não puder ser bloqueado ou persistido, o sistema executa a limpeza física da categoria e reinicia a geração. A coerência prevalece sobre o ganho de desempenho.

## Conformidade

- gerações são separadas por categoria e as chaves continuam incluindo o escopo funcional definido pelo chamador;
- não são armazenados segredos, senhas ou fatores de autenticação;
- a invalidação permanece obrigatória após mutações;
- gerações antigas podem ser removidas apenas por manutenção assíncrona, nunca como requisito para a consistência da resposta atual.
""")

changelog = ROOT / "CHANGELOG.md"
text = changelog.read_text(encoding="utf-8")
entry = """\n## 1.7.30.2 — Evolução do servidor, Fase 2: gerações de cache

- substitui a invalidação recursiva por troca atômica de geração;
- mantém fallback físico fail-safe quando a geração não pode ser persistida;
- adiciona métricas de troca e fallback;
- preserva TTL, chaves, isolamento e invalidação após mutações;
- não altera banco, schema ou interface.\n"""
if entry.strip() not in text:
    changelog.write_text(text.replace("# Histórico de versões\n", "# Histórico de versões\n" + entry, 1), encoding="utf-8")
change_txt = ROOT / "ChangeLog.txt"
text = change_txt.read_text(encoding="utf-8")
entry = """Prontoo 1.7.30.2 — Evolução do servidor, Fase 2: gerações de cache

- Troca invalidação recursiva por gerações atômicas com fallback seguro.
- Mantém banco, schema, interface, permissões e comportamento funcional.
"""
if not text.startswith("Prontoo 1.7.30.2"):
    change_txt.write_text(entry + text, encoding="utf-8")
docs_index = ROOT / "docs/index.md"
text = docs_index.read_text(encoding="utf-8")
line = "- [Invalidação de cache por geração](performance/cache-generations.md)\n"
if line not in text:
    docs_index.write_text(text.rstrip() + "\n" + line, encoding="utf-8")

for path, constant in [("app/prontoo.php", "PRONTOO_VERSION_FALLBACK"), ("br/index.php", "BR_LANDING_VERSION_FALLBACK")]:
    target = ROOT / path
    source = target.read_text(encoding="utf-8")
    pattern = rf"(const\s+{constant}\s*=\s*['\"])[^'\"]+(['\"]\s*;)"
    source, count = re.subn(pattern, rf"\g<1>{VERSION}\g<2>", source, count=1)
    if count != 1:
        raise SystemExit(f"Fallback não localizado: {path}")
    target.write_text(source, encoding="utf-8")

common = {
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "package_type": "incremental_refactor",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": False,
    "visual_changes": False,
    "functional_equivalence_policy": "preserves_1_7_30_1_runtime_behavior",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "notes": "Substitui invalidação recursiva do cache por gerações atômicas com fallback seguro.",
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "server_json_cache_generation_invalidation",
    "deployment_sync_id": "github-server-phase2-cache-generations-1-7-30-2",
    "deployment_sync_requested_at": NOW,
}
update_json("version.json", common)
update_json("app/update.manifest.json", common)
update_json("app/architecture.manifest.json", {
    "version": VERSION,
    "generated_at": NOW,
    "updated_at": NOW,
    "baseline_source": PREVIOUS,
    "native_files_min": 30,
    "transitional_files_max": 80,
    "json_cache_policy": "category_generation_keyed_atomic_invalidation_with_fail_safe_physical_fallback",
    "server_evolution_phase_two_policy": "cache_invalidations_switch_generation_without_recursive_request_path_deletion",
    "server_evolution_phase_two_components": ["app/Support/ServerJsonCache.php", "app/Support/Telemetry.php"],
    "server_evolution_phase_two_check": "tools/architecture-check.php",
})
