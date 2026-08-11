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

final class FinancialRuntimeOperations07
{
    private function __construct()
    {
    }

    public static function financial_request_opening_authorization(
        int $cid,
        int $uid,
        int $loc,
        string $today,
        int $expected,
        int $informed,
        ?array $existing = null,
    ): int 
    {
    
        $diff = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
            $informed,
            -$expected,
            "Diferença da abertura",
        );
        $notes =
            "Abertura bloqueada: Saldo Inicial informado diverge do saldo não retirado do último fechamento.";
        if ($existing && (int) ($existing["id"] ?? 0) > 0) {
            $sid = (int) $existing["id"];
            $oldStatus = (string) ($existing["status"] ?? "");
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.07.request_opening_authorization.01", [
                    $loc,
                    $informed,
                    $expected,
                    $informed,
                    $expected,
                    $diff,
                    $notes,
                    $sid,
                    $cid,
                    $uid,
                ], []);
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("abertura_caixa_divergente_atualizada", "financeiro", $sid, [
                "esperado" => $expected,
                "informado" => $informed,
                "diferenca" => $diff,
                "status_anterior" => $oldStatus,
                "audit_body" =>
                    "Atendimento atualizou solicitação de abertura de caixa com Saldo Inicial divergente.",
            ]);
            if ($oldStatus !== "opening_pending_review") {
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_notify_opening_authorization_request(
                    $cid,
                    $uid,
                    $sid,
                    $expected,
                    $informed,
                    $uid,
                );
            }
            return $sid;
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.07.request_opening_authorization.02", [
                $cid,
                $uid,
                $loc,
                $today,
                $informed,
                $expected,
                $informed,
                $expected,
                $diff,
                $notes,
            ], []);
        $sid = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("abertura_caixa_divergente_solicitada", "financeiro", $sid, [
            "esperado" => $expected,
            "informado" => $informed,
            "diferenca" => $diff,
            "audit_body" =>
                "Atendimento solicitou abertura de caixa com Saldo Inicial divergente do saldo não retirado anterior.",
        ]);
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_notify_opening_authorization_request(
            $cid,
            $uid,
            $sid,
            $expected,
            $informed,
            $uid,
        );
        return $sid;
    
    }

    public static function financial_unclosed_previous_session(
        int $cid,
        int $uid,
        string $today = "",
    ): ?array 
    {
    
        $today = $today ?: \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.07.unclosed_previous_session.01", [$cid, $uid, $today], []);
    
    }

    public static function financial_keep_closed(int $cid, int $uid): int
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        if ($cid <= 0 || $uid <= 0) {
            throw new RuntimeException("Usuário ou consultório inválido.");
        }
        $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        return \Prontoo\Runtime\Financial\FinancialComposition::cashSessionService()->keepClosed(
            $cid,
            $uid,
            $today,
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::class,
                'financial_cashier_location_for_user',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::class,
                'financial_drawer_guard_can_use',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::class,
                'financial_drawer_open_session',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::class,
                'financial_drawer_pending_previous_review',
            ]),
            Closure::fromCallable([self::class, 'financial_unclosed_previous_session']),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::class,
                'financial_session_for_date',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::class,
                'financial_drawer_previous_balance',
            ]),
            Closure::fromCallable([
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::class,
                'first_name',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class,
                'audit',
            ]),
        );
    
    }

    public static function financial_open_session(int $cid, int $uid, int $openingBalance): int
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        if ($cid <= 0 || $uid <= 0) {
            throw new RuntimeException("Usuário ou consultório inválido.");
        }
        $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        $result = \Prontoo\Runtime\Financial\FinancialComposition::cashSessionService()->open(
            $cid,
            $uid,
            $openingBalance,
            $today,
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::class,
                'financial_cashier_location_for_user',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::class,
                'financial_drawer_guard_can_use',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::class,
                'financial_drawer_open_session',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::class,
                'financial_drawer_pending_previous_review',
            ]),
            Closure::fromCallable([self::class, 'financial_unclosed_previous_session']),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::class,
                'financial_session_for_date',
            ]),
            Closure::fromCallable([
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::class,
                'first_name',
            ]),
            Closure::fromCallable([
                \Prontoo\Domain\Financial\FinancialDomainOperations01::class,
                'financial_assert_amount_cents',
            ]),
            Closure::fromCallable([
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::class,
                'financial_expected_opening_balance',
            ]),
            Closure::fromCallable([self::class, 'financial_request_opening_authorization']),
            Closure::fromCallable([
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class,
                'audit',
            ]),
        );
        if (!empty($result['authorization_requested'])) {
            throw new RuntimeException((string) $result['authorization_message']);
        }
        return (int) $result['session_id'];
    
    }

    public static function financial_session_movement_totals(int $cid, int $sessionId): array
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.07.session_movement_totals.01", [$cid, $sessionId], [])->fetchAll();
        $out = [
            "receipt" => 0,
            "payment" => 0,
            "refund" => 0,
            "transfer" => 0,
            "deposit" => 0,
            "adjustment" => 0,
        ];
        foreach ($rows as $r) {
            $out[(string) $r["movement_type"]] = (int) $r["total"];
        }
        return $out;
    
    }

    public static function financial_session_expected(array $session): int
    
    {
    
        return \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_session_position_cents($session);
    
    }

    public static function financial_record_cash_difference(
        int $cid,
        int $uid,
        int $sessionId,
        int $locationId,
        int $difference,
        string $phase,
        string $status,
        string $notes = "",
        bool $linkSession = true,
    ): int 
    {
    
        if ($difference === 0) {
            return 0;
        }
        $positive = $difference > 0;
        return \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_create_movement(
            $cid,
            "adjustment",
            abs($difference),
            $positive ? null : $locationId,
            $positive ? $locationId : null,
            $linkSession ? $sessionId : null,
            $uid,
            ($positive ? "Sobra" : "Falta") .
                " na reconciliação de " .
                ($phase === "opening" ? "abertura" : "fechamento"),
            "",
            trim($notes),
            $status,
            "cash_" . $phase . "_adjustment",
            $sessionId,
        );
    
    }

    public static function financial_ensure_closing_adjustment(
        array $session,
        int $uid,
        string $status,
    ): void 
    {
    
        $difference = (int) ($session["difference_cents"] ?? 0);
        if ($difference === 0) {
            return;
        }
        $existing = (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.07.ensure_closing_adjustment.01", [
                (int) $session["clinic_id"],
                (int) $session["id"],
                (int) $session["id"],
            ], []) ?:
            0);
        if ($existing === 0) {
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_record_cash_difference(
                (int) $session["clinic_id"],
                $uid,
                (int) $session["id"],
                (int) $session["location_id"],
                $difference,
                "closing",
                $status,
                mb_trim((string) ($session["closing_notes"] ?? "")),
                true,
            );
        }
    
    }

    public static function financial_current_open_session(int $cid, int $uid): ?array
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.07.current_open_session.01", [$cid, $uid, \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid)], []);
    
    }

    public static function financial_require_open_session(int $cid, int $uid): array
    
    {
    
        $s = \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_current_open_session($cid, $uid);
        if (!$s) {
            $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_session_for_date($cid, $uid, \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid));
            if ($today && (string) $today["status"] === "kept_closed") {
                throw new RuntimeException(
                    "A Gaveta foi mantida fechada. Abra a Gaveta antes de registrar movimentos.",
                );
            }
            throw new RuntimeException(
                "Abra a Gaveta antes de registrar movimentos do Atendimento.",
            );
        }
        return $s;
    
    }
}
