<?php
declare(strict_types=1);

namespace Prontoo\Domain\Financial;

use RuntimeException;

final class FinancialMovementMutationPolicy
{
    private function __construct()
    {
    }

    public static function target(string $sql, array $params): ?array
    {
        $setClause = '';
        $whereClause = '';
        if (preg_match(
            '/^\s*UPDATE\s+`?pi_financial_movements`?\s+SET\s+(.*?)\s+WHERE\s+(.+)$/is',
            $sql,
            $match,
        )) {
            $setClause = (string) ($match[1] ?? '');
            $whereClause = mb_trim((string) ($match[2] ?? ''));
        } elseif (preg_match(
            '/^\s*DELETE\s+FROM\s+`?pi_financial_movements`?\s+WHERE\s+(.+)$/is',
            $sql,
            $match,
        )) {
            $whereClause = mb_trim((string) ($match[1] ?? ''));
        } else {
            return null;
        }
        if ($whereClause === '') {
            throw new RuntimeException('Mutação financeira sem predicado foi bloqueada.');
        }
        return [
            'where' => $whereClause,
            'params' => array_slice($params, substr_count($setClause, '?')),
        ];
    }
}
