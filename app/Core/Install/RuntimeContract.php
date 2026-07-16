<?php
declare(strict_types=1);
namespace Prontoo\Core\Install;
final class RuntimeContract
{
    private function __construct() {}
    public static function requiredCoreFunctions(): array
    {
        return [
            "csrf",
            "csrf_field",
            "ctx",
            "q",
            "one",
            "val",
            "sql_write_scope_guard",
            "tenant_scoped_tables",
            "read_only_write_allowed_for_sql",
            "enforce_action_integrity",
            "page",
            "route",
            "redirect",
            "app_fail",
        ];
    }
    public static function requiredFullFunctions(): array
    {
        return ["page_home", "audit", "audit_items", "verify_audit_row"];
    }
    public static function requiredFunctions(): array
    {
        return array_values(
            array_unique(
                array_merge(
                    self::requiredCoreFunctions(),
                    self::requiredFullFunctions(),
                ),
            ),
        );
    }
    public static function requiredFiles(string $root, string $version): array
    {
        $files = [
            "version.json",
            "app/update.manifest.json",
            "br/index.php",
            "app/bootstrap_architecture.php",
            "app/bootstrap_specialized.php",
            "app/Database/schema.sql",
            "public/assets/design-system.css",
            "public/assets/app.js",
        ];
        if (\function_exists("prontoo_full_runtime_modules")) {
            foreach (\prontoo_full_runtime_modules() as $module) {
                $files[] = "app/" . ltrim((string) $module, "/");
            }
        }
        return array_values(
            array_unique(
                array_map(
                    static fn(string $file): string =>
                        rtrim($root, "/") . "/" . ltrim($file, "/"),
                    $files,
                ),
            ),
        );
    }
    public static function optionalFiles(string $root, string $version): array
    {
        return [];
    }
    private static function assertFunctions(array $functions): void
    {
        foreach ($functions as $fn) {
            if (!\function_exists($fn)) {
                throw new \RuntimeException("Função essencial ausente: " . $fn);
            }
        }
    }
    private static function fullRuntimeExpected(): bool
    {
        if (
            \function_exists("prontoo_use_light_boot") &&
            \prontoo_use_light_boot()
        ) {
            return false;
        }
        return true;
    }
    private static function assertVersionContract(string $root, string $version): void
    {
        if (\function_exists("prontoo_version_contract_status")) {
            $status = \prontoo_version_contract_status();
            if (empty($status["ok"])) {
                throw new \RuntimeException(
                    "Contrato de versão divergente: " .
                        \implode(", ", \array_map("strval", (array) ($status["issues"] ?? []))),
                );
            }
            if ((string) ($status["version"] ?? "") !== $version) {
                throw new \RuntimeException("Versão canônica divergente do runtime.");
            }
            return;
        }
        $file = \rtrim($root, "/") . "/version.json";
        $raw = \is_file($file) ? @\file_get_contents($file) : false;
        $json = \is_string($raw) ? \json_decode($raw, true) : null;
        if (!\is_array($json) ||
            (string) ($json["version"] ?? "") !== $version ||
            (string) ($json["release"] ?? "") !== $version) {
            throw new \RuntimeException("version.json diverge da versão do runtime.");
        }
    }
    public static function assert(string $root, string $version): void
    {
        self::assertVersionContract($root, $version);
        self::assertFunctions(self::requiredCoreFunctions());
        if (self::fullRuntimeExpected()) {
            self::assertFunctions(self::requiredFullFunctions());
            if (\function_exists("prontoo_route_map")) {
                foreach (\prontoo_route_map() as $route) {
                    $fn = "page_" . $route;
                    if (!\function_exists($fn)) {
                        throw new \RuntimeException(
                            "Rota sem função de página: " . $fn,
                        );
                    }
                }
            }
        }
        foreach (self::requiredFiles($root, $version) as $file) {
            if (!\is_file($file)) {
                throw new \RuntimeException(
                    "Arquivo essencial ausente: " .
                        str_replace($root . "/", "", $file),
                );
            }
        }
        $updateValidation =
            (string) \getenv("PRONTOO_UPDATE_VALIDATION") === "1";
        if (!$updateValidation && \function_exists("has_cfg") && \has_cfg()) {
            $strict = \is_file($root . "/storage/install.lock");
            \ensure_runtime_schema_minimum();
            \Prontoo\Core\Database\TenantIntegrity::assertRegistryMatchesSchema(
                $strict,
            );
        }
    }
}
