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

final class AuditActivityRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function clinic_recent_metrics(array $clinicIds, int $days = 30): array
    
    {
    
        $clinicIds = array_values(
            array_unique(array_filter(array_map("intval", $clinicIds))),
        );
        if (!$clinicIds) {
            return [];
        }
        sort($clinicIds, SORT_NUMERIC);
        $days = max(1, min(366, $days));
        $from = date("Y-m-d", strtotime("-" . $days . " days"));
        $loader = function () use ($clinicIds, $from): array {
            try {
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.audit_activity.03.clinic_recent_metrics.01', array_merge($clinicIds, [$from]), ['itemCount' => count($clinicIds)])->fetchAll();
            } catch (Throwable $e) {
                error_log("[Prontoo clinic_recent_metrics] " . $e->getMessage());
                return [];
            }
            $out = [];
            foreach ($rows as $row) {
                $clinicId = (int) $row["clinic_id"];
                $metric = (string) $row["metric_key"];
                $out[$clinicId][$metric] = (int) $row["metric_value"];
            }
            return $out;
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "dashboard",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("clinic_metrics", [
                    $clinicIds,
                    $days,
                    $from,
                ]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("dashboard"),
                $loader,
                ["table:pi_clinic_daily_stats", "admin:clinic_metrics"],
            );
        }
        return $loader();
    
    }

    public static function audit_patient_name_by_link(int $patientId, ?int $cid = null): string
    
    {
    
        if ($patientId <= 0) {
            return "";
        }
        try {
            if ($cid) {
                $pat = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_patient_name_by_link.01', [$patientId, $cid], []);
            } else {
                $pat = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_patient_name_by_link.02', [
                    $patientId,
                ], []);
            }
            if (!$pat) {
                return "";
            }
            $person = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_patient_name_by_link.03', [
                (int) $pat["person_id"],
            ], []);
            return mb_trim((string) ($person["full_name"] ?? ""));
        } catch (Throwable $e) {
            return "";
        }
    
    }

    public static function audit_user_name_lookup(int $uid, ?int $cid = null): string
    
    {
    
        static $cache = [];
        if ($uid <= 0) {
            return "";
        }
        $memoryKey = ($cid ? "c" . $cid . ":" : "g:") . $uid;
        if (array_key_exists($memoryKey, $cache)) {
            return $cache[$memoryKey];
        }
        $loader = static function () use ($uid, $cid): string {
    
            try {
                $u = $cid
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_user_name_lookup.01', [$uid, $cid], [])
                    : \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_user_name_lookup.02', [$uid], []);
                return mb_trim((string) ($u["name"] ?? ""));
            } catch (Throwable $e) {
                error_log("[Prontoo audit user lookup] " . $e->getMessage());
                return "";
            }
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember']) && \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_read_allowed()) {
            $cache[$memoryKey] = (string) \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "lookup",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("audit_user_name", [$cid ?: 0, $uid]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("lookup"),
                $loader,
                ["table:pi_users", "table:pi_user_roles", "scope:" . ($cid ?: 0)],
            );
        } else {
            $cache[$memoryKey] = $loader();
        }
        return $cache[$memoryKey];
    
    }

    public static function audit_clinic_name_lookup(int $cid): string
    
    {
    
        static $cache = [];
        if ($cid <= 0) {
            return "";
        }
        if (array_key_exists($cid, $cache)) {
            return $cache[$cid];
        }
        $loader = static function () use ($cid): string {
    
            try {
                $cl = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_clinic_name_lookup.01', [$cid], []);
                return mb_trim((string) ($cl["display_name"] ?? ""));
            } catch (Throwable $e) {
                error_log("[Prontoo audit clinic lookup] " . $e->getMessage());
                return "";
            }
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember']) && \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_read_allowed()) {
            $cache[$cid] = (string) \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "clinic",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("audit_clinic_name", $cid),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("clinic"),
                $loader,
                ["table:pi_clinics", "scope:" . $cid],
            );
        } else {
            $cache[$cid] = $loader();
        }
        return $cache[$cid];
    
    }

    public static function audit_enrich_context(
        string $event,
        ?string $entity,
        mixed $entityId,
        array $context,
        ?int $cid = null,
    ): array 
    {
    
        $id = (int) $entityId;
        if (empty($context["clinic_name"]) && $cid) {
            $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_clinic_name_lookup((int) $cid);
            if ($n !== "") {
                $context["clinic_name"] = $n;
            }
        }
        if (empty($context["patient_name"])) {
            $pid =
                (int) ($context["patient_link_id"] ??
                    ($entity === "paciente" && $id > 0 ? $id : 0));
            if ($pid > 0) {
                $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_patient_name_by_link($pid, $cid);
                if ($n !== "") {
                    $context["patient_name"] = $n;
                }
            }
        }
        if (empty($context["doctor_name"]) && !empty($context["doctor_user_id"])) {
            $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_user_name_lookup((int) $context["doctor_user_id"], $cid);
            if ($n !== "") {
                $context["doctor_name"] = $n;
            }
        }
        if (empty($context["assigned_name"]) && !empty($context["assigned_to"])) {
            $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_user_name_lookup((int) $context["assigned_to"], $cid);
            if ($n !== "") {
                $context["assigned_name"] = $n;
            }
        }
        if (empty($context["target_name"]) && $entity === "usuario" && $id > 0) {
            $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_user_name_lookup($id, $cid);
            if ($n !== "") {
                $context["target_name"] = $n;
            }
        }
        if (empty($context["target_name"]) && !empty($context["destinatario"])) {
            $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_user_name_lookup((int) $context["destinatario"], $cid);
            if ($n !== "") {
                $context["target_name"] = $n;
            }
        }
        if (empty($context["task_title"]) && !empty($context["tarefa_id"])) {
            try {
                $t = $cid
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.01', [(int) $context["tarefa_id"], $cid], [])
                    : \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.02', [
                        (int) $context["tarefa_id"],
                    ], []);
                if ($t && !empty($t["title"])) {
                    $context["task_title"] = $t["title"];
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
            in_array(
                $event,
                [
                    "modelo_documento_criado",
                    "modelo_documento_atualizado",
                    "modelo_documento_aprovado",
                    "modelo_documento_rejeitado",
                ],
                true,
            ) &&
            $id > 0
        ) {
            try {
                $tpl = $cid
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.03', [$id, $cid], [])
                    : \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.04', [$id], []);
                if ($tpl) {
                    if (empty($context["titulo"]) && !empty($tpl["title"])) {
                        $context["titulo"] = $tpl["title"];
                    }
                    if (
                        empty($context["template_title"]) &&
                        !empty($tpl["title"])
                    ) {
                        $context["template_title"] = $tpl["title"];
                    }
                    if (
                        empty($context["document_type"]) &&
                        !empty($tpl["type_key"])
                    ) {
                        $context["document_type"] = $tpl["type_key"];
                    }
                    if (empty($context["status"]) && !empty($tpl["status"])) {
                        $context["status"] = function_exists(
                            "document_status_label",
                        )
                            ? \Prontoo\Domain\Documents\DocumentTypePolicy::document_status_label((string) $tpl["status"])
                            : (string) $tpl["status"];
                    }
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
            $entity === "documento" &&
            $id > 0 &&
            !in_array(
                $event,
                [
                    "modelo_documento_criado",
                    "modelo_documento_atualizado",
                    "modelo_documento_aprovado",
                    "modelo_documento_rejeitado",
                ],
                true,
            )
        ) {
            try {
                $d = $cid
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.05', [$id, $cid], [])
                    : \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.06', [$id], []);
                if ($d) {
                    if (empty($context["titulo"]) && !empty($d["title"])) {
                        $context["titulo"] = $d["title"];
                    }
                    if (
                        empty($context["document_type"]) &&
                        !empty($d["type_key"])
                    ) {
                        $context["document_type"] = $d["type_key"];
                    }
                    if (
                        empty($context["document_type_label"]) &&
                        !empty($d["type_key"])
                    ) {
                        $types = is_callable([\Prontoo\Domain\Documents\DocumentTypePolicy::class, 'document_type_options'])
                            ? \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options()
                            : [];
                        $context["document_type_label"] =
                            $types[(string) $d["type_key"]] ?? "Documento";
                    }
                    if (
                        empty($context["patient_link_id"]) &&
                        !empty($d["patient_link_id"])
                    ) {
                        $context["patient_link_id"] = (int) $d["patient_link_id"];
                    }
                    if (
                        empty($context["patient_name"]) &&
                        !empty($d["patient_name"])
                    ) {
                        $context["patient_name"] = $d["patient_name"];
                    }
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
        if ($entity === "consulta" && $id > 0) {
            try {
                $a = $cid
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.07', [$id, $cid], [])
                    : \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.08', [$id], []);
                if ($a) {
                    foreach (
                        [
                            "patient_link_id",
                            "doctor_user_id",
                            "start_at",
                            "end_at",
                            "reason",
                        ]
                        as $k
                    ) {
                        if (empty($context[$k]) && !empty($a[$k])) {
                            $context[$k] = $a[$k];
                        }
                    }
                    if (empty($context["patient_name"])) {
                        $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_patient_name_by_link(
                            (int) $a["patient_link_id"],
                            $cid,
                        );
                        if ($n !== "") {
                            $context["patient_name"] = $n;
                        }
                    }
                    if (empty($context["doctor_name"])) {
                        $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_user_name_lookup(
                            (int) $a["doctor_user_id"],
                            $cid,
                        );
                        if ($n !== "") {
                            $context["doctor_name"] = $n;
                        }
                    }
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
        if ($entity === "tarefa" && $id > 0) {
            try {
                $t = $cid
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.09', [$id, $cid], [])
                    : \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.10', [$id], []);
                if ($t) {
                    if (empty($context["task_title"])) {
                        $context["task_title"] = $t["title"] ?? "";
                    }
                    if (
                        empty($context["assigned_name"]) &&
                        !empty($t["assigned_to"])
                    ) {
                        $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_user_name_lookup((int) $t["assigned_to"], $cid);
                        if ($n !== "") {
                            $context["assigned_name"] = $n;
                        }
                    }
                    if (
                        empty($context["patient_name"]) &&
                        !empty($t["patient_link_id"])
                    ) {
                        $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_patient_name_by_link(
                            (int) $t["patient_link_id"],
                            $cid,
                        );
                        if ($n !== "") {
                            $context["patient_name"] = $n;
                        }
                    }
                    if (empty($context["due_at"]) && !empty($t["due_at"])) {
                        $context["due_at"] = $t["due_at"];
                    }
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
        if ($entity === "comunicado" && $id > 0) {
            try {
                $n = $cid
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.11', [$id, $cid], [])
                    : \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.12', [$id], []);
                if ($n && empty($context["notice_title"])) {
                    $context["notice_title"] = $n["title"] ?? "";
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
        if ($entity === "lead" && $id > 0) {
            try {
                $l = $cid
                    ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.13', [$id, $cid], [])
                    : \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.audit_activity.03.audit_enrich_context.14', [
                        $id,
                    ], []);
                if ($l && empty($context["target_name"])) {
                    $context["target_name"] = $l["name"] ?? "";
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
            in_array($entity, ["consultorio", "clinica", "assinatura"], true) &&
            $id > 0 &&
            empty($context["clinic_name"])
        ) {
            $n = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_clinic_name_lookup($id);
            if ($n !== "") {
                $context["clinic_name"] = $n;
            }
        }
        if (
            empty($context["environment_label"]) &&
            !empty($context["role_code"]) &&
            is_callable([\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::class, 'role_label_for'])
        ) {
            try {
                $context["environment_label"] = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for(
                    (string) $context["role_code"],
                    $cid ?: null,
                );
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $e->getMessage(),
                );
            }
        }
        return $context;
    
    }
}
