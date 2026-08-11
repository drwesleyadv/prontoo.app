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

final class FinancialRuntimeOperations11
{
    private function __construct()
    {
    }

    public static function financial_cashier_page(array $c): void
    
    {
    
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        $drawerId = 0;
        try {
            \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
            $drawerId = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_location_for_user($cid, $uid);
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_admin_safe($cid, $uid);
        } catch (Throwable $e) {
            \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_cash_store_debug(
                $e,
                $c,
                (string) ($_POST["act"] ?? "preparo"),
            );
            $msg =
                "Não foi possível preparar sua Gaveta. O erro foi registrado no log técnico do sistema.";
            if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($msg, "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial");
            }
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Caixa",
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Minha Gaveta", "") .
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cash_debug_error_html() .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                        '<h2>Revisão da Gaveta necessária</h2><p class="muted">' .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($msg) .
                            "</p>",
                        "finance-alert-card",
                    ),
            );
            return;
        }
        if ($drawerId <= 0) {
            $body =
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cash_debug_error_html() .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<h2>Você não é responsável por nenhuma gaveta ainda.</h2><p class="muted">Aguarde até que receba autorização para gerenciar gavetas.</p>',
                    "finance-alert-card",
                );
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Caixa", \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Minha Gaveta", "") . $body);
            return;
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            try {
                if ($act === "cash_open") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_open_session(
                        $cid,
                        $uid,
                        \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents(
                            (string) ($_POST["opening_balance"] ?? "0"),
                        ),
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Gaveta aberta para movimentação.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial");
                }
                if ($act === "cash_keep_closed") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_keep_closed($cid, $uid);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Gaveta mantida fechada. Você pode abri-la mais tarde, se houver movimento.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial");
                }
                if ($act === "cash_receipt") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_receive_expected_appointment_revenue(
                        $cid,
                        $uid,
                        (int) ($_POST["revenue_id"] ?? 0),
                        (string) ($_POST["payment_method"] ?? ""),
                        (int) ($_POST["destination_location_id"] ?? 0),
                        mb_trim((string) ($_POST["notes"] ?? "")),
                    );
                    $method = \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method(
                        (string) ($_POST["payment_method"] ?? ""),
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        $method === "dinheiro"
                            ? "Valor recebido em dinheiro e guardado na sua Gaveta."
                            : "Valor recebido fora da Gaveta e enviado ao destino do Consultório.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["op" => "receber"]);
                }
                if ($act === "cash_payment") {
                    $s = \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_require_open_session($cid, $uid);
                    $amount = \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents((string) ($_POST["amount"] ?? "0"));
                    $title = trim(
                        (string) ($_POST["title"] ?? "Pagamento do Atendimento"),
                    );
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_create_movement(
                        $cid,
                        "payment",
                        $amount,
                        (int) $s["location_id"],
                        null,
                        (int) $s["id"],
                        $uid,
                        $title,
                        "dinheiro",
                        mb_trim((string) ($_POST["notes"] ?? "")),
                        "pending_review",
                        "cash_session",
                        (int) $s["id"],
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Pagamento em dinheiro registrado como saída da sua Gaveta para conferência.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["op" => "pagar"]);
                }
                if ($act === "cash_close") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_close_session(
                        $cid,
                        $uid,
                        (int) ($_POST["session_id"] ?? 0),
                        \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents(
                            (string) ($_POST["declared_balance"] ?? "0"),
                        ),
                        \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents(
                            (string) ($_POST["withdrawal_amount"] ??
                                ($_POST["transfer_to_safe"] ?? "0")),
                        ),
                        (int) ($_POST["withdrawal_destination_location_id"] ?? 0),
                        mb_trim((string) ($_POST["notes"] ?? "")),
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Gaveta enviada para conferência da Gerência.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial");
                }
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (
                    $act === "cash_open" &&
                    str_starts_with($msg, "O Saldo Inicial informado não coincide")
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($msg, "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial");
                }
                \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_cash_store_debug($e, $c, $act);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Não foi possível concluir a ação da Gaveta. O erro foi registrado no log técnico do sistema.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial");
            }
        }
        $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        $drawerState = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_auto_unlock_if_due($cid, $drawerId);
        $drawerLocked =
            $drawerState &&
            (string) ($drawerState["drawer_lock_status"] ?? "unlocked") ===
                "locked";
        $prev = \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_unclosed_previous_session($cid, $uid, $today);
        $session = \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_session_for_date($cid, $uid, $today);
        $sessionStatus = $session ? (string) $session["status"] : "";
        $canOpen =
            !$drawerLocked &&
            !$prev &&
            (!$session ||
                in_array(
                    $sessionStatus,
                    ["kept_closed", "opening_rejected"],
                    true,
                ));
        $canPayReceive = !$prev && $session && $sessionStatus === "open";
        $canClose = (bool) $prev || $canPayReceive;
        $op = (string) ($_GET["op"] ?? "");
        if (!in_array($op, ["abrir", "pagar", "receber", "fechar"], true)) {
            $op = "";
        }
        $preselectRevenue = (int) ($_GET["revenue_id"] ?? 0);
        $preselectAppointment = (int) ($_GET["appointment_id"] ?? 0);
        if ($op === "abrir" && !$canOpen) {
            $op = "";
        }
        if (($op === "pagar" || $op === "receber") && !$canPayReceive) {
            $op = "";
        }
        if ($op === "fechar" && !$canClose) {
            $op = "";
        }
        if (
            $op === "receber" &&
            $canPayReceive &&
            $preselectRevenue <= 0 &&
            $preselectAppointment > 0
        ) {
            $preselectRevenue = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_revenue_id_for_appointment(
                $cid,
                $preselectAppointment,
                $uid,
            );
        }
        $actions = \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cashier_pagehead_actions(
            $canOpen,
            $canPayReceive,
            $canClose,
            $op,
        );
        $fallbackOpening = 0;
        $body = \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cash_debug_error_html();
        $body .= \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cashier_drawer_summary(
            $session,
            $prev,
            $fallbackOpening,
            $drawerState ?: null,
        );
        if ($drawerLocked) {
            $unlock = mb_trim((string) ($drawerState["drawer_unlock_at"] ?? ""));
            $lockedMsg =
                $unlock !== ""
                    ? "A Gaveta está trancada para conferência da Gerência e será destrancada em <strong>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($unlock)) .
                        "</strong>."
                    : "A Gaveta está trancada para conferência da Gerência. Aguarde o destravamento para abri-la.";
            $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Gaveta trancada</h2><p class="muted">' . $lockedMsg . "</p>",
                "finance-alert-card",
            );
        }
        if ($drawerLocked && !$prev) {
        } elseif ($prev) {
            $expected = \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_session_expected($prev);
            if ($op === "fechar") {
                $withdrawalDestOptions = \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_office_destination_options(
                    $cid,
                    $uid,
                );
                $form =
                    '<form method="post" class="compact finance-lite-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="cash_close"><input type="hidden" name="session_id" value="' .
                    (int) $prev["id"] .
                    '"><p class="muted">Existe uma Gaveta aberta em ' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $prev["business_date"]) .
                    '. Feche esta Gaveta antes de abrir uma nova movimentação.</p><div class="three">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Valor esperado",
                        '<strong class="finance-big-value">' .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected) .
                            "</strong>",
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Dinheiro contado",
                        \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input(
                            "declared_balance",
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected),
                            "required",
                        ),
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Fazer Retirada",
                        \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input(
                            "withdrawal_amount",
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected),
                            "required",
                        ),
                    ) .
                    "</div>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                        "Destino da Retirada",
                        "withdrawal_destination_location_id",
                        $withdrawalDestOptions,
                        "",
                        "required",
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Observação",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "notes",
                            "text",
                            "",
                            'placeholder="Explique diferenças, se houver"',
                        ),
                    ) .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Fechar Gaveta") .
                    "</form>";
                $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    "<h2>Fechar Gaveta</h2>" . $form,
                    "finance-form finance-alert-card",
                );
            } else {
                $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<h2>Gaveta anterior pendente</h2><p class="muted">Há uma Gaveta de ' .
                        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $prev["business_date"]) .
                        " aberta. Use <strong>Fechar Gaveta</strong> no cabeçalho para regularizar antes de abrir ou movimentar a Gaveta de hoje.</p>",
                    "finance-alert-card",
                );
            }
        } elseif ($session && $sessionStatus === "opening_pending_review") {
            $informed = (int) ($session["opening_balance_cents"] ?? 0);
            $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Abertura da Gaveta aguardando autorização</h2><p class="muted">O valor informado não coincidiu com o controle da Gaveta. A Gerência já recebeu aviso para autorizar ou recusar a abertura.</p><div class="finance-mini-grid"><span>Informado <b>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($informed) .
                    "</b></span><span>Situação <b>Aguardando autorização</b></span></div>",
                "finance-alert-card",
            );
        } elseif (
            !$session ||
            in_array($sessionStatus, ["kept_closed", "opening_rejected"], true)
        ) {
            $keptToday = $session && $sessionStatus === "kept_closed";
            $rejectedToday = $session && $sessionStatus === "opening_rejected";
            $suggest = $keptToday
                ? (int) ($session["opening_balance_cents"] ?? 0)
                : ($rejectedToday
                    ? (int) ($session["expected_closing_cents"] ?? $fallbackOpening)
                    : $fallbackOpening);
            if ($keptToday && $op !== "abrir") {
                $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<h2>Gaveta mantida fechada</h2><p class="muted">A Gaveta permanece fechada. Se houver movimento hoje, use <strong>Abrir Gaveta</strong> no cabeçalho e informe o dinheiro encontrado fisicamente.</p>',
                    "finance-dashboard-card",
                );
            }
            if ($rejectedToday && $op !== "abrir") {
                $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<h2>Abertura da Gaveta recusada</h2><p class="muted">A Gerência recusou a abertura com valor diferente. Use <strong>Abrir Gaveta</strong> e informe novamente o valor encontrado fisicamente, ou solicite nova autorização.</p>',
                    "finance-alert-card",
                );
            }
            if ($op === "abrir") {
                $openActions =
                    '<div class="form-actions"><button type="submit" class="primary">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("playlist_add_check") .
                    "<span>Abrir Gaveta</span></button></div>";
                $openHint =
                    "Conte o dinheiro físico da Gaveta e informe o valor encontrado. O sistema valida internamente se coincide com o último saldo não retirado, sem revelar o valor anterior.";
                $openForm =
                    '<form method="post" class="compact finance-lite-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="cash_open"><p class="muted">' .
                    $openHint .
                    "</p>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Dinheiro encontrado na Gaveta",
                        \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input(
                            "opening_balance",
                            "",
                            'required placeholder="R$ 0,00"',
                        ),
                    ) .
                    $openActions .
                    "</form>";
                $keepForm = "";
                if (!$keptToday) {
                    $keepForm =
                        '<form method="post" class="compact finance-lite-form cash-secondary-form">' .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        '<input type="hidden" name="act" value="cash_keep_closed"><p class="muted">Sem movimento agora? Mantenha a Gaveta fechada e abra mais tarde, se necessário.</p><div class="form-actions"><button type="submit" class="ghost">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lock") .
                        "<span>Manter Gaveta fechada</span></button></div></form>";
                }
                $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    "<h2>Abrir Gaveta</h2>" . $openForm . $keepForm,
                    "finance-form",
                );
            } else {
                $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<h2>Gaveta fechada</h2><p class="muted">Use <strong>Abrir Gaveta</strong> no cabeçalho para iniciar o uso da Gaveta no Atendimento.</p>',
                    "finance-dashboard-card",
                );
            }
        } else {
            $expected =
                $sessionStatus === "open"
                    ? \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_session_expected($session)
                    : (int) ($session["declared_closing_cents"] ?? 0);
            if ($canPayReceive && $op === "receber") {
                $revenueOptions = \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_expected_appointment_revenue_options(
                    $cid,
                );
                $destOptions = \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_office_destination_options($cid, $uid);
                $pendingPanel = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_cashier_pending_receipts_html($cid);
                if (count($revenueOptions) <= 1) {
                    $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                        '<h2>Receber atendimento</h2><p class="muted">Quando o profissional concluir uma consulta com valor pendente, ela aparecerá aqui para baixa simples pela Recepção.</p>' .
                            $pendingPanel,
                        "finance-form",
                    );
                } else {
                    $receipt =
                        '<form method="post" class="compact finance-lite-form">' .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        '<input type="hidden" name="act" value="cash_receipt"><p class="muted">Baixe aqui o valor de um atendimento. Dinheiro físico fica na sua Gaveta; PIX, cartão ou transferência seguem para o destino do Consultório e não entram na contagem da Gaveta.</p>' .
                        $pendingPanel .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                            "Atendimento",
                            "revenue_id",
                            $revenueOptions,
                            $preselectRevenue ?: "",
                            "required",
                        ) .
                        '<div class="two">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                            "Forma",
                            "payment_method",
                            \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_cashier_receipt_method_options(),
                            "dinheiro",
                            "required",
                        ) .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                            "Destino se não for Dinheiro",
                            "destination_location_id",
                            $destOptions,
                            "",
                        ) .
                        "</div>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                            "Observação",
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("notes", "text", "", 'placeholder="Opcional"'),
                        ) .
                        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Confirmar recebimento") .
                        "</form>";
                    $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                        "<h2>Receber atendimento</h2>" . $receipt,
                        "finance-form",
                    );
                }
            }
            if ($canPayReceive && $op === "pagar") {
                $payment =
                    '<form method="post" class="compact finance-lite-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="cash_payment"><input type="hidden" name="payment_method" value="dinheiro"><p class="muted">Use esta ação somente para pagamento autorizado em dinheiro. O valor sai fisicamente da sua Gaveta e compõe a conferência do fechamento.</p><div class="two">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Descrição",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "title",
                            "text",
                            "",
                            'required placeholder="Ex.: devolução, pagamento autorizado"',
                        ),
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Valor",
                        \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input(
                            "amount",
                            "",
                            'required placeholder="R$ 0,00"',
                        ),
                    ) .
                    '</div><div class="two">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Forma",
                        '<strong class="finance-big-value">Dinheiro</strong>',
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Observação",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "notes",
                            "text",
                            "",
                            'required placeholder="Obrigatório para pagamentos"',
                        ),
                    ) .
                    "</div>" .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Confirmar pagamento", "danger") .
                    "</form>";
                $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card("<h2>Paguei</h2>" . $payment, "finance-form");
            }
            if ($canClose && $op === "fechar") {
                $withdrawalDestOptions = \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_office_destination_options(
                    $cid,
                    $uid,
                );
                $close =
                    '<form method="post" class="compact finance-lite-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="cash_close"><input type="hidden" name="session_id" value="' .
                    (int) $session["id"] .
                    '"><div class="three">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Esperado",
                        '<strong class="finance-big-value">' .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected) .
                            "</strong>",
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Dinheiro contado",
                        \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input(
                            "declared_balance",
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected),
                            "required",
                        ),
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Fazer Retirada",
                        \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input(
                            "withdrawal_amount",
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($expected),
                            "required",
                        ),
                    ) .
                    "</div>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                        "Destino da Retirada",
                        "withdrawal_destination_location_id",
                        $withdrawalDestOptions,
                        "",
                        "required",
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Observação da Gaveta",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "notes",
                            "text",
                            "",
                            'placeholder="Informe diferenças ou deixe em branco"',
                        ),
                    ) .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Fechar Gaveta") .
                    "</form>";
                $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card("<h2>Fechar Gaveta</h2>" . $close, "finance-form");
            }
            if ($sessionStatus !== "open") {
                $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    '<h2>Gaveta fechada</h2><p class="muted">A Gaveta de hoje já foi fechada ou ainda está em conferência.</p>',
                    "finance-dashboard-card",
                );
            }
        }
        $extractSession = $prev ?: $session;
        $list = "";
        if ($extractSession) {
            $extractDate = (string) ($extractSession["business_date"] ?? $today);
            $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.11.cashier_page.01", [$cid, (int) $extractSession["id"], $uid, $extractDate], [])->fetchAll();
            foreach ($rows as $r) {
                $type = (string) $r["movement_type"];
                $outside = (int) ($r["cash_session_id"] ?? 0) <= 0;
                $place = $outside
                    ? " · fora da gaveta" .
                        (!empty($r["to_name"])
                            ? " · " . (string) $r["to_name"]
                            : "")
                    : "";
                $isWithdrawal =
                    $type === "transfer" &&
                    (string) ($r["source_entity"] ?? "") === "cash_session";
                $iconClass = $isWithdrawal
                    ? "withdrawal"
                    : (in_array($type, ["receipt", "payment"], true)
                        ? $type
                        : "other");
                $rowClass = $isWithdrawal
                    ? "withdrawal"
                    : (in_array($type, ["receipt", "payment"], true)
                        ? $type
                        : "other");
                $movementLabel = $isWithdrawal
                    ? "Retirada"
                    : \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_movement_type($type);
                $movementIcon = $isWithdrawal
                    ? "move_up"
                    : \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_icon($type);
                $status = (string) $r["status"];
                $statusHtml =
                    $status === "confirmed"
                        ? '<span class="pill ok status-icon-only" title="Confirmado" aria-label="Confirmado">' .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check_circle") .
                            "</span>"
                        : '<span class="pill ' .
                            ($status === "pending_review" ? "warn" : "ok") .
                            '">' .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_movement_status($status)) .
                            "</span>";
                $list .=
                    '<article class="finance-row finance-extract-row finance-extract-' .
                    $rowClass .
                    '"><span class="finance-movement-icon ' .
                    $iconClass .
                    '" aria-hidden="true">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($movementIcon) .
                    "</span><div><strong>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($movementLabel . " · " . (string) $r["title"]) .
                    "</strong><small>" .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) $r["created_at"]) .
                    " · " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($r["payment_method"] ?: "sem forma") . $place) .
                    "</small></div><b>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["amount_cents"]) .
                    "</b>" .
                    $statusHtml .
                    "</article>";
            }
        }
        $body .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            "<h2>Movimento da Gaveta</h2>" .
                ($list ?:
                    '<div class="empty">Nenhum movimento registrado na Gaveta hoje.</div>'),
            "finance-list",
        );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Caixa", \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Minha Gaveta", "", $actions) . $body);
    
    }
}
