<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use Prontoo\Infrastructure\AdminPages\AdminPagesInfrastructureOperations01;
use Prontoo\Infrastructure\Appointments\AppointmentsInfrastructureOperations01;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03;
use Prontoo\Infrastructure\Patients\PatientsInfrastructureOperations01;
use Prontoo\Infrastructure\TasksNotices\TasksNoticesInfrastructureOperations01;
use RuntimeException;

final class OperationalSchemaContracts
{
    private function __construct()
    {
    }

    public static function ensure(string $contract): void
    {
        match ($contract) {
            'admin_alerts' => AdminPagesInfrastructureOperations01::admin_alerts_ensure_schema(),
            'agenda_notes' => AppointmentsInfrastructureOperations01::agenda_notes_ensure_schema(),
            'financial_operational' => DatabaseSchemaInfrastructureOperations03::ensure_financial_operational_schema(),
            'lead_events' => DatabaseSchemaInfrastructureOperations03::ensure_lead_events_schema(),
            'patient_guardians' => PatientsInfrastructureOperations01::patient_guardians_ensure_schema(),
            'patient_tabs' => PatientsInfrastructureOperations01::patient_tabs_ensure_schema(),
            'readonly_support_alerts' => TasksNoticesInfrastructureOperations01::readonly_support_alerts_ensure_schema(),
            default => throw new RuntimeException('Contrato de schema operacional desconhecido.'),
        };
    }
}
