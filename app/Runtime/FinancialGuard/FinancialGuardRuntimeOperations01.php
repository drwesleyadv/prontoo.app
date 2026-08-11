<?php
declare(strict_types=1);

namespace Prontoo\Runtime\FinancialGuard;

use Prontoo\Domain\Financial\FinancialMovementMutationPolicy;
use RuntimeException;
use Throwable;

final class FinancialGuardRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function financial_movement_write_guard(string $sql, array $params): void
    {
        static $inside = false;
        if ($inside) {
            return;
        }
        $target = FinancialMovementMutationPolicy::target($sql, $params);
        if ($target === null) {
            return;
        }
        $inside = true;
        try {
            $rows = \Prontoo\Runtime\Operational\OperationalComposition::platform()->result('operational.financial_guard.01.financial_movement_write_guard.01', (array) $target['params'], ['target' => $target])->fetchAll();
            $lockedClinics = [];
            foreach ($rows as $row) {
                $cid = (int) ($row['clinic_id'] ?? 0);
                $sessionId = (int) ($row['cash_session_id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                if (!isset($lockedClinics[$cid])) {
                    \Prontoo\Runtime\Operational\OperationalComposition::platform()->result('operational.financial_guard.01.financial_movement_write_guard.02', [$cid], []);
                    $lockedClinics[$cid] = true;
                }
                $businessDate = '';
                if ($sessionId > 0) {
                    $session = \Prontoo\Runtime\Operational\OperationalComposition::platform()->row('operational.financial_guard.01.financial_movement_write_guard.03', [$sessionId, $cid], []);
                    $businessDate = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                        $session['business_date'] ?? '',
                    );
                }
                if ($businessDate === '') {
                    $created = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local(
                        $row['created_at'] ?? null,
                        $cid,
                    );
                    $businessDate = $created ? $created->format('Y-m-d') : '';
                }
                $closing = $businessDate !== ''
                    ? \Prontoo\Runtime\Operational\OperationalComposition::platform()->row('operational.financial_guard.01.financial_movement_write_guard.04', [$cid, $businessDate], [])
                    : null;
                if ((int) ($closing['id'] ?? 0) > 0) {
                    throw new RuntimeException(
                        'Movimento de dia consolidado é imutável. Reabra o período por fluxo autorizado antes de corrigir o lançamento.',
                    );
                }
            }
        } finally {
            $inside = false;
        }
    }

    public static function financial_cashier_requires_attention_light(array $c): bool
    {
        if (($c['scope'] ?? '') !== 'clinic' || (string) ($c['role'] ?? '') !== 'recepcionista') {
            return false;
        }
        $cid = (int) ($c['clinic_id'] ?? 0);
        $uid = (int) ($c['user']['id'] ?? 0);
        if ($cid <= 0 || $uid <= 0) {
            return false;
        }
        try {
            return \Prontoo\Runtime\Financial\FinancialComposition::cashierAttentionService()->requiresAttention(
                $c,
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_read_only_db($cid),
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid),
            );
        } catch (Throwable $error) {
            error_log('[Prontoo financeiro caixa atenção leve] ' . $error->getMessage());
            return false;
        }
    }
}
