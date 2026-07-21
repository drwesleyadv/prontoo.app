<?php
declare(strict_types=1);

function security_ip_in_cidr(string $ip, string $cidr): bool
{
    $cidr = trim($cidr);
    if ($ip === "" || $cidr === "") {
        return false;
    }
    if (!str_contains($cidr, "/")) {
        return hash_equals($cidr, $ip);
    }
    [$subnet, $bits] = array_pad(explode("/", $cidr, 2), 2, "");
    if (
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
        filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
    ) {
        $bits = max(0, min(32, (int) $bits));
        $mask = $bits === 0 ? 0 : (-1 << 32 - $bits) & 0xffffffff;
        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }
    return false;
}
function security_trusted_proxy_request(): bool
{
    $remote = trim((string) ($_SERVER["REMOTE_ADDR"] ?? ""));
    if ($remote === "") {
        return false;
    }
    $raw = trim((string) (getenv("PRONTOO_TRUSTED_PROXIES") ?: ""));
    $constantTrust =
        defined("PRONTOO_TRUST_PROXY_HEADERS") &&
        PRONTOO_TRUST_PROXY_HEADERS === true;
    $envTrust = (string) getenv("PRONTOO_TRUST_PROXY_HEADERS") === "1";
    if ($raw === "" && !($constantTrust || $envTrust)) {
        return false;
    }
    if ($raw === "" && ($constantTrust || $envTrust)) {
        return in_array($remote, ["127.0.0.1", "::1"], true);
    }
    foreach (preg_split("/[,\s]+/", $raw) ?: [] as $entry) {
        $entry = trim($entry);
        if ($entry !== "" && security_ip_in_cidr($remote, $entry)) {
            return true;
        }
    }
    return false;
}
function security_https_active(): bool
{
    $https = strtolower((string) ($_SERVER["HTTPS"] ?? ""));
    if ($https !== "" && $https !== "off") {
        return true;
    }
    if ((string) ($_SERVER["SERVER_PORT"] ?? "") === "443") {
        return true;
    }
    $forwarded = strtolower(
        (string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ""),
    );
    $front = strtolower((string) ($_SERVER["HTTP_FRONT_END_HTTPS"] ?? ""));
    if (
        ($forwarded === "https" || $front === "on") &&
        security_trusted_proxy_request()
    ) {
        return true;
    }
    return false;
}

function security_disable_runtime_error_display(): void
{
    ini_set("log_errors", "1");
    if (PHP_SAPI !== "cli" && has_cfg()) {
        ini_set("display_errors", "0");
        ini_set("display_startup_errors", "0");
        ini_set("html_errors", "0");
    }
}

function privacy_sanitize_text(string $text, int $limit = 900): string
{
    $text = str_replace("\0", "", $text);
    $text =
        preg_replace("/[A-Z]:\\[^\s]+|\/[^\s]+/u", "[caminho]", $text) ?? $text;
    $text =
        preg_replace(
            "/([A-Z0-9._%+\-])[A-Z0-9._%+\-]*(@[A-Z0-9.\-]+\.[A-Z]{2,})/iu",
            '$1***$2',
            $text,
        ) ?? $text;
    $text =
        preg_replace_callback(
            "/\b\d{3}\.?\d{3}\.?\d{3}\-?\d{2}\b/u",
            static function (array $m): string {
                $d = preg_replace("/\D+/", "", $m[0]) ?? "";
                return strlen($d) === 11
                    ? substr($d, 0, 3) . ".***.***-" . substr($d, -2)
                    : "***";
            },
            $text,
        ) ?? $text;
    $text =
        preg_replace_callback(
            "/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}\-?\d{2}\b/u",
            static function (array $m): string {
                $d = preg_replace("/\D+/", "", $m[0]) ?? "";
                return strlen($d) === 14
                    ? substr($d, 0, 2) . ".***.***/****-" . substr($d, -2)
                    : "***";
            },
            $text,
        ) ?? $text;
    $text =
        preg_replace(
            "/\b(?:senha|password|secret|token|csrf|db_pass)\s*[=:]\s*[^\s;,&]+/iu",
            '$1=***',
            $text,
        ) ?? $text;
    $text = preg_replace("/\s+/u", " ", trim($text)) ?? trim($text);
    return mb_strlen($text) > $limit
        ? mb_substr($text, 0, $limit) . "…"
        : $text;
}

function privacy_sanitize_error_message(Throwable $e, int $limit = 900): string
{
    return privacy_sanitize_text($e->getMessage(), $limit);
}

function privacy_log_file_label(string $file): string
{
    $file = str_replace("\\", "/", $file);
    $root = defined("PRONTOO_ROOT") ? str_replace("\\", "/", PRONTOO_ROOT) : "";
    if ($root !== "" && str_starts_with($file, rtrim($root, "/") . "/")) {
        return ltrim(substr($file, strlen(rtrim($root, "/"))), "/");
    }
    return basename($file);
}

function security_storage_deny_file(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (!is_dir($dir)) {
        return;
    }
    $ht = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ".htaccess";
    $rules =
        "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n<FilesMatch \".*\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n</FilesMatch>\n";
    if (!is_file($ht) || (string) @file_get_contents($ht) !== $rules) {
        @file_put_contents($ht, $rules, LOCK_EX);
    }
    @chmod($ht, 0640);
    $index =
        rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "index.html";
    if (!is_file($index)) {
        @file_put_contents(
            $index,
            '<!doctype html><meta charset="utf-8"><title>403</title>',
            LOCK_EX,
        );
    }
    @chmod($index, 0640);
}
