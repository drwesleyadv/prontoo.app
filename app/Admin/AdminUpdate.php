<?php
declare(strict_types=1);

const PRONTOO_UPDATE_MAX_BYTES = 67108864;

const PRONTOO_UPDATE_MAX_UNPACKED_BYTES = 134217728;

const PRONTOO_UPDATE_MAX_FILE_BYTES = 33554432;

const PRONTOO_UPDATE_MAX_ENTRIES = 2500;
const PRONTOO_UPDATE_MANIFEST = "app/update.manifest.json";
const PRONTOO_UPDATE_SIGNATURE = "app/update.signature";

function admin_update_root(): string
{
    return defined("PRONTOO_ROOT") ? PRONTOO_ROOT : dirname(__DIR__, 2);
}
function admin_update_tmp_dir(): string
{
    $dir = storage_path("tmp");
    if (!is_dir($dir)) {
        prontoo_fs_mkdir($dir);
    }
    return is_dir($dir) && is_writable($dir) ? $dir : sys_get_temp_dir();
}
function admin_update_acquire_lock()
{
    $file =
        rtrim(admin_update_tmp_dir(), DIRECTORY_SEPARATOR) .
        DIRECTORY_SEPARATOR .
        "prontoo-update-" .
        substr(hash("sha256", admin_update_root()), 0, 16) .
        ".lock";
    $lock = prontoo_runtime_attempt(
        static fn() => fopen($file, "c"),
        false,
        "abertura do bloqueio exclusivo de atualização",
    );
    if (!is_resource($lock)) {
        throw new RuntimeException(
            "Não foi possível reservar a área segura de atualização.",
        );
    }
    @chmod($file, 0640);
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        throw new RuntimeException(
            "Outra atualização já está em andamento. Aguarde a conclusão antes de reenviar o pacote.",
        );
    }
    return $lock;
}
function admin_update_release_lock($lock): void
{
    if (!is_resource($lock)) {
        return;
    }
    @flock($lock, LOCK_UN);
    fclose($lock);
}
function admin_update_path_is_canonical(string $name): bool
{
    if (
        $name === "" ||
        trim($name) !== $name ||
        str_contains($name, "\0") ||
        str_contains($name, "\\") ||
        str_starts_with($name, "/") ||
        preg_match('/^[a-z]:\//i', $name) ||
        preg_match('#/{2,}#', $name)
    ) {
        return false;
    }
    $isDirectory = str_ends_with($name, "/");
    $trimmed = $isDirectory ? substr($name, 0, -1) : $name;
    if ($trimmed === "" || str_ends_with($trimmed, "/")) {
        return false;
    }
    foreach (explode("/", $trimmed) as $part) {
        if ($part === "" || $part === "." || $part === "..") {
            return false;
        }
    }
    return true;
}

function admin_update_normalize_zip_name(string $name): string
{
    return admin_update_path_is_canonical($name) ? $name : "";
}

function admin_update_zip_exact_index($zip, string $name): int|false
{
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (!$stat || !isset($stat["name"])) {
            continue;
        }
        if ((string) $stat["name"] === $name) {
            return $i;
        }
    }
    return false;
}

function admin_update_validate_entry_set($zip): array
{
    $seen = [];
    $files = [];
    $directories = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $raw = (string) ($stat["name"] ?? "");
        $entry = $raw;
        if (!admin_update_path_is_canonical($entry)) {
            throw new RuntimeException(
                "Pacote recusado: caminho inválido ou ambíguo no ZIP: " . $raw,
            );
        }
        $isDir = str_ends_with($entry, "/");
        $canonical = rtrim($entry, "/");
        $key = strtolower($canonical);
        if (isset($seen[$key])) {
            throw new RuntimeException(
                "Pacote recusado: entrada duplicada ou divergente apenas por maiúsculas/minúsculas: " .
                    $canonical .
                    ".",
            );
        }
        $seen[$key] = true;
        if (!admin_update_entry_allowed($entry)) {
            throw new RuntimeException(
                "Entrada não permitida no pacote: " . $entry,
            );
        }
        if ($isDir) {
            $directories[$canonical] = true;
        } else {
            $files[$canonical] = true;
        }
    }
    foreach (array_keys($files) as $file) {
        $parts = explode("/", $file);
        array_pop($parts);
        $prefix = "";
        foreach ($parts as $part) {
            $prefix = $prefix === "" ? $part : $prefix . "/" . $part;
            if (isset($files[$prefix])) {
                throw new RuntimeException(
                    "Pacote recusado: um arquivo foi usado como diretório: " .
                        $prefix .
                        ".",
                );
            }
        }
    }
    return ["files" => $files, "directories" => $directories];
}
function admin_update_human_bytes(int $bytes): string
{
    return function_exists("human_bytes")
        ? human_bytes((float) $bytes)
        : number_format($bytes / 1048576, 1, ",", ".") . " MB";
}

