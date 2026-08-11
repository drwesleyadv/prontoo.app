<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class UiComponentsSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.ui_components.01.reception_cash_state_icon.01' => (
                "SELECT id FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date=? AND status='open' LIMIT 1"
            ),
            'operational.ui_components.01.notification_button_light.01' => (
                "SELECT COUNT(*) FROM pi_notices n WHERE n.clinic_id=? AND " . NoticeQuerySql::target('n') . " AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND r.user_id=? AND (r.read_at IS NOT NULL OR r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1)"
            ),
            'operational.ui_components.01.shared_goal_cmdbar_html.01' => (
                "SELECT target_cents,base_metric,share_with_team FROM pi_financial_goals WHERE clinic_id=? AND month_key=? AND share_with_team=1 LIMIT 1"
            ),
            'operational.ui_components.01.shared_goal_cmdbar_html.02' => (
                "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.expected_at>=? AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))"
            ),
            'operational.ui_components.01.shared_goal_cmdbar_html.03' => (
                "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?"
            ),
            'operational.ui_components.01.cmdbar_access_touch.01' => (
                "INSERT INTO pi_user_cmdbar_access (user_id,scope,clinic_scope_id,role_code,action_key,last_accessed_at,access_count,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),1,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_accessed_at=VALUES(last_accessed_at), access_count=access_count+1, updated_at=NOW()"
            ),
            'operational.ui_components.01.cmdbar_access_recency.01' => (
                "SELECT action_key,last_accessed_at FROM pi_user_cmdbar_access WHERE user_id=? AND scope=? AND clinic_scope_id=? AND role_code=? AND action_key IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ")"
            ),
            'operational.ui_components.01.floating_pending_count.01' => (
                OperationalDynamicSqlCatalog::statement($query, $bindings)
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
