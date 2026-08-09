<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SubscriptionSettings;

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

final class SubscriptionSettingsInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function subscription_payment_proof_validate_pdf(string $tmp): void
    
    {
    
        $fh = @fopen($tmp, "rb");
        if (!$fh) {
            throw new RuntimeException("Não foi possível ler o PDF.");
        }
        $head = (string) fread($fh, 5);
        fclose($fh);
        if ($head !== "%PDF-") {
            throw new RuntimeException("PDF inválido.");
        }
    
    }

    public static function subscription_payment_proof_storage(int $cid, bool $image = false): array
    
    {
    
        if ($cid <= 0) {
            throw new RuntimeException("Consultório inválido para o comprovante.");
        }
        $relativeRoot = $image ? "ssd/img/payment-proofs" : "ssd/payment-proofs";
        $root = $image ? \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("img/payment-proofs") : \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("payment-proofs");
        $directory = $root . "/clinic-" . $cid;
        foreach ([$root, $directory] as $path) {
            if (is_link($path)) {
                throw new RuntimeException("Diretório de comprovantes inválido.");
            }
            if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
                throw new RuntimeException("Não foi possível preparar o armazenamento do comprovante.");
            }
            @chmod($path, 0750);
        }
        $resolvedRoot = realpath($root);
        $resolvedDirectory = realpath($directory);
        if ($resolvedRoot === false || $resolvedDirectory === false || ($resolvedDirectory !== $resolvedRoot && !str_starts_with($resolvedDirectory, $resolvedRoot . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException("Diretório de comprovantes inválido.");
        }
        return ["absolute" => $resolvedDirectory, "relative" => $relativeRoot . "/clinic-" . $cid];
    
    }

    public static function subscription_payment_proof_absolute_path(?string $proofPath): ?string
    
    {
    
        $proofPath = mb_trim((string) $proofPath);
        if ($proofPath === "") {
            return null;
        }
        $proofPath = str_replace("\\", "/", $proofPath);
        if (str_contains($proofPath, "..")) {
            return null;
        }
        $roots = [
            "ssd/img/payment-proofs/" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("img/payment-proofs"),
            "ssd/payment-proofs/" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("payment-proofs"),
            "storage/payment-proofs/" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("payment-proofs"),
        ];
        $matchedPrefix = null;
        $rootPath = null;
        foreach ($roots as $prefix => $candidateRoot) {
            if (str_starts_with($proofPath, $prefix)) {
                $matchedPrefix = $prefix;
                $rootPath = $candidateRoot;
                break;
            }
        }
        if ($matchedPrefix === null || $rootPath === null) {
            return null;
        }
        $root = realpath($rootPath);
        $relative = substr($proofPath, strlen($matchedPrefix));
        if ($root === false || $relative === false || $relative === "") {
            return null;
        }
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relative);
        $full = realpath($candidate);
        if ($full === false || !is_file($full) || !str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $full;
    
    }

    public static function subscription_payment_delete_proof(?string $proofPath): bool
    
    {
    
        $full = \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_proof_absolute_path($proofPath);
        if ($full === null) {
            return true;
        }
        if (!is_file($full)) {
            return true;
        }
        $ok = @unlink($full);
        if (!$ok) {
            error_log(
                "[Prontoo payment proof delete] Falha ao excluir comprovante: " .
                    $full,
            );
        }
        return $ok;
    
    }
}
