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

final class FinancialRuntimeOperations13
{
    private function __construct()
    {
    }

    public static function financial_admin_reviews_panel(int $cid, int $uid): string
    
    {
    
        $openRows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.13.admin_reviews_panel.01", [$cid], [])->fetchAll();
        $openList = "";
        foreach ($openRows as $s) {
            $expected = (int) ($s["expected_closing_cents"] ?? 0);
            $informed = (int) ($s["opening_balance_cents"] ?? 0);
            $diff = $informed - $expected;
            $form = "";
            if ((string) $s["status"] === "opening_pending_review") {
                $form =
                    '<form method="post" class="finance-inline-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="review_opening"><input type="hidden" name="session_id" value="' .
                    (int) $s["id"] .
                    '"><input type="hidden" name="decision" value="approve">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Observação opcional"',
                    ) .
                    '<button type="submit" class="primary small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified") .
                    '<span>Autorizar</span></button></form><form method="post" class="finance-inline-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="review_opening"><input type="hidden" name="session_id" value="' .
                    (int) $s["id"] .
                    '"><input type="hidden" name="decision" value="reject">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "notes",
                        "text",
                        "",
                        'required placeholder="Motivo da recusa"',
                    ) .
                    '<button type="submit" class="danger small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("block") .
                    "<span>Recusar</span></button></form>";
            }
            $openList .=
                '<article class="finance-row finance-action-row"><div><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) $s["user_name"])) .
                " · " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($s["location_name"] ?? "Gaveta")) .
                " · " .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $s["business_date"]) .
                "</strong><small>Esperado " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected) .
                " · Saldo informado " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($informed) .
                " · Diferença " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($diff) .
                "</small></div><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($informed) .
                '</b><span class="pill ' .
                ((string) $s["status"] === "opening_pending_review"
                    ? "warn"
                    : "bad") .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_session_status((string) $s["status"])) .
                "</span>" .
                $form .
                "</article>";
        }
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.13.admin_reviews_panel.02", [$cid], [])->fetchAll();
        $list = "";
        foreach ($rows as $s) {
            $diff = (int) $s["difference_cents"];
            $form = "";
            if ((string) $s["status"] === "closed_pending_review") {
                $unlockDefault = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_default_drawer_unlock_local(
                    $cid,
                    (int) $s["location_id"],
                    [
                        "drawer_locked_business_date" =>
                            $s["drawer_locked_business_date"] ?? null,
                    ],
                );
                $form =
                    '<form method="post" class="finance-inline-form finance-drawer-review-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                    (int) $s["id"] .
                    '"><input type="hidden" name="decision" value="approve">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Confirme a destinação das retiradas / observação"',
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "drawer_unlock_at",
                        "datetime-local",
                        $unlockDefault,
                        'required title="Horário de destravamento da Gaveta"',
                    ) .
                    '<button type="submit" class="primary small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lock_clock") .
                    '<span>Conferir e agendar</span></button></form><form method="post" class="finance-inline-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                    (int) $s["id"] .
                    '"><input type="hidden" name="decision" value="reject">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "notes",
                        "text",
                        "",
                        'required placeholder="Motivo da devolução"',
                    ) .
                    '<button type="submit" class="danger small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("undo") .
                    "<span>Devolver</span></button></form>";
            }
            $list .=
                '<article class="finance-row finance-action-row"><div><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) $s["user_name"])) .
                " · " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($s["location_name"] ?? "Gaveta")) .
                " · " .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $s["business_date"]) .
                "</strong><small>Esperado " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $s["expected_closing_cents"]) .
                " · Declarado " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $s["declared_closing_cents"]) .
                " · Diferença " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($diff) .
                "</small></div><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $s["transfer_to_safe_cents"]) .
                '</b><span class="pill ' .
                ((string) $s["status"] === "closed_pending_review"
                    ? "warn"
                    : "ok") .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_session_status((string) $s["status"])) .
                "</span>" .
                $form .
                "</article>";
        }
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<h2>Autorizações de Abertura</h2><p class="muted">Autorize apenas quando o Atendimento justificar Saldo Inicial diferente do saldo não retirado no último fechamento.</p>' .
                ($openList ?:
                    '<div class="empty">Nenhuma abertura divergente aguardando autorização.</div>'),
            "finance-list",
        ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
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
            $r = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.13.location_account_id.01", [$locationId, $cid], []);
            $acc = (int) ($r["account_id"] ?? 0);
            return $acc > 0 ? $acc : null;
        } catch (Throwable $e) {
            return null;
        }
    
    }

    public static function financial_admin_locations_panel(int $cid, int $uid, array $pos): string
    
    {
    
        $safe = (int) $pos["safe_id"];
        $safeCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            "<h2>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("account_balance_wallet") .
                '<span>Cofre</span></h2><p class="muted">Local de guarda do Consultório antes de levar valores ao banco. Saldo atual: <strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $pos["safe_cents"]) .
                "</strong>.</p>",
            "finance-location-card finance-safe-card",
        );
        $banks = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.13.admin_locations_panel.01", [$cid], [])->fetchAll();
        $opts = ["" => "Escolha a conta"];
        $resolvedBanks = [];
        foreach ($banks as $b) {
            $loc =
                (int) ($b["location_id"] ?:
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_bank_location($cid, (int) $b["id"], $uid));
            $opts[(int) $b["id"]] = (string) $b["name"];
            $b["resolved_location_id"] = $loc;
            $resolvedBanks[] = $b;
        }
        $bankBalances = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_location_movement_balances(
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("account_balance") .
                "</span><div><h3>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $b["name"]) .
                "</h3><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($b["bank_name"] ?: "Conta Bancária")) .
                "</p></div><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($bal) .
                "</b></article>";
        }
        $cards .= $resolvedBanks
            ? "</div>"
            : '<div class="empty">Nenhuma conta bancária cadastrada. O Consultório pode funcionar com Gavetas e Cofre.</div>';
        $new =
            '<form method="post" class="compact finance-lite-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="bank_account"><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome da conta",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "name",
                    "text",
                    "",
                    'required placeholder="Ex.: Sicredi, Banco do Brasil"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Banco",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("bank_name", "text", "", 'placeholder="Opcional"'),
            ) .
            "</div>" .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Cadastrar banco") .
            "</form>";
        $dep =
            '<form method="post" class="compact finance-lite-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="deposit_bank"><div class="three">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label("Banco de destino", "account_id", $opts, "", "required") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Valor", \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input("amount", "", "required")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Observação",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("notes", "text", "", 'placeholder="Opcional"'),
            ) .
            "</div>" .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Depositar do Cofre") .
            "</form>";
        $banksPanel =
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                "<h2>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("account_balance") .
                    "<span>Bancos</span></h2>" .
                    $cards,
                "finance-accounts-panel",
            ) .
            '<div class="finance-form-stack finance-bank-actions">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                "<h2>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("add_business") .
                    '<span>Novo banco</span></h2><p class="muted">Cadastre apenas locais reais de destino para valores do Consultório.</p>' .
                    $new,
                "finance-form finance-context-form",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                "<h2>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("move_down") .
                    '<span>Depósito do Cofre</span></h2><p class="muted">Move valor do Cofre para um Banco do Consultório.</p>' .
                    $dep,
                "finance-form finance-context-form",
            ) .
            "</div>";
        return '<div class="finance-workspace finance-locations-workspace">' .
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations12::financial_admin_drawers_panel($cid, $uid) .
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_admin_reviews_panel($cid, $uid) .
            $safeCard .
            $banksPanel .
            "</div>";
    
    }

    public static function financial_admin_movements_panel(int $cid, int $limit = 180): string
    
    {
    
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.13.admin_movements_panel.01", [$cid], compact('limit'))->fetchAll();
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_movement_type($type) .
                        " · " .
                        (string) $r["title"],
                ) .
                "</strong><small>" .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) $r["created_at"]) .
                " · " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($path ?: "sem local") .
                " · " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($r["user_name"] ?? ""))) .
                "</small></div><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["amount_cents"]) .
                '</b><span class="pill">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_movement_status((string) $r["status"])) .
                "</span></article>";
        }
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
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
                    : \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_session_status($status);
            $tot =
                $sid > 0
                    ? \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_session_movement_totals($cid, $sid)
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) ($r["opening_balance_cents"] ?? 0)) .
                "</b></span><span><small>Recebido</small><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) ($tot["receipt"] ?? 0)) .
                "</b></span><span><small>Pagamentos</small><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) ($tot["payment"] ?? 0)) .
                "</b></span><span><small>Retirada</small><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) ($r["transfer_to_safe_cents"] ?? 0)) .
                "</b></span><span><small>Declarado</small><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) ($r["declared_closing_cents"] ?? 0)) .
                "</b></span><span><small>Diferença</small><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) ($r["difference_cents"] ?? 0)) .
                "</b></span></div>";
            $actions = "";
            if ($sid > 0 && $status === "closed_pending_review") {
                $unlockDefault = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_default_drawer_unlock_local(
                    $cid,
                    (int) ($r["location_id"] ?? 0),
                );
                $actions =
                    '<div class="finance-drawer-partial-actions"><form method="post" class="finance-inline-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                    $sid .
                    '"><input type="hidden" name="decision" value="approve">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Observação ou ajuste justificado"',
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "drawer_unlock_at",
                        "datetime-local",
                        $unlockDefault,
                        'required title="Horário de destravamento da Gaveta"',
                    ) .
                    '<button class="primary small" type="submit">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified") .
                    '<span>Está tudo certo</span></button></form><form method="post" class="finance-inline-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                    $sid .
                    '"><input type="hidden" name="decision" value="reject">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "notes",
                        "text",
                        "",
                        'required placeholder="Justifique a correção necessária"',
                    ) .
                    '<button class="danger small" type="submit">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_note") .
                    "<span>Corrigir</span></button></form></div>";
            }
            $h .=
                '<article class="finance-row finance-action-row finance-drawer-partial-row"><div><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($r["drawer_name"] ?? "Gaveta")) .
                " · " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($r["user_name"] ?? "Colaborador"))) .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($statusLabel) .
                "</small>" .
                $metrics .
                '</div><span class="pill ' .
                $cls .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($statusLabel) .
                "</span>" .
                $actions .
                "</article>";
        }
        return $h . "</div>";
    
    }
}
