<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SecurityPrivacy;

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

final class SecurityPrivacyRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function security_trusted_proxy_request(): bool
    
    {
    
        $remote = mb_trim((string) ($_SERVER["REMOTE_ADDR"] ?? ""));
        if ($remote === "") {
            return false;
        }
        $raw = mb_trim((string) (getenv("PRONTOO_TRUSTED_PROXIES") ?: ""));
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
            if ($entry !== "" && \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_ip_in_cidr($remote, $entry)) {
                return true;
            }
        }
        return false;
    
    }

    public static function security_https_active(): bool
    
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
            \Prontoo\Runtime\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_trusted_proxy_request()
        ) {
            return true;
        }
        return false;
    
    }
}
