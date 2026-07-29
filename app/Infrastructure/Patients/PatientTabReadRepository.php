<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Patients {
    final class PatientTabReadRepository
    {
        public static function activeTabs(int $clinicId, int $patientId): array
        {
            return \q(
                "SELECT id,label,icon_name,sort_order,created_at FROM pi_patient_tabs WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY sort_order ASC,id ASC",
                [$clinicId, $patientId],
            )->fetchAll();
        }

        public static function labelById(int $tabId): string
        {
            return (string) \safe_val(
                "SELECT label FROM pi_patient_tabs WHERE id=? LIMIT 1",
                [$tabId],
                "",
            );
        }
    }
}

