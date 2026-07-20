<?php
declare(strict_types=1);

namespace Prontoo\Core\Install;

use Prontoo\Application\Audit\ActionProofPort;
use Prontoo\Application\Authorization\ActionCatalog;
use Prontoo\Application\Authorization\AuthorizationService;
use Prontoo\Application\Authorization\CapabilityProvider;
use Prontoo\Core\Architecture\ArchitectureVerifier;
use Prontoo\Core\Architecture\LayerMap;
use Prontoo\Domain\Authorization\ActionContract;
use Prontoo\Infrastructure\Audit\PdoActionProofStore;
use Prontoo\Infrastructure\Authorization\RuntimeCapabilityProvider;
use Prontoo\Presentation\Http\ActionMiddleware;
use Prontoo\Runtime\LayeredKernel;

final class RuntimeContract
{
    private const REPOSITORY_ONLY_ERROR_PREFIXES = [
        'architecture_native_files_below_baseline:',
        'architecture_transitional_files_above_ceiling:',
    ];

    private function __construct() {}

    public static function requiredCoreFunctions(): array
    {
        return [
            'csrf',
            'csrf_field',
            'ctx',
            'q',
            'one',
            'val',
            'sql_write_scope_guard',
            'tenant_scoped_tables',
            'read_only_write_allowed_for_sql',
            'enforce_action_integrity',
            'page',
            'route',
            'redirect',
            'app_fail',
        ];
    }

    public static function requiredFullFunctions(): array
    {
        return ['page_home', 'audit', 'audit_items', 'verify_audit_row'];
    }

    public static function requiredFunctions(): array
    {
        return array_values(array_unique(array_merge(
            self::requiredCoreFunctions(),
            self::requiredFullFunctions(),
        )));
    }

    public static function requiredClasses(): array
    {
        return [
            LayerMap::class,
            ArchitectureVerifier::class,
            ActionContract::class,
            ActionCatalog::class,
            AuthorizationService::class,
            RuntimeCapabilityProvider::class,
            PdoActionProofStore::class,
            ActionMiddleware::class,
            LayeredKernel::class,
        ];
    }

    public static function requiredInterfaces(): array
    {
        return [CapabilityProvider::class, ActionProofPort::class];
    }

    public static function requiredFiles(string $root, string $version): array
    {
        $files = [
            'version.json',
            'app/update.manifest.json',
            'app/architecture.manifest.json',
            'br/index.php',
            'app/bootstrap_architecture.php',
            'app/bootstrap_specialized.php',
            'app/Database/schema.sql',
            'public/assets/design-system.css',
            'public/assets/app.js',
        ];
        if (\function_exists('prontoo_full_runtime_modules')) {
            foreach (\prontoo_full_runtime_modules() as $module) {
                $files[] = 'app/' . ltrim((string) $module, '/');
            }
        }
        return array_values(array_unique(array_map(
            static fn(string $file): string => rtrim($root, '/') . '/' . ltrim($file, '/'),
            $files,
        )));
    }

    public static function optionalFiles(string $root, string $version): array
    {
        return [];
    }

    private static function assertFunctions(array $functions): void
    {
        foreach ($functions as $function) {
            if (!\function_exists($function)) {
                throw new \RuntimeException('Função essencial ausente: ' . $function);
            }
        }
    }

    private static function assertTypes(): void
    {
        foreach (self::requiredClasses() as $class) {
            if (!\class_exists($class)) {
                throw new \RuntimeException('Classe arquitetural ausente: ' . $class);
            }
        }
        foreach (self::requiredInterfaces() as $interface) {
            if (!\interface_exists($interface)) {
                throw new \RuntimeException('Porta arquitetural ausente: ' . $interface);
            }
        }
    }

    private static function fullRuntimeExpected(): bool
    {
        if (\function_exists('prontoo_use_light_boot') && \prontoo_use_light_boot()) {
            return false;
        }
        return true;
    }

    private static function assertVersionContract(string $root, string $version): void
    {
        if (\function_exists('prontoo_version_contract_status')) {
            $status = \prontoo_version_contract_status();
            if (empty($status['ok'])) {
                throw new \RuntimeException(
                    'Contrato de versão divergente: ' .
                        \implode(', ', \array_map('strval', (array) ($status['issues'] ?? []))),
                );
            }
            if ((string) ($status['version'] ?? '') !== $version) {
                throw new \RuntimeException('Versão canônica divergente do runtime.');
            }
            return;
        }
        $file = rtrim($root, '/') . '/version.json';
        $raw = is_file($file) ? @file_get_contents($file) : false;
        $json = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($json) ||
            (string) ($json['version'] ?? '') !== $version ||
            (string) ($json['release'] ?? '') !== $version) {
            throw new \RuntimeException('version.json diverge da versão do runtime.');
        }
    }

    private static function assertRuntimeArchitecture(string $root): void
    {
        $report = ArchitectureVerifier::report($root, false);
        $runtimeErrors = [];
        $repositoryOnly = [];
        foreach ((array) ($report['errors'] ?? []) as $error) {
            $error = (string) $error;
            $repositoryMetric = false;
            foreach (self::REPOSITORY_ONLY_ERROR_PREFIXES as $prefix) {
                if (str_starts_with($error, $prefix)) {
                    $repositoryMetric = true;
                    break;
                }
            }
            if ($repositoryMetric) {
                $repositoryOnly[] = $error;
            } else {
                $runtimeErrors[] = $error;
            }
        }
        if ($repositoryOnly !== []) {
            error_log(
                '[Prontoo architecture repository metric] ' .
                implode(', ', array_slice($repositoryOnly, 0, 12)),
            );
        }
        if ($runtimeErrors !== []) {
            throw new \RuntimeException(
                'Contrato arquitetural divergente: ' .
                implode(', ', array_slice($runtimeErrors, 0, 12)),
            );
        }
    }

    public static function assert(string $root, string $version): void
    {
        self::assertVersionContract($root, $version);
        self::assertTypes();
        self::assertFunctions(self::requiredCoreFunctions());
        self::assertRuntimeArchitecture($root);

        if (self::fullRuntimeExpected()) {
            self::assertFunctions(self::requiredFullFunctions());
            if (\function_exists('prontoo_route_map')) {
                foreach (\prontoo_route_map() as $route) {
                    $function = 'page_' . $route;
                    if (!\function_exists($function)) {
                        throw new \RuntimeException('Rota sem função de página: ' . $function);
                    }
                }
            }
        }
        foreach (self::requiredFiles($root, $version) as $file) {
            if (!is_file($file)) {
                throw new \RuntimeException(
                    'Arquivo essencial ausente: ' . str_replace($root . '/', '', $file),
                );
            }
        }
        $updateValidation = (string) getenv('PRONTOO_UPDATE_VALIDATION') === '1';
        if (!$updateValidation && \function_exists('has_cfg') && \has_cfg()) {
            $strict = is_file($root . '/storage/install.lock');
            \ensure_runtime_schema_minimum();
            \Prontoo\Core\Database\TenantIntegrity::assertRegistryMatchesSchema($strict);
        }
    }
}
