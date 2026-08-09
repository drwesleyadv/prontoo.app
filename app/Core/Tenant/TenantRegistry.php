<?php
declare(strict_types=1);
namespace Prontoo\Core\Tenant;
use Prontoo\Core\Support\Check;
final class TenantRegistry
{
    private static ?int $modelClinicCache = null;
    private const SCOPED_TABLES = [
        "pi_user_roles" => "clinic_id",
        "pi_clinic_roles" => "clinic_id",
        "pi_permissions" => "clinic_id",
        "pi_permission_rules" => "clinic_id",
        "pi_patients" => "clinic_id",
        "pi_patient_tabs" => "clinic_id",
        "pi_patient_guardians" => "clinic_id",
        "pi_leads" => "clinic_id",
        "pi_lead_events" => "clinic_id",
        "pi_appointments" => "clinic_id",
        "pi_blocks" => "clinic_id",
        "pi_agenda_notes" => "clinic_id",
        "pi_tasks" => "clinic_id",
        "pi_task_details" => "clinic_id",
        "pi_task_events" => "clinic_id",
        "pi_task_comments" => "clinic_id",
        "pi_notices" => "clinic_id",
        "pi_document_templates" => "clinic_id",
        "pi_documents" => "clinic_id",
        "pi_document_pdfs" => "clinic_id",
        "pi_user_work_hours" => "clinic_id",
        "pi_procedures" => "clinic_id",
        "pi_financial_accounts" => "clinic_id",
        "pi_financial_payment_methods" => "clinic_id",
        "pi_financial_counterparties" => "clinic_id",
        "pi_financial_revenues" => "clinic_id",
        "pi_financial_expenses" => "clinic_id",
        "pi_financial_allocations" => "clinic_id",
        "pi_financial_goals" => "clinic_id",
        "pi_financial_transfers" => "clinic_id",
        "pi_financial_locations" => "clinic_id",
        "pi_financial_location_users" => "clinic_id",
        "pi_cash_sessions" => "clinic_id",
        "pi_financial_movements" => "clinic_id",
        "pi_financial_daily_closings" => "clinic_id",
        "pi_cash_closing_reviews" => "clinic_id",
        "pi_subscription_payments" => "clinic_id",
        "pi_care" => "clinic_id",
        "pi_care_content" => "clinic_id",
        "pi_care_versions" => "clinic_id",
        "pi_clinic_daily_stats" => "clinic_id",
        "pi_maestro_rules" => "clinic_id",
        "pi_maestro_executions" => "clinic_id",
    ];
    private function __construct() {

    }
    public static function scopedTables(): array
    {

        return self::SCOPED_TABLES;
    }
    public static function isScoped(string $table): bool
    {

        return array_key_exists(strtolower($table), self::SCOPED_TABLES);
    }
    public static function scopeColumn(string $table): ?string
    {

        $table = strtolower($table);
        return self::SCOPED_TABLES[$table] ?? null;
    }
    private static function globalAdminExemptSubquery(): string
    {

        return "SELECT c.id FROM pi_clinics c LEFT JOIN pi_users owner_user ON owner_user.id=c.owner_user_id LEFT JOIN pi_users manager_user ON manager_user.id=c.manager_user_id WHERE c.subscription_status='exempt' AND (COALESCE(owner_user.is_global_admin,0)=1 OR COALESCE(manager_user.is_global_admin,0)=1)";
    }
    public static function excludeModelClinicSql(
        string $column = "clinic_id",
    ): string {

        $column = Check::scopedColumn($column);
        return " AND " .
            $column .
            " NOT IN (" .
            self::globalAdminExemptSubquery() .
            ") ";
    }
    public static function excludeModelClinicWhere(
        string $column = "clinic_id",
    ): string {

        $column = Check::scopedColumn($column);
        return $column . " NOT IN (" . self::globalAdminExemptSubquery() . ")";
    }
    public static function modelClinicId(): int
    {

        if (self::$modelClinicCache !== null) {
            return self::$modelClinicCache;
        }
        if (!\Prontoo\Core\Architecture\OperationGateway::has('safe_val')) {
            return self::$modelClinicCache = 0;
        }
        return self::$modelClinicCache = (int) \Prontoo\Core\Architecture\OperationGateway::invoke('safe_val', 
            "SELECT c.id FROM pi_clinics c LEFT JOIN pi_users owner_user ON owner_user.id=c.owner_user_id LEFT JOIN pi_users manager_user ON manager_user.id=c.manager_user_id WHERE c.subscription_status='exempt' AND (COALESCE(owner_user.is_global_admin,0)=1 OR COALESCE(manager_user.is_global_admin,0)=1) ORDER BY c.id ASC LIMIT 1",
            [],
            0,
        );
    }
    public static function resetModelClinicCache(): void
    {

        self::$modelClinicCache = null;
    }
}
