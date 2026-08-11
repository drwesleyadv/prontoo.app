<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Patients;

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

final class PatientsRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function page_patient_suggest(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            throw new ProntooHttpError(
                403,
                "Busca disponível apenas no consultório.",
            );
        }
        if (!\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("patients") && !\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("documents")) {
            throw new ProntooHttpError(403, "Sem permissão para buscar pacientes.");
        }
        $cid = (int) $c["clinic_id"];
        $q = mb_trim((string) ($_GET["q"] ?? ""));
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        if ($q === "") {
            echo json_encode(["ok" => true, "items" => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        $limit = max(1, min(80, (int) ($_GET["limit"] ?? 12)));
        $digits = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($q);
        $params = [$cid];
        $searchMode = $digits !== "" ? "digits" : "text";
        $like = "%" . $q . "%";
        $dateLike = "%" . str_replace("/", "-", $q) . "%";
        if ($digits !== "") {
            $params[] = $like;
            $params[] = "%" . $digits . "%";
            $params[] = "%" . $digits . "%";
            $params[] = $like;
            $params[] = $dateLike;
        } else {
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $dateLike;
        }
        [$todayStart, $todayEnd] = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_today_utc_range($cid);
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.04.page_patient_suggest.01', $params, compact('searchMode', 'todayStart', 'todayEnd', 'limit'))->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $birth = !empty($r["birth_date"])
                ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["birth_date"])
                : "Nascimento não informado";
            $age = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_age_years((string) ($r["birth_date"] ?? ""));
            [$level, $label, $message] = \Prontoo\Runtime\Patients\PatientsRuntimeOperations03::patient_directory_status($r);
            $timeRaw = mb_trim((string) ($r["today_appointment_start_at"] ?? ""));
            $timeLabel =
                $timeRaw !== "" && $timeRaw !== "0"
                    ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($timeRaw, $cid)
                    : "";
            $lastRaw = mb_trim((string) ($r["last_consultation_at"] ?? ""));
            $lastLabel =
                $lastRaw !== "" && $lastRaw !== "0"
                    ? (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'app_date_br'])
                        ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_date_br($lastRaw, $cid)
                        : \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($lastRaw))
                    : "";
            if ($lastLabel === "—") {
                $lastLabel = "";
            }
            $items[] = [
                "id" => (int) $r["id"],
                "name" => (string) $r["full_name"],
                "birth" => $birth,
                "birth_raw" => (string) ($r["birth_date"] ?? ""),
                "age" => $age !== null ? $age . " anos" : "Idade não informada",
                "cpf" => \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) ($r["cpf"] ?? "")),
                "phone" => \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($r["phone"] ?? "")) ?: "Sem telefone",
                "status_level" => $level,
                "status_label" => $label,
                "status_message" => $message,
                "today_appointment_time" => $timeLabel,
                "last_consultation_date" => $lastLabel,
                "value" => (string) $r["full_name"] . " · " . $birth,
                "open_url" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => (int) $r["id"]]),
            ];
        }
        echo json_encode(["ok" => true, "items" => $items], JSON_UNESCAPED_UNICODE);
        return;
    
    }
}