function admin_update_entry_allowed(string $entry): bool
{
    if (!admin_update_path_is_canonical($entry)) {
        return false;
    }
    $lower = strtolower($entry);
    $isDir = str_ends_with($lower, "/");
    if ($isDir) {
        $lower = rtrim($lower, "/");
    }

    $denyExact = [
        "app/config.php",
        "storage/install.lock",
        "storage/rom.enabled",
        "rom.php",
        "storage",
        "vendor",
        "node_modules",
    ];
    if (in_array($lower, $denyExact, true)) {
        return false;
    }
    if (
        str_starts_with($lower, "storage/") ||
        str_starts_with($lower, "vendor/") ||
        str_starts_with($lower, "node_modules/")
    ) {
        return false;
    }
    if ($isDir) {
        if ($lower === "") {
            return true;
        }
        if (
            in_array(
                $lower,
                [
                    "app",
                    "cron",
                    "pdfs",
                    "public",
                    "public/assets",
                    "br",
                    "br/assets",
                    "br/images",
                ],
                true,
            )
        ) {
            return true;
        }
        return str_starts_with($lower, "app/") ||
            str_starts_with($lower, "cron/") ||
            str_starts_with($lower, "public/assets/") ||
            str_starts_with($lower, "br/assets/") ||
            str_starts_with($lower, "br/images/");
    }
    if (
        preg_match(
            '#(^|/)(\.env|composer\.(json|lock)|package(-lock)?\.json|pnpm-lock\.yaml|yarn\.lock)$#i',
            $lower,
        )
    ) {
        return false;
    }
    if (
        preg_match(
            '#\.(?:sql|bak|old|orig|save|swp|tmp|log|phar|phtml|shtml|cgi|pl|py|sh)$#i',
            $lower,
        )
    ) {
        return false;
    }
    if (
        str_starts_with($lower, "public/assets/") &&
        preg_match('#\.(?:php[0-9]?|phtml)$#i', $lower)
    ) {
        return false;
    }

    if ($lower === "br/index.php") {
        return true;
    }
    if (
        preg_match(
            '#^br/assets/[a-z0-9._/-]+\.(?:css|js|png|svg|ico|webp|jpg|jpeg)$#i',
            $lower,
        )
    ) {
        return true;
    }
    if (
        preg_match(
            '#^br/images/[a-z0-9._/-]+\.(?:png|svg|ico|webp|jpg|jpeg)$#i',
            $lower,
        )
    ) {
        return true;
    }

    foreach (["app/", "cron/"] as $root) {
        if (str_starts_with($lower, $root)) {
            return true;
        }
    }
    if (in_array($lower, ["pdfs/.htaccess", "pdfs/index.html"], true)) {
        return true;
    }

    return in_array(
        $lower,
        [
            ".htaccess",
            "index.php",
            "install.php",
            "version.json",
            "changelog.txt",
            "favicon.ico",
            "robots.txt",
            "sitemap.xml",
            "llms.txt",
        ],
        true,
    ) ||
        preg_match(
            '#^public/assets/[a-z0-9._-]+\.(?:css|js|png|svg|ico|webp)$#i',
            $lower,
        ) === 1;
}

function admin_update_safe_target(
    string $entry,
    ?array &$createdDirectories = null,
): ?string {
    $entry = admin_update_normalize_zip_name($entry);
    if ($entry === "" || !admin_update_entry_allowed($entry)) {
        return null;
    }
    $parts = explode("/", rtrim($entry, "/"));
    if (!$parts) {
        return null;
    }
    $fileName = array_pop($parts);
    if ($fileName === null || $fileName === "") {
        return null;
    }
    $rootReal = realpath(admin_update_root());
    if ($rootReal === false) {
        return null;
    }
    $rootPrefix = rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $current = rtrim($rootReal, DIRECTORY_SEPARATOR);
    foreach ($parts as $part) {
        if ($part === "" || $part === "." || $part === "..") {
            return null;
        }
        $next = $current . DIRECTORY_SEPARATOR . $part;
        if (is_link($next)) {
            return null;
        }
        $directoryCreated = false;
        if (!is_dir($next)) {
            if (!prontoo_fs_mkdir($next, 0755)) {
                return null;
            }
            $directoryCreated = true;
        }
        $nextReal = realpath($next);
        if ($nextReal === false) {
            return null;
        }
        $nextPrefix = rtrim($nextReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($nextPrefix, $rootPrefix)) {
            return null;
        }
        if ($directoryCreated && is_array($createdDirectories)) {
            $createdDirectories[] = rtrim($nextReal, DIRECTORY_SEPARATOR);
        }
        $current = rtrim($nextReal, DIRECTORY_SEPARATOR);
    }
    $target = $current . DIRECTORY_SEPARATOR . $fileName;
    if (is_link($target)) {
        return null;
    }
    return $target;
}

function admin_update_zip_text($zip, string $name, int $limit = 262144): string
{
    $idx = admin_update_zip_exact_index($zip, $name);
    if ($idx === false) {
        return "";
    }
    $stat = $zip->statIndex($idx);
    if ((int) ($stat["size"] ?? 0) > $limit) {
        return "";
    }
    $raw = $zip->getFromIndex($idx);
    return is_string($raw) ? $raw : "";
}

function admin_update_manifest_payload($zip): array
{
    $raw = admin_update_zip_text($zip, PRONTOO_UPDATE_MANIFEST, 1048576);
    if ($raw === "") {
        return [];
    }
    $json = json_decode($raw, true);
    if (
        !is_array($json) ||
        !isset($json["files"]) ||
        !is_array($json["files"])
    ) {
        throw new RuntimeException("Manifesto de integridade inválido.");
    }
    if (
        !empty($json["version"]) &&
        version_compare(
            (string) $json["version"],
            admin_update_current_semver(),
            "<",
        )
    ) {
        throw new RuntimeException(
            "Pacote recusado: versão inferior à instalada.",
        );
    }
    admin_update_validate_signature($zip);
    return $json;
}

function admin_update_current_semver(): string
{
    if (
        defined("PRONTOO_VERSION") &&
        preg_match('/^1\.\d{1,2}\.\d{1,2}\.\d+$/', (string) PRONTOO_VERSION)
    ) {
        return (string) PRONTOO_VERSION;
    }
    $vf = dirname(__DIR__, 2) . "/version.json";
    if (is_file($vf)) {
        $json = json_decode((string) prontoo_fs_read($vf, false), true);
        if (is_array($json)) {
            foreach (["release", "version"] as $key) {
                $v = trim((string) ($json[$key] ?? ""));
                if (preg_match('/^1\.\d{1,2}\.\d{1,2}\.\d+$/', $v)) {
                    return $v;
                }
            }
        }
    }
    return "0.0.0";
}

