<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization\Definitions;

use Prontoo\Application\Authorization\ActionDefinitionCollection;
use Prontoo\Application\Authorization\ActionDefinitionSource;

final class SchedulingActionDefinitions implements ActionDefinitionSource
{
    public function definitions(): array
    {
        $definitions = new ActionDefinitionCollection();
        $leads = 'Runtime/Leads/LeadsRuntimeOperations02.php';
        $appointments = 'Domain/Appointments/Appointments.php';

        $definitions->add('leads', ['__default__', 'save'], 'clinic', $leads, ['leads:add'], ['leads:add']);
        $definitions->add('leads', 'update', 'clinic', $leads, ['leads:edit'], ['leads:edit']);
        $definitions->add('leads', 'convert', 'clinic', $leads, ['leads:edit', 'patients:add'], ['leads:convert', 'patients:add']);
        $definitions->add('leads', 'archive_lead', 'clinic', $leads, ['leads:edit'], ['leads:archive']);

        $definitions->add('appointments', ['__default__', 'create'], 'clinic', $appointments, ['appointments:add'], ['appointments:add'], ['financial:sync', 'tasks:add']);
        $definitions->add('appointments', ['agenda_note', 'block'], 'clinic', $appointments, ['appointments:add'], ['agenda:add']);
        $definitions->add('appointments', ['agenda_note_delete', 'delete_appointment', 'delete_block', 'unblock', 'cancel'], 'clinic', $appointments, ['appointments:delete'], ['agenda:delete'], ['financial:sync', 'tasks:edit']);
        $definitions->add(
            'appointments',
            ['edit', 'update_block', 'update_appointment', 'confirm', 'arrived', 'no_show', 'start_prepare', 'finish_prepare', 'start_consultation', 'finish_consultation', 'finish_checkout'],
            'clinic',
            $appointments,
            ['appointments:edit'],
            ['appointments:transition'],
            ['tasks:edit', 'financial:sync'],
        );

        return $definitions->definitions();
    }
}
