<?php
declare(strict_types=1);

namespace Prontoo\Runtime\PublicWeb;

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

final class PublicWebRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function page_mobile_web_access(): void
    {
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login", ["source" => "mobile_web"]);
    }

    public static function page_public_status(): void
    {
        if (!headers_sent()) {
            http_response_code(404);
            header("Content-Type: text/plain; charset=utf-8");
            header("Cache-Control: no-store, max-age=0");
            header("X-Robots-Tag: noindex, nofollow");
        }
        echo "Página não encontrada.";
    }

    public static function page_public_telemetry_tombstone(): void
    {
        if (!headers_sent()) {
            http_response_code(410);
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, max-age=0");
            header("X-Content-Type-Options: nosniff");
        }
        echo json_encode(
            [
                "ok" => false,
                "telemetry_public" => false,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public static function page_public_login_autotest(): void
    {
        if ((string) ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET") {
            if (!headers_sent()) {
                http_response_code(405);
                header("Allow: GET");
            }
            return;
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, max-age=0");
            header("X-Content-Type-Options: nosniff");
        }
        echo json_encode(
            [
                "ok" => true,
                "auto_login" => false,
                "redirect" => "",
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
