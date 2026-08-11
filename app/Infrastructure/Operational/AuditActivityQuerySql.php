<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use InvalidArgumentException;

final class AuditActivityQuerySql
{
    private function __construct()
    {
    }

    public static function where(array $criteria): string
    {
        $scope = (string) ($criteria['scope'] ?? 'all');

        if ($scope === 'all') {
            return '1=1';
        }
        if ($scope === 'model_excluded') {
            return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_exclude_where('a.clinic_id');
        }
        if ($scope === 'clinic') {
            return 'a.clinic_id=?';
        }
        if ($scope === 'appointment') {
            return 'a.clinic_id=? AND a.entity_key=? AND a.entity_id=?';
        }
        if ($scope !== 'activity_page') {
            throw new InvalidArgumentException('Escopo de consulta de auditoria inválido.');
        }

        $where = 'a.clinic_id=?';
        $visibility = (string) ($criteria['visibility'] ?? 'clinic');
        $userCount = max(0, (int) ($criteria['userCount'] ?? 0));
        if (in_array($visibility, ['medico', 'assistente'], true) && $userCount > 0) {
            $where .= ' AND (a.user_id IN (' . OperationalSequenceSql::placeholders($userCount) . ") OR a.event_key='janela_aberta')";
        } elseif ($visibility === 'recepcionista' && $userCount > 0) {
            $where .= ' AND (a.user_id IN (' . OperationalSequenceSql::placeholders($userCount) . ") OR a.entity_key IN ('paciente','consulta','lead') OR a.event_key IN ('paciente_salvo','consulta_agendada','lead_criado','janela_aberta'))";
        }
        $where .= ' AND a.created_at>=? AND a.created_at<?';
        if (!empty($criteria['member'])) {
            $where .= ' AND a.user_id=?';
        }

        return $where;
    }
}
