<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class DashboardsSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.dashboards.03.page_gerente_painel.01' => (
                "SELECT COALESCE(p.title,'Sem procedimento vinculado') title, COALESCE(SUM(fr.amount_cents),0) total FROM pi_financial_revenues fr LEFT JOIN pi_procedures p ON p.id=fr.procedure_id AND p.clinic_id=fr.clinic_id WHERE fr.clinic_id=? AND fr.status='efetivada' AND fr.received_at>=? AND fr.received_at<? GROUP BY COALESCE(p.title,'Sem procedimento vinculado') ORDER BY total DESC LIMIT 5"
            ),
            'operational.dashboards.03.page_painel.01' => (
                "SELECT COUNT(*) FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<?"
            ),
            'operational.dashboards.03.page_painel.02' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status='aberta' AND due_at IS NOT NULL AND due_at<NOW() AND (assigned_to IS NULL OR assigned_to=?)"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
