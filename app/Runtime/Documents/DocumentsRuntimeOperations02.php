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
        $actor = $actor !== "" ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($actor) : "Colaborador";
        $patient = mb_trim((string) ($patientName ?? ""));
        $patient = $patient !== "" ? $patient : "paciente";
        $when = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($issuedAt);
        $verb =
            \Prontoo\Domain\Documents\DocumentTypePolicy::document_editable_status($status) && $status !== "emitido"
                ? "preparou"
                : "emitiu";
        return $actor . " " . $verb . " para " . $patient . " em " . $when . ".";
    
    }

    public static function document_time_br(?string $dt): string
    
    {
    
        $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($dt);
        return $ts ? date("H\hi", $ts) : "";
    
    }

    public static function document_appointment_row(int $cid, int $appointmentId): ?array
    
    {
    
        if ($cid <= 0 || $appointmentId <= 0) {
            return null;
        }
        return \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.02.document_appointment_row.01', [$appointmentId, $cid], []) ?:
            null;
    
    }

    public static function document_resolve_patient_appointment(
        int $cid,
        int $patientId = 0,
        int $appointmentId = 0,
    ): array 
    {
    
        if ($appointmentId > 0) {
            $appt = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_appointment_row($cid, $appointmentId);
            if (!$appt) {
                throw new RuntimeException(
                    "Agendamento não encontrado para este consultório.",
                );
            }
            $apptPatient = (int) ($appt["patient_link_id"] ?? 0);
            if ($apptPatient > 0) {
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::require_patient_in_clinic($cid, $apptPatient);
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
            \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::require_patient_in_clinic($cid, $patientId);
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
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.02.document_appointment_options.01', [$cid], ['limit' => $limit])->fetchAll();
        $out = [];
        foreach ($rows as $a) {
            $id = (int) $a["id"];
            $when = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($a["start_at"] ?? "");
            $patient = trim(
                (string) ($a["patient_name"] ?? "Paciente não vinculado"),
            );
            $proc = mb_trim((string) ($a["procedure_title"] ?? "Consulta"));
            $doctor = mb_trim((string) ($a["doctor_name"] ?? "Profissional"));
            $out[$id] = $when . " · " . $patient . " · " . $proc . " · " . $doctor;
        }
        if ($selectedId > 0 && !isset($out[$selectedId])) {
            $a = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_appointment_row($cid, $selectedId);
            if ($a) {
                $out[$selectedId] =
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($a["start_at"] ?? "") .
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
    
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
            "Agendamento vinculado",
            "appointment_id",
            ["0" => "Sem agendamento vinculado"] +
                \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_appointment_options($cid, $selectedId),
            (string) max(0, $selectedId),
        );
    
    }

    public static function document_context_ids_from_post(array $c): array
    
    {
    
        $cid = (int) ($c["clinic_id"] ?? 0);
        $ctx = [];
        $checks = [
            "lead_id" => ["entity" => "lead", "label" => "Interessado"],
            "procedure_id" => [
                "entity" => "procedure",
                "label" => "Procedimento",
            ],
            "task_id" => ["entity" => "task", "label" => "Tarefa"],
            "care_id" => ["entity" => "care", "label" => "Atividade"],
        ];
        foreach ($checks as $key => $cfg) {
            $id = max(0, (int) ($_POST[$key] ?? 0));
            if ($id <= 0) {
                continue;
            }
            $exists = (int) \Prontoo\Runtime\Operational\OperationalComposition::documents()->safeScalar('operational.documents.02.document_context_ids_from_post.01', [$id, $cid], 0, ['entity' => $cfg["entity"]]);
            if ($exists <= 0) {
                throw new RuntimeException(
                    $cfg["label"] . " não encontrado(a) neste consultório.",
                );
            }
            $ctx[$key] = $id;
        }
        $collab = max(0, (int) ($_POST["collaborator_user_id"] ?? 0));
        if ($collab > 0) {
            if (!\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($cid, $collab)) {
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
                    $rows = \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.02.document_context_select_options.01', [$cid], ['limit' => $limit])->fetchAll();
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
                    $rows = array_slice(\Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_options($cid, true), 0, $limit);
                    foreach ($rows as $r) {
                        $meta = array_filter([
                            (string) ($r["category"] ?? ""),
                            \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::format_minutes((int) ($r["duration_minutes"] ?? 0)),
                            (int) ($r["price_cents"] ?? 0) > 0
                                ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["price_cents"])
                                : "",
                        ]);
                        $out[(int) $r["id"]] =
                            mb_trim((string) $r["title"]) .
                            ($meta ? " · " . implode(" · ", $meta) : "");
                    }
                    break;
                case "task":
                    $rows = \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.02.document_context_select_options.02', [$cid], ['limit' => $limit])->fetchAll();
                    foreach ($rows as $r) {
                        $meta = array_filter([
                            \Prontoo\Domain\Documents\DocumentTypePolicy::document_status_label((string) ($r["status"] ?? "")),
                            !empty($r["due_at"])
                                ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) $r["due_at"])
                                : "",
                            (string) ($r["assigned_name"] ?? ""),
                        ]);
                        $out[(int) $r["id"]] =
                            mb_trim((string) $r["title"]) .
                            ($meta ? " · " . implode(" · ", $meta) : "");
                    }
                    break;
                case "care":
                    $rows = \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.02.document_context_select_options.03', [$cid], ['limit' => $limit])->fetchAll();
                    foreach ($rows as $r) {
                        $title = mb_trim((string) ($r["title"] ?? ""));
                        if ($title === "") {
                            $title = "Atividade";
                        }
                        $meta = array_filter([
                            (string) ($r["patient_name"] ?? ""),
                            \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_record_type_label(
                                (string) ($r["record_type"] ?? ""),
                            ),
                            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) ($r["created_at"] ?? "")),
                        ]);
                        $out[(int) $r["id"]] =
                            $title . ($meta ? " · " . implode(" · ", $meta) : "");
                    }
                    break;
                case "collaborator":
                    $collaboratorLoader =  fn(): array => \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.02.document_context_select_options.04', [$cid], ['limit' => $limit])->fetchAll();
                    $rows = is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])
                        ? \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                            "catalog",
                            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("collaborators", [$cid, $limit]),
                            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("catalog"),
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
                    $label = (string) \Prontoo\Runtime\Operational\OperationalComposition::documents()->safeScalar('operational.documents.02.document_context_select_options.05', [$selectedId, $cid], "", []);
                } elseif ($kind === "procedure") {
                    $label = (string) \Prontoo\Runtime\Operational\OperationalComposition::documents()->safeScalar('operational.documents.02.document_context_select_options.06', [$selectedId, $cid], "", []);
                } elseif ($kind === "task") {
                    $label = (string) \Prontoo\Runtime\Operational\OperationalComposition::documents()->safeScalar('operational.documents.02.document_context_select_options.07', [$selectedId, $cid], "", []);
                } elseif ($kind === "care") {
                    $label = (string) \Prontoo\Runtime\Operational\OperationalComposition::documents()->safeScalar('operational.documents.02.document_context_select_options.08', [$selectedId, $cid], "", []);
                } elseif ($kind === "collaborator") {
                    $label = (string) \Prontoo\Runtime\Operational\OperationalComposition::documents()->safeScalar('operational.documents.02.document_context_select_options.09', [$selectedId, $cid], "", []);
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
        $ctx = \Prontoo\Domain\Documents\DocumentHtmlPolicy::document_context_json_decode($doc["context_json"] ?? "{}");
        $lead = (int) ($ctx["lead_id"] ?? 0);
        $proc = (int) ($ctx["procedure_id"] ?? 0);
        $task = (int) ($ctx["task_id"] ?? 0);
        $care = (int) ($ctx["care_id"] ?? 0);
        $collab = (int) ($ctx["collaborator_user_id"] ?? 0);
        $html =
            '<div class="doc-context-grid"><section><h4>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("personal_injury") .
            '<span>Paciente e agenda</span></h4><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "",
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_lookup_field(
                    $cid,
                    "patient_link_id",
                    (string) ($doc["patient_link_id"] ?? 0),
                    "patient_search_doc_" . (int) $doc["id"],
                ),
            ) .
            \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_appointment_select_field(
                $cid,
                (int) ($doc["appointment_id"] ?? 0),
            ) .
            "</div></section>";
        $html .=
            "<section><h4>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("dataset_linked") .
            '<span>Registros contextuais</span></h4><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Interessado",
                "lead_id",
                ["0" => "Sem interessado vinculado"] +
                    \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_context_select_options($cid, "lead", $lead),
                (string) $lead,
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Procedimento",
                "procedure_id",
                ["0" => "Sem procedimento vinculado"] +
                    \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_context_select_options($cid, "procedure", $proc),
                (string) $proc,
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Tarefa",
                "task_id",
                ["0" => "Sem tarefa vinculada"] +
                    \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_context_select_options($cid, "task", $task),
                (string) $task,
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Atividade da ficha",
                "care_id",
                ["0" => "Sem atividade vinculada"] +
                    \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_context_select_options($cid, "care", $care),
                (string) $care,
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Colaborador",
                "collaborator_user_id",
                ["0" => "Usar colaborador atual"] +
                    \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_context_select_options($cid, "collaborator", $collab),
                (string) $collab,
            ) .
            "</div></section></div>";
        return $html;
    
    }
}
