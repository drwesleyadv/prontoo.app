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
    
        $diff = financial_checked_add(
            $informed,
            -$expected,
            "Diferença da abertura",
        );
        $notes =
            "Abertura bloqueada: Saldo Inicial informado diverge do saldo não retirado do último fechamento.";
        if ($existing && (int) ($existing["id"] ?? 0) > 0) {
            $sid = (int) $existing["id"];
            $oldStatus = (string) ($existing["status"] ?? "");
            q(
                "UPDATE pi_cash_sessions SET location_id=?, opened_at=NULL, kept_closed_at=NULL, opening_balance_cents=?, expected_closing_cents=?, declared_closing_cents=?, keep_in_drawer_cents=?, transfer_to_safe_cents=0, difference_cents=?, status='opening_pending_review', closing_notes=?, reviewed_by=NULL, reviewed_at=NULL, review_status=NULL, review_notes=NULL, updated_at=NOW() WHERE id=? AND clinic_id=? AND user_id=?",
                [
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
                ],
            );
            audit("abertura_caixa_divergente_atualizada", "financeiro", $sid, [
                "esperado" => $expected,
                "informado" => $informed,
                "diferenca" => $diff,
                "status_anterior" => $oldStatus,
                "audit_body" =>
                    "Atendimento atualizou solicitação de abertura de caixa com Saldo Inicial divergente.",
            ]);
            if ($oldStatus !== "opening_pending_review") {
                financial_notify_opening_authorization_request(
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
        q(
            "INSERT INTO pi_cash_sessions (clinic_id,user_id,location_id,business_date,opened_at,kept_closed_at,opening_balance_cents,expected_closing_cents,declared_closing_cents,keep_in_drawer_cents,transfer_to_safe_cents,difference_cents,status,closing_notes,created_at,updated_at) VALUES (?,?,?,?,NULL,NULL,?,?,?,?,0,?,'opening_pending_review',?,NOW(),NOW())",
            [
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
            ],
        );
        $sid = db_last_insert_id();
        audit("abertura_caixa_divergente_solicitada", "financeiro", $sid, [
            "esperado" => $expected,
            "informado" => $informed,
            "diferenca" => $diff,
            "audit_body" =>
                "Atendimento solicitou abertura de caixa com Saldo Inicial divergente do saldo não retirado anterior.",
        ]);
        financial_notify_opening_authorization_request(
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
    
        $today = $today ?: financial_today($cid);
        financial_operational_schema_ready();
        return one(
            "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date<? AND status='open' ORDER BY business_date DESC,id DESC LIMIT 1",
            [$cid, $uid, $today],
        );
    
    }

    public static function financial_keep_closed(int $cid, int $uid): int
    
    {
    
        financial_operational_schema_ready();
        if ($cid <= 0 || $uid <= 0) {
            throw new RuntimeException("Usuário ou consultório inválido.");
        }
        $today = financial_today($cid);
        return (int) db_tx(function () use ($cid, $uid, $today) {
    
            q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
            $loc = financial_cashier_location_for_user($cid, $uid);
            if ($loc <= 0) {
                throw new RuntimeException(
                    "Você não é responsável por nenhuma gaveta ainda. Aguarde até que receba autorização para gerenciar gavetas.",
                );
            }
            financial_drawer_guard_can_use($cid, $loc);
            $other = financial_drawer_open_session($cid, $loc, $uid);
            if ($other) {
                throw new RuntimeException(
                    "Esta Gaveta já está aberta por " .
                        first_name(
                            (string) ($other["user_name"] ?? "outro colaborador"),
                        ) .
                        ". Aguarde o fechamento antes de abrir ou manter fechado.",
                );
            }
            $pending = financial_drawer_pending_previous_review($cid, $loc, $today);
            if ($pending) {
                throw new RuntimeException(
                    "Esta Gaveta possui fechamento anterior aguardando conferência da Gerência. Aguarde o destravamento para usá-la.",
                );
            }
            $prev = financial_unclosed_previous_session($cid, $uid, $today);
            if ($prev) {
                throw new RuntimeException(
                    "Há uma Gaveta de caixa anterior sem fechamento. Feche a sessão pendente antes de manter o caixa fechado hoje.",
                );
            }
            $exists = financial_session_for_date($cid, $uid, $today);
            if ($exists) {
                if ((string) $exists["status"] === "kept_closed") {
                    return (int) $exists["id"];
                }
                if ((string) $exists["status"] === "open") {
                    throw new RuntimeException("O caixa de hoje já está aberto.");
                }
                throw new RuntimeException(
                    "O caixa de hoje já foi fechado ou está em conferência.",
                );
            }
            $balance = financial_drawer_previous_balance($cid, $loc, $today);
            q(
                "INSERT INTO pi_cash_sessions (clinic_id,user_id,location_id,business_date,opened_at,kept_closed_at,opening_balance_cents,closed_at,expected_closing_cents,declared_closing_cents,keep_in_drawer_cents,transfer_to_safe_cents,difference_cents,status,closing_notes,created_at,updated_at) VALUES (?,?,?,?,NULL,NOW(),?,NULL,?,?,?,0,0,'kept_closed',?,NOW(),NOW())",
                [
                    $cid,
                    $uid,
                    $loc,
                    $today,
                    $balance,
                    $balance,
                    $balance,
                    $balance,
                    "Gaveta mantida fechada pelo Atendimento; saldo físico preservado na Gaveta.",
                ],
            );
            $sid = db_last_insert_id();
            audit("caixa_atendimento_mantido_fechado", "financeiro", $sid, [
                "gaveta" => $loc,
                "audit_body" =>
                    "Atendimento optou por manter a Gaveta fechada no dia, preservando o saldo físico.",
            ]);
            return $sid;
        });
    
    }

    public static function financial_open_session(int $cid, int $uid, int $openingBalance): int
    
    {
    
        financial_operational_schema_ready();
        if ($cid <= 0 || $uid <= 0) {
            throw new RuntimeException("Usuário ou consultório inválido.");
        }
        $today = financial_today($cid);
        $authorizationRequested = false;
        $authorizationMessage = "";
        $result = (int) db_tx(function () use (
            $cid,
            $uid,
            $openingBalance,
            $today,
            &$authorizationRequested,
            &$authorizationMessage,
        ) {
    
            q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
            $loc = financial_cashier_location_for_user($cid, $uid);
            if ($loc <= 0) {
                throw new RuntimeException(
                    "Você não é responsável por nenhuma gaveta ainda. Aguarde até que receba autorização para gerenciar gavetas.",
                );
            }
            financial_drawer_guard_can_use($cid, $loc);
            $other = financial_drawer_open_session($cid, $loc, $uid);
            if ($other) {
                throw new RuntimeException(
                    "Esta Gaveta já está aberta por " .
                        first_name(
                            (string) ($other["user_name"] ?? "outro colaborador"),
                        ) .
                        ". Aguarde o fechamento antes de abrir a Gaveta.",
                );
            }
            $pending = financial_drawer_pending_previous_review($cid, $loc, $today);
            if ($pending) {
                throw new RuntimeException(
                    "Esta Gaveta possui fechamento anterior aguardando conferência da Gerência. Aguarde o destravamento para usá-la.",
                );
            }
            $prev = financial_unclosed_previous_session($cid, $uid, $today);
            if ($prev) {
                throw new RuntimeException(
                    "Há uma Gaveta de caixa anterior sem fechamento. Feche a sessão pendente antes de abrir uma nova.",
                );
            }
            $exists = financial_session_for_date($cid, $uid, $today);
            $status = $exists ? (string) $exists["status"] : "";
            if ($exists && $status === "open") {
                throw new RuntimeException(
                    "A sessão de caixa de hoje já foi aberta.",
                );
            }
            if (
                $exists &&
                in_array(
                    $status,
                    ["closed_pending_review", "approved", "rejected"],
                    true,
                )
            ) {
                throw new RuntimeException(
                    "O caixa de hoje já foi fechado ou está em conferência.",
                );
            }
            $openingBalance = financial_assert_amount_cents(
                max(0, $openingBalance),
                "Saldo inicial",
            );
            $expected = financial_expected_opening_balance(
                $cid,
                $uid,
                $today,
                $loc,
                $exists ? (int) $exists["id"] : 0,
            );
            if ($exists && $status === "kept_closed") {
                $expected = (int) ($exists["opening_balance_cents"] ?? $expected);
            }
            if ($openingBalance !== $expected) {
                $sid = financial_request_opening_authorization(
                    $cid,
                    $uid,
                    $loc,
                    $today,
                    $expected,
                    $openingBalance,
                    $exists ?: null,
                );
                $authorizationRequested = true;
                $authorizationMessage =
                    "O Saldo Inicial informado não coincide com o valor esperado para esta Gaveta. O Administrativo recebeu aviso para autorizar a abertura com saldo diferente.";
                return $sid;
            }
            if ($exists) {
                if (
                    in_array(
                        $status,
                        [
                            "kept_closed",
                            "opening_pending_review",
                            "opening_rejected",
                        ],
                        true,
                    )
                ) {
                    q(
                        "UPDATE pi_cash_sessions SET location_id=?, opened_at=NOW(), kept_closed_at=NULL, opening_balance_cents=?, closed_at=NULL, expected_closing_cents=0, declared_closing_cents=0, keep_in_drawer_cents=0, transfer_to_safe_cents=0, difference_cents=0, status='open', closing_notes=NULL, reviewed_by=NULL, reviewed_at=NULL, review_status=NULL, review_notes=NULL, updated_at=NOW() WHERE id=? AND clinic_id=? AND user_id=?",
                        [$loc, $openingBalance, (int) $exists["id"], $cid, $uid],
                    );
                    audit(
                        "caixa_atendimento_aberto",
                        "financeiro",
                        (int) $exists["id"],
                        [
                            "gaveta" => $loc,
                            "saldo_inicial" => $openingBalance,
                            "audit_body" =>
                                "Gaveta aberta pelo Atendimento com valor inicial coincidente.",
                        ],
                    );
                    return (int) $exists["id"];
                }
                throw new RuntimeException(
                    "A situação atual do caixa não permite abertura.",
                );
            }
            q(
                "INSERT INTO pi_cash_sessions (clinic_id,user_id,location_id,business_date,opened_at,opening_balance_cents,status,created_at,updated_at) VALUES (?,?,?,?,NOW(),?,'open',NOW(),NOW())",
                [$cid, $uid, $loc, $today, $openingBalance],
            );
            $sid = db_last_insert_id();
            audit("caixa_atendimento_aberto", "financeiro", $sid, [
                "gaveta" => $loc,
                "saldo_inicial" => $openingBalance,
                "audit_body" => "Gaveta aberta pelo Atendimento.",
            ]);
            return $sid;
        });
        if ($authorizationRequested) {
            throw new RuntimeException($authorizationMessage);
        }
        return $result;
    
    }

    public static function financial_session_movement_totals(int $cid, int $sessionId): array
    
    {
    
        financial_operational_schema_ready();
        $rows = q(
            "SELECT movement_type,COALESCE(SUM(amount_cents),0) total FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND status IN ('confirmed','pending_review') GROUP BY movement_type",
            [$cid, $sessionId],
        )->fetchAll();
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
    
        return financial_session_position_cents($session);
    
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
        return financial_create_movement(
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
        $existing = (int) (val(
            "SELECT COUNT(*) FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND movement_type='adjustment' AND source_entity='cash_closing_adjustment' AND source_id=? AND status IN ('confirmed','pending_review')",
            [
                (int) $session["clinic_id"],
                (int) $session["id"],
                (int) $session["id"],
            ],
        ) ?:
            0);
        if ($existing === 0) {
            financial_record_cash_difference(
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
    
        financial_operational_schema_ready();
        return one(
            "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date=? AND status='open' LIMIT 1",
            [$cid, $uid, financial_today($cid)],
        );
    
    }

    public static function financial_require_open_session(int $cid, int $uid): array
    
    {
    
        $s = financial_current_open_session($cid, $uid);
        if (!$s) {
            $today = financial_session_for_date($cid, $uid, financial_today($cid));
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
