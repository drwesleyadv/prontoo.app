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

final class FinancialRuntimeOperations09
{
    private function __construct()
    {
    }

    public static function financial_location_movement_balances(
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
        if ($cid <= 0 || !$locationIds) {
            return [];
        }
        $known = $requestCache[$cid] ?? [];
        $missing = array_values(
            array_filter(
                $locationIds,
                static  fn(int $id): bool => !array_key_exists($id, $known),
            ),
        );
        if ($missing) {
            $ph = implode(",", array_fill(0, count($missing), "?"));
            foreach ($missing as $locationId) {
                $known[$locationId] = 0;
            }
            $rows = q(
                "SELECT location_id,COALESCE(SUM(delta_cents),0) balance_cents FROM (SELECT to_location_id location_id,amount_cents delta_cents FROM pi_financial_movements WHERE clinic_id=? AND to_location_id IN ($ph) AND status='confirmed' UNION ALL SELECT from_location_id location_id,-amount_cents delta_cents FROM pi_financial_movements WHERE clinic_id=? AND from_location_id IN ($ph) AND status='confirmed') movement_totals GROUP BY location_id",
                array_merge([$cid], $missing, [$cid], $missing),
            )->fetchAll();
            foreach ($rows as $row) {
                $known[(int) $row["location_id"]] =
                    financial_assert_balance_cents(
                        (int) $row["balance_cents"],
                        "Saldo do local financeiro",
                    );
            }
            $requestCache[$cid] = $known;
        }
        return array_intersect_key($known, array_flip($locationIds));
    
    }

    public static function financial_pos_balance_for_user(int $cid, int $uid): int
    
    {
    
        $loc = financial_cashier_location_for_user($cid, $uid);
        return $loc > 0 ? financial_drawer_balance($cid, $loc) : 0;
    
    }

    public static function financial_global_position(int $cid): array
    
    {
    
        financial_operational_schema_ready();
        $safe = financial_ensure_admin_safe($cid, (int) ($_SESSION["uid"] ?? 0));
        $safeBalance = financial_location_movement_balance($cid, $safe);
        $pending =
            (int) (val(
                "SELECT COALESCE(SUM(transfer_to_safe_cents),0) FROM pi_cash_sessions WHERE clinic_id=? AND status='closed_pending_review'",
                [$cid],
            ) ?:
            0);
        $posRows = q(
            "SELECT l.id,l.user_id,l.name FROM pi_financial_locations l WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,l.id",
            [$cid],
        )->fetchAll();
        $posIds = array_map(
            static  fn(array $row): int => (int) $row["id"],
            $posRows,
        );
        $posSnapshot = financial_drawer_balance_snapshot($cid, $posIds);
        $posBalances = (array) ($posSnapshot["balances"] ?? []);
        $posOpen = (array) ($posSnapshot["open"] ?? []);
        $linkedByLocation = [];
        if ($posIds) {
            $posPh = implode(",", array_fill(0, count($posIds), "?"));
            foreach (
                q(
                    "SELECT location_id,COUNT(*) total FROM pi_financial_location_users WHERE clinic_id=? AND location_id IN ($posPh) AND active=1 GROUP BY location_id",
                    array_merge([$cid], $posIds),
                )->fetchAll()
                as $linkRow
            ) {
                $linkedByLocation[(int) $linkRow["location_id"]] =
                    (int) $linkRow["total"];
            }
        }
        $pos = [];
        $posTotal = 0;
        foreach ($posRows as $r) {
            $locationId = (int) $r["id"];
            $bal = (int) ($posBalances[$locationId] ?? 0);
            $open = $posOpen[$locationId] ?? null;
            $links = (int) ($linkedByLocation[$locationId] ?? 0);
            $r["balance_cents"] = $bal;
            $r["open_user_name"] = $open ? (string) ($open["user_name"] ?? "") : "";
            $r["linked_users"] = $links;
            $posTotal = financial_checked_add(
                $posTotal,
                $bal,
                "Total das Gavetas",
            );
            $pos[] = $r;
        }
        $bankRows = q(
            "SELECT l.id,l.name,l.account_id,a.bank_name,a.account_type FROM pi_financial_locations l LEFT JOIN pi_financial_accounts a ON a.id=l.account_id AND a.clinic_id=l.clinic_id WHERE l.clinic_id=? AND l.location_type='bank_account' AND l.active=1 ORDER BY l.name",
            [$cid],
        )->fetchAll();
        $bankBalances = financial_location_movement_balances(
            $cid,
            array_map(static  fn(array $row): int => (int) $row["id"], $bankRows),
        );
        $banks = [];
        $bankTotal = 0;
        foreach ($bankRows as $r) {
            $bal = (int) ($bankBalances[(int) $r["id"]] ?? 0);
            $r["balance_cents"] = $bal;
            $bankTotal = financial_checked_add(
                $bankTotal,
                $bal,
                "Total dos Bancos",
            );
            $banks[] = $r;
        }
        return [
            "safe_id" => $safe,
            "safe_cents" => $safeBalance,
            "pending_cents" => $pending,
            "pos_rows" => $pos,
            "pos_cents" => $posTotal,
            "bank_rows" => $banks,
            "bank_cents" => $bankTotal,
            "total_cents" => financial_checked_add(
                financial_checked_add(
                    $safeBalance,
                    $posTotal,
                    "Posição financeira global",
                ),
                $bankTotal,
                "Posição financeira global",
            ),
        ];
    
    }

