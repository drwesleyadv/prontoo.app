<?php
declare(strict_types=1);

namespace Prontoo\Domain\AuditActivity;

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

final class AuditRecordPolicy
{
    private function __construct()
    {
    }

    public static function audit_context_array(array $row): array
    
    {
    
        $raw = (string) ($row["context_json"] ?? "");
        if ($raw === "") {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    
    }

    public static function audit_integrity_base(array $r): string
    
    {
    
        return implode("|", [
            (string) ($r["clinic_id"] ?? ""),
            (string) ($r["user_id"] ?? ""),
            (string) ($r["event"] ?? ($r["event_key"] ?? "")),
            (string) ($r["entity"] ?? ($r["entity_key"] ?? "")),
            (string) ($r["entity_id"] ?? ""),
            (string) ($r["friendly_text"] ?? ""),
            (string) ($r["context_json"] ?? ""),
        ]);
    
    }

    public static function audit_select_sql(): string
    
    {
    
        return "SELECT a.id,a.clinic_id,a.user_id,a.event_key,a.event_key AS event,a.event_label,a.event_icon,a.entity_key,a.entity_key AS entity,a.entity_label,a.entity_id,a.friendly_text,a.context_json,a.integrity_hash,a.previous_hash,a.chain_hash,a.proof_hash,a.proof_json,a.policy_version,a.created_at FROM pi_audit a";
    
    }

    public static function int_ids(array $rows, string $key): array
    
    {
    
        $ids = [];
        foreach ($rows as $r) {
            $v = (int) ($r[$key] ?? 0);
            if ($v > 0) {
                $ids[$v] = $v;
            }
        }
        return array_values($ids);
    
    }

    public static function audit_where_sql(string $where): string
    
    {
    
        return trim($where) === "1=1" ? "1=1" : $where;
    
    }

}
