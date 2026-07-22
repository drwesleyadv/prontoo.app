from __future__ import annotations

import hashlib
import json
import re
import subprocess
from pathlib import Path

ROOT = Path('.')
VERSION = '1.7.22.6'
PREVIOUS = '1.7.22.5'
STAMP = '2026-07-22T19:45:00Z'
STAMP_UNIX = 1784749500
BUILD = '1.7.22.6-ssd-persistence-policy'
PACKAGE = 'ssd_persistence_policy'
SYNC_ID = 'hostoo-ssd-persistence-20260722T194500Z'


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def save(path: str, value: str) -> None:
    (ROOT / path).write_text(value, encoding='utf-8')


def jload(path: str) -> dict:
    value = json.loads(text(path))
    if not isinstance(value, dict):
        raise RuntimeError(f'JSON inválido: {path}')
    return value


def jsave(path: str, value: dict, indent: int = 2) -> None:
    save(path, json.dumps(value, ensure_ascii=False, indent=indent) + '\n')


save('.gitignore', '''# Configuração e credenciais da instalação
/app/config.php
/.env
/.env.*

# Persistência canônica da instalação
/ssd/

# Entradas legadas preservadas até a migração automática
/storage/
/pdfs/

# Logs e locks locais
/error_log
*.log
*.lock

# Arquivos locais de ferramentas e sistema operacional
/.idea/
/.vscode/
/.DS_Store
Thumbs.db
''')

htaccess = text('.htaccess')
htaccess = htaccess.replace(
    '    RewriteRule ^storage/ - [F,L]\n',
    '    RewriteRule ^ssd/ - [F,L,NC]\n    RewriteRule ^storage/ - [F,L,NC]\n',
)
save('.htaccess', htaccess)

foundation = text('app/Support/Foundation.php').replace(
    'return app_root() . "/storage" . ($path ? "/" . ltrim($path, "/") : "");',
    'return app_root() . "/ssd" . ($path ? "/" . ltrim($path, "/") : "");',
)
save('app/Support/Foundation.php', foundation)

landing_telemetry = text('br/runtime-telemetry.php').replace(
    'dirname(__DIR__) . DIRECTORY_SEPARATOR . "storage"',
    'dirname(__DIR__) . DIRECTORY_SEPARATOR . "ssd"',
)
save('br/runtime-telemetry.php', landing_telemetry)

schema_runtime = text('app/Database/DatabaseSchema.php').replace(
    'dirname(__DIR__, 2) . "/storage/cache/runtime-notices"',
    'dirname(__DIR__, 2) . "/ssd/cache/runtime-notices"',
)
save('app/Database/DatabaseSchema.php', schema_runtime)

runtime_contract = text('app/Core/Install/RuntimeContract.php').replace(
    "$root . '/storage/install.lock'",
    "$root . '/ssd/install.lock'",
)
save('app/Core/Install/RuntimeContract.php', runtime_contract)

