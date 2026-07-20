<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class DocumentContextInvariant
{
    private const TABLES = [
        "pi_document_templates",
        "pi_documents",
        "pi_document_pdfs",
    ];

    private function __construct() {}

    public static function supports(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }

    public static function assertWrite(string $table): array
    {
        return [
            "context" => "documents",
            "checked" => true,
            "entity_table" => $table,
        ];
    }
}
