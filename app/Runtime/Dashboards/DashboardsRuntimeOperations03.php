<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Dashboards;

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
use Prontoo\Domain\Leads\LeadsDomainOperations01;
use Prontoo\Presentation\Dashboards\DashboardsPresentationOperations01;

final class DashboardsRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function page_gerente_painel(array $c): void
    
    {
    
        $cid = (int) $c["clinic_id"];
        $month = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_month_in_timezone($cid, $c);
        [$monthStart, $nextMonth] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_month_utc_range($month, $cid, $c);
        $localNow = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_now_in_timezone($cid, $c);
        $monthEnd = $localNow->format("Y-m-t");
        [$todayStart, $todayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range(
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $today = DashboardsRuntimeOperations02::manager_metric_row('read.dashboards.03.page_gerente_painel.01', [$cid, $todayStart, $todayEnd], []);
        $todayTotal = (int) ($today["total"] ?? 0);
        $todayArrived = (int) ($today["arrived"] ?? 0);
        $todayFinished = (int) ($today["finished"] ?? 0);
        $todayCanceled = (int) ($today["canceled"] ?? 0);
        $doctorCount = max(1, count(\Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::doctors($cid)));
        $estimatedCapacity = max(1, $doctorCount * 8);
        $estimatedOcc = (float) round(
            min(100, ($todayTotal / $estimatedCapacity) * 100),
            1,
        \RoundingMode::HalfAwayFromZero);
        $activeLeads = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.02', [$cid], []);
        $leadsNoNext = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.03', [$cid], []);
        $lateLeads = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.04', [$cid], []);
        $pendingTasks = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.05', [$cid], []);
        $lateTasks = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.06', [$cid], []);
        $unassignedTasks = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.07', [$cid], []);
        $pendingRevenue = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.08', [$cid, $nextMonth], []);
        $overdueRevenue = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.09', [$cid], []);
        $receivedRevenue = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.10', [$cid, $monthStart, $nextMonth], []);
        $plannedRevenue = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.11', [$cid, $monthStart, $nextMonth], []);
        $paidExpenses = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.12', [$cid, $monthStart, $nextMonth], []);
        $goal = \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::monthly_goal_status($cid);
        $target = (int) $goal["target_cents"];
        $done = (int) $goal["done_cents"];
        $goalPct = (float) $goal["percent"];
        $goalBar = max(0, min(100, $goalPct));
        $missing = max(0, $target - $done);
        $result = $receivedRevenue - $paidExpenses;
        $goalBaseLabel = (string) ($goal["base_label"] ?? "Receita Efetivada");
        $monthDay = (int) $localNow->format("j");
        $monthDays = (int) $localNow->format("t");
        $expectedPct =
            $target > 0 ? (float) round(($monthDay / $monthDays) * 100, 1, \RoundingMode::HalfAwayFromZero) : 0.0;
        $businessLeft = max(
            1,
            DashboardsPresentationOperations01::manager_count_business_days($localNow->format("Y-m-d"), $monthEnd),
        );
        $dailyNeeded = $missing > 0 ? (int) ceil($missing / $businessLeft) : 0;
        $goalState =
            $target <= 0
                ? "Meta não definida"
                : ($goalPct >= 100
                    ? "Meta cumprida"
                    : ($goalPct + 5 >= $expectedPct
                        ? "No ritmo da meta"
                        : "Abaixo do ritmo esperado"));
        $finishedMonth = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.13', [$cid, $monthStart, $nextMonth], []);
        $ticket =
            $finishedMonth > 0 ? (int) round($receivedRevenue / $finishedMonth, 0, \RoundingMode::HalfAwayFromZero) : 0;
        $procedureRows = [];
        try {
            $procedureRows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.dashboards.03.page_gerente_painel.01', [$cid, $monthStart, $nextMonth], [])->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo manager procedures] " . $e->getMessage());
        }
        $kpis =
            '<div class="kpis manager-kpis manager-operational-kpis">' .
            DashboardsPresentationOperations01::manager_dashboard_card(
                "Agendados",
                (string) $todayTotal,
                "calendar_month",
                $todayArrived .
                    " chegaram · " .
                    $todayFinished .
                    " concluídos · " .
                    $todayCanceled .
                    " cancelados",
            ) .
            DashboardsPresentationOperations01::manager_dashboard_card(
                "Ocupação Estimada",
                DashboardsPresentationOperations01::manager_percent_label($estimatedOcc),
                "speed",
                $todayTotal .
                    " de " .
                    $estimatedCapacity .
                    " horários úteis estimados",
            ) .
            DashboardsPresentationOperations01::manager_dashboard_card(
                "Interessados Ativos",
                (string) $activeLeads,
                "person_search",
                $leadsNoNext . " sem próxima ação",
            ) .
            DashboardsPresentationOperations01::manager_dashboard_card(
                "Tarefas Pendentes",
                (string) $pendingTasks,
                "pending_actions",
                $lateTasks .
                    " atrasadas · " .
                    $unassignedTasks .
                    " sem responsável",
                $lateTasks > 0 || $unassignedTasks > 0 ? "bad" : "",
            ) .
            DashboardsPresentationOperations01::manager_dashboard_card(
                "Retornos Vencidos",
                (string) $lateLeads,
                "event_busy",
                "interessados com próximo contato vencido",
                $lateLeads > 0 ? "bad" : "",
            ) .
            "</div>";
        $goalHtml =
            '<section class="card manager-goal"><header><div><h2>Meta mensal</h2><p>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($goalState . " · base: " . $goalBaseLabel) .
            '</p></div><a class="ghost small" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "meta"]) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("flag") .
            '<span>Ajustar meta</span></a></header><div class="manager-goal-bar"><i style="width:' .
            $goalBar .
            '%"></i></div><div class="manager-goal-grid"><span><b>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($done) .
            "</b><small>realizado na base da meta</small></span><span><b>" .
            ($target > 0 ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($target) : "—") .
            "</b><small>meta</small></span><span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($missing) .
            "</b><small>faltante</small></span><span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($dailyNeeded) .
            '</b><small>necessário por dia útil</small></span></div><p class="manager-goal-note">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                $target > 0
                    ? "O ritmo esperado para hoje é " .
                        DashboardsPresentationOperations01::manager_percent_label($expectedPct) .
                        ". A meta está calculada sobre " .
                        $goalBaseLabel .
                        " e considera os dias úteis restantes."
                    : "A meta mensal ainda não foi cadastrada. Sem ela, o painel não consegue medir o rumo financeiro do mês.",
            ) .
            "</p></section>";
        $finance =
            '<section class="card manager-finance-card"><h2>Indicadores financeiros</h2><div class="manager-mini-grid"><span><b>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($plannedRevenue) .
            "</b><small>receita prevista no mês</small></span><span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($receivedRevenue) .
            "</b><small>receita efetivada</small></span><span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($pendingRevenue) .
            "</b><small>a receber</small></span><span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($paidExpenses) .
            "</b><small>despesas pagas</small></span><span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($result) .
            "</b><small>resultado parcial</small></span><span><b>" .
            ($ticket > 0 ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($ticket) : "—") .
            "</b><small>ticket médio efetivado</small></span></div></section>";
        $actions = [];
        $paymentRejected = DashboardsRuntimeOperations02::manager_metric_val('read.dashboards.03.page_gerente_painel.14', [(int) $c["user"]["id"], $cid], []);
        if ($paymentRejected > 0) {
            $actions[] = DashboardsRuntimeOperations02::manager_action_card(
                "upload_file",
                "Pagamento não confirmado",
                "Envie o comprovante de pagamento em Meu Consultório > Assinatura para nova conferência.",
                "settings",
                "Regularizar",
            );
        }
        if ($target <= 0) {
            $actions[] = DashboardsRuntimeOperations02::manager_action_card(
                "flag",
                "Definir meta mensal",
                "Sem meta, o sistema não consegue orientar ritmo, faltante e tendência financeira.",
                "financial",
                "Configurar",
            );
        } elseif ($goalPct + 5 < $expectedPct) {
            $actions[] = DashboardsRuntimeOperations02::manager_action_card(
                "trending_down",
                "Meta abaixo do ritmo",
                "Revise agenda, conversão de interessados e receitas previstas para recuperar o mês.",
                "financial",
                "Ver financeiro",
            );
        }
        if ($leadsNoNext > 0) {
            $actions[] = DashboardsRuntimeOperations02::manager_action_card(
                "person_search",
                $leadsNoNext . " interessado(s) sem próxima ação",
                "Defina responsável, prazo ou próximo contato para não perder demanda.",
                "leads",
                "Organizar",
            );
        }
        if ($lateLeads > 0) {
            $actions[] = DashboardsRuntimeOperations02::manager_action_card(
                "event_repeat",
                $lateLeads . " retorno(s) vencido(s)",
                "Há interessados com próximo contato vencido. Atualize prazo, responsável ou desfecho.",
                "leads",
                "Revisar retornos",
            );
        }
        if ($lateTasks > 0 || $unassignedTasks > 0) {
            $actions[] = DashboardsRuntimeOperations02::manager_action_card(
                "checklist",
                $lateTasks .
                    " tarefa(s) atrasada(s) e " .
                    $unassignedTasks .
                    " sem responsável",
                "Repriorize a equipe e elimine pendências sem dono claro.",
                "tasks",
                "Revisar",
            );
        }
        if ($overdueRevenue > 0) {
            $actions[] = DashboardsRuntimeOperations02::manager_action_card(
                "account_balance_wallet",
                "Receitas previstas vencidas",
                "Há " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($overdueRevenue) .
                    " em receitas previstas com data vencida.",
                "financial",
                "Conferir",
            );
        }
        if ($estimatedOcc < 40) {
            $actions[] = DashboardsRuntimeOperations02::manager_action_card(
                "event_busy",
                "Ocupação estimada baixa",
                "A ocupação estimada está abaixo de 40%; avalie retornos, encaixes e contatos pendentes.",
                "appointments",
                "Ver agenda",
            );
        }
        $actionsHtml =
            '<section class="card manager-actions"><h2>Ações recomendadas</h2>' .
            ($actions
                ? '<div class="manager-action-list">' .
                    implode("", $actions) .
                    "</div>"
                : '<div class="empty">Nenhuma ação crítica detectada agora.</div>') .
            "</section>";
        $procedureHtml = "";
        if ($procedureRows) {
            $procedureHtml = '<div class="manager-procedure-list">';
            foreach ($procedureRows as $r) {
                $procedureHtml .=
                    "<span><b>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $r["title"]) .
                    "</b><small>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["total"]) .
                    "</small></span>";
            }
            $procedureHtml .= "</div>";
        } else {
            $procedureHtml =
                '<div class="empty">Ainda não há receita efetivada por procedimento neste mês.</div>';
        }
        $procedureBlock =
            '<section class="card manager-procedures"><h2>Receita por procedimento</h2>' .
            $procedureHtml .
            "</section>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Painel",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Painel",
                "Indicadores operacionais, meta mensal, financeiro e ações prioritárias.",
            ) .
                $kpis .
                $goalHtml .
                $finance .
                $actionsHtml .
                $procedureBlock,
        );
    
    }

    public static function page_painel(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("painel");
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        if (\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments");
        }
        if (($c["role"] ?? "") === "medico") {
            DashboardsRuntimeOperations01::page_medico_painel($c);
            return;
        }
        if (($c["role"] ?? "") === "recepcionista") {
            DashboardsRuntimeOperations01::page_recepcao_painel($c);
            return;
        }
        if (($c["role"] ?? "") === "assistente") {
            DashboardsRuntimeOperations02::page_triagem_painel($c);
            return;
        }
        [$todayStart, $todayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range(
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $today =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.dashboards.03.page_painel.01', [$cid, $todayStart, $todayEnd], []) ?? 0);
        $lateTasks =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.dashboards.03.page_painel.02', [$cid, $uid], []) ?? 0);
        $cards =
            '<div class="kpis"><div><b>' .
            $today .
            "</b><span>consultas hoje</span></div><div><b>" .
            $lateTasks .
            "</b><span>Tarefas Atrasadas</span></div><div><b>—</b><span>Atraso médio do dia</span></div></div>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Painel",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Painel", "O que você precisa saber agora.") .
                $cards .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline(
                        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::recent_events($cid, $uid, $c["role"]),
                        "Nenhum evento relevante.",
                    ),
                ),
        );
    
    }
}
