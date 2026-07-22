from __future__ import annotations

import hashlib
import json
import re
import subprocess
from pathlib import Path

ROOT = Path('.')
VERSION = '1.7.22.6'
PREVIOUS = '1.7.22.5'
GENERATED_AT = '2026-07-22T19:45:00Z'
GENERATED_UNIX = 1784749500
BUILD = '1.7.22.6-ssd-persistence-policy'
PACKAGE = 'ssd_persistence_policy'
SYNC_ID = 'hostoo-ssd-persistence-20260722T194500Z'


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding='utf-8')


def replace_once(path: str, old: str, new: str) -> None:
    content = read(path)
    if old not in content:
        raise RuntimeError(f'Âncora ausente em {path}: {old[:120]!r}')
    write(path, content.replace(old, new, 1))


def replace_all(path: str, replacements: list[tuple[str, str]]) -> None:
    content = read(path)
    for old, new in replacements:
        if old not in content:
            raise RuntimeError(f'Âncora ausente em {path}: {old[:120]!r}')
        content = content.replace(old, new)
    write(path, content)


def json_load(path: str) -> dict:
    data = json.loads(read(path))
    if not isinstance(data, dict):
        raise RuntimeError(f'JSON raiz inválido: {path}')
    return data


def json_write(path: str, data: dict, indent: int = 2) -> None:
    write(path, json.dumps(data, ensure_ascii=False, indent=indent) + '\n')


# Persistência ignorada pelo Git. Diretórios antigos permanecem ignorados apenas
# como entradas de migração, não como destinos canônicos.
write(
    '.gitignore',
    '''# Configuração e credenciais da instalação
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
''',
)

# Apache/LiteSpeed: bloqueia o SSD físico e mantém bloqueio da entrada legada.
replace_once(
    '.htaccess',
    '    RewriteRule ^storage/ - [F,L]\n',
    '    RewriteRule ^ssd/ - [F,L,NC]\n    RewriteRule ^storage/ - [F,L,NC]\n',
)

# storage_path continua como API interna estável, mas agora aponta para /ssd.
replace_once(
    'app/Support/Foundation.php',
    '    return app_root() . "/storage" . ($path ? "/" . ltrim($path, "/") : "");',
    '    return app_root() . "/ssd" . ($path ? "/" . ltrim($path, "/") : "");',
)
replace_once(
    'br/runtime-telemetry.php',
    '        $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . "storage";',
    '        $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . "ssd";',
)
replace_once(
    'app/Database/DatabaseSchema.php',
    '        : dirname(__DIR__, 2) . "/storage/cache/runtime-notices";',
    '        : dirname(__DIR__, 2) . "/ssd/cache/runtime-notices";',
)
replace_once(
    'app/Core/Install/RuntimeContract.php',
    "$strict = is_file($root . '/storage/install.lock');",
    "$strict = is_file($root . '/ssd/install.lock');",
)

# Migração automática e constantes da política SSD, antes do bootstrap funcional.
prontoo = read('app/prontoo.php')
prontoo = re.sub(
    r'const PRONTOO_VERSION_FALLBACK = "[^"]+";',
    f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";',
    prontoo,
    count=1,
)
prontoo = re.sub(
    r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";',
    f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS}";',
    prontoo,
    count=1,
)
prontoo = prontoo.replace('PRONTOO_ROOT . "/storage/logs"', 'PRONTOO_ROOT . "/ssd/logs"')
anchor = 'if (!function_exists("mb_substr")) {'
if anchor not in prontoo:
    raise RuntimeError('Âncora de inicialização persistente ausente em app/prontoo.php')
migration = r'''// PRONTOO_SSD_PERSISTENCE_POLICY
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
            new RecursiveDirectoryIterator(
                $prontooLegacyRoot,
                FilesystemIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($prontooLegacyIterator as $prontooLegacyEntry) {
            if ($prontooLegacyEntry->isLink()) {
                continue;
            }
            $prontooLegacyPath = $prontooLegacyEntry->getPathname();
            $prontooRelativePath = substr(
                $prontooLegacyPath,
                strlen($prontooLegacyRoot) + 1,
            );
            if ($prontooRelativePath === false || $prontooRelativePath === "") {
                continue;
            }
            $prontooCanonicalPath =
                $prontooCanonicalRoot . DIRECTORY_SEPARATOR . $prontooRelativePath;
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
        error_log(
            "[Prontoo SSD migration] " . $prontooPersistenceError->getMessage(),
        );
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
prontoo = prontoo.replace(anchor, migration + anchor, 1)
write('app/prontoo.php', prontoo)

# O diretório físico raiz /pdfs deixa de existir; a URL /pdfs/ continua como
# rota autenticada e os arquivos passam a residir em /ssd/pdfs/.
doc = read('app/Domain/Documents/DocumentPdf.php')
pattern = re.compile(
    r'function document_pdf_public_router_dir\(\): string\n\{.*?\n\}\nfunction document_pdf_storage_dir',
    re.S,
)
replacement = '''function document_pdf_public_router_dir(): string
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
doc, count = pattern.subn(replacement, doc, count=1)
if count != 1:
    raise RuntimeError('Não foi possível substituir document_pdf_public_router_dir')
