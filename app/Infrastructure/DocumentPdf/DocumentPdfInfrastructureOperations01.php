<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\DocumentPdf;

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

final class DocumentPdfInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function document_pdf_public_router_dir(): string
    
    {
    
        $dir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("pdfs");
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
        return $dir;
    
    }

    public static function document_pdf_storage_dir(): string
    
    {
    
        $dir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("pdfs");
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
        return $dir;
    
    }
}
