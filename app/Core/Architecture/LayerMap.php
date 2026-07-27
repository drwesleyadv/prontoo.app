<?php
declare(strict_types=1);

namespace Prontoo\Core\Architecture;

final class LayerMap
{
    public const CORE = 'core';
    public const DOMAIN = 'domain';
    public const APPLICATION = 'application';
    public const INFRASTRUCTURE = 'infrastructure';
    public const PRESENTATION = 'presentation';
    public const COMPOSITION = 'composition';

    private const NATIVE_PREFIXES = [
        'app/Core/Architecture/',
        'app/Core/Install/InstallAccess.php',
        'app/Core/Database/SchemaMutationLock.php',
        'app/Core/Database/SchemaHardening.php',
        'app/Domain/Authorization/',
        'app/Application/',
        'app/Infrastructure/',
        'app/Presentation/',
    ];

    private const ENVIRONMENT_PHP_PATHS = [
        'app/config.php',
    ];

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Architecture.LayerMap::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Architecture/LayerMap.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function layerFor(string $relativePath): ?string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Architecture.LayerMap::layerFor
         * Responsabilidade: Implementa a responsabilidade “layer for” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Architecture/LayerMap.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Architecture.ArchitectureVerifier::report`, `prontoo_module_layer`.
         * Dependências chamadas: `str_replace`, `ltrim`, `str_ends_with`, `strtolower`, `str_contains`, `str_starts_with`, `substr`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $path = str_replace('\\', '/', ltrim($relativePath, '/'));
        if ($path === '' || !str_ends_with(strtolower($path), '.php')) {
            return null;
        }

        if (!str_contains($path, '/')) {
            return self::COMPOSITION;
        }
        if (str_starts_with($path, 'br/') || str_starts_with($path, 'public/')) {
            return self::PRESENTATION;
        }
        if (str_starts_with($path, 'tools/')) {
            return self::COMPOSITION;
        }
        if (!str_starts_with($path, 'app/')) {
            return self::COMPOSITION;
        }

        $path = substr($path, 4);
        if (!str_contains($path, '/')) {
            return self::COMPOSITION;
        }
        return match (true) {
            $path === 'Core/Invariant/Request/ActionProof.php',
            $path === 'Core/Architecture/ArchitectureVerifier.php',
            $path === 'Core/Install/RuntimeContract.php' => self::COMPOSITION,
            str_starts_with($path, 'Core/') => self::CORE,
            str_starts_with($path, 'Domain/') => self::DOMAIN,
            str_starts_with($path, 'Application/') => self::APPLICATION,
            str_starts_with($path, 'Infrastructure/') => self::INFRASTRUCTURE,
            str_starts_with($path, 'Presentation/') => self::PRESENTATION,
            str_starts_with($path, 'Runtime/'),
            str_starts_with($path, 'Install/') => self::COMPOSITION,
            str_starts_with($path, 'Database/'),
            str_starts_with($path, 'Support/') => self::INFRASTRUCTURE,
            str_starts_with($path, 'Auth/'),
            str_starts_with($path, 'Admin/'),
            str_starts_with($path, 'Pages/'),
            str_starts_with($path, 'Ui/') => self::PRESENTATION,
            default => null,
        };
    }

    public static function isNativePath(string $relativePath): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Architecture.LayerMap::isNativePath
         * Responsabilidade: Implementa a responsabilidade “is native path” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Architecture/LayerMap.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Architecture.ArchitectureVerifier::report`, `Core.Architecture.ArchitectureVerifier::inspectLayerNativeFile`.
         * Dependências chamadas: `str_replace`, `ltrim`, `str_starts_with`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $path = str_replace('\\', '/', ltrim($relativePath, '/'));
        if ($path === 'app/Runtime/LayeredKernel.php') {
            return true;
        }
        foreach (self::NATIVE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    public static function isEnvironmentPhpPath(string $relativePath): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Architecture.LayerMap::isEnvironmentPhpPath
         * Responsabilidade: Implementa a responsabilidade “is environment php path” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Architecture/LayerMap.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Architecture.LayerMap::phpFiles`.
         * Dependências chamadas: `str_replace`, `ltrim`, `in_array`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $path = str_replace('\\', '/', ltrim($relativePath, '/'));
        return in_array($path, self::ENVIRONMENT_PHP_PATHS, true);
    }

    /** @return list<string> */
    public static function environmentPhpPaths(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Architecture.LayerMap::environmentPhpPaths
         * Responsabilidade: Implementa a responsabilidade “environment php paths” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Architecture/LayerMap.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return self::ENVIRONMENT_PHP_PATHS;
    }

    public static function layerFromNamespace(string $namespace): ?string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Architecture.LayerMap::layerFromNamespace
         * Responsabilidade: Implementa a responsabilidade “layer from namespace” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Architecture/LayerMap.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Architecture.ArchitectureVerifier::inspectDependencies`.
         * Dependências chamadas: `ltrim`, `str_starts_with`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $namespace = ltrim($namespace, '\\');
        return match (true) {
            str_starts_with($namespace, 'Prontoo\\Core\\') => self::CORE,
            str_starts_with($namespace, 'Prontoo\\Domain\\') => self::DOMAIN,
            str_starts_with($namespace, 'Prontoo\\Application\\') => self::APPLICATION,
            str_starts_with($namespace, 'Prontoo\\Infrastructure\\') => self::INFRASTRUCTURE,
            str_starts_with($namespace, 'Prontoo\\Presentation\\') => self::PRESENTATION,
            str_starts_with($namespace, 'Prontoo\\Runtime\\'),
            str_starts_with($namespace, 'Prontoo\\Install\\') => self::COMPOSITION,
            default => null,
        };
    }

