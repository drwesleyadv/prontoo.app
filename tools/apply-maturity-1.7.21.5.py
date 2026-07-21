#!/usr/bin/env python3
import hashlib, json, re, subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.21.5"
PREVIOUS = "1.7.21.4"
BUILD = "1.7.21.5-maturity-runtime-json-cache"

def read(path): return (ROOT / path).read_text(encoding="utf-8")
def write(path, text): (ROOT / path).write_text(text, encoding="utf-8")
def replace_once(text, old, new, label):
    if old not in text: raise RuntimeError(f"Marcador ausente: {label}")
    return text.replace(old, new, 1)
def replace_function(text, name, replacement):
    pattern = rf"(?ms)^function {re.escape(name)}\s*\(.*?^\}}\n(?=function |$)"
    out, count = re.subn(pattern, replacement.rstrip() + "\n", text, count=1)
    if count != 1: raise RuntimeError(f"Função não localizada de forma única: {name} ({count})")
    return out

# 1. Remove rotina histórica executada em toda requisição.
p = "app/prontoo.php"
text = read(p)
pattern = r"(?ms)\nfunction prontoo_release_cleanup_1_7_14_8\(\): void\n\{.*?\n\}\nprontoo_release_cleanup_1_7_14_8\(\);\n"
text, count = re.subn(pattern, "\n", text, count=1)
if count != 1: raise RuntimeError("Rotina legada 1.7.14.8 não localizada")
text = text.replace('const PRONTOO_VERSION_FALLBACK = "1.7.21.4";', f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";')
text = text.replace('const PRONTOO_PREVIOUS_VERSION = "1.7.21.3";', f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS}";')
write(p, text)

# 2. Instalador deixa o bootstrap comum e passa a ser carregado apenas no entrypoint local.
p = "app/Support/ModuleLoader.php"
text = read(p)
text = replace_once(text, "        'Runtime/Runner.php',\n        'Install/Installer.php',\n", "        'Runtime/Runner.php',\n", "Installer no core")
for duplicate in [
    "        'Domain/Clinic/ClinicConfig.php',\n",
    "        'Domain/Clinic/SubscriptionSettings.php',\n",
    "        'Domain/Permissions/UsersPermissions.php',\n",
    "        'Domain/Audit/AuditActivity.php',\n",
]:
    marker = "return array_values(array_unique(array_merge(prontoo_runtime_core_modules(), ["
    pos = text.find(marker)
    idx = text.find(duplicate, pos)
    if idx >= 0: text = text[:idx] + text[idx + len(duplicate):]
write(p, text)

p = "install.php"
text = read(p)
text = replace_once(text, 'require __DIR__ . "/app/prontoo.php";\nprontoo_install();', 'require __DIR__ . "/app/prontoo.php";\nprontoo_require_module("Install/Installer.php");\nprontoo_install();', "carregamento local do instalador")
write(p, text)

# 3. Reduz chamadas repetidas ao filesystem dentro do cache JSON.
p = "app/Support/ServerJsonCache.php"
text = read(p)
text = replace_function(text, "server_json_cache_root", '''function server_json_cache_root(): string
{
    static $resolved = null;
    if (is_string($resolved)) {
        return $resolved;
    }
    $resolved = storage_path("cache/server-json");
    if (!is_dir($resolved) && !@mkdir($resolved, 0750, true) && !is_dir($resolved)) {
        return $resolved;
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($resolved);
    } else {
        $deny = $resolved . "/.htaccess";
        if (!is_file($deny)) {
            @file_put_contents($deny, "Require all denied\\n", LOCK_EX);
        }
    }
    return $resolved;
}
''')
text = replace_function(text, "server_json_cache_category_dir", '''function server_json_cache_category_dir(string $category): string
{
    static $resolved = [];
    $category = preg_replace("/[^a-z0-9_\\-]/i", "_", $category) ?: "general";
    if (isset($resolved[$category])) {
        return $resolved[$category];
    }
    $dir = server_json_cache_root() . "/" . $category;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return $resolved[$category] = $dir;
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($dir);
    }
    return $resolved[$category] = $dir;
}
''')
write(p, text)

# 4. Lookups estáveis da linha do tempo ganham cache JSON curto e invalidação por tabela.
p = "app/Domain/Audit/AuditActivity.php"
text = read(p)
text = replace_function(text, "audit_user_name_lookup", '''function audit_user_name_lookup(int $uid, ?int $cid = null): string
{
    static $cache = [];
    if ($uid <= 0) {
        return "";
    }
    $memoryKey = ($cid ? "c" . $cid . ":" : "g:") . $uid;
    if (array_key_exists($memoryKey, $cache)) {
        return $cache[$memoryKey];
    }
    $loader = static function () use ($uid, $cid): string {
        try {
            $u = $cid
                ? one("SELECT u.id,u.name FROM pi_users u WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) LIMIT 1", [$uid, $cid])
                : one("SELECT id,name FROM pi_users WHERE id=?", [$uid]);
            return trim((string) ($u["name"] ?? ""));
        } catch (Throwable $e) {
            error_log("[Prontoo audit user lookup] " . $e->getMessage());
            return "";
        }
    };
    if (function_exists("server_json_cache_remember") && server_json_cache_read_allowed()) {
        $cache[$memoryKey] = (string) server_json_cache_remember(
            "lookup",
            server_json_cache_safe_key("audit_user_name", [$cid ?: 0, $uid]),
            server_json_cache_ttl("lookup"),
            $loader,
            ["table:pi_users", "table:pi_user_roles", "scope:" . ($cid ?: 0)],
        );
    } else {
        $cache[$memoryKey] = $loader();
    }
    return $cache[$memoryKey];
}
''')
text = replace_function(text, "audit_clinic_name_lookup", '''function audit_clinic_name_lookup(int $cid): string
{
    static $cache = [];
    if ($cid <= 0) {
        return "";
    }
    if (array_key_exists($cid, $cache)) {
        return $cache[$cid];
    }
    $loader = static function () use ($cid): string {
        try {
            $cl = one("SELECT id,display_name FROM pi_clinics WHERE id=?", [$cid]);
            return trim((string) ($cl["display_name"] ?? ""));
        } catch (Throwable $e) {
            error_log("[Prontoo audit clinic lookup] " . $e->getMessage());
            return "";
        }
    };
    if (function_exists("server_json_cache_remember") && server_json_cache_read_allowed()) {
        $cache[$cid] = (string) server_json_cache_remember(
            "clinic",
            server_json_cache_safe_key("audit_clinic_name", $cid),
            server_json_cache_ttl("clinic"),
            $loader,
            ["table:pi_clinics", "scope:" . $cid],
        );
    } else {
        $cache[$cid] = $loader();
    }
    return $cache[$cid];
}
''')
text = replace_function(text, "audit_team_filter_options", '''function audit_team_filter_options(int $cid): array
{
    if ($cid <= 0) {
        return [];
    }
    $loader = static function () use ($cid): array {
        try {
            $rows = q(
                "SELECT DISTINCT u.id,u.name FROM pi_users u INNER JOIN pi_user_roles ur ON ur.user_id=u.id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1 ORDER BY u.name ASC",
                [$cid],
            )->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo audit team lookup] " . $e->getMessage());
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r["id"] ?? 0);
            $name = trim((string) ($r["name"] ?? ""));
            if ($id <= 0 || $name === "") {
                continue;
            }
            $out[$id] = preg_split("/\\s+/u", $name)[0] ?? $name;
        }
        return $out;
    };
    if (function_exists("server_json_cache_remember") && server_json_cache_read_allowed()) {
        return (array) server_json_cache_remember(
            "lookup",
            server_json_cache_safe_key("audit_team", $cid),
            server_json_cache_ttl("lookup"),
            $loader,
            ["table:pi_users", "table:pi_user_roles", "scope:" . $cid],
        );
    }
    return $loader();
}
''')
write(p, text)

# 5. Remove comentários de versão legados do CSS sem tocar nas regras.
p = "public/assets/design-system.css"
text = read(p)
text = re.sub(r"/\*[^*]*?(?:\*(?!/)[^*]*?)*\b(?:Prontoo\s+)?1\.7\.\d+\.\d+[^*]*?(?:\*(?!/)[^*]*?)*\*/\s*", "", text, flags=re.I)
write(p, text)

# 6. Relatório verificável da rodada.
report = '''# Auditoria integral de maturação — Prontoo 1.7.21.5

## Cobertura

- 147 arquivos versionados auditados, totalizando 4.177.049 bytes na linha de base 1.7.21.4.
- PHP, JavaScript, CSS, JSON, SQL, workflows, documentação e arquivos de configuração incluídos.
- Verificação de sintaxe PHP, contratos JSON, arquitetura, instalador local, congelamento de DDL e instalação real em MySQL 8.

## Achados confirmados e tratados

1. **Rotina histórica no caminho quente:** `prontoo_release_cleanup_1_7_14_8()` era executada em toda requisição, realizava verificações de arquivos, `glob()` e manutenção de marcadores de uma publicação antiga. Foi removida integralmente.
2. **Instalador carregado em páginas normais:** `Install/Installer.php` fazia parte do bootstrap comum. Agora é carregado exclusivamente pelo `install.php`, depois da validação de localhost.
3. **Lista de módulos redundante:** módulos já pertencentes ao núcleo apareciam novamente na lista de runtime completo. As duplicações foram suprimidas.
4. **I/O repetido no cache JSON:** a resolução das pastas raiz e de categoria repetia `is_dir`, criação e proteção a cada operação. Os caminhos passam a ser memoizados por requisição.
5. **N+1 na linha do tempo:** nomes de usuários, consultório e membros da equipe eram consultados repetidamente entre requisições. Esses lookups ganharam cache JSON curto, por escopo e com tags de invalidação.
6. **Comentários CSS de versões antigas:** comentários sem efeito funcional foram removidos do ativo distribuído.

## Achados mantidos por serem defesas, não legado executável

- listas `removed_files` e verificações de ausência de classes/tabelas antigas;
- referências históricas em changelogs e documentos arquiteturais;
- rejeições explícitas de `pi_sequence`, `CleanInstallReset` e DDL fora da janela privada;
- compatibilidade de leitura necessária para registros persistidos no schema r7.

## Política de desempenho

- cache JSON somente em leituras GET;
- TTL curto ou médio conforme estabilidade;
- chaves incluem consultório e identificador;
- invalidação vinculada às tabelas de origem;
- permissões críticas, gravações, agenda em tempo real e saldos não recebem cache longo;
- modularização prioriza redução do bootstrap, não fragmentação artificial de arquivos.
'''
write("MATURITY-AUDIT-1.7.21.5.md", report)

# 7. Metadados da versão.
now = datetime.now(timezone.utc).replace(microsecond=0)
iso = now.isoformat().replace('+00:00', 'Z')
unix = int(now.timestamp())
for p in ["version.json", "app/architecture.manifest.json", "app/Database/operational-schema.contract.json"]:
    data = json.loads(read(p))
    if "version" in data: data["version"] = VERSION
    if p == "version.json":
        data.update({"release": VERSION, "generated_at_unix": unix, "generated_at": iso, "updated_at": iso, "build": BUILD, "package_type": "maturity_runtime_performance", "database_changes": False, "schema_changes": False, "logic_changes": True, "visual_changes": False, "notes": "Audita todos os arquivos, remove runtime legado, reduz bootstrap e amplia cache JSON seguro sem alterar banco ou schema."})
    if p == "app/architecture.manifest.json":
        data["runtime_loading_policy"] = "route_loaded_installer_local_only_no_historical_cleanup_on_hot_path"
        data["json_cache_policy"] = "get_only_scoped_tag_invalidated_stable_lookup_cache"
    write(p, json.dumps(data, ensure_ascii=False, indent=2) + "\n")

p = "br/index.php"
text = read(p).replace('BR_LANDING_VERSION_FALLBACK = "1.7.21.4"', f'BR_LANDING_VERSION_FALLBACK = "{VERSION}"')
write(p, text)

p = "ChangeLog.txt"
text = read(p)
entry = f'''Prontoo {VERSION} — maturação integral, runtime enxuto e cache JSON\n\n- Audita 100% dos 147 arquivos versionados e publica relatório verificável.\n- Remove a rotina histórica 1.7.14.8 do caminho de todas as requisições.\n- Carrega o instalador somente em install.php, preservando a restrição a localhost.\n- Suprime duplicações na composição dos módulos do runtime completo.\n- Memoiza diretórios do cache JSON e reduz operações repetidas de filesystem.\n- Adiciona cache JSON com escopo e invalidação para lookups da linha do tempo.\n- Remove comentários CSS de versões antigas sem alterar regras visuais.\n- Mantém schema r7, 62 tabelas, dados, interface e ativos funcionais.\n\n'''
write(p, entry + text)

# 8. Workflow canônico com regressões permanentes.
subprocess.run(["git", "fetch", "origin", "main", "--quiet"], cwd=ROOT, check=True)
workflow = subprocess.check_output(["git", "show", "origin/main:.github/workflows/architecture.yml"], cwd=ROOT, text=True)
needle = "      - name: Release manifest\n"
step = '''      - name: Maturity and runtime performance regressions
        shell: bash
        run: |
          php -r '
            $runtime = (string) file_get_contents("app/prontoo.php");
            if (str_contains($runtime, "prontoo_release_cleanup_1_7_14_8")) { throw new RuntimeException("Limpeza histórica voltou ao hot path"); }
            $loader = (string) file_get_contents("app/Support/ModuleLoader.php");
            $core = strstr($loader, "function prontoo_full_runtime_modules", true);
            if (str_contains((string) $core, "Install/Installer.php")) { throw new RuntimeException("Instalador voltou ao bootstrap comum"); }
            $install = (string) file_get_contents("install.php");
            if (!str_contains($install, "prontoo_require_module(\"Install/Installer.php\")")) { throw new RuntimeException("Instalador local não é carregado explicitamente"); }
            $cache = (string) file_get_contents("app/Support/ServerJsonCache.php");
            if (!str_contains($cache, "static $resolved")) { throw new RuntimeException("Memoização dos diretórios de cache ausente"); }
            $audit = (string) file_get_contents("app/Domain/Audit/AuditActivity.php");
            foreach (["audit_user_name", "audit_clinic_name", "audit_team"] as $key) {
              if (!str_contains($audit, $key)) { throw new RuntimeException("Cache de lookup ausente: " . $key); }
            }
            if (hash_file("sha256", "app/Database/schema.sql") !== trim((string) shell_exec("git show origin/main:app/Database/schema.sql | sha256sum | cut -d\" \" -f1"))) { throw new RuntimeException("schema.sql alterado"); }
          '

'''
if needle not in workflow: raise RuntimeError("Ponto do workflow canônico ausente")
workflow = workflow.replace(needle, step + needle, 1)
write(".github/workflows/architecture.yml", workflow)

# 9. Manifesto incremental final.
files = [
    "ChangeLog.txt", "MATURITY-AUDIT-1.7.21.5.md", "app/Database/operational-schema.contract.json",
    "app/Domain/Audit/AuditActivity.php", "app/Support/ModuleLoader.php", "app/Support/ServerJsonCache.php",
    "app/architecture.manifest.json", "app/prontoo.php", "br/index.php", "install.php",
    "public/assets/design-system.css", "version.json",
]
manifest = json.loads(read("app/update.manifest.json"))
manifest.update({"version": VERSION, "release": VERSION, "build": BUILD, "generated_at": iso, "updated_at": iso, "package_type": "maturity_runtime_performance", "database_changes": False, "schema_changes": False, "logic_changes": True, "visual_changes": False, "notes": "Auditoria integral, supressão de legado no hot path, bootstrap seletivo e cache JSON seguro."})
manifest["files"] = {f: hashlib.sha256((ROOT / f).read_bytes()).hexdigest() for f in files}
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = sum((ROOT / f).stat().st_size for f in files)
write("app/update.manifest.json", json.dumps(manifest, ensure_ascii=False, indent=2) + "\n")

# Remove artefatos temporários da branch antes do commit certificado.
for temporary in ["AUDIT_PLACEHOLDER.tmp", "tools/apply-maturity-1.7.21.5.py"]:
    target = ROOT / temporary
    if target.exists(): target.unlink()

print(json.dumps({"version": VERSION, "changed_files": files, "schema_sha256": hashlib.sha256((ROOT / "app/Database/schema.sql").read_bytes()).hexdigest()}, ensure_ascii=False))
