<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class DocumentPdfSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.document_pdf.01.document_pdf_cleanup.01' => (
                "DELETE FROM pi_document_pdfs WHERE clinic_id=? AND created_at < ?"
            ),
            'operational.document_pdf.01.document_pdf_cleanup.02' => (
                "DELETE FROM pi_document_pdfs WHERE created_at < ?"
            ),
            'operational.document_pdf.01.document_pdf_delete_pair.01' => (
                "DELETE FROM pi_document_pdfs WHERE clinic_id=? AND file_name=?"
            ),
            'operational.document_pdf.01.document_pdf_register_file.01' => (
                "INSERT INTO pi_document_pdfs (clinic_id,document_id,generated_by,document_identifier,generated_unix,file_name,file_path,file_sha256,file_size,created_at) VALUES (?,?,?,?,?,?,?,?,?,FROM_UNIXTIME(?)) ON DUPLICATE KEY UPDATE clinic_id=VALUES(clinic_id), document_id=VALUES(document_id), generated_by=VALUES(generated_by), document_identifier=VALUES(document_identifier), generated_unix=VALUES(generated_unix), file_path=VALUES(file_path), file_sha256=VALUES(file_sha256), file_size=VALUES(file_size), created_at=VALUES(created_at)"
            ),
            'operational.document_pdf.01.document_pdf_catalog_row.01' => (
                "SELECT clinic_id,document_id,file_sha256,generated_unix,created_at FROM pi_document_pdfs WHERE file_name=? LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
