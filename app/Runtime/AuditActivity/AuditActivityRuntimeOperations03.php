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
    
            $placeholders = implode(",", array_fill(0, count($clinicIds), "?"));
            try {
                $rows = q(
                    "SELECT clinic_id,metric_key,SUM(metric_value) AS metric_value FROM pi_clinic_daily_stats WHERE clinic_id IN ($placeholders) AND day_date>=? GROUP BY clinic_id,metric_key",
                    array_merge($clinicIds, [$from]),
                )->fetchAll();
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
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "dashboard",
                server_json_cache_safe_key("clinic_metrics", [
                    $clinicIds,
                    $days,
                    $from,
                ]),
                server_json_cache_ttl("dashboard"),
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
                $pat = one(
                    "SELECT id,person_id FROM pi_patients WHERE id=? AND clinic_id=?",
                    [$patientId, $cid],
                );
            } else {
                $pat = one("SELECT id,person_id FROM pi_patients WHERE id=?", [
                    $patientId,
                ]);
            }
            if (!$pat) {
                return "";
            }
            $person = one("SELECT full_name FROM pi_persons WHERE id=?", [
                (int) $pat["person_id"],
            ]);
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
                    ? one("SELECT u.id,u.name FROM pi_users u WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) LIMIT 1", [$uid, $cid])
                    : one("SELECT id,name FROM pi_users WHERE id=?", [$uid]);
                return mb_trim((string) ($u["name"] ?? ""));
            } catch (Throwable $e) {
                error_log("[Prontoo audit user lookup] " . $e->getMessage());
                return "";
            }
        };
        if (function_exists("server_json_cache_remember") && server_json_cache_read_allowed()) {
            $cache[$memoryKey] = (string) server_json_cache_remember(
                "lookup",
                server_json_cache_safe_key("audit_user_name", [$cid ?: 0, $uid]),
                server_json_cache_ttl("lookup"),
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
                $cl = one("SELECT id,display_name FROM pi_clinics WHERE id=?", [$cid]);
                return mb_trim((string) ($cl["display_name"] ?? ""));
            } catch (Throwable $e) {
                error_log("[Prontoo audit clinic lookup] " . $e->getMessage());
                return "";
            }
        };
        if (function_exists("server_json_cache_remember") && server_json_cache_read_allowed()) {
            $cache[$cid] = (string) server_json_cache_remember(
                "clinic",
                server_json_cache_safe_key("audit_clinic_name", $cid),
                server_json_cache_ttl("clinic"),
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
            $n = audit_clinic_name_lookup((int) $cid);
            if ($n !== "") {
                $context["clinic_name"] = $n;
            }
        }
        if (empty($context["patient_name"])) {
            $pid =
                (int) ($context["patient_link_id"] ??
                    ($entity === "paciente" && $id > 0 ? $id : 0));
            if ($pid > 0) {
                $n = audit_patient_name_by_link($pid, $cid);
                if ($n !== "") {
                    $context["patient_name"] = $n;
                }
            }
        }
        if (empty($context["doctor_name"]) && !empty($context["doctor_user_id"])) {
            $n = audit_user_name_lookup((int) $context["doctor_user_id"], $cid);
            if ($n !== "") {
                $context["doctor_name"] = $n;
            }
        }
        if (empty($context["assigned_name"]) && !empty($context["assigned_to"])) {
            $n = audit_user_name_lookup((int) $context["assigned_to"], $cid);
            if ($n !== "") {
                $context["assigned_name"] = $n;
            }
        }
        if (empty($context["target_name"]) && $entity === "usuario" && $id > 0) {
            $n = audit_user_name_lookup($id, $cid);
            if ($n !== "") {
                $context["target_name"] = $n;
            }
        }
        if (empty($context["target_name"]) && !empty($context["destinatario"])) {
            $n = audit_user_name_lookup((int) $context["destinatario"], $cid);
            if ($n !== "") {
                $context["target_name"] = $n;
            }
        }
        if (empty($context["task_title"]) && !empty($context["tarefa_id"])) {
            try {
                $t = $cid
                    ? one(
                        "SELECT id,title FROM pi_tasks WHERE id=? AND clinic_id=?",
                        [(int) $context["tarefa_id"], $cid],
                    )
                    : one("SELECT id,title FROM pi_tasks WHERE id=?", [
                        (int) $context["tarefa_id"],
                    ]);
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
                    ? one(
                        "SELECT id,title,type_key,status FROM pi_document_templates WHERE id=? AND clinic_id=?",
                        [$id, $cid],
                    )
                    : one(
                        "SELECT id,title,type_key,status FROM pi_document_templates WHERE id=?",
                        [$id],
                    );
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
                            ? document_status_label((string) $tpl["status"])
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
                    ? one(
                        "SELECT d.id,d.title,d.type_key,d.patient_link_id,p.full_name AS patient_name FROM pi_documents d LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id AND pl.clinic_id=d.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.id=? AND d.clinic_id=?",
                        [$id, $cid],
                    )
                    : one(
                        "SELECT d.id,d.title,d.type_key,d.patient_link_id,p.full_name AS patient_name FROM pi_documents d LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.id=?",
                        [$id],
                    );
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
                        $types = function_exists("document_type_options")
                            ? document_type_options()
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
                    ? one(
                        "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,reason FROM pi_appointments WHERE id=? AND clinic_id=?",
                        [$id, $cid],
                    )
                    : one(
                        "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,reason FROM pi_appointments WHERE id=?",
                        [$id],
                    );
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
                        $n = audit_patient_name_by_link(
                            (int) $a["patient_link_id"],
                            $cid,
                        );
                        if ($n !== "") {
                            $context["patient_name"] = $n;
                        }
                    }
                    if (empty($context["doctor_name"])) {
                        $n = audit_user_name_lookup(
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
                    ? one(
                        "SELECT t.id,t.title,t.assigned_to,td.patient_link_id,t.due_at FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id WHERE t.id=? AND t.clinic_id=?",
                        [$id, $cid],
                    )
                    : one(
                        "SELECT t.id,t.title,t.assigned_to,td.patient_link_id,t.due_at FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id WHERE t.id=?",
                        [$id],
                    );
                if ($t) {
                    if (empty($context["task_title"])) {
                        $context["task_title"] = $t["title"] ?? "";
                    }
                    if (
                        empty($context["assigned_name"]) &&
                        !empty($t["assigned_to"])
                    ) {
                        $n = audit_user_name_lookup((int) $t["assigned_to"], $cid);
                        if ($n !== "") {
                            $context["assigned_name"] = $n;
                        }
                    }
                    if (
                        empty($context["patient_name"]) &&
                        !empty($t["patient_link_id"])
                    ) {
                        $n = audit_patient_name_by_link(
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
                    ? one(
                        "SELECT id,title FROM pi_notices WHERE id=? AND clinic_id=?",
                        [$id, $cid],
                    )
                    : one("SELECT id,title FROM pi_notices WHERE id=?", [$id]);
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
                    ? one(
                        "SELECT id,name,person_id FROM pi_leads WHERE id=? AND clinic_id=?",
                        [$id, $cid],
                    )
                    : one("SELECT id,name,person_id FROM pi_leads WHERE id=?", [
                        $id,
                    ]);
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
            $n = audit_clinic_name_lookup($id);
            if ($n !== "") {
                $context["clinic_name"] = $n;
            }
        }
        if (
            empty($context["environment_label"]) &&
            !empty($context["role_code"]) &&
            function_exists("role_label_for")
        ) {
            try {
                $context["environment_label"] = role_label_for(
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