    public static function financial_cashier_requires_attention(array $c): bool
    
    {
    
        if (!financial_is_cashier($c)) {
            return false;
        }
        if (clinic_read_only_db((int) $c["clinic_id"])) {
            return false;
        }
        try {
            financial_operational_schema_ready();
            $cid = (int) $c["clinic_id"];
            $uid = (int) $c["user"]["id"];
            $today = financial_today($cid);
            if (financial_cashier_location_for_user($cid, $uid) <= 0) {
                return false;
            }
            return (bool) financial_unclosed_previous_session($cid, $uid, $today);
        } catch (Throwable $e) {
            error_log("[Prontoo financeiro caixa atenção] " . $e->getMessage());
            return false;
        }
    
    }

    public static function financial_register_appointment_payment_movement(
        int $cid,
        int $appointmentId,
        int $userId,
        int $amount,
        string $method,
        string $title,
        bool $paid,
        int $paymentDestinationLocationId = 0,
    ): void 
    {
    
        financial_operational_schema_ready();
        $existing = one(
            "SELECT id,status FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' ORDER BY id DESC LIMIT 1",
            [$cid, $appointmentId],
        );
        if (!$paid) {
            if ($existing) {
                q(
                    "UPDATE pi_financial_movements SET status='cancelled', confirmed_by=NULL, confirmed_at=NULL, notes=CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='', '', ' | '), 'Pagamento desmarcado no agendamento.'), reviewed_at=NOW() WHERE id=? AND clinic_id=?",
                    [(int) $existing["id"], $cid],
                );
            }
            return;
        }
        if ($amount <= 0) {
            return;
        }
        $method = normalize_payment_method($method);
        if ($method === "") {
            throw new RuntimeException("Selecione a forma de pagamento.");
        }
        $to = 0;
        $sessionId = null;
        $movementNotes = "Recebimento vinculado ao agendamento.";
        if ($method === "dinheiro") {
            $s = financial_require_open_session($cid, $userId);
            $sessionId = (int) $s["id"];
            $to = (int) $s["location_id"];
            $movementNotes =
                "Recebimento em dinheiro vinculado ao agendamento; compõe a conferência da Gaveta aberta.";
        } else {
            if ($paymentDestinationLocationId <= 0) {
                throw new RuntimeException("Selecione o destino do recebimento.");
            }
            if (
                function_exists("financial_office_destination_belongs")
                    ? !financial_office_destination_belongs(
                        $cid,
                        $paymentDestinationLocationId,
                    )
                    : !financial_location_belongs(
                        $cid,
                        $paymentDestinationLocationId,
                    )
            ) {
                throw new RuntimeException(
                    "Selecione um destino financeiro válido para este consultório.",
                );
            }
            $to = $paymentDestinationLocationId;
            $movementNotes =
                "Recebimento sem dinheiro físico vinculado ao agendamento; creditado no destino selecionado.";
        }
        if ($to <= 0) {
            throw new RuntimeException(
                "Não foi possível identificar o destino financeiro do recebimento.",
            );
        }
        if ($existing) {
            financial_update_existing_movement(
                $cid,
                (int) $existing["id"],
                "receipt",
                $amount,
                null,
                $to,
                $sessionId,
                $userId,
                $title,
                $method,
                $movementNotes,
                "confirmed",
            );
        } else {
            financial_create_movement(
                $cid,
                "receipt",
                $amount,
                null,
                $to,
                $sessionId,
                $userId,
                $title,
                $method,
                $movementNotes,
                "confirmed",
                "appointment",
                $appointmentId,
            );
        }
    
    }

