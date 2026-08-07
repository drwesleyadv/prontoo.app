<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Financial;

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

final class FinancialRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function financial_daily_metrics(int $cid, string $businessDate): array
    
    {
    
        [$dayStart, $dayEnd] = app_local_day_utc_range($businessDate, $cid);
        $activeAppointment =
            "(a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))";
        $expected = (int) (val(
            "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.amount_cents>0 AND r.expected_at>=? AND r.expected_at<? AND " .
                $activeAppointment,
            [$cid, $dayStart, $dayEnd],
        ) ?? 0);
        $received = (int) (val(
            "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='efetivada' AND r.amount_cents>0 AND r.expected_at>=? AND r.expected_at<? AND " .
                $activeAppointment,
            [$cid, $dayStart, $dayEnd],
        ) ?? 0);
        $pending = (int) (val(
            "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.expected_at>=? AND r.expected_at<? AND " .
                $activeAppointment,
            [$cid, $dayStart, $dayEnd],
        ) ?? 0);
        $movements = (int) (val(
            "SELECT COUNT(DISTINCT m.id) FROM pi_financial_movements m LEFT JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND m.status='confirmed' AND ((m.created_at>=? AND m.created_at<?) OR s.business_date=?)",
            [$cid, $dayStart, $dayEnd, $businessDate],
        ) ?? 0);
        $expected = financial_assert_balance_cents(
            $expected,
            "Receita prevista diária",
        );
        $received = financial_assert_balance_cents(
            $received,
            "Receita recebida diária",
        );
        $pending = financial_assert_balance_cents(
            $pending,
            "Receita pendente diária",
        );
        if (
            $expected !==
            financial_checked_add(
                $received,
                $pending,
                "Partição da receita diária",
            )
        ) {
            throw new RuntimeException(
                "A receita diária não fecha entre valores recebidos e pendentes.",
            );
        }
        return [
            "expected_cents" => $expected,
            "received_cents" => $received,
            "pending_cents" => $pending,
            "movement_count" => max(0, $movements),
            "day_start" => $dayStart,
            "day_end" => $dayEnd,
        ];
    
    }

    public static function financial_daily_reconciliation(
        int $cid,
        string $businessDate,
        int $uid = 0,
        bool $prepareLegacy = false,
    ): array 
    {
    
        $issues = [];
        $sessions = q(
            "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND status IN ('approved','kept_closed') ORDER BY id",
            [$cid, $businessDate],
        )->fetchAll();
        foreach ($sessions as $session) {
            try {
                if ($prepareLegacy && (string) $session["status"] === "approved") {
                    financial_ensure_closing_adjustment(
                        $session,
                        $uid,
                        "confirmed",
                    );
                }
                financial_assert_session_reconciled($session);
            } catch (Throwable $error) {
                $issues[] =
                    "Sessão #" .
                    (int) ($session["id"] ?? 0) .
                    ": " .
                    $error->getMessage();
            }
        }
        [$dayStart, $dayEnd] = app_local_day_utc_range($businessDate, $cid);
        $movements = q(
            "SELECT DISTINCT m.id,m.movement_type,m.status,m.amount_cents,m.from_location_id,m.to_location_id,m.cash_session_id,m.source_entity,m.source_id FROM pi_financial_movements m LEFT JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND m.status='confirmed' AND ((m.created_at>=? AND m.created_at<?) OR s.business_date=?) ORDER BY m.id",
            [$cid, $dayStart, $dayEnd, $businessDate],
        )->fetchAll();
        foreach ($movements as $movement) {
            try {
                $type = (string) ($movement["movement_type"] ?? "");
                $from = (int) ($movement["from_location_id"] ?? 0) ?: null;
                $to = (int) ($movement["to_location_id"] ?? 0) ?: null;
                financial_assert_amount_cents(
                    (int) ($movement["amount_cents"] ?? 0),
                );
                financial_validate_movement_topology($type, $from, $to);
                $sessionId = (int) ($movement["cash_session_id"] ?? 0);
                if ($sessionId > 0) {
                    $session = one(
                        "SELECT location_id FROM pi_cash_sessions WHERE id=? AND clinic_id=? LIMIT 1",
                        [$sessionId, $cid],
                    );
                    if (
                        !$session ||
                        financial_movement_delta_for_location(
                            (int) $movement["amount_cents"],
                            $from,
                            $to,
                            (int) $session["location_id"],
                        ) === 0
                    ) {
                        throw new RuntimeException(
                            "extremos incompatíveis com a sessão",
                        );
                    }
                }
            } catch (Throwable $error) {
                $issues[] =
                    "Movimento #" .
                    (int) ($movement["id"] ?? 0) .
                    ": " .
                    $error->getMessage();
            }
        }
        $metrics = financial_daily_metrics($cid, $businessDate);
        $position = financial_global_position($cid);
        foreach (
            [
                "drawer_cents" => (int) ($position["pos_cents"] ?? 0),
                "safe_cents" => (int) ($position["safe_cents"] ?? 0),
                "bank_cents" => (int) ($position["bank_cents"] ?? 0),
                "total_cents" => (int) ($position["total_cents"] ?? 0),
            ] as $label => $value
        ) {
            try {
                financial_assert_balance_cents($value, $label);
            } catch (Throwable $error) {
                $issues[] = $error->getMessage();
            }
        }
        $payload = [
            "policy" => "financial-reconciliation-v2",
            "clinic_id" => $cid,
            "business_date" => $businessDate,
            "metrics" => $metrics,
            "position" => [
                "drawer_cents" => (int) ($position["pos_cents"] ?? 0),
                "safe_cents" => (int) ($position["safe_cents"] ?? 0),
                "bank_cents" => (int) ($position["bank_cents"] ?? 0),
                "total_cents" => (int) ($position["total_cents"] ?? 0),
            ],
            "sessions" => $sessions,
            "movements" => $movements,
        ];
        $canonical = class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity")
            ? \Prontoo\Infrastructure\Integrity\PiIntegrity::canonicalJson($payload)
            : (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: "{}");
        return [
            "ok" => $issues === [],
            "issues" => $issues,
            "hash" => hash("sha256", $canonical),
            "metrics" => $metrics,
            "position" => $position,
            "session_count" => count($sessions),
            "movement_count" => count($movements),
        ];
    
    }

    public static function financial_daily_consolidation_state(
        int $cid,
        string $businessDate = "",
    ): array 
    {
    
        financial_operational_schema_ready();
        financial_daily_closing_ensure_schema();
        $businessDate = $businessDate ?: financial_today($cid);
        [$dayStart, $dayEnd] = app_local_day_utc_range($businessDate, $cid);
        $closure = financial_daily_drawer_closure_state($cid, $businessDate);
        $pendingOpen = (int) ($closure["blocking_count"] ?? 0);
        $pendingReview = (int) (val(
            "SELECT COUNT(*) FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND status IN ('closed_pending_review','rejected','opening_pending_review','opening_rejected')",
            [$cid, $businessDate],
        ) ?? 0);
        $pendingMovements = (int) (val(
            "SELECT COUNT(DISTINCT m.id) FROM pi_financial_movements m LEFT JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND m.status IN ('pending_review','rejected') AND ((m.created_at>=? AND m.created_at<?) OR s.business_date=?)",
            [$cid, $dayStart, $dayEnd, $businessDate],
        ) ?? 0);
        $consolidated = financial_day_is_consolidated($cid, $businessDate);
        $reconciliation = [
            "ok" => false,
            "issues" => [],
            "hash" => "",
        ];
        if (
            $pendingOpen === 0 &&
            $pendingReview === 0 &&
            $pendingMovements === 0 &&
            !$consolidated
        ) {
            $reconciliation = financial_daily_reconciliation(
                $cid,
                $businessDate,
            );
        }
        $blockers = [];
        if ($pendingOpen > 0) {
            $blockers[] =
                $pendingOpen .
                " colaborador(es) vinculado(s) ainda sem fechamento de gaveta";
        }
        if ($pendingReview > 0) {
            $blockers[] =
                $pendingReview .
                " conferência(s) de gaveta pendente(s) ou devolvida(s)";
        }
        if ($pendingMovements > 0) {
            $blockers[] = $pendingMovements . " movimento(s) ainda em revisão";
        }
        if ($consolidated) {
            $blockers[] = "dia financeiro já consolidado";
        }
        if (
            !$consolidated &&
            $pendingOpen === 0 &&
            $pendingReview === 0 &&
            $pendingMovements === 0 &&
            empty($reconciliation["ok"])
        ) {
            $blockers[] =
                "a reconciliação matemática encontrou " .
                max(1, count((array) ($reconciliation["issues"] ?? []))) .
                " inconsistência(s)";
        }
        return [
            "business_date" => $businessDate,
            "closure" => $closure,
            "pending_open_drawers" => $pendingOpen,
            "pending_reviews" => $pendingReview,
            "pending_movements" => $pendingMovements,
            "consolidated" => $consolidated,
            "reconciliation" => $reconciliation,
            "blockers" => $blockers,
            "conference_released" => $pendingOpen === 0,
            "can_consolidate" =>
                $pendingOpen === 0 &&
                $pendingReview === 0 &&
                $pendingMovements === 0 &&
                !empty($reconciliation["ok"]) &&
                !$consolidated,
        ];
    
    }

    public static function financial_patient_pending_revenue_count(int $cid, int $patientId): int
    
    {
    
        if ($patientId <= 0) {
            return 0;
        }
        return (int) safe_val(
            "SELECT COUNT(*) FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.patient_link_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.appointment_id IS NOT NULL AND a.status NOT IN ('cancelado','nao_compareceu','reagendado')",
            [$cid, $patientId],
            0,
        );
    
    }

    public static function financial_pending_revenue_belongs_to_patient(
        int $cid,
        int $revenueId,
        int $patientId,
    ): bool 
    {
    
        if ($revenueId <= 0 || $patientId <= 0) {
            return false;
        }
        return (int) (val(
            "SELECT r.id FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.id=? AND r.clinic_id=? AND r.patient_link_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.appointment_id IS NOT NULL AND a.status NOT IN ('cancelado','nao_compareceu','reagendado') LIMIT 1",
            [$revenueId, $cid, $patientId],
        ) ?:
            0) > 0;
    
    }

    public static function financial_cancel_appointment_revenue(
        int $cid,
        int $appointmentId,
        int $uid,
        string $reason = "",
    ): void 
    {
    
        if ($cid <= 0 || $appointmentId <= 0) {
            return;
        }
        financial_operational_schema_ready();
        $reason =
            trim($reason) ?:
            "Atendimento cancelado ou ausência registrada; cobrança prevista cancelada pela regra operacional.";
        try {
            db_tx(function () use ($cid, $appointmentId, $uid, $reason): void {
    
                $rev = one(
                    "SELECT id,status FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=? FOR UPDATE",
                    [$cid, $appointmentId],
                );
                if (!$rev || (string) ($rev["status"] ?? "") !== "prevista") {
                    return;
                }
                q(
                    "UPDATE pi_financial_revenues SET status='cancelada', updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status='prevista'",
                    [$uid, (int) $rev["id"], $cid],
                );
                q(
                    "UPDATE pi_financial_movements SET status='cancelled', confirmed_by=NULL, confirmed_at=NULL, reviewed_at=NOW(), notes=CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='', '', ' | '), ?) WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' AND status<>'confirmed'",
                    [$reason, $cid, $appointmentId],
                );
                audit(
                    "receita_prevista_cancelada",
                    "financeiro",
                    (int) $rev["id"],
                    [
                        "appointment_id" => $appointmentId,
                        "motivo" => $reason,
                        "audit_body" =>
                            "Receita prevista do agendamento foi cancelada por cancelamento, ausência ou remarcação sem cobrança.",
                    ],
                );
            });
        } catch (Throwable $e) {
            error_log("[Prontoo cancel appointment revenue] " . $e->getMessage());
        }
    
    }

    public static function financial_admin_receive_expected_revenue(
        int $cid,
        int $uid,
        int $revenueId,
        string $method,
        int $destinationLocationId,
        string $notes = "",
    ): int 
    {
    
        financial_operational_schema_ready();
        $method = normalize_payment_method($method);
        if ($method === "") {
            throw new RuntimeException("Informe a forma de recebimento.");
        }
        if (!financial_admin_location_belongs($cid, $destinationLocationId)) {
            throw new RuntimeException(
                "Recebimento pelo Administrador deve entrar em Cofre ou Banco do Consultório. Gaveta pertence ao fluxo da Recepção.",
            );
        }
        return (int) db_tx(function () use (
            $cid,
            $uid,
            $revenueId,
            $method,
            $destinationLocationId,
            $notes,
        ): int {
    
            $rev = one(
                "SELECT r.*,a.status appointment_status,a.start_at,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.id=? AND r.clinic_id=? AND r.status='prevista' AND r.appointment_id IS NOT NULL AND r.amount_cents>0 AND a.status NOT IN ('cancelado','nao_compareceu','reagendado') FOR UPDATE",
                [$revenueId, $cid],
            );
            if (!$rev) {
                throw new RuntimeException(
                    "Selecione uma pendência de agendamento ainda não recebida.",
                );
            }
            $amount = (int) $rev["amount_cents"];
            $appointmentId = (int) $rev["appointment_id"];
            $dest = one(
                "SELECT id,account_id,location_type,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') LIMIT 1",
                [$destinationLocationId, $cid],
            );
            if (!$dest) {
                throw new RuntimeException("Destino financeiro inválido.");
            }
            $accountId =
                (string) $dest["location_type"] === "bank_account"
                    ? ((int) ($dest["account_id"] ?? 0) ?:
                    null)
                    : null;
            $title =
                "Recebimento · " .
                trim(
                    (string) ($rev["procedure_title"] ?:
                    $rev["title"] ?:
                    "Atendimento agendado"),
                );
            $movementNotes =
                "Administrador recebeu pendência de agendamento em Cofre/Banco; não movimenta Gaveta." .
                (trim($notes) !== "" ? " " . trim($notes) : "");
            $existing = one(
                "SELECT id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$cid, $appointmentId],
            );
            if ($existing) {
                financial_update_existing_movement(
                    $cid,
                    (int) $existing["id"],
                    "receipt",
                    $amount,
                    null,
                    $destinationLocationId,
                    null,
                    $uid,
                    $title,
                    $method,
                    $movementNotes,
                    "confirmed",
                );
                $movementId = (int) $existing["id"];
            } else {
                $movementId = financial_create_movement(
                    $cid,
                    "receipt",
                    $amount,
                    null,
                    $destinationLocationId,
                    null,
                    $uid,
                    $title,
                    $method,
                    $movementNotes,
                    "confirmed",
                    "appointment",
                    $appointmentId,
                );
            }
            q(
                "UPDATE pi_financial_revenues SET status='efetivada', payment_method=?, account_id=?, received_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$method, $accountId, $uid, $revenueId, $cid],
            );
            q(
                "UPDATE pi_appointments SET payment_status='efetivada', payment_method=?, payment_amount_cents=?, payment_confirmed_at=NOW(), revenue_id=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$method, $amount, $revenueId, $appointmentId, $cid],
            );
            audit(
                "recebimento_administrativo_pendencia_agendamento",
                "financeiro",
                $revenueId,
                [
                    "appointment_id" => $appointmentId,
                    "movement_id" => $movementId,
                    "valor" => $amount,
                    "forma" => $method,
                    "destino" => $destinationLocationId,
                    "audit_body" =>
                        "Administrador recebeu pendência financeira vinculada ao agendamento, evitando receita avulsa duplicada.",
                ],
            );
            return $movementId;
        });
    
    }

    public static function financial_session_position_cents(
        array $session,
        int $excludeMovementId = 0,
    ): int 
    {
    
        $cid = (int) ($session["clinic_id"] ?? 0);
        $sessionId = (int) ($session["id"] ?? 0);
        $locationId = (int) ($session["location_id"] ?? 0);
        $balance = financial_assert_balance_cents(
            (int) ($session["opening_balance_cents"] ?? 0),
            "Saldo inicial da Gaveta",
        );
        $params = [$cid, $sessionId];
        $sql =
            "SELECT id,amount_cents,from_location_id,to_location_id FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND status IN ('confirmed','pending_review')";
        if ($excludeMovementId > 0) {
            $sql .= " AND id<>?";
            $params[] = $excludeMovementId;
        }
        $sql .= " ORDER BY id";
        foreach (q($sql, $params)->fetchAll() as $movement) {
            $balance = financial_checked_add(
                $balance,
                financial_movement_delta_for_location(
                    (int) ($movement["amount_cents"] ?? 0),
                    (int) ($movement["from_location_id"] ?? 0) ?: null,
                    (int) ($movement["to_location_id"] ?? 0) ?: null,
                    $locationId,
                ),
                "Saldo potencial da Gaveta",
            );
        }
        return $balance;
    
    }
}
