<?php
declare(strict_types=1);

namespace Prontoo\Presentation\DocumentPdf;

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

final class DocumentPdfPresentationOperations01
{
    private function __construct()
    {
    }

    public static function document_pdf_public_url(
        string $fileName,
        ?string $token = null,
        ?int $expires = null,
    ): string 
    {
    
        $url = (\Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::base_path() ?: "") . "/pdfs/" . rawurlencode(basename($fileName));
        $qs = [];
        if ($expires !== null && $expires > 0) {
            $qs["exp"] = (string) $expires;
        }
        if ($token) {
            $qs["t"] = $token;
        }
        return $qs ? $url . "?" . http_build_query($qs) : $url;
    
    }
}
