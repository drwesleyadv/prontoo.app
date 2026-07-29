<?php
declare(strict_types=1);
function financial_movement_write_guard(string $sql, array $params): void
{
    








    static $inside = false;
    if ($inside) {
        return;
    }
    $setClause = "";
    $whereClause = "";
    if (
        preg_match(
            '/^\s*UPDATE\s+`?pi_financial_movements`?\s+SET\s+(.*?)\s+WHERE\s+(.+)$/is',
            $sql,
            $match,
        )
    ) {
        $setClause = (string) ($match[1] ?? "");
        $whereClause = trim((string) ($match[2] ?? ""));
    } elseif (
        preg_match(
            '/^\s*DELETE\s+FROM\s+`?pi_financial_movements`?\s+WHERE\s+(.+)$/is',
            $sql,
            $match,
        )
    ) {
        $whereClause = trim((string) ($match[1] ?? ""));
    } else {
        return;
    }
    if ($whereClause === "") {
        throw new RuntimeException(
            "Mutação financeira sem predicado foi bloqueada.",
        );
    }
    $whereParams = array_slice($params, substr_count($setClause, "?"));
    $inside = true;
    try {
        $rows = q(
            "SELECT id,clinic_id,created_at,cash_session_id FROM pi_financial_movements WHERE " .
                $whereClause,
            $whereParams,
        )->fetchAll();
        $lockedClinics = [];
        foreach ($rows as $row) {
            $cid = (int) ($row["clinic_id"] ?? 0);
            $sessionId = (int) ($row["cash_session_id"] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            if (!isset($lockedClinics[$cid])) {
                q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                $lockedClinics[$cid] = true;
            }
            $businessDate = "";
            if ($sessionId > 0) {
                $session = one(
                    "SELECT business_date FROM pi_cash_sessions WHERE id=? AND clinic_id=? LIMIT 1",
                    [$sessionId, $cid],
                );
                $businessDate = app_date_input_from_storage(
                    $session["business_date"] ?? "",
                );
            }
            if ($businessDate === "") {
                $created = app_db_utc_to_local(
                    $row["created_at"] ?? null,
                    $cid,
                );
                $businessDate = $created ? $created->format("Y-m-d") : "";
            }
            $closing =
                $businessDate !== ""
                    ? one(
                    "SELECT id FROM pi_financial_daily_closings WHERE clinic_id=? AND business_date=? AND status='consolidado' LIMIT 1 FOR UPDATE",
                    [$cid, $businessDate],
                )
                    : null;
            if ((int) ($closing["id"] ?? 0) > 0) {
                throw new RuntimeException(
                    "Movimento de dia consolidado é imutável. Reabra o período por fluxo autorizado antes de corrigir o lançamento.",
                );
            }
        }
    } finally {
        $inside = false;
    }
}
function financial_cashier_requires_attention_light(array $c): bool
{
    








    if (($c["scope"] ?? "") !== "clinic") {
        return false;
    }
    if ((string) ($c["role"] ?? "") !== "recepcionista") {
        return false;
    }
    $cid = (int) ($c["clinic_id"] ?? 0);
    $uid = (int) ($c["user"]["id"] ?? 0);
    if ($cid <= 0 || $uid <= 0) {
        return false;
    }
    if (clinic_read_only_db($cid)) {
        return false;
    }
    try {
        $hasAssigned =
            (int) (val(
                "SELECT l.id FROM pi_financial_locations l JOIN pi_financial_location_users lu ON lu.location_id=l.id AND lu.clinic_id=l.clinic_id AND lu.user_id=? AND lu.active=1 WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,l.id LIMIT 1",
                [$uid, $cid],
            ) ?:
            0);
        if ($hasAssigned <= 0) {
            $hasAssigned =
                (int) (val(
                    "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND user_id=? AND active=1 ORDER BY id ASC LIMIT 1",
                    [$cid, $uid],
                ) ?:
                0);
        }
        if ($hasAssigned <= 0) {
            return false;
        }
        $today = app_today_in_timezone($cid);
        return (int) (val(
            "SELECT id FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date<? AND status='open' ORDER BY business_date DESC,id DESC LIMIT 1",
            [$cid, $uid, $today],
        ) ?:
            0) > 0;
    } catch (Throwable $e) {
        error_log(
            "[Prontoo financeiro caixa atenção leve] " . $e->getMessage(),
        );
        return false;
    }
}