    public static function dependencyAllowed(string $from, string $to): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Architecture.LayerMap::dependencyAllowed
         * Responsabilidade: Implementa a responsabilidade “dependency allowed” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Architecture/LayerMap.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Architecture.ArchitectureVerifier::inspectDependencies`.
         * Dependências chamadas: `in_array`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $allowed = [
            self::CORE => [self::CORE],
            self::DOMAIN => [self::CORE, self::DOMAIN],
            self::APPLICATION => [self::CORE, self::DOMAIN, self::APPLICATION],
            self::INFRASTRUCTURE => [self::CORE, self::DOMAIN, self::APPLICATION, self::INFRASTRUCTURE],
            self::PRESENTATION => [self::CORE, self::DOMAIN, self::APPLICATION, self::PRESENTATION],
            self::COMPOSITION => [
                self::CORE,
                self::DOMAIN,
                self::APPLICATION,
                self::INFRASTRUCTURE,
                self::PRESENTATION,
                self::COMPOSITION,
            ],
        ];
        return in_array($to, $allowed[$from] ?? [], true);
    }

    /** @return list<string> */
    public static function phpFiles(string $root): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Architecture.LayerMap::phpFiles
         * Responsabilidade: Implementa a responsabilidade “php files” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Architecture/LayerMap.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Architecture.ArchitectureVerifier::report`.
         * Dependências chamadas: `rtrim`, `str_replace`, `is_dir`, `->isFile`, `strtolower`, `->getExtension`, `->getPathname`, `ltrim`, `self::isEnvironmentPhpPath`, `str_contains`, `sort`.
         * Classes ou serviços instanciados: `.RecursiveIteratorIterator`, `.RecursiveDirectoryIterator`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (!is_dir($root)) {
            return [];
        }
        $excluded = ['/ssd/', '/vendor/', '/node_modules/', '/.git/'];
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            $normalized = '/' . ltrim(str_replace($root, '', $path), '/');
            $relative = ltrim($normalized, '/');
            if (self::isEnvironmentPhpPath($relative)) {
                continue;
            }
            $skip = false;
            foreach ($excluded as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    $skip = true;
                    break;
                }
            }
            if (!$skip) {
                $files[] = $path;
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }
}
