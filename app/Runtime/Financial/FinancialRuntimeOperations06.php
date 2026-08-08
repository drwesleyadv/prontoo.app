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
        $excludeTo = "";
        $excludeFrom = "";
        if ($excludeMovementId > 0) {
            $excludeTo = " AND id<>?";
            $excludeFrom = " AND id<>?";
            $params = [
                $locationId,
                $cid,
                $excludeMovementId,
                $locationId,
                $cid,
                $excludeMovementId,
            ];
        }
        $balance = (int) (val(
            "SELECT COALESCE(SUM(delta_cents),0) FROM (" .
                "SELECT amount_cents delta_cents FROM pi_financial_movements WHERE to_location_id=? AND clinic_id=? AND status IN ('confirmed','pending_review')" .
                $excludeTo .
                " UNION ALL SELECT -amount_cents delta_cents FROM pi_financial_movements WHERE from_location_id=? AND clinic_id=? AND status IN ('confirmed','pending_review')" .
                $excludeFrom .
                ") financial_potential",
            $params,
        ) ?:
            0);
        return financial_assert_balance_cents(
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
    
        financial_assert_amount_cents($amount);
        if ($cid <= 0 || $amount <= 0) {
            throw new RuntimeException("Informe um valor financeiro válido.");
        }
        q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
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
        financial_validate_movement_topology($type, $from, $to);
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
            $placeholders = implode(",", array_fill(0, count($locationIds), "?"));
            $locked = q(
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND id IN ($placeholders) AND active=1 ORDER BY id FOR UPDATE",
                array_merge([$cid], $locationIds),
            )->fetchAll();
            if (count($locked) !== count($locationIds)) {
                throw new RuntimeException("Origem ou destino financeiro inválido.");
            }
        }
        $session = null;
        $businessDate = financial_today($cid);
        if (($sessionId ?? 0) > 0) {
            $session = one(
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? FOR UPDATE",
                [$sessionId, $cid],
            );
            if (!$session) {
                throw new RuntimeException(
                    "Sessão de gaveta inválida para o lançamento.",
                );
            }
            $businessDate = app_date_input_from_storage(
                $session["business_date"] ?? "",
            ) ?: $businessDate;
            $sessionLocation = (int) ($session["location_id"] ?? 0);
            if (
                financial_movement_delta_for_location(
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
        if (financial_day_is_consolidated($cid, $businessDate)) {
            throw new RuntimeException(
                "O dia financeiro já foi consolidado e seus movimentos são imutáveis.",
            );
        }
        if (in_array($status, ["confirmed", "pending_review"], true)) {
            foreach ($locationIds as $locationId) {
                financial_checked_add(
                    financial_location_potential_balance(
                        $cid,
                        $locationId,
                        $excludeMovementId,
                    ),
                    financial_movement_delta_for_location(
                        $amount,
                        $from,
                        $to,
                        $locationId,
                    ),
                    "Saldo potencial do local financeiro",
                );
            }
            if (is_array($session)) {
                financial_checked_add(
                    financial_session_position_cents(
                        $session,
                        $excludeMovementId,
                    ),
                    financial_movement_delta_for_location(
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
    
        db_tx(function () use (
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
    
            $existing = one(
                "SELECT id FROM pi_financial_movements WHERE id=? AND clinic_id=? FOR UPDATE",
                [$movementId, $cid],
            );
            if (!$existing) {
                throw new RuntimeException("Movimento financeiro não encontrado.");
            }
            financial_validate_movement_invariants(
                $cid,
                $type,
                $amount,
                $from,
                $to,
                $sessionId,
                $status,
                $movementId,
            );
            q(
                "UPDATE pi_financial_movements SET movement_type=?,status=?,amount_cents=?,payment_method=?,from_location_id=?,to_location_id=?,cash_session_id=?,title=?,notes=?,updated_at=NOW(),confirmed_by=IF(?='confirmed',COALESCE(confirmed_by,?),NULL),confirmed_at=IF(?='confirmed',COALESCE(confirmed_at,NOW()),NULL),reviewed_by=NULL,reviewed_at=NULL WHERE id=? AND clinic_id=?",
                [
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
                ],
            );
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
    
        return (int) db_tx(function () use (
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
    
            financial_operational_schema_ready();
            financial_validate_movement_invariants(
                $cid,
                $type,
                $amount,
                $from,
                $to,
                $sessionId,
                $status,
            );
            $title = trim($title) ?: financial_human_movement_type($type);
            $paymentMethod = mb_substr(trim($paymentMethod), 0, 40);
            q(
                "INSERT INTO pi_financial_movements (clinic_id,movement_type,status,amount_cents,payment_method,from_location_id,to_location_id,cash_session_id,source_entity,source_id,title,notes,created_by,created_at,confirmed_by,confirmed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,IF(?='confirmed',NOW(),NULL))",
                [
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
                ],
            );
            return db_last_insert_id();
        });
    
    }

    public static function financial_session_for_date(int $cid, int $uid, string $date): ?array
    
    {
    
        financial_operational_schema_ready();
        return one(
            "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date=? LIMIT 1",
            [$cid, $uid, $date],
        );
    
    }

    public static function financial_latest_session(int $cid, int $uid): ?array
    
    {
    
        financial_operational_schema_ready();
        return one(
            "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? ORDER BY business_date DESC,id DESC LIMIT 1",
            [$cid, $uid],
        );
    
    }

    public static function financial_previous_drawer_balance(
        int $cid,
        int $uid,
        string $beforeDate = "",
    ): int 
    {
    
        financial_operational_schema_ready();
        $loc = financial_cashier_location_for_user($cid, $uid);
        if ($loc <= 0) {
            return 0;
        }
        return financial_drawer_previous_balance(
            $cid,
            $loc,
            $beforeDate ?: financial_today($cid),
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
    
        $businessDate = $businessDate ?: financial_today($cid);
        $locationId =
            $locationId > 0
                ? $locationId
                : financial_cashier_location_for_user($cid, $uid);
        if ($locationId <= 0) {
            return 0;
        }
        return financial_drawer_previous_balance(
            $cid,
            $locationId,
            $businessDate,
            $ignoreSessionId,
        );
    
    }

    public static function financial_cashier_name(int $uid): string
    
    {
    
        $name = trim(
            (string) (val("SELECT name FROM pi_users WHERE id=? LIMIT 1", [$uid]) ?:
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
            $cashier = financial_cashier_name($cashierUid);
            $diff = financial_checked_add(
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
                money_br($expected) .
                "\nSaldo informado: " .
                money_br($informed) .
                "\nDiferença: " .
                money_br($diff) .
                "\n\nAbra Financeiro > Gavetas para autorizar ou recusar esta abertura.";
            q(
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $title,
                    $body,
                    1,
                    "role",
                    "gerente",
                    null,
                    $createdBy ?: null,
                ],
            );
            $noticeId = db_last_insert_id();
            if (function_exists("counter_inc")) {
                counter_inc("notices_total");
            }
            if (function_exists("clinic_metric_inc")) {
                clinic_metric_inc($cid, "notices");
            }
            audit(
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
