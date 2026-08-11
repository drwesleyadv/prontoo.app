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

final class FinancialRuntimeOperations08
{
    private function __construct()
    {
    }

    public static function financial_close_session(
        int $cid,
        int $uid,
        int $sessionId,
        int $declared,
        int $withdrawalAmount,
        int $withdrawalDestinationId = 0,
        string $notes = "",
    ): void 
    {

        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        \Prontoo\Runtime\Financial\FinancialComposition::cashSessionService()->close(
            $cid,
            $uid,
            $sessionId,
            $declared,
            $withdrawalAmount,
            $withdrawalDestinationId,
            $notes,
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::class,
                'financial_session_expected',
            ]),
            Closure::fromCallable([
                \Prontoo\Domain\Financial\FinancialDomainOperations01::class,
                'financial_assert_amount_cents',
            ]),
            Closure::fromCallable([
                \Prontoo\Domain\Financial\FinancialDomainOperations01::class,
                'financial_checked_add',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::class,
                'financial_office_destination_belongs',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::class,
                'financial_ensure_admin_safe',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::class,
                'financial_validate_movement_invariants',
            ]),
            Closure::fromCallable([
                \Prontoo\Domain\Financial\FinancialDomainOperations01::class,
                'financial_human_movement_type',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::class,
                'financial_record_cash_difference',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::class,
                'financial_drawer_lock_after_close',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class,
                'audit',
            ]),
        );
    
    }

    public static function financial_review_opening_request(
        int $cid,
        int $adminUid,
        int $sessionId,
        string $decision,
        string $notes = "",
    ): void 
    {

        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        \Prontoo\Runtime\Financial\FinancialComposition::reviewService()->reviewOpening(
            $cid,
            $adminUid,
            $sessionId,
            $decision,
            $notes,
            Closure::fromCallable([
                \Prontoo\Domain\Financial\FinancialDomainOperations01::class,
                'financial_checked_add',
            ]),
            Closure::fromCallable([
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::class,
                'money_br',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::class,
                'financial_record_cash_difference',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class,
                'audit',
            ]),
        );
    
    }

    public static function financial_review_session(
        int $cid,
        int $uid,
        int $sessionId,
        string $decision,
        string $notes = "",
        string $drawerUnlockLocal = "",
    ): void 
    {

        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        \Prontoo\Runtime\Financial\FinancialComposition::reviewService()->reviewSession(
            $cid,
            $uid,
            $sessionId,
            $decision,
            $notes,
            $drawerUnlockLocal,
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::class,
                'financial_ensure_closing_adjustment',
            ]),
            Closure::fromCallable([self::class, 'financial_assert_session_reconciled']),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::class,
                'financial_drawer_row',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::class,
                'financial_schedule_drawer_unlock',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class,
                'audit',
            ]),
        );
    
    }

    public static function financial_assert_session_reconciled(array $session): void
    
    {
    
        $cid = (int) ($session["clinic_id"] ?? 0);
        $sessionId = (int) ($session["id"] ?? 0);
        $locationId = (int) ($session["location_id"] ?? 0);
        $expected = (int) ($session["expected_closing_cents"] ?? 0);
        $declared = (int) ($session["declared_closing_cents"] ?? 0);
        $kept = (int) ($session["keep_in_drawer_cents"] ?? 0);
        $transferred = (int) ($session["transfer_to_safe_cents"] ?? 0);
        $difference = (int) ($session["difference_cents"] ?? 0);
        if (
            !\Prontoo\Domain\Financial\FinancialDomainOperations01::financial_closing_equation(
                $expected,
                $declared,
                $kept,
                $transferred,
                $difference,
            )
        ) {
            throw new RuntimeException(
                "O fechamento não satisfaz a equação de conservação monetária.",
            );
        }
        if (\Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_session_position_cents($session) !== $kept) {
            throw new RuntimeException(
                "A posição dos movimentos da Gaveta diverge do saldo mantido.",
            );
        }
        $movements = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.08.assert_session_reconciled.01", [$cid, $sessionId], [])->fetchAll();
        $transferTotal = 0;
        $adjustments = [];
        foreach ($movements as $movement) {
            $type = (string) ($movement["movement_type"] ?? "");
            $from = (int) ($movement["from_location_id"] ?? 0) ?: null;
            $to = (int) ($movement["to_location_id"] ?? 0) ?: null;
            \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_validate_movement_topology($type, $from, $to);
            if (
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_delta_for_location(
                    (int) ($movement["amount_cents"] ?? 0),
                    $from,
                    $to,
                    $locationId,
                ) === 0
            ) {
                throw new RuntimeException(
                    "Movimento da sessão não alcança a Gaveta reconciliada.",
                );
            }
            if (
                $type === "transfer" &&
                (string) ($movement["source_entity"] ?? "") === "cash_session" &&
                (int) ($movement["source_id"] ?? 0) === $sessionId
            ) {
                $transferTotal = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
                    $transferTotal,
                    (int) $movement["amount_cents"],
                    "Total de retiradas",
                );
            }
            if (
                $type === "adjustment" &&
                (string) ($movement["source_entity"] ?? "") ===
                    "cash_closing_adjustment" &&
                (int) ($movement["source_id"] ?? 0) === $sessionId
            ) {
                $adjustments[] = $movement;
            }
        }
        if ($transferTotal !== $transferred) {
            throw new RuntimeException(
                "A retirada declarada diverge dos movimentos da sessão.",
            );
        }
        if ($difference === 0 && $adjustments !== []) {
            throw new RuntimeException(
                "Fechamento sem diferença contém ajuste compensatório indevido.",
            );
        }
        if ($difference !== 0) {
            if (
                count($adjustments) !== 1 ||
                (int) $adjustments[0]["amount_cents"] !== abs($difference)
            ) {
                throw new RuntimeException(
                    "A diferença do fechamento não possui compensação única e exata.",
                );
            }
            $adjustment = $adjustments[0];
            $from = (int) ($adjustment["from_location_id"] ?? 0) ?: null;
            $to = (int) ($adjustment["to_location_id"] ?? 0) ?: null;
            $expectedDelta = $difference > 0 ? abs($difference) : -abs($difference);
            if (
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_delta_for_location(
                    (int) $adjustment["amount_cents"],
                    $from,
                    $to,
                    $locationId,
                ) !== $expectedDelta
            ) {
                throw new RuntimeException(
                    "O ajuste compensatório possui orientação incompatível com a diferença.",
                );
            }
        }
    
    }

    public static function financial_location_movement_balance(int $cid, int $locationId): int
    
    {
    
        $balances = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_location_movement_balances($cid, [$locationId]);
        return (int) ($balances[$locationId] ?? 0);
    
    }
}
