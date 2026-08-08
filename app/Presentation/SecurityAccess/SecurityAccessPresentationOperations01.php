<?php
declare(strict_types=1);

namespace Prontoo\Presentation\SecurityAccess;

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

final class SecurityAccessPresentationOperations01
{
    private function __construct()
    {
    }

    public static function has_session_user(): bool
    
    {
    
        return !empty($_SESSION["uid"]);
    
    }

    public static function security_client_bucket(string $prefix): string
    
    {
    
        return $prefix .
            "_" .
            hash(
                "sha256",
                ($_SERVER["REMOTE_ADDR"] ?? "") .
                    "|" .
                    ($_SERVER["HTTP_USER_AGENT"] ?? ""),
            );
    
    }

    public static function security_ip_bucket(string $prefix): string
    
    {
    
        return $prefix .
            "_ip_" .
            hash("sha256", (string) ($_SERVER["REMOTE_ADDR"] ?? ""));
    
    }

    public static function csrf(): string
    
    {
    
        if (empty($_SESSION["csrf"])) {
            $_SESSION["csrf"] = bin2hex(random_bytes(32));
        }
        return $_SESSION["csrf"];
    
    }

    public static function flash(?string $m = null, string $type = "ok"): ?array
    
    {
    
        if ($m !== null) {
            $_SESSION["flash"] = [$type, $m];
            return null;
        }
        $f = $_SESSION["flash"] ?? null;
        unset($_SESSION["flash"]);
        return $f;
    
    }

    public static function secure_session_destroy(): void
    
    {
    
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), "", [
                "expires" => time() - 42000,
                "path" => $p["path"] ?? "/",
                "domain" => $p["domain"] ?? "",
                "secure" => (bool) ($p["secure"] ?? false),
                "httponly" => true,
                "samesite" => $p["samesite"] ?? "Lax",
            ]);
        }
        session_destroy();
    
    }

    public static function security_global_scope_verified(int $uid): bool
    
    {
        if ($uid <= 0) {
            return false;
        }
        $mfaAt = (int) ($_SESSION["mfa_verified_at"] ?? 0);
        $privilegedAt = (int) ($_SESSION["privileged_auth_at"] ?? 0);
        $born = (int) ($_SESSION["born"] ?? 0);
        return $mfaAt > 0 &&
            $privilegedAt > 0 &&
            $mfaAt >= $born &&
            $privilegedAt >= $born;
    
    }

    public static function read_only_post_allowed(string $route): bool
    
    {
    
        return \Prontoo\Core\Readonly\ReadonlyPolicy::postAllowed(
            $route,
            (string) ($_POST["act"] ?? ""),
        );
    
    }

    public static function read_only_write_allowed(): bool
    
    {
    
        if (!empty($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"])) {
            return true;
        }
        if (($_SESSION["scope"] ?? "") !== "clinic") {
            return true;
        }
        return read_only_post_allowed(route());
    
    }

    public static function read_only_allowed_write_tables_for_request(): array
    
    {
    
        if (!empty($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"])) {
            return ["*"];
        }
        return \Prontoo\Core\Readonly\ReadonlyPolicy::allowedWriteTables(
            route(),
            (string) ($_POST["act"] ?? ""),
            ($_SESSION["scope"] ?? "") === "clinic",
        );
    
    }

    public static function read_only_write_allowed_for_sql(string $sql): bool
    
    {
    
        if (!empty($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"])) {
            return true;
        }
        return \Prontoo\Core\Readonly\ReadonlyPolicy::sqlAllowed(
            $sql,
            route(),
            (string) ($_POST["act"] ?? ""),
            ($_SESSION["scope"] ?? "") === "clinic",
        );
    
    }

    public static function enforce_action_integrity(array $c, string $route): void
    
    {
    
        \Prontoo\Core\Integrity\ActionProof::enforce(
            $route,
            (string) ($_SERVER["REQUEST_METHOD"] ?? "GET"),
            $_POST,
            $c,
        );
    
    }
}
