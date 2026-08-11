<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class SupportFoundationSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.support_foundation.02.person_signature_sync.01' => (
                "SELECT id,full_name,cpf,birth_date,assinatura FROM pi_persons WHERE id=?"
            ),
            'operational.support_foundation.02.person_signature_sync.02' => (
                "UPDATE pi_persons SET assinatura=?, updated_at=COALESCE(updated_at,NOW()) WHERE id=?"
            ),
            'operational.support_foundation.02.person_signature_sync.03' => (
                "SELECT assinatura FROM pi_persons WHERE id=?"
            ),
            'operational.support_foundation.02.person_identity_immutable_values.01' => (
                "SELECT cpf,birth_date FROM pi_persons WHERE id=?"
            ),
            'operational.support_foundation.02.meta_get.01' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key=?"
            ),
            'operational.support_foundation.02.meta_set.01' => (
                "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), updated_at=NOW()"
            ),
            'operational.support_foundation.02.log_runtime_error.01' => (
                "INSERT INTO pi_error_events (route,method,http_status,message,file,line,user_id,clinic_id,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.support_foundation.02.person_common_profile_update.01' => (
                "UPDATE pi_persons SET " . implode(",", $sets) . " WHERE id=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
