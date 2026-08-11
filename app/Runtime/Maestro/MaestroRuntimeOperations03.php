<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Maestro;

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
        $catalog = \Prontoo\Domain\Maestro\MaestroDomainOperations01::maestro_trigger_catalog();
        $item = $catalog[$trigger] ?? [];
        $cond = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_decode_json($rule["condition_json"] ?? "");
        $unit = (string) ($cond["unit"] ?? ($item["unit"] ?? "days"));
        $amount = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_amount(
            $cond["amount"] ?? ($cond["days"] ?? ($item["amount_default"] ?? 1)),
            (int) ($item["amount_default"] ?? 1),
            $unit,
        );
        $days = $amount;
        $limit = max(1, min(300, $limit));
        $out = [];
        $clinic = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_clinic_name_lookup($cid);
        $prazo = (string) $amount;
        $activeTask = "'aberta','em_andamento','aguardando'";
        try {
            if ($trigger === "appointment_before_start") {
                $day = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($cid, $amount);
                [$start, $end] = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day_utc_range($cid, $day);
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.01', [$cid, $start, $end], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["start_at"]);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["start_at"] ?? ""),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["start_at"] ?? ""),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.02', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["start_at"]);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["start_at"] ?? ""),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["start_at"] ?? ""),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.03', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["start_at"]);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["start_at"] ?? ""),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["start_at"] ?? ""),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.04', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["start_at"]);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["start_at"] ?? ""),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["start_at"] ?? ""),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.05', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["arrived_at"]);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["arrived_at"] ?? ""),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["arrived_at"] ?? ""),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.06', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["consultation_finished_at"]);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => $r["patient_name"] ?: "paciente",
                            "profissional" => $r["doctor_name"] ?: "profissional",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["consultation_finished_at"] ?? ""),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br(
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
                [$start, $end] = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day_utc_range(
                    $cid,
                    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($cid),
                );
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.07', [$cid, $start, $end], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["start_at"]);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "consulta",
                            "consultorio" => $clinic,
                            "paciente" => "paciente não vinculado",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["start_at"] ?? ""),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["start_at"] ?? ""),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.08', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "paciente" => $r["name"],
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["created_at"]),
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
                $day = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($cid, $amount);
                [$start, $end] = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day_utc_range($cid, $day);
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.09', [$cid, $start, $end], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["next_action_at"]);
                    $occ = str_replace("-", "", $day);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "paciente" => $r["name"],
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["next_action_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["next_action_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.10', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "paciente" => $r["name"],
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["next_action_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.11', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "etapa" => \Prontoo\Domain\Documents\DocumentTypePolicy::document_status_label((string) $r["stage"]),
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["moved_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.12', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "interessado",
                            "consultorio" => $clinic,
                            "interessado" => $r["name"],
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["moved_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.13', [$cid], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.14', [$cid, $amount, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
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
                $target = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($cid, $amount);
                $md = substr($target, 5, 5);
                $year = substr($target, 0, 4);
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.15', [$cid, $md], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $birthTs = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["birth_date"]);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.16', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.17', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp(
                        $r["updated_at"] ?: $r["issued_at"],
                    );
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "documento",
                            "consultorio" => $clinic,
                            "documento" => $r["title"],
                            "paciente" => $r["patient_link_id"]
                                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::patient_display_name(
                                    (int) $r["patient_link_id"],
                                    $cid,
                                )
                                : "",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br(
                                ($r["updated_at"] ?: $r["issued_at"]) ?? "",
                            ),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br(
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.18', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["issued_at"]);
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "documento",
                            "consultorio" => $clinic,
                            "documento" => $r["title"],
                            "identificador" => $r["document_identifier"] ?? "",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($r["issued_at"] ?? ""),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["issued_at"] ?? ""),
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
                $day = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($cid, -$amount);
                [$start, $end] = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day_utc_range($cid, $day);
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.19', [$cid, $start, $end], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "documento",
                            "consultorio" => $clinic,
                            "documento" => $r["title"],
                            "identificador" => $r["document_identifier"] ?? "",
                            "paciente" => $r["patient_link_id"]
                                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::patient_display_name(
                                    (int) $r["patient_link_id"],
                                    $cid,
                                )
                                : "",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["issued_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["issued_at"]),
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
                $day = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($cid, $amount);
                [$start, $end] = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day_utc_range($cid, $day);
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.20', [$cid, $start, $end], ['activeTask' => $activeTask, 'limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "tarefa",
                            "consultorio" => $clinic,
                            "tarefa" => $r["title"],
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["due_at"]),
                            "hora" => $r["due_at"] ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["due_at"]) : "",
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.21', [$cid, $amount], ['activeTask' => $activeTask, 'limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "tarefa",
                            "consultorio" => $clinic,
                            "tarefa" => $r["title"],
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["due_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["due_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.22', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "tarefa",
                            "consultorio" => $clinic,
                            "tarefa" => $r["title"],
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["ref_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["ref_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.23', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "tarefa",
                            "consultorio" => $clinic,
                            "tarefa" => $r["title"],
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["created_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["created_at"]),
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
                $day = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($cid, $amount);
                [$start, $end] = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day_utc_range($cid, $day);
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.24', [$cid, $start, $end], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "receita",
                            "consultorio" => $clinic,
                            "receita" => $r["title"],
                            "valor" => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["amount_cents"]),
                            "paciente" => $r["patient_link_id"]
                                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::patient_display_name(
                                    (int) $r["patient_link_id"],
                                    $cid,
                                )
                                : "",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["expected_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["expected_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.25', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "receita",
                            "consultorio" => $clinic,
                            "receita" => $r["title"],
                            "valor" => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["amount_cents"]),
                            "paciente" => $r["patient_link_id"]
                                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::patient_display_name(
                                    (int) $r["patient_link_id"],
                                    $cid,
                                )
                                : "",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["expected_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["expected_at"]),
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
                $day = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($cid, $amount);
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.26', [$cid, $day], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "despesa",
                            "consultorio" => $clinic,
                            "despesa" => $r["title"],
                            "valor" => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["amount_cents"]),
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["due_at"]),
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
                $cutoff = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($cid, -$amount);
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.27', [$cid, $cutoff], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "despesa",
                            "consultorio" => $clinic,
                            "despesa" => $r["title"],
                            "valor" => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["amount_cents"]),
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["due_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.28', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "aviso",
                            "consultorio" => $clinic,
                            "notificacao" => $r["title"],
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["created_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["created_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.29', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "segurança",
                            "consultorio" => $clinic,
                            "evento" => $r["violation_key"],
                            "rota" => $r["route"] ?? "",
                            "ocorrencias" => (int) ($r["occurrences"] ?? 1),
                            "detalhes" => function_exists(
                                "scope_violation_detail_summary",
                            )
                                ? \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_violation_detail_summary(
                                    $r["details"] ?? "",
                                )
                                : (string) ($r["details"] ?? ""),
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["created_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["created_at"]),
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
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.03.maestro_fetch_candidates.30', [$cid, $amount], ['limit' => $limit])->fetchAll();
                foreach ($rows as $r) {
                    $out[] = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base(
                        [
                            "origem" => "segurança",
                            "consultorio" => $clinic,
                            "evento" => "erro técnico",
                            "detalhes" => mb_substr((string) $r["message"], 0, 120),
                            "rota" => $r["route"] ?? "",
                            "data" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["created_at"]),
                            "hora" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($r["created_at"]),
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
