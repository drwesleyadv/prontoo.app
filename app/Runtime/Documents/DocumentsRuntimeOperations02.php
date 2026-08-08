<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Documents;

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

final class DocumentsRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function document_issue_meta_sentence(
        ?string $issuedName,
        ?string $patientName,
        null|string|int $issuedAt,
        string $status = "emitido",
    ): string 
    {
    
        $actor = mb_trim((string) ($issuedName ?? ""));
        $actor = $actor !== "" ? first_name($actor) : "Colaborador";
        $patient = mb_trim((string) ($patientName ?? ""));
        $patient = $patient !== "" ? $patient : "paciente";
        $when = dt_br($issuedAt);
        $verb =
            document_editable_status($status) && $status !== "emitido"
                ? "preparou"
                : "emitiu";
        return $actor . " " . $verb . " para " . $patient . " em " . $when . ".";
    
    }

    public static function document_time_br(?string $dt): string
    
    {
    
        $ts = app_storage_timestamp($dt);
        return $ts ? date("H\hi", $ts) : "";
    
    }

    public static function document_appointment_row(int $cid, int $appointmentId): ?array
    
    {
    
        if ($cid <= 0 || $appointmentId <= 0) {
            return null;
        }
        return one(
            "SELECT a.id,a.patient_link_id,a.doctor_user_id,a.start_at,a.end_at,a.reason,a.notes,a.status,a.consultation_started_at,a.consultation_finished_at,COALESCE(pr.title,a.reason,'Consulta') AS procedure_title,u.name AS doctor_name,p.full_name AS patient_name FROM pi_appointments a LEFT JOIN pi_procedures pr ON pr.id=a.procedure_id AND pr.clinic_id=a.clinic_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE a.id=? AND a.clinic_id=? LIMIT 1",
            [$appointmentId, $cid],
        ) ?:
            null;
    
    }

    public static function document_resolve_patient_appointment(
        int $cid,
        int $patientId = 0,
        int $appointmentId = 0,
    ): array 
    {
    
        if ($appointmentId > 0) {
            $appt = document_appointment_row($cid, $appointmentId);
            if (!$appt) {
                throw new RuntimeException(
                    "Agendamento não encontrado para este consultório.",
                );
            }
            $apptPatient = (int) ($appt["patient_link_id"] ?? 0);
            if ($apptPatient > 0) {
                require_patient_in_clinic($cid, $apptPatient);
                if ($patientId > 0 && $patientId !== $apptPatient) {
                    throw new RuntimeException(
                        "O agendamento escolhido pertence a outro paciente.",
                    );
                }
                $patientId = $apptPatient;
            } elseif ($patientId <= 0) {
                throw new RuntimeException(
                    "Escolha o paciente antes de vincular este agendamento.",
                );
            }
            return [$patientId, $appointmentId, $appt];
        }
        if ($patientId > 0) {
            require_patient_in_clinic($cid, $patientId);
        }
        return [$patientId, 0, null];
    
    }

    public static function document_appointment_options(
        int $cid,
        int $selectedId = 0,
        int $limit = 240,
    ): array 
    {
    
        $limit = max(20, min(500, $limit));
        $rows = q(
            "SELECT a.id,a.patient_link_id,a.start_at,a.end_at,a.reason,a.status,a.consultation_started_at,a.consultation_finished_at,COALESCE(pr.title,a.reason,'Consulta') AS procedure_title,u.name AS doctor_name,p.full_name AS patient_name FROM pi_appointments a LEFT JOIN pi_procedures pr ON pr.id=a.procedure_id AND pr.clinic_id=a.clinic_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE a.clinic_id=? AND COALESCE(a.status,'')<>'cancelado' ORDER BY ABS(TIMESTAMPDIFF(SECOND,a.start_at,NOW())) ASC, a.start_at DESC LIMIT " .
                (int) $limit,
            [$cid],
        )->fetchAll();
        $out = [];
        foreach ($rows as $a) {
            $id = (int) $a["id"];
            $when = dt_br($a["start_at"] ?? "");
            $patient = trim(
                (string) ($a["patient_name"] ?? "Paciente não vinculado"),
            );
            $proc = mb_trim((string) ($a["procedure_title"] ?? "Consulta"));
            $doctor = mb_trim((string) ($a["doctor_name"] ?? "Profissional"));
            $out[$id] = $when . " · " . $patient . " · " . $proc . " · " . $doctor;
        }
        if ($selectedId > 0 && !isset($out[$selectedId])) {
            $a = document_appointment_row($cid, $selectedId);
            if ($a) {
                $out[$selectedId] =
                    dt_br($a["start_at"] ?? "") .
                    " · " .
                    ($a["patient_name"] ?? "" ?: "Paciente não vinculado") .
                    " · " .
                    ($a["procedure_title"] ?? "" ?: "Consulta") .
                    " · " .
                    ($a["doctor_name"] ?? "" ?: "Profissional");
            }
        }
        return $out;
    
    }

    public static function document_appointment_select_field(
        int $cid,
        int $selectedId = 0,
    ): string 
    {
    
        return select_label(
            "Agendamento vinculado",
            "appointment_id",
            ["0" => "Sem agendamento vinculado"] +
                document_appointment_options($cid, $selectedId),
            (string) max(0, $selectedId),
        );
    
    }

    public static function document_context_ids_from_post(array $c): array
    
    {
    
        $cid = (int) ($c["clinic_id"] ?? 0);
        $ctx = [];
        $checks = [
            "lead_id" => ["table" => "pi_leads", "label" => "Interessado"],
            "procedure_id" => [
                "table" => "pi_procedures",
                "label" => "Procedimento",
            ],
            "task_id" => ["table" => "pi_tasks", "label" => "Tarefa"],
            "care_id" => ["table" => "pi_care", "label" => "Atividade"],
        ];
        foreach ($checks as $key => $cfg) {
            $id = max(0, (int) ($_POST[$key] ?? 0));
            if ($id <= 0) {
                continue;
            }
            $exists = (int) safe_val(
                "SELECT id FROM " .
                    $cfg["table"] .
                    " WHERE id=? AND clinic_id=? LIMIT 1",
                [$id, $cid],
                0,
            );
            if ($exists <= 0) {
                throw new RuntimeException(
                    $cfg["label"] . " não encontrado(a) neste consultório.",
                );
            }
            $ctx[$key] = $id;
        }
        $collab = max(0, (int) ($_POST["collaborator_user_id"] ?? 0));
        if ($collab > 0) {
            if (!clinic_user_exists($cid, $collab)) {
                throw new RuntimeException(
                    "Colaborador não encontrado neste consultório.",
                );
            }
            $ctx["collaborator_user_id"] = $collab;
        }
        return $ctx;
    
    }

    public static function document_context_select_options(
        int $cid,
        string $kind,
        int $selectedId = 0,
        int $limit = 120,
    ): array 
    {
    
        $limit = max(20, min(240, $limit));
        $out = [];
        try {
            switch ($kind) {
                case "lead":
                    $rows = q(
                        "SELECT id,name,phone,interest,stage,created_at FROM pi_leads WHERE clinic_id=? ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT " .
                            (int) $limit,
                        [$cid],
                    )->fetchAll();
                    foreach ($rows as $r) {
                        $meta = array_filter([
                            (string) ($r["phone"] ?? ""),
                            (string) ($r["interest"] ?? ""),
                            (string) ($r["stage"] ?? ""),
                        ]);
                        $out[(int) $r["id"]] =
                            mb_trim((string) $r["name"]) .
                            ($meta ? " · " . implode(" · ", $meta) : "");
                    }
                    break;
                case "procedure":
                    $rows = array_slice(procedure_options($cid, true), 0, $limit);
                    foreach ($rows as $r) {
                        $meta = array_filter([
                            (string) ($r["category"] ?? ""),
                            format_minutes((int) ($r["duration_minutes"] ?? 0)),
                            (int) ($r["price_cents"] ?? 0) > 0
                                ? money_br((int) $r["price_cents"])
                                : "",
                        ]);
                        $out[(int) $r["id"]] =
                            mb_trim((string) $r["title"]) .
                            ($meta ? " · " . implode(" · ", $meta) : "");
                    }
                    break;
                case "task":
                    $rows = q(
                        "SELECT t.id,t.title,t.status,t.due_at,u.name AS assigned_name FROM pi_tasks t LEFT JOIN pi_users u ON u.id=COALESCE(t.assigned_to,t.target_user_id) WHERE t.clinic_id=? ORDER BY FIELD(t.status,'aberta','em_andamento','concluida'), COALESCE(t.due_at,t.created_at) DESC, t.id DESC LIMIT " .
                            (int) $limit,
                        [$cid],
                    )->fetchAll();
                    foreach ($rows as $r) {
                        $meta = array_filter([
                            document_status_label((string) ($r["status"] ?? "")),
                            !empty($r["due_at"])
                                ? dt_br((string) $r["due_at"])
                                : "",
                            (string) ($r["assigned_name"] ?? ""),
                        ]);
                        $out[(int) $r["id"]] =
                            mb_trim((string) $r["title"]) .
                            ($meta ? " · " . implode(" · ", $meta) : "");
                    }
                    break;
                case "care":
                    $rows = q(
                        "SELECT c.id,c.record_type,c.title,c.created_at,p.full_name AS patient_name FROM pi_care c JOIN pi_patients pl ON pl.id=c.patient_link_id AND pl.clinic_id=c.clinic_id JOIN pi_persons p ON p.id=pl.person_id WHERE c.clinic_id=? AND c.deleted_at IS NULL ORDER BY c.created_at DESC,c.id DESC LIMIT " .
                            (int) $limit,
                        [$cid],
                    )->fetchAll();
                    foreach ($rows as $r) {
                        $title = mb_trim((string) ($r["title"] ?? ""));
                        if ($title === "") {
                            $title = "Atividade";
                        }
                        $meta = array_filter([
                            (string) ($r["patient_name"] ?? ""),
                            patient_record_type_label(
                                (string) ($r["record_type"] ?? ""),
                            ),
                            dt_br((string) ($r["created_at"] ?? "")),
                        ]);
                        $out[(int) $r["id"]] =
                            $title . ($meta ? " · " . implode(" · ", $meta) : "");
                    }
                    break;
                case "collaborator":
                    $collaboratorLoader =  fn(): array => q(
                        "SELECT u.id,u.name,u.email,GROUP_CONCAT(DISTINCT cr.label ORDER BY cr.sort_order SEPARATOR ', ') AS role_label FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE u.active=1 GROUP BY u.id,u.name,u.email ORDER BY u.name ASC LIMIT " .
                            (int) $limit,
                        [$cid],
                    )->fetchAll();
                    $rows = function_exists("server_json_cache_remember")
                        ? server_json_cache_remember(
                            "catalog",
                            server_json_cache_safe_key("collaborators", [$cid, $limit]),
                            server_json_cache_ttl("catalog"),
                            $collaboratorLoader,
                            ["clinic:" . $cid, "table:pi_user_roles"],
                        )
                        : $collaboratorLoader();
                    foreach ($rows as $r) {
                        $meta = array_filter([
                            (string) ($r["role_label"] ?? ""),
                            (string) ($r["email"] ?? ""),
                        ]);
                        $out[(int) $r["id"]] =
                            mb_trim((string) $r["name"]) .
                            ($meta ? " · " . implode(" · ", $meta) : "");
                    }
                    break;
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo document_context_select_options] " .
                    $kind .
                    " " .
                    $e->getMessage(),
            );
        }
        if ($selectedId > 0 && !isset($out[$selectedId])) {
            try {
                $label = "";
                if ($kind === "lead") {
                    $label = (string) safe_val(
                        "SELECT name FROM pi_leads WHERE id=? AND clinic_id=?",
                        [$selectedId, $cid],
                        "",
                    );
                } elseif ($kind === "procedure") {
                    $label = (string) safe_val(
                        "SELECT title FROM pi_procedures WHERE id=? AND clinic_id=?",
                        [$selectedId, $cid],
                        "",
                    );
                } elseif ($kind === "task") {
                    $label = (string) safe_val(
                        "SELECT title FROM pi_tasks WHERE id=? AND clinic_id=?",
                        [$selectedId, $cid],
                        "",
                    );
                } elseif ($kind === "care") {
                    $label = (string) safe_val(
                        "SELECT COALESCE(title,record_type) FROM pi_care WHERE id=? AND clinic_id=?",
                        [$selectedId, $cid],
                        "",
                    );
                } elseif ($kind === "collaborator") {
                    $label = (string) safe_val(
                        "SELECT name FROM pi_users u WHERE id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1)",
                        [$selectedId, $cid],
                        "",
                    );
                }
                if ($label !== "") {
                    $out[$selectedId] = $label;
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
        return $out;
    
    }

    public static function document_context_binding_fields(array $c, array $doc): string
    
    {
    
        $cid = (int) ($c["clinic_id"] ?? 0);
        $ctx = document_context_json_decode($doc["context_json"] ?? "{}");
        $lead = (int) ($ctx["lead_id"] ?? 0);
        $proc = (int) ($ctx["procedure_id"] ?? 0);
        $task = (int) ($ctx["task_id"] ?? 0);
        $care = (int) ($ctx["care_id"] ?? 0);
        $collab = (int) ($ctx["collaborator_user_id"] ?? 0);
        $html =
            '<div class="doc-context-grid"><section><h4>' .
            icon("personal_injury") .
            '<span>Paciente e agenda</span></h4><div class="two">' .
            form_row(
                "",
                patient_lookup_field(
                    $cid,
                    "patient_link_id",
                    (string) ($doc["patient_link_id"] ?? 0),
                    "patient_search_doc_" . (int) $doc["id"],
                ),
            ) .
            document_appointment_select_field(
                $cid,
                (int) ($doc["appointment_id"] ?? 0),
            ) .
            "</div></section>";
        $html .=
            "<section><h4>" .
            icon("dataset_linked") .
            '<span>Registros contextuais</span></h4><div class="two">' .
            select_label(
                "Interessado",
                "lead_id",
                ["0" => "Sem interessado vinculado"] +
                    document_context_select_options($cid, "lead", $lead),
                (string) $lead,
            ) .
            select_label(
                "Procedimento",
                "procedure_id",
                ["0" => "Sem procedimento vinculado"] +
                    document_context_select_options($cid, "procedure", $proc),
                (string) $proc,
            ) .
            select_label(
                "Tarefa",
                "task_id",
                ["0" => "Sem tarefa vinculada"] +
                    document_context_select_options($cid, "task", $task),
                (string) $task,
            ) .
            select_label(
                "Atividade da ficha",
                "care_id",
                ["0" => "Sem atividade vinculada"] +
                    document_context_select_options($cid, "care", $care),
                (string) $care,
            ) .
            select_label(
                "Colaborador",
                "collaborator_user_id",
                ["0" => "Usar colaborador atual"] +
                    document_context_select_options($cid, "collaborator", $collab),
                (string) $collab,
            ) .
            "</div></section></div>";
        return $html;
    
    }
}
