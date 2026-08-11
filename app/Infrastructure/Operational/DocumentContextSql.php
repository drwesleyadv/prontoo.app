<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use InvalidArgumentException;

final class DocumentContextSql
{
    private function __construct()
    {
    }

    public static function entityExists(string $entity): string
    {
        $table = match ($entity) {
            'lead' => 'pi_leads',
            'procedure' => 'pi_procedures',
            'task' => 'pi_tasks',
            'care' => 'pi_care',
            default => throw new InvalidArgumentException('Entidade de contexto documental inválida.'),
        };
        return "SELECT id FROM {$table} WHERE id=? AND clinic_id=? LIMIT 1";
    }
}