prontoo = text('app/prontoo.php')
prontoo = re.sub(r'const PRONTOO_VERSION_FALLBACK = "[^"]+";', f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";', prontoo, count=1)
prontoo = re.sub(r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";', f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS}";', prontoo, count=1)
prontoo = prontoo.replace('PRONTOO_ROOT . "/storage/logs"', 'PRONTOO_ROOT . "/ssd/logs"')
marker = '// PRONTOO_SSD_PERSISTENCE_POLICY'
if marker not in prontoo:
    block = r'''// PRONTOO_SSD_PERSISTENCE_POLICY
if (!defined("PRONTOO_SSD_ROOT")) {
    define("PRONTOO_SSD_ROOT", PRONTOO_ROOT . "/ssd");
    define("PRONTOO_PDF_ROOT", PRONTOO_SSD_ROOT . "/pdfs");
    define("PRONTOO_IMAGE_UPLOAD_ROOT", PRONTOO_SSD_ROOT . "/img");
}
$prontooPersistencePairs = [
    [PRONTOO_ROOT . "/storage", PRONTOO_SSD_ROOT],
    [PRONTOO_ROOT . "/pdfs", PRONTOO_PDF_ROOT],
];
foreach ($prontooPersistencePairs as [$prontooLegacyRoot, $prontooCanonicalRoot]) {
    if (!is_dir($prontooLegacyRoot) || is_link($prontooLegacyRoot)) {
        continue;
    }
    if (!is_dir($prontooCanonicalRoot)) {
        $prontooCanonicalParent = dirname($prontooCanonicalRoot);
        if (!is_dir($prontooCanonicalParent)) {
            @mkdir($prontooCanonicalParent, 0750, true);
        }
        if (@rename($prontooLegacyRoot, $prontooCanonicalRoot)) {
            @chmod($prontooCanonicalRoot, 0750);
            continue;
        }
        @mkdir($prontooCanonicalRoot, 0750, true);
    }
    try {
        $prontooLegacyIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($prontooLegacyRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($prontooLegacyIterator as $prontooLegacyEntry) {
            if ($prontooLegacyEntry->isLink()) {
                continue;
            }
            $prontooLegacyPath = $prontooLegacyEntry->getPathname();
            $prontooRelativePath = substr($prontooLegacyPath, strlen($prontooLegacyRoot) + 1);
            if ($prontooRelativePath === false || $prontooRelativePath === "") {
                continue;
            }
            $prontooCanonicalPath = $prontooCanonicalRoot . DIRECTORY_SEPARATOR . $prontooRelativePath;
            if ($prontooLegacyEntry->isDir()) {
                if (!is_dir($prontooCanonicalPath)) {
                    @mkdir($prontooCanonicalPath, 0750, true);
                }
                @rmdir($prontooLegacyPath);
                continue;
            }
            $prontooCanonicalParent = dirname($prontooCanonicalPath);
            if (!is_dir($prontooCanonicalParent)) {
                @mkdir($prontooCanonicalParent, 0750, true);
            }
            if (!file_exists($prontooCanonicalPath)) {
                @rename($prontooLegacyPath, $prontooCanonicalPath);
            }
        }
        @rmdir($prontooLegacyRoot);
    } catch (Throwable $prontooPersistenceError) {
        error_log("[Prontoo SSD migration] " . $prontooPersistenceError->getMessage());
    }
}
foreach ([PRONTOO_SSD_ROOT, PRONTOO_PDF_ROOT, PRONTOO_IMAGE_UPLOAD_ROOT] as $prontooPersistentDir) {
    if (!is_dir($prontooPersistentDir)) {
        @mkdir($prontooPersistentDir, 0750, true);
    }
    if (is_dir($prontooPersistentDir)) {
        @chmod($prontooPersistentDir, 0750);
    }
}
unset(
    $prontooPersistencePairs,
    $prontooLegacyRoot,
    $prontooCanonicalRoot,
    $prontooCanonicalParent,
    $prontooLegacyIterator,
    $prontooLegacyEntry,
    $prontooLegacyPath,
    $prontooRelativePath,
    $prontooCanonicalPath,
    $prontooPersistenceError,
    $prontooPersistentDir,
);
'''
    prontoo = prontoo.replace('if (!function_exists("mb_substr")) {', block + 'if (!function_exists("mb_substr")) {', 1)
save('app/prontoo.php', prontoo)

doc = text('app/Domain/Documents/DocumentPdf.php')
new_public_router = '''function document_pdf_public_router_dir(): string
{
    /*
     * GUIA DE MANUTENÇÃO — document_pdf_public_router_dir
     * Responsabilidade: Resolve o armazenamento físico protegido dos PDFs, mantendo a URL pública abstrata sob controle do roteador autenticado.
     * Local arquitetural: app/Domain/Documents/DocumentPdf.php (domínio e regras de negócio).
     * Chamadores detectados: `document_pdf_dir`.
     * Dependências chamadas: `storage_path`, `is_dir`, `mkdir`, `function_exists`, `security_storage_deny_file`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: PDFs físicos devem permanecer exclusivamente em `/ssd/pdfs/`; não recrie o diretório raiz `/pdfs/`.
     */
    $dir = storage_path("pdfs");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($dir);
    }
    return $dir;
}
function document_pdf_storage_dir'''
doc = re.sub(
    r'function document_pdf_public_router_dir\(\): string\n\{.*?\n\}\nfunction document_pdf_storage_dir',
    new_public_router,
    doc,
    count=1,
    flags=re.S,
)
save('app/Domain/Documents/DocumentPdf.php', doc)

subscription = text('app/Domain/Clinic/SubscriptionSettings.php')
new_storage = '''function subscription_payment_proof_storage(int $cid, bool $image = false): array
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_storage
     * Responsabilidade: Resolve comprovantes no SSD, separando imagens sob `/ssd/img/`.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_payment_proof_upload`.
     * Dependências chamadas: `RuntimeException`, `storage_path`, `is_link`, `is_dir`, `mkdir`, `chmod`, `realpath`, `str_starts_with`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: acessa o sistema de arquivos; pode interromper o fluxo por exceção.
     * Cuidado 1: Imagens enviadas devem permanecer exclusivamente sob `/ssd/img/`.
     */
    if ($cid <= 0) {
        throw new RuntimeException("Consultório inválido para o comprovante.");
    }
    $relativeRoot = $image ? "ssd/img/payment-proofs" : "ssd/payment-proofs";
    $root = $image ? storage_path("img/payment-proofs") : storage_path("payment-proofs");
    $directory = $root . "/clinic-" . $cid;
    foreach ([$root, $directory] as $path) {
        if (is_link($path)) {
            throw new RuntimeException("Diretório de comprovantes inválido.");
        }
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException("Não foi possível preparar o armazenamento do comprovante.");
        }
        @chmod($path, 0750);
    }
    $resolvedRoot = realpath($root);
    $resolvedDirectory = realpath($directory);
    if ($resolvedRoot === false || $resolvedDirectory === false || ($resolvedDirectory !== $resolvedRoot && !str_starts_with($resolvedDirectory, $resolvedRoot . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException("Diretório de comprovantes inválido.");
    }
    return ["absolute" => $resolvedDirectory, "relative" => $relativeRoot . "/clinic-" . $cid];
}

function subscription_payment_proof_upload'''
subscription = re.sub(
    r'function subscription_payment_proof_storage\(int \$cid\): array\n\{.*?\n\}\n\nfunction subscription_payment_proof_upload',
    new_storage,
    subscription,
    count=1,
    flags=re.S,
)
subscription = subscription.replace(
    '$storage = subscription_payment_proof_storage($cid);',
    '$storage = subscription_payment_proof_storage(\n        $cid,\n        $mime !== "application/pdf",\n    );',
    1,
)
new_absolute = '''function subscription_payment_proof_absolute_path(?string $proofPath): ?string
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_absolute_path
     * Responsabilidade: Resolve comprovantes atuais e caminhos legados já gravados.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_payment_delete_proof`, `page_admin_payment_proof`.
     * Dependências chamadas: `trim`, `str_replace`, `str_starts_with`, `str_contains`, `realpath`, `storage_path`, `is_file`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Preserve a compatibilidade de leitura de `storage/payment-proofs/`.
     */
    $proofPath = trim((string) $proofPath);
    if ($proofPath === "") {
        return null;
    }
    $proofPath = str_replace("\\\\", "/", $proofPath);
    if (str_contains($proofPath, "..")) {
        return null;
    }
    $roots = [
        "ssd/img/payment-proofs/" => storage_path("img/payment-proofs"),
        "ssd/payment-proofs/" => storage_path("payment-proofs"),
        "storage/payment-proofs/" => storage_path("payment-proofs"),
    ];
    $matchedPrefix = null;
    $rootPath = null;
    foreach ($roots as $prefix => $candidateRoot) {
        if (str_starts_with($proofPath, $prefix)) {
            $matchedPrefix = $prefix;
            $rootPath = $candidateRoot;
            break;
        }
    }
    if ($matchedPrefix === null || $rootPath === null) {
        return null;
    }
    $root = realpath($rootPath);
    $relative = substr($proofPath, strlen($matchedPrefix));
    if ($root === false || $relative === false || $relative === "") {
        return null;
    }
    $candidate = $root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relative);
    $full = realpath($candidate);
    if ($full === false || !is_file($full) || !str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $full;
}
function subscription_payment_delete_proof'''
subscription = re.sub(
    r'function subscription_payment_proof_absolute_path\(\?string \$proofPath\): \?string\n\{.*?\n\}\nfunction subscription_payment_delete_proof',
    new_absolute,
    subscription,
    count=1,
    flags=re.S,
)
save('app/Domain/Clinic/SubscriptionSettings.php', subscription)

admin = text('app/Admin/AdminPages.php')
admin = admin.replace('app_root() . "/storage"', 'app_root() . "/ssd"')
admin = admin.replace('Sistema de arquivos sem permissão de escrita no storage.', 'Diretório persistente /ssd sem permissão de escrita.')
admin = admin.replace('Sem escrita em storage', 'Sem escrita em /ssd')
admin = admin.replace('Arquivos temporários, métricas e comprovantes dependem de escrita no storage.', 'Arquivos temporários, métricas e comprovantes dependem de escrita em /ssd.')
save('app/Admin/AdminPages.php', admin)

installer = text('app/Install/Installer.php')
for old, new in [
    ('"storage/" => storage_path()', '"ssd/" => storage_path()'),
    ('"storage/cache/" => storage_path("cache")', '"ssd/cache/" => storage_path("cache")'),
    ('"storage/telemetry/" => storage_path("telemetry")', '"ssd/telemetry/" => storage_path("telemetry")'),
    ('"storage/tmp/" => storage_path("tmp")', '"ssd/tmp/" => storage_path("tmp")'),
    ('"pdfs/" => install_pdf_dir()', '"ssd/pdfs/" => install_pdf_dir()'),
    ('["storage/", storage_path()]', '["ssd/", storage_path()]'),
    ('["storage/install.lock", storage_path("install.lock")]', '["ssd/install.lock", storage_path("install.lock")]'),
    ('["storage/cache/", storage_path("cache")]', '["ssd/cache/", storage_path("cache")]'),
    ('["storage/telemetry/", storage_path("telemetry")]', '["ssd/telemetry/", storage_path("telemetry")]'),
    ('["storage/tmp/", storage_path("tmp")]', '["ssd/tmp/", storage_path("tmp")]'),
    ('["pdfs/ (rota pública)", install_pdf_dir()]', '["ssd/pdfs/ (persistência física)", install_pdf_dir()]'),
    ('["storage/pdfs/", storage_path("pdfs")]', '["ssd/img/", storage_path("img")]'),
    ('storage/tenant_integrity.json', 'ssd/tenant_integrity.json'),
    ('permissões de escrita em app/, storage/ e pdfs/.', 'permissões de escrita em app/ e ssd/.'),
    ('storage/install.lock', 'ssd/install.lock'),
]:
    installer = installer.replace(old, new)
installer = installer.replace('str_contains($msg, "storage") ||', 'str_contains($msg, "ssd") ||\n        str_contains($msg, "storage") ||', 1)
save('app/Install/Installer.php', installer)

runtime_core = text('br/runtime-core.php').replace('Disallow: /storage/', 'Disallow: /ssd/')
save('br/runtime-core.php', runtime_core)

layer_map = text('app/Core/Architecture/LayerMap.php').replace("'/storage/'", "'/ssd/'")
save('app/Core/Architecture/LayerMap.php', layer_map)

architecture = jload('app/architecture.manifest.json')
architecture['version'] = VERSION
architecture['coverage_scope'] = 'all_versioned_php_files_excluding_ssd_vendor_node_modules_and_git'
if 'generated_at' in architecture:
    architecture['generated_at'] = STAMP
jsave('app/architecture.manifest.json', architecture)

operational = jload('app/Database/operational-schema.contract.json')
operational['version'] = VERSION
if 'release' in operational:
    operational['release'] = VERSION
if 'generated_at' in operational:
    operational['generated_at'] = STAMP
jsave('app/Database/operational-schema.contract.json', operational)

security = text('tools/install-security-check.php')
if 'ssd_gitignore_policy' not in security:
    anchor = "$components = (string) file_get_contents($root . '/app/Ui/Components.php');"
    check = '''$gitignore = (string) file_get_contents($root . '/.gitignore');
if (!str_contains($gitignore, "/ssd/") || !str_contains($gitignore, "/storage/") || !str_contains($gitignore, "/pdfs/")) {
    $errors[] = 'ssd_gitignore_policy';
}
$foundationSource = (string) file_get_contents($root . '/app/Support/Foundation.php');
if (!str_contains($foundationSource, 'app_root() . "/ssd"') || str_contains($foundationSource, 'app_root() . "/storage"')) {
    $errors[] = 'ssd_storage_path_policy';
}
$prontooSource = (string) file_get_contents($root . '/app/prontoo.php');
foreach (['PRONTOO_SSD_PERSISTENCE_POLICY', 'PRONTOO_SSD_ROOT', 'PRONTOO_PDF_ROOT', 'PRONTOO_IMAGE_UPLOAD_ROOT'] as $requiredPersistenceMarker) {
    if (!str_contains($prontooSource, $requiredPersistenceMarker)) {
        $errors[] = 'ssd_migration_marker:' . $requiredPersistenceMarker;
    }
}
$documentPdfSource = (string) file_get_contents($root . '/app/Domain/Documents/DocumentPdf.php');
if (!str_contains($documentPdfSource, 'storage_path("pdfs")') || str_contains($documentPdfSource, 'app_root() . "/pdfs"')) {
    $errors[] = 'ssd_pdf_policy';
}
$subscriptionSource = (string) file_get_contents($root . '/app/Domain/Clinic/SubscriptionSettings.php');
foreach (['storage_path("img/payment-proofs")', '"ssd/img/payment-proofs"', '"ssd/payment-proofs"', '"storage/payment-proofs/"', '$mime !== "application/pdf"'] as $requiredUploadPolicy) {
    if (!str_contains($subscriptionSource, $requiredUploadPolicy)) {
        $errors[] = 'ssd_upload_policy:' . $requiredUploadPolicy;
    }
}
$htaccessPolicy = (string) file_get_contents($root . '/.htaccess');
if (!str_contains($htaccessPolicy, 'RewriteRule ^ssd/ - [F,L,NC]') || !str_contains($htaccessPolicy, 'RewriteRule ^storage/ - [F,L,NC]')) {
    $errors[] = 'ssd_webserver_policy';
}
if (is_file($root . '/pdfs/.htaccess') || is_file($root . '/pdfs/index.html')) {
    $errors[] = 'legacy_root_pdfs_router_present';
}

'''
    security = security.replace(anchor, check + anchor, 1)
    security = security.replace("    'https_enforced' => true,\n", "    'https_enforced' => true,\n    'persistent_root' => 'ssd',\n    'pdf_storage' => 'ssd/pdfs',\n    'image_upload_storage' => 'ssd/img',\n", 1)
save('tools/install-security-check.php', security)

canonical = subprocess.check_output(['git', 'show', 'origin/main:.github/workflows/architecture.yml'], text=True)
canonical = canonical.replace("-path './storage' -prune", "-path './ssd' -prune")
canonical = canonical.replace("str_contains($path, '/storage/')", "str_contains($path, '/ssd/')")
save('.github/workflows/architecture.yml', canonical)

landing = re.sub(r'BR_LANDING_VERSION_FALLBACK\s*=\s*["\'][^"\']+["\']', f'BR_LANDING_VERSION_FALLBACK = "{VERSION}"', text('br/index.php'), count=1)
save('br/index.php', landing)

version = jload('version.json')
version.update({
    'version': VERSION,
    'release': VERSION,
    'generated_at_unix': STAMP_UNIX,
    'generated_at': STAMP,
    'updated_at': STAMP,
    'build': BUILD,
    'package_type': PACKAGE,
    'database_changes': False,
    'schema_changes': False,
    'logic_changes': True,
    'visual_changes': True,
    'documentation_changes': True,
    'previous_version': PREVIOUS,
    'notes': 'Migra a persistência canônica para /ssd, move PDFs físicos para /ssd/pdfs e exige /ssd/img para imagens enviadas.',
    'persistent_storage_policy': {
        'root': 'ssd',
        'pdfs': 'ssd/pdfs',
        'images': 'ssd/img',
        'legacy_storage_migration': True,
        'legacy_root_pdfs_migration': True,
    },
    'deployment_sync_id': SYNC_ID,
    'deployment_sync_requested_at': STAMP,
})
jsave('version.json', version)

entry = f'''Prontoo {VERSION} — persistência canônica em SSD

- Renomeia o diretório persistente raiz de `storage/` para `ssd/` sem alterar banco ou schema.
- Move a persistência física de PDFs para `ssd/pdfs/`, mantendo a URL autenticada `/pdfs/` por compatibilidade.
- Define `ssd/img/` como destino obrigatório para imagens enviadas; comprovantes em imagem ficam em `ssd/img/payment-proofs/`.
- Mantém PDFs de comprovantes em `ssd/payment-proofs/` e preserva leitura de caminhos legados já gravados.
- Migra automaticamente conteúdos existentes de `storage/` e do diretório físico raiz `pdfs/`, sem sobrescrever arquivos canônicos existentes.
- Bloqueia acesso HTTP direto a `ssd/` e mantém bloqueio da entrada legada `storage/`.

'''
save('ChangeLog.txt', entry + text('ChangeLog.txt'))

for legacy in ['pdfs/.htaccess', 'pdfs/index.html']:
    path = ROOT / legacy
    if path.exists():
        path.unlink()

manifest = jload('app/update.manifest.json')
manifest.update({
    'version': VERSION,
    'release': VERSION,
    'build': BUILD,
    'package_type': PACKAGE,
    'generated_at': STAMP,
    'updated_at': STAMP,
    'database_changes': False,
    'schema_changes': False,
    'logic_changes': True,
    'visual_changes': True,
    'documentation_changes': True,
    'previous_version': PREVIOUS,
    'notes': 'Persistência canônica em /ssd, PDFs em /ssd/pdfs e imagens enviadas em /ssd/img, com migração automática dos diretórios legados.',
    'persistent_storage_policy': {
        'root': 'ssd',
        'pdfs': 'ssd/pdfs',
        'images': 'ssd/img',
        'legacy_storage_migration': True,
        'legacy_root_pdfs_migration': True,
    },
    'deployment_sync_id': SYNC_ID,
})
files = dict(manifest.get('files', {}))
for name in files:
    path = ROOT / name
    files[name] = hashlib.sha256(path.read_bytes()).hexdigest()
manifest['files'] = files
manifest['file_count'] = len(files)
manifest['total_uncompressed_bytes'] = sum((ROOT / name).stat().st_size for name in files)
jsave('app/update.manifest.json', manifest, 4)

for temporary in ['.automation/persistence-inventory.txt', '.automation/apply_ssd_persistence.py', 'ssd-apply-error.txt']:
    path = ROOT / temporary
    if path.exists():
        path.unlink()
try:
    (ROOT / '.automation').rmdir()
except OSError:
    pass

print(json.dumps({
    'ok': True,
    'version': VERSION,
    'persistent_root': 'ssd',
    'pdf_storage': 'ssd/pdfs',
    'image_upload_storage': 'ssd/img',
    'database_changes': False,
    'schema_changes': False,
}, ensure_ascii=False, indent=2))
