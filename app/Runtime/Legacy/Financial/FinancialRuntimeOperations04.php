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

final class FinancialRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function financial_drawer_lock_after_close(
        int $cid,
        int $drawerId,
        string $businessDate,
        int $sessionId,
        int $uid,
    ): void 
    {
    
        if ($cid <= 0 || $drawerId <= 0) {
            return;
        }
        $open = financial_drawer_open_session($cid, $drawerId, 0);
        if ($open) {
            return;
        }
        $current = financial_drawer_row($cid, $drawerId);
        if (
            !$current ||
            (string) ($current["drawer_lock_status"] ?? "unlocked") === "locked"
        ) {
            return;
        }
        $linked =
            (int) (val(
                "SELECT COUNT(*) FROM pi_financial_location_users WHERE clinic_id=? AND location_id=? AND active=1",
                [$cid, $drawerId],
            ) ?:
            0);
        if ($linked <= 0 && (int) ($current["user_id"] ?? 0) > 0) {
            $linked = 1;
        }
        if ($linked <= 0) {
            $linked = 1;
        }
        $done =
            (int) (val(
                "SELECT COUNT(DISTINCT user_id) FROM pi_cash_sessions WHERE clinic_id=? AND location_id=? AND business_date=? AND status IN ('closed_pending_review','approved','rejected','kept_closed')",
                [$cid, $drawerId, $businessDate],
            ) ?:
            0);
        if ($done < $linked) {
            audit(
                "gaveta_permanece_destrancada_turnos_pendentes",
                "financeiro",
                $drawerId,
                [
                    "cash_session_id" => $sessionId,
                    "business_date" => $businessDate,
                    "colaboradores_vinculados" => $linked,
                    "colaboradores_com_sessao_finalizada" => $done,
                    "audit_body" =>
                        "Gaveta permaneceu disponível porque ainda há colaboradores vinculados sem fechamento no dia.",
                ],
            );
            return;
        }
        q(
            "UPDATE pi_financial_locations SET drawer_lock_status='locked', drawer_locked_business_date=?, drawer_locked_at=NOW(), drawer_unlock_at=NULL, drawer_unlocked_at=NULL, drawer_reviewed_by=NULL, drawer_reviewed_at=NULL, drawer_review_notes=NULL, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
            [$businessDate, $drawerId, $cid],
        );
        audit("gaveta_trancada_para_conferencia", "financeiro", $drawerId, [
            "cash_session_id" => $sessionId,
            "business_date" => $businessDate,
            "colaboradores_vinculados" => $linked,
            "audit_body" =>
                "Gaveta trancada automaticamente após o último colaborador vinculado finalizar o Caixa do dia.",
        ]);
        financial_notify_drawer_locked($cid, $drawerId, $sessionId, $uid);
    
    }

    public static function financial_default_drawer_unlock_local(
        int $cid,
        int $drawerId,
        ?array $drawer = null,
    ): string
    
    {
    
        $d = $drawer ?: financial_drawer_row($cid, $drawerId);
        $locked =
            (string) ($d["drawer_locked_business_date"] ?? financial_today($cid));
        try {
            $dt = new DateTimeImmutable(
                $locked . " 08:00:00",
                new DateTimeZone(app_context_timezone(null, $cid)),
            )->modify("+1 day");
        } catch (Throwable $e) {
            $dt = app_now_in_timezone($cid)->modify("+1 day")->setTime(8, 0);
        }
        return $dt->format("Y-m-d\TH:i");
    
    }

    public static function financial_schedule_drawer_unlock(
        int $cid,
        int $drawerId,
        int $adminUid,
        string $unlockLocal,
        string $notes = "",
    ): void 
    {
    
        $d = financial_drawer_row($cid, $drawerId);
        if (!$d) {
            throw new RuntimeException("Gaveta inválida.");
        }
        if ((string) ($d["drawer_lock_status"] ?? "unlocked") !== "locked") {
            throw new RuntimeException(
                "Esta Gaveta não está trancada para conferência.",
            );
        }
        $lockedDay = (string) ($d["drawer_locked_business_date"] ?? "");
        $unlockLocal = trim($unlockLocal);
        if ($unlockLocal === "") {
            throw new RuntimeException(
                "Informe o horário em que a Gaveta será destrancada.",
            );
        }
        try {
            $tz = new DateTimeZone(app_context_timezone(null, $cid));
            $localDt = new DateTimeImmutable($unlockLocal, $tz);
            if ($lockedDay !== "" && $localDt->format("Y-m-d") <= $lockedDay) {
                throw new RuntimeException(
                    "O destravamento deve ser agendado para o dia seguinte ao fechamento, no mínimo.",
                );
            }
            $unlockUtc = $localDt
                ->setTimezone(new DateTimeZone("UTC"))
                ->format("Y-m-d H:i:s");
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Informe um horário de destravamento válido.",
            );
        }
        q(
            "UPDATE pi_financial_locations SET drawer_unlock_at=?, drawer_reviewed_by=?, drawer_reviewed_at=NOW(), drawer_review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
            [$unlockUtc, $adminUid, trim($notes) ?: null, $drawerId, $cid],
        );
        audit("gaveta_destravamento_agendado", "financeiro", $drawerId, [
            "unlock_at" => $unlockUtc,
            "locked_business_date" => $lockedDay,
            "observacao" => trim($notes),
            "audit_body" => "Gerência conferiu a Gaveta e agendou o destravamento.",
        ]);
    
    }

    public static function financial_drawer_open_session(
        int $cid,
        int $locationId,
        int $excludeUid = 0,
    ): ?array 
    {
    
        if ($cid <= 0 || $locationId <= 0) {
            return null;
        }
        $params = [$cid, $locationId];
        $sql =
            "SELECT s.*,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id=? AND s.status='open'";
        if ($excludeUid > 0) {
            $sql .= " AND s.user_id<>?";
            $params[] = $excludeUid;
        }
        $sql .= " ORDER BY s.opened_at DESC,s.id DESC LIMIT 1";
        return one($sql, $params);
    
    }

    public static function financial_latest_drawer_session(
        int $cid,
        int $locationId,
        string $maxDate = "",
        int $ignoreSessionId = 0,
    ): ?array 
    {
    
        if ($cid <= 0 || $locationId <= 0) {
            return null;
        }
        $params = [$cid, $locationId];
        $sql =
            "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND location_id=? AND status IN ('closed_pending_review','approved','kept_closed')";
        if ($maxDate !== "") {
            $sql .= " AND business_date<=?";
            $params[] = $maxDate;
        }
        if ($ignoreSessionId > 0) {
            $sql .= " AND id<>?";
            $params[] = $ignoreSessionId;
        }
        $sql .=
            " ORDER BY business_date DESC, COALESCE(closed_at,kept_closed_at,created_at) DESC, id DESC LIMIT 1";
        return one($sql, $params);
    
    }

    public static function financial_drawer_previous_balance(
        int $cid,
        int $locationId,
        string $businessDate = "",
        int $ignoreSessionId = 0,
    ): int 
    {
    
        $businessDate = $businessDate ?: financial_today($cid);
        $last = financial_latest_drawer_session(
            $cid,
            $locationId,
            $businessDate,
            $ignoreSessionId,
        );
        if (!$last) {
            return 0;
        }
        return (int) ($last["keep_in_drawer_cents"] ?? 0);
    
    }

    public static function financial_drawer_pending_previous_review(
        int $cid,
        int $locationId,
        string $today = "",
    ): ?array 
    {
    
        $today = $today ?: financial_today($cid);
        if ($cid <= 0 || $locationId <= 0) {
            return null;
        }
        return one(
            "SELECT s.*,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id=? AND s.business_date<? AND s.status IN ('closed_pending_review','rejected') ORDER BY s.business_date DESC,s.id DESC LIMIT 1",
            [$cid, $locationId, $today],
        );
    
    }

    public static function financial_drawer_balance(int $cid, int $locationId): int
    
    {
    
        $open = financial_drawer_open_session($cid, $locationId, 0);
        if ($open) {
            return financial_session_expected($open);
        }
        $last = financial_latest_drawer_session($cid, $locationId, "", 0);
        return $last ? (int) ($last["keep_in_drawer_cents"] ?? 0) : 0;
    
    }

    public static function financial_drawer_balance_snapshot(
        int $cid,
        array $locationIds,
    ): array 
    {
    
        static $requestCache = [];
        $locationIds = array_values(
            array_unique(
                array_filter(
                    array_map("intval", $locationIds),
                    static  fn(int $id): bool => $id > 0,
                ),
            ),
        );
        $balances = array_fill_keys($locationIds, 0);
        $openByLocation = [];
        if ($cid <= 0 || !$locationIds) {
            return ["balances" => $balances, "open" => $openByLocation];
        }
        sort($locationIds, SORT_NUMERIC);
        $requestKey = $cid . ":" . implode(",", $locationIds);
        if (isset($requestCache[$requestKey])) {
            return $requestCache[$requestKey];
        }
        $locationPh = implode(",", array_fill(0, count($locationIds), "?"));
        $openRows = q(
            "SELECT s.id,s.location_id,s.opening_balance_cents,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id IN ($locationPh) AND s.status='open' ORDER BY s.location_id,s.opened_at DESC,s.id DESC",
            array_merge([$cid], $locationIds),
        )->fetchAll();
        $sessionToLocation = [];
        foreach ($openRows as $open) {
            $locationId = (int) ($open["location_id"] ?? 0);
            if ($locationId <= 0 || isset($openByLocation[$locationId])) {
                continue;
            }
            $openByLocation[$locationId] = $open;
            $sessionToLocation[(int) $open["id"]] = $locationId;
            $balances[$locationId] = (int) $open["opening_balance_cents"];
        }
        if ($sessionToLocation) {
            $sessionIds = array_keys($sessionToLocation);
            $sessionPh = implode(",", array_fill(0, count($sessionIds), "?"));
            $movementRows = q(
                "SELECT cash_session_id,from_location_id,to_location_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id IN ($sessionPh) AND status IN ('confirmed','pending_review') GROUP BY cash_session_id,from_location_id,to_location_id",
                array_merge([$cid], $sessionIds),
            )->fetchAll();
            foreach ($movementRows as $movement) {
                $locationId =
                    $sessionToLocation[(int) ($movement["cash_session_id"] ?? 0)] ??
                    0;
                if ($locationId <= 0) {
                    continue;
                }
                $amount = (int) ($movement["total"] ?? 0);
                $balances[$locationId] = financial_checked_add(
                    (int) $balances[$locationId],
                    financial_movement_delta_for_location(
                        $amount,
                        (int) ($movement["from_location_id"] ?? 0) ?: null,
                        (int) ($movement["to_location_id"] ?? 0) ?: null,
                        $locationId,
                    ),
                    "Saldo da Gaveta",
                );
            }
        }
        $closedLocationIds = array_values(
            array_diff($locationIds, array_keys($openByLocation)),
        );
        if ($closedLocationIds) {
            $closedPh = implode(",", array_fill(0, count($closedLocationIds), "?"));
            $closedRows = q(
                "SELECT location_id,keep_in_drawer_cents FROM (SELECT location_id,keep_in_drawer_cents,ROW_NUMBER() OVER (PARTITION BY location_id ORDER BY business_date DESC,COALESCE(closed_at,kept_closed_at,created_at) DESC,id DESC) row_rank FROM pi_cash_sessions WHERE clinic_id=? AND location_id IN ($closedPh) AND status IN ('closed_pending_review','approved','kept_closed')) ranked WHERE row_rank=1",
                array_merge([$cid], $closedLocationIds),
            )->fetchAll();
            foreach ($closedRows as $closed) {
                $balances[(int) $closed["location_id"]] =
                    (int) $closed["keep_in_drawer_cents"];
            }
        }
        return $requestCache[$requestKey] = [
            "balances" => $balances,
            "open" => $openByLocation,
        ];
    
    }

    public static function financial_drawer_daily_totals(
        int $cid,
        int $locationId,
        string $date,
    ): array 
    {
    
        $map = financial_drawer_daily_totals_map($cid, [$locationId], [$date]);
        return $map[$locationId . "|" . $date] ??
            financial_drawer_daily_totals_empty();
    
    }

    public static function financial_drawer_daily_totals_map(
        int $cid,
        array $locationIds,
        array $dates,
    ): array 
    {
    
        $locationIds = array_values(
            array_unique(
                array_filter(
                    array_map("intval", $locationIds),
                    static  fn(int $id): bool => $id > 0,
                ),
            ),
        );
        $dates = array_values(
            array_unique(
                array_filter(
                    array_map("strval", $dates),
                    static  fn(string $date): bool =>
                        preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1,
                ),
            ),
        );
        if ($cid <= 0 || !$locationIds || !$dates) {
            return [];
        }
        $out = [];
        foreach ($locationIds as $locationId) {
            foreach ($dates as $date) {
                $out[$locationId . "|" . $date] =
                    financial_drawer_daily_totals_empty();
            }
        }
        $locationPh = implode(",", array_fill(0, count($locationIds), "?"));
        $datePh = implode(",", array_fill(0, count($dates), "?"));
        $storageDates = array_map(
            static  fn(string $date): int =>
                \Prontoo\Core\Temporal\PiTime::dateOnlyToTimestamp($date),
            $dates,
        );
        $params = array_merge([$cid], $locationIds, $storageDates);
        $sessions = q(
            "SELECT id,location_id,business_date,opening_balance_cents,keep_in_drawer_cents,transfer_to_safe_cents,declared_closing_cents,status FROM pi_cash_sessions WHERE clinic_id=? AND location_id IN ($locationPh) AND business_date IN ($datePh) ORDER BY location_id,business_date,id ASC",
            $params,
        )->fetchAll();
        foreach ($sessions as $session) {
            $key =
                (int) ($session["location_id"] ?? 0) .
                "|" .
                app_date_input_from_storage($session["business_date"] ?? "");
            if (!isset($out[$key])) {
                continue;
            }
            if ((int) $out[$key]["sessions"] === 0) {
                $out[$key]["opening"] = (int) $session["opening_balance_cents"];
            }
            $out[$key]["sessions"]++;
            $out[$key]["kept"] = (int) $session["keep_in_drawer_cents"];
            $out[$key]["withdrawn"] +=
                (int) $session["transfer_to_safe_cents"];
            $out[$key]["declared"] +=
                (int) $session["declared_closing_cents"];
            if ((string) $session["status"] === "open") {
                $out[$key]["open_count"]++;
            }
            if ((string) $session["status"] === "closed_pending_review") {
                $out[$key]["pending_count"]++;
            }
        }
        $movements = q(
            "SELECT s.location_id,s.business_date,m.movement_type,COALESCE(SUM(m.amount_cents),0) total FROM pi_financial_movements m JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND s.location_id IN ($locationPh) AND s.business_date IN ($datePh) AND m.status IN ('confirmed','pending_review') GROUP BY s.location_id,s.business_date,m.movement_type",
            $params,
        )->fetchAll();
        $movementKeys = [
            "receipt" => "receipts",
            "payment" => "payments",
            "transfer" => "transfers",
        ];
        foreach ($movements as $movement) {
            $key =
                (int) ($movement["location_id"] ?? 0) .
                "|" .
                app_date_input_from_storage($movement["business_date"] ?? "");
            $metric = $movementKeys[(string) ($movement["movement_type"] ?? "")] ??
                null;
            if ($metric !== null && isset($out[$key])) {
                $out[$key][$metric] = (int) $movement["total"];
            }
        }
        return $out;
    
    }

    public static function financial_location_belongs(int $cid, int $locationId): bool
    
    {
    
        return $locationId > 0 &&
            (int) (val(
                "SELECT id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
                [$locationId, $cid],
            ) ?:
                0) > 0;
    
    }

    public static function financial_admin_location_belongs(int $cid, int $locationId): bool
    
    {
    
        if ($locationId <= 0) {
            return false;
        }
        return (int) (val(
            "SELECT id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') LIMIT 1",
            [$locationId, $cid],
        ) ?:
            0) > 0;
    
    }

    public static function financial_admin_location_select_options(int $cid): array
    
    {
    
        financial_operational_schema_ready();
        $rows = q(
            "SELECT id,name,location_type FROM pi_financial_locations WHERE clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') ORDER BY FIELD(location_type,'admin_safe','bank_account'), name,id",
            [$cid],
        )->fetchAll();
        $out = ["" => "Selecione"];
        foreach ($rows as $r) {
            $label =
                (string) ($r["location_type"] ?? "") === "bank_account"
                    ? "Banco"
                    : "Cofre";
            $out[(int) $r["id"]] = $label . " · " . (string) $r["name"];
        }
        return $out;
    
    }

    public static function financial_day_is_consolidated(
        int $cid,
        string $businessDate = "",
    ): bool 
    {
    
        try {
            financial_daily_closing_ensure_schema();
            $businessDate = $businessDate ?: financial_today($cid);
            return (int) (val(
                "SELECT id FROM pi_financial_daily_closings WHERE clinic_id=? AND business_date=? AND status='consolidado' LIMIT 1",
                [$cid, $businessDate],
            ) ?:
                0) > 0;
        } catch (Throwable $e) {
            error_log("[Prontoo daily closing check] " . $e->getMessage());
            throw $e;
        }
    
    }
}
