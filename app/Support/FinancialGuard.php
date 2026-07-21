<?php
declare(strict_types=1);
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
