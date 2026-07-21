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

    private function __construct() {}

    public static function layerFor(string $relativePath): ?string
    {
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
        $path = str_replace('\\', '/', ltrim($relativePath, '/'));
        return in_array($path, self::ENVIRONMENT_PHP_PATHS, true);
    }

    /** @return list<string> */
    public static function environmentPhpPaths(): array
    {
        return self::ENVIRONMENT_PHP_PATHS;
    }

    public static function layerFromNamespace(string $namespace): ?string
    {
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
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (!is_dir($root)) {
            return [];
        }
        $excluded = ['/storage/', '/vendor/', '/node_modules/', '/.git/'];
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
