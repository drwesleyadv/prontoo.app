<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization\Definitions;

use Prontoo\Application\Authorization\ActionDefinitionCollection;
use Prontoo\Application\Authorization\ActionDefinitionSource;

final class PatientActionDefinitions implements ActionDefinitionSource
{
    public function definitions(): array
    {
        $definitions = new ActionDefinitionCollection();
        $patients = 'Domain/Patients/Patients.php';

        $definitions->add('patients', '__default__', 'clinic', $patients, ['patients:add'], ['patients:add']);
        $definitions->add('patient', ['save_legal_guardian', 'update_patient_contact', 'update_patient', 'create_patient_tab', 'update_care'], 'clinic', $patients, ['patients:edit'], ['patients:edit']);
        $definitions->add('patient', ['delete_legal_guardian', 'delete_patient', 'delete_care'], 'clinic', $patients, ['patients:delete'], ['patients:delete']);
        $definitions->add('patient', 'issue_patient_document', 'clinic', $patients, ['patients:view', 'documents:add'], ['documents:add']);
        $definitions->add('patient', 'patient_revenue_receive', 'clinic', $patients, ['patients:view', 'financial:edit'], ['financial:receipt'], [], 'financial_operational', 'financial:edit');
        $definitions->add('patient', 'finish_active_appointment', 'clinic', $patients, ['patients:view', 'appointments:edit'], ['appointments:transition'], ['tasks:edit']);
        $definitions->add('patient', ['__default__', 'record'], 'clinic', $patients, ['patients:edit'], ['care:add'], ['appointments:transition', 'tasks:edit']);

        return $definitions->definitions();
    }
}
