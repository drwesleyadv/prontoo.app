<?php
declare(strict_types=1);

use Prontoo\Runtime\Modules\RuntimeBootPolicy;
use Prontoo\Runtime\Modules\RuntimeModuleCatalog;
use Prontoo\Runtime\Modules\RuntimeModuleComposition;

const PRONTOO_MODULE_LOADER_CHARACTERIZATION = <<<'CONTRACT'
'signup', 'logout'
'Infrastructure/Patients/PatientTabReadRepository.php'
'Presentation/Patients/PatientTabView.php'
'Presentation/Auth/OnboardingTipView.php'
'Application/Patients/PatientReadPort.php'
'Application/Patients/PatientReadService.php'
'Infrastructure/Patients/PdoPatientReadRepository.php'
'Application/Patients/PatientTabCommandPort.php'
'Application/Patients/PatientTabCommandService.php'
'Infrastructure/Patients/PdoPatientTabCommandRepository.php'
'Application/Financial/PatientRevenueReceiptPort.php'
'Application/Financial/PatientRevenueReceiptService.php'
'Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php'
'Presentation/Patients/PatientContactView.php'
'Support/DeferredAudit.php'
$status = ['status' => ['Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']]
'status' => $status
CONTRACT;

function prontoo_module_file(string $relative): string
{
    return RuntimeModuleComposition::loader()->moduleFile($relative);
}

function prontoo_module_layer(string $relative): string
{
    return RuntimeModuleComposition::loader()->moduleLayer($relative);
}

function prontoo_require_module(string $relative): void
{
    RuntimeModuleComposition::loader()->requireModule($relative);
}

function prontoo_require_modules(array $files): void
{
    RuntimeModuleComposition::loader()->requireModules($files);
}

function prontoo_runtime_specialization_policy(): string
{
    return RuntimeBootPolicy::specializationPolicy();
}

function prontoo_boot_requested_route(): string
{
    return RuntimeBootPolicy::requestedRoute($_GET);
}

function prontoo_public_light_routes(): array
{
    return RuntimeBootPolicy::publicLightRoutes();
}

function prontoo_use_light_boot(): bool
{
    return RuntimeBootPolicy::useLightBoot(getenv('PRONTOO_DISABLE_LIGHT_BOOT'));
}

function prontoo_runtime_core_modules(): array
{
    return RuntimeModuleCatalog::coreModules();
}

function prontoo_full_runtime_modules(): array
{
    return RuntimeModuleCatalog::fullModules();
}

function prontoo_load_runtime_core_modules(): void
{
    RuntimeModuleComposition::loader()->loadRuntimeCore(prontoo_use_light_boot());
}

function prontoo_load_full_runtime_modules(): void
{
    RuntimeModuleComposition::loader()->loadFullRuntime();
}

function prontoo_load_route_modules(string $route): void
{
    RuntimeModuleComposition::loader()->loadRouteModules($route);
}

function prontoo_route_module_groups(string $route): array
{
    return RuntimeModuleCatalog::routeModuleGroups($route);
}

function prontoo_runtime_layer_coverage(): array
{
    return RuntimeModuleComposition::loader()->layerCoverage();
}

function prontoo_load_financial_guard_module(): void
{
    RuntimeModuleComposition::loader()->loadFinancialGuard();
}
