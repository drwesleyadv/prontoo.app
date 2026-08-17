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

    public static function footer_telemetry_volume_geometry(
        array $pageLoads,
        array $databaseQueries,
    ): array
    {
        $normalize = static fn(array $values): array => array_values(
            array_map(
                static fn(mixed $value): float => max(0.0, (float) $value),
                $values,
            ),
        );
        $pageLoads = $normalize($pageLoads);
        $databaseQueries = $normalize($databaseQueries);
        $pageScale = $pageLoads !== [] ? $pageLoads : [0.0];
        $queryScale = $databaseQueries !== [] ? $databaseQueries : [0.0];
        $scaleMin = min(0.0, min($pageScale), min($queryScale));
        $scaleMax = max(0.0, max($pageScale), max($queryScale));
        if ($scaleMax <= $scaleMin) {
            $scaleMax = $scaleMin + 1.0;
        }
        $range = $scaleMax - $scaleMin;
        $width = 960;
        $height = 210;
        $padLeft = 40;
        $padRight = 16;
        $padTop = 18;
        $padBottom = 32;
        $plotWidth = $width - $padLeft - $padRight;
        $plotHeight = $height - $padTop - $padBottom;
        $baseline = round(
            $padTop + $plotHeight - ((0.0 - $scaleMin) / $range) * $plotHeight,
            2,
            \RoundingMode::HalfAwayFromZero,
        );
        $makePoints = static function (array $values) use (
            $padLeft,
            $plotWidth,
            $padTop,
            $plotHeight,
            $scaleMin,
            $range,
        ): array {
            $count = count($values);
            $points = [];
            foreach ($values as $index => $value) {
                $x = $padLeft + ($count <= 1 ? 0 : $index * ($plotWidth / ($count - 1)));
                $y = $padTop + $plotHeight - (($value - $scaleMin) / $range) * $plotHeight;
                $points[] = [
                    round($x, 2, \RoundingMode::HalfAwayFromZero),
                    round($y, 2, \RoundingMode::HalfAwayFromZero),
                ];
            }
            return $points;
        };
        return [
            "width" => $width,
            "height" => $height,
            "view_box" => "0 0 960 210",
            "baseline" => $baseline,
            "page_loads" => $makePoints($pageLoads),
            "database_queries" => $makePoints($databaseQueries),
        ];
    }

    public static function footer_telemetry_mountain_area_path(
        array $points,
        float $baseline,
        float $cornerRadius = 4.0,
    ): string
    {
        if ($points === []) {
            return "";
        }
        $format = static function (float $value): string {
            $formatted = number_format($value, 2, ".", "");
            return rtrim(rtrim($formatted, "0"), ".");
        };
        $count = count($points);
        $first = $points[0];
        $last = $points[$count - 1];
        $path = "M" . $format((float) $first[0]) . " " . $format((float) $first[1]);
        if ($count > 1 && $cornerRadius > 0.0) {
            $stepX = abs((float) $points[1][0] - (float) $points[0][0]);
            $cornerX = min(max(1.5, $cornerRadius), $stepX * 0.14);
            for ($index = 1; $index < $count - 1; $index++) {
                $previous = $points[$index - 1];
                $current = $points[$index];
                $next = $points[$index + 1];
                $incomingX = max(0.000001, (float) $current[0] - (float) $previous[0]);
                $outgoingX = max(0.000001, (float) $next[0] - (float) $current[0]);
                $incomingRatio = min(0.49, $cornerX / $incomingX);
                $outgoingRatio = min(0.49, $cornerX / $outgoingX);
                $beforeX = (float) $current[0] - ((float) $current[0] - (float) $previous[0]) * $incomingRatio;
                $beforeY = (float) $current[1] - ((float) $current[1] - (float) $previous[1]) * $incomingRatio;
                $afterX = (float) $current[0] + ((float) $next[0] - (float) $current[0]) * $outgoingRatio;
                $afterY = (float) $current[1] + ((float) $next[1] - (float) $current[1]) * $outgoingRatio;
                $path .=
                    " L " . $format(round($beforeX, 2, \RoundingMode::HalfAwayFromZero)) .
                    " " . $format(round($beforeY, 2, \RoundingMode::HalfAwayFromZero)) .
                    " Q " . $format((float) $current[0]) .
                    " " . $format((float) $current[1]) .
                    " " . $format(round($afterX, 2, \RoundingMode::HalfAwayFromZero)) .
                    " " . $format(round($afterY, 2, \RoundingMode::HalfAwayFromZero));
            }
            $path .= " L " . $format((float) $last[0]) . " " . $format((float) $last[1]);
        } else {
            for ($index = 1; $index < $count; $index++) {
                $path .= " L " . $format((float) $points[$index][0]) . " " . $format((float) $points[$index][1]);
            }
        }
        return $path .
            " L " . $format((float) $last[0]) . " " . $format($baseline) .
            " L " . $format((float) $first[0]) . " " . $format($baseline) .
            " Z";
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
        $geometry = self::footer_telemetry_volume_geometry($pageLoads, $databaseQueries);
        $pageLoadArea = self::footer_telemetry_mountain_area_path(
            (array) $geometry["page_loads"],
            (float) $geometry["baseline"],
        );
        $databaseQueryArea = self::footer_telemetry_mountain_area_path(
            (array) $geometry["database_queries"],
            (float) $geometry["baseline"],
        );
        $pageLoadColor = $public ? "#1f6f56" : ($primaryColor !== "" ? $primaryColor : "#1f6f56");
        $databaseQueryColor = $public ? "#347963" : ($secondaryColor !== "" ? $secondaryColor : "#347963");
        $opacity = "0.5";
        return '<div class="footer-telemetry-lines app-telemetry-mountains" data-footer-telemetry-lines="1" data-footer-telemetry-lines-ready="1" aria-hidden="true"><svg viewBox="0 0 960 210" preserveAspectRatio="none" focusable="false" role="presentation"><path class="footer-telemetry-mountain-fill is-page-loads" fill="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pageLoadColor) .
            '" opacity="' . $opacity . '" stroke="none" d="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pageLoadArea) .
            '"/><path class="footer-telemetry-mountain-fill is-database-queries" fill="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($databaseQueryColor) .
            '" opacity="' . $opacity . '" stroke="none" d="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($databaseQueryArea) .
            '"/></svg></div>';
    }

}
