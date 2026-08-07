<?php
declare(strict_types=1);

namespace Prontoo\Domain\Legacy\AuditActivity;

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

final class AuditWritePolicy
{
    private function __construct()
    {
    }

    public static function activity_meta_text(
        string $event,
        ?string $entity,
        string $currentMeta = "",
    ): string 
    {
    
        return trim($currentMeta);
    
    }

    public static function audit_should_write(string $event): bool
    
    {
    
        if ($event === "janela_aberta" && !PRONTOO_AUDIT_PAGE_VIEWS) {
            return false;
        }
        return true;
    
    }

    public static function audit_trusted_origin_resolve(
        array &$context,
        ?array $trustedOrigin,
    ): array 
    {
        foreach (array_keys($context) as $key) {
            if (
                str_starts_with((string) $key, "_audit_") ||
                str_starts_with((string) $key, "_skip_")
            ) {
                unset($context[$key]);
            }
        }
        $origin = is_array($trustedOrigin) ? $trustedOrigin : [];
        $createdAt = mb_trim((string) ($origin["created_at"] ?? ""));
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                $createdAt,
            ) !== 1
        ) {
            $createdAt = "";
        }
        $ipHash = strtolower(mb_trim((string) ($origin["ip_hash"] ?? "")));
        if (preg_match('/^[a-f0-9]{64}$/', $ipHash) !== 1) {
            $ipHash = "";
        }
        return [
            "skip_runtime_context" => !empty(
                $origin["skip_runtime_context"]
            ),
            "skip_context_enrichment" => !empty(
                $origin["skip_context_enrichment"]
            ),
            "has_user_id" => array_key_exists("user_id", $origin),
            "user_id" => max(0, (int) ($origin["user_id"] ?? 0)),
            "has_ip_hash" => array_key_exists("ip_hash", $origin),
            "ip_hash" => $ipHash,
            "has_user_agent" => array_key_exists("user_agent", $origin),
            "user_agent" => mb_substr(
                mb_trim((string) ($origin["user_agent"] ?? "")),
                0,
                180,
            ),
            "created_at" => $createdAt,
            "proof_context" =>
                isset($origin["proof_context"]) &&
                is_array($origin["proof_context"])
                    ? $origin["proof_context"]
                    : null,
        ];
    
    }

}
