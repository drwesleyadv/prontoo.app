<?php
declare(strict_types=1);

$replaceOne = static function (string $path, string $old, string $new): void {
    $text = (string) file_get_contents($path);
    if (substr_count($text, $old) !== 1) {
        throw new RuntimeException($path . ': trecho esperado não encontrado uma única vez.');
    }
    file_put_contents($path, str_replace($old, $new, $text));
};

$replaceOne(
    'app/Runtime/Patients/PatientsRuntimeOperations05.php',
    <<<'OLD'
        $todayCount =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.05.page_patients.08', [$cid, $todayStart, $todayEnd], []) ?? 0);
        $weekCount =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.05.page_patients.09', [$cid, $weekStart, $weekEnd], []) ?? 0);
        $dropoutCount =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.05.page_patients.10', [$cid], []) ?? 0);
        $incompleteCount =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.05.page_patients.11', [$cid], []) ?? 0);
OLD,
    <<<'NEW'
        $directoryStats = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row(
            'operational.patients.05.page_patients.08',
            [$cid, $todayStart, $todayEnd, $cid, $weekStart, $weekEnd, $cid, $cid],
            [],
        ) ?? [];
        $todayCount = (int) ($directoryStats["today_count"] ?? 0);
        $weekCount = (int) ($directoryStats["week_count"] ?? 0);
        $dropoutCount = (int) ($directoryStats["dropout_count"] ?? 0);
        $incompleteCount = (int) ($directoryStats["incomplete_count"] ?? 0);
NEW,
);

$replaceOne(
    'app/Infrastructure/Operational/PatientsSqlCatalog05.php',
    <<<'OLD'
            'operational.patients.05.page_patients.08' => (
                "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')"
            ),
            'operational.patients.05.page_patients.09' => (
                "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')"
            ),
            'operational.patients.05.page_patients.10' => (
                "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND status IN ('cancelado','nao_compareceu') AND COALESCE(updated_at,created_at,start_at)>=DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            'operational.patients.05.page_patients.11' => (
                "SELECT COUNT(*) FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 AND ((p.birth_date IS NOT NULL AND p.birth_date>DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND NOT EXISTS (SELECT 1 FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1)) OR COALESCE(pp.phone,'')='' OR COALESCE(pp.updated_at,pp.created_at,0)<DATE_SUB(NOW(), INTERVAL 180 DAY))"
            ),
OLD,
    <<<'NEW'
            'operational.patients.05.page_patients.08' => (
                "SELECT " .
                "(SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')) today_count," .
                "(SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')) week_count," .
                "(SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND status IN ('cancelado','nao_compareceu') AND COALESCE(updated_at,created_at,start_at)>=DATE_SUB(NOW(), INTERVAL 30 DAY)) dropout_count," .
                "(SELECT COUNT(*) FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 AND ((p.birth_date IS NOT NULL AND p.birth_date>DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND NOT EXISTS (SELECT 1 FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1)) OR COALESCE(pp.phone,'')='' OR COALESCE(pp.updated_at,pp.created_at,0)<DATE_SUB(NOW(), INTERVAL 180 DAY))) incomplete_count"
            ),
NEW,
);
