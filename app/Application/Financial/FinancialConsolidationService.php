<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

use Closure;
use RuntimeException;

final class FinancialConsolidationService
{
    public function __construct(private FinancialDataService $data)
    {
    }

    public function consolidateDay(
        int $clinicId,
        int $userId,
        Closure $businessDate,
        Closure $reconcile,
        Closure $consolidationState,
        Closure $audit,
    ): void {
        $this->data->ensureDailyClosingSchema();
        $this->data->atomic(function () use (
            $clinicId,
            $userId,
            $businessDate,
            $reconcile,
            $consolidationState,
            $audit,
        ): void {
            $today = (string) $businessDate($clinicId);
            $this->data->result(
                'financial.14.admin_daily_consolidate.01',
                [$clinicId],
            );
            $this->data->result(
                'financial.14.admin_daily_consolidate.02',
                [$clinicId, $today],
            );
            $reconcile($clinicId, $today, $userId, true);
            $state = (array) $consolidationState($clinicId, $today);
            if (!empty($state['consolidated'])) {
                throw new RuntimeException('Este dia financeiro já foi consolidado.');
            }
            if (empty($state['conference_released'])) {
                throw new RuntimeException(
                    'A conferência diária só é liberada quando todas as gavetas abertas hoje, por todos os colaboradores, estiverem fechadas.',
                );
            }
            if (empty($state['can_consolidate'])) {
                throw new RuntimeException(
                    'Ainda existem conferências, devoluções ou movimentos pendentes. Resolva tudo antes de consolidar o dia.',
                );
            }
            $reconciliation = (array) ($state['reconciliation'] ?? []);
            if (empty($reconciliation['ok'])) {
                throw new RuntimeException(
                    'A reconciliação matemática do dia não foi confirmada.',
                );
            }
            $metrics = (array) $reconciliation['metrics'];
            $expected = (int) $metrics['expected_cents'];
            $received = (int) $metrics['received_cents'];
            $pending = (int) $metrics['pending_cents'];
            $movements = (int) $metrics['movement_count'];
            $position = (array) $reconciliation['position'];
            $integrityNote =
                'integrity:financial-reconciliation-v2:' .
                (string) $reconciliation['hash'];
            $this->data->result(
                'financial.14.admin_daily_consolidate.03',
                [
                    $clinicId,
                    $today,
                    'consolidado',
                    $expected,
                    $received,
                    $pending,
                    (int) $position['pos_cents'],
                    (int) $position['safe_cents'],
                    (int) $position['bank_cents'],
                    $movements,
                    (int) ($state['closure']['opened_count'] ?? 0),
                    $integrityNote,
                    $userId,
                ],
            );
            $audit(
                'financeiro_dia_consolidado',
                'financeiro',
                $clinicId,
                [
                    'business_date' => $today,
                    'opened_drawers_checked' =>
                        (int) ($state['closure']['opened_count'] ?? 0),
                    'movimentos' => $movements,
                    'reconciliation_hash' => (string) $reconciliation['hash'],
                    'audit_body' =>
                        'Administrador conferiu e consolidou os lançamentos financeiros do dia após fechamento e revisão das gavetas abertas.',
                ],
            );
        });
    }
}
