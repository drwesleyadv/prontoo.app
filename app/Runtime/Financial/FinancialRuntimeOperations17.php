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

final class FinancialRuntimeOperations17
{
    private function __construct()
    {
    }

    public static function financial_admin_page(array $c): void
    
    {
    
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        financial_operational_schema_ready();
        financial_ensure_admin_safe($cid, $uid);
        financial_seed_payment_methods($cid, $uid);
        $rawTab = (string) ($_GET["tab"] ?? "painel");
        if (
            in_array($rawTab, ["receber", "pagar", "transferir"], true) &&
            empty($_GET["op"])
        ) {
            $_GET["op"] = $rawTab;
        }
        $tab = $rawTab;
        $valid = [
            "painel",
            "locais",
            "operacoes",
            "conferencias",
            "meta",
            "consolidacao",
        ];
        if (!in_array($tab, $valid, true)) {
            $tab = "painel";
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            try {
                if ($act === "drawer_create") {
                    financial_create_drawer(
                        $cid,
                        $uid,
                        (string) ($_POST["drawer_name"] ?? ""),
                    );
                    flash("Gaveta criada.");
                    redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_rename") {
                    financial_rename_drawer(
                        $cid,
                        (int) ($_POST["drawer_id"] ?? 0),
                        $uid,
                        (string) ($_POST["drawer_name"] ?? ""),
                    );
                    flash("Gaveta renomeada.");
                    redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_assign") {
                    financial_link_drawer_user(
                        $cid,
                        (int) ($_POST["drawer_id"] ?? 0),
                        (int) ($_POST["cashier_user_id"] ?? 0),
                        $uid,
                    );
                    flash("Colaborador vinculado à Gaveta.");
                    redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_unassign") {
                    financial_unlink_drawer_user(
                        $cid,
                        (int) ($_POST["link_id"] ?? 0),
                        $uid,
                    );
                    flash("Vínculo removido.");
                    redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_deactivate") {
                    financial_deactivate_drawer(
                        $cid,
                        (int) ($_POST["drawer_id"] ?? 0),
                        $uid,
                    );
                    flash("Gaveta desativada.");
                    redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_schedule_unlock") {
                    financial_schedule_drawer_unlock(
                        $cid,
                        (int) ($_POST["drawer_id"] ?? 0),
                        $uid,
                        (string) ($_POST["drawer_unlock_at"] ?? ""),
                        mb_trim((string) ($_POST["notes"] ?? "")),
                    );
                    flash("Destravamento da Gaveta agendado.");
                    redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "goal") {
                    $target = parse_money_cents((string) ($_POST["target"] ?? "0"));
                    $share = isset($_POST["share_with_team"]) ? 1 : 0;
                    $base = (string) ($_POST["base_metric"] ?? "efetivada");
                    if (!in_array($base, ["prevista", "efetivada"], true)) {
                        $base = "efetivada";
                    }
                    $month = app_month_in_timezone($cid);
                    q(
                        "INSERT INTO pi_financial_goals (clinic_id,month_key,target_cents,base_metric,share_with_team,updated_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_cents=VALUES(target_cents), base_metric=VALUES(base_metric), share_with_team=VALUES(share_with_team), updated_by=VALUES(updated_by), updated_at=NOW()",
                        [$cid, $month, $target, $base, $share, $uid],
                    );
                    audit("meta_financeira_salva", "financeiro", $cid, [
                        "valor" => $target,
                        "base" => $base,
                        "compartilhar" => $share,
                    ]);
                    flash("Meta mensal atualizada.");
                    redirect("financial", ["tab" => "meta"]);
                }
                if ($act === "review_close") {
                    financial_review_session(
                        $cid,
                        $uid,
                        (int) ($_POST["session_id"] ?? 0),
                        (string) ($_POST["decision"] ?? "approve"),
                        mb_trim((string) ($_POST["notes"] ?? "")),
                        (string) ($_POST["drawer_unlock_at"] ?? ""),
                    );
                    flash("Conferência registrada.");
                    redirect("financial", ["tab" => "conferencias"]);
                }
                if ($act === "review_opening") {
                    financial_review_opening_request(
                        $cid,
                        $uid,
                        (int) ($_POST["session_id"] ?? 0),
                        (string) ($_POST["decision"] ?? "approve"),
                        mb_trim((string) ($_POST["notes"] ?? "")),
                    );
                    flash("Autorização de abertura registrada.");
                    redirect("financial", ["tab" => "conferencias"]);
                }
                if ($act === "daily_consolidate") {
                    financial_admin_daily_consolidate($cid, $uid);
                    flash("Consolidação do dia registrada.");
                    redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "admin_receive") {
                    financial_admin_save_receipt($cid, $uid);
                    flash("Recebimento registrado.");
                    redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "admin_payment") {
                    financial_admin_save_payment($cid, $uid);
                    flash("Pagamento registrado.");
                    redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "admin_transfer") {
                    financial_admin_save_transfer($cid, $uid);
                    flash("Transferência registrada.");
                    redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "safe_payment") {
                    financial_admin_save_payment($cid, $uid);
                    flash("Pagamento registrado.");
                    redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "safe_receipt") {
                    financial_admin_save_receipt($cid, $uid);
                    flash("Recebimento registrado.");
                    redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "bank_account") {
                    $name = mb_trim((string) ($_POST["name"] ?? ""));
                    if ($name === "") {
                        throw new RuntimeException(
                            "Informe o nome da conta bancária.",
                        );
                    }
                    q(
                        "INSERT INTO pi_financial_accounts (clinic_id,name,bank_name,account_type,opening_balance_cents,active,created_by,created_at) VALUES (?,?,?,?,0,1,?,NOW())",
                        [
                            $cid,
                            $name,
                            mb_trim((string) ($_POST["bank_name"] ?? "")),
                            "conta_corrente",
                            $uid,
                        ],
                    );
                    $acc = db_last_insert_id();
                    financial_ensure_bank_location($cid, $acc, $uid);
                    audit("conta_bancaria_criada", "financeiro", $acc, [
                        "name" => $name,
                    ]);
                    flash("Banco cadastrado.");
                    redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "deposit_bank") {
                    $safe = financial_ensure_admin_safe($cid, $uid);
                    $acc = (int) ($_POST["account_id"] ?? 0);
                    $bank = financial_ensure_bank_location($cid, $acc, $uid);
                    $amount = parse_money_cents((string) ($_POST["amount"] ?? "0"));
                    if ($bank <= 0) {
                        throw new RuntimeException(
                            "Escolha uma conta bancária válida.",
                        );
                    }
                    financial_create_movement(
                        $cid,
                        "deposit",
                        $amount,
                        $safe,
                        $bank,
                        null,
                        $uid,
                        "Depósito em conta bancária",
                        "transferencia",
                        mb_trim((string) ($_POST["notes"] ?? "")),
                    );
                    flash("Depósito registrado.");
                    redirect("financial", ["tab" => "locais"]);
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo financeiro administrativo] " . $e->getMessage(),
                );
                flash(
                    app_public_error_message(
                        $e,
                        "Não foi possível concluir a operação financeira.",
                    ),
                    "bad",
                );
                redirect("financial", ["tab" => $tab]);
            }
        }
        $pos = financial_global_position($cid);
        $tabs = financial_tabs_html(
            [
                "painel" => ["Painel", "monitoring"],
                "locais" => ["Locais", "account_balance_wallet"],
                "operacoes" => ["Operações", "payments"],
                "conferencias" => ["Conferências", "fact_check"],
                "meta" => ["Meta", "flag"],
            ],
            $tab,
        );
        $content = "";
        if ($tab === "painel") {
            $content =
                financial_admin_balance_kpis_html($pos) .
                financial_admin_daily_consolidation_html($cid) .
                card(
                    "<h2>" .
                        icon("receipt_long") .
                        '<span>Movimentos do dia</span></h2><p class="muted">O que entrou, saiu ou foi transferido hoje.</p>' .
                        financial_admin_daily_ledger_timeline($cid),
                    "finance-ledger-card finance-panel-ledger-card",
                );
        } elseif ($tab === "consolidacao") {
            $content = financial_admin_daily_conference_panel($cid, $uid);
        } elseif ($tab === "locais") {
            $content = financial_admin_locations_panel($cid, $uid, $pos);
        } elseif ($tab === "operacoes") {
            $content = financial_admin_operations_panel($cid, $uid);
        } elseif ($tab === "conferencias") {
            $content = financial_admin_conferences_panel($cid, $uid);
        } elseif ($tab === "meta") {
            $st = monthly_goal_status($cid);
            $goalPreview =
                '<div class="finance-goal-progress"><div><strong data-goal-percent>' .
                e(number_format((float) $st["percent"], 1, ",", ".")) .
                "%</strong><span data-goal-values>" .
                e(
                    (string) ($st["values"] ??
                        money_br((int) ($st["done_cents"] ?? 0)) .
                            " de " .
                            money_br((int) ($st["target_cents"] ?? 0))),
                ) .
                '</span></div><div class="goal-bar"><i data-goal-bar style="width:' .
                max(0, min(100, (float) $st["percent"])) .
                '%"></i></div><small>Base atual: ' .
                e($st["base_label"]) .
                ".</small></div>";
            $goalForm =
                '<form method="post" id="finance-goal-form" class="compact finance-lite-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="goal"><div class="two">' .
                form_row(
                    "Meta mensal gerencial",
                    input(
                        "target",
                        "text",
                        $st["target_cents"] > 0
                            ? money_br($st["target_cents"])
                            : "",
                        'inputmode="decimal" placeholder="R$ 0,00"',
                    ),
                ) .
                select_label(
                    "Base da meta",
                    "base_metric",
                    financial_goal_base_options(),
                    $st["base_metric"],
                ) .
                '</div><label class="checkline"><input type="checkbox" name="share_with_team" value="1" ' .
                ($st["share"] ? "checked" : "") .
                "><span>Compartilhar percentual cumprido da meta com a equipe.</span></label>" .
                form_actions("Salvar meta") .
                "</form>";
            $content = card(
                '<h2>Meta</h2><p class="muted">Acompanhe o mês com uma meta simples, sem transformar o Financeiro do Consultório em sistema contábil avançado.</p>' .
                    $goalPreview .
                    $goalForm,
                "finance-report-card finance-goal-card-panel",
            );
        }
        page(
            "Financeiro",
            page_head(
                "Painel financeiro",
                "Guia operacional para acompanhar o dia, registrar movimentos e conferir os locais do Consultório.",
                $tabs,
            ) . $content,
        );
    
    }

    public static function page_financial(): void
    
    {
    
        $c = require_can("financial");
        ensure_financial_operational_schema();
        if (financial_is_cashier($c)) {
            financial_cashier_page($c);
            return;
        }
        financial_admin_page($c);
    
    }
}
