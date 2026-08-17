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

    public static function footer_telemetry_wave_path(
        array $values,
        float $maximum,
        int $width = 1000,
        int $height = 250,
    ): string
    {
        $values = array_values($values);
        $count = count($values);
        if ($count === 0) {
            return "";
        }
        $maximum = max(1.0, $maximum);
        $top = 10.0;
        $bottom = 14.0;
        $plotHeight = max(1.0, $height - $top - $bottom);
        $format = static function (float $value): string {
            $formatted = number_format($value, 2, ".", "");
            return rtrim(rtrim($formatted, "0"), ".");
        };
        $points = [];
        foreach ($values as $index => $value) {
            $x = $count <= 1 ? 0.0 : $index * ($width / ($count - 1));
            $y = $top + $plotHeight - (max(0.0, (float) $value) / $maximum) * $plotHeight;
            $points[] = [$x, $y];
        }
        $widthValue = $format((float) $width);
        $heightValue = $format((float) $height);
        if ($count === 1) {
            $y = $format($points[0][1]);
            return "M 0 " . $y .
                " L " . $widthValue . " " . $y .
                " L " . $widthValue . " " . $heightValue .
                " L 0 " . $heightValue . " Z";
        }
        $path = "M " . $format($points[0][0]) . " " . $format($points[0][1]);
        for ($index = 1; $index < $count; $index++) {
            $previousPoint = $points[$index - 1];
            $current = $points[$index];
            $middleX = ($previousPoint[0] + $current[0]) / 2;
            $path .=
                " C " . $format($middleX) . " " . $format($previousPoint[1]) .
                " " . $format($middleX) . " " . $format($current[1]) .
                " " . $format($current[0]) . " " . $format($current[1]);
        }
        return $path .
            " L " . $widthValue . " " . $heightValue .
            " L 0 " . $heightValue . " Z";
    }

    public static function footer_telemetry_mountains_html(
        array $payload,
        bool $public,
        string $primaryColor = "#1f6f56",
        string $secondaryColor = "#347963",
    ): string
    {
        $pageLoads = array_values(
            array_map(
                static fn(mixed $value): float => max(0.0, (float) $value),
                (array) ($payload["page_loads"] ?? []),
            ),
        );
        $databaseQueries = array_values(
            array_map(
                static fn(mixed $value): float => max(0.0, (float) $value),
                (array) ($payload["database_queries"] ?? []),
            ),
        );
        if ($pageLoads === [] && $databaseQueries === []) {
            return "";
        }
        $maximum = max(array_merge([1.0], $pageLoads, $databaseQueries));
        $pageLoadPath = self::footer_telemetry_wave_path($pageLoads, (float) $maximum);
        $databaseQueryPath = self::footer_telemetry_wave_path($databaseQueries, (float) $maximum);
        $pageLoadColor = $public ? "#347963" : ($secondaryColor !== "" ? $secondaryColor : "#347963");
        $databaseQueryColor = $public ? "#1f6f56" : ($primaryColor !== "" ? $primaryColor : "#1f6f56");
        return '<div class="footer-telemetry-lines app-telemetry-mountains" data-footer-telemetry-lines="1" data-footer-telemetry-lines-ready="1" aria-hidden="true"><svg viewBox="0 0 1000 250" preserveAspectRatio="none" focusable="false" role="presentation"><path class="footer-telemetry-wave-path is-page-loads" fill="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pageLoadColor) .
            '" d="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pageLoadPath) .
            '"/><path class="footer-telemetry-wave-path is-database-queries" fill="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($databaseQueryColor) .
            '" d="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($databaseQueryPath) .
            '"/></svg></div>';
    }

}
