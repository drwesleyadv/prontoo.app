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

final class FinancialRuntimeOperations10
{
    private function __construct()
    {
    }

    public static function financial_location_select_options(int $cid, string $type = ""): array
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $params = [$cid];
        $where = "clinic_id=? AND active=1";
        if ($type !== "") {
            $where .= " AND location_type=?";
            $params[] = $type;
        }
        $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "SELECT id,name,location_type FROM pi_financial_locations WHERE $where ORDER BY FIELD(location_type,'admin_safe','pos','bank_account'), name",
            $params,
        )->fetchAll();
        $out = ["" => "Selecione"];
        foreach ($rows as $r) {
            $out[(int) $r["id"]] =
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_location_type_label((string) $r["location_type"]) .
                " · " .
                (string) $r["name"];
        }
        return $out;
    
    }

    public static function financial_office_destination_options(int $cid, int $uid = 0): array
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_admin_safe($cid, $uid);
        try {
            $accounts = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "SELECT id FROM pi_financial_accounts WHERE clinic_id=? AND active=1 AND account_type IN ('conta_corrente','conta_poupanca','conta_pagamento','investimento') ORDER BY name",
                [$cid],
            )->fetchAll();
            foreach ($accounts as $a) {
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_bank_location($cid, (int) $a["id"], $uid);
            }
        } catch (Throwable $e) {
            error_log("[Prontoo destinos financeiros] " . $e->getMessage());
        }
        $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "SELECT l.id,l.name,l.location_type,a.bank_name FROM pi_financial_locations l LEFT JOIN pi_financial_accounts a ON a.id=l.account_id AND a.clinic_id=l.clinic_id WHERE l.clinic_id=? AND l.active=1 AND l.location_type IN ('admin_safe','bank_account') ORDER BY FIELD(l.location_type,'admin_safe','bank_account'), l.name",
            [$cid],
        )->fetchAll();
        $out = ["" => "Selecione o destino"];
        foreach ($rows as $r) {
            $type = (string) $r["location_type"];
            $label =
                $type === "admin_safe"
                    ? "Cofre do Consultório"
                    : "Conta do Consultório";
            $detail = mb_trim((string) ($r["name"] ?? ""));
            if ($type === "bank_account" && !empty($r["bank_name"])) {
                $detail .= " · " . (string) $r["bank_name"];
            }
            $out[(int) $r["id"]] = $label . ($detail !== "" ? " · " . $detail : "");
        }
        return $out;
    
    }

    public static function financial_office_destination_belongs(int $cid, int $locationId): bool
    
    {
    
        if ($cid <= 0 || $locationId <= 0) {
            return false;
        }
        return (int) (\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
            "SELECT id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') LIMIT 1",
            [$locationId, $cid],
        ) ?:
            0) > 0;
    
    }

    public static function financial_expected_appointment_revenue_options(int $cid): array
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "SELECT r.id,r.amount_cents,r.title,r.expected_at,a.start_at,a.status,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.clinic_id=? AND r.status='prevista' AND r.appointment_id IS NOT NULL AND r.procedure_id IS NOT NULL AND r.amount_cents>0 AND a.status NOT IN ('cancelado','nao_compareceu') ORDER BY CASE WHEN a.status IN ('atendimento_concluido','finalizado') THEN 0 ELSE 1 END, COALESCE(a.start_at,r.expected_at,NOW()) ASC,r.id ASC LIMIT 200",
            [$cid],
        )->fetchAll();
        $out = ["" => "Selecione o atendimento"];
        foreach ($rows as $r) {
            $patient =
                mb_trim((string) ($r["patient_name"] ?? "Paciente")) ?: "Paciente";
            $proc =
                trim(
                    (string) ($r["procedure_title"] ??
                        ($r["title"] ?? "Procedimento")),
                ) ?:
                "Procedimento";
            $when = (string) ($r["start_at"] ?: $r["expected_at"] ?: "");
            $date = $when !== "" ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($when) : "sem data";
            $status = in_array(
                (string) ($r["status"] ?? ""),
                ["atendimento_concluido", "finalizado"],
                true,
            )
                ? "aguardando pagamento"
                : "previsto";
            $out[(int) $r["id"]] =
                $patient .
                " · " .
                $proc .
                " · " .
                $date .
                " · " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["amount_cents"]) .
                " · " .
                $status;
        }
        return $out;
    
    }

    public static function financial_receive_expected_appointment_revenue(
        int $cid,
        int $uid,
        int $revenueId,
        string $method,
        int $destinationLocationId = 0,
        string $notes = "",
    ): int 
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $method = \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method($method);
        if ($method === "") {
            throw new RuntimeException("Informe a forma de recebimento.");
        }
        $s = \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_require_open_session($cid, $uid);
        return (int) \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_tx(function () use (
            $cid,
            $uid,
            $revenueId,
            $method,
            $destinationLocationId,
            $notes,
            $s,
        ): int {
    
            $session = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? AND user_id=? AND status='open' FOR UPDATE",
                [(int) $s["id"], $cid, $uid],
            );
            if (!$session) {
                throw new RuntimeException(
                    "Não há Gaveta aberta para registrar recebimento.",
                );
            }
            $rev = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                "SELECT r.*,a.status appointment_status,a.start_at,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.id=? AND r.clinic_id=? AND r.status='prevista' AND r.appointment_id IS NOT NULL AND r.procedure_id IS NOT NULL AND r.amount_cents>0 AND a.status NOT IN ('cancelado') FOR UPDATE",
                [$revenueId, $cid],
            );
            if (!$rev) {
                throw new RuntimeException(
                    "Selecione uma receita prevista de Procedimento Agendado ainda não recebida.",
                );
            }
            $amount = (int) $rev["amount_cents"];
            $appointmentId = (int) $rev["appointment_id"];
            $to = (int) $session["location_id"];
            $sessionId = (int) $session["id"];
            $accountId = null;
            $extraNote =
                "Recebimento em dinheiro vinculado ao agendamento; compõe a conferência da Gaveta.";
            if ($method !== "dinheiro") {
                if (
                    !\Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_office_destination_belongs(
                        $cid,
                        $destinationLocationId,
                    )
                ) {
                    throw new RuntimeException(
                        "Informe o Destino entre as contas do Consultório para recebimentos que não forem em Dinheiro.",
                    );
                }
                $dest = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,account_id,location_type,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
                    [$destinationLocationId, $cid],
                );
                $to = (int) $destinationLocationId;
                $sessionId = null;
                $accountId =
                    $dest && (string) $dest["location_type"] === "bank_account"
                        ? ((int) ($dest["account_id"] ?? 0) ?:
                        null)
                        : null;
                $extraNote =
                    "Recebimento sem dinheiro físico vinculado ao agendamento; direcionado ao Consultório e fora da conferência da Gaveta.";
            }
            $title =
                "Recebimento · " .
                trim(
                    (string) ($rev["procedure_title"] ?:
                    $rev["title"] ?:
                    "Procedimento agendado"),
                );
            $cleanNotes = trim($notes);
            $movementNotes =
                $extraNote . ($cleanNotes !== "" ? " " . $cleanNotes : "");
            $movementStatus =
                $method === "dinheiro" ? "pending_review" : "confirmed";
            $existing = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                "SELECT id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$cid, $appointmentId],
            );
            if ($existing) {
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_update_existing_movement(
                    $cid,
                    (int) $existing["id"],
                    "receipt",
                    $amount,
                    null,
                    $to,
                    $sessionId,
                    $uid,
                    $title,
                    $method,
                    $movementNotes,
                    $movementStatus,
                );
                $movementId = (int) $existing["id"];
            } else {
                $movementId = \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_create_movement(
                    $cid,
                    "receipt",
                    $amount,
                    null,
                    $to,
                    $sessionId,
                    $uid,
                    $title,
                    $method,
                    $movementNotes,
                    $movementStatus,
                    "appointment",
                    $appointmentId,
                );
            }
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "UPDATE pi_financial_revenues SET status='efetivada', payment_method=?, account_id=?, received_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$method, $accountId, $uid, $revenueId, $cid],
            );
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "UPDATE pi_appointments SET payment_status='efetivada', payment_method=?, payment_amount_cents=?, payment_confirmed_at=NOW(), revenue_id=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$method, $amount, $revenueId, $appointmentId, $cid],
            );
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("recebimento_atendimento_agendado", "financeiro", $revenueId, [
                "appointment_id" => $appointmentId,
                "movement_id" => $movementId,
                "valor" => $amount,
                "forma" => $method,
                "conta_na_gaveta" => $method === "dinheiro",
                "audit_body" =>
                    "Recepção registrou recebimento de receita prevista vinculada a Procedimento Agendado.",
            ]);
            return $movementId;
        });
    
    }

    public static function financial_tabs_html(array $tabs, string $active): string
    
    {
    
        $h =
            '<nav class="finance-admin-primary-nav" aria-label="Financeiro do Consultório">';
        foreach ($tabs as $key => $meta) {
            $h .=
                '<a class="ghost small' .
                ($active === $key ? " active" : "") .
                '" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => $key]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($meta[1]) .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($meta[0]) .
                "</span></a>";
        }
        return $h . "</nav>";
    
    }

    public static function financial_cash_debug_details_enabled(): bool
    
    {
    
        return is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'app_debug']) && \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_debug();
    
    }

    public static function financial_cash_debug_error_html(): string
    
    {
    
        $debug = (string) ($_SESSION["financial_cash_debug_error"] ?? "");
        $when = (string) ($_SESSION["financial_cash_error_at"] ?? "");
        if ($debug === "") {
            return "";
        }
        unset(
            $_SESSION["financial_cash_debug_error"],
            $_SESSION["financial_cash_error_at"],
        );
        if (!\Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cash_debug_details_enabled()) {
            $ref =
                $when !== ""
                    ? " Horário técnico: <strong>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($when) . "</strong>."
                    : "";
            return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Erro operacional da Gaveta</h2><p class="muted">Não foi possível concluir a ação da Gaveta. O erro foi registrado no log técnico do sistema.' .
                    $ref .
                    "</p>",
                "finance-alert-card",
            );
        }
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<h2>Erro técnico da Gaveta</h2><p class="muted">Modo debug ativo. Copie todo o conteúdo abaixo para análise técnica.</p><textarea class="tech-debug-copy" rows="16" readonly onclick="this.select()">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($debug) .
                "</textarea>",
            "finance-alert-card",
        );
    
    }

    public static function financial_cashier_pagehead_link(
        string $op,
        string $label,
        string $iconName,
        bool $enabled,
        string $active,
    ): string 
    {
    
        $cls =
            "ghost small finance-cash-action cash-action-" .
            $op .
            ($active === $op ? " active" : "");
        $content = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) . "<span>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) . "</span>";
        if ($enabled) {
            return '<a class="' .
                $cls .
                '" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["op" => $op]) .
                '">' .
                $content .
                "</a>";
        }
        return '<span class="' .
            $cls .
            ' disabled" aria-disabled="true" tabindex="-1">' .
            $content .
            "</span>";
    
    }

    public static function financial_cashier_pagehead_actions(
        bool $canOpen,
        bool $canPayReceive,
        bool $canClose,
        string $active = "",
    ): string 
    {
    
        return '<nav class="finance-pagehead-nav finance-cash-pagehead-actions" aria-label="Ações da Gaveta do Atendimento">' .
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cashier_pagehead_link(
                "abrir",
                "Abrir Gaveta",
                "lock_open",
                $canOpen,
                $active,
            ) .
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cashier_pagehead_link(
                "receber",
                "Recebi",
                "move_to_inbox",
                $canPayReceive,
                $active,
            ) .
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cashier_pagehead_link(
                "pagar",
                "Paguei",
                "outbox",
                $canPayReceive,
                $active,
            ) .
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cashier_pagehead_link(
                "fechar",
                "Fechar Gaveta",
                "lock",
                $canClose,
                $active,
            ) .
            "</nav>";
    
    }

    public static function financial_cashier_drawer_summary(
        ?array $session = null,
        ?array $prev = null,
        int $fallbackOpening = 0,
        ?array $drawer = null,
    ): string 
    {
    
        $source = $prev ?: $session;
        $isOpen = $source && (string) ($source["status"] ?? "") === "open";
        $opening = $source
            ? (int) ($source["opening_balance_cents"] ?? 0)
            : max(0, $fallbackOpening);
        $receipts = 0;
        $payments = 0;
        $expected = $opening;
        if ($source) {
            $totals = \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_session_movement_totals(
                (int) $source["clinic_id"],
                (int) $source["id"],
            );
            $receipts = (int) $totals["receipt"];
            $payments = (int) $totals["payment"] + (int) $totals["refund"];
            if ((string) ($source["status"] ?? "") === "open") {
                $expected = \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_session_expected($source);
            } elseif (
                isset($source["expected_closing_cents"]) &&
                (int) $source["expected_closing_cents"] > 0
            ) {
                $expected = (int) $source["expected_closing_cents"];
            } else {
                $expected = $opening + $receipts - $payments;
            }
        }
        $rawStatus = $source ? (string) ($source["status"] ?? "") : "";
        $drawerName = mb_trim((string) ($drawer["name"] ?? ""));
        $drawerLocked =
            $drawer &&
            (string) ($drawer["drawer_lock_status"] ?? "unlocked") === "locked";
        if ($drawerLocked) {
            $statusLabel = "Trancada";
            $statusTitle = "Trancada para conferência";
            $statusClass = "locked";
            $statusIcon = "lock_clock";
        } else {
            $statusLabel = $isOpen ? "Aberta" : "Fechada";
            $statusTitle = $isOpen
                ? "Aberta"
                : ($rawStatus === "opening_pending_review"
                    ? "Aguardando autorização"
                    : ($rawStatus === "opening_rejected"
                        ? "Abertura da Gaveta recusada"
                        : "Fechada"));
            $statusClass = $isOpen
                ? "open"
                : ($rawStatus === "opening_pending_review"
                    ? "pending"
                    : "closed");
            $statusIcon = $isOpen ? "currency_exchange" : "lock_clock";
        }
        $subtitle = $drawerName !== "" ? "<em>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($drawerName) . "</em>" : "";
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="cash-drawer-line"><div class="cash-drawer-title"><span class="cash-drawer-status ' .
                $statusClass .
                '" title="Gaveta ' .
                $statusTitle .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($statusIcon) .
                "</span><strong>Minha Gaveta</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($statusLabel) .
                "</small>" .
                $subtitle .
                '</div><div class="cash-drawer-pills"><div class="cash-drawer-metric"><span>Saldo Inicial</span><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($opening) .
                '</b></div><div class="cash-drawer-metric"><span>Recebi</span><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($receipts) .
                '</b></div><div class="cash-drawer-metric"><span>Paguei</span><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($payments) .
                '</b></div><div class="cash-drawer-metric"><span>Esperado</span><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected) .
                "</b></div></div></div>",
            "finance-dashboard-card cash-drawer-card",
        );
    
    }

    public static function financial_cash_debug_failure_page(Throwable $e): void
    
    {
    
        $ctx = [];
        $ctxErr = null;
        try {
            if (is_callable([\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::class, 'ctx'])) {
                $ctx = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
            }
        } catch (Throwable $ce) {
            $ctxErr = $ce;
        }
        $debug = is_callable([\Prontoo\Presentation\Financial\FinancialPresentationOperations01::class, 'financial_cash_exception_debug'])
            ? \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_cash_exception_debug(
                $e,
                is_array($ctx) ? $ctx : [],
                (string) ($_POST["act"] ?? ""),
            )
            : $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        if ($ctxErr) {
            $debug .=
                PHP_EOL . PHP_EOL . "CTX_EXCEPTION_CLASS=" . get_class($ctxErr);
            $debug .= PHP_EOL . "CTX_EXCEPTION_MESSAGE=" . $ctxErr->getMessage();
            $debug .= PHP_EOL . "CTX_EXCEPTION_FILE=" . $ctxErr->getFile();
            $debug .= PHP_EOL . "CTX_EXCEPTION_LINE=" . $ctxErr->getLine();
        }
        error_log(
            "[Prontoo caixa atendimento] " . str_replace(PHP_EOL, " | ", $debug),
        );
        if (!headers_sent()) {
            http_response_code(
                $e instanceof ProntooHttpError ? (int) $e->status : 500,
            );
            header("Content-Type: text/html; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        $version = defined("PRONTOO_VERSION") ? PRONTOO_VERSION : (string) time();
        $back = is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'href']) ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial") : "/?r=financial";
        if (!\Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cash_debug_details_enabled()) {
            echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Minha Gaveta · Prontoo</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#334155"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
                rawurlencode($version) .
                '"></head><body class="app"><main><section class="auth widebox finance-alert-card"><h1>Não foi possível concluir a ação da Gaveta</h1><p>O erro foi registrado no log técnico do sistema. Tente novamente após revisar a conexão e, se persistir, informe o horário da tentativa ao suporte.</p><p><a class="primary" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($back) .
                '">Voltar à Gaveta</a></p></section></main></body></html>';
            exit();
        }
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Erro técnico da Gaveta · Prontoo</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#334155"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
            rawurlencode($version) .
            '"></head><body class="app"><main><section class="auth widebox finance-alert-card"><h1>Erro técnico da Gaveta</h1><p>Modo debug ativo. Copie todo o conteúdo abaixo para análise técnica.</p><textarea class="tech-debug-copy" rows="22" readonly onclick="this.select()">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($debug) .
            '</textarea><p><a class="primary" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($back) .
            '">Voltar à Gaveta</a></p></section></main></body></html>';
        exit();
    
    }
}
