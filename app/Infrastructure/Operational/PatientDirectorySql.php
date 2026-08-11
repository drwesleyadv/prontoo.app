<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

final class PatientDirectorySql
{
    private function __construct()
    {
    }

    public static function metrics(int|string $todayStart, int|string $todayEnd): string
    {
        $start = (int) $todayStart;
        $end = (int) $todayEnd;

        return ",(SELECT MIN(pa.start_at) FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>={$start} AND pa.start_at<{$end} AND pa.status NOT IN ('cancelado','nao_compareceu')) today_appointment_start_at"
            . ",(SELECT pa.status FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>={$start} AND pa.start_at<{$end} AND pa.status NOT IN ('cancelado','nao_compareceu') ORDER BY pa.start_at ASC LIMIT 1) today_appointment_status"
            . ",(SELECT pa.arrived_at FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>={$start} AND pa.start_at<{$end} AND pa.status NOT IN ('cancelado','nao_compareceu') ORDER BY pa.start_at ASC LIMIT 1) today_appointment_arrived_at"
            . ",(SELECT pa.consultation_started_at FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>={$start} AND pa.start_at<{$end} AND pa.status NOT IN ('cancelado','nao_compareceu') ORDER BY pa.start_at ASC LIMIT 1) today_appointment_started_at"
            . ",(SELECT pa.consultation_finished_at FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>={$start} AND pa.start_at<{$end} AND pa.status NOT IN ('cancelado','nao_compareceu') ORDER BY pa.start_at ASC LIMIT 1) today_appointment_finished_at"
            . ",(SELECT MAX(COALESCE(pa.consultation_finished_at,pa.consultation_started_at,pa.start_at)) FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at<=NOW() AND (pa.consultation_finished_at IS NOT NULL OR pa.consultation_started_at IS NOT NULL OR pa.status IN ('atendimento_concluido','finalizado','em_atendimento')) AND pa.status NOT IN ('cancelado','nao_compareceu')) last_consultation_at"
            . ",(SELECT MAX(COALESCE(pa.updated_at,pa.created_at,pa.start_at)) FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.status IN ('cancelado','nao_compareceu')) dropout_at";
    }

    public static function filter(string $filter): string
    {
        return match ($filter) {
            'today', 'week' => " AND EXISTS (SELECT 1 FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>=? AND pa.start_at<? AND pa.status NOT IN ('cancelado','nao_compareceu'))",
            'dropouts' => " AND EXISTS (SELECT 1 FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.status IN ('cancelado','nao_compareceu') AND COALESCE(pa.updated_at,pa.created_at,pa.start_at)>=DATE_SUB(NOW(), INTERVAL 30 DAY))",
            'incomplete' => " AND ((p.birth_date IS NOT NULL AND p.birth_date>DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND NOT EXISTS (SELECT 1 FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1)) OR COALESCE(pp.phone,'')='' OR COALESCE(pp.updated_at,pp.created_at,0)<DATE_SUB(NOW(), INTERVAL 180 DAY))",
            default => '',
        };
    }

    public static function search(string $mode): string
    {
        return match ($mode) {
            'digits' => " AND (p.full_name LIKE ? OR p.cpf LIKE ? OR pp.phone LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)",
            'text' => " AND (p.full_name LIKE ? OR pp.email LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)",
            default => '',
        };
    }

    public static function order(string $filter, string $searchMode): string
    {
        return $searchMode !== 'none'
            ? 'p.full_name ASC, pp.id DESC'
            : \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_order_sql($filter);
    }
}
