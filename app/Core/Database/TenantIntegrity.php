<?php
declare(strict_types=1);
namespace Prontoo\Core\Database;
use Prontoo\Core\Tenant\TenantRegistry;
final class TenantIntegrity
{
    private const GOVERNANCE_TABLES_WITH_CLINIC_ID = [
        "pi_audit",
        "pi_scope_violations",
        "pi_action_ledger",
        "pi_clinics",
        "pi_persons",
        "pi_error_events",
        "pi_user_devices",
    ];
    private function __construct() {

    }
    public static function assertRegistryMatchesSchema(
        bool $strict = false,
    ): void {

        if (!function_exists("pdo")) {
            return;
        }
        try {
            $stmt = \pdo()->query(
                "SELECT TABLE_NAME AS tenant_table_name, COLUMN_NAME AS tenant_column_name " .
                    "FROM information_schema.columns " .
                    "WHERE table_schema=DATABASE() " .
                    "AND table_name LIKE 'pi\\_%' " .
                    "AND column_name='clinic_id'",
            );
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            error_log(
                "[Prontoo tenant integrity] não foi possível inspecionar schema: " .
                    $e->getMessage(),
            );
            return;
        }
        $schemaTables = [];
        foreach ($rows as $row) {
            $table = strtolower(
                self::rowValue(
                    $row,
                    ["tenant_table_name", "TABLE_NAME", "table_name"],
                    0,
                ),
            );
            if ($table === "") {
                error_log(
                    "[Prontoo tenant integrity] linha de schema sem nome de tabela; inspeção ignorada.",
                );
                continue;
            }
            if (str_starts_with($table, "pi_")) {
                $table = "pi_" . substr($table, 3);
            }
            $schemaTables[] = $table;
        }
        $schemaTables = array_values(array_unique($schemaTables));
        $registered = array_keys(TenantRegistry::scopedTables());
        $missingRegistered = [];
        foreach ($registered as $table) {
            if (!in_array($table, $schemaTables, true)) {
                $missingRegistered[] = $table;
            }
        }
        if ($strict && $missingRegistered !== []) {
            error_log(
                "[Prontoo tenant integrity] tabela(s) registrada(s) ainda ausente(s): " .
                    implode(", ", $missingRegistered),
            );
        }
        $unknown = array_diff(
            $schemaTables,
            $registered,
            self::GOVERNANCE_TABLES_WITH_CLINIC_ID,
        );
        if ($unknown !== []) {
            $message =
                "Tabela(s) com clinic_id fora do registro de isolamento: " .
                implode(", ", $unknown);
            error_log("[Prontoo tenant integrity] " . $message);
            self::writeDiagnostic($schemaTables, $registered, $unknown);
            if (
                $strict &&
                defined("PRONTOO_TENANT_INTEGRITY_STRICT") &&
                PRONTOO_TENANT_INTEGRITY_STRICT
            ) {
                throw new \RuntimeException($message);
            }
        } else {
            self::writeDiagnostic($schemaTables, $registered, []);
        }
    }
    private static function writeDiagnostic(
        array $schemaTables,
        array $registered,
        array $unknown,
    ): void {

        if (!function_exists("storage_path")) {
            return;
        }
        $payload = [
            "checked_at" => date("c"),
            "strict_runtime" => defined("PRONTOO_TENANT_INTEGRITY_STRICT")
                ? (bool) PRONTOO_TENANT_INTEGRITY_STRICT
                : false,
            "schema_tables_with_clinic_id" => array_values($schemaTables),
            "registered_scoped_tables" => array_values($registered),
            "governance_tables_with_clinic_id" =>
                self::GOVERNANCE_TABLES_WITH_CLINIC_ID,
            "unregistered_tables_with_clinic_id" => array_values($unknown),
            "registered_tables_not_found_yet" => array_values(
                array_diff($registered, $schemaTables),
            ),
        ];
        $file = \storage_path("tenant_integrity.json");
        @file_put_contents(
            $file,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX,
        );
        @chmod($file, 0640);
    }
    private static function rowValue(
        array $row,
        array $keys,
        int $numericIndex,
    ): string {

        foreach ($keys as $key) {
            foreach ([$key, strtolower($key), strtoupper($key)] as $candidate) {
                if (
                    array_key_exists($candidate, $row) &&
                    $row[$candidate] !== null
                ) {
                    return mb_trim((string) $row[$candidate]);
                }
            }
        }
        if (
            array_key_exists($numericIndex, $row) &&
            $row[$numericIndex] !== null
        ) {
            return mb_trim((string) $row[$numericIndex]);
        }
        return "";
    }
}
