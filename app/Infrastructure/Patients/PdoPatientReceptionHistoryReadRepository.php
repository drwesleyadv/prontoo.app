<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Patients;

use Prontoo\Application\Patients\PatientReceptionHistoryReadPort;

final class PdoPatientReceptionHistoryReadRepository implements PatientReceptionHistoryReadPort
{
    public function read(
        int $clinicId,
        int $patientId,
        int $personId,
        string $phoneDigits,
    ): array {
        $identity = [];
        $params = [$clinicId, $patientId];
        if ($personId > 0) {
            $identity[] = 'l.person_id=?';
            $params[] = $personId;
        }
        if ($phoneDigits !== '') {
            $identity[] = "l.phone_digits=? OR LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(l.phone,''),'(',''),')',''),' ',''),'-',''),'.',''),11)=?";
            $params[] = $phoneDigits;
            $params[] = $phoneDigits;
        }
        if ($identity === []) {
            return ['leads' => [], 'events' => [], 'users' => []];
        }
        $rows = \q(
            "SELECT l.id AS lead_id,l.person_id,l.name,l.phone,l.phone_digits,l.source,l.interest,l.stage,l.next_action_at,l.notes,l.created_by AS lead_created_by,l.created_at AS lead_created_at,l.updated_at AS lead_updated_at,e.id AS event_id,e.event_type,e.stage_from,e.stage_to,e.phone AS event_phone,e.source AS event_source,e.interest AS event_interest,e.next_action_at AS event_next_action_at,e.body AS event_body,e.created_by AS event_created_by,e.created_at AS event_created_at,u.name AS event_created_by_name FROM pi_leads l LEFT JOIN pi_lead_events e ON e.clinic_id=l.clinic_id AND e.lead_id=l.id LEFT JOIN pi_users u ON u.id=e.created_by WHERE l.clinic_id=? AND EXISTS (SELECT 1 FROM pi_patients pp WHERE pp.id=? AND pp.clinic_id=l.clinic_id AND pp.active=1) AND (" . implode(' OR ', $identity) . ") ORDER BY l.created_at ASC,l.id ASC,e.created_at ASC,e.id ASC",
            $params,
        )->fetchAll();
        $leads = [];
        $events = [];
        $users = [];
        foreach ($rows as $row) {
            $leadId = (int) ($row['lead_id'] ?? 0);
            if ($leadId <= 0) {
                continue;
            }
            if (!isset($leads[$leadId])) {
                $leads[$leadId] = [
                    'id' => $leadId,
                    'person_id' => $row['person_id'] ?? null,
                    'name' => $row['name'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'phone_digits' => $row['phone_digits'] ?? '',
                    'source' => $row['source'] ?? '',
                    'interest' => $row['interest'] ?? '',
                    'stage' => $row['stage'] ?? '',
                    'next_action_at' => $row['next_action_at'] ?? null,
                    'notes' => $row['notes'] ?? '',
                    'created_by' => $row['lead_created_by'] ?? null,
                    'created_at' => $row['lead_created_at'] ?? null,
                    'updated_at' => $row['lead_updated_at'] ?? null,
                ];
            }
            $eventId = (int) ($row['event_id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }
            $creatorId = (int) ($row['event_created_by'] ?? 0);
            $events[$leadId][] = [
                'id' => $eventId,
                'lead_id' => $leadId,
                'event_type' => $row['event_type'] ?? '',
                'stage_from' => $row['stage_from'] ?? null,
                'stage_to' => $row['stage_to'] ?? null,
                'phone' => $row['event_phone'] ?? null,
                'source' => $row['event_source'] ?? null,
                'interest' => $row['event_interest'] ?? null,
                'next_action_at' => $row['event_next_action_at'] ?? null,
                'body' => $row['event_body'] ?? null,
                'created_by' => $creatorId > 0 ? $creatorId : null,
                'created_at' => $row['event_created_at'] ?? null,
            ];
            if ($creatorId > 0 && trim((string) ($row['event_created_by_name'] ?? '')) !== '') {
                $users[$creatorId] = ['id' => $creatorId, 'name' => (string) $row['event_created_by_name']];
            }
        }
        return ['leads' => array_values($leads), 'events' => $events, 'users' => $users];
    }
}
