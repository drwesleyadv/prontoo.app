<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Modules;

use Prontoo\Core\Architecture\LayerMap;
use RuntimeException;

final class RuntimeModuleLoader
{
    private bool $runtimeCoreLoaded = false;
    private bool $fullRuntimeLoaded = false;
    private bool $financialGuardLoaded = false;
    private array $loadedRouteGroups = [];

    public function __construct(private readonly string $appRoot)
    {
    }

    public function moduleFile(string $relative): string
    {
        return rtrim($this->appRoot, '/\\') . '/' . ltrim($relative, '/');
    }

    public function moduleLayer(string $relative): string
    {
        $relative = ltrim($relative, '/');
        $layer = LayerMap::layerFor('app/' . $relative);
        if ($layer === null) {
            throw new RuntimeException('Módulo sem camada arquitetural: app/' . $relative);
        }
        return $layer;
    }

    public function requireModule(string $relative): void
    {
        $file = $this->moduleFile($relative);
        if (!is_file($file)) {
            $repositoryRoot = defined('PRONTOO_ROOT') ? (string) constant('PRONTOO_ROOT') : dirname($this->appRoot);
            throw new RuntimeException(
                'Módulo especializado essencial ausente: ' . str_replace(rtrim($repositoryRoot, '/\\') . '/', '', $file),
            );
        }
        $this->moduleLayer($relative);
        require_once $file;
    }

    public function requireModules(array $files): void
    {
        foreach ($files as $file) {
            $this->requireModule((string) $file);
        }
    }

    public function loadRuntimeCore(bool $lightBoot): void
    {
        if ($this->runtimeCoreLoaded) {
            return;
        }
        $this->runtimeCoreLoaded = true;
        $this->requireModules($lightBoot ? RuntimeModuleCatalog::coreModules() : RuntimeModuleCatalog::fullModules());
    }

    public function loadFullRuntime(): void
    {
        if ($this->fullRuntimeLoaded) {
            return;
        }
        $this->requireModules(RuntimeModuleCatalog::fullModules());
        $this->fullRuntimeLoaded = true;
    }

    public function loadRouteModules(string $route): void
    {
        foreach (RuntimeModuleCatalog::routeModuleGroups($route) as $group => $files) {
            if (isset($this->loadedRouteGroups[$group])) {
                continue;
            }
            $this->requireModules($files);
            $this->loadedRouteGroups[$group] = true;
        }
    }

    public function layerCoverage(): array
    {
        $modules = RuntimeModuleCatalog::fullModules();
        $layers = [];
        foreach ($modules as $module) {
            $layer = $this->moduleLayer((string) $module);
            $layers[$layer] = ($layers[$layer] ?? 0) + 1;
        }
        return [
            'ok' => array_sum($layers) === count($modules),
            'modules' => count($modules),
            'classified' => array_sum($layers),
            'coverage_percent' => $modules !== [] ? 100.0 : 0.0,
            'layers' => $layers,
        ];
    }

    public function loadFinancialGuard(): void
    {
        if ($this->financialGuardLoaded) {
            return;
        }
        $this->financialGuardLoaded = true;
        $this->requireModule('Domain/Financial/Financial.php');
    }
}
