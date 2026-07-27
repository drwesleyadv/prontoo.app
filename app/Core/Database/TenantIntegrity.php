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
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.TenantIntegrity::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Database/TenantIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
    }
    public static function assertRegistryMatchesSchema(
        bool $strict = false,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.TenantIntegrity::assertRegistryMatchesSchema
         * Responsabilidade: Implementa a responsabilidade “assert registry matches schema” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/TenantIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::assert`.
         * Dependências chamadas: `function_exists`, `->query`, `->fetchAll`, `error_log`, `->getMessage`, `strtolower`, `self::rowValue`, `str_starts_with`, `substr`, `array_values`, `array_unique`, `array_keys` e mais 6.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
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
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.TenantIntegrity::writeDiagnostic
         * Responsabilidade: Implementa a responsabilidade “write diagnostic” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/TenantIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.TenantIntegrity::assertRegistryMatchesSchema`.
         * Dependências chamadas: `function_exists`, `date`, `defined`, `array_values`, `array_diff`, `file_put_contents`, `json_encode`, `chmod`.
         * Efeitos colaterais: produz conteúdo de saída; acessa o sistema de arquivos.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
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
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.TenantIntegrity::rowValue
         * Responsabilidade: Implementa a responsabilidade “row value” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/TenantIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.TenantIntegrity::assertRegistryMatchesSchema`.
         * Dependências chamadas: `strtolower`, `strtoupper`, `array_key_exists`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        foreach ($keys as $key) {
            foreach ([$key, strtolower($key), strtoupper($key)] as $candidate) {
                if (
                    array_key_exists($candidate, $row) &&
                    $row[$candidate] !== null
                ) {
                    return trim((string) $row[$candidate]);
                }
            }
        }
        if (
            array_key_exists($numericIndex, $row) &&
            $row[$numericIndex] !== null
        ) {
            return trim((string) $row[$numericIndex]);
        }
        return "";
    }
}
