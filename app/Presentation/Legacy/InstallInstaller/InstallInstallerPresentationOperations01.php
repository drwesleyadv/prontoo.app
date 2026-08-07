<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Legacy\InstallInstaller;

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

final class InstallInstallerPresentationOperations01
{
    private function __construct()
    {
    }

    public static function install_checks_html(array $checks): string
    
    {
    
        if (!$checks) {
            return "";
        }
        $out = '<div class="install-checks" aria-label="Pré-checagem do ambiente">';
        foreach ($checks as $check) {
            $class = !empty($check["ok"])
                ? "ok"
                : (($check["level"] ?? "error") === "warn"
                    ? "warn"
                    : "bad");
            $icon = !empty($check["ok"])
                ? "check_circle"
                : (($check["level"] ?? "error") === "warn"
                    ? "warning"
                    : "error");
            $out .=
                '<p class="install-check ' .
                $class .
                '">' .
                icon($icon) .
                "<span><b>" .
                e($check["label"]) .
                "</b><small>" .
                e($check["message"]) .
                "</small></span></p>";
        }
        return $out . "</div>";
    
    }
}
