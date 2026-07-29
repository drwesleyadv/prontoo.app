<?php
declare(strict_types=1);
namespace Prontoo\Core\Metrics;
use Prontoo\Core\Tenant\TenantRegistry;
final class GlobalMetricScope
{
    private function __construct() {
        








    }
    public static function modelClinicSql(string $column = "clinic_id"): string
    {
        








        return TenantRegistry::excludeModelClinicSql($column);
    }
    public static function modelClinicWhere(
        string $column = "clinic_id",
    ): string {
        








        return TenantRegistry::excludeModelClinicWhere($column);
    }
    public static function countNote(): string
    {
        








        return TenantRegistry::modelClinicId() > 0
            ? "consultório isento do Desenvolvedor descontado"
            : "consultórios isentos do Desenvolvedor descontados quando existirem";
    }
}
