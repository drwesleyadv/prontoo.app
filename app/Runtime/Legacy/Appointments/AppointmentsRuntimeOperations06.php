<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Appointments;

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

final class AppointmentsRuntimeOperations06
{
    private function __construct()
    {
    }

    public static function appointment_procedure_id_from_post(
        int $cid,
        string $field = "reason",
    ): ?int 
    {
    
        $v = mb_trim((string) ($_POST[$field] ?? ""));
        if (str_starts_with($v, "procedure:")) {
            $id = (int) substr($v, 10);
            $p = one(
                "SELECT id FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1",
                [$id, $cid],
            );
            if ($p) {
                return $id;
            }
        }
        return null;
    
    }

    public static function appointment_procedure_price_cents(
        int $cid,
        ?int $procedureId,
        int $fallback = 0,
    ): int 
    {
    
        $fallback = max(0, $fallback);
        if ($fallback > 0) {
            return $fallback;
        }
        if (!$procedureId) {
            return 0;
        }
        $price =
            (int) (val(
                "SELECT price_cents FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1",
                [$procedureId, $cid],
            ) ?:
            0);
        return max(0, $price);
    
    }

    public static function appointment_payment_post_context(
        int $cid,
        ?int $procedureId,
        int $fallbackAmount = 0,
    ): array 
    {
    
        $amount = appointment_procedure_price_cents(
            $cid,
            $procedureId,
            $fallbackAmount,
        );
        $paid = isset($_POST["payment_confirmed"]);
        if (!$paid) {
            return [false, "", $amount, 0, null];
        }
        if (!$procedureId) {
            return [
                true,
                "",
                0,
                0,
                "Selecione um Procedimento para registrar pagamento.",
            ];
        }
        $method = normalize_payment_method(
            (string) ($_POST["payment_method"] ?? ""),
        );
        if ($method === "") {
            return [true, "", $amount, 0, "Selecione a forma de pagamento."];
        }
        $destination = 0;
        if ($method !== "dinheiro") {
            $destination = (int) ($_POST["payment_destination_location_id"] ?? 0);
            if ($destination <= 0) {
                return [
                    true,
                    $method,
                    $amount,
                    0,
                    "Selecione o destino do recebimento.",
                ];
            }
            if (
                function_exists("financial_office_destination_belongs")
                    ? !financial_office_destination_belongs($cid, $destination)
                    : !financial_location_belongs($cid, $destination)
            ) {
                return [
                    true,
                    $method,
                    $amount,
                    0,
                    "Selecione um destino financeiro válido para este consultório.",
                ];
            }
        }
        return [true, $method, $amount, $destination, null];
    
    }

    public static function appointment_min_duration_message(
        int $cid,
        ?int $procedureId,
        string $startAt,
        string $endAt,
    ): ?string 
    {
    
        if (!$procedureId) {
            return null;
        }
        $p = one(
            "SELECT title,duration_minutes FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1",
            [$procedureId, $cid],
        );
        if (!$p) {
            return null;
        }
        $duration = max(0, (int) ($p["duration_minutes"] ?? 0));
        if ($duration <= 0) {
            return null;
        }
        $start = strtotime($startAt);
        $end = strtotime($endAt);
        if (!$start || !$end) {
            return null;
        }
        $minEnd = $start + $duration * 60;
        if ($end < $minEnd) {
            $title = mb_trim((string) ($p["title"] ?? "procedimento selecionado"));
            if ($title === "") {
                $title = "procedimento selecionado";
            }
            return "O horário final não pode ser inferior à duração cadastrada para " .
                $title .
                " (mínimo de " .
                $duration .
                " min). Ajuste o fim para " .
                date("H:i", $minEnd) .
                " ou um horário posterior.";
        }
        return null;
    
    }
}
