<?php
declare(strict_types=1);

use Prontoo\Presentation\Http\JsonResponder;
use Prontoo\Runtime\Boot\RuntimeBootCoordinator;
use Prontoo\Runtime\Financial\FinancialComposition;
use Prontoo\Runtime\Modules\RuntimeBootPolicy;
use Prontoo\Runtime\Modules\RuntimeModuleCatalog;
use Prontoo\Runtime\Modules\RuntimeModuleComposition;
use Prontoo\Runtime\Patients\PatientComposition;
use Prontoo\Runtime\Patients\PatientViewComposition;
use Prontoo\Runtime\Routing\RouteCatalog;
use Prontoo\Runtime\Runner;

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

function prontoo_route_map(): array
{
    return RouteCatalog::all();
}

function prontoo_public_runtime_routes(): array
{
    return RouteCatalog::public();
}

function prontoo_json_runtime_routes(): array
{
    return RouteCatalog::json();
}

function prontoo_route_wants_json(string $route): bool
{
    return RouteCatalog::wantsJson($route, (string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
}

function prontoo_json_response(array $payload, int $status = 200): void
{
    JsonResponder::send($payload, $status);
}

function prontoo_json_failure_message(string $route, int $status, Throwable $error): string
{
    return JsonResponder::failureMessage($route, $status, $error);
}

function prontoo_route_is_public_light(string $route): bool
{
    return RouteCatalog::isPublicLight($route, (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function prontoo_schema_boot_marker_path(): string
{
    return RuntimeBootCoordinator::schemaMarkerPath();
}

function prontoo_schema_boot_marker_valid(int $ttlSeconds = 0): bool
{
    return RuntimeBootCoordinator::schemaMarkerValid($ttlSeconds);
}

function prontoo_schema_boot_mark_ok(string $mode): void
{
    RuntimeBootCoordinator::markSchemaOk($mode);
}

function prontoo_boot_database_for_route(string $route): void
{
    RuntimeBootCoordinator::bootDatabaseForRoute(
        $route,
        prontoo_route_is_public_light($route),
        PHP_SAPI === 'cli' && getenv('PRONTOO_FORCE_DEEP_BOOT') === '1',
    );
}

function prontoo_run_runtime_maintenance_cycle(string $mode = 'route_deep', int $uid = 0): array
{
    return RuntimeBootCoordinator::runMaintenanceCycle($mode, $uid);
}

function prontoo_login_post_password_maintenance(int $uid): array
{
    return RuntimeBootCoordinator::postPasswordMaintenance($uid);
}

function prontoo_flush_integrity_before_render(): void
{
    RuntimeBootCoordinator::flushIntegrityBeforeRender();
}

function prontoo_run(bool $installMode = false): void
{
    Runner::run($installMode);
}

function prontoo_patient_tab_active_rows(int $clinicId, int $patientId): array
{
    return PatientComposition::activeTabs($clinicId, $patientId);
}

function prontoo_patient_tab_label_by_id(int $tabId): string
{
    return PatientComposition::tabLabel($tabId);
}

function prontoo_patient_tab_icon_picker(array $options, string $current): string
{
    return PatientViewComposition::tabIconPicker($options, $current);
}

function prontoo_onboarding_tip_render(
    array $tip,
    string $key,
    string $return,
    string $csrfField,
): string {
    return PatientViewComposition::onboardingTip($tip, $key, $return, $csrfField);
}

function prontoo_patient_read_service(): \Prontoo\Application\Patients\PatientReadService
{
    return PatientComposition::readService();
}

function prontoo_patient_appointment_registration_block_reason(
    int $clinicId,
    int $patientId,
): ?string {
    return PatientComposition::registrationBlockReason($clinicId, $patientId);
}

function prontoo_patient_legal_guardians(int $clinicId, int $patientId): array
{
    return PatientComposition::legalGuardians($clinicId, $patientId);
}

function prontoo_patient_has_legal_guardian(int $clinicId, int $patientId): bool
{
    return PatientComposition::hasLegalGuardian($clinicId, $patientId);
}

function prontoo_patient_reception_history_read_service(): \Prontoo\Application\Patients\PatientReceptionHistoryReadService
{
    return PatientComposition::historyService();
}

function prontoo_patient_reception_history_read_model(
    int $clinicId,
    int $patientId,
    int $personId,
    string $phoneDigits,
): array {
    return PatientComposition::receptionHistory($clinicId, $patientId, $personId, $phoneDigits);
}

function prontoo_patient_tab_command_service(): \Prontoo\Application\Patients\PatientTabCommandService
{
    return PatientComposition::tabCommandService();
}

function prontoo_create_patient_tab_command(
    int $clinicId,
    int $patientId,
    string $label,
    string $iconName,
    int $userId,
): array {
    return PatientComposition::createTab($clinicId, $patientId, $label, $iconName, $userId);
}

function prontoo_patient_contact_edit_form(array $patient, int $clinicId): string
{
    return PatientViewComposition::contactEditForm($patient, $clinicId);
}

function prontoo_patient_contact_command_service(): \Prontoo\Application\Patients\PatientContactCommandService
{
    return PatientComposition::contactCommandService();
}

function prontoo_update_patient_contact_command(
    int $clinicId,
    int $patientId,
    int $userId,
    array $contact,
): array {
    return PatientComposition::updateContact($clinicId, $patientId, $userId, $contact);
}

function prontoo_patient_revenue_receipt_service(): \Prontoo\Application\Financial\PatientRevenueReceiptService
{
    return FinancialComposition::patientRevenueService();
}

function prontoo_receive_patient_revenue_command(
    int $clinicId,
    int $patientId,
    int $revenueId,
    int $userId,
    string $role,
): array {
    return FinancialComposition::receivePatientRevenue(
        $clinicId,
        $patientId,
        $revenueId,
        $userId,
        $role,
    );
}
