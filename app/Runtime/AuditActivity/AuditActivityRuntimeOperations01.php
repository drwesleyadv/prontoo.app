<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AuditActivity;

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

final class AuditActivityRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function mask(mixed $v): mixed
    
    {
    
        if (is_array($v)) {
            $o = [];
            foreach ($v as $k => $x) {
                $kl = strtolower((string) $k);
                if (preg_match("/senha|password|csrf|token|hash|secret/i", $kl)) {
                    $o[$k] = "***";
                } elseif (
                    preg_match(
                        '/(^|_)(cpf|cnpj|legal_document|documento_legal)(_|$)/i',
                        $kl,
                    )
                ) {
                    $o[$k] = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask_document_value($x);
                } else {
                    $o[$k] = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($x);
                }
            }
            return $o;
        }
        $s = (string) $v;
        if (preg_match('/^\d{11}$/', $s)) {
            return substr($s, 0, 3) . ".***.***-" . substr($s, -2);
        }
        if (preg_match('/^\d{14}$/', $s)) {
            return substr($s, 0, 2) . ".***.***/****-" . substr($s, -2);
        }
        if (filter_var($s, FILTER_VALIDATE_EMAIL)) {
            return preg_replace('/(^.).*(@.*$)/', '$1***$2', $s);
        }
        return mb_strlen($s) > 800 ? mb_substr($s, 0, 800) . "…" : $s;
    
    }

    public static function mask_document_value(mixed $v): string
    
    {
    
        $d = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) $v);
        if (strlen($d) === 11) {
            return substr($d, 0, 3) . ".***.***-" . substr($d, -2);
        }
        if (strlen($d) === 14) {
            return substr($d, 0, 2) . ".***.***/****-" . substr($d, -2);
        }
        return $d !== "" ? "***" : \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($v);
    
    }

    public static function audit_appointment_target(array $ctx): string
    
    {
    
        $patient = \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_patient_name($ctx, $ctx["patient_link_id"] ?? null);
        $start = mb_trim((string) ($ctx["start_at"] ?? ""));
        $when = $start !== "" ? " em " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($start) : "";
        return "consulta de " . $patient . $when;
    
    }

    public static function audit_due_text(array $ctx): string
    
    {
    
        $v = \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_ctx_pick($ctx, ["due_at", "vencimento", "expected_at"]);
        return $v !== "" ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($v) : "";
    
    }

    public static function audit_body_for_event(
        string $event,
        ?string $entity,
        mixed $entityId,
        array $ctx,
    ): string 
    {
    
        return \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations02::activity_direct_body($event, $entity, $entityId, $ctx);
    
    }

    public static function verify_audit_row(array $r): bool
    
    {
    
        $hash = mb_trim((string) ($r["integrity_hash"] ?? ""));
        try {
            if (class_exists("\\Prontoo\\Infrastructure\\Audit\\AuditChain")) {
                return \Prontoo\Infrastructure\Audit\AuditChain::verifyRow(
                    $r,
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key(),
                );
            }
            return $hash !== "" && hash_equals(
                $hash,
                hash_hmac("sha256", \Prontoo\Domain\AuditActivity\AuditRecordPolicy::audit_integrity_base($r), \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key()),
            );
        } catch (Throwable $e) {
            return false;
        }
    
    }

    public static function audit_chain_integrity_status(int $limit = 240): array
    
    {
    
        $limit = max(2, min(1000, $limit));
        try {
            $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                \Prontoo\Domain\AuditActivity\AuditRecordPolicy::audit_select_sql() . " ORDER BY a.id DESC LIMIT " . $limit,
            )->fetchAll();
            $sequenceOk = \Prontoo\Infrastructure\Audit\AuditChain::verifySequence(
                $rows,
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key(),
            );
            $headOk = \Prontoo\Infrastructure\Audit\AuditChain::storedHeadMatchesLatest();
            return [
                "ok" => $sequenceOk && $headOk,
                "sequence_ok" => $sequenceOk,
                "head_ok" => $headOk,
                "checked" => count($rows),
            ];
        } catch (Throwable $error) {
            error_log("[Prontoo audit chain] " . $error->getMessage());
            return [
                "ok" => false,
                "sequence_ok" => false,
                "head_ok" => false,
                "checked" => 0,
            ];
        }
    
    }

    public static function fetch_map(string $table, array $ids, string $cols = "id"): array
    
    {
    
        $table = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::allowed_db_table($table);
        $cols = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::safe_db_columns($cols);
        $ids = array_values(array_unique(array_map("intval", $ids)));
        if (!$ids) {
            return [];
        }
        $ids = array_slice($ids, 0, 300);
        $scopeCid = \Prontoo\Runtime\Tenant\SessionTenantAccess::clinicId();
        $loader = function () use ($table, $ids, $cols, $scopeCid): array {
    
            $ph = implode(",", array_fill(0, count($ids), "?"));
            if ($scopeCid > 0 && \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::tenant_table_is_scoped($table)) {
                $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "SELECT $cols FROM $table WHERE clinic_id=? AND id IN ($ph)",
                    array_merge([$scopeCid], $ids),
                )->fetchAll();
            } else {
                $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "SELECT $cols FROM $table WHERE id IN ($ph)",
                    $ids,
                )->fetchAll();
            }
            $m = [];
            foreach ($rows as $r) {
                if (isset($r["id"])) {
                    $m[(int) $r["id"]] = $r;
                }
            }
            return $m;
        };
        if (
            is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember']) &&
            \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_read_allowed()
        ) {
            $key = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("fetch_map", [
                $table,
                $ids,
                $cols,
                $scopeCid,
                defined("PRONTOO_SCHEMA_REV") ? PRONTOO_SCHEMA_REV : "",
            ]);
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "auxiliary",
                $key,
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("auxiliary"),
                $loader,
                ["table:" . $table, "scope:" . $scopeCid],
            );
        }
        return $loader();
    
    }

    public static function scoped_patient_map(
        int $cid,
        array $ids,
        string $cols = "id,person_id",
    ): array 
    {
    
        $cols = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::safe_db_columns($cols);
        $ids = array_values(array_unique(array_map("intval", $ids)));
        if ($cid <= 0 || !$ids) {
            return [];
        }
        $ids = array_slice($ids, 0, 300);
        $ph = implode(",", array_fill(0, count($ids), "?"));
        $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "SELECT $cols FROM pi_patients WHERE clinic_id=? AND id IN ($ph)",
            array_merge([$cid], $ids),
        )->fetchAll();
        $m = [];
        foreach ($rows as $r) {
            if (isset($r["id"])) {
                $m[(int) $r["id"]] = $r;
            }
        }
        return $m;
    
    }

    public static function scoped_user_map(int $cid, array $ids, string $cols = "id,name"): array
    
    {
    
        $cols = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::safe_db_columns($cols);
        $ids = array_values(array_unique(array_map("intval", $ids)));
        if ($cid <= 0 || !$ids) {
            return [];
        }
        $ids = array_slice($ids, 0, 300);
        $ph = implode(",", array_fill(0, count($ids), "?"));
        $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "SELECT $cols FROM pi_users u WHERE u.id IN ($ph) AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) ",
            array_merge($ids, [$cid]),
        )->fetchAll();
        $m = [];
        foreach ($rows as $r) {
            if (isset($r["id"])) {
                $m[(int) $r["id"]] = $r;
            }
        }
        return $m;
    
    }

    public static function audit_rows_light(
        string $where = "1=1",
        array $p = [],
        int $limit = 80,
        int $offset = 0,
    ): array 
    {
    
        $limit = max(1, min(120, $limit));
        $offset = max(0, $offset);
        try {
            return \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                \Prontoo\Domain\AuditActivity\AuditRecordPolicy::audit_select_sql() .
                    " WHERE " .
                    \Prontoo\Domain\AuditActivity\AuditRecordPolicy::audit_where_sql($where) .
                    " AND a.event_key NOT IN ('login_clinica_pendente','login_credencial_pendente') ORDER BY a.id DESC LIMIT $limit OFFSET $offset",
                $p,
            )->fetchAll();
        } catch (Throwable $e) {
            if (!\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_schema_error_is_missing_table($e)) {
                error_log("[Prontoo audit compact read] " . $e->getMessage());
            }
            return [];
        }
    
    }

    public static function activity_time_direct(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        $raw = mb_trim((string) ($value ?? ""));
        if ($raw === "") {
            return "—";
        }
        if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'app_datetime_br'])) {
            $formatted = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_datetime_br($raw, $clinicId, $context);
            if (trim($formatted) !== "" && $formatted !== $raw) {
                return $formatted;
            }
            if (preg_match('/^-?\d+$/', $raw)) {
                $formatted = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_datetime_br((int) $raw, $clinicId, $context);
                if (trim($formatted) !== "" && $formatted !== $raw) {
                    return $formatted;
                }
            }
        }
        if (preg_match('/^-?\d+$/', $raw)) {
            $ts = (int) $raw;
            if ($ts > 0) {
                return date("d/m/Y, H\hi", $ts);
            }
        }
        $ts = strtotime($raw);
        if (!$ts) {
            return $raw;
        }
        return date("d/m/Y, H\hi", $ts);
    
    }

    public static function activity_date_from_ctx(array $ctx, array $keys): string
    
    {
    
        foreach ($keys as $k) {
            $v = \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value($ctx[$k] ?? "");
            if ($v !== "") {
                return \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($v);
            }
        }
        return "";
    
    }

    public static function activity_environment_label(array $ctx): string
    
    {
    
        $label = \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value(
            $ctx["environment_label"] ??
                ($ctx["role_label"] ?? ($ctx["ambiente"] ?? "")),
        );
        if ($label !== "") {
            return $label;
        }
        $role = \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value($ctx["role_code"] ?? ($ctx["role"] ?? ""));
        $cid = (int) ($ctx["clinic_id"] ?? 0);
        if ($role !== "" && is_callable([\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::class, 'role_label_for'])) {
            try {
                $resolved = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, $cid > 0 ? $cid : null);
                if (mb_trim((string) $resolved) !== "") {
                    return mb_trim((string) $resolved);
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $e->getMessage(),
                );
            }
        }
        if (
            $role !== "" &&
            defined("PRONTOO_ROLES") &&
            isset(PRONTOO_ROLES[$role])
        ) {
            return (string) PRONTOO_ROLES[$role];
        }
        if (\Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value($ctx["scope"] ?? "") === "global") {
            return "Desenvolvedor";
        }
        return "";
    
    }
}
