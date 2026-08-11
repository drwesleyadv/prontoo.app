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

final class FinancialRuntimeOperations12
{
    private function __construct()
    {
    }

    public static function financial_admin_drawers_panel(int $cid, int $uid): string
    
    {
    
        $drawerOptions = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_location_options($cid, true);
        $cashierOptions = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_user_options($cid, true);
        $create =
            '<form method="post" class="compact finance-lite-form finance-drawer-create-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="drawer_create"><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome da Gaveta",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "drawer_name",
                    "text",
                    "",
                    'required placeholder="Ex.: Gaveta Recepção 1"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Uso",
                '<input type="text" value="Dinheiro do Caixa do Atendimento" readonly>',
            ) .
            "</div>" .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Criar Gaveta") .
            "</form>";
        $assign =
            '<form method="post" class="compact finance-lite-form finance-drawer-assign-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="drawer_assign"><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label("Gaveta", "drawer_id", $drawerOptions, "", "required") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Colaborador do Atendimento",
                "cashier_user_id",
                $cashierOptions,
                "",
                "required",
            ) .
            '</div><p class="muted">Mais de um colaborador pode estar vinculado à mesma Gaveta, mas ela só pode ficar aberta por um colaborador por vez.</p>' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Vincular colaborador") .
            "</form>";
        $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        try {
            $yesterday = new DateTimeImmutable($today)
                ->modify("-1 day")
                ->format("Y-m-d");
        } catch (Throwable $e) {
            $yesterday = date("Y-m-d", strtotime("-1 day"));
        }
        $drawers = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.12.admin_drawers_panel.01", [$cid], [])->fetchAll();
        $drawerIds = array_values(
            array_filter(
                array_map(
                    static  fn(array $drawer): int => (int) ($drawer["id"] ?? 0),
                    $drawers,
                ),
            ),
        );
        $linksByDrawer = [];
        if ($drawerIds) {
            $drawerPh = implode(",", array_fill(0, count($drawerIds), "?"));
            $linkRows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.12.admin_drawers_panel.02", array_merge([$cid], $drawerIds), compact('drawerPh'))->fetchAll();
            foreach ($linkRows as $link) {
                $linksByDrawer[(int) $link["location_id"]][] = $link;
            }
        }
        $dailyTotals = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_daily_totals_map(
            $cid,
            $drawerIds,
            [$today, $yesterday],
        );
        $balanceSnapshot = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_balance_snapshot($cid, $drawerIds);
        $balances = (array) ($balanceSnapshot["balances"] ?? []);
        $openByDrawer = (array) ($balanceSnapshot["open"] ?? []);
        $list = "";
        foreach ($drawers as $d) {
            $did = (int) $d["id"];
            $name = (string) $d["name"];
            $links = $linksByDrawer[$did] ?? [];
            $linkHtml = "";
            foreach ($links as $l) {
                $remove = "";
                if ((int) ($l["id"] ?? 0) > 0) {
                    $remove =
                        '<form method="post" class="inline finance-chip-action">' .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        '<input type="hidden" name="act" value="drawer_unassign"><input type="hidden" name="link_id" value="' .
                        (int) $l["id"] .
                        '"><button class="ghost small icon-only" type="submit" title="Remover vínculo" aria-label="Remover vínculo">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                        "</button></form>";
                }
                $linkHtml .=
                    '<span class="pill finance-drawer-user-chip">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) $l["name"])) .
                    "</span>" .
                    $remove .
                    "</span>";
            }
            if ($linkHtml === "") {
                $linkHtml =
                    '<span class="pill warn">Sem colaborador vinculado</span>';
            }
            $open = $openByDrawer[$did] ?? null;
            $drawerState = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_auto_unlock_row_if_due($cid, $d);
            $locked =
                (string) ($drawerState["drawer_lock_status"] ?? "unlocked") ===
                "locked";
            if ($open) {
                $status =
                    '<span class="pill warn">Aberta por ' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($open["user_name"] ?? "Atendimento"))) .
                    "</span>";
            } elseif ($locked) {
                $unlock = mb_trim((string) ($drawerState["drawer_unlock_at"] ?? ""));
                $status =
                    '<span class="pill bad">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lock") .
                    " " .
                    ($unlock !== ""
                        ? "Trancada até " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($unlock, $cid))
                        : "Trancada") .
                    "</span>";
            } else {
                $status =
                    '<span class="pill ok">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lock_open") .
                    " Destrancada</span>";
            }
            $todayTotals =
                $dailyTotals[$did . "|" . $today] ??
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_drawer_daily_totals_empty();
            $yTotals =
                $dailyTotals[$did . "|" . $yesterday] ??
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_drawer_daily_totals_empty();
            $balance = (int) ($balances[$did] ?? 0);
            $metrics =
                '<div class="finance-drawer-metrics">' .
                '<span class="finance-drawer-metric"><small>Sessões hoje</small><b>' .
                (int) $todayTotals["sessions"] .
                "</b></span>" .
                '<span class="finance-drawer-metric"><small>Recebi</small><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $todayTotals["receipts"]) .
                "</b></span>" .
                '<span class="finance-drawer-metric"><small>Paguei</small><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $todayTotals["payments"]) .
                "</b></span>" .
                '<span class="finance-drawer-metric"><small>Retiradas</small><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $todayTotals["withdrawn"]) .
                "</b></span>" .
                '<span class="finance-drawer-metric"><small>Saldo atual</small><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($balance) .
                "</b></span>" .
                '<span class="finance-drawer-metric"><small>Ontem</small><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $yTotals["kept"]) .
                "</b></span>" .
                "</div>";
            $schedule = "";
            if ($locked) {
                $schedule =
                    '<form method="post" class="compact finance-drawer-unlock-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="drawer_schedule_unlock"><input type="hidden" name="drawer_id" value="' .
                    $did .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Destravar em",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "drawer_unlock_at",
                            "datetime-local",
                            \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_default_drawer_unlock_local(
                                $cid,
                                $did,
                                $drawerState,
                            ),
                            "required",
                        ),
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Conferência / destino das retiradas",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "notes",
                            "text",
                            "",
                            'placeholder="Ex.: retirada enviada ao cofre / banco"',
                        ),
                    ) .
                    '<button class="primary small" type="submit">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lock_clock") .
                    "<span>Agendar destravamento</span></button></form>";
            }
            $rename =
                '<form method="post" class="compact finance-drawer-rename-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="drawer_rename"><input type="hidden" name="drawer_id" value="' .
                $did .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Novo nome",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("drawer_name", "text", $name, 'required maxlength="120"'),
                ) .
                '<button class="primary small" type="submit">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
                "<span>Salvar nome</span></button></form>";
            $deactivate =
                '<form method="post" class="compact finance-drawer-danger-form" onsubmit="return confirm(&quot;Desativar esta Gaveta? Os históricos serão preservados.&quot;)">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="drawer_deactivate"><input type="hidden" name="drawer_id" value="' .
                $did .
                '"><button class="danger small" type="submit">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("inventory_2") .
                "<span>Desativar Gaveta</span></button></form>";
            $actions =
                '<details class="finance-drawer-actions"><summary class="ghost small">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("more_horiz") .
                "<span>Ações</span></summary><div>" .
                $schedule .
                $rename .
                $deactivate .
                "</div></details>";
            $lockInfo = "";
            if ($locked) {
                $unlock = mb_trim((string) ($drawerState["drawer_unlock_at"] ?? ""));
                $lockInfo =
                    " · trancada desde " .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br(
                        (string) ($drawerState["drawer_locked_business_date"] ??
                            ""),
                    ) .
                    ($unlock !== ""
                        ? " · destrava em " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($unlock)
                        : " · aguardando conferência");
            }
            $list .=
                '<article class="finance-drawer-admin-card' .
                ($locked ? " drawer-locked" : "") .
                '"><header><div class="finance-drawer-heading"><span class="finance-drawer-avatar">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($locked ? "lock" : "point_of_sale") .
                "</span><div><strong>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
                '</strong><small>Gaveta física do Caixa do Atendimento</small></div></div><div class="finance-drawer-card-actions"><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($balance) .
                "</b>" .
                $status .
                $actions .
                "</div></header>" .
                $metrics .
                '<section class="finance-drawer-users"><small>Colaboradores vinculados</small><div class="finance-inline-pills">' .
                $linkHtml .
                "</div></section><footer><span>Dia anterior: sessões " .
                (int) $yTotals["sessions"] .
                " · pendentes " .
                (int) $yTotals["pending_count"] .
                " · saldo final " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $yTotals["kept"]) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($lockInfo) .
                "</span></footer></article>";
        }
        if ($list === "") {
            $list =
                '<div class="empty">Nenhuma Gaveta criada. Crie pelo menos uma e vincule os colaboradores do Atendimento.</div>';
        }
        $setup =
            '<div class="finance-drawer-setup-grid">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Criar Gaveta</h2><p class="muted">Cadastre cada gaveta física usada para guardar dinheiro do Caixa do Atendimento.</p>' .
                    $create,
                "finance-form finance-drawer-setup-card",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                "<h2>Vincular colaborador</h2>" . $assign,
                "finance-form finance-drawer-setup-card",
            ) .
            "</div>";
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<h2>Gavetas do Atendimento</h2><p class="muted">Gerencie nomes, vínculos e status das Gavetas. Vínculos e histórico permanecem atrelados ao ID da Gaveta, mesmo após renomear.</p>',
            "finance-report-card finance-drawer-intro",
        ) .
            $setup .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Controle das Gavetas</h2><div class="finance-drawer-admin-grid">' .
                    $list .
                    "</div>",
                "finance-list finance-drawer-admin-list",
            );
    
    }

    public static function financial_admin_daily_ledger_timeline(int $cid): string
    
    {
    
        $day = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        [$startUtc, $endUtc] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($day, $cid);
        try {
            $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.12.admin_daily_ledger_timeline.01", [$cid, $startUtc, $endUtc], [])->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo financeiro razonete diário] " . $e->getMessage());
            return '<div class="empty">Não foi possível carregar as movimentações do dia.</div>';
        }
        if (!$rows) {
            return '<div class="empty">Nenhuma movimentação financeira registrada hoje.</div>';
        }
        $items = [];
        foreach ($rows as $r) {
            $type = (string) ($r["movement_type"] ?? "");
            $status = (string) ($r["status"] ?? "");
            $from = mb_trim((string) ($r["from_name"] ?? ""));
            $to = mb_trim((string) ($r["to_name"] ?? ""));
            $path =
                ($from !== "" ? $from : "Origem externa") .
                " → " .
                ($to !== "" ? $to : "Destino externo");
            $kind = "other";
            if ($type === "receipt") {
                $kind = "receipt";
            } elseif ($type === "payment") {
                $kind = "payment";
            } elseif (
                in_array($type, ["transfer", "deposit", "cash_closing"], true)
            ) {
                $kind = "transfer";
            }
            $method = mb_trim((string) ($r["payment_method"] ?? ""));
            $user = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($r["user_name"] ?? ""));
            $statusPill =
                $status === "confirmed"
                    ? '<span class="pill icon-only ok" title="Confirmado" aria-label="Confirmado">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check_circle") .
                        "</span>"
                    : '<span class="pill warn">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_movement_status($status)) .
                        "</span>";
            $items[] = [
                "icon" => \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_icon($type),
                "class" => "finance-ledger-entry finance-ledger-" . $kind,
                "time" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) ($r["created_at"] ?? ""), $cid),
                "title" =>
                    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_movement_type($type) .
                    " · " .
                    ((string) ($r["title"] ?? "Movimentação")),
                "body" => $path,
                "meta" =>
                    ($user !== "" ? "Registrado por " . $user . " · " : "") .
                    ($method !== ""
                        ? ucfirst(str_replace("_", " ", $method))
                        : "Sem forma informada"),
                "html" =>
                    '<div class="finance-ledger-activity-pills"><span class="pill">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) ($r["amount_cents"] ?? 0)) .
                    "</span>" .
                    $statusPill .
                    "</div>",
            ];
        }
        return '<div class="activity-timeline finance-ledger-activity">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($items, "Nenhuma movimentação financeira registrada hoje.") .
            "</div>";
    
    }
}
