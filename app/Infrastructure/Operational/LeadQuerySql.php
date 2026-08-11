<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use Prontoo\Domain\Leads\LeadsDomainOperations01;

final class LeadQuerySql
{
    private function __construct()
    {
    }

    public static function active(string $column = 'stage'): string
    {
        $active = array_map(
            static fn(string $stage): string => "'" . str_replace("'", "''", $stage) . "'",
            LeadsDomainOperations01::lead_active_stages(),
        );
        return '(' . self::column($column) . ') IN (' . implode(',', $active) . ')';
    }

    public static function column(string $column = 'stage'): string
    {
        return preg_replace('/[^A-Za-z0-9_\.]+/', '', $column) ?: 'stage';
    }
}
