<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class PeopleContextInvariant
{
    private const TABLES = [
        "pi_patients",
        "pi_patient_tabs",
        "pi_patient_guardians",
        "pi_leads",
        "pi_lead_events",
        "pi_care",
        "pi_care_content",
        "pi_care_versions",
    ];

    private function __construct() {

    }

    public static function supports(string $table): bool
    {

        return in_array($table, self::TABLES, true);
    }

    public static function assertWrite(string $table): array
    {

        return [
            "context" => "people",
            "checked" => true,
            "entity_table" => $table,
        ];
    }
}