    public static function financial_revenue_id_for_appointment(
        int $cid,
        int $appointmentId,
        int $uid = 0,
    ): int 
    {
    
        if ($cid <= 0 || $appointmentId <= 0) {
            return 0;
        }
        financial_operational_schema_ready();
        $rid =
            (int) (val(
                "SELECT id FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=? LIMIT 1",
                [$cid, $appointmentId],
            ) ?:
            0);
        if ($rid > 0) {
            return $rid;
        }
        try {
            financial_sync_appointment($cid, $appointmentId, $uid);
        } catch (Throwable $e) {
            error_log("[Prontoo financial revenue sync link] " . $e->getMessage());
        }
        return (int) (val(
            "SELECT id FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=? LIMIT 1",
            [$cid, $appointmentId],
        ) ?:
        0);
    
    }

    public static function financial_appointment_operational_chip_html(
        int $cid,
        array $a,
        string $role = "",
    ): string 
    {
    
        $st = financial_appointment_payment_state($a);
        $amount = (int) ($st["amount"] ?? 0);
        $title = $amount > 0 ? money_br($amount) : "Sem valor financeiro";
        $method = (string) ($st["method"] ?? "");
        $methodLabel =
            $method !== "" ? payment_methods_options()[$method] ?? $method : "";
        $chip =
            '<span class="finance-appointment-chip finance-appointment-chip-' .
            e((string) $st["code"]) .
            " pill " .
            e((string) $st["class"]) .
            '" title="' .
            e($title . ($methodLabel !== "" ? " · " . $methodLabel : "")) .
            '">' .
            icon((string) $st["icon"]) .
            "<span>" .
            e((string) $st["label"]) .
            "</span>" .
            ($amount > 0 ? "<b>" . e(money_br($amount)) . "</b>" : "") .
            "</span>";
        $canReceive =
            function_exists("appointment_journey_role_matches") &&
            (appointment_journey_role_matches($role, "recepcionista") ||
                appointment_journey_role_matches($role, "gerente"));
        if (
            $canReceive &&
            (string) $st["code"] === "aguardando_pagamento" &&
            (int) ($a["id"] ?? 0) > 0
        ) {
            $chip .=
                '<a class="finance-appointment-receive small primary" href="' .
                href("financial", [
                    "op" => "receber",
                    "appointment_id" => (int) $a["id"],
                ]) .
                '">' .
                icon("point_of_sale") .
                "<span>Receber</span></a>";
        }
        return '<span class="finance-appointment-inline">' . $chip . "</span>";
    
    }

    public static function financial_daily_drawer_closure_state(
        int $cid,
        string $businessDate = "",
    ): array 
    {
    
        financial_operational_schema_ready();
        $businessDate = $businessDate ?: financial_today($cid);
        $expected = [];
        $linked = q(
            "SELECT l.id location_id,COALESCE(l.name,'Gaveta') drawer_name,u.id user_id,COALESCE(u.name,'Colaborador') user_name FROM pi_financial_locations l JOIN pi_financial_location_users lu ON lu.location_id=l.id AND lu.clinic_id=l.clinic_id AND lu.active=1 JOIN pi_users u ON u.id=lu.user_id AND u.active=1 WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,u.name",
            [$cid],
        )->fetchAll();
        foreach ($linked as $r) {
            $key = (int) $r["location_id"] . ":" . (int) $r["user_id"];
            $expected[$key] = $r;
        }
        $sessions = q(
            "SELECT s.id,s.status,s.opened_at,s.closed_at,s.business_date,s.location_id,s.user_id,s.opening_balance_cents,s.expected_closing_cents,s.declared_closing_cents,s.keep_in_drawer_cents,s.transfer_to_safe_cents,s.difference_cents,COALESCE(l.name,'Gaveta') drawer_name,COALESCE(u.name,'Colaborador') user_name FROM pi_cash_sessions s LEFT JOIN pi_financial_locations l ON l.id=s.location_id AND l.clinic_id=s.clinic_id LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.business_date=? ORDER BY COALESCE(l.name,''),COALESCE(u.name,''),s.id",
            [$cid, $businessDate],
        )->fetchAll();
        $sessionByKey = [];
        foreach ($sessions as $srow) {
            $key = (int) $srow["location_id"] . ":" . (int) $srow["user_id"];
            $sessionByKey[$key] = $srow;
            if (!isset($expected[$key])) {
                $expected[$key] = [
                    "location_id" => (int) $srow["location_id"],
                    "drawer_name" => (string) ($srow["drawer_name"] ?? "Gaveta"),
                    "user_id" => (int) $srow["user_id"],
                    "user_name" => (string) ($srow["user_name"] ?? "Colaborador"),
                ];
            }
        }
        $rows = [];
        $blocking = [];
        $closedStatuses = [
            "closed_pending_review",
            "approved",
            "rejected",
            "kept_closed",
        ];
        foreach ($expected as $key => $base) {
            $r = $sessionByKey[$key] ?? [];
            $status = $r ? (string) ($r["status"] ?? "") : "not_started";
            $row = array_merge($base, $r);
            $row["status"] = $status;
            $row["has_session"] = $r ? 1 : 0;
            $isBlocking = !$r || !in_array($status, $closedStatuses, true);
            $row["blocking"] = $isBlocking ? 1 : 0;
            $rows[] = $row;
            if ($isBlocking) {
                $blocking[] = $row;
            }
        }
        return [
            "business_date" => $businessDate,
            "opened_count" => count($rows),
            "expected_count" => count($expected),
            "blocking_count" => count($blocking),
            "blocking_rows" => $blocking,
            "rows" => $rows,
            "all_closed" => count($blocking) === 0,
        ];
    
    }

