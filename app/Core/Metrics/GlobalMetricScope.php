<?php
declare(strict_types=1);
namespace Prontoo\Core\Metrics;
use Prontoo\Core\Tenant\TenantRegistry;
final class GlobalMetricScope
{
    private function __construct() {

    }
    public static function countNote(): string
    {

        return TenantRegistry::modelClinicId() > 0
            ? "consultório isento do Desenvolvedor descontado"
            : "consultórios isentos do Desenvolvedor descontados quando existirem";
    }
}
