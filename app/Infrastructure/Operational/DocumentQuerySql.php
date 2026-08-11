<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

final class DocumentQuerySql
{
    private function __construct()
    {
    }

    public static function templateVisibility(string $mode): string
    {
        return match ($mode) {
            'manager' => '1=1',
            'doctor' => "(dt.owner_user_id=? OR dt.owner_role IN ('medico','assistente','recepcionista'))",
            default => '(dt.owner_user_id=? OR dt.owner_role=?)',
        };
    }

    public static function issuedDocumentVisibility(string $role): string
    {
        return match ($role) {
            'gerente' => '',
            'medico' => " AND (d.issued_by=? OR dt.owner_user_id=? OR dt.owner_role IN ('medico','assistente','recepcionista'))",
            default => ' AND (d.issued_by=? OR dt.owner_user_id=? OR dt.owner_role=?)',
        };
    }

    public static function issuedDocumentSearch(bool $enabled): string
    {
        return $enabled
            ? " AND (d.title LIKE ? OR d.content LIKE ? OR d.type_key LIKE ? OR p.full_name LIKE ? OR u.name LIKE ? OR UPPER(COALESCE(d.document_identifier,'')) LIKE ?)"
            : '';
    }
}