function admin_update_signature_required(): bool
{
    $env = getenv("PRONTOO_UPDATE_REQUIRE_SIGNATURE");
    if ($env !== false) {
        $flag = strtolower(trim((string) $env));
    } else {
        $flag = defined("PRONTOO_UPDATE_REQUIRE_SIGNATURE") &&
            PRONTOO_UPDATE_REQUIRE_SIGNATURE
            ? "1"
            : "";
    }
    if (in_array($flag, ["1", "true", "yes", "on"], true)) {
        return true;
    }
    if (in_array($flag, ["0", "false", "no", "off"], true)) {
        return false;
    }
    return admin_update_public_key() !== "";
}
function admin_update_public_key(): string
{
    $key = (string) (getenv("PRONTOO_UPDATE_PUBLIC_KEY") ?: "");
    if ($key !== "") {
        return trim($key);
    }
    $path = dirname(__DIR__, 2) . "/storage/keys/update_public.key";
    return is_file($path) ? trim((string) prontoo_fs_read($path, false)) : "";
}
function admin_update_validate_signature($zip): void
{
    $manifest = admin_update_zip_text($zip, PRONTOO_UPDATE_MANIFEST, 1048576);
    if ($manifest === "") {
        throw new RuntimeException(
            "Pacote recusado: manifesto interno ausente.",
        );
    }
    $sig = admin_update_zip_text($zip, PRONTOO_UPDATE_SIGNATURE, 65536);
    if ($sig === "") {
        if (admin_update_signature_required()) {
            throw new RuntimeException(
                "Pacote recusado: assinatura criptográfica ausente.",
            );
        }
        return;
    }
    if (!function_exists("openssl_verify")) {
        throw new RuntimeException(
            "Pacote recusado: foi fornecida assinatura, mas OpenSSL está indisponível para validá-la.",
        );
    }
    $pub = admin_update_public_key();
    if ($pub === "") {
        throw new RuntimeException(
            "Pacote recusado: foi fornecida assinatura, mas a chave pública não está configurada.",
        );
    }
    $decoded = base64_decode(trim($sig), true);
    $signature = $decoded !== false ? $decoded : $sig;
    $ok = prontoo_runtime_attempt(
        static fn(): int|false => openssl_verify(
            $manifest,
            $signature,
            $pub,
            OPENSSL_ALGO_SHA256,
        ),
        false,
        "validação da assinatura da atualização",
    );
    if ($ok !== 1) {
        throw new RuntimeException(
            "Pacote recusado: assinatura criptográfica inválida.",
        );
    }
}
function admin_update_recursive_rm(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        prontoo_fs_unlink($path);
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($it as $item) {
        if ($item->isLink() || $item->isFile()) {
            prontoo_fs_unlink($item->getPathname());
            continue;
        }
        prontoo_runtime_attempt(
            static fn(): bool => rmdir($item->getPathname()),
            false,
            "remoção do diretório temporário " . $item->getPathname(),
        );
    }
    prontoo_runtime_attempt(
        static fn(): bool => rmdir($path),
        false,
        "remoção do diretório temporário {$path}",
    );
}
function admin_update_php_binary(): string
{
    $binary = defined("PHP_BINARY") ? trim((string) PHP_BINARY) : "";
    if ($binary !== "" && is_file($binary) && is_executable($binary)) {
        return $binary;
    }
    return "php";
}
function admin_update_compatible_php_binary(): string
{
    if (!function_exists("exec")) {
        return "";
    }
    $candidates = [];
    $runtimeBinary = defined("PHP_BINARY") ? trim((string) PHP_BINARY) : "";
    if ($runtimeBinary !== "") {
        $candidates[] = $runtimeBinary;
    }
    $candidates[] = "php";
    foreach (array_values(array_unique($candidates)) as $candidate) {
        $command =
            escapeshellarg($candidate) .
            " -r " .
            escapeshellarg('echo PHP_SAPI, PHP_EOL, PHP_VERSION;') .
            " 2>&1";
        $result = prontoo_runtime_attempt(
            static function () use ($command): array {
                $output = [];
                $code = 0;
                exec($command, $output, $code);
                return [$output, $code];
            },
            null,
            "identificação do PHP CLI",
        );
        if (!is_array($result)) {
            continue;
        }
        [$output, $code] = $result;
        $lines = array_values(
            array_filter(
                array_map(
                    static fn($line): string => trim((string) $line),
                    (array) $output,
                ),
                static fn(string $line): bool => $line !== "",
            ),
        );
        $sapi = strtolower((string) ($lines[0] ?? ""));
        $version = (string) ($lines[1] ?? "");
        if (
            $code === 0 &&
            $sapi === "cli" &&
            $version !== "" &&
            version_compare($version, PRONTOO_MIN_PHP_VERSION, ">=")
        ) {
            return $candidate;
        }
    }
    return "";
}
function admin_update_invalidate_opcode(string $path): void
{
    clearstatcache(true, $path);
    if (
        strtolower(pathinfo($path, PATHINFO_EXTENSION)) === "php" &&
        function_exists("opcache_invalidate")
    ) {
        @opcache_invalidate($path, true);
    }
}
function admin_update_lint_php_file(string $file): void
{
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== "php") {
        return;
    }
    $source = prontoo_runtime_attempt(
        static fn(): string|false => file_get_contents($file),
        false,
        "leitura do arquivo PHP para validação sintática",
    );
    if (!is_string($source)) {
        throw new RuntimeException(
            "Pacote recusado: não foi possível ler " .
                basename($file) .
                " para validação sintática.",
        );
    }
    try {
        token_get_all($source, TOKEN_PARSE);
    } catch (ParseError $error) {
        throw new RuntimeException(
            "Pacote recusado: erro de sintaxe em " .
                basename($file) .
                " — " .
                $error->getMessage(),
        );
    }
}
function admin_update_copy_file_verified(
    string $source,
    string $destination,
): void {
    $directory = dirname($destination);
    if (!is_dir($directory) && !prontoo_fs_mkdir($directory, 0770)) {
        throw new RuntimeException(
            "Não foi possível preparar o backup transacional da atualização.",
        );
    }
    $copied = prontoo_runtime_attempt(
        static fn(): bool => copy($source, $destination),
        false,
        "cópia de segurança de " . basename($source),
    );
    $sourceHash = $copied ? hash_file("sha256", $source) : false;
    $backupHash = $copied ? hash_file("sha256", $destination) : false;
    if (
        !$copied ||
        !is_string($sourceHash) ||
        !is_string($backupHash) ||
        !hash_equals($sourceHash, $backupHash)
    ) {
        if (is_file($destination)) {
            prontoo_fs_unlink($destination);
        }
        throw new RuntimeException(
            "Não foi possível confirmar a cópia de segurança de " .
                basename($source) .
                ".",
        );
    }
    prontoo_fs_chmod($destination, 0640);
}
function admin_update_restore_backups(
    array $backups,
    array $created = [],
    array $createdDirectories = [],
): array {
    $errors = [];
    foreach (array_reverse($created) as $target) {
        if (file_exists((string) $target) || is_link((string) $target)) {
            admin_update_recursive_rm((string) $target);
        }
        if (file_exists((string) $target) || is_link((string) $target)) {
            $errors[] = "remoção de " . basename((string) $target);
        }
    }
    foreach (array_reverse($backups) as $pair) {
        [$target, $backup, $mode] = $pair;
        if (!is_file($backup)) {
            $errors[] = "backup ausente de " . basename((string) $target);
            continue;
        }
        if (prontoo_fs_rename($backup, $target)) {
            prontoo_fs_chmod($target, (int) $mode);
            admin_update_invalidate_opcode((string) $target);
        } else {
            $errors[] = "restauração de " . basename((string) $target);
        }
    }
    foreach (array_reverse(array_unique($createdDirectories)) as $directory) {
        $directory = (string) $directory;
        if (!is_dir($directory) || is_link($directory)) {
            continue;
        }
        $items = scandir($directory);
        if (is_array($items) && count($items) === 2) {
            prontoo_runtime_attempt(
                static fn(): bool => rmdir($directory),
                false,
                "remoção de diretório vazio após rollback {$directory}",
            );
        }
    }
    return $errors;
}
function admin_update_validate_manifest($zip): array
{
    $manifest = admin_update_manifest_payload($zip);
    if (!$manifest) {
        throw new RuntimeException(
            "Pacote recusado: o manifesto interno de integridade é obrigatório.",
        );
    }
    $algorithm = strtolower(trim((string) ($manifest["hash_algorithm"] ?? "sha256")));
    if ($algorithm !== "sha256") {
        throw new RuntimeException(
            "Pacote recusado: o manifesto deve usar SHA-256.",
        );
    }
    $files = $manifest["files"];
    $listed = [];
    $totalBytes = 0;
    foreach ($files as $rawName => $definition) {
        $name = admin_update_normalize_zip_name((string) $rawName);
        if (
            $name === "" ||
            $name !== (string) $rawName ||
            str_ends_with($name, "/") ||
            $name === PRONTOO_UPDATE_MANIFEST ||
            $name === PRONTOO_UPDATE_SIGNATURE ||
            !admin_update_entry_allowed($name)
        ) {
            throw new RuntimeException(
                "Manifesto recusado: caminho inválido ou não permitido: " .
                    (string) $rawName,
            );
        }
        $key = strtolower($name);
        if (isset($listed[$key])) {
            throw new RuntimeException(
                "Manifesto recusado: arquivo duplicado ou divergente apenas por capitalização: " .
                    $name,
            );
        }
        $listed[$key] = $name;
        $hash = is_array($definition)
            ? (string) ($definition["sha256"] ?? "")
            : (string) $definition;
        if ($hash === "" || !preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            throw new RuntimeException("Manifesto inválido para " . $name);
        }
        $index = admin_update_zip_exact_index($zip, $name);
        if ($index === false) {
            throw new RuntimeException(
                "Pacote recusado: manifesto referencia arquivo ausente: " .
                    $name,
            );
        }
        $stat = $zip->statIndex($index);
        if (!$stat || str_ends_with((string) ($stat["name"] ?? ""), "/")) {
            throw new RuntimeException(
                "Pacote recusado: manifesto referencia entrada inválida: " .
                    $name,
            );
        }
        $stream = $zip->getStream((string) $stat["name"]);
        if (!$stream) {
            throw new RuntimeException(
                "Não foi possível ler " . $name . " para validação.",
            );
        }
        $ctx = hash_init("sha256");
        hash_update_stream($ctx, $stream);
        fclose($stream);
        if (!hash_equals(strtolower($hash), strtolower(hash_final($ctx)))) {
            throw new RuntimeException(
                "Pacote recusado: integridade divergente em " . $name,
            );
        }
        $totalBytes += (int) ($stat["size"] ?? 0);
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = admin_update_normalize_zip_name((string) ($stat["name"] ?? ""));
        if ($name === "" || str_ends_with($name, "/")) {
            continue;
        }
        if (
            $name === PRONTOO_UPDATE_MANIFEST ||
            $name === PRONTOO_UPDATE_SIGNATURE
        ) {
            continue;
        }
        if (!isset($files[$name])) {
            throw new RuntimeException(
                "Pacote recusado: arquivo não previsto no manifesto: " . $name,
            );
        }
    }
    $declaredCount = $manifest["file_count"] ?? null;
    if ($declaredCount === null || (int) $declaredCount !== count($files)) {
        throw new RuntimeException(
            "Manifesto recusado: file_count diverge da lista de arquivos.",
        );
    }
    $declaredBytes = $manifest["total_uncompressed_bytes"] ?? null;
    if ($declaredBytes === null || (int) $declaredBytes !== $totalBytes) {
        throw new RuntimeException(
            "Manifesto recusado: total_uncompressed_bytes diverge do conteúdo.",
        );
    }
    return $manifest;
}
function admin_update_validate_zip($zip): array
{
    if ($zip->numFiles > PRONTOO_UPDATE_MAX_ENTRIES) {
        throw new RuntimeException(
            "O ZIP excede o limite de entradas permitido.",
        );
    }
    admin_update_validate_entry_set($zip);
    foreach (
        [
            "index.php",
            "app/prontoo.php",
            "version.json",
            "br/index.php",
            "public/assets/design-system.css",
        ]
        as $file
    ) {
        if (admin_update_zip_exact_index($zip, $file) === false) {
            throw new RuntimeException(
                "O arquivo não parece ser um pacote Prontoo válido: falta " .
                    $file .
                    ".",
            );
        }
    }
    if (admin_update_zip_exact_index($zip, "rom.php") !== false) {
        throw new RuntimeException(
            "Pacote recusado: rom.php não pode mais existir na raiz. Use a função Atualização do Desenvolvedor.",
        );
    }

    $versionRaw = admin_update_zip_text($zip, "version.json");
    $versionJson = json_decode($versionRaw, true);
    if (!is_array($versionJson)) {
        throw new RuntimeException("version.json inválido ou ilegível.");
    }
    $version = trim((string) ($versionJson["version"] ?? ""));
    $release = trim((string) ($versionJson["release"] ?? ""));
    $assetVersion = trim((string) ($versionJson["asset_version"] ?? ""));
    if (
        $version === "" ||
        !preg_match('/^1\.\d{1,2}\.\d{1,2}\.\d+$/', $version)
    ) {
        throw new RuntimeException(
            "version.json não segue o padrão 1.Mês.Dia.Sequencial.",
        );
    }
    if ($release !== $version || $assetVersion !== $version) {
        throw new RuntimeException(
            "Pacote recusado: version, release e asset_version devem informar a mesma versão.",
        );
    }
    if (version_compare($version, admin_update_current_semver(), "<")) {
        throw new RuntimeException(
            "Pacote recusado: versão inferior à instalada.",
        );
    }
    $manifest = admin_update_validate_manifest($zip);
    if (
        trim((string) ($manifest["version"] ?? "")) !== $version ||
        trim((string) ($manifest["release"] ?? "")) !== $version
    ) {
        throw new RuntimeException(
            "Pacote recusado: manifesto e version.json informam versões diferentes.",
        );
    }

    $app = admin_update_zip_text($zip, "app/prontoo.php");
    if (
        $app === "" ||
        !str_contains($app, "PRONTOO_VERSION") ||
        !str_contains($app, "PRONTOO_MIN_PHP_VERSION")
    ) {
        throw new RuntimeException(
            "app/prontoo.php não contém as constantes mínimas do runtime.",
        );
    }
    foreach (
        ["PRONTOO_VERSION_FALLBACK", "PRONTOO_ASSET_REV_FALLBACK"]
        as $constant
    ) {
        if (
            !preg_match(
                '/' . preg_quote($constant, '/') . '\s*=\s*["\']([^"\']+)["\']/',
                $app,
                $match,
            ) ||
            trim((string) ($match[1] ?? "")) !== $version
        ) {
            throw new RuntimeException(
                "Pacote recusado: " .
                    $constant .
                    " diverge de version.json.",
            );
        }
    }
    $landing = admin_update_zip_text($zip, "br/index.php");
    if (
        $landing === "" ||
        !preg_match(
            '/BR_LANDING_VERSION_FALLBACK\s*=\s*["\']([^"\']+)["\']/',
            $landing,
            $match,
        ) ||
        trim((string) ($match[1] ?? "")) !== $version
    ) {
        throw new RuntimeException(
            "Pacote recusado: a versão da Landing diverge de version.json.",
        );
    }
    $assetFiles = [
        "public/assets/app-icon-" . $assetVersion . ".png",
        "public/assets/prontoo-mark-" . $assetVersion . ".png",
        "public/assets/favicon-" . $assetVersion . ".png",
        "public/assets/favicon-" . $assetVersion . ".ico",
        "public/assets/pix-" . $assetVersion . ".svg",
    ];
    foreach ($assetFiles as $assetFile) {
        if (admin_update_zip_exact_index($zip, $assetFile) === false) {
            throw new RuntimeException(
                "Pacote recusado: asset_version exige o arquivo " .
                    $assetFile .
                    ".",
            );
        }
    }
    $css = admin_update_zip_text(
        $zip,
        "public/assets/design-system.css",
        PRONTOO_UPDATE_MAX_FILE_BYTES,
    );
    if ($css === "" || !str_contains($css, "pix-" . $assetVersion . ".svg")) {
        throw new RuntimeException(
            "Pacote recusado: o Design System diverge da revisão de assets.",
        );
    }
    if (
        preg_match(
            '/PRONTOO_MIN_PHP_VERSION\s*=\s*["\']([^"\']+)["\']/',
            $app,
            $m,
        ) &&
        version_compare(PHP_VERSION, (string) $m[1], "<")
    ) {
        throw new RuntimeException(
            "Este servidor usa PHP " .
                PHP_VERSION .
                ", mas o pacote exige PHP " .
                (string) $m[1] .
                ".",
        );
    }

    $total = 0;
    $files = 0;
    $dirs = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = admin_update_normalize_zip_name((string) ($stat["name"] ?? ""));
        if ($name === "") {
            throw new RuntimeException(
                "Pacote recusado: entrada inválida durante a contabilização.",
            );
        }
        if (str_ends_with($name, "/")) {
            $dirs++;
            continue;
        }
        $size = (int) ($stat["size"] ?? 0);
        if ($size > PRONTOO_UPDATE_MAX_FILE_BYTES) {
            throw new RuntimeException(
                "Arquivo individual excede o limite permitido: " . $name,
            );
        }
        $total += $size;
        if ($total > PRONTOO_UPDATE_MAX_UNPACKED_BYTES) {
            throw new RuntimeException(
                "Conteúdo descompactado excede o limite permitido.",
            );
        }
        $files++;
    }

    return [
        "version" => $version,
        "files" => $files,
        "dirs" => $dirs,
        "entries" => (int) $zip->numFiles,
        "unpacked_bytes" => $total,
        "manifest" => $manifest,
    ];
}

