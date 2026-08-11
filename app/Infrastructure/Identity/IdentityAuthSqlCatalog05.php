<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityAuthSqlCatalog05
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.auth05.upsert_person.01' => (
                "SELECT id FROM pi_persons WHERE cpf=?"
            ),
            'identity.auth05.upsert_person.02' => (
                "SELECT full_name,birth_date FROM pi_persons WHERE id=?"
            ),
            'identity.auth05.upsert_person.03' => (
                'UPDATE pi_persons SET ' . implode(',', array_filter([
                    !empty($update_name) ? 'full_name=?' : null,
                    !empty($update_birth) ? 'birth_date=?' : null,
                    'updated_at=NOW()',
                ])) . ' WHERE id=?'
            ),
            'identity.auth05.upsert_person.04' => (
                "SELECT birth_date FROM pi_persons WHERE id=?"
            ),
            'identity.auth05.upsert_person.05' => (
                "INSERT INTO pi_persons (full_name,cpf,birth_date,assinatura,created_at) VALUES (?,?,?,?,NOW())"
            ),
            'identity.auth05.lock_person_user_identity.01' => (
                "SELECT id FROM pi_persons WHERE id=? FOR UPDATE"
            ),
            'identity.auth05.save_person_flexible.01' => (
                "SELECT id FROM pi_persons WHERE cpf=? LIMIT 1"
            ),
            'identity.auth05.save_person_flexible.02' => (
                "SELECT full_name,birth_date,clinic_id FROM pi_persons WHERE id=?"
            ),
            'identity.auth05.save_person_flexible.03' => (
                'UPDATE pi_persons SET ' . implode(',', array_filter([
                    !empty($update_clinic) ? 'clinic_id=?' : null,
                    !empty($update_name) ? 'full_name=?' : null,
                    !empty($update_birth) ? 'birth_date=?' : null,
                    'updated_at=NOW()',
                ])) . ' WHERE id=?'
            ),
            'identity.auth05.save_person_flexible.04' => (
                "SELECT birth_date FROM pi_persons WHERE id=?"
            ),
            'identity.auth05.save_person_flexible.05' => (
                "INSERT INTO pi_persons (full_name,cpf,birth_date,assinatura,clinic_id,created_at) VALUES (?,?,?,?,?,NOW())"
            ),
            'identity.auth05.page_person_lookup.01' => (
                "SELECT full_name,cpf,birth_date FROM pi_persons WHERE cpf=? LIMIT 1"
            ),
            'identity.auth05.page_person_lookup.02' => (
                "SELECT p.full_name,p.cpf,p.birth_date
                                 FROM pi_persons p
                                 WHERE p.cpf=?
                                   AND (
                                     EXISTS (SELECT 1 FROM pi_patients pat WHERE pat.person_id=p.id AND pat.clinic_id=?)
                                     OR EXISTS (SELECT 1 FROM pi_leads l WHERE l.person_id=p.id AND l.clinic_id=?)
                                     OR EXISTS (
                                       SELECT 1
                                       FROM pi_users u
                                       JOIN pi_user_roles ur ON ur.user_id=u.id
                                       WHERE u.person_id=p.id AND ur.clinic_id=?
                                     )
                                   )
                                 LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
