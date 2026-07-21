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
use Prontoo\Infrastructure\Database\SeqContract;
use Prontoo\Presentation\Http\ActionMiddleware;
use Prontoo\Runtime\LayeredKernel;

final class RuntimeContract
{
    private const REPOSITORY_ONLY_ERROR_PREFIXES = [
        'architecture_native_files_below_baseline:',
        'architecture_transitional_files_above_ceiling:',
    ];

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function requiredCoreFunctions(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::requiredCoreFunctions
         * Responsabilidade: Implementa a responsabilidade “required core functions” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::requiredFunctions`, `Core.Install.RuntimeContract::assert`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
         */
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
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::requiredFullFunctions
         * Responsabilidade: Implementa a responsabilidade “required full functions” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::requiredFunctions`, `Core.Install.RuntimeContract::assert`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return ['page_home', 'audit', 'audit_items', 'verify_audit_row'];
    }

    public static function requiredFunctions(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::requiredFunctions
         * Responsabilidade: Implementa a responsabilidade “required functions” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `array_values`, `array_unique`, `array_merge`, `self::requiredCoreFunctions`, `self::requiredFullFunctions`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return array_values(array_unique(array_merge(
            self::requiredCoreFunctions(),
            self::requiredFullFunctions(),
        )));
    }

    public static function requiredClasses(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::requiredClasses
         * Responsabilidade: Implementa a responsabilidade “required classes” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::assertTypes`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return [
            LayerMap::class,
            ArchitectureVerifier::class,
            ActionContract::class,
            ActionCatalog::class,
            AuthorizationService::class,
            RuntimeCapabilityProvider::class,
            SeqContract::class,
            PdoActionProofStore::class,
            ActionMiddleware::class,
            LayeredKernel::class,
        ];
    }

    public static function requiredInterfaces(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::requiredInterfaces
         * Responsabilidade: Implementa a responsabilidade “required interfaces” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::assertTypes`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return [CapabilityProvider::class, ActionProofPort::class];
    }

    public static function requiredFiles(string $root, string $version): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::requiredFiles
         * Responsabilidade: Implementa a responsabilidade “required files” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::assert`.
         * Dependências chamadas: `ltrim`, `array_values`, `array_unique`, `array_map`, `rtrim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $files = [
            'version.json',
            'app/update.manifest.json',
            'app/architecture.manifest.json',
            'app/Database/operational-schema.contract.json',
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
            static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de núcleo de invariantes e decisões canônicas. Dependências diretas: `rtrim`, `ltrim`. Efeitos: transformação local sem efeito externo detectado. */ fn(string $file): string => rtrim($root, '/') . '/' . ltrim($file, '/'),
            $files,
        )));
    }

    public static function optionalFiles(string $root, string $version): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::optionalFiles
         * Responsabilidade: Implementa a responsabilidade “optional files” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return [];
    }

    private static function assertFunctions(array $functions): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::assertFunctions
         * Responsabilidade: Implementa a responsabilidade “assert functions” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::assert`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        foreach ($functions as $function) {
            if (!\function_exists($function)) {
                throw new \RuntimeException('Função essencial ausente: ' . $function);
            }
        }
    }

    private static function assertTypes(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::assertTypes
         * Responsabilidade: Implementa a responsabilidade “assert types” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::assert`.
         * Dependências chamadas: `self::requiredClasses`, `self::requiredInterfaces`.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
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
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::fullRuntimeExpected
         * Responsabilidade: Implementa a responsabilidade “full runtime expected” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::assert`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (\function_exists('prontoo_use_light_boot') && \prontoo_use_light_boot()) {
            return false;
        }
        return true;
    }

    private static function assertVersionContract(string $root, string $version): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::assertVersionContract
         * Responsabilidade: Implementa a responsabilidade “assert version contract” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::assert`.
         * Dependências chamadas: `rtrim`, `is_file`, `file_get_contents`, `is_string`, `json_decode`, `is_array`.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: acessa o sistema de arquivos; pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
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
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::assertRuntimeArchitecture
         * Responsabilidade: Implementa a responsabilidade “assert runtime architecture” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.RuntimeContract::assert`.
         * Dependências chamadas: `ArchitectureVerifier::report`, `str_starts_with`, `error_log`, `implode`, `array_slice`.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
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
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.RuntimeContract::assert
         * Responsabilidade: Avalia ou impõe a regra “assert”, falhando de forma controlada quando a pré-condição não é satisfeita.
         * Local arquitetural: app/Core/Install/RuntimeContract.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `runtime_self_check`.
         * Dependências chamadas: `self::assertVersionContract`, `self::assertTypes`, `self::assertFunctions`, `self::requiredCoreFunctions`, `self::assertRuntimeArchitecture`, `self::fullRuntimeExpected`, `self::requiredFullFunctions`, `self::requiredFiles`, `is_file`, `str_replace`, `getenv`, `SeqContract::assert` e mais 1.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
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
            SeqContract::assert(\pdo());
            \Prontoo\Core\Database\TenantIntegrity::assertRegistryMatchesSchema($strict);
        }
    }
}
