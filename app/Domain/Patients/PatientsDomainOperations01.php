<?php
declare(strict_types=1);

namespace Prontoo\Domain\Patients;

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

final class PatientsDomainOperations01
{
    private function __construct()
    {
    }

    public static function normalize_patient_tab_icon(?string $icon): string
    
    {
    
        $icon = preg_replace("/[^a-z0-9_]+/i", "", (string) $icon) ?: "";
        return array_key_exists($icon, \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::patient_health_icon_options())
            ? $icon
            : "clinical_notes";
    
    }

    public static function patient_tab_label_clean(string $label): string
    
    {
        return \Prontoo\Domain\Patients\PatientPure::cleanTabLabel($label);
    
    }

    public static function patient_tab_record_type(int $tabId): string
    
    {
        return \Prontoo\Domain\Patients\PatientPure::tabRecordType($tabId);
    
    }

    public static function patient_tab_key(int $tabId): string
    
    {
        return \Prontoo\Domain\Patients\PatientPure::tabKey($tabId);
    
    }

    public static function patient_tab_map_by_type(array $tabs): array
    
    {
    
        $out = [];
        foreach ($tabs as $t) {
            $out[(string) $t["record_type"]] = $t;
        }
        return $out;
    
    }

    public static function patient_tab_record_options(array $tabs): array
    
    {
    
        $out = [];
        foreach ($tabs as $t) {
            $out[(string) $t["record_type"]] = $t["label"];
        }
        return $out;
    
    }

    public static function patient_record_type_label(
        string $type,
        ?string $customTabLabel = null,
    ): string {
        $type = trim($type);
        $base = [
            "note" => "Nota",
            "nota" => "Nota",
            "evolucao" => "Evolução",
            "evolution" => "Evolução",
            "triagem" => "Triagem",
            "anamnese" => "Anamnese",
            "exame" => "Exame",
            "conduta" => "Conduta",
            "retorno" => "Retorno",
            "orientacao" => "Orientação",
            "documento" => "Documento",
            "outro" => "Outro",
        ];
        if (isset($base[$type])) {
            return $base[$type];
        }
        if (str_starts_with($type, "tab_") && mb_trim((string) $customTabLabel) !== "") {
            return \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_tab_label_clean((string) $customTabLabel);
        }
        $fallback = trim(str_replace("_", " ", $type));
        return $fallback !== ""
            ? mb_convert_case($fallback, MB_CASE_TITLE, "UTF-8")
            : "Atividade";
    
    }

    public static function patient_guardian_relationship_options(): array
    
    {
        return \Prontoo\Domain\Patients\PatientPure::guardianRelationshipOptions();
    
    }

    public static function normalize_guardian_relationship(string $v): string
    
    {
        return \Prontoo\Domain\Patients\PatientPure::normalizeGuardianRelationship($v);
    
    }

    public static function patient_age_years(null|string|int $birth): ?int
    
    {
        return \Prontoo\Domain\Patients\PatientPure::ageYears($birth);
    
    }

    public static function patient_is_minor(array $p): bool
    
    {
        return \Prontoo\Domain\Patients\PatientPure::isMinor($p);
    
    }

    public static function patient_directory_filter_options(): array
    
    {
    
        return [
            "today" => "Hoje",
            "week" => "Essa semana",
            "dropouts" => "Desistentes",
            "incomplete" => "Cadastro Incompleto",
        ];
    
    }

    public static function patient_directory_filter_default(): string
    
    {
    
        return "today";
    
    }

    public static function patient_directory_filter_icons(): array
    
    {
    
        return [
            "today" => "today",
            "week" => "calendar_month",
            "dropouts" => "event_busy",
            "incomplete" => "fact_check",
        ];
    
    }

    public static function patient_directory_cancel_statuses(): array
    
    {
    
        return ["cancelado", "nao_compareceu"];
    
    }

    public static function patient_appointment_code(array $a): string
    
