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

final class FinancialRuntimeOperations13
{
    private function __construct()
    {
    }

    public static function financial_admin_reviews_panel(int $cid, int $uid): string
    
    {
    
        $openRows = q(
            "SELECT s.id,s.status,s.business_date,s.opening_balance_cents,s.expected_closing_cents,u.name user_name,l.name location_name FROM pi_cash_sessions s JOIN pi_users u ON u.id=s.user_id LEFT JOIN pi_financial_locations l ON l.id=s.location_id AND l.clinic_id=s.clinic_id WHERE s.clinic_id=? AND s.status IN ('opening_pending_review','opening_rejected') ORDER BY FIELD(s.status,'opening_pending_review','opening_rejected'), s.business_date DESC,s.id DESC LIMIT 80",
            [$cid],
        )->fetchAll();
        $openList = "";
        foreach ($openRows as $s) {
            $expected = (int) ($s["expected_closing_cents"] ?? 0);
            $informed = (int) ($s["opening_balance_cents"] ?? 0);
            $diff = $informed - $expected;
            $form = "";
            if ((string) $s["status"] === "opening_pending_review") {
                $form =
                    '<form method="post" class="finance-inline-form">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="review_opening"><input type="hidden" name="session_id" value="' .
                    (int) $s["id"] .
                    '"><input type="hidden" name="decision" value="approve">' .
                    input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Observação opcional"',
                    ) .
                    '<button type="submit" class="primary small">' .
                    icon("verified") .
                    '<span>Autorizar</span></button></form><form method="post" class="finance-inline-form">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="review_opening"><input type="hidden" name="session_id" value="' .
                    (int) $s["id"] .
                    '"><input type="hidden" name="decision" value="reject">' .
                    input(
                        "notes",
                        "text",
                        "",
                        'required placeholder="Motivo da recusa"',
                    ) .
                    '<button type="submit" class="danger small">' .
                    icon("block") .
                    "<span>Recusar</span></button></form>";
            }
            $openList .=
                '<article class="finance-row finance-action-row"><div><strong>' .
                e(first_name((string) $s["user_name"])) .
                " · " .
                e((string) ($s["location_name"] ?? "Gaveta")) .
                " · " .
                date_br((string) $s["business_date"]) .
                "</strong><small>Esperado " .
                money_br($expected) .
                " · Saldo informado " .
                money_br($informed) .
                " · Diferença " .
                money_br($diff) .
                "</small></div><b>" .
                money_br($informed) .
                '</b><span class="pill ' .
                ((string) $s["status"] === "opening_pending_review"
                    ? "warn"
                    : "bad") .
                '">' .
                e(financial_human_session_status((string) $s["status"])) .
                "</span>" .
                $form .
                "</article>";
        }
        $rows = q(
            "SELECT s.id,s.location_id,s.status,s.business_date,s.expected_closing_cents,s.declared_closing_cents,s.difference_cents,s.transfer_to_safe_cents,u.name user_name,l.name location_name,l.drawer_locked_business_date FROM pi_cash_sessions s JOIN pi_users u ON u.id=s.user_id LEFT JOIN pi_financial_locations l ON l.id=s.location_id AND l.clinic_id=s.clinic_id WHERE s.clinic_id=? AND s.status IN ('closed_pending_review','approved','rejected') ORDER BY FIELD(s.status,'closed_pending_review','rejected','approved'), s.business_date DESC,s.id DESC LIMIT 120",
            [$cid],
        )->fetchAll();
        $list = "";
        foreach ($rows as $s) {
            $diff = (int) $s["difference_cents"];
            $form = "";
            if ((string) $s["status"] === "closed_pending_review") {
                $unlockDefault = financial_default_drawer_unlock_local(
                    $cid,
                    (int) $s["location_id"],
                    [
                        "drawer_locked_business_date" =>
                            $s["drawer_locked_business_date"] ?? null,
                    ],
                );
                $form =
                    '<form method="post" class="finance-inline-form finance-drawer-review-form">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                    (int) $s["id"] .
                    '"><input type="hidden" name="decision" value="approve">' .
                    input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Confirme a destinação das retiradas / observação"',
                    ) .
                    input(
                        "drawer_unlock_at",
                        "datetime-local",
                        $unlockDefault,
                        'required title="Horário de destravamento da Gaveta"',
                    ) .
                    '<button type="submit" class="primary small">' .
                    icon("lock_clock") .
                    '<span>Conferir e agendar</span></button></form><form method="post" class="finance-inline-form">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                    (int) $s["id"] .
                    '"><input type="hidden" name="decision" value="reject">' .
                    input(
                        "notes",
                        "text",
                        "",
                        'required placeholder="Motivo da devolução"',
                    ) .
                    '<button type="submit" class="danger small">' .
                    icon("undo") .
                    "<span>Devolver</span></button></form>";
            }
            $list .=
                '<article class="finance-row finance-action-row"><div><strong>' .
                e(first_name((string) $s["user_name"])) .
                " · " .
                e((string) ($s["location_name"] ?? "Gaveta")) .
                " · " .
                date_br((string) $s["business_date"]) .
                "</strong><small>Esperado " .
                money_br((int) $s["expected_closing_cents"]) .
                " · Declarado " .
                money_br((int) $s["declared_closing_cents"]) .
                " · Diferença " .
                money_br($diff) .
                "</small></div><b>" .
                money_br((int) $s["transfer_to_safe_cents"]) .
                '</b><span class="pill ' .
                ((string) $s["status"] === "closed_pending_review"
                    ? "warn"
                    : "ok") .
                '">' .
                e(financial_human_session_status((string) $s["status"])) .
                "</span>" .
                $form .
                "</article>";
        }
        return card(
            '<h2>Autorizações de Abertura</h2><p class="muted">Autorize apenas quando o Atendimento justificar Saldo Inicial diferente do saldo não retirado no último fechamento.</p>' .
                ($openList ?:
                    '<div class="empty">Nenhuma abertura divergente aguardando autorização.</div>'),
            "finance-list",
        ) .
            card(
                '<h2>Fechamentos do Atendimento</h2><p class="muted">Conferir confirma a destinação das retiradas e agenda o horário de destravamento da Gaveta para o dia seguinte. Devolver mantém a Gaveta trancada.</p>' .
                    ($list ?:
                        '<div class="empty">Nenhum fechamento pendente.</div>'),
                "finance-list",
            );
    
    }

    public static function financial_location_account_id(int $cid, int $locationId): ?int
    
    {
    
        if ($cid <= 0 || $locationId <= 0) {
            return null;
        }
        try {
            $r = one(
                "SELECT account_id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
                [$locationId, $cid],
            );
            $acc = (int) ($r["account_id"] ?? 0);
            return $acc > 0 ? $acc : null;
        } catch (Throwable $e) {
            return null;
        }
    
    }

    public static function financial_admin_locations_panel(int $cid, int $uid, array $pos): string
    
    {
    
        $safe = (int) $pos["safe_id"];
        $safeCard = card(
            "<h2>" .
                icon("account_balance_wallet") .
                '<span>Cofre</span></h2><p class="muted">Local de guarda do Consultório antes de levar valores ao banco. Saldo atual: <strong>' .
                money_br((int) $pos["safe_cents"]) .
                "</strong>.</p>",
            "finance-location-card finance-safe-card",
        );
        $banks = q(
            "SELECT a.id,a.name,a.bank_name,l.id location_id FROM pi_financial_accounts a LEFT JOIN pi_financial_locations l ON l.account_id=a.id AND l.clinic_id=a.clinic_id AND l.location_type='bank_account' WHERE a.clinic_id=? AND a.active=1 AND a.account_type IN ('conta_corrente','conta_poupanca','conta_pagamento','investimento') ORDER BY a.name",
            [$cid],
        )->fetchAll();
        $opts = ["" => "Escolha a conta"];
        $resolvedBanks = [];
        foreach ($banks as $b) {
            $loc =
                (int) ($b["location_id"] ?:
                financial_ensure_bank_location($cid, (int) $b["id"], $uid));
            $opts[(int) $b["id"]] = (string) $b["name"];
            $b["resolved_location_id"] = $loc;
            $resolvedBanks[] = $b;
        }
        $bankBalances = financial_location_movement_balances(
            $cid,
            array_map(
                static  fn(array $bank): int =>
                    (int) ($bank["resolved_location_id"] ?? 0),
                $resolvedBanks,
            ),
        );
        $cards = '<div class="finance-account-cards">';
        foreach ($resolvedBanks as $b) {
            $bal = (int) (
                $bankBalances[(int) ($b["resolved_location_id"] ?? 0)] ?? 0
            );
            $cards .=
                "<article><span>" .
                icon("account_balance") .
                "</span><div><h3>" .
                e((string) $b["name"]) .
                "</h3><p>" .
                e((string) ($b["bank_name"] ?: "Conta Bancária")) .
                "</p></div><b>" .
                money_br($bal) .
                "</b></article>";
        }
        $cards .= $resolvedBanks
            ? "</div>"
            : '<div class="empty">Nenhuma conta bancária cadastrada. O Consultório pode funcionar com Gavetas e Cofre.</div>';
        $new =
            '<form method="post" class="compact finance-lite-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="bank_account"><div class="two">' .
            form_row(
                "Nome da conta",
                input(
                    "name",
                    "text",
                    "",
                    'required placeholder="Ex.: Sicredi, Banco do Brasil"',
                ),
            ) .
            form_row(
                "Banco",
                input("bank_name", "text", "", 'placeholder="Opcional"'),
            ) .
            "</div>" .
            form_actions("Cadastrar banco") .
            "</form>";
        $dep =
            '<form method="post" class="compact finance-lite-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="deposit_bank"><div class="three">' .
            select_label("Banco de destino", "account_id", $opts, "", "required") .
            form_row("Valor", financial_money_input("amount", "", "required")) .
            form_row(
                "Observação",
                input("notes", "text", "", 'placeholder="Opcional"'),
            ) .
            "</div>" .
            form_actions("Depositar do Cofre") .
            "</form>";
        $banksPanel =
            card(
                "<h2>" .
                    icon("account_balance") .
                    "<span>Bancos</span></h2>" .
                    $cards,
                "finance-accounts-panel",
            ) .
            '<div class="finance-form-stack finance-bank-actions">' .
            card(
                "<h2>" .
                    icon("add_business") .
                    '<span>Novo banco</span></h2><p class="muted">Cadastre apenas locais reais de destino para valores do Consultório.</p>' .
                    $new,
                "finance-form finance-context-form",
            ) .
            card(
                "<h2>" .
                    icon("move_down") .
                    '<span>Depósito do Cofre</span></h2><p class="muted">Move valor do Cofre para um Banco do Consultório.</p>' .
                    $dep,
                "finance-form finance-context-form",
            ) .
            "</div>";
        return '<div class="finance-workspace finance-locations-workspace">' .
            financial_admin_drawers_panel($cid, $uid) .
            financial_admin_reviews_panel($cid, $uid) .
            $safeCard .
            $banksPanel .
            "</div>";
    
    }

    public static function financial_admin_movements_panel(int $cid, int $limit = 180): string
    
    {
    
        $rows = q(
            "SELECT m.movement_type,m.title,m.created_at,m.amount_cents,m.status,lf.name from_name,lt.name to_name,u.name user_name FROM pi_financial_movements m LEFT JOIN pi_financial_locations lf ON lf.id=m.from_location_id AND lf.clinic_id=m.clinic_id LEFT JOIN pi_financial_locations lt ON lt.id=m.to_location_id AND lt.clinic_id=m.clinic_id LEFT JOIN pi_users u ON u.id=m.created_by WHERE m.clinic_id=? ORDER BY m.created_at DESC,m.id DESC LIMIT " .
                max(10, min(300, $limit)),
            [$cid],
        )->fetchAll();
        $list = "";
        foreach ($rows as $r) {
            $type = (string) $r["movement_type"];
            $path = trim(
                (string) ($r["from_name"] ?? "") .
                    " → " .
                    (string) ($r["to_name"] ?? ""),
                " →",
            );
            $list .=
                '<article class="finance-row"><div><strong>' .
                e(
                    financial_human_movement_type($type) .
                        " · " .
                        (string) $r["title"],
                ) .
                "</strong><small>" .
                dt_br((string) $r["created_at"]) .
                " · " .
                e($path ?: "sem local") .
                " · " .
                e(first_name((string) ($r["user_name"] ?? ""))) .
                "</small></div><b>" .
                money_br((int) $r["amount_cents"]) .
                '</b><span class="pill">' .
                e(financial_human_movement_status((string) $r["status"])) .
                "</span></article>";
        }
        return card(
            '<h2>Movimentos</h2><p class="muted">Registro simples das entradas, saídas e transferências do Consultório.</p>' .
                ($list ?:
                    '<div class="empty">Nenhuma movimentação registrada.</div>'),
            "finance-list finance-ledger-card",
        );
    
    }

    public static function financial_daily_drawer_partials_html(
        int $cid,
        int $uid,
        array $state,
    ): string 
    {
    
        $rows = $state["rows"] ?? [];
        if (!$rows) {
            return '<div class="empty">Nenhuma gaveta vinculada a colaborador para esta data.</div>';
        }
        $h = '<div class="finance-drawer-partials">';
        foreach ($rows as $r) {
            $sid = (int) ($r["id"] ?? 0);
            $status = (string) ($r["status"] ?? "not_started");
            $statusLabel =
                $status === "not_started"
                    ? "Aguardando fechamento"
                    : financial_human_session_status($status);
            $tot =
                $sid > 0
                    ? financial_session_movement_totals($cid, $sid)
                    : [
                        "receipt" => 0,
                        "payment" => 0,
                        "refund" => 0,
                        "transfer" => 0,
                        "deposit" => 0,
                        "adjustment" => 0,
                    ];
            $cls = in_array($status, ["closed_pending_review"], true)
                ? "warn"
                : (in_array($status, ["approved", "kept_closed"], true)
                    ? "ok"
                    : ($status === "rejected"
                        ? "bad"
                        : "muted"));
            $metrics =
                '<div class="finance-drawer-partial-metrics"><span><small>Inicial</small><b>' .
                money_br((int) ($r["opening_balance_cents"] ?? 0)) .
                "</b></span><span><small>Recebido</small><b>" .
                money_br((int) ($tot["receipt"] ?? 0)) .
                "</b></span><span><small>Pagamentos</small><b>" .
                money_br((int) ($tot["payment"] ?? 0)) .
                "</b></span><span><small>Retirada</small><b>" .
                money_br((int) ($r["transfer_to_safe_cents"] ?? 0)) .
                "</b></span><span><small>Declarado</small><b>" .
                money_br((int) ($r["declared_closing_cents"] ?? 0)) .
                "</b></span><span><small>Diferença</small><b>" .
                money_br((int) ($r["difference_cents"] ?? 0)) .
                "</b></span></div>";
            $actions = "";
            if ($sid > 0 && $status === "closed_pending_review") {
                $unlockDefault = financial_default_drawer_unlock_local(
                    $cid,
                    (int) ($r["location_id"] ?? 0),
                );
                $actions =
                    '<div class="finance-drawer-partial-actions"><form method="post" class="finance-inline-form">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                    $sid .
                    '"><input type="hidden" name="decision" value="approve">' .
                    input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Observação ou ajuste justificado"',
                    ) .
                    input(
                        "drawer_unlock_at",
                        "datetime-local",
                        $unlockDefault,
                        'required title="Horário de destravamento da Gaveta"',
                    ) .
                    '<button class="primary small" type="submit">' .
                    icon("verified") .
                    '<span>Está tudo certo</span></button></form><form method="post" class="finance-inline-form">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                    $sid .
                    '"><input type="hidden" name="decision" value="reject">' .
                    input(
                        "notes",
                        "text",
                        "",
                        'required placeholder="Justifique a correção necessária"',
                    ) .
                    '<button class="danger small" type="submit">' .
                    icon("edit_note") .
                    "<span>Corrigir</span></button></form></div>";
            }
            $h .=
                '<article class="finance-row finance-action-row finance-drawer-partial-row"><div><strong>' .
                e((string) ($r["drawer_name"] ?? "Gaveta")) .
                " · " .
                e(first_name((string) ($r["user_name"] ?? "Colaborador"))) .
                "</strong><small>" .
                e($statusLabel) .
                "</small>" .
                $metrics .
                '</div><span class="pill ' .
                $cls .
                '">' .
                e($statusLabel) .
                "</span>" .
                $actions .
                "</article>";
        }
        return $h . "</div>";
    
    }
}
