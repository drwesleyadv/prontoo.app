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
    
        financial_operational_schema_ready();
        db_tx(function () use (
            $cid,
            $uid,
            $sessionId,
            $declared,
            $withdrawalAmount,
            $withdrawalDestinationId,
            $notes,
        ): void {
    
            $s = one(
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? AND user_id=? AND status='open' FOR UPDATE",
                [$sessionId, $cid, $uid],
            );
            if (!$s) {
                throw new RuntimeException("Não há Gaveta aberta para fechamento.");
            }
            $expected = financial_session_expected($s);
            $declared = financial_assert_amount_cents(max(0, $declared));
            $withdrawalAmount = max(0, min($withdrawalAmount, $declared));
            $keep = max(0, $declared - $withdrawalAmount);
            $diff = financial_checked_add(
                $declared,
                -$expected,
                "Diferença do fechamento",
            );
            $destinationId = 0;
            if ($withdrawalAmount > 0) {
                if ($withdrawalDestinationId > 0) {
                    if (
                        !financial_office_destination_belongs(
                            $cid,
                            $withdrawalDestinationId,
                        )
                    ) {
                        throw new RuntimeException(
                            "Informe um Destino da Retirada válido entre as contas do Consultório.",
                        );
                    }
                    $destinationId = $withdrawalDestinationId;
                } else {
                    $destinationId = financial_ensure_admin_safe($cid, $uid);
                }
                if ($destinationId <= 0) {
                    throw new RuntimeException(
                        "Não foi possível preparar o Destino da Retirada.",
                    );
                }
            }
            q(
                "UPDATE pi_cash_sessions SET closed_at=NOW(), expected_closing_cents=?, declared_closing_cents=?, keep_in_drawer_cents=?, transfer_to_safe_cents=?, difference_cents=?, closing_notes=?, status='closed_pending_review', updated_at=NOW() WHERE id=? AND clinic_id=? AND user_id=?",
                [
                    $expected,
                    $declared,
                    $keep,
                    $withdrawalAmount,
                    $diff,
                    trim($notes) ?: null,
                    $sessionId,
                    $cid,
                    $uid,
                ],
            );
            if ($withdrawalAmount > 0) {
                financial_create_movement(
                    $cid,
                    "transfer",
                    $withdrawalAmount,
                    (int) $s["location_id"],
                    $destinationId,
                    $sessionId,
                    $uid,
                    "Retirada do fechamento da Gaveta",
                    "",
                    trim($notes),
                    "pending_review",
                    "cash_session",
                    $sessionId,
                );
            }
            financial_record_cash_difference(
                $cid,
                $uid,
                $sessionId,
                (int) $s["location_id"],
                $diff,
                "closing",
                "pending_review",
                trim($notes),
                true,
            );
            financial_drawer_lock_after_close(
                $cid,
                (int) $s["location_id"],
                (string) $s["business_date"],
                $sessionId,
                $uid,
            );
            audit("caixa_atendimento_fechado", "financeiro", $sessionId, [
                "esperado" => $expected,
                "declarado" => $declared,
                "fazer_retirada" => $withdrawalAmount,
                "destino_retirada" => $destinationId,
                "manter_gaveta" => $keep,
                "diferenca" => $diff,
            ]);
        });
    
    }

    public static function financial_review_opening_request(
        int $cid,
        int $adminUid,
        int $sessionId,
        string $decision,
        string $notes = "",
    ): void 
    {
    
        financial_operational_schema_ready();
        db_tx(function () use (
            $cid,
            $adminUid,
            $sessionId,
            $decision,
            $notes,
        ): void {
    
            $s = one(
                "SELECT s.*,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.id=? AND s.clinic_id=? AND s.status='opening_pending_review' FOR UPDATE",
                [$sessionId, $cid],
            );
            if (!$s) {
                throw new RuntimeException(
                    "A solicitação de abertura não está pendente de autorização.",
                );
            }
            $decision = $decision === "reject" ? "reject" : "approve";
            $expected = (int) ($s["expected_closing_cents"] ?? 0);
            $informed = (int) ($s["opening_balance_cents"] ?? 0);
            $diff = financial_checked_add(
                $informed,
                -$expected,
                "Diferença da abertura",
            );
            $cleanNotes = trim($notes);
            if ($decision === "reject") {
                q(
                    "UPDATE pi_cash_sessions SET status='opening_rejected', reviewed_by=?, reviewed_at=NOW(), review_status='rejected', review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [$adminUid, $cleanNotes ?: null, $sessionId, $cid],
                );
                q(
                    "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                    [
                        $cid,
                        "Abertura de caixa recusada",
                        "O Administrativo recusou a abertura de caixa com saldo diferente. Esperado: " .
                        money_br($expected) .
                        ". Saldo informado: " .
                        money_br($informed) .
                        ($cleanNotes !== ""
                            ? "\n\nObservação: " . $cleanNotes
                            : ""),
                        1,
                        "user",
                        null,
                        (int) $s["user_id"],
                        $adminUid,
                    ],
                );
                audit(
                    "abertura_caixa_divergente_recusada",
                    "financeiro",
                    $sessionId,
                    [
                        "usuario_caixa" => (int) $s["user_id"],
                        "esperado" => $expected,
                        "informado" => $informed,
                        "diferenca" => $diff,
                        "motivo" => $cleanNotes,
                    ],
                );
                return;
            }
            q(
                "UPDATE pi_cash_sessions SET opened_at=NOW(), kept_closed_at=NULL, closed_at=NULL, expected_closing_cents=0, declared_closing_cents=0, keep_in_drawer_cents=0, transfer_to_safe_cents=0, difference_cents=0, status='open', reviewed_by=?, reviewed_at=NOW(), review_status='approved', review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$adminUid, $cleanNotes ?: null, $sessionId, $cid],
            );
            financial_record_cash_difference(
                $cid,
                $adminUid,
                $sessionId,
                (int) $s["location_id"],
                $diff,
                "opening",
                "confirmed",
                $cleanNotes,
                false,
            );
            q(
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    "Abertura de caixa autorizada",
                    "O Administrativo autorizou a abertura de caixa com saldo diferente. Esperado: " .
                    money_br($expected) .
                    ". Saldo autorizado: " .
                    money_br($informed) .
                    ($cleanNotes !== "" ? "\n\nObservação: " . $cleanNotes : ""),
                    1,
                    "user",
                    null,
                    (int) $s["user_id"],
                    $adminUid,
                ],
            );
            audit(
                "abertura_caixa_divergente_autorizada",
                "financeiro",
                $sessionId,
                [
                    "usuario_caixa" => (int) $s["user_id"],
                    "esperado" => $expected,
                    "informado" => $informed,
                    "diferenca" => $diff,
                    "observacao" => $cleanNotes,
                    "audit_body" =>
                        "Gerência autorizou abertura da Gaveta com valor inicial divergente.",
                ],
            );
        });
    
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
    
        financial_operational_schema_ready();
        db_tx(function () use (
            $cid,
            $uid,
            $sessionId,
            $decision,
            $notes,
            $drawerUnlockLocal,
        ): void {
    
            $s = one(
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? AND status='closed_pending_review' FOR UPDATE",
                [$sessionId, $cid],
            );
            if (!$s) {
                throw new RuntimeException(
                    "Fechamento não está pendente de conferência.",
                );
            }
            if ($decision === "reject") {
                q(
                    "UPDATE pi_cash_sessions SET status='rejected', review_status='rejected', reviewed_by=?, reviewed_at=NOW(), review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [$uid, trim($notes) ?: null, $sessionId, $cid],
                );
                q(
                    "UPDATE pi_financial_movements SET status='rejected', reviewed_by=?, reviewed_at=NOW(), notes=CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='', '', ' | '), ?) WHERE clinic_id=? AND cash_session_id=? AND status='pending_review'",
                    [$uid, trim($notes), $cid, $sessionId],
                );
                q(
                    "INSERT INTO pi_cash_closing_reviews (clinic_id,cash_session_id,reviewed_by,decision,expected_cents,declared_cents,approved_transfer_cents,difference_cents,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())",
                    [
                        $cid,
                        $sessionId,
                        $uid,
                        "rejected",
                        (int) $s["expected_closing_cents"],
                        (int) $s["declared_closing_cents"],
                        0,
                        (int) $s["difference_cents"],
                        trim($notes) ?: null,
                    ],
                );
                audit("fechamento_caixa_devolvido", "financeiro", $sessionId, [
                    "motivo" => $notes,
                ]);
                return;
            }
            financial_ensure_closing_adjustment(
                $s,
                $uid,
                "pending_review",
            );
            $s = one(
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? FOR UPDATE",
                [$sessionId, $cid],
            ) ?: $s;
            financial_assert_session_reconciled($s);
            $remaining =
                (int) (val(
                    "SELECT COUNT(*) FROM pi_cash_sessions WHERE clinic_id=? AND location_id=? AND business_date=? AND status='closed_pending_review' AND id<>?",
                    [
                        $cid,
                        (int) $s["location_id"],
                        (string) $s["business_date"],
                        $sessionId,
                    ],
                ) ?:
                0);
            if ($remaining === 0 && (string) ($s["location_id"] ?? "") !== "0") {
                $drawer = financial_drawer_row($cid, (int) $s["location_id"]);
                if (
                    $drawer &&
                    (string) ($drawer["drawer_lock_status"] ?? "unlocked") ===
                        "locked" &&
                    trim($drawerUnlockLocal) === ""
                ) {
                    throw new RuntimeException(
                        "Informe o horário do dia seguinte em que a Gaveta será destrancada.",
                    );
                }
            }
            q(
                "UPDATE pi_cash_sessions SET status='approved', review_status='approved', reviewed_by=?, reviewed_at=NOW(), review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$uid, trim($notes) ?: null, $sessionId, $cid],
            );
            q(
                "UPDATE pi_financial_movements SET status='confirmed', confirmed_by=?, confirmed_at=NOW(), reviewed_by=?, reviewed_at=NOW() WHERE clinic_id=? AND cash_session_id=? AND status='pending_review'",
                [$uid, $uid, $cid, $sessionId],
            );
            q(
                "INSERT INTO pi_cash_closing_reviews (clinic_id,cash_session_id,reviewed_by,decision,expected_cents,declared_cents,approved_transfer_cents,difference_cents,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $sessionId,
                    $uid,
                    "approved",
                    (int) $s["expected_closing_cents"],
                    (int) $s["declared_closing_cents"],
                    (int) $s["transfer_to_safe_cents"],
                    (int) $s["difference_cents"],
                    trim($notes) ?: null,
                ],
            );
            if ($remaining === 0 && (int) $s["location_id"] > 0) {
                financial_schedule_drawer_unlock(
                    $cid,
                    (int) $s["location_id"],
                    $uid,
                    $drawerUnlockLocal,
                    trim($notes),
                );
            }
            audit("fechamento_caixa_conferido", "financeiro", $sessionId, [
                "retirada" => (int) $s["transfer_to_safe_cents"],
                "diferenca" => (int) $s["difference_cents"],
                "destravar_em" => $drawerUnlockLocal,
            ]);
        });
    
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
            !financial_closing_equation(
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
        if (financial_session_position_cents($session) !== $kept) {
            throw new RuntimeException(
                "A posição dos movimentos da Gaveta diverge do saldo mantido.",
            );
        }
        $movements = q(
            "SELECT movement_type,status,amount_cents,from_location_id,to_location_id,cash_session_id,source_entity,source_id FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND status IN ('confirmed','pending_review') ORDER BY id",
            [$cid, $sessionId],
        )->fetchAll();
        $transferTotal = 0;
        $adjustments = [];
        foreach ($movements as $movement) {
            $type = (string) ($movement["movement_type"] ?? "");
            $from = (int) ($movement["from_location_id"] ?? 0) ?: null;
            $to = (int) ($movement["to_location_id"] ?? 0) ?: null;
            financial_validate_movement_topology($type, $from, $to);
            if (
                financial_movement_delta_for_location(
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
                $transferTotal = financial_checked_add(
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
                financial_movement_delta_for_location(
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
    
        $balances = financial_location_movement_balances($cid, [$locationId]);
        return (int) ($balances[$locationId] ?? 0);
    
    }
}
