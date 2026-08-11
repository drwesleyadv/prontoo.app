<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Financial;

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
    
        [$dayStart, $dayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($businessDate, $cid);
        $expected = (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.05.daily_metrics.01", [$cid, $dayStart, $dayEnd], []) ?? 0);
        $received = (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.05.daily_metrics.02", [$cid, $dayStart, $dayEnd], []) ?? 0);
        $pending = (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.05.daily_metrics.03", [$cid, $dayStart, $dayEnd], []) ?? 0);
        $movements = (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.05.daily_metrics.04", [$cid, $dayStart, $dayEnd, $businessDate], []) ?? 0);
        $expected = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_balance_cents(
            $expected,
            "Receita prevista diária",
        );
        $received = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_balance_cents(
            $received,
            "Receita recebida diária",
        );
        $pending = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_balance_cents(
            $pending,
            "Receita pendente diária",
        );
        if (
            $expected !==
            \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
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
        $sessions = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.05.daily_reconciliation.01", [$cid, $businessDate], [])->fetchAll();
        foreach ($sessions as $session) {
            try {
                if ($prepareLegacy && (string) $session["status"] === "approved") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_ensure_closing_adjustment(
                        $session,
                        $uid,
                        "confirmed",
                    );
                }
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_assert_session_reconciled($session);
            } catch (Throwable $error) {
                $issues[] =
                    "Sessão #" .
                    (int) ($session["id"] ?? 0) .
                    ": " .
                    $error->getMessage();
            }
        }
        [$dayStart, $dayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($businessDate, $cid);
        $movements = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.05.daily_reconciliation.02", [$cid, $dayStart, $dayEnd, $businessDate], [])->fetchAll();
        foreach ($movements as $movement) {
            try {
                $type = (string) ($movement["movement_type"] ?? "");
                $from = (int) ($movement["from_location_id"] ?? 0) ?: null;
                $to = (int) ($movement["to_location_id"] ?? 0) ?: null;
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_amount_cents(
                    (int) ($movement["amount_cents"] ?? 0),
                );
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_validate_movement_topology($type, $from, $to);
                $sessionId = (int) ($movement["cash_session_id"] ?? 0);
                if ($sessionId > 0) {
                    $session = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.05.daily_reconciliation.03", [$sessionId, $cid], []);
                    if (
                        !$session ||
                        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_delta_for_location(
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
        $metrics = \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_metrics($cid, $businessDate);
        $position = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_global_position($cid);
        foreach (
            [
                "drawer_cents" => (int) ($position["pos_cents"] ?? 0),
                "safe_cents" => (int) ($position["safe_cents"] ?? 0),
                "bank_cents" => (int) ($position["bank_cents"] ?? 0),
                "total_cents" => (int) ($position["total_cents"] ?? 0),
            ] as $label => $value
        ) {
            try {
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_balance_cents($value, $label);
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
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->ensureDailyClosingSchema();
        $businessDate = $businessDate ?: \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        [$dayStart, $dayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($businessDate, $cid);
        $closure = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_daily_drawer_closure_state($cid, $businessDate);
        $pendingOpen = (int) ($closure["blocking_count"] ?? 0);
        $pendingReview = (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.05.daily_consolidation_state.01", [$cid, $businessDate], []) ?? 0);
        $pendingMovements = (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.05.daily_consolidation_state.02", [$cid, $dayStart, $dayEnd, $businessDate], []) ?? 0);
        $consolidated = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_day_is_consolidated($cid, $businessDate);
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
            $reconciliation = \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_reconciliation(
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
        return (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->safeScalar("financial.05.patient_pending_revenue_count.01", [$cid, $patientId], 0, []);
    
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
        return (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.05.pending_revenue_belongs_to_patient.01", [$revenueId, $cid, $patientId], []) ?:
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
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $reason =
            trim($reason) ?:
            "Atendimento cancelado ou ausência registrada; cobrança prevista cancelada pela regra operacional.";
        try {
            \Prontoo\Runtime\Financial\FinancialComposition::revenueService()->cancelAppointmentRevenue(
                $cid,
                $appointmentId,
                $uid,
                $reason,
                Closure::fromCallable([
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class,
                    'audit',
                ]),
            );
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
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $method = \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method($method);
        if ($method === "") {
            throw new RuntimeException("Informe a forma de recebimento.");
        }
        if (!\Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_admin_location_belongs($cid, $destinationLocationId)) {
            throw new RuntimeException(
                "Recebimento pelo Administrador deve entrar em Cofre ou Banco do Consultório. Gaveta pertence ao fluxo da Recepção.",
            );
        }
        return \Prontoo\Runtime\Financial\FinancialComposition::revenueService()->receiveByAdministrator(
            $cid,
            $uid,
            $revenueId,
            $method,
            $destinationLocationId,
            $notes,
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::class,
                'financial_validate_movement_invariants',
            ]),
            Closure::fromCallable([
                \Prontoo\Domain\Financial\FinancialDomainOperations01::class,
                'financial_human_movement_type',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class,
                'audit',
            ]),
        );
    
    }

    public static function financial_session_position_cents(
        array $session,
        int $excludeMovementId = 0,
    ): int 
    {
    
        $cid = (int) ($session["clinic_id"] ?? 0);
        $sessionId = (int) ($session["id"] ?? 0);
        $locationId = (int) ($session["location_id"] ?? 0);
        $balance = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_balance_cents(
            (int) ($session["opening_balance_cents"] ?? 0),
            "Saldo inicial da Gaveta",
        );
        $params = [$cid, $sessionId];
        if ($excludeMovementId > 0) {
            $params[] = $excludeMovementId;
        }
        foreach (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.05.session_position_cents.01", $params, compact('excludeMovementId'))->fetchAll() as $movement) {
            $balance = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
                $balance,
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_delta_for_location(
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