    {
    
        return is_callable([\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::class, 'appointment_status_code'])
            ? \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a)
            : mb_strtolower(mb_trim((string) ($a["status"] ?? "")));
    
    }

    public static function patient_appointment_status_title(array $a): string
    
    {
    
        $code = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_code($a);
        return match ($code) {
            "atendimento_concluido", "finalizado" => "Consulta realizada",
            "em_atendimento" => "Consulta em atendimento",
            "cancelado" => "Consulta cancelada",
            "nao_compareceu" => "Não compareceu",
            "reagendado" => "Consulta reagendada",
            "confirmado" => "Consulta confirmada",
            "chegou" => "Paciente chegou",
            "em_preparo" => "Em preparo",
            "pronto_atendimento" => "Pronta para atendimento",
            default => "Consulta agendada",
        };
    
    }

    public static function patient_appointment_icon(array $a): string
    
    {
    
        $code = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_code($a);
        return match ($code) {
            "atendimento_concluido", "finalizado" => "event_available",
            "em_atendimento" => "stethoscope",
            "cancelado" => "event_busy",
            "nao_compareceu" => "person_cancel",
            "reagendado" => "event_repeat",
            "confirmado" => "event_available",
            "chegou" => "how_to_reg",
            default => "event",
        };
    
    }

    public static function patient_appointment_status_class(array $a): string
    
    {
    
        $code = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_code($a);
        $safe = preg_replace('/[^a-z0-9_\t -]/i', "", $code) ?: "agendado";
        return "patient-appointment-didactic patient-appointment-status-" .
            str_replace("_", "-", $safe);
    
    }

    public static function patient_document_type_human_label(?string $typeKey): string
    
    {
    
        $key = mb_strtolower(mb_trim((string) $typeKey));
        if ($key === "") {
            return "Documento";
        }
        if (is_callable([\Prontoo\Domain\Documents\DocumentTypePolicy::class, 'document_type_options'])) {
            try {
                $opts = \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options();
                if (isset($opts[$key]) && mb_trim((string) $opts[$key]) !== "") {
                    return (string) $opts[$key];
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
        $map = [
            "receita" => "Receita",
            "prescricao" => "Prescrição",
            "atestado" => "Atestado",
            "declaracao" => "Declaração",
            "declaracao_comparecimento" => "Declaração de comparecimento",
            "recibo" => "Recibo",
            "encaminhamento" => "Encaminhamento",
            "solicitacao_exame" => "Solicitação de exame",
            "laudo" => "Laudo",
            "relatorio" => "Relatório",
            "orientacao" => "Orientação",
        ];
        return $map[$key] ??
            mb_convert_case(str_replace("_", " ", $key), MB_CASE_TITLE, "UTF-8");
    
    }

    public static function patient_appointment_docs_label(array $docs): string
    
    {
    
        if (!$docs) {
            return "Documentos gerados: não houve geração de documentos nesta consulta.";
        }
        $labels = [];
        foreach ($docs as $d) {
            $title = mb_trim((string) ($d["title"] ?? ""));
            if ($title === "") {
                $title = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_document_type_human_label($d["type_key"] ?? null);
            }
            $status = mb_trim((string) ($d["document_status"] ?? ""));
            $statusLabel =
                $status !== "" && is_callable([\Prontoo\Domain\Documents\DocumentTypePolicy::class, 'document_status_label'])
                    ? \Prontoo\Domain\Documents\DocumentTypePolicy::document_status_label($status)
                    : ($status !== ""
                        ? ucfirst(str_replace("_", " ", $status))
                        : "");
            $labels[] =
                mb_substr($title, 0, 80, "UTF-8") .
                ($statusLabel !== "" ? " (" . $statusLabel . ")" : "");
        }
        $total = count($labels);
        if ($total > 6) {
            $labels = array_merge(array_slice($labels, 0, 6), [
                "+" . ($total - 6) . " documento(s)",
            ]);
        }
        return "Documentos gerados: " . implode("; ", $labels) . ".";
    
    }
}
