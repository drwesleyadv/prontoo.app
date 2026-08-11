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

final class FinancialRuntimeOperations06
{
    private function __construct()
    {
    }

    public static function financial_location_potential_balance(
        int $cid,
        int $locationId,
        int $excludeMovementId = 0,
    ): int 
    {
    
        $params = [$locationId, $cid, $locationId, $cid];
        if ($excludeMovementId > 0) {
            $params = [
                $locationId,
                $cid,
                $excludeMovementId,
                $locationId,
                $cid,
                $excludeMovementId,
            ];
        }
        $balance = (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.06.location_potential_balance.01", $params, ['excludeMovement' => $excludeMovementId > 0]) ?:
            0);
        return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_balance_cents(
            $balance,
            "Saldo potencial do local financeiro",
        );
    
    }

    public static function financial_validate_movement_invariants(
        int $cid,
        string $type,
        int $amount,
        ?int $from,
        ?int $to,
        ?int $sessionId,
        string $status,
        int $excludeMovementId = 0,
    ): void 
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_amount_cents($amount);
        if ($cid <= 0 || $amount <= 0) {
            throw new RuntimeException("Informe um valor financeiro válido.");
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.06.validate_movement_invariants.01", [$cid], []);
        $allowedTypes = [
            "receipt",
            "payment",
            "transfer",
            "deposit",
            "cash_opening",
            "cash_closing",
            "adjustment",
            "refund",
        ];
        if (!in_array($type, $allowedTypes, true)) {
            throw new RuntimeException("Natureza de movimento financeiro inválida.");
        }
        if (
            !in_array(
                $status,
                ["confirmed", "pending_review", "cancelled", "rejected"],
                true,
            )
        ) {
            throw new RuntimeException("Estado de movimento financeiro inválido.");
        }
        $from = ($from ?? 0) > 0 ? $from : null;
        $to = ($to ?? 0) > 0 ? $to : null;
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_validate_movement_topology($type, $from, $to);
        $locationIds = array_values(
            array_unique(
                array_filter(
                    [$from, $to],
                    static  fn(?int $id): bool =>
                        $id !== null,
                ),
            ),
        );
        sort($locationIds, SORT_NUMERIC);
        if ($locationIds) {
            $locked = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.06.validate_movement_invariants.02", array_merge([$cid], $locationIds), ['itemCount' => count($locationIds)])->fetchAll();
            if (count($locked) !== count($locationIds)) {
                throw new RuntimeException("Origem ou destino financeiro inválido.");
            }
        }
        $session = null;
        $businessDate = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        if (($sessionId ?? 0) > 0) {
            $session = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.06.validate_movement_invariants.03", [$sessionId, $cid], []);
            if (!$session) {
                throw new RuntimeException(
                    "Sessão de gaveta inválida para o lançamento.",
                );
            }
            $businessDate = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                $session["business_date"] ?? "",
            ) ?: $businessDate;
            $sessionLocation = (int) ($session["location_id"] ?? 0);
            if (
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_delta_for_location(
                    $amount,
                    $from,
                    $to,
                    $sessionLocation,
                ) === 0
            ) {
                throw new RuntimeException(
                    "O movimento da sessão deve partir ou chegar à Gaveta vinculada.",
                );
            }
            if (
                (string) ($session["status"] ?? "") === "approved" &&
                $status === "pending_review"
            ) {
                throw new RuntimeException(
                    "A sessão de gaveta já foi conferida. Novo lançamento exige ajuste próprio.",
                );
            }
        }
        if (\Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_day_is_consolidated($cid, $businessDate)) {
            throw new RuntimeException(
                "O dia financeiro já foi consolidado e seus movimentos são imutáveis.",
            );
        }
        if (in_array($status, ["confirmed", "pending_review"], true)) {
            foreach ($locationIds as $locationId) {
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_location_potential_balance(
                        $cid,
                        $locationId,
                        $excludeMovementId,
                    ),
                    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_delta_for_location(
                        $amount,
                        $from,
                        $to,
                        $locationId,
                    ),
                    "Saldo potencial do local financeiro",
                );
            }
            if (is_array($session)) {
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_session_position_cents(
                        $session,
                        $excludeMovementId,
                    ),
                    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_delta_for_location(
                        $amount,
                        $from,
                        $to,
                        (int) $session["location_id"],
                    ),
                    "Saldo potencial da sessão",
                );
            }
        }
    
    }

    public static function financial_update_existing_movement(
        int $cid,
        int $movementId,
        string $type,
        int $amount,
        ?int $from,
        ?int $to,
        ?int $sessionId,
        int $uid,
        string $title,
        string $paymentMethod,
        string $notes,
        string $status,
    ): void 
    {
    
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->atomic(function () use (
            $cid,
            $movementId,
            $type,
            $amount,
            $from,
            $to,
            $sessionId,
            $uid,
            $title,
            $paymentMethod,
            $notes,
            $status,
        ): void {
    
            $existing = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.06.update_existing_movement.01", [$movementId, $cid], []);
            if (!$existing) {
                throw new RuntimeException("Movimento financeiro não encontrado.");
            }
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_validate_movement_invariants(
                $cid,
                $type,
                $amount,
                $from,
                $to,
                $sessionId,
                $status,
                $movementId,
            );
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.06.update_existing_movement.02", [
                    $type,
                    $status,
                    $amount,
                    trim($paymentMethod) ?: null,
                    $from ?: null,
                    $to ?: null,
                    $sessionId ?: null,
                    trim($title),
                    trim($notes) ?: null,
                    $status,
                    $uid ?: null,
                    $status,
                    $movementId,
                    $cid,
                ], []);
        });
    
    }

    public static function financial_create_movement(
        int $cid,
        string $type,
        int $amount,
        ?int $from,
        ?int $to,
        ?int $sessionId,
        int $uid,
        string $title,
        string $paymentMethod = "",
        string $notes = "",
        string $status = "confirmed",
        string $sourceEntity = "",
        int $sourceId = 0,
    ): int 
    {
    
        return (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->atomic(function () use (
            $cid,
            $type,
            $amount,
            $from,
            $to,
            $sessionId,
            $uid,
            $title,
            $paymentMethod,
            $notes,
            $status,
            $sourceEntity,
            $sourceId,
        ): int {
    
            \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_validate_movement_invariants(
                $cid,
                $type,
                $amount,
                $from,
                $to,
                $sessionId,
                $status,
            );
            $title = trim($title) ?: \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_movement_type($type);
            $paymentMethod = mb_substr(trim($paymentMethod), 0, 40);
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.06.create_movement.01", [
                    $cid,
                    $type,
                    $status,
                    $amount,
                    $paymentMethod ?: null,
                    $from ?: null,
                    $to ?: null,
                    $sessionId ?: null,
                    $sourceEntity ?: null,
                    $sourceId ?: null,
                    $title,
                    trim($notes) ?: null,
                    $uid ?: null,
                    $status === "confirmed" ? ($uid ?: null) : null,
                    $status,
                ], []);
            return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
        });
    
    }

    public static function financial_session_for_date(int $cid, int $uid, string $date): ?array
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.06.session_for_date.01", [$cid, $uid, $date], []);
    
    }

    public static function financial_latest_session(int $cid, int $uid): ?array
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.06.latest_session.01", [$cid, $uid], []);
    
    }

    public static function financial_previous_drawer_balance(
        int $cid,
        int $uid,
        string $beforeDate = "",
    ): int 
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $loc = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_location_for_user($cid, $uid);
        if ($loc <= 0) {
            return 0;
        }
        return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_previous_balance(
            $cid,
            $loc,
            $beforeDate ?: \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid),
        );
    
    }

    public static function financial_expected_opening_balance(
        int $cid,
        int $uid,
        string $businessDate = "",
        int $locationId = 0,
        int $ignoreSessionId = 0,
    ): int 
    {
    
        $businessDate = $businessDate ?: \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        $locationId =
            $locationId > 0
                ? $locationId
                : \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_location_for_user($cid, $uid);
        if ($locationId <= 0) {
            return 0;
        }
        return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_previous_balance(
            $cid,
            $locationId,
            $businessDate,
            $ignoreSessionId,
        );
    
    }

    public static function financial_cashier_name(int $uid): string
    
    {
    
        $name = trim(
            (string) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.06.cashier_name.01", [$uid], []) ?:
            "Atendimento"),
        );
        return $name !== "" ? $name : "Atendimento";
    
    }

    public static function financial_notify_opening_authorization_request(
        int $cid,
        int $cashierUid,
        int $sessionId,
        int $expected,
        int $informed,
        int $createdBy,
    ): void 
    {
    
        try {
            $cashier = \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_cashier_name($cashierUid);
            $diff = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
                $informed,
                -$expected,
                "Diferença da abertura",
            );
            $title = "Autorizar abertura de caixa";
            $body =
                "A Recepção/Atendimento informou um Saldo Inicial diferente do saldo não retirado no último fechamento." .
                "\n\nColaborador: " .
                $cashier .
                "\nEsperado: " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected) .
                "\nSaldo informado: " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($informed) .
                "\nDiferença: " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($diff) .
                "\n\nAbra Financeiro > Gavetas para autorizar ou recusar esta abertura.";
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.06.notify_opening_authorization_request.01", [
                    $cid,
                    $title,
                    $body,
                    1,
                    "role",
                    "gerente",
                    null,
                    $createdBy ?: null,
                ], []);
            $noticeId = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
            if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'counter_inc'])) {
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("notices_total");
            }
            if (is_callable([\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::class, 'clinic_metric_inc'])) {
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "notices");
            }
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                "notificacao_autorizacao_abertura_caixa",
                "comunicado",
                $noticeId,
                [
                    "cash_session_id" => $sessionId,
                    "usuario_caixa" => $cashierUid,
                    "esperado" => $expected,
                    "informado" => $informed,
                    "diferenca" => $diff,
                    "audit_body" =>
                        "Aviso automática criada para o Administrativo autorizar abertura de caixa com saldo divergente.",
                ],
            );
        } catch (Throwable $e) {
            error_log("[Prontoo abertura caixa aviso] " . $e->getMessage());
        }
    
    }
}
