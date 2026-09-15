<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Appointments;

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
            $p = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row('operational.appointments.06.appointment_procedure_id_from_post.01', [$id, $cid], []);
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
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::appointments()->scalar('operational.appointments.06.appointment_procedure_price_cents.01', [$procedureId, $cid], []) ?:
            0);
        return max(0, $price);
    
    }

    public static function appointment_payment_post_context(
        int $cid,
        ?int $procedureId,
        int $fallbackAmount = 0,
    ): array 
    {
    
        $amount = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_procedure_price_cents(
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
        $method = \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method(
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
                is_callable([\Prontoo\Runtime\Financial\FinancialRuntimeOperations10::class, 'financial_office_destination_belongs'])
                    ? !\Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_office_destination_belongs($cid, $destination)
                    : !\Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_location_belongs($cid, $destination)
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

    public static function appointment_recurrence_rows_from_post(
        int $cid,
        array $procedures,
    ): array {
        $procedureValues = $_POST['recurrence_procedure'] ?? [];
        $startValues = $_POST['recurrence_start_at'] ?? [];
        if (!is_array($procedureValues) || !is_array($startValues)) {
            throw new RuntimeException('Dados de recorrência inválidos.');
        }
        $procedureMap = [];
        foreach ($procedures as $procedure) {
            $procedureId = (int) ($procedure['id'] ?? 0);
            if ($procedureId > 0) {
                $procedureMap[$procedureId] = $procedure;
            }
        }
        $rows = [];
        $count = max(count($procedureValues), count($startValues));
        for ($index = 0; $index < $count; $index++) {
            $selection = mb_trim((string) ($procedureValues[$index] ?? ''));
            $startLocal = mb_trim((string) ($startValues[$index] ?? ''));
            if ($selection === '' && $startLocal === '') {
                continue;
            }
            $line = $index + 1;
            if ($selection === '' || $startLocal === '') {
                throw new RuntimeException('Complete Procedimento e Data/Hora na recorrência ' . $line . '.');
            }
            if (!str_starts_with($selection, 'procedure:')) {
                throw new RuntimeException('Selecione um procedimento cadastrado na recorrência ' . $line . '.');
            }
            $procedureId = (int) substr($selection, 10);
            $procedure = $procedureMap[$procedureId] ?? null;
            if (!$procedure) {
                throw new RuntimeException('O procedimento da recorrência ' . $line . ' não está disponível.');
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/', $startLocal) !== 1) {
                throw new RuntimeException('Informe Data/Hora válida na recorrência ' . $line . '.');
            }
            $duration = max(0, (int) ($procedure['duration_minutes'] ?? 0));
            if ($duration <= 0) {
                throw new RuntimeException(
                    'O procedimento da recorrência ' . $line . ' precisa ter duração cadastrada antes do agendamento.',
                );
            }
            $endLocal = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_local_input_add_minutes(
                $startLocal,
                $duration,
            );
            $rows[] = [
                'procedure_id' => $procedureId,
                'start_at' => \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($startLocal),
                'end_at' => \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($endLocal),
                'duration_minutes' => $duration,
            ];
        }
        return $rows;
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
        $p = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row('operational.appointments.06.appointment_min_duration_message.01', [$procedureId, $cid], []);
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
