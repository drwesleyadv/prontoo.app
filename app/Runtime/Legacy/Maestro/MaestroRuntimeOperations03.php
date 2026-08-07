<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Maestro;

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

final class MaestroRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function maestro_fetch_candidates(array $rule, int $limit = 120): array
    
    {
    
        $cid = (int) $rule["clinic_id"];
        $trigger = (string) $rule["trigger_event"];
        $catalog = maestro_trigger_catalog();
        $item = $catalog[$trigger] ?? [];
        $cond = maestro_decode_json($rule["condition_json"] ?? "");
        $unit = (string) ($cond["unit"] ?? ($item["unit"] ?? "days"));
        $amount = maestro_amount(
            $cond["amount"] ?? ($cond["days"] ?? ($item["amount_default"] ?? 1)),
            (int) ($item["amount_default"] ?? 1),
            $unit,
        );
        $days = $amount;
        $limit = max(1, min(300, $limit));
        $out = [];
        $clinic = audit_clinic_name_lookup($cid);
        $prazo = (string) $amount;
        $activeTask = "'aberta','em_andamento','aguardando'";
        try {
            if ($trigger === "appointment_before_start") {
                $day = maestro_local_day($cid, $amount);
                [$start, $end] = maestro_local_day_utc_range($cid, $day);
                $rows = q(
                    "SELECT a.id,a.patient_link_id,a.doctor_user_id,a.start_at,a.reason,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.start_at>=? AND a.start_at<? AND a.status NOT IN ('cancelado','nao_compareceu','reagendado','atendimento_concluido','finalizado') ORDER BY a.start_at ASC LIMIT $limit",
                    [$cid, $start, $end],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp($r["start_at"]);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => date_br($r["start_at"] ?? ""),
                            "hora" => app_time_br($r["start_at"] ?? ""),
                            "dias" => $prazo,
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "consulta",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => (int) $r["patient_link_id"],
                            "appointment_id" => (int) $r["id"],
                        ],
                    );
                }
            } elseif ($trigger === "appointment_created_without_confirmation") {
                $rows = q(
                    "SELECT a.id,a.patient_link_id,a.start_at,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) AND a.status='agendado' ORDER BY a.created_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp($r["start_at"]);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => date_br($r["start_at"] ?? ""),
                            "hora" => app_time_br($r["start_at"] ?? ""),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "consulta",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => (int) $r["patient_link_id"],
                            "appointment_id" => (int) $r["id"],
                        ],
                    );
                }
            } elseif ($trigger === "appointment_changed_recent") {
                $rows = q(
                    "SELECT a.id,a.patient_link_id,a.start_at,a.change_reason,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.updated_at>=DATE_SUB(NOW(), INTERVAL ? DAY) AND a.change_reason IS NOT NULL AND a.status<>'cancelado' ORDER BY a.updated_at DESC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp($r["start_at"]);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => date_br($r["start_at"] ?? ""),
                            "hora" => app_time_br($r["start_at"] ?? ""),
                            "motivo" => $r["change_reason"] ?? "",
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "consulta",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => (int) $r["patient_link_id"],
                            "appointment_id" => (int) $r["id"],
                        ],
                    );
                }
            } elseif ($trigger === "appointment_cancelled_recent") {
                $rows = q(
                    "SELECT a.id,a.patient_link_id,a.start_at,a.cancel_reason,p.full_name AS patient_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE a.clinic_id=? AND COALESCE(a.updated_at,a.created_at)>=DATE_SUB(NOW(), INTERVAL ? DAY) AND (a.status='cancelado' OR a.cancel_reason IS NOT NULL) ORDER BY COALESCE(a.updated_at,a.created_at) DESC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp($r["start_at"]);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "data" => date_br($r["start_at"] ?? ""),
                            "hora" => app_time_br($r["start_at"] ?? ""),
                            "motivo" => $r["cancel_reason"] ?? "",
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "consulta",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => (int) $r["patient_link_id"],
                            "appointment_id" => (int) $r["id"],
                        ],
                    );
                }
            } elseif ($trigger === "appointment_waiting_without_start_minutes") {
                $rows = q(
                    "SELECT a.id,a.patient_link_id,a.arrived_at,a.start_at,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.arrived_at IS NOT NULL AND a.consultation_started_at IS NULL AND a.consultation_finished_at IS NULL AND a.arrived_at<=DATE_SUB(NOW(), INTERVAL ? MINUTE) AND a.status NOT IN ('cancelado','nao_compareceu','reagendado','atendimento_concluido','finalizado') ORDER BY a.arrived_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp($r["arrived_at"]);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => date_br($r["arrived_at"] ?? ""),
                            "hora" => app_time_br($r["arrived_at"] ?? ""),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "consulta_aguardando",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => (int) $r["patient_link_id"],
                            "appointment_id" => (int) $r["id"],
                        ],
                    );
                }
            } elseif ($trigger === "appointment_finished_without_care_hours") {
                $rows = q(
                    "SELECT a.id,a.patient_link_id,a.consultation_finished_at,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.consultation_finished_at IS NOT NULL AND a.consultation_finished_at<=DATE_SUB(NOW(), INTERVAL ? HOUR) AND NOT EXISTS (SELECT 1 FROM pi_care c WHERE c.clinic_id=a.clinic_id AND c.appointment_id=a.id AND c.deleted_at IS NULL LIMIT 1) ORDER BY a.consultation_finished_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp($r["consultation_finished_at"]);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => date_br($r["consultation_finished_at"] ?? ""),
                            "hora" => app_time_br(
                                $r["consultation_finished_at"] ?? "",
                            ),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "consulta_sem_registro",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => (int) $r["patient_link_id"],
                            "appointment_id" => (int) $r["id"],
                        ],
                    );
                }
            } elseif ($trigger === "appointment_today_without_patient") {
                [$start, $end] = maestro_local_day_utc_range(
                    $cid,
                    maestro_local_day($cid),
                );
                $rows = q(
                    "SELECT id,start_at,reason FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND patient_link_id IS NULL AND status<>'cancelado' ORDER BY start_at ASC LIMIT $limit",
                    [$cid, $start, $end],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp($r["start_at"]);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => "paciente não vinculado",
                            "data" => date_br($r["start_at"] ?? ""),
                            "hora" => app_time_br($r["start_at"] ?? ""),
                            "motivo" => $r["reason"] ?? "",
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "consulta_sem_paciente",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => (int) $r["id"],
                        ],
                    );
                }
            } elseif ($trigger === "lead_without_next_action_after_days") {
                $rows = q(
                    "SELECT id,name,created_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NULL AND created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY created_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "paciente" => $r["name"],
                            "data" => date_br((string) $r["created_at"]),
                            "hora" => "",
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "interessado_sem_retorno",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "lead_next_action_before_days") {
                $day = maestro_local_day($cid, $amount);
                [$start, $end] = maestro_local_day_utc_range($cid, $day);
                $rows = q(
                    "SELECT id,name,next_action_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NOT NULL AND next_action_at>=? AND next_action_at<? ORDER BY next_action_at ASC LIMIT $limit",
                    [$cid, $start, $end],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp($r["next_action_at"]);
                    $occ = str_replace("-", "", $day);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "paciente" => $r["name"],
                            "data" => date_br($r["next_action_at"]),
                            "hora" => app_time_br($r["next_action_at"]),
                            "dias" => $prazo,
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "interessado_retorno_agendado",
                            "source_entity_id" => (string) $r["id"] . ":" . $occ,
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "lead_next_action_due") {
                $rows = q(
                    "SELECT id,name,next_action_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NOT NULL AND next_action_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY next_action_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "paciente" => $r["name"],
                            "data" => date_br((string) $r["next_action_at"]),
                            "hora" => "",
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "interessado",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "lead_stalled_stage_after_days") {
                $rows = q(
                    "SELECT id,name,stage,COALESCE(updated_at,created_at) AS moved_at FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND COALESCE(updated_at,created_at)<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY moved_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "etapa" => document_status_label((string) $r["stage"]),
                            "data" => date_br((string) $r["moved_at"]),
                            "hora" => "",
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "interessado_parado",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "lead_converted_recent") {
                $rows = q(
                    "SELECT id,name,COALESCE(updated_at,created_at) AS moved_at FROM pi_leads WHERE clinic_id=? AND stage='convertido' AND COALESCE(updated_at,created_at)>=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY moved_at DESC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "data" => date_br((string) $r["moved_at"]),
                            "hora" => "",
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "interessado_convertido",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "patient_incomplete_registration") {
                $rows = q(
                    "SELECT pl.id,p.full_name FROM pi_patients pl INNER JOIN pi_persons p ON p.id=pl.person_id WHERE pl.clinic_id=? AND pl.active=1 AND pl.deleted_at IS NULL AND (pl.registration_needs_update=1 OR pl.phone IS NULL OR pl.phone='' OR pl.email IS NULL OR pl.email='' OR pl.address_city IS NULL OR pl.address_city='') ORDER BY pl.updated_at ASC,pl.id ASC LIMIT $limit",
                    [$cid],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "paciente",
                            "consultorio" => $clinic,
                            "paciente" => $r["full_name"],
                            "data" => date("d/m/Y"),
                            "hora" => date("H:i"),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "paciente_cadastro",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => (int) $r["id"],
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "patient_without_recent_activity") {
                $rows = q(
                    "SELECT pl.id,p.full_name FROM pi_patients pl INNER JOIN pi_persons p ON p.id=pl.person_id WHERE pl.clinic_id=? AND pl.active=1 AND pl.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM pi_care c WHERE c.clinic_id=pl.clinic_id AND c.patient_link_id=pl.id AND c.deleted_at IS NULL AND c.created_at>=DATE_SUB(NOW(), INTERVAL ? DAY)) AND NOT EXISTS (SELECT 1 FROM pi_appointments a WHERE a.clinic_id=pl.clinic_id AND a.patient_link_id=pl.id AND a.start_at>=DATE_SUB(NOW(), INTERVAL ? DAY)) ORDER BY pl.updated_at ASC, pl.id ASC LIMIT $limit",
                    [$cid, $amount, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "paciente",
                            "consultorio" => $clinic,
                            "paciente" => $r["full_name"],
                            "dias" => $prazo,
                            "prazo" => $prazo,
                            "data" => date("d/m/Y"),
                            "hora" => date("H:i"),
                        ],
                        [
                            "source_entity" => "paciente",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => (int) $r["id"],
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "patient_birthday_before_days") {
                $target = maestro_local_day($cid, $amount);
                $md = substr($target, 5, 5);
                $year = substr($target, 0, 4);
                $rows = q(
                    "SELECT pl.id AS patient_link_id, p.full_name, p.birth_date FROM pi_patients pl INNER JOIN pi_persons p ON p.id=pl.person_id WHERE pl.clinic_id=? AND pl.active=1 AND pl.deleted_at IS NULL AND p.birth_date IS NOT NULL AND DATE_FORMAT(p.birth_date,'%m-%d')=? ORDER BY p.full_name ASC LIMIT $limit",
                    [$cid, $md],
                )->fetchAll();
                foreach ($rows as $r) {
                    $birthTs = app_storage_timestamp($r["birth_date"]);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "paciente",
                            "consultorio" => $clinic,
                            "paciente" => $r["full_name"] ?: "paciente",
                            "data" => substr($target, 8, 2) .
                                "/" .
                                substr($target, 5, 2) .
                                "/" .
                                $year,
                            "hora" => "",
                            "dias" => $prazo,
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "aniversario_paciente",
                            "source_entity_id" =>
                                (string) $r["patient_link_id"] . ":" . $year,
                            "patient_link_id" => (int) $r["patient_link_id"],
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "document_template_pending_approval") {
                $rows = q(
                    "SELECT dt.id,dt.title,dt.status,u.name AS owner_name FROM pi_document_templates dt LEFT JOIN pi_users u ON u.id=dt.owner_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_owner WHERE ur_owner.user_id=u.id AND ur_owner.clinic_id=dt.clinic_id AND ur_owner.active=1) WHERE dt.clinic_id=? AND dt.status='pending_approval' AND COALESCE(dt.updated_at,dt.created_at)<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY COALESCE(dt.updated_at,dt.created_at) ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "modelo",
                            "consultorio" => $clinic,
                            "modelo" => $r["title"],
                            "colaborador" => $r["owner_name"] ?? "",
                            "data" => "",
                            "hora" => "",
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "modelo",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "document_preview_abandoned") {
                $rows = q(
                    "SELECT id,title,patient_link_id,updated_at,issued_at FROM pi_documents WHERE clinic_id=? AND document_status='preparado' AND COALESCE(updated_at,issued_at)<=DATE_SUB(NOW(), INTERVAL ? HOUR) ORDER BY COALESCE(updated_at,issued_at) ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp(
                        $r["updated_at"] ?: $r["issued_at"],
                    );
                    $out[] = maestro_match_base(
                        [
                            "origem" => "documento",
                            "consultorio" => $clinic,
                            "documento" => $r["title"],
                            "paciente" => $r["patient_link_id"]
                                ? patient_display_name(
                                    (int) $r["patient_link_id"],
                                    $cid,
                                )
                                : "",
                            "data" => date_br(
                                ($r["updated_at"] ?: $r["issued_at"]) ?? "",
                            ),
                            "hora" => app_time_br(
                                ($r["updated_at"] ?: $r["issued_at"]) ?? "",
                            ),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "documento_previa",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" =>
                                (int) ($r["patient_link_id"] ?? 0) ?: null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "document_issued_without_patient") {
                $rows = q(
                    "SELECT id,title,issued_at,document_identifier FROM pi_documents WHERE clinic_id=? AND document_status='emitido' AND patient_link_id IS NULL AND issued_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY issued_at DESC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $ts = app_storage_timestamp($r["issued_at"]);
                    $out[] = maestro_match_base(
                        [
                            "origem" => "documento",
                            "consultorio" => $clinic,
                            "documento" => $r["title"],
                            "identificador" => $r["document_identifier"] ?? "",
                            "data" => date_br($r["issued_at"] ?? ""),
                            "hora" => app_time_br($r["issued_at"] ?? ""),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "documento_sem_paciente",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "document_issued_after_days") {
                $day = maestro_local_day($cid, -$amount);
                [$start, $end] = maestro_local_day_utc_range($cid, $day);
                $rows = q(
                    "SELECT id,title,patient_link_id,issued_at,document_identifier FROM pi_documents WHERE clinic_id=? AND document_status='emitido' AND issued_at>=? AND issued_at<? ORDER BY issued_at DESC LIMIT $limit",
                    [$cid, $start, $end],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "documento",
                            "consultorio" => $clinic,
                            "documento" => $r["title"],
                            "identificador" => $r["document_identifier"] ?? "",
                            "paciente" => $r["patient_link_id"]
                                ? patient_display_name(
                                    (int) $r["patient_link_id"],
                                    $cid,
                                )
                                : "",
                            "data" => date_br((string) $r["issued_at"]),
                            "hora" => app_time_br($r["issued_at"]),
                            "dias" => $prazo,
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "documento",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" =>
                                (int) ($r["patient_link_id"] ?? 0) ?: null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "task_due_in_days") {
                $day = maestro_local_day($cid, $amount);
                [$start, $end] = maestro_local_day_utc_range($cid, $day);
                $rows = q(
                    "SELECT id,title,due_at FROM pi_tasks WHERE clinic_id=? AND status IN ($activeTask) AND due_at IS NOT NULL AND due_at>=? AND due_at<? ORDER BY due_at ASC LIMIT $limit",
                    [$cid, $start, $end],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "tarefa",
                            "consultorio" => $clinic,
                            "tarefa" => $r["title"],
                            "data" => date_br((string) $r["due_at"]),
                            "hora" => $r["due_at"] ? app_time_br($r["due_at"]) : "",
                            "dias" => $prazo,
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "tarefa",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "task_overdue_after_days") {
                $rows = q(
                    "SELECT id,title,due_at FROM pi_tasks WHERE clinic_id=? AND status IN ($activeTask) AND due_at IS NOT NULL AND due_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY due_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "tarefa",
                            "consultorio" => $clinic,
                            "tarefa" => $r["title"],
                            "data" => date_br((string) $r["due_at"]),
                            "hora" => app_time_br($r["due_at"]),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "tarefa_vencida",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "task_stalled_after_days") {
                $rows = q(
                    "SELECT id,title,COALESCE(updated_at,started_at,created_at) AS ref_at FROM pi_tasks WHERE clinic_id=? AND status='em_andamento' AND COALESCE(updated_at,started_at,created_at)<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY ref_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "tarefa",
                            "consultorio" => $clinic,
                            "tarefa" => $r["title"],
                            "data" => date_br((string) $r["ref_at"]),
                            "hora" => app_time_br($r["ref_at"]),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "tarefa_parada",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "task_returned_queue_recent") {
                $rows = q(
                    "SELECT t.id,t.title,e.created_at FROM pi_task_events e INNER JOIN pi_tasks t ON t.id=e.task_id AND t.clinic_id=e.clinic_id WHERE e.clinic_id=? AND e.event_key='devolvida_fila' AND e.created_at>=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY e.created_at DESC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "tarefa",
                            "consultorio" => $clinic,
                            "tarefa" => $r["title"],
                            "data" => date_br((string) $r["created_at"]),
                            "hora" => app_time_br($r["created_at"]),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "tarefa_devolvida",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "revenue_due_in_days") {
                $day = maestro_local_day($cid, $amount);
                [$start, $end] = maestro_local_day_utc_range($cid, $day);
                $rows = q(
                    "SELECT id,title,patient_link_id,expected_at,amount_cents FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND expected_at>=? AND expected_at<? ORDER BY expected_at ASC LIMIT $limit",
                    [$cid, $start, $end],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "receita",
                            "consultorio" => $clinic,
                            "receita" => $r["title"],
                            "valor" => money_br((int) $r["amount_cents"]),
                            "paciente" => $r["patient_link_id"]
                                ? patient_display_name(
                                    (int) $r["patient_link_id"],
                                    $cid,
                                )
                                : "",
                            "data" => date_br((string) $r["expected_at"]),
                            "hora" => app_time_br($r["expected_at"]),
                            "dias" => $prazo,
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "receita",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" =>
                                (int) ($r["patient_link_id"] ?? 0) ?: null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "revenue_overdue_after_days") {
                $rows = q(
                    "SELECT id,title,patient_link_id,expected_at,amount_cents FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND expected_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY expected_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "receita",
                            "consultorio" => $clinic,
                            "receita" => $r["title"],
                            "valor" => money_br((int) $r["amount_cents"]),
                            "paciente" => $r["patient_link_id"]
                                ? patient_display_name(
                                    (int) $r["patient_link_id"],
                                    $cid,
                                )
                                : "",
                            "data" => date_br((string) $r["expected_at"]),
                            "hora" => app_time_br($r["expected_at"]),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "receita_vencida",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" =>
                                (int) ($r["patient_link_id"] ?? 0) ?: null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "expense_due_in_days") {
                $day = maestro_local_day($cid, $amount);
                $rows = q(
                    "SELECT id,title,due_at,amount_cents FROM pi_financial_expenses WHERE clinic_id=? AND status='prevista' AND due_at=? ORDER BY due_at ASC LIMIT $limit",
                    [$cid, $day],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "despesa",
                            "consultorio" => $clinic,
                            "despesa" => $r["title"],
                            "valor" => money_br((int) $r["amount_cents"]),
                            "data" => date_br((string) $r["due_at"]),
                            "hora" => "",
                            "dias" => $prazo,
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "despesa",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "expense_overdue_after_days") {
                $cutoff = maestro_local_day($cid, -$amount);
                $rows = q(
                    "SELECT id,title,due_at,amount_cents FROM pi_financial_expenses WHERE clinic_id=? AND status='prevista' AND due_at IS NOT NULL AND due_at<=? ORDER BY due_at ASC LIMIT $limit",
                    [$cid, $cutoff],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "despesa",
                            "consultorio" => $clinic,
                            "despesa" => $r["title"],
                            "valor" => money_br((int) $r["amount_cents"]),
                            "data" => date_br((string) $r["due_at"]),
                            "hora" => "",
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "despesa_vencida",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "notice_unread_after_days") {
                $rows = q(
                    "SELECT n.id,n.title,n.created_at FROM pi_notices n WHERE n.clinic_id=? AND n.requires_ack=1 AND n.created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND (r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1) ORDER BY n.created_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "aviso",
                            "consultorio" => $clinic,
                            "notificacao" => $r["title"],
                            "data" => date_br((string) $r["created_at"]),
                            "hora" => app_time_br($r["created_at"]),
                            "dias" => $prazo,
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "notificacao",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "scope_violation_recent") {
                $rows = q(
                    "SELECT violation_key,sql_fingerprint,route,MAX(details) AS details,MAX(created_at) AS created_at,COUNT(*) AS occurrences FROM pi_scope_violations WHERE clinic_id=? AND violation_key<>'write_in_read_only' AND created_at>=DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY violation_key,sql_fingerprint,route ORDER BY created_at DESC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "segurança",
                            "consultorio" => $clinic,
                            "evento" => $r["violation_key"],
                            "rota" => $r["route"] ?? "",
                            "ocorrencias" => (int) ($r["occurrences"] ?? 1),
                            "detalhes" => function_exists(
                                "scope_violation_detail_summary",
                            )
                                ? scope_violation_detail_summary(
                                    $r["details"] ?? "",
                                )
                                : (string) ($r["details"] ?? ""),
                            "data" => date_br((string) $r["created_at"]),
                            "hora" => app_time_br($r["created_at"]),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "seguranca_integridade",
                            "source_entity_id" =>
                                mb_substr((string) $r["violation_key"], 0, 40) .
                                ":" .
                                mb_substr(
                                    (string) $r["sql_fingerprint"],
                                    0,
                                    32,
                                ),
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            } elseif ($trigger === "technical_error_open_after_days") {
                $rows = q(
                    "SELECT id,message,route,created_at FROM pi_error_events WHERE clinic_id=? AND resolved_at IS NULL AND created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY created_at ASC LIMIT $limit",
                    [$cid, $amount],
                )->fetchAll();
                foreach ($rows as $r) {
                    $out[] = maestro_match_base(
                        [
                            "origem" => "segurança",
                            "consultorio" => $clinic,
                            "evento" => "erro técnico",
                            "detalhes" => mb_substr((string) $r["message"], 0, 120),
                            "rota" => $r["route"] ?? "",
                            "data" => date_br((string) $r["created_at"]),
                            "hora" => app_time_br($r["created_at"]),
                            "prazo" => $prazo,
                        ],
                        [
                            "source_entity" => "erro_tecnico",
                            "source_entity_id" => (string) $r["id"],
                            "patient_link_id" => null,
                            "appointment_id" => null,
                        ],
                    );
                }
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo Maestro candidates] " .
                    $trigger .
                    " | " .
                    $e->getMessage(),
            );
        }
        return $out;
    
    }
}
