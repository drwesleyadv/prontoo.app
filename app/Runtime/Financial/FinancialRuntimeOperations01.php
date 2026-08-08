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

final class FinancialRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function financial_seed_payment_methods(int $cid, int $uid = 0): void
    
    {
    
        if ($cid <= 0) {
            return;
        }
        try {
            $count =
                (int) (val(
                    "SELECT COUNT(*) FROM pi_financial_payment_methods WHERE clinic_id=?",
                    [$cid],
                ) ?? 0);
            if ($count > 0) {
                return;
            }
            foreach (
                [
                    ["PIX", "pix", 0],
                    ["Dinheiro", "dinheiro", 0],
                    ["Cartão de débito", "cartao_debito", 1],
                    ["Cartão de crédito", "cartao_credito", 30],
                    ["Transferência bancária", "transferencia", 0],
                    ["Boleto bancário", "boleto", 2],
                    ["Cheque", "cheque", 2],
                ]
                as $m
            ) {
                q(
                    "INSERT INTO pi_financial_payment_methods (clinic_id,name,method_type,settlement_days,active,created_by,created_at) VALUES (?,?,?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE active=VALUES(active)",
                    [$cid, $m[0], $m[1], $m[2], $uid ?: null],
                );
            }
        } catch (Throwable $e) {
            error_log("[Prontoo payment methods seed] " . $e->getMessage());
        }
    
    }

    public static function financial_payment_method_options(
        int $cid,
        bool $withEmpty = true,
    ): array 
    {
    
        financial_seed_payment_methods($cid);
        $out = $withEmpty ? ["" => "Não informada"] : [];
        try {
            $rows = q(
                "SELECT id,name,method_type FROM pi_financial_payment_methods WHERE clinic_id=? AND active=1 ORDER BY FIELD(method_type,'pix','dinheiro','cartao_debito','cartao_credito','transferencia','boleto','cheque','outro'), name",
                [$cid],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[(int) $r["id"]] = $r["name"];
            }
        } catch (Throwable $e) {
            foreach (payment_methods_options() as $k => $v) {
                $out[$k] = $v;
            }
        }
        return $out;
    
    }

    public static function financial_payment_method_from_post(int $cid): array
    
    {
    
        $id = (int) ($_POST["payment_method_id"] ?? 0);
        if ($id > 0) {
            $r = one(
                "SELECT id,name,method_type FROM pi_financial_payment_methods WHERE id=? AND clinic_id=? AND active=1",
                [$id, $cid],
            );
            if ($r) {
                return [
                    (int) $r["id"],
                    (string) $r["method_type"],
                    (string) $r["name"],
                ];
            }
        }
        return [null, "", ""];
    
    }

    public static function appointment_payment_destination_options(
        int $cid,
        bool $withEmpty = true,
    ): array 
    {
    
        $out = $withEmpty ? ["" => "Selecione o destino"] : [];
        if ($cid <= 0) {
            return $out;
        }
        financial_operational_schema_ready();
        financial_ensure_admin_safe($cid, (int) ($_SESSION["uid"] ?? 0));
        try {
            $accounts = q(
                "SELECT id FROM pi_financial_accounts WHERE clinic_id=? AND active=1 AND account_type IN ('conta_corrente','conta_poupanca','conta_pagamento','investimento') ORDER BY id LIMIT 80",
                [$cid],
            )->fetchAll();
            foreach ($accounts as $acc) {
                financial_ensure_bank_location(
                    $cid,
                    (int) $acc["id"],
                    (int) ($_SESSION["uid"] ?? 0),
                );
            }
        } catch (Throwable $e) {
            error_log("[Prontoo appointment destinations] " . $e->getMessage());
        }
        try {
            $rows = q(
                "SELECT id,name,location_type FROM pi_financial_locations WHERE clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') ORDER BY FIELD(location_type,'admin_safe','bank_account'), name,id",
                [$cid],
            )->fetchAll();
            foreach ($rows as $r) {
                $kind = (string) ($r["location_type"] ?? "");
                $label = $kind === "bank_account" ? "Banco" : "Cofre";
                $out[(int) $r["id"]] = $label . " · " . (string) $r["name"];
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo appointment destinations list] " . $e->getMessage(),
            );
        }
        return $out;
    
    }

    public static function appointment_payment_existing_destination(int $cid, array $appt): int
    
    {
    
        $appointmentId = (int) ($appt["id"] ?? 0);
        if ($cid <= 0 || $appointmentId <= 0) {
            return 0;
        }
        try {
            return (int) (val(
                "SELECT to_location_id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' AND status='confirmed' ORDER BY id DESC LIMIT 1",
                [$cid, $appointmentId],
            ) ?:
            0);
        } catch (Throwable $e) {
            return 0;
        }
    
    }

    public static function appointment_payment_form_html(int $cid, array $appt = []): string
    
    {
    
        $amount = (int) ($appt["payment_amount_cents"] ?? 0);
        if ($amount <= 0 && !empty($appt["procedure_id"])) {
            try {
                $amount =
                    (int) (val(
                        "SELECT price_cents FROM pi_procedures WHERE id=? AND clinic_id=?",
                        [(int) $appt["procedure_id"], $cid],
                    ) ?:
                    0);
            } catch (Throwable $e) {
                $amount = 0;
            }
        }
        $paid =
            !empty($appt["payment_confirmed_at"]) ||
            ($appt["payment_status"] ?? "") === "efetivada";
        $method = (string) ($appt["payment_method"] ?? "");
        $dest = appointment_payment_existing_destination($cid, $appt);
        $detailsAttr = $paid ? "" : " hidden";
        $destAttr =
            $paid && $method !== "" && $method !== "dinheiro" ? "" : " hidden";
        $amountText = $amount > 0 ? money_br($amount) : 'R$ 0,00';
        $fields =
            '<section class="agenda-payment-fields" data-appointment-payment><h4>' .
            icon("payments") .
            '<span>Pagamento</span></h4><label class="checkline"><input type="checkbox" name="payment_confirmed" value="1" data-appointment-paid-toggle ' .
            ($paid ? "checked" : "") .
            '><span>Paciente Pagou</span></label><div class="agenda-payment-details" data-appointment-payment-details' .
            $detailsAttr .
            ">" .
            form_row(
                "Valor fixado no agendamento",
                '<input type="text" value="' .
                    e($amountText) .
                    '" readonly aria-readonly="true" data-appointment-payment-amount-display><input type="hidden" name="payment_amount" value="' .
                    e($amountText) .
                    '" data-appointment-payment-amount-hidden>',
            ) .
            select_label(
                "Forma de pagamento",
                "payment_method",
                ["" => "Selecione"] + payment_methods_options(),
                $method,
                "data-appointment-payment-method",
            ) .
            "<div data-appointment-payment-destination" .
            $destAttr .
            ">" .
            select_label(
                "Destino do recebimento",
                "payment_destination_location_id",
                appointment_payment_destination_options($cid),
                $dest,
            ) .
            "</div></div></section>";
        return $fields;
    
    }

    public static function financial_sync_appointment(
        int $cid,
        int $appointmentId,
        int $userId,
        int $paymentDestinationLocationId = 0,
    ): void 
    {
    
        $a = one(
            "SELECT id,patient_link_id,procedure_id,start_at,reason,payment_amount_cents,payment_method,payment_status,payment_confirmed_at,revenue_id FROM pi_appointments WHERE id=? AND clinic_id=?",
            [$appointmentId, $cid],
        );
        if (!$a) {
            return;
        }
        $amount = (int) ($a["payment_amount_cents"] ?? 0);
        $procId = (int) ($a["procedure_id"] ?? 0);
        if ($amount <= 0 && $procId > 0) {
            $pr = one(
                "SELECT price_cents FROM pi_procedures WHERE id=? AND clinic_id=?",
                [$procId, $cid],
            );
            $amount = (int) ($pr["price_cents"] ?? 0);
            if ($amount > 0) {
                q(
                    "UPDATE pi_appointments SET payment_amount_cents=? WHERE id=? AND clinic_id=?",
                    [$amount, $appointmentId, $cid],
                );
            }
        }
        if ($amount <= 0) {
            return;
        }
        $paid =
            !empty($a["payment_confirmed_at"]) ||
            ($a["payment_status"] ?? "") === "efetivada";
        $status = $paid ? "efetivada" : "prevista";
        $title = mb_trim((string) ($a["reason"] ?? "Atendimento agendado"));
        if ($title === "") {
            $title = "Atendimento agendado";
        }
        $received = $paid ? ($a["payment_confirmed_at"] ?: now()) : null;
        $method =
            normalize_payment_method((string) ($a["payment_method"] ?? "")) ?: null;
        $existing = one(
            "SELECT id FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=?",
            [$cid, $appointmentId],
        );
        if ($existing) {
            q(
                "UPDATE pi_financial_revenues SET procedure_id=?, patient_link_id=?, title=?, amount_cents=?, status=?, payment_method=?, expected_at=?, received_at=?, updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                [
                    $procId ?: null,
                    (int) ($a["patient_link_id"] ?? 0) ?: null,
                    $title,
                    $amount,
                    $status,
                    $method,
                    (string) $a["start_at"],
                    $received,
                    $userId,
                    (int) $existing["id"],
                    $cid,
                ],
            );
            q(
                "UPDATE pi_appointments SET revenue_id=? WHERE id=? AND clinic_id=?",
                [(int) $existing["id"], $appointmentId, $cid],
            );
        } else {
            q(
                "INSERT INTO pi_financial_revenues (clinic_id,appointment_id,procedure_id,patient_link_id,title,amount_cents,status,payment_method,expected_at,received_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $appointmentId,
                    $procId ?: null,
                    (int) ($a["patient_link_id"] ?? 0) ?: null,
                    $title,
                    $amount,
                    $status,
                    $method,
                    (string) $a["start_at"],
                    $received,
                    $userId,
                ],
            );
            $rid = db_last_insert_id();
            q(
                "UPDATE pi_appointments SET revenue_id=? WHERE id=? AND clinic_id=?",
                [$rid, $appointmentId, $cid],
            );
        }
        try {
            financial_register_appointment_payment_movement(
                $cid,
                $appointmentId,
                $userId,
                $amount,
                (string) ($method ?: ""),
                $title,
                $paid,
                $paymentDestinationLocationId,
            );
        } catch (Throwable $e) {
            error_log("[Prontoo appointment cash movement] " . $e->getMessage());
            throw $e;
        }
    
    }

    public static function monthly_goal_status(int $cid): array
    
    {
    
        $month = app_month_in_timezone($cid);
        $loader = static function () use ($cid, $month): array {
    
            [$start, $next] = app_local_month_utc_range($month, $cid);
            $goal = one(
                "SELECT target_cents,base_metric,share_with_team FROM pi_financial_goals WHERE clinic_id=? AND month_key=?",
                [$cid, $month],
            ) ?: [
                "target_cents" => 0,
                "base_metric" => "efetivada",
                "share_with_team" => 0,
            ];
            $target = (int) ($goal["target_cents"] ?? 0);
            $base = (string) ($goal["base_metric"] ?? "efetivada");
            if (!in_array($base, ["prevista", "efetivada"], true)) {
                $base = "efetivada";
            }
            if ($base === "prevista") {
                $done =
                    (int) (val(
                        "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.expected_at>=? AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))",
                        [$cid, $start, $next],
                    ) ?? 0);
            } else {
                $done =
                    (int) (val(
                        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?",
                        [$cid, $start, $next],
                    ) ?? 0);
            }
            $pct = $target > 0
                ? min(999, round(($done / $target) * 100, 1, \RoundingMode::HalfAwayFromZero))
                : 0;
            return [
                "month" => $month,
                "target_cents" => $target,
                "done_cents" => $done,
                "percent" => $pct,
                "share" => (int) ($goal["share_with_team"] ?? 0) === 1,
                "base_metric" => $base,
                "base_label" => financial_goal_base_label($base),
                "values" => money_br($done) . " de " . money_br($target),
            ];
        };
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "financial",
                server_json_cache_safe_key("monthly_goal_status", [$cid, $month]),
                20,
                $loader,
                ["clinic:" . $cid, "goal:" . $month],
            );
        }
        return $loader();
    
    }

    public static function monthly_goal_card(array $c, string $variant = "compact"): string
    
    {
    
        if (($c["scope"] ?? "") !== "clinic") {
            return "";
        }
        $cid = (int) ($c["clinic_id"] ?? 0);
        if ($cid <= 0) {
            return "";
        }
        $st = monthly_goal_status($cid);
        if (!$st["share"] || $st["target_cents"] <= 0) {
            return "";
        }
        $pct = (float) $st["percent"];
        $bar = max(0, min(100, $pct));
        $pctLabel = number_format($pct, 0, ",", ".") . "%";
        $cls = $variant === "rolebar" ? " goal-card-rolebar" : " goal-card-compact";
        return '<section class="goal-card' .
            $cls .
            '" data-monthly-goal-card aria-label="Meta mensal: ' .
            e($pctLabel) .
            '"><span class="material-symbols-rounded goal-mini-icon" aria-hidden="true">flag</span><span class="sr-only">Meta mensal</span><div class="goal-bar" aria-hidden="true"><i data-goal-bar style="width:' .
            $bar .
            '%"></i></div><b data-goal-percent>' .
            e($pctLabel) .
            "</b></section>";
    
    }

    public static function page_goal_status(): void
    
    {
    
        $c = need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            http_response_code(403);
            exit();
        }
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        $st = monthly_goal_status((int) $c["clinic_id"]);
        $shared = (bool) ($st["share"] ?? false);
        $percent = $shared ? (float) ($st["percent"] ?? 0) : 0.0;
        echo json_encode(
            [
                "ok" => true,
                "share" => $shared,
                "percent" => $percent,
                "bar" => max(0, min(100, $percent)),
            ],
            JSON_UNESCAPED_UNICODE,
        );
        exit();
    
    }

    public static function page_operations(): void
    
    {
    
        $c = require_can("operations");
        $items = [
            [
                "leads",
                "Interessados",
                "person_search",
                "Captar e acompanhar contatos antes do cadastro.",
            ],
            [
                "patients",
                "Pacientes",
                "patient_list",
                "Abrir cadastros, prontuários e documentos.",
            ],
            [
                "appointments",
                "Agenda",
                "calendar_month",
                "Conduzir horários, chegadas e pagamentos.",
            ],
            ["tasks", "Tarefas", "task_alt", "Distribuir e acompanhar pendências."],
            [
                "maestro",
                "Rotinas",
                "event_repeat",
                "Automatizar tarefas e avisos recorrentes do consultório.",
            ],
            [
                "audit",
                "Atividades",
                "history",
                "Consultar o histórico operacional.",
            ],
        ];
        $html = '<div class="admin-grid operations-grid">';
        foreach ($items as [$route, $title, $ico, $desc]) {
            if (can($route)) {
                $html .=
                    '<a class="admin-tile" href="' .
                    href($route) .
                    '">' .
                    icon($ico) .
                    "<b>" .
                    e($title) .
                    "</b><span>" .
                    e($desc) .
                    "</span></a>";
            }
        }
        $html .= "</div>";
        page("Fluxo", page_head("Fluxo", "") . card($html, "operations-card"));
    
    }

    public static function counterparty_autosuggest_datalist(
        int $cid,
        string $id = "prontoo_counterparty_suggestions",
    ): string 
    {
    
        $rows = q(
            "SELECT fc.id,p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.active=1 ORDER BY p.full_name ASC LIMIT 800",
            [$cid],
        )->fetchAll();
        $h = '<datalist id="' . e($id) . '">';
        foreach ($rows as $r) {
            $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
            $masked = mask($doc);
            $value =
                (string) $r["full_name"] . ($masked !== "" ? " · " . $masked : "");
            $label =
                $masked !== "" ? "CPF/CNPJ " . $masked : "Documento não informado";
            $h .=
                '<option value="' .
                e($value) .
                '" label="' .
                e($label) .
                '" data-counterparty-id="' .
                (int) $r["id"] .
                '" data-counterparty-name="' .
                e((string) $r["full_name"]) .
                '" data-counterparty-doc="' .
                e($masked) .
                '" data-counterparty-doc-raw="' .
                e($doc) .
                '"></option>';
        }
        return $h . "</datalist>";
    
    }
}
