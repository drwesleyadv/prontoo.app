<?php
declare(strict_types=1);

$__prontoo_core_files = [
    __DIR__ . '/Core/Support/Check.php',
    __DIR__ . '/Core/Architecture/LayerMap.php',
    __DIR__ . '/Core/Tenant/TenantRegistry.php',
    __DIR__ . '/Core/Readonly/ReadonlyPolicy.php',
    __DIR__ . '/Core/Invariant/Canonical.php',
    __DIR__ . '/Core/Invariant/Decision.php',
    __DIR__ . '/Core/Invariant/SqlExpression.php',
    __DIR__ . '/Domain/Authorization/ActionContract.php',
    __DIR__ . '/Application/Authorization/CapabilityProvider.php',
    __DIR__ . '/Application/Audit/ActionProofPort.php',
    __DIR__ . '/Application/Authorization/ActionDefinitionSource.php',
    __DIR__ . '/Application/Authorization/ActionDefinitionCollection.php',
    __DIR__ . '/Application/Authorization/ActionDefinitionRegistry.php',
    __DIR__ . '/Application/Authorization/ActionRequirementPolicy.php',
    __DIR__ . '/Application/Authorization/Definitions/AuthActionDefinitions.php',
    __DIR__ . '/Application/Authorization/Definitions/PatientActionDefinitions.php',
    __DIR__ . '/Application/Authorization/Definitions/SchedulingActionDefinitions.php',
    __DIR__ . '/Application/Authorization/Definitions/DocumentTaskActionDefinitions.php',
    __DIR__ . '/Application/Authorization/Definitions/WorkforceActionDefinitions.php',
    __DIR__ . '/Application/Authorization/Definitions/FinancialActionDefinitions.php',
    __DIR__ . '/Application/Authorization/Definitions/AdminActionDefinitions.php',
    __DIR__ . '/Application/Authorization/ActionCatalog.php',
    __DIR__ . '/Application/Authorization/AuthorizationService.php',
    __DIR__ . '/Infrastructure/Authorization/RuntimeCapabilityProvider.php',
    __DIR__ . '/Infrastructure/Audit/PdoActionProofStore.php',
    __DIR__ . '/Presentation/Http/ActionMiddleware.php',
    __DIR__ . '/Core/Invariant/Tenant/TenantContext.php',
    __DIR__ . '/Core/Invariant/Tenant/ScopeProof.php',
    __DIR__ . '/Core/Invariant/Relation/ForeignKeyGraph.php',
    __DIR__ . '/Core/Invariant/Workflow/StateMachine.php',
    __DIR__ . '/Core/Invariant/Workflow/AppointmentWorkflow.php',
    __DIR__ . '/Core/Invariant/Context/AppointmentContextInvariant.php',
    __DIR__ . '/Core/Invariant/Context/PeopleContextInvariant.php',
    __DIR__ . '/Core/Invariant/Context/TaskContextInvariant.php',
    __DIR__ . '/Core/Invariant/Context/DocumentContextInvariant.php',
    __DIR__ . '/Core/Invariant/Context/FinancialContextInvariant.php',
    __DIR__ . '/Core/Invariant/Context/MaestroContextInvariant.php',
    __DIR__ . '/Core/Invariant/Context/AccessContextInvariant.php',
    __DIR__ . '/Core/Invariant/Context/ContextInvariantRegistry.php',
    __DIR__ . '/Core/Invariant/Mutation/MutationLedger.php',
    __DIR__ . '/Core/Invariant/Mutation/MutationInvariant.php',
    __DIR__ . '/Core/Invariant/InvariantKernel.php',
    __DIR__ . '/Core/Invariant/Tenant/SqlScopeGuard.php',
    __DIR__ . '/Core/Install/InstallAccess.php',
    __DIR__ . '/Core/Database/SchemaMutationLock.php',
    __DIR__ . '/Core/Database/SchemaHardening.php',
    __DIR__ . '/Core/Database/TenantIntegrity.php',
    __DIR__ . '/Core/Integrity/AuditChain.php',
    __DIR__ . '/Core/Temporal/PiTime.php',
    __DIR__ . '/Infrastructure/Database/SeqContract.php',
    __DIR__ . '/Core/Integrity/PiIntegrity.php',
    __DIR__ . '/Core/Metrics/GlobalMetricScope.php',
    __DIR__ . '/Core/Architecture/ArchitectureVerifier.php',
    __DIR__ . '/Runtime/Modules/RuntimeBootPolicy.php',
    __DIR__ . '/Runtime/Modules/RuntimeModuleCatalog.php',
    __DIR__ . '/Runtime/Modules/RuntimeModuleLoader.php',
    __DIR__ . '/Runtime/Modules/RuntimeModuleComposition.php',
    __DIR__ . '/Runtime/Authorization/ActionCatalogComposition.php',
    __DIR__ . '/Runtime/LayeredKernel.php',
    __DIR__ . '/Core/Invariant/Request/ActionProof.php',
    __DIR__ . '/Core/Install/RuntimeContract.php',
];

foreach ($__prontoo_core_files as $__prontoo_core_file) {
    if (!is_file($__prontoo_core_file)) {
        $root = defined('PRONTOO_ROOT') ? PRONTOO_ROOT : dirname(__DIR__);
        throw new RuntimeException(
            'Módulo arquitetural essencial ausente: ' .
                str_replace($root . '/', '', $__prontoo_core_file),
        );
    }
    require_once $__prontoo_core_file;
}

\Prontoo\Application\Authorization\ActionCatalog::configure(
    \Prontoo\Runtime\Authorization\ActionCatalogComposition::source(),
);

unset($__prontoo_core_file, $__prontoo_core_files);
