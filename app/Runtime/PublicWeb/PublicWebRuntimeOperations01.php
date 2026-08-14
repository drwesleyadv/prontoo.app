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
            header("Cache-Control: no-store, max-age=0");
            header("X-Robots-Tag: noindex, nofollow");
            header("X-Content-Type-Options: nosniff");
        }
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Página não encontrada · Prontoo</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#238763"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="application-name" content="Prontoo"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,-25..200&display=swap" rel="stylesheet"><link rel="stylesheet" href="/public/assets/presentation.css"></head><body class="public has-top-shell" style="--clinic-accent:#238763;--clinic-accent-dark:#105e44;--clinic-accent-soft:#dff3ea;--clinic-on-accent:#ffffff;"><a class="skip-link" href="#conteudo">Pular para o conteúdo</a><header class="top"><a class="brand" href="/" aria-label="Prontoo"><span class="brand-mark app-brandmark-inline"><img class="brand-mark-img app-brandmark-img" src="/favicon.ico" alt="" aria-hidden="true"></span><span class="brand-copy"><b>Prontoo</b></span></a><div class="top-actions"></div></header><main id="conteudo" tabindex="-1"><section class="auth login-card login-shell" aria-labelledby="not-found-title"><div class="auth-titleline login-titleline"><div class="auth-brandmark"><img class="auth-brandmark-favicon" src="/favicon.ico" alt="" aria-hidden="true"></div><div><span class="eyebrow">Prontoo</span><h1 id="not-found-title">Página não encontrada</h1></div></div><div class="login-boot is-ok" role="status"><span aria-hidden="true"><span class="material-symbols-rounded">search_off</span></span><small>O endereço informado não está disponível.</small></div><p class="field-help">Confira o endereço ou volte para acessar o Prontoo.</p><a class="primary wide login-submit" href="/"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span><span>Voltar ao Prontoo</span></a></section></main></body></html>';
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
