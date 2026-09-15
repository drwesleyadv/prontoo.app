<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AppointmentsSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.appointments.03.procedure_options.01' => (
                "SELECT id,title,category,description,duration_minutes,price_cents,payment_methods,pre_instructions,post_care,active FROM pi_procedures WHERE clinic_id=?" .
                                (!empty($activeOnly) ? " AND active=1" : "") .
                                " ORDER BY active DESC,title ASC LIMIT 400"
            ),
            'operational.appointments.03.procedure_reason_from_post.01' => (
                "SELECT title FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1"
            ),
            'operational.appointments.03.doctors.01' => (
                "SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND role_code='medico' AND active=1 ORDER BY id ASC LIMIT 80"
            ),
            'operational.appointments.03.agenda_doctor_name.01' => (
                "SELECT u.name FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.clinic_id=? AND ur.role_code='medico' AND ur.active=1 WHERE u.id=? AND u.active=1 LIMIT 1"
            ),
            'operational.appointments.03.agenda_conflict_message.01' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado','nao_compareceu','reagendado') AND start_at < ? AND end_at > ?" .
                (!empty($ignoreAppointment) ? " AND id<>?" : "") .
                (!empty($doctorScoped) ? " AND doctor_user_id=?" : "") .
                " ORDER BY start_at ASC LIMIT 1" .
                (!empty($lockRows) ? " FOR UPDATE" : "")
            ),
            'operational.appointments.03.agenda_conflict_message.02' => (
                "SELECT p.full_name FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=?"
            ),
            'operational.appointments.03.agenda_conflict_message.03' => (
                "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at < ? AND end_at > ?" .
                (!empty($ignoreBlock) ? " AND id<>?" : "") .
                (!empty($doctorScoped) ? " AND (doctor_user_id IS NULL OR doctor_user_id=?)" : "") .
                " ORDER BY start_at ASC LIMIT 1" .
                (!empty($lockRows) ? " FOR UPDATE" : "")
            ),
            'operational.appointments.03.agenda_conflict_message.04' => (
                "SELECT u.name FROM pi_users u WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) LIMIT 1"
            ),
            'operational.appointments.03.agenda_conflict_message.05' => (
                "SELECT id,doctor_user_id,start_at,end_at,created_by FROM pi_agenda_notes WHERE clinic_id=? AND doctor_user_id=? AND deleted_at IS NULL AND start_at IS NOT NULL AND end_at IS NOT NULL AND start_at < ? AND end_at > ? ORDER BY start_at ASC LIMIT 1" .
                (!empty($lockRows) ? " FOR UPDATE" : "")
            ),
            'operational.appointments.03.agenda_note_visible_for_day.01' => (
                "SELECT n.id,n.note_date,n.start_at,n.end_at,n.content,n.target_scope,n.target_role,n.created_by,n.updated_by,n.deleted_by,n.created_at,n.updated_at,n.deleted_at,u.name AS created_by_name FROM pi_agenda_notes n LEFT JOIN pi_users u ON u.id=n.created_by WHERE n.clinic_id=? AND n.note_date=? AND n.start_at IS NULL AND n.end_at IS NULL AND n.deleted_at IS NULL AND (n.target_scope IN ('clinic','all')" .
                                ((int) $roleCount > 0
                                    ? " OR (n.target_scope='role' AND n.target_role IN (" . OperationalSequenceSql::placeholders((int) $roleCount) . "))"
                                    : "") .
                                ") ORDER BY n.id DESC LIMIT 1"
            ),
            'operational.appointments.03.agenda_notes_timed_visible_for_day.01' => (
                "SELECT n.id,n.doctor_user_id,n.note_date,n.start_at,n.end_at,n.content,n.target_scope,n.target_role,n.created_by,n.updated_by,n.deleted_by,n.created_at,n.updated_at,n.deleted_at,u.name AS created_by_name FROM pi_agenda_notes n LEFT JOIN pi_users u ON u.id=n.created_by WHERE n.clinic_id=? AND n.doctor_user_id=? AND n.note_date=? AND n.start_at IS NOT NULL AND n.end_at IS NOT NULL AND n.deleted_at IS NULL ORDER BY n.start_at ASC,n.id DESC LIMIT 100"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
