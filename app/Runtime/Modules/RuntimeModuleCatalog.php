<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Modules;

use Prontoo\Runtime\Routing\RouteRegistry;
use Throwable;

final class RuntimeModuleCatalog
{
    private function __construct()
    {
    }

    public static function coreModules(): array
    {
        return [
            'Core/Performance/PerformanceBudget.php',
            'Presentation/Auth/OnboardingTipView.php',
            'Runtime/Runner.php',
        ];
    }

    public static function fullModules(): array
    {
        return array_values(array_unique(array_merge(self::coreModules(), [
            'Application/Patients/PatientContactCommandPort.php',
            'Application/Patients/PatientContactCommandService.php',
            'Infrastructure/Patients/PdoPatientContactCommandRepository.php',
            'Application/Patients/PatientReceptionHistoryReadPort.php',
            'Application/Patients/PatientReceptionHistoryReadService.php',
            'Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php',
            'Application/Patients/PatientTabCommandPort.php',
            'Application/Patients/PatientTabCommandService.php',
            'Infrastructure/Patients/PdoPatientTabCommandRepository.php',
            'Application/Patients/PatientReadPort.php',
            'Application/Patients/PatientReadService.php',
            'Infrastructure/Patients/PdoPatientReadRepository.php',
            'Infrastructure/Patients/PatientTabReadRepository.php',
            'Presentation/Patients/PatientTabView.php',
            'Presentation/Patients/PatientContactView.php',
            'Application/Financial/PatientRevenueReceiptPort.php',
            'Application/Financial/PatientRevenueReceiptService.php',
            'Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php',
        ])));
    }

    private static function moduleGroups(): array
    {
        return [
            'patients' => [
                'Application/Patients/PatientContactCommandPort.php',
                'Application/Patients/PatientContactCommandService.php',
                'Infrastructure/Patients/PdoPatientContactCommandRepository.php',
                'Application/Patients/PatientReceptionHistoryReadPort.php',
                'Application/Patients/PatientReceptionHistoryReadService.php',
                'Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php',
                'Application/Patients/PatientTabCommandPort.php',
                'Application/Patients/PatientTabCommandService.php',
                'Infrastructure/Patients/PdoPatientTabCommandRepository.php',
                'Application/Patients/PatientReadPort.php',
                'Application/Patients/PatientReadService.php',
                'Infrastructure/Patients/PdoPatientReadRepository.php',
                'Infrastructure/Patients/PatientTabReadRepository.php',
                'Presentation/Patients/PatientTabView.php',
                'Presentation/Patients/PatientContactView.php',
            ],
            'financial' => [
                'Application/Financial/PatientRevenueReceiptPort.php',
                'Application/Financial/PatientRevenueReceiptService.php',
                'Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php',
            ],
        ];
    }

    public static function routeModuleGroups(string $route): array
    {
        $definition = RouteRegistry::definition($route);
        if ($definition === null) {
            return [];
        }
        $available = self::moduleGroups();
        $groups = [];
        foreach ($definition->moduleGroups as $group) {
            if (!array_key_exists($group, $available)) {
                throw new \LogicException('Grupo de módulo não registrado: ' . $group);
            }
            $groups[$group] = $available[$group];
        }
        return $groups;
    }

    public static function logicSelfTest(array $routes): array
    {
        $failures = [];
        $registry = RouteRegistry::logicSelfTest();
        if (empty($registry['ok'])) {
            $failures[] = 'route_registry_invalid';
        }
        try {
            $declared = RouteRegistry::names();
            $expected = array_values(array_map('strval', $routes));
            if ($declared !== $expected) {
                $failures[] = 'route_set_mismatch';
            }
            $available = self::moduleGroups();
            foreach ($declared as $route) {
                $definition = RouteRegistry::definition($route);
                if ($definition === null) {
                    $failures[] = 'route_missing:' . $route;
                    continue;
                }
                foreach ($definition->moduleGroups as $group) {
                    if (!array_key_exists($group, $available)) {
                        $failures[] = 'group_missing:' . $route . ':' . $group;
                    }
                }
                foreach (self::routeModuleGroups($route) as $group => $files) {
                    if (!is_string($group) || $group === '' || !is_array($files)) {
                        $failures[] = 'group_invalid:' . $route;
                        continue;
                    }
                    foreach ($files as $file) {
                        if (!is_string($file) || $file === '') {
                            $failures[] = 'module_invalid:' . $route . ':' . $group;
                        }
                    }
                }
            }
        } catch (Throwable $error) {
            $failures[] = 'runtime_error:' . $error::class . ':' . $error->getMessage();
        }
        $failures = array_values(array_unique($failures));
        return [
            'ok' => $failures === [],
            'routes_checked' => count($routes),
            'registered_groups' => count(self::moduleGroups()),
            'registry' => $registry,
            'failures' => $failures,
        ];
    }
}
