<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

final class OperationalSequenceSql
{
    private function __construct()
    {
    }

    public static function dailyMutationSeries(int $days = 30): string
    {
        $columns = [];
        for ($index = 0; $index < max(1, $days); $index++) {
            $columns[] = "SUM(CASE WHEN created_at>=? AND created_at<? AND status='committed' THEN mutation_count ELSE 0 END) AS d{$index}";
        }
        return 'SELECT ' . implode(',', $columns) . ' FROM pi_action_ledger WHERE created_at>=? AND created_at<?';
    }

    public static function placeholders(int $count): string
    {
        return implode(',', array_fill(0, max(1, $count), '?'));
    }
}
