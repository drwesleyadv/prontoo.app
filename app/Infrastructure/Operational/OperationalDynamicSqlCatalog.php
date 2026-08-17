<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class OperationalDynamicSqlCatalog
{
    private function __construct()
    {
    }

    public static function statement(string $queryId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        $since = 'DATE_SUB(NOW(), INTERVAL 30 DAY)';
        $scopeModelWhere = ModelClinicQuerySql::exclude('clinic_id');
        $modelScopedWhere = $scopeModelWhere;
        $modelClinicWhere = ModelClinicQuerySql::exclude('id');
        return match ($queryId) {
            'read.admin_pages.02.admin_global_ops_finance_html.01' => (
                "SELECT COUNT(DISTINCT clinic_id) FROM pi_audit WHERE clinic_id IS NOT NULL AND created_at>=$since $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.02' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (created_at>=$since OR updated_at>=$since OR trial_started_at>=$since OR paid_until>=CURDATE()) $modelClinicWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.03' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND created_at>=$since $modelClinicWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.04' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='exempt' $modelClinicWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.05' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (subscription_status='read_only' OR (paid_until IS NOT NULL AND paid_until<CURDATE())) $modelClinicWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.06' => (
                "SELECT COUNT(DISTINCT user_id) FROM pi_user_roles WHERE active=1 $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.07' => (
                "SELECT COUNT(*) FROM pi_appointments WHERE start_at>=$since AND start_at<NOW() AND status<>'cancelado' $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.08' => (
                "SELECT COUNT(*) FROM pi_appointments WHERE start_at>=NOW() AND start_at<DATE_ADD(NOW(), INTERVAL 30 DAY) AND status<>'cancelado' $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.09' => (
                "SELECT COUNT(*) FROM pi_leads WHERE created_at>=$since AND " .
                                LeadQuerySql::active("stage") .
                                " $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.10' => (
                "SELECT COUNT(*) FROM pi_patients WHERE created_at>=$since AND active=1 AND deleted_at IS NULL $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.11' => (
                "SELECT COUNT(*) FROM pi_documents WHERE issued_at>=$since AND document_status<>'cancelado' $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.12' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE created_at>=$since AND status NOT IN ('concluida','cancelada') $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.13' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE due_at>=$since AND due_at<NOW() AND status NOT IN ('concluida','cancelada') $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.14' => (
                "SELECT COUNT(*) FROM pi_notices WHERE created_at>=$since $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.15' => (
                "SELECT COUNT(*) FROM pi_global_notices WHERE created_at>=$since"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.16' => (
                "SELECT COUNT(*) FROM pi_maestro_rules WHERE 1=1 $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.17' => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE status='efetivada' AND received_at>=$since $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.18' => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE status='prevista' AND expected_at>=$since $modelScopedWhere"
            ),
            'read.admin_pages.02.admin_global_ops_finance_html.19' => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_expenses WHERE status='paga' AND paid_at>=$since $modelScopedWhere"
            ),
            'read.clinic_config.01.open_incidents_count.01' => (
                "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL"
            ),
            'read.dashboards.03.page_gerente_painel.01' => (
                "SELECT COUNT(*) total, SUM(CASE WHEN arrived_at IS NOT NULL OR status IN ('chegou','em_preparo','pronto_atendimento') THEN 1 ELSE 0 END) arrived, SUM(CASE WHEN consultation_finished_at IS NOT NULL OR status IN ('atendimento_concluido','finalizado') THEN 1 ELSE 0 END) finished, SUM(CASE WHEN status='cancelado' THEN 1 ELSE 0 END) canceled FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<?"
            ),
            'read.dashboards.03.page_gerente_painel.02' => (
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                                LeadQuerySql::active("stage")
            ),
            'read.dashboards.03.page_gerente_painel.03' => (
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                                LeadQuerySql::active("stage") .
                                " AND next_action_at IS NULL"
            ),
            'read.dashboards.03.page_gerente_painel.04' => (
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                                LeadQuerySql::active("stage") .
                                " AND next_action_at IS NOT NULL AND next_action_at<NOW()"
            ),
            'read.dashboards.03.page_gerente_painel.05' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando')"
            ),
            'read.dashboards.03.page_gerente_painel.06' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at<NOW()"
            ),
            'read.dashboards.03.page_gerente_painel.07' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND assigned_to IS NULL"
            ),
            'read.dashboards.03.page_gerente_painel.08' => (
                "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='prevista' AND (r.expected_at IS NULL OR r.expected_at<?) AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))"
            ),
            'read.dashboards.03.page_gerente_painel.09' => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND expected_at<NOW()"
            ),
            'read.dashboards.03.page_gerente_painel.10' => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?"
            ),
            'read.dashboards.03.page_gerente_painel.11' => (
                "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.expected_at>=? AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))"
            ),
            'read.dashboards.03.page_gerente_painel.12' => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_expenses WHERE clinic_id=? AND status='paga' AND paid_at>=? AND paid_at<?"
            ),
            'read.dashboards.03.page_gerente_painel.13' => (
                "SELECT COUNT(*) FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND (consultation_finished_at IS NOT NULL OR status IN ('atendimento_concluido','finalizado'))"
            ),
            'read.dashboards.03.page_gerente_painel.14' => (
                "SELECT COUNT(*) FROM pi_notices n LEFT JOIN pi_notice_reads nr ON nr.notice_id=n.id AND nr.user_id=? WHERE n.clinic_id=? AND n.title='Pagamento não confirmado' AND (nr.hidden_at IS NULL)"
            ),
            'read.ui_components.01.floating_pending_cards_html.01' => (
                "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND " . TaskQuerySql::active() . " AND " . TaskQuerySql::floatingAccess((bool) $clinicWide) . " AND t.due_at IS NOT NULL AND t.due_at<NOW()"
            ),
            'read.ui_components.01.floating_pending_cards_html.02' => (
                "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND " . TaskQuerySql::active() . " AND " . TaskQuerySql::floatingAccess((bool) $clinicWide) . " AND t.due_at IS NOT NULL AND t.due_at>=? AND t.due_at<?"
            ),
            'read.ui_components.01.floating_pending_cards_html.03' => (
                "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND " . TaskQuerySql::active() . " AND " . TaskQuerySql::floatingAccess((bool) $clinicWide) . " AND t.due_at IS NOT NULL AND t.due_at>=CURDATE() AND t.due_at<DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
            ),
            'read.ui_components.01.floating_pending_cards_html.04' => (
                "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND " . TaskQuerySql::active() . " AND " . TaskQuerySql::floatingAccess((bool) $clinicWide)
            ),
            'read.ui_components.01.floating_pending_cards_html.05' => (
                "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND t.status='em_andamento' AND (t.assigned_to=? OR t.started_by=?)"
            ),
            'read.ui_components.01.floating_pending_cards_html.06' => (
                "SELECT COUNT(*) FROM pi_notices n WHERE n.clinic_id=? AND n.requires_ack=1 AND " . NoticeQuerySql::target('n') . " AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND r.user_id=? AND (r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1)"
            ),
            default => throw new RuntimeException('Consulta operacional dinâmica desconhecida.'),
        };
    }
}