write('app/Domain/Documents/DocumentPdf.php', doc)

# Uploads: imagens obrigatoriamente em /ssd/img; PDFs de comprovantes ficam em
# /ssd/payment-proofs. Caminhos antigos gravados no banco continuam válidos.
subscription = read('app/Domain/Clinic/SubscriptionSettings.php')
storage_pattern = re.compile(
    r'function subscription_payment_proof_storage\(int \$cid\): array\n\{.*?\n\}\n\nfunction subscription_payment_proof_upload',
    re.S,
)
storage_replacement = '''function subscription_payment_proof_storage(int $cid, bool $image = false): array
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_storage
     * Responsabilidade: Resolve o diretório persistente de comprovantes, separando imagens em `/ssd/img/` e documentos PDF no SSD protegido.
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
    $root = $image
        ? storage_path("img/payment-proofs")
        : storage_path("payment-proofs");
    $directory = $root . "/clinic-" . $cid;
    foreach ([$root, $directory] as $path) {
        if (is_link($path)) {
            throw new RuntimeException("Diretório de comprovantes inválido.");
        }
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException(
                "Não foi possível preparar o armazenamento do comprovante.",
            );
        }
        @chmod($path, 0750);
    }
    $resolvedRoot = realpath($root);
    $resolvedDirectory = realpath($directory);
    if (
        $resolvedRoot === false ||
        $resolvedDirectory === false ||
        ($resolvedDirectory !== $resolvedRoot &&
            !str_starts_with($resolvedDirectory, $resolvedRoot . DIRECTORY_SEPARATOR))
    ) {
        throw new RuntimeException("Diretório de comprovantes inválido.");
    }
    return [
        "absolute" => $resolvedDirectory,
        "relative" => $relativeRoot . "/clinic-" . $cid,
    ];
}

function subscription_payment_proof_upload'''
subscription, count = storage_pattern.subn(storage_replacement, subscription, count=1)
if count != 1:
    raise RuntimeError('Não foi possível substituir subscription_payment_proof_storage')
subscription = subscription.replace(
    '$storage = subscription_payment_proof_storage($cid);',
    '$storage = subscription_payment_proof_storage(\n        $cid,\n        $mime !== "application/pdf",\n    );',
    1,
)
absolute_pattern = re.compile(
    r'function subscription_payment_proof_absolute_path\(\?string \$proofPath\): \?string\n\{.*?\n\}\nfunction subscription_payment_delete_proof',
    re.S,
)
absolute_replacement = '''function subscription_payment_proof_absolute_path(?string $proofPath): ?string
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_absolute_path
     * Responsabilidade: Resolve comprovantes persistidos na política SSD atual e em caminhos legados já gravados.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_payment_delete_proof`, `page_admin_payment_proof`.
     * Dependências chamadas: `trim`, `str_replace`, `str_starts_with`, `str_contains`, `realpath`, `storage_path`, `is_file`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Preserve a compatibilidade de leitura dos registros `storage/payment-proofs/` durante a migração.
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
    if ($root === false) {
        return null;
    }
    $relative = substr($proofPath, strlen($matchedPrefix));
    if ($relative === false || $relative === "") {
        return null;
    }
    $candidate = $root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relative);
    $full = realpath($candidate);
    if (
        $full === false ||
        !is_file($full) ||
        !str_starts_with($full, $root . DIRECTORY_SEPARATOR)
    ) {
        return null;
    }
    return $full;
}
function subscription_payment_delete_proof'''
subscription, count = absolute_pattern.subn(absolute_replacement, subscription, count=1)
if count != 1:
    raise RuntimeError('Não foi possível substituir subscription_payment_proof_absolute_path')
write('app/Domain/Clinic/SubscriptionSettings.php', subscription)

# Caminhos diretos remanescentes e textos operacionais.
replace_all(
    'app/Admin/AdminPages.php',
    [
        ('app_root() . "/storage"', 'app_root() . "/ssd"'),
        ('Sistema de arquivos sem permissão de escrita no storage.', 'Diretório persistente /ssd sem permissão de escrita.'),
        ('Sem escrita em storage', 'Sem escrita em /ssd'),
        ('Arquivos temporários, métricas e comprovantes dependem de escrita no storage.', 'Arquivos temporários, métricas e comprovantes dependem de escrita em /ssd.'),
    ],
)
installer = read('app/Install/Installer.php')
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
    ('Verifique storage/tenant_integrity.json', 'Verifique ssd/tenant_integrity.json'),
    ('permissões de escrita em app/, storage/ e pdfs/.', 'permissões de escrita em app/ e ssd/.'),
    ('storage/install.lock', 'ssd/install.lock'),
    ('"Não foi possível gravar storage/install.lock."', '"Não foi possível gravar ssd/install.lock."'),
]:
    if old not in installer:
        raise RuntimeError(f'Âncora ausente em Installer.php: {old}')
    installer = installer.replace(old, new)
