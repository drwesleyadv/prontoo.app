<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use InvalidArgumentException;

final class NoticeQuerySql
{
    private function __construct()
    {
    }

    public static function target(string $alias = 'n'): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/i', $alias) !== 1) {
            throw new InvalidArgumentException('Alias de aviso inválido.');
        }
        return "({$alias}.target_scope='all' OR ({$alias}.target_scope='role' AND {$alias}.target_role=?) OR ({$alias}.target_scope='user' AND {$alias}.target_user_id=?))";
    }

    public static function targetOrCreator(string $alias = 'n'): string
    {
        return '(' . self::target($alias) . " OR {$alias}.created_by=?)";
    }
}
