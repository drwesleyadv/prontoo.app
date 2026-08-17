<?php
declare(strict_types=1);

namespace Prontoo\Presentation\AuthOnboarding;

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

final class AuthOnboardingPresentationOperations01
{
    private function __construct()
    {
    }

    public static function onboarding_tip_module_routes(): array
    
    {
    
        return [
            "painel",
            "operations",
            "leads",
            "patients",
            "appointments",
            "procedures",
            "documents",
            "tasks",
            "maestro",
            "audit",
            "financial",
            "notices",
            "users",
            "settings",
            "permissions",
        ];
    
    }

    public static function onboarding_tip_key(array $c, string $route): string
    
    {
    
        $role = (string) ($c["role"] ?? "usuario");
        $clinicId = max(0, (int) ($c["clinic_id"] ?? 0));
        return mb_substr(
            $route . ":" . $role . ":clinic:" . $clinicId,
            0,
            120,
        );
    
    }

    public static function valid_birth_date(null|string|int $birth): bool
    
    {
        return \Prontoo\Domain\Identity\IdentityDocumentValidator::birthDate($birth);
    
    }

    public static function login_last_credential_key(int $uid): string
    
    {
    
        return "last_login_credential_user_" . max(0, $uid);
    
    }

    public static function login_last_credential_normalize(mixed $raw): ?array
    
    {
    
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === "") {
                return null;
            }
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw)) {
            return null;
        }
        $scope = (string) ($raw["scope"] ?? "");
        if ($scope === "global") {
            return ["scope" => "global", "clinic_role_id" => null];
        }
        if ($scope === "clinic") {
            $roleId = (int) ($raw["clinic_role_id"] ?? ($raw["uc_id"] ?? 0));
            if ($roleId > 0) {
                return ["scope" => "clinic", "clinic_role_id" => $roleId];
            }
        }
        return null;
    
    }

    public static function login_last_credential_from_devices(int $uid): ?array
    
    {
    
        return null;
    
    }

    public static function login_credential_match(
        int $uid,
        bool $isAdmin,
        array $choices,
        ?array $credential,
    ): ?array 
    {
    
        if (!$credential) {
            return null;
        }
        if (($credential["scope"] ?? "") === "global") {
            return $isAdmin
                ? ["scope" => "global", "clinic_role_id" => null, "choice" => null]
                : null;
        }
        $roleId = (int) ($credential["clinic_role_id"] ?? 0);
        if ($roleId <= 0) {
            return null;
        }
        foreach ($choices as $choice) {
            if (
                (int) ($choice["id"] ?? 0) === $roleId &&
                (int) ($choice["user_id"] ?? $uid) === $uid
            ) {
                return [
                    "scope" => "clinic",
                    "clinic_role_id" => $roleId,
                    "choice" => $choice,
                ];
            }
        }
        return null;
    
    }

    public static function mfa_pending_login_clear(): void
    
    {
        unset(
            $_SESSION["pending_mfa_login"],
            $_SESSION["mfa_enrollment_secret"],
            $_SESSION["mfa_recovery_codes"],
            $_SESSION["mfa_pending_verified"],
        );
    
    }

    public static function login_session_remember(
        string $cpf,
        int $wait,
        string $message = "CPF ou senha não conferem.",
    ): void 
    {
    
        $_SESSION["login_last_cpf"] = $cpf;
        $_SESSION["login_wait_until"] = time() + max(0, $wait);
        $_SESSION["login_lock_message"] = $message;
    
    }

    public static function login_session_wait(): int
    
    {
    
        return max(0, (int) ($_SESSION["login_wait_until"] ?? 0) - time());
    
    }

    public static function login_session_forget(): void
    
    {
    
        unset(
            $_SESSION["login_wait_until"],
            $_SESSION["login_last_cpf"],
            $_SESSION["login_lock_message"],
        );
    
    }

    public static function seconds_label(int $s): string
    
    {
    
        $s = max(0, $s);
        $m = floor($s / 60);
        $r = $s % 60;
        return $m > 0
            ? $m . "min " . str_pad((string) $r, 2, "0", STR_PAD_LEFT) . "s"
            : $r . "s";
    
    }

    public static function footer_telemetry_line_values(array $series): array
    
    {
        return array_values(
            array_map(
      static fn($row): float => max(
          0.0,
          (float) ($row["value"] ?? 0),
      ),
      $series,
            ),
        );
    
    }

}