function admin_update_post_validate_published(
    array $manifest,
    string $version,
): void {
    $root = admin_update_root();
    foreach ((array) ($manifest["files"] ?? []) as $name => $definition) {
        $entry = admin_update_normalize_zip_name((string) $name);
        $target = $entry !== "" ? admin_update_safe_target($entry) : null;
        if ($target === null || !is_file($target)) {
            throw new RuntimeException(
                "Pós-validação da release falhou: arquivo publicado ausente: " .
                    (string) $name,
            );
        }
        $hash = is_array($definition)
            ? (string) ($definition["sha256"] ?? "")
            : (string) $definition;
        $actual = hash_file("sha256", $target);
        if (!is_string($actual) || !hash_equals(strtolower($hash), strtolower($actual))) {
            throw new RuntimeException(
                "Pós-validação da release falhou: hash divergente em " .
                    (string) $name,
            );
        }
    }
    $versionFile = $root . "/version.json";
    $versionPayload = is_file($versionFile)
        ? json_decode((string) prontoo_fs_read($versionFile, false), true)
        : null;
    if (
        !is_array($versionPayload) ||
        trim((string) ($versionPayload["version"] ?? "")) !== $version ||
        trim((string) ($versionPayload["release"] ?? "")) !== $version ||
        trim((string) ($versionPayload["asset_version"] ?? "")) !== $version
    ) {
        throw new RuntimeException(
            "Pós-validação da release falhou: version.json publicado é divergente.",
        );
    }
    $installedManifest = $root . "/" . PRONTOO_UPDATE_MANIFEST;
    $installedPayload = is_file($installedManifest)
        ? json_decode((string) prontoo_fs_read($installedManifest, false), true)
        : null;
    if (
        !is_array($installedPayload) ||
        trim((string) ($installedPayload["version"] ?? "")) !== $version ||
        trim((string) ($installedPayload["release"] ?? "")) !== $version
    ) {
        throw new RuntimeException(
            "Pós-validação da release falhou: manifesto publicado é divergente.",
        );
    }
    $compatiblePhpBinary = admin_update_compatible_php_binary();
    if ($compatiblePhpBinary === "") {
        return;
    }
    $script =
        'if(function_exists("opcache_reset")){@opcache_reset();}' .
        'putenv("PRONTOO_DISABLE_LIGHT_BOOT=1");' .
        'putenv("PRONTOO_UPDATE_VALIDATION=1");' .
        '$_SERVER["REQUEST_METHOD"]="GET";' .
        '$_GET["r"]="login";' .
        'require ' . var_export($root . "/app/prontoo.php", true) . ';' .
        'if(function_exists("prontoo_load_full_runtime_modules")){prontoo_load_full_runtime_modules();}' .
        '\\Prontoo\\Core\\Install\\RuntimeContract::assert(' .
        var_export($root, true) . ',' . var_export($version, true) . ');' .
        'if((string)PRONTOO_VERSION!==' . var_export($version, true) .
        '||(string)PRONTOO_ASSET_REV!==' . var_export($version, true) .
        '){throw new RuntimeException("Versão publicada divergente.");}' .
        'echo "PRONTOO_UPDATE_RUNTIME_OK";';
    $cmd =
        escapeshellarg($compatiblePhpBinary) .
        " -d display_errors=1" .
        " -d opcache.enable_cli=0" .
        " -d opcache.validate_timestamps=1" .
        " -d opcache.revalidate_freq=0 -r " .
        escapeshellarg($script) .
        " 2>&1";
    $result = prontoo_runtime_attempt(
        static function () use ($cmd): array {
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);
            return [$output, $code];
        },
        null,
        "pós-validação do runtime publicado",
    );
    if (!is_array($result)) {
        throw new RuntimeException(
            "Pós-validação da release falhou: não foi possível iniciar o runtime publicado.",
        );
    }
    [$output, $code] = $result;
    $text = implode("\n", array_map("strval", (array) $output));
    if ($code !== 0 || !str_contains($text, "PRONTOO_UPDATE_RUNTIME_OK")) {
        throw new RuntimeException(
            "Pós-validação da release falhou. Runtime: " .
                trim(mb_substr($text, 0, 360)),
        );
    }
}
function admin_update_extract_zip(string $zipPath): array
{
    if (!class_exists("ZipArchive")) {
        throw new RuntimeException(
            "Extensão ZipArchive indisponível no PHP. Ative a extensão zip para usar Atualização.",
        );
    }
    $zip = new ZipArchive();
    $open = $zip->open($zipPath);
    if ($open !== true) {
        throw new RuntimeException("Não foi possível abrir o pacote ZIP.");
    }
    try {
        $meta = admin_update_validate_zip($zip);
        $version = (string) ($meta["version"] ?? "");
        $release = $version;
        $manifest = (array) ($meta["manifest"] ?? []);
    } catch (Throwable $e) {
        $zip->close();
        throw $e;
    }
    $root = admin_update_root();
    $stage =
        $root .
        "/storage/update_stage_" .
        date("Ymd_His") .
        "_" .
        bin2hex(random_bytes(4));
    $backups = [];
    $created = [];
    $createdDirectories = [];
    $changed = [];
    if (!is_dir($stage) && !prontoo_fs_mkdir($stage, 0770)) {
        $zip->close();
        throw new RuntimeException(
            "Não foi possível preparar diretório temporário de atualização.",
        );
    }
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || empty($stat["name"])) {
                continue;
            }
            $entry = admin_update_normalize_zip_name((string) $stat["name"]);
            if ($entry === "") {
                throw new RuntimeException(
                    "Pacote recusado: entrada inválida durante o staging.",
                );
            }
            if (
                str_ends_with($entry, "/") ||
                $entry === PRONTOO_UPDATE_SIGNATURE
            ) {
                continue;
            }
            $destStage = $stage . "/" . $entry;
            $dir = dirname($destStage);
            if (!is_dir($dir) && !prontoo_fs_mkdir($dir, 0775)) {
                throw new RuntimeException(
                    "Não foi possível criar staging para " . $entry,
                );
            }
            $stream = $zip->getStream((string) $stat["name"]);
            if (!$stream) {
                throw new RuntimeException("Não foi possível ler " . $entry);
            }
            $out = prontoo_runtime_attempt(
                static fn() => fopen($destStage, "wb"),
                false,
                "abertura do staging {$destStage}",
            );
            if (!$out) {
                fclose($stream);
                throw new RuntimeException(
                    "Não foi possível escrever staging para " . $entry,
                );
            }
            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);
            admin_update_lint_php_file($destStage);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || empty($stat["name"])) {
                continue;
            }
            $entry = admin_update_normalize_zip_name((string) $stat["name"]);
            if ($entry === "") {
                throw new RuntimeException(
                    "Pacote recusado: entrada inválida durante a publicação.",
                );
            }
            if (
                str_ends_with($entry, "/") ||
                $entry === PRONTOO_UPDATE_SIGNATURE
            ) {
                continue;
            }
            $src = $stage . "/" . $entry;
            if (!is_file($src)) {
                continue;
            }
            $target = admin_update_safe_target($entry, $createdDirectories);
            if ($target === null) {
                throw new RuntimeException(
                    "Não foi possível resolver destino seguro para " . $entry,
                );
            }
            if (is_dir($target)) {
                throw new RuntimeException(
                    "Não foi possível publicar arquivo sobre diretório: " .
                        $entry,
                );
            }
            $bak = null;
            if (file_exists($target) || is_link($target)) {
                $permissions = prontoo_fs_fileperms($target, false);
                $mode = is_int($permissions) ? $permissions & 0777 : 0644;
                $bak = $stage . "/.rollback/" . $entry;
                admin_update_copy_file_verified($target, $bak);
                $backups[] = [$target, $bak, $mode];
            } else {
                $created[] = $target;
            }
            // O rename ocorre no mesmo volume e substitui o arquivo publicado
            // de forma atômica, sem janela em que a rota fique ausente.
            if (!prontoo_fs_rename($src, $target)) {
                throw new RuntimeException(
                    "Não foi possível publicar " . $entry,
                );
            }
            prontoo_fs_chmod($target, is_executable($target) ? 0755 : 0644);
            admin_update_invalidate_opcode($target);
            $changed[] = $entry;
        }
        if (function_exists("opcache_reset")) {
            @opcache_reset();
        }
        admin_update_post_validate_published($manifest, $version);
        foreach ($backups as [$target, $backup, $mode]) {
            if (file_exists($backup)) {
                admin_update_recursive_rm($backup);
            }
        }
        admin_update_recursive_rm($stage);
        $zip->close();
        return [
            "version" => $version,
            "release" => $release,
            "files" => count($changed),
            "entries" => (int) ($meta["entries"] ?? 0),
            "unpacked_bytes" => (int) ($meta["unpacked_bytes"] ?? 0),
            "changed" => $changed,
        ];
    } catch (Throwable $e) {
        $rollbackErrors = admin_update_restore_backups(
            $backups,
            $created,
            $createdDirectories,
        );
        if (!$rollbackErrors) {
            admin_update_recursive_rm($stage);
        }
        $zip->close();
        if ($rollbackErrors) {
            throw new RuntimeException(
                $e->getMessage() .
                    " Rollback incompleto: " .
                    implode(", ", $rollbackErrors) .
                    ". Os arquivos de recuperação foram preservados em " .
                    basename($stage) .
                    ".",
                0,
                $e,
            );
        }
        throw $e;
    }
}
function page_admin_update(): void
{
    $c = require_can("admin_update");
    if (($c["scope"] ?? "") !== "global") {
        secure_relogin_after_forbidden_action("admin_update");
    }

    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $uploaded = $_FILES["zip"] ?? null;
        $updateLock = null;
        try {
            if (!is_array($uploaded)) {
                throw new RuntimeException(
                    "Envie um arquivo ZIP da atualização.",
                );
            }
            $err = (int) ($uploaded["error"] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                throw new RuntimeException(
                    "Falha no upload. Código: " . $err . ".",
                );
            }
            $size = (int) ($uploaded["size"] ?? 0);
            if ($size <= 0) {
                throw new RuntimeException("O arquivo enviado está vazio.");
            }
            if ($size > PRONTOO_UPDATE_MAX_BYTES) {
                throw new RuntimeException(
                    "O ZIP excede o limite de " .
                        admin_update_human_bytes(PRONTOO_UPDATE_MAX_BYTES) .
                        ".",
                );
            }
            $name = (string) ($uploaded["name"] ?? "pacote.zip");
            if (!str_ends_with(strtolower($name), ".zip")) {
                throw new RuntimeException("Envie apenas arquivo .zip.");
            }
            $updateLock = admin_update_acquire_lock();

            $zipPath =
                rtrim(admin_update_tmp_dir(), DIRECTORY_SEPARATOR) .
                DIRECTORY_SEPARATOR .
                "update_" .
                date("Ymd_His") .
                "_" .
                bin2hex(random_bytes(6)) .
                ".zip";
            if (
                !prontoo_fs_move_upload((string) $uploaded["tmp_name"], $zipPath)
            ) {
                throw new RuntimeException(
                    "Não foi possível mover o ZIP para a área temporária.",
                );
            }
            try {
                $result = admin_update_extract_zip($zipPath);
            } finally {
                prontoo_fs_unlink($zipPath);
            }
            audit("atualizacao_plataforma_aplicada", "plataforma", null, [
                "versao_pacote" => $result["version"] ?? "",
                "arquivos" => $result["files"] ?? 0,
                "entradas" => $result["entries"] ?? 0,
                "audit_body" =>
                    "Desenvolvedor aplicou pacote de atualização validado pela função Atualização.",
            ]);
            flash(
                "Atualização aplicada com sucesso. Versão do pacote: " .
                    (string) $result["version"] .
                    ". Arquivos substituídos: " .
                    (int) $result["files"] .
                    ".",
            );
        } catch (Throwable $e) {
            audit("atualizacao_plataforma_recusada", "plataforma", null, [
                "erro" => $e->getMessage(),
                "audit_body" =>
                    "Pacote de atualização recusado antes de concluir a aplicação.",
            ]);
            flash(
                "Atualização recusada: " .
                    app_public_error_message(
                        $e,
                        "o pacote não passou pelas validações de segurança.",
                    ),
                "bad",
            );
        } finally {
            admin_update_release_lock($updateLock);
        }
        redirect("admin_update");
    }

    $zipOk = class_exists("ZipArchive");
    $romLeft = is_file(
        rtrim(admin_update_root(), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR .
            "rom.php",
    );
    $status =
        '<div class="notice-kpis ds-kpi-grid admin-update-kpis">' .
        '<div class="notice-kpi ds-kpi ' .
        ($zipOk ? "" : "notice-kpi-critical") .
        '">' .
        icon($zipOk ? "inventory_2" : "error") .
        "<div><b>" .
        e($zipOk ? "Disponível" : "Indisponível") .
        "</b><span>ZipArchive</span></div></div>" .
        '<div class="notice-kpi ds-kpi ' .
        ($romLeft ? "notice-kpi-pending" : "") .
        '">' .
        icon($romLeft ? "warning" : "verified_user") .
        "<div><b>" .
        e($romLeft ? "Pendente" : "Removido") .
        "</b><span>rom.php público</span></div></div>" .
        '<div class="notice-kpi ds-kpi"><span class="pt-icon-glyph material-symbols-rounded" aria-hidden="true">memory</span><div><b>' .
        e(ini_get("memory_limit") ?: "n/d") .
        "</b><span>memory_limit</span></div></div>" .
        "</div>";

    $form =
        '<form method="post" enctype="multipart/form-data" class="admin-update-form">' .
        csrf_field() .
        '<div class="admin-update-form-head"><span class="admin-update-form-icon">' .
        icon("system_update_alt") .
        '</span><div><span class="eyebrow">Pacote oficial</span><h2>Aplicar atualização</h2><p>Selecione o ZIP distribuído para o Prontoo. A validação ocorre integralmente antes da substituição dos arquivos.</p></div></div>' .
        '<div class="admin-update-upload-zone">' .
        form_row(
            "Arquivo ZIP",
            '<input name="zip" type="file" accept=".zip,application/zip" required>',
        ) .
        '<div class="admin-update-upload-meta"><span>' .
        icon("inventory_2") .
        '<span>Somente arquivo <b>.zip</b></span></span><span>' .
        icon("data_usage") .
        '<span>Limite: <b>' .
        e(admin_update_human_bytes(PRONTOO_UPDATE_MAX_BYTES)) .
        '</b></span></span><span>' .
        icon("verified_user") .
        '<span>Manifesto e integridade obrigatórios</span></span></div></div>' .
        '<div class="admin-update-form-footer"><p>Configuração, storage, backups, arquivos temporários, scripts externos e rom.php não são aceitos no pacote.</p><button class="primary" type="submit">' .
        icon("system_update_alt") .
        '<span>Verificar e aplicar atualização</span></button></div></form>';

    page(
        "Atualização",
        page_head(
            "Atualização",
            "Aplicação segura de pacote oficial pelo Desenvolvedor.",
        ) .
            card($status, "admin-update-status-card") .
            card($form, "admin-update-card"),
    );
}
