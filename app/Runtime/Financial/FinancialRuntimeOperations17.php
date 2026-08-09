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
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_admin_safe($cid, $uid);
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_seed_payment_methods($cid, $uid);
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
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_create_drawer(
                        $cid,
                        $uid,
                        (string) ($_POST["drawer_name"] ?? ""),
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Gaveta criada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_rename") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_rename_drawer(
                        $cid,
                        (int) ($_POST["drawer_id"] ?? 0),
                        $uid,
                        (string) ($_POST["drawer_name"] ?? ""),
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Gaveta renomeada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_assign") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_link_drawer_user(
                        $cid,
                        (int) ($_POST["drawer_id"] ?? 0),
                        (int) ($_POST["cashier_user_id"] ?? 0),
                        $uid,
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Colaborador vinculado à Gaveta.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_unassign") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_unlink_drawer_user(
                        $cid,
                        (int) ($_POST["link_id"] ?? 0),
                        $uid,
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Vínculo removido.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_deactivate") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_deactivate_drawer(
                        $cid,
                        (int) ($_POST["drawer_id"] ?? 0),
                        $uid,
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Gaveta desativada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "drawer_schedule_unlock") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_schedule_drawer_unlock(
                        $cid,
                        (int) ($_POST["drawer_id"] ?? 0),
                        $uid,
                        (string) ($_POST["drawer_unlock_at"] ?? ""),
                        mb_trim((string) ($_POST["notes"] ?? "")),
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Destravamento da Gaveta agendado.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "goal") {
                    $target = \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents((string) ($_POST["target"] ?? "0"));
                    $share = isset($_POST["share_with_team"]) ? 1 : 0;
                    $base = (string) ($_POST["base_metric"] ?? "efetivada");
                    if (!in_array($base, ["prevista", "efetivada"], true)) {
                        $base = "efetivada";
                    }
                    $month = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_month_in_timezone($cid);
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "INSERT INTO pi_financial_goals (clinic_id,month_key,target_cents,base_metric,share_with_team,updated_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_cents=VALUES(target_cents), base_metric=VALUES(base_metric), share_with_team=VALUES(share_with_team), updated_by=VALUES(updated_by), updated_at=NOW()",
                        [$cid, $month, $target, $base, $share, $uid],
                    );
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("meta_financeira_salva", "financeiro", $cid, [
                        "valor" => $target,
                        "base" => $base,
                        "compartilhar" => $share,
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Meta mensal atualizada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "meta"]);
                }
                if ($act === "review_close") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_review_session(
                        $cid,
                        $uid,
                        (int) ($_POST["session_id"] ?? 0),
                        (string) ($_POST["decision"] ?? "approve"),
                        mb_trim((string) ($_POST["notes"] ?? "")),
                        (string) ($_POST["drawer_unlock_at"] ?? ""),
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Conferência registrada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "conferencias"]);
                }
                if ($act === "review_opening") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_review_opening_request(
                        $cid,
                        $uid,
                        (int) ($_POST["session_id"] ?? 0),
                        (string) ($_POST["decision"] ?? "approve"),
                        mb_trim((string) ($_POST["notes"] ?? "")),
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Autorização de abertura registrada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "conferencias"]);
                }
                if ($act === "daily_consolidate") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_daily_consolidate($cid, $uid);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Consolidação do dia registrada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "admin_receive") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_save_receipt($cid, $uid);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Recebimento registrado.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "admin_payment") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_save_payment($cid, $uid);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Pagamento registrado.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "admin_transfer") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_save_transfer($cid, $uid);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Transferência registrada.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "safe_payment") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_save_payment($cid, $uid);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Pagamento registrado.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "safe_receipt") {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_save_receipt($cid, $uid);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Recebimento registrado.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "painel"]);
                }
                if ($act === "bank_account") {
                    $name = mb_trim((string) ($_POST["name"] ?? ""));
                    if ($name === "") {
                        throw new RuntimeException(
                            "Informe o nome da conta bancária.",
                        );
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "INSERT INTO pi_financial_accounts (clinic_id,name,bank_name,account_type,opening_balance_cents,active,created_by,created_at) VALUES (?,?,?,?,0,1,?,NOW())",
                        [
                            $cid,
                            $name,
                            mb_trim((string) ($_POST["bank_name"] ?? "")),
                            "conta_corrente",
                            $uid,
                        ],
                    );
                    $acc = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_bank_location($cid, $acc, $uid);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("conta_bancaria_criada", "financeiro", $acc, [
                        "name" => $name,
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Banco cadastrado.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "locais"]);
                }
                if ($act === "deposit_bank") {
                    $safe = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_admin_safe($cid, $uid);
                    $acc = (int) ($_POST["account_id"] ?? 0);
                    $bank = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_bank_location($cid, $acc, $uid);
                    $amount = \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents((string) ($_POST["amount"] ?? "0"));
                    if ($bank <= 0) {
                        throw new RuntimeException(
                            "Escolha uma conta bancária válida.",
                        );
                    }
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_create_movement(
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
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Depósito registrado.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "locais"]);
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo financeiro administrativo] " . $e->getMessage(),
                );
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                        $e,
                        "Não foi possível concluir a operação financeira.",
                    ),
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => $tab]);
            }
        }
        $pos = \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_global_position($cid);
        $tabs = \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_tabs_html(
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
                \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_admin_balance_kpis_html($pos) .
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_admin_daily_consolidation_html($cid) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    "<h2>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("receipt_long") .
                        '<span>Movimentos do dia</span></h2><p class="muted">O que entrou, saiu ou foi transferido hoje.</p>' .
                        \Prontoo\Runtime\Financial\FinancialRuntimeOperations12::financial_admin_daily_ledger_timeline($cid),
                    "finance-ledger-card finance-panel-ledger-card",
                );
        } elseif ($tab === "consolidacao") {
            $content = \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_daily_conference_panel($cid, $uid);
        } elseif ($tab === "locais") {
            $content = \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_admin_locations_panel($cid, $uid, $pos);
        } elseif ($tab === "operacoes") {
            $content = \Prontoo\Runtime\Financial\FinancialRuntimeOperations15::financial_admin_operations_panel($cid, $uid);
        } elseif ($tab === "conferencias") {
            $content = \Prontoo\Runtime\Financial\FinancialRuntimeOperations16::financial_admin_conferences_panel($cid, $uid);
        } elseif ($tab === "meta") {
            $st = \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::monthly_goal_status($cid);
            $goalPreview =
                '<div class="finance-goal-progress"><div><strong data-goal-percent>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(number_format((float) $st["percent"], 1, ",", ".")) .
                "%</strong><span data-goal-values>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    (string) ($st["values"] ??
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) ($st["done_cents"] ?? 0)) .
                            " de " .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) ($st["target_cents"] ?? 0))),
                ) .
                '</span></div><div class="goal-bar"><i data-goal-bar style="width:' .
                max(0, min(100, (float) $st["percent"])) .
                '%"></i></div><small>Base atual: ' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($st["base_label"]) .
                ".</small></div>";
            $goalForm =
                '<form method="post" id="finance-goal-form" class="compact finance-lite-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="goal"><div class="two">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Meta mensal gerencial",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "target",
                        "text",
                        $st["target_cents"] > 0
                            ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($st["target_cents"])
                            : "",
                        'inputmode="decimal" placeholder="R$ 0,00"',
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                    "Base da meta",
                    "base_metric",
                    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_goal_base_options(),
                    $st["base_metric"],
                ) .
                '</div><label class="checkline"><input type="checkbox" name="share_with_team" value="1" ' .
                ($st["share"] ? "checked" : "") .
                "><span>Compartilhar percentual cumprido da meta com a equipe.</span></label>" .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Salvar meta") .
                "</form>";
            $content = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Meta</h2><p class="muted">Acompanhe o mês com uma meta simples, sem transformar o Financeiro do Consultório em sistema contábil avançado.</p>' .
                    $goalPreview .
                    $goalForm,
                "finance-report-card finance-goal-card-panel",
            );
        }
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Financeiro",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Painel financeiro",
                "Guia operacional para acompanhar o dia, registrar movimentos e conferir os locais do Consultório.",
                $tabs,
            ) . $content,
        );
    
    }

    public static function page_financial(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("financial");
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::ensure_financial_operational_schema();
        if (\Prontoo\Domain\Financial\FinancialDomainOperations01::financial_is_cashier($c)) {
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations11::financial_cashier_page($c);
            return;
        }
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations17::financial_admin_page($c);
    
    }
}
