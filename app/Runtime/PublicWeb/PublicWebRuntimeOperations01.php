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
            header("Content-Type: text/html; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("X-Robots-Tag: noindex, nofollow, noarchive");
            header("X-Content-Type-Options: nosniff");
        }
        $document = dirname(__DIR__, 3) . "/public/errors/404.html";
        if (is_file($document)) {
            readfile($document);
            return;
        }
        echo "<!doctype html><html lang=\"pt-BR\"><meta charset=\"utf-8\"><title>Página não encontrada · Prontoo</title><body><main><h1>Esta página não está por aqui</h1><p>Você pode voltar ao Prontoo e continuar normalmente.</p><p><a href=\"/\">Voltar ao Prontoo</a></p></main></body></html>";
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
