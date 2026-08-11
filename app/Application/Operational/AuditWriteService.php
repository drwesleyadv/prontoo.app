<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

use Closure;
use RuntimeException;

final class AuditWriteService
{
    public function __construct(private OperationalUseCaseService $data)
    {
    }

    public function write(Closure $recordParameters): void
    {
        $this->data->atomic(function () use ($recordParameters): void {
            $parameters = $recordParameters();
            if (!is_array($parameters) || count($parameters) !== 19) {
                throw new RuntimeException('Registro de auditoria inválido.');
            }
            $this->data->result(
                'operational.audit_activity.04.audit.01',
                $parameters,
            );
        });
    }
}