installer = installer.replace(
    'str_contains($msg, "storage") ||',
    'str_contains($msg, "ssd") ||\n        str_contains($msg, "storage") ||',
    1,
)
write('app/Install/Installer.php', installer)

replace_once('br/runtime-core.php', 'echo "Disallow: /storage/\\n";', 'echo "Disallow: /ssd/\\n";')
replace_once(
    'app/Core/Architecture/LayerMap.php',
    "$excluded = ['/storage/', '/vendor/', '/node_modules/', '/.git/'];",
    "$excluded = ['/ssd/', '/vendor/', '/node_modules/', '/.git/'];",
)
architecture_manifest = json_load('app/architecture.manifest.json')
architecture_manifest['version'] = VERSION
architecture_manifest['coverage_scope'] = 'all_versioned_php_files_excluding_ssd_vendor_node_modules_and_git'
if 'generated_at' in architecture_manifest:
    architecture_manifest['generated_at'] = GENERATED_AT
json_write('app/architecture.manifest.json', architecture_manifest)

operational = json_load('app/Database/operational-schema.contract.json')
operational['version'] = VERSION
if 'release' in operational:
    operational['release'] = VERSION
if 'generated_at' in operational:
    operational['generated_at'] = GENERATED_AT
json_write('app/Database/operational-schema.contract.json', operational)

# Teste permanente da política de persistência.
security = read('tools/install-security-check.php')
security_anchor = '''$components = (string) file_get_contents($root . '/app/Ui/Components.php');'''
persistence_test = '''$gitignore = (string) file_get_contents($root . '/.gitignore');
if (!str_contains($gitignore, "/ssd/") ||
    !str_contains($gitignore, "/storage/") ||
    !str_contains($gitignore, "/pdfs/")) {
    $errors[] = 'ssd_gitignore_policy';
}
$foundationSource = (string) file_get_contents($root . '/app/Support/Foundation.php');
if (!str_contains($foundationSource, 'app_root() . "/ssd"') ||
    str_contains($foundationSource, 'app_root() . "/storage"')) {
    $errors[] = 'ssd_storage_path_policy';
}
$prontooSource = (string) file_get_contents($root . '/app/prontoo.php');
foreach ([
    'PRONTOO_SSD_PERSISTENCE_POLICY',
    'PRONTOO_SSD_ROOT',
    'PRONTOO_PDF_ROOT',
    'PRONTOO_IMAGE_UPLOAD_ROOT',
    'PRONTOO_ROOT . "/storage"',
    'PRONTOO_ROOT . "/pdfs"',
] as $requiredPersistenceMarker) {
    if (!str_contains($prontooSource, $requiredPersistenceMarker)) {
        $errors[] = 'ssd_migration_marker:' . $requiredPersistenceMarker;
    }
}
$documentPdfSource = (string) file_get_contents($root . '/app/Domain/Documents/DocumentPdf.php');
if (!str_contains($documentPdfSource, 'storage_path("pdfs")') ||
    str_contains($documentPdfSource, 'app_root() . "/pdfs"')) {
    $errors[] = 'ssd_pdf_policy';
}
$subscriptionSource = (string) file_get_contents($root . '/app/Domain/Clinic/SubscriptionSettings.php');
foreach ([
    'storage_path("img/payment-proofs")',
    '"ssd/img/payment-proofs"',
    '"ssd/payment-proofs"',
    '"storage/payment-proofs/"',
    '$mime !== "application/pdf"',
] as $requiredUploadPolicy) {
    if (!str_contains($subscriptionSource, $requiredUploadPolicy)) {
        $errors[] = 'ssd_upload_policy:' . $requiredUploadPolicy;
    }
}
$htaccessPolicy = (string) file_get_contents($root . '/.htaccess');
if (!str_contains($htaccessPolicy, 'RewriteRule ^ssd/ - [F,L,NC]') ||
    !str_contains($htaccessPolicy, 'RewriteRule ^storage/ - [F,L,NC]')) {
    $errors[] = 'ssd_webserver_policy';
}
if (is_file($root . '/pdfs/.htaccess') || is_file($root . '/pdfs/index.html')) {
    $errors[] = 'legacy_root_pdfs_router_present';
}

'''
if security_anchor not in security:
    raise RuntimeError('Âncora de teste permanente ausente')
