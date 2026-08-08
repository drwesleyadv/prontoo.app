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
        'app/Core/',
        'app/Domain/Authorization/',
        'app/Domain/Identity/',
        'app/Domain/Appointments/AppointmentsDomainOperations01.php',
        'app/Domain/AuditActivity/ActivityDisplayPolicy.php',
        'app/Domain/AuditActivity/ActivityTargetPolicy.php',
        'app/Domain/AuditActivity/ActivityTaxonomy.php',
        'app/Domain/AuditActivity/ActivityValuePolicy.php',
        'app/Domain/AuditActivity/AuditActivityDomainOperations03.php',
        'app/Domain/AuditActivity/AuditCopyPolicy.php',
        'app/Domain/AuditActivity/AuditDocumentPolicy.php',
        'app/Domain/AuditActivity/AuditRecordPolicy.php',
        'app/Domain/AuditActivity/AuditTargetPolicy.php',
        'app/Domain/AuditActivity/AuditWritePolicy.php',
        'app/Domain/ClinicConfig/ClinicConfigDomainOperations01.php',
        'app/Domain/ClinicConfig/ClinicConfigDomainOperations02.php',
        'app/Domain/DocumentPdf/DocumentPdfDomainOperations01.php',
        'app/Domain/Documents/DocumentHtmlPolicy.php',
        'app/Domain/Documents/DocumentIdentifierPolicy.php',
        'app/Domain/Documents/DocumentTemplatePolicy.php',
        'app/Domain/Documents/DocumentTypePolicy.php',
        'app/Domain/Documents/DocumentsCompatibilityOperations01.php',
        'app/Domain/Documents/DocumentsDomainOperations02.php',
        'app/Domain/Financial/FinancialDomainOperations01.php',
        'app/Domain/Leads/LeadsDomainOperations01.php',
        'app/Domain/Maestro/MaestroDomainOperations01.php',
        'app/Domain/Maestro/MaestroDomainOperations02.php',
        'app/Domain/Patients/PatientsDomainOperations01.php',
        'app/Domain/SubscriptionSettings/SubscriptionSettingsDomainOperations01.php',
        'app/Domain/TasksNotices/TasksNoticesDomainOperations01.php',
        'app/Domain/UsersPermissions/UsersPermissionsDomainOperations01.php',
        'app/Domain/Patients/PatientPure.php',
        'app/Application/',
        'app/Infrastructure/',
        'app/Presentation/',
        'app/Runtime/',
    ];

    private const ENVIRONMENT_PHP_PATHS = [
        'app/config.php',
    ];

    private function __construct() {
    }

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
            $path === 'Core/Architecture/ArchitectureVerifier.php',
            $path === 'Core/Install/RuntimeContract.php',
            $path === 'Core/Install/InstallAccess.php',
            $path === 'Core/Database/SchemaMutationLock.php' => self::COMPOSITION,
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

    public static function phpFiles(string $root): array
    {
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
