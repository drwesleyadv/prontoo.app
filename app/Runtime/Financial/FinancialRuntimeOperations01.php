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
                (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.01.seed_payment_methods.01", [$cid], []) ?? 0);
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
                \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.seed_payment_methods.02", [$cid, $m[0], $m[1], $m[2], $uid ?: null], []);
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
    
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_seed_payment_methods($cid);
        $out = $withEmpty ? ["" => "Não informada"] : [];
        try {
            $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.payment_method_options.01", [$cid], [])->fetchAll();
            foreach ($rows as $r) {
                $out[(int) $r["id"]] = $r["name"];
            }
        } catch (Throwable $e) {
            foreach (\Prontoo\Domain\Financial\FinancialDomainOperations01::payment_methods_options() as $k => $v) {
                $out[$k] = $v;
            }
        }
        return $out;
    
    }

    public static function financial_payment_method_from_post(int $cid): array
    
    {
    
        $id = (int) ($_POST["payment_method_id"] ?? 0);
        if ($id > 0) {
            $r = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.01.payment_method_from_post.01", [$id, $cid], []);
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
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_admin_safe($cid, (int) ($_SESSION["uid"] ?? 0));
        try {
            $accounts = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.appointment_payment_destination_options.01", [$cid], [])->fetchAll();
            foreach ($accounts as $acc) {
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_bank_location(
                    $cid,
                    (int) $acc["id"],
                    (int) ($_SESSION["uid"] ?? 0),
                );
            }
        } catch (Throwable $e) {
            error_log("[Prontoo appointment destinations] " . $e->getMessage());
        }
        try {
            $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.appointment_payment_destination_options.02", [$cid], [])->fetchAll();
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
            return (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.01.appointment_payment_existing_destination.01", [$cid, $appointmentId], []) ?:
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
                    (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.01.appointment_payment_form_html.01", [(int) $appt["procedure_id"], $cid], []) ?:
                    0);
            } catch (Throwable $e) {
                $amount = 0;
            }
        }
        $paid =
            !empty($appt["payment_confirmed_at"]) ||
            ($appt["payment_status"] ?? "") === "efetivada";
        $method = (string) ($appt["payment_method"] ?? "");
        $dest = \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::appointment_payment_existing_destination($cid, $appt);
        $detailsAttr = $paid ? "" : " hidden";
        $destAttr =
            $paid && $method !== "" && $method !== "dinheiro" ? "" : " hidden";
        $amountText = $amount > 0 ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($amount) : 'R$ 0,00';
        $fields =
            '<section class="agenda-payment-fields" data-appointment-payment><h4>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
            '<span>Pagamento</span></h4><label class="checkline"><input type="checkbox" name="payment_confirmed" value="1" data-appointment-paid-toggle ' .
            ($paid ? "checked" : "") .
            '><span>Paciente Pagou</span></label><div class="agenda-payment-details" data-appointment-payment-details' .
            $detailsAttr .
            ">" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Valor fixado no agendamento",
                '<input type="text" value="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($amountText) .
                    '" readonly aria-readonly="true" data-appointment-payment-amount-display><input type="hidden" name="payment_amount" value="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($amountText) .
                    '" data-appointment-payment-amount-hidden>',
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Forma de pagamento",
                "payment_method",
                ["" => "Selecione"] + \Prontoo\Domain\Financial\FinancialDomainOperations01::payment_methods_options(),
                $method,
                "data-appointment-payment-method",
            ) .
            "<div data-appointment-payment-destination" .
            $destAttr .
            ">" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Destino do recebimento",
                "payment_destination_location_id",
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::appointment_payment_destination_options($cid),
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
    
        $a = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.01.sync_appointment.01", [$appointmentId, $cid], []);
        if (!$a) {
            return;
        }
        $amount = (int) ($a["payment_amount_cents"] ?? 0);
        $procId = (int) ($a["procedure_id"] ?? 0);
        if ($amount <= 0 && $procId > 0) {
            $pr = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.01.sync_appointment.02", [$procId, $cid], []);
            $amount = (int) ($pr["price_cents"] ?? 0);
            if ($amount > 0) {
                \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.sync_appointment.03", [$amount, $appointmentId, $cid], []);
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
        $received = $paid ? ($a["payment_confirmed_at"] ?: \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::now()) : null;
        $method =
            \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method((string) ($a["payment_method"] ?? "")) ?: null;
        $existing = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.01.sync_appointment.04", [$cid, $appointmentId], []);
        if ($existing) {
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.sync_appointment.05", [
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
                ], []);
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.sync_appointment.06", [(int) $existing["id"], $appointmentId, $cid], []);
        } else {
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.sync_appointment.07", [
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
                ], []);
            $rid = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.sync_appointment.08", [$rid, $appointmentId, $cid], []);
        }
        try {
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_register_appointment_payment_movement(
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
    
        $month = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_month_in_timezone($cid);
        $loader = static function () use ($cid, $month): array {
    
            [$start, $next] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_month_utc_range($month, $cid);
            $goal = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.01.monthly_goal_status.01", [$cid, $month], []) ?: [
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
                    (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.01.monthly_goal_status.02", [$cid, $start, $next], []) ?? 0);
            } else {
                $done =
                    (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.01.monthly_goal_status.03", [$cid, $start, $next], []) ?? 0);
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
                "base_label" => \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_goal_base_label($base),
                "values" => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($done) . " de " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($target),
            ];
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "financial",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("monthly_goal_status", [$cid, $month]),
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
        $st = \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::monthly_goal_status($cid);
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pctLabel) .
            '"><span class="material-symbols-rounded goal-mini-icon" aria-hidden="true">flag</span><span class="sr-only">Meta mensal</span><div class="goal-bar" aria-hidden="true"><i data-goal-bar style="width:' .
            $bar .
            '%"></i></div><b data-goal-percent>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pctLabel) .
            "</b></section>";
    
    }

    public static function page_goal_status(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            http_response_code(403);
            exit();
        }
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        $st = \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::monthly_goal_status((int) $c["clinic_id"]);
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
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("operations");
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
            if (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can($route)) {
                $html .=
                    '<a class="admin-tile" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
                    "<b>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                    "</b><span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($desc) .
                    "</span></a>";
            }
        }
        $html .= "</div>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Fluxo", \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Fluxo", "") . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($html, "operations-card"));
    
    }

    public static function counterparty_autosuggest_datalist(
        int $cid,
        string $id = "prontoo_counterparty_suggestions",
    ): string 
    {
    
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.01.counterparty_autosuggest_datalist.01", [$cid], [])->fetchAll();
        $h = '<datalist id="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($id) . '">';
        foreach ($rows as $r) {
            $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
            $masked = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($doc);
            $value =
                (string) $r["full_name"] . ($masked !== "" ? " · " . $masked : "");
            $label =
                $masked !== "" ? "CPF/CNPJ " . $masked : "Documento não informado";
            $h .=
                '<option value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) .
                '" label="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                '" data-counterparty-id="' .
                (int) $r["id"] .
                '" data-counterparty-name="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $r["full_name"]) .
                '" data-counterparty-doc="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($masked) .
                '" data-counterparty-doc-raw="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($doc) .
                '"></option>';
        }
        return $h . "</datalist>";
    
    }
}