    public static function financial_admin_daily_consolidation_html(int $cid): string
    
    {
    
        financial_operational_schema_ready();
        $today = financial_today($cid);
        [$dayStart, $dayEnd] = app_local_day_utc_range($today, $cid);
        $expected = (int) safe_val(
            "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND amount_cents>0 AND expected_at>=? AND expected_at<?",
            [$cid, $dayStart, $dayEnd],
            0,
        );
        $received = (int) safe_val(
            "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?",
            [$cid, $dayStart, $dayEnd],
            0,
        );
        $pending = (int) safe_val(
            "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND amount_cents>0 AND expected_at<?",
            [$cid, $dayEnd],
            0,
        );
        $state = financial_daily_consolidation_state($cid, $today);
        $openDrawers = (int) ($state["pending_open_drawers"] ?? 0);
        $pos = financial_global_position($cid);
        $cards =
            '<div class="finance-consolidation-kpis"><article>' .
            icon("event_available") .
            "<b>" .
            money_br($expected) .
            "</b><span>Previsto hoje</span></article><article>" .
            icon("task_alt") .
            "<b>" .
            money_br($received) .
            "</b><span>Recebido hoje</span></article><article>" .
            icon("pending_actions") .
            "<b>" .
            money_br($pending) .
            "</b><span>Pendente</span></article><article>" .
            icon("point_of_sale") .
            "<b>" .
            money_br((int) $pos["pos_cents"]) .
            "</b><span>Em gavetas</span></article></div>";
        if (!empty($state["consolidated"])) {
            $footer =
                '<footer class="finance-consolidation-footer"><button class="ghost small" type="button" disabled aria-disabled="true">' .
                icon("verified") .
                "<span>Dia consolidado</span></button></footer>";
        } elseif ($openDrawers > 0) {
            $footer =
                '<footer class="finance-consolidation-footer"><button class="ghost small" type="button" disabled aria-disabled="true">' .
                icon("point_of_sale") .
                "<span>Aguardando fechamentos</span></button></footer>";
        } else {
            $footer =
                '<footer class="finance-consolidation-footer"><a class="primary small" href="' .
                href("financial", ["tab" => "consolidacao"]) .
                '">' .
                icon("fact_check") .
                "<span>Conferência liberada</span></a></footer>";
        }
        return '<section class="finance-consolidation-panel"><header><span>' .
            icon("monitoring") .
            "</span><div><h2>Consolidação do dia</h2></div></header>" .
            $cards .
            $footer .
            "</section>";
    
    }

    public static function financial_cashier_pending_receipts_html(int $cid): string
    
    {
    
        financial_operational_schema_ready();
        $today = financial_today($cid);
        [$dayStart, $dayEnd] = app_local_day_utc_range($today, $cid);
        $rows = q(
            "SELECT r.id,r.amount_cents,r.title,a.id appointment_id,a.start_at,a.status,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.clinic_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.expected_at<? AND a.status NOT IN ('cancelado','nao_compareceu') ORDER BY COALESCE(a.start_at,r.expected_at,NOW()) ASC,r.id ASC LIMIT 8",
            [$cid, $dayEnd],
        )->fetchAll();
        if (!$rows) {
            return '<div class="empty">Nenhum atendimento com pagamento pendente até agora.</div>';
        }
        $h = '<div class="finance-reception-pending">';
        foreach ($rows as $r) {
            $patient =
                mb_trim((string) ($r["patient_name"] ?? "Paciente")) ?: "Paciente";
            $proc =
                trim(
                    (string) ($r["procedure_title"] ??
                        ($r["title"] ?? "Procedimento")),
                ) ?:
                "Procedimento";
            $h .=
                '<a href="' .
                href("financial", [
                    "op" => "receber",
                    "revenue_id" => (int) $r["id"],
                ]) .
                '"><span>' .
                icon("payments") .
                "<b>" .
                e($patient) .
                "</b></span><small>" .
                e(app_time_br((string) $r["start_at"]) . " · " . $proc) .
                "</small><em>" .
                e(money_br((int) $r["amount_cents"])) .
                "</em></a>";
        }
        return $h . "</div>";
    
    }
}
