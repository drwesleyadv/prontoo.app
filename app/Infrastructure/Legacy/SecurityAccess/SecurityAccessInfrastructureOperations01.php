<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Legacy\SecurityAccess;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class SecurityAccessInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function security_rate_limit(
        string $bucket,
        int $limit,
        int $windowSeconds,
    ): bool 
    {
    
        $bucket = preg_replace("/[^a-zA-Z0-9_\-]/", "_", $bucket) ?: "rate";
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);
        $dir = storage_path("cache/rate-limits");
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            error_log("[Prontoo rate limit] Diretório de controle indisponível.");
            return true;
        }
        $file = $dir . "/" . hash("sha256", $bucket) . ".json";
        $handle = @fopen($file, "c+");
        if (!is_resource($handle)) {
            error_log("[Prontoo rate limit] Controle atômico indisponível.");
            return true;
        }
        $locked = false;
        $now = time();
        try {
            $locked = flock($handle, LOCK_EX);
            if (!$locked) {
                error_log("[Prontoo rate limit] Trava atômica indisponível.");
                return true;
            }
            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = is_string($raw) && $raw !== ""
                ? json_decode($raw, true)
                : [];
            if (!is_array($state)) {
                $state = [];
            }
            $hits = array_values(
                array_filter(
                    array_map("intval", $state),
                    static  fn($t) => $t > $now - $windowSeconds,
                ),
            );
            $limited = count($hits) >= $limit;
            if (!$limited) {
                $hits[] = $now;
            }
            $encoded = json_encode($hits, JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                return true;
            }
            rewind($handle);
            $written = false;
            if (ftruncate($handle, 0)) {
                $written = fwrite($handle, $encoded);
            }
            if ($written !== strlen($encoded) || !fflush($handle)) {
                error_log("[Prontoo rate limit] Estado atômico não pôde ser salvo.");
                return true;
            }
            @chmod($file, 0640);
            return $limited;
        } finally {
            if ($locked) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    
    }

    public static function security_value_bucket(string $prefix, string $value): string
    
    {
    
        return $prefix . "_" . hash("sha256", $value);
    
    }

    public static function password_common_rejected(string $s): bool
    
    {
    
        $v = strtolower(trim($s));
        $common = [
            "123456",
            "12345678",
            "123456789",
            "1234567890",
            "password",
            "senha",
            "senha123",
            "admin123",
            "prontoo123",
            "qwerty",
            "abc123",
            "111111",
            "000000",
        ];
        if (in_array($v, $common, true)) {
            return true;
        }
        if (preg_match('/^(.)\1{7,}$/', $v)) {
            return true;
        }
        if (preg_match("/^(0123456789|1234567890|9876543210)/", $v)) {
            return true;
        }
        return false;
    
    }

    public static function password_ok(string $s): bool
    
    {
    
        $length = mb_strlen($s);
        return $length >= 8 &&
            $length <= 128 &&
            !password_common_rejected($s);
    
    }

    public static function password_hash_secure(string $password): string
    
    {
    
        if (defined("PASSWORD_ARGON2ID")) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                "memory_cost" => 65536,
                "time_cost" => 3,
                "threads" => 1,
            ]);
        }
        return password_hash($password, PASSWORD_DEFAULT);
    
    }

    public static function mfa_meta_key(int $uid): string
    
    {
        return "mfa_user_" . max(0, $uid);
    
    }

    public static function mfa_base32_encode(string $bytes): string
    
    {
        $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $buffer = 0;
        $bits = 0;
        $out = "";
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= $alphabet[($buffer >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $out .= $alphabet[($buffer << (5 - $bits)) & 31];
        }
        return $out;
    
    }

    public static function mfa_base32_decode(string $value): string
    
    {
        $alphabet = array_flip(
            str_split("ABCDEFGHIJKLMNOPQRSTUVWXYZ234567"),
        );
        $value = strtoupper(
            preg_replace('/[^A-Z2-7]/i', "", $value) ?? "",
        );
        $buffer = 0;
        $bits = 0;
        $out = "";
        foreach (str_split($value) as $char) {
            if (!isset($alphabet[$char])) {
                throw new RuntimeException("Segredo MFA inválido.");
            }
            $buffer = ($buffer << 5) | (int) $alphabet[$char];
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 255);
            }
        }
        return $out;
    
    }

    public static function mfa_totp_counter(?int $timestamp = null): int
    
    {
        return intdiv($timestamp ?? time(), 30);
    
    }

    public static function mfa_totp_code(string $secret, int $counter): string
    
    {
        $binary = mfa_base32_decode($secret);
        $counterBytes = pack(
            "N2",
            (int) floor($counter / 4294967296),
            $counter & 0xffffffff,
        );
        $hash = hash_hmac("sha1", $counterBytes, $binary, true);
        $offset = ord($hash[19]) & 15;
        $number =
            ((ord($hash[$offset]) & 127) << 24) |
            ((ord($hash[$offset + 1]) & 255) << 16) |
            ((ord($hash[$offset + 2]) & 255) << 8) |
            (ord($hash[$offset + 3]) & 255);
        return str_pad(
            (string) ($number % 1000000),
            6,
            "0",
            STR_PAD_LEFT,
        );
    
    }

    public static function mfa_totp_matching_counter(
        string $secret,
        string $code,
        int $lastCounter = -1,
    ): ?int 
    {
        $code = preg_replace('/\D/', "", $code) ?? "";
        if (strlen($code) !== 6) {
            return null;
        }
        $current = mfa_totp_counter();
        for ($delta = -1; $delta <= 1; $delta++) {
            $counter = $current + $delta;
            if (
                $counter > $lastCounter &&
                hash_equals(mfa_totp_code($secret, $counter), $code)
            ) {
                return $counter;
            }
        }
        return null;
    
    }

    public static function mfa_recovery_code_normalize(string $code): string
    
    {
        return strtoupper(
            preg_replace('/[^A-Z0-9]/i', "", trim($code)) ?? "",
        );
    
    }

    public static function mfa_recovery_codes_generate(int $count = 10): array
    
    {
        $alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = "";
            for ($j = 0; $j < 12; $j++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] =
                substr($raw, 0, 4) .
                "-" .
                substr($raw, 4, 4) .
                "-" .
                substr($raw, 8, 4);
        }
        return $codes;
    
    }

    public static function mfa_totp_secret_generate(): string
    
    {
        return mfa_base32_encode(random_bytes(20));
    
    }

    public static function mfa_otpauth_uri(string $account, string $secret): string
    
    {
        $issuer = "Prontoo";
        return "otpauth://totp/" .
            rawurlencode($issuer . ":" . $account) .
            "?secret=" .
            rawurlencode($secret) .
            "&issuer=" .
            rawurlencode($issuer) .
            "&algorithm=SHA1&digits=6&period=30";
    
    }

    public static function user_auth_generation_key(int $uid): string
    
    {
        return "auth_user_" . max(0, $uid);
    
    }

    public static function device_cookie_name(): string
    
    {
    
        return "PRONTOO_DEVICE";
    
    }

    public static function device_session_lifetime_seconds(): int
    
    {
    
        return 86400;
    
    }

    public static function device_session_cookie_ttl_seconds(): int
    
    {
    
        return 2592000;
    
    }

    public static function device_hash_is_valid(string $hash): bool
    
    {
    
        return (bool) preg_match('/^[a-f0-9]{64}$/', $hash);
    
    }

    public static function device_login_fields(): string
    
    {
    
        return '<input type="hidden" name="device_hash" value="" data-device-hash><input type="hidden" name="device_label" value="" data-device-label><input type="hidden" name="device_platform" value="" data-device-platform><input type="hidden" name="device_meta" value="" data-device-meta>';
    
    }

    public static function device_cookie_pack(int $uid, string $deviceHash, string $token): string
    
    {
    
        return $uid . "." . $deviceHash . "." . $token;
    
    }

    public static function session_clinic_scope_id(): int
    
    {
    
        return \Prontoo\Core\Tenant\TenantRegistry::sessionClinicId();
    
    }

    public static function session_clinic_role_code(): string
    
    {
    
        return \Prontoo\Core\Tenant\TenantRegistry::sessionRoleCode();
    
    }

    public static function tenant_scoped_tables(): array
    
    {
    
        return \Prontoo\Core\Tenant\TenantRegistry::scopedTables();
    
    }

    public static function tenant_table_is_scoped(string $table): bool
    
    {
    
        return \Prontoo\Core\Tenant\TenantRegistry::isScoped($table);
    
    }

    public static function sql_fingerprint(string $sql): string
    
    {
    
        return hash("sha256", preg_replace("/\s+/", " ", trim($sql)));
    
    }

    public static function scope_violation_detail_decode(mixed $details): array
    
    {
    
        $raw = mb_trim((string) $details);
        if ($raw === "") {
            return ["v" => 1, "reason" => "Motivo não registrado."];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || (int) ($decoded["v"] ?? 0) < 2) {
            return ["v" => 1, "reason" => $raw];
        }
        return [
            "v" => (int) ($decoded["v"] ?? 2),
            "reason" => mb_trim((string) ($decoded["reason"] ?? "")),
            "method" => mb_trim((string) ($decoded["method"] ?? "")),
            "action" => mb_trim((string) ($decoded["action"] ?? "")),
            "operation" => mb_trim((string) ($decoded["operation"] ?? "")),
            "table" => mb_trim((string) ($decoded["table"] ?? "")),
            "sql_shape" => mb_trim((string) ($decoded["sql_shape"] ?? "")),
        ];
    
    }

    public static function scope_violation_detail_summary(mixed $details): string
    
    {
    
        $payload = scope_violation_detail_decode($details);
        $summary = mb_trim((string) ($payload["reason"] ?? ""));
        $context = array_values(
            array_filter([
                mb_trim((string) ($payload["operation"] ?? "")),
                mb_trim((string) ($payload["table"] ?? "")),
                mb_trim((string) ($payload["action"] ?? "")),
            ]),
        );
        if ($context) {
            $summary .= ($summary !== "" ? " · " : "") . implode(" · ", $context);
        }
        return $summary !== "" ? $summary : "Motivo não registrado.";
    
    }

    public static function scope_violation_safe_reason(string $detail): string
    
    {
    
        $detail = preg_replace('/#\d+\b/', "#?", $detail) ?? $detail;
        $detail = preg_replace('/\b(?:usuário|registro)\s+\d+\b/iu', '$1 ?', $detail) ?? $detail;
        $detail = preg_replace("/\s+/", " ", trim($detail)) ?? trim($detail);
        return mb_substr($detail, 0, 140);
    
    }

    public static function scope_violation_sql_shape(string $sql): string
    
    {
    
        $shape = preg_replace('/\/\*.*?\*\//s', " ", $sql) ?? $sql;
        $shape = preg_replace('/--[^\r\n]*/', " ", $shape) ?? $shape;
        $shape = preg_replace(
            "/'(?:''|\\\\.|[^'])*'|\"(?:\"\"|\\\\.|[^\"])*\"/s",
            "?",
            $shape,
        ) ?? $shape;
        $shape = preg_replace('/\b(?:0x[0-9a-f]+|\d+(?:\.\d+)?)\b/i', "?", $shape) ?? $shape;
        $shape = preg_replace("/\s+/", " ", trim($shape)) ?? trim($shape);
        return mb_substr($shape, 0, 170);
    
    }

    public static function with_read_only_guard_disabled(callable $fn): mixed
    
    {
    
        $had = array_key_exists("PRONTOO_READONLY_GUARD_DISABLED", $GLOBALS);
        $prev = $GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"] ?? null;
        $GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"] = true;
        try {
            return $fn();
        } finally {
            if ($had) {
                $GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"] = $prev;
            } else {
                unset($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"]);
            }
        }
    
    }

    public static function with_scope_guard_disabled(callable $fn): mixed
    
    {
    
        if (
            (int) ($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] ?? 0) > 0
        ) {
            throw new LogicException(
                "Contextos de consultório e sistema não podem ser combinados.",
            );
        }
        $had = array_key_exists("PRONTOO_SCOPE_GUARD_SYSTEM", $GLOBALS);
        $prev = $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] ?? null;
        $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] = true;
        try {
            return $fn();
        } finally {
            if ($had) {
                $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] = $prev;
            } else {
                unset($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"]);
            }
        }
    
    }

    public static function scope_guard_expected_clinic_id(): int
    
    {
    
        return max(
            0,
            (int) ($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] ?? 0),
        );
    
    }

    public static function scope_guard_active_clinic_id(): int
    
    {
    
        $expected = scope_guard_expected_clinic_id();
        return $expected > 0 ? $expected : session_clinic_scope_id();
    
    }

    public static function with_scope_guard_clinic(int $clinicId, callable $fn): mixed
    
    {
    
        if ($clinicId <= 0) {
            throw new InvalidArgumentException(
                "O contexto determinístico do guardião exige um consultório válido.",
            );
        }
        if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"])) {
            throw new LogicException(
                "Contextos de sistema e consultório não podem ser combinados.",
            );
        }
        $had = array_key_exists(
            "PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID",
            $GLOBALS,
        );
        $previous = scope_guard_expected_clinic_id();
        if ($previous > 0 && $previous !== $clinicId) {
            throw new LogicException(
                "Uma execução não pode trocar de consultório durante a mesma operação.",
            );
        }
        $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] = $clinicId;
        try {
            return $fn();
        } finally {
            if ($had) {
                $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] = $previous;
            } else {
                unset($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"]);
            }
        }
    
    }
}
