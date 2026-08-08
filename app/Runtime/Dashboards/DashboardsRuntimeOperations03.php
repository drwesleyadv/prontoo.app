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
use Prontoo\Presentation\Dashboards\DashboardsPresentationOperations01;

final class DashboardsRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function page_gerente_painel(array $c): void
    
    {
    
        $cid = (int) $c["clinic_id"];
        $month = app_month_in_timezone($cid, $c);
        [$monthStart, $nextMonth] = app_local_month_utc_range($month, $cid, $c);
        $localNow = app_now_in_timezone($cid, $c);
        $monthEnd = $localNow->format("Y-m-t");
        [$todayStart, $todayEnd] = app_local_day_utc_range(
            app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $today = DashboardsRuntimeOperations02::manager_metric_row(
            "SELECT COUNT(*) total, SUM(CASE WHEN arrived_at IS NOT NULL OR status IN ('chegou','em_preparo','pronto_atendimento') THEN 1 ELSE 0 END) arrived, SUM(CASE WHEN consultation_finished_at IS NOT NULL OR status IN ('atendimento_concluido','finalizado') THEN 1 ELSE 0 END) finished, SUM(CASE WHEN status='cancelado' THEN 1 ELSE 0 END) canceled FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<?",
            [$cid, $todayStart, $todayEnd],
        );
        $todayTotal = (int) ($today["total"] ?? 0);
        $todayArrived = (int) ($today["arrived"] ?? 0);
        $todayFinished = (int) ($today["finished"] ?? 0);
        $todayCanceled = (int) ($today["canceled"] ?? 0);
        $doctorCount = max(1, count(doctors($cid)));
        $estimatedCapacity = max(1, $doctorCount * 8);
        $estimatedOcc = (float) round(
            min(100, ($todayTotal / $estimatedCapacity) * 100),
            1,
        \RoundingMode::HalfAwayFromZero);
        $activeLeads = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                lead_active_stage_sql("stage"),
            [$cid],
        );
        $leadsNoNext = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                lead_active_stage_sql("stage") .
                " AND next_action_at IS NULL",
            [$cid],
        );
        $lateLeads = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                lead_active_stage_sql("stage") .
                " AND next_action_at IS NOT NULL AND next_action_at<NOW()",
            [$cid],
        );
        $pendingTasks = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando')",
            [$cid],
        );
        $lateTasks = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at<NOW()",
            [$cid],
        );
        $unassignedTasks = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND assigned_to IS NULL",
            [$cid],
        );
        $pendingRevenue = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='prevista' AND (r.expected_at IS NULL OR r.expected_at<?) AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))",
            [$cid, $nextMonth],
        );
        $overdueRevenue = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND expected_at<NOW()",
            [$cid],
        );
        $receivedRevenue = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?",
            [$cid, $monthStart, $nextMonth],
        );
        $plannedRevenue = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.expected_at>=? AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))",
            [$cid, $monthStart, $nextMonth],
        );
        $paidExpenses = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_expenses WHERE clinic_id=? AND status='paga' AND paid_at>=? AND paid_at<?",
            [$cid, $monthStart, $nextMonth],
        );
        $goal = monthly_goal_status($cid);
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
        $finishedMonth = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COUNT(*) FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND (consultation_finished_at IS NOT NULL OR status IN ('atendimento_concluido','finalizado'))",
            [$cid, $monthStart, $nextMonth],
        );
        $ticket =
            $finishedMonth > 0 ? (int) round($receivedRevenue / $finishedMonth, 0, \RoundingMode::HalfAwayFromZero) : 0;
        $procedureRows = [];
        try {
            $procedureRows = q(
                "SELECT COALESCE(p.title,'Sem procedimento vinculado') title, COALESCE(SUM(fr.amount_cents),0) total FROM pi_financial_revenues fr LEFT JOIN pi_procedures p ON p.id=fr.procedure_id AND p.clinic_id=fr.clinic_id WHERE fr.clinic_id=? AND fr.status='efetivada' AND fr.received_at>=? AND fr.received_at<? GROUP BY COALESCE(p.title,'Sem procedimento vinculado') ORDER BY total DESC LIMIT 5",
                [$cid, $monthStart, $nextMonth],
            )->fetchAll();
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
            e($goalState . " · base: " . $goalBaseLabel) .
            '</p></div><a class="ghost small" href="' .
            href("financial", ["tab" => "meta"]) .
            '">' .
            icon("flag") .
            '<span>Ajustar meta</span></a></header><div class="manager-goal-bar"><i style="width:' .
            $goalBar .
            '%"></i></div><div class="manager-goal-grid"><span><b>' .
            money_br($done) .
            "</b><small>realizado na base da meta</small></span><span><b>" .
            ($target > 0 ? money_br($target) : "—") .
            "</b><small>meta</small></span><span><b>" .
            money_br($missing) .
            "</b><small>faltante</small></span><span><b>" .
            money_br($dailyNeeded) .
            '</b><small>necessário por dia útil</small></span></div><p class="manager-goal-note">' .
            e(
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
            money_br($plannedRevenue) .
            "</b><small>receita prevista no mês</small></span><span><b>" .
            money_br($receivedRevenue) .
            "</b><small>receita efetivada</small></span><span><b>" .
            money_br($pendingRevenue) .
            "</b><small>a receber</small></span><span><b>" .
            money_br($paidExpenses) .
            "</b><small>despesas pagas</small></span><span><b>" .
            money_br($result) .
            "</b><small>resultado parcial</small></span><span><b>" .
            ($ticket > 0 ? money_br($ticket) : "—") .
            "</b><small>ticket médio efetivado</small></span></div></section>";
        $actions = [];
        $paymentRejected = DashboardsRuntimeOperations02::manager_metric_val(
            "SELECT COUNT(*) FROM pi_notices n LEFT JOIN pi_notice_reads nr ON nr.notice_id=n.id AND nr.user_id=? WHERE n.clinic_id=? AND n.title='Pagamento não confirmado' AND (nr.hidden_at IS NULL)",
            [(int) $c["user"]["id"], $cid],
        );
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
                    money_br($overdueRevenue) .
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
                    e((string) $r["title"]) .
                    "</b><small>" .
                    money_br((int) $r["total"]) .
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
        page(
            "Painel",
            page_head(
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
    
        $c = require_can("painel");
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        if (has_effective_role($c, "gerente")) {
            redirect("appointments");
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
        [$todayStart, $todayEnd] = app_local_day_utc_range(
            app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $today =
            (int) (val(
                "SELECT COUNT(*) FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<?",
                [$cid, $todayStart, $todayEnd],
            ) ?? 0);
        $lateTasks =
            (int) (val(
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status='aberta' AND due_at IS NOT NULL AND due_at<NOW() AND (assigned_to IS NULL OR assigned_to=?)",
                [$cid, $uid],
            ) ?? 0);
        $cards =
            '<div class="kpis"><div><b>' .
            $today .
            "</b><span>consultas hoje</span></div><div><b>" .
            $lateTasks .
            "</b><span>Tarefas Atrasadas</span></div><div><b>—</b><span>Atraso médio do dia</span></div></div>";
        page(
            "Painel",
            page_head("Painel", "O que você precisa saber agora.") .
                $cards .
                card(
                    timeline(
                        recent_events($cid, $uid, $c["role"]),
                        "Nenhum evento relevante.",
                    ),
                ),
        );
    
    }
}
