<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Patients;

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
    
        $c = need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            throw new ProntooHttpError(
                403,
                "Busca disponível apenas no consultório.",
            );
        }
        if (!can("patients") && !can("documents")) {
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
        $digits = only_digits($q);
        $params = [$cid];
        $where = "pp.clinic_id=? AND pp.active=1 AND pp.deleted_at IS NULL";
        $like = "%" . $q . "%";
        $dateLike = "%" . str_replace("/", "-", $q) . "%";
        if ($digits !== "") {
            $where .=
                " AND (p.full_name LIKE ? OR p.cpf LIKE ? OR pp.phone LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)";
            $params[] = $like;
            $params[] = "%" . $digits . "%";
            $params[] = "%" . $digits . "%";
            $params[] = $like;
            $params[] = $dateLike;
        } else {
            $where .=
                " AND (p.full_name LIKE ? OR pp.email LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $dateLike;
        }
        $metrics = patient_directory_select_metrics_sql($cid);
        $order = "p.full_name ASC, pp.id DESC";
        $rows = q(
            "SELECT pp.id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,pp.created_at,pp.updated_at,pp.registration_needs_update,p.full_name,p.birth_date,p.cpf,(SELECT COUNT(*) FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1) guardian_count $metrics FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE $where ORDER BY $order LIMIT " .
                (int) $limit,
            $params,
        )->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $birth = !empty($r["birth_date"])
                ? date_br((string) $r["birth_date"])
                : "Nascimento não informado";
            $age = patient_age_years((string) ($r["birth_date"] ?? ""));
            [$level, $label, $message] = patient_directory_status($r);
            $timeRaw = mb_trim((string) ($r["today_appointment_start_at"] ?? ""));
            $timeLabel =
                $timeRaw !== "" && $timeRaw !== "0"
                    ? app_time_br($timeRaw, $cid)
                    : "";
            $lastRaw = mb_trim((string) ($r["last_consultation_at"] ?? ""));
            $lastLabel =
                $lastRaw !== "" && $lastRaw !== "0"
                    ? (function_exists("app_date_br")
                        ? app_date_br($lastRaw, $cid)
                        : date_br($lastRaw))
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
                "cpf" => mask((string) ($r["cpf"] ?? "")),
                "phone" => phone_br((string) ($r["phone"] ?? "")) ?: "Sem telefone",
                "status_level" => $level,
                "status_label" => $label,
                "status_message" => $message,
                "today_appointment_time" => $timeLabel,
                "last_consultation_date" => $lastLabel,
                "value" => (string) $r["full_name"] . " · " . $birth,
                "open_url" => href("patient", ["id" => (int) $r["id"]]),
            ];
        }
        echo json_encode(["ok" => true, "items" => $items], JSON_UNESCAPED_UNICODE);
        return;
    
    }
}