security = security.replace(security_anchor, persistence_test + security_anchor, 1)
security = security.replace(
    "    'https_enforced' => true,\n",
    "    'https_enforced' => true,\n    'persistent_root' => 'ssd',\n    'pdf_storage' => 'ssd/pdfs',\n    'image_upload_storage' => 'ssd/img',\n",
    1,
)
write('tools/install-security-check.php', security)

# Workflow canônico restaurado com exclusão do novo diretório persistente.
canonical_workflow = subprocess.check_output(
    ['git', 'show', 'origin/main:.github/workflows/architecture.yml'],
    text=True,
)
canonical_workflow = canonical_workflow.replace("-path './storage' -prune", "-path './ssd' -prune")
canonical_workflow = canonical_workflow.replace("str_contains($path, '/storage/')", "str_contains($path, '/ssd/')")
write('.github/workflows/architecture.yml', canonical_workflow)

# Versão e changelog.
landing = read('br/index.php')
landing = re.sub(
    r'BR_LANDING_VERSION_FALLBACK\s*=\s*["\'][^"\']+["\']',
    f'BR_LANDING_VERSION_FALLBACK = "{VERSION}"',
    landing,
    count=1,
)
write('br/index.php', landing)

version = json_load('version.json')
version.update({
    'version': VERSION,
    'release': VERSION,
    'generated_at_unix': GENERATED_UNIX,
    'generated_at': GENERATED_AT,
    'updated_at': GENERATED_AT,
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
    'deployment_sync_requested_at': GENERATED_AT,
})
json_write('version.json', version)

changelog = read('ChangeLog.txt')
entry = f'''Prontoo {VERSION} — persistência canônica em SSD

- Renomeia o diretório persistente raiz de `storage/` para `ssd/` sem alterar banco ou schema.
- Move a persistência física de PDFs para `ssd/pdfs/`, mantendo a URL autenticada `/pdfs/` por compatibilidade.
- Define `ssd/img/` como destino obrigatório para imagens enviadas; comprovantes em imagem ficam em `ssd/img/payment-proofs/`.
- Mantém PDFs de comprovantes em `ssd/payment-proofs/` e preserva leitura de caminhos legados já gravados.
- Migra automaticamente conteúdos existentes de `storage/` e do diretório físico raiz `pdfs/`, sem sobrescrever arquivos canônicos existentes.
- Bloqueia acesso HTTP direto a `ssd/` e mantém bloqueio da entrada legada `storage/`.

'''
write('ChangeLog.txt', entry + changelog)

# Remove os roteadores físicos legados da raiz /pdfs.
for legacy in ['pdfs/.htaccess', 'pdfs/index.html']:
    path = ROOT / legacy
    if path.exists():
        path.unlink()

# Atualiza o manifesto depois de todas as mudanças permanentes.
manifest = json_load('app/update.manifest.json')
manifest.update({
    'version': VERSION,
    'release': VERSION,
    'build': BUILD,
    'package_type': PACKAGE,
    'generated_at': GENERATED_AT,
    'updated_at': GENERATED_AT,
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
manifest.pop('temporary_public_installer', None)
files = dict(manifest.get('files', {}))
for file_name in list(files):
    path = ROOT / file_name
    if not path.is_file():
        raise RuntimeError(f'Arquivo do manifesto ausente: {file_name}')
    files[file_name] = hashlib.sha256(path.read_bytes()).hexdigest()
manifest['files'] = files
manifest['file_count'] = len(files)
manifest['total_uncompressed_bytes'] = sum((ROOT / name).stat().st_size for name in files)
json_write('app/update.manifest.json', manifest, 4)

# Remove artefatos temporários usados para inventário/aplicação.
for temporary in [
    '.automation/persistence-inventory.txt',
    '.automation/apply_ssd_persistence.py',
]:
    path = ROOT / temporary
    if path.exists():
        path.unlink()
try:
    (ROOT / '.automation').rmdir()
except OSError:
    pass

# Validações finais locais da transformação.
for forbidden in [
    'app_root() . "/storage"',
    'dirname(__DIR__) . DIRECTORY_SEPARATOR . "storage"',
    'dirname(__DIR__, 2) . "/storage/cache/runtime-notices"',
]:
    for path in ROOT.rglob('*.php'):
        if '.git' in path.parts or 'vendor' in path.parts or 'node_modules' in path.parts:
            continue
        if forbidden in path.read_text(encoding='utf-8'):
            raise RuntimeError(f'Referência física legada remanescente em {path}: {forbidden}')

report = {
    'ok': True,
    'version': VERSION,
    'persistent_root': 'ssd',
    'pdf_storage': 'ssd/pdfs',
    'image_upload_storage': 'ssd/img',
    'legacy_migration': ['storage', 'pdfs'],
    'database_changes': False,
    'schema_changes': False,
}
print(json.dumps(report, ensure_ascii=False, indent=2))
