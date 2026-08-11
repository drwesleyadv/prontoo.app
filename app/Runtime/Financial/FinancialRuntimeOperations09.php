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
            $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.09.location_movement_balances.01", array_merge([$cid], $missing, [$cid], $missing), compact('ph'))->fetchAll();
            foreach ($rows as $row) {
                $known[(int) $row["location_id"]] =
                    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_balance_cents(
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
    
        $loc = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_location_for_user($cid, $uid);
        return $loc > 0 ? \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_balance($cid, $loc) : 0;
    
    }

    public static function financial_global_position(int $cid): array
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $safe = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_admin_safe($cid, (int) ($_SESSION["uid"] ?? 0));
        $safeBalance = \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_location_movement_balance($cid, $safe);
        $pending =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.09.global_position.01", [$cid], []) ?:
            0);
        $posRows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.09.global_position.02", [$cid], [])->fetchAll();
        $posIds = array_map(
            static  fn(array $row): int => (int) $row["id"],
            $posRows,
        );
        $posSnapshot = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_balance_snapshot($cid, $posIds);
        $posBalances = (array) ($posSnapshot["balances"] ?? []);
        $posOpen = (array) ($posSnapshot["open"] ?? []);
        $linkedByLocation = [];
        if ($posIds) {
            $posPh = implode(",", array_fill(0, count($posIds), "?"));
            foreach (
                \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.09.global_position.03", array_merge([$cid], $posIds), compact('posPh'))->fetchAll()
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
            $posTotal = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
                $posTotal,
                $bal,
                "Total das Gavetas",
            );
            $pos[] = $r;
        }
        $bankRows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.09.global_position.04", [$cid], [])->fetchAll();
        $bankBalances = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_location_movement_balances(
            $cid,
            array_map(static  fn(array $row): int => (int) $row["id"], $bankRows),
        );
        $banks = [];
        $bankTotal = 0;
        foreach ($bankRows as $r) {
            $bal = (int) ($bankBalances[(int) $r["id"]] ?? 0);
            $r["balance_cents"] = $bal;
            $bankTotal = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
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
            "total_cents" => \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
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
    
        if (!\Prontoo\Domain\Financial\FinancialDomainOperations01::financial_is_cashier($c)) {
            return false;
        }
        if (\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_read_only_db((int) $c["clinic_id"])) {
            return false;
        }
        try {
            \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
            $cid = (int) $c["clinic_id"];
            $uid = (int) $c["user"]["id"];
            $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
            if (\Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_location_for_user($cid, $uid) <= 0) {
                return false;
            }
            return (bool) \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_unclosed_previous_session($cid, $uid, $today);
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
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $existing = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.09.register_appointment_payment_movement.01", [$cid, $appointmentId], []);
        if (!$paid) {
            if ($existing) {
                \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.09.register_appointment_payment_movement.02", [(int) $existing["id"], $cid], []);
            }
            return;
        }
        if ($amount <= 0) {
            return;
        }
        $method = \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method($method);
        if ($method === "") {
            throw new RuntimeException("Selecione a forma de pagamento.");
        }
        $to = 0;
        $sessionId = null;
        $movementNotes = "Recebimento vinculado ao agendamento.";
        if ($method === "dinheiro") {
            $s = \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_require_open_session($cid, $userId);
            $sessionId = (int) $s["id"];
            $to = (int) $s["location_id"];
            $movementNotes =
                "Recebimento em dinheiro vinculado ao agendamento; compõe a conferência da Gaveta aberta.";
        } else {
            if ($paymentDestinationLocationId <= 0) {
                throw new RuntimeException("Selecione o destino do recebimento.");
            }
            if (
                is_callable([\Prontoo\Runtime\Financial\FinancialRuntimeOperations10::class, 'financial_office_destination_belongs'])
                    ? !\Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_office_destination_belongs(
                        $cid,
                        $paymentDestinationLocationId,
                    )
                    : !\Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_location_belongs(
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
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_update_existing_movement(
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
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_create_movement(
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
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $rid =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.09.revenue_id_for_appointment.01", [$cid, $appointmentId], []) ?:
            0);
        if ($rid > 0) {
            return $rid;
        }
        try {
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_sync_appointment($cid, $appointmentId, $uid);
        } catch (Throwable $e) {
            error_log("[Prontoo financial revenue sync link] " . $e->getMessage());
        }
        return (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.09.revenue_id_for_appointment.02", [$cid, $appointmentId], []) ?:
        0);
    
    }

    public static function financial_appointment_operational_chip_html(
        int $cid,
        array $a,
        string $role = "",
    ): string 
    {
    
        $st = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_appointment_payment_state($a);
        $amount = (int) ($st["amount"] ?? 0);
        $title = $amount > 0 ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($amount) : "Sem valor financeiro";
        $method = (string) ($st["method"] ?? "");
        $methodLabel =
            $method !== "" ? \Prontoo\Domain\Financial\FinancialDomainOperations01::payment_methods_options()[$method] ?? $method : "";
        $chip =
            '<span class="finance-appointment-chip finance-appointment-chip-' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $st["code"]) .
            " pill " .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $st["class"]) .
            '" title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title . ($methodLabel !== "" ? " · " . $methodLabel : "")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon((string) $st["icon"]) .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $st["label"]) .
            "</span>" .
            ($amount > 0 ? "<b>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($amount)) . "</b>" : "") .
            "</span>";
        $canReceive =
            is_callable([\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::class, 'appointment_journey_role_matches']) &&
            (\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "recepcionista") ||
                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "gerente"));
        if (
            $canReceive &&
            (string) $st["code"] === "aguardando_pagamento" &&
            (int) ($a["id"] ?? 0) > 0
        ) {
            $chip .=
                '<a class="finance-appointment-receive small primary" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", [
                    "op" => "receber",
                    "appointment_id" => (int) $a["id"],
                ]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("point_of_sale") .
                "<span>Receber</span></a>";
        }
        return '<span class="finance-appointment-inline">' . $chip . "</span>";
    
    }

    public static function financial_daily_drawer_closure_state(
        int $cid,
        string $businessDate = "",
    ): array 
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $businessDate = $businessDate ?: \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        $expected = [];
        $linked = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.09.daily_drawer_closure_state.01", [$cid], [])->fetchAll();
        foreach ($linked as $r) {
            $key = (int) $r["location_id"] . ":" . (int) $r["user_id"];
            $expected[$key] = $r;
        }
        $sessions = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.09.daily_drawer_closure_state.02", [$cid, $businessDate], [])->fetchAll();
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
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        [$dayStart, $dayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($today, $cid);
        $expected = (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->safeScalar("financial.09.admin_daily_consolidation_html.01", [$cid, $dayStart, $dayEnd], 0, []);
        $received = (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->safeScalar("financial.09.admin_daily_consolidation_html.02", [$cid, $dayStart, $dayEnd], 0, []);
        $pending = (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->safeScalar("financial.09.admin_daily_consolidation_html.03", [$cid, $dayEnd], 0, []);
        $state = \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_consolidation_state($cid, $today);
        $openDrawers = (int) ($state["pending_open_drawers"] ?? 0);
        $pos = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_global_position($cid);
        $cards =
            '<div class="finance-consolidation-kpis"><article>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected) .
            "</b><span>Previsto hoje</span></article><article>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("task_alt") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($received) .
            "</b><span>Recebido hoje</span></article><article>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("pending_actions") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($pending) .
            "</b><span>Pendente</span></article><article>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("point_of_sale") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $pos["pos_cents"]) .
            "</b><span>Em gavetas</span></article></div>";
        if (!empty($state["consolidated"])) {
            $footer =
                '<footer class="finance-consolidation-footer"><button class="ghost small" type="button" disabled aria-disabled="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified") .
                "<span>Dia consolidado</span></button></footer>";
        } elseif ($openDrawers > 0) {
            $footer =
                '<footer class="finance-consolidation-footer"><button class="ghost small" type="button" disabled aria-disabled="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("point_of_sale") .
                "<span>Aguardando fechamentos</span></button></footer>";
        } else {
            $footer =
                '<footer class="finance-consolidation-footer"><a class="primary small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "consolidacao"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("fact_check") .
                "<span>Conferência liberada</span></a></footer>";
        }
        return '<section class="finance-consolidation-panel"><header><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("monitoring") .
            "</span><div><h2>Consolidação do dia</h2></div></header>" .
            $cards .
            $footer .
            "</section>";
    
    }

    public static function financial_cashier_pending_receipts_html(int $cid): string
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        [$dayStart, $dayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($today, $cid);
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.09.cashier_pending_receipts_html.01", [$cid, $dayEnd], [])->fetchAll();
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
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", [
                    "op" => "receber",
                    "revenue_id" => (int) $r["id"],
                ]) .
                '"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($patient) .
                "</b></span><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $r["start_at"]) . " · " . $proc) .
                "</small><em>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["amount_cents"])) .
                "</em></a>";
        }
        return $h . "</div>";
    
    }
}
