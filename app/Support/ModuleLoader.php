<?php
declare(strict_types=1);

use Prontoo\Core\Architecture\LayerMap;

function prontoo_module_file(string $relative): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_module_file
     * Responsabilidade: Implementa a responsabilidade “prontoo module file” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_require_module`.
     * Dependências chamadas: `ltrim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return __DIR__ . '/../' . ltrim($relative, '/');
}

function prontoo_module_layer(string $relative): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_module_layer
     * Responsabilidade: Implementa a responsabilidade “prontoo module layer” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_require_module`, `prontoo_runtime_layer_coverage`.
     * Dependências chamadas: `LayerMap::layerFor`, `ltrim`, `RuntimeException`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $layer = LayerMap::layerFor('app/' . ltrim($relative, '/'));
    if ($layer === null) {
        throw new RuntimeException('Módulo sem camada arquitetural: app/' . ltrim($relative, '/'));
    }
    return $layer;
}

function prontoo_require_module(string $relative): void
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_require_module
     * Responsabilidade: Avalia ou impõe a regra “prontoo require module”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_require_modules`, `prontoo_load_financial_guard_module`.
     * Dependências chamadas: `prontoo_module_file`, `is_file`, `defined`, `dirname`, `RuntimeException`, `str_replace`, `prontoo_module_layer`, `realpath`, `is_array`, `count`, `max`, `filesize`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Estado externo lido: `$GLOBALS`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $file = prontoo_module_file($relative);
    if (!is_file($file)) {
        $root = defined('PRONTOO_ROOT') ? PRONTOO_ROOT : dirname(__DIR__, 2);
        throw new RuntimeException(
            'Módulo especializado essencial ausente: ' . str_replace($root . '/', '', $file),
        );
    }
    $layer = prontoo_module_layer($relative);
    $key = realpath($file) ?: $file;
    if (!isset($GLOBALS['PRONTOO_LOADED_MODULE_FILES']) || !is_array($GLOBALS['PRONTOO_LOADED_MODULE_FILES'])) {
        $GLOBALS['PRONTOO_LOADED_MODULE_FILES'] = [];
    }
    if (!isset($GLOBALS['PRONTOO_LOADED_MODULE_LAYERS']) || !is_array($GLOBALS['PRONTOO_LOADED_MODULE_LAYERS'])) {
        $GLOBALS['PRONTOO_LOADED_MODULE_LAYERS'] = [];
    }
    $measured = &$GLOBALS['PRONTOO_LOADED_MODULE_FILES'];
    require_once $file;
    if (!isset($measured[$key])) {
        $measured[$key] = true;
        $GLOBALS['PRONTOO_LOADED_MODULE_LAYERS'][$layer] =
            (int) ($GLOBALS['PRONTOO_LOADED_MODULE_LAYERS'][$layer] ?? 0) + 1;
        $GLOBALS['PRONTOO_MODULE_FILES_LOADED'] = count($measured);
        $GLOBALS['PRONTOO_MODULE_BYTES_LOADED'] =
            (int) ($GLOBALS['PRONTOO_MODULE_BYTES_LOADED'] ?? 0) +
            max(0, (int) (@filesize($file) ?: 0));
    }
}

function prontoo_require_modules(array $files): void
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_require_modules
     * Responsabilidade: Avalia ou impõe a regra “prontoo require modules”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_load_runtime_core_modules`, `prontoo_load_full_runtime_modules`, `prontoo_load_route_modules`.
     * Dependências chamadas: `prontoo_require_module`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    foreach ($files as $file) {
        prontoo_require_module((string) $file);
    }
}

function prontoo_runtime_specialization_policy(): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_runtime_specialization_policy
     * Responsabilidade: Implementa a responsabilidade “prontoo runtime specialization policy” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return 'pi-v3-php-layered-runtime-route-loaded';
}

function prontoo_boot_requested_route(): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_boot_requested_route
     * Responsabilidade: Implementa a responsabilidade “prontoo boot requested route” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `preg_replace`.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $route = $_GET['r'] ?? 'login';
    return preg_replace('/[^a-z0-9_\-]/i', '', (string) $route) ?: 'login';
}

function prontoo_public_light_routes(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_public_light_routes
     * Responsabilidade: Implementa a responsabilidade “prontoo public light routes” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return ['login', 'login_autotest', 'mfa', 'mobile_web_access', 'signup'];
}

function prontoo_use_light_boot(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_use_light_boot
     * Responsabilidade: Implementa a responsabilidade “prontoo use light boot” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_load_runtime_core_modules`.
     * Dependências chamadas: `getenv`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return (string) getenv('PRONTOO_DISABLE_LIGHT_BOOT') !== '1';
}

function prontoo_runtime_core_modules(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_runtime_core_modules
     * Responsabilidade: Implementa a responsabilidade “prontoo runtime core modules” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_full_runtime_modules`, `prontoo_load_runtime_core_modules`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     * Cuidado 2: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    return [
        'Support/Runtime.php',
        'Support/SecurityPrivacy.php',
        'Support/Foundation.php',
        'Support/ServerJsonCache.php',
        'Support/Telemetry.php',
        'Database/DatabaseSchema.php',
        'Support/SeqFooter.php',
        'Support/SecurityAccess.php',
        'Support/FinancialGuard.php',
        'Domain/Audit/AuditActivity.php',
        'Domain/Clinic/ClinicConfig.php',
        'Domain/Clinic/SubscriptionSettings.php',
        'Domain/Permissions/UsersPermissions.php',
        'Ui/Components.php',
        'Ui/PublicWeb.php',
        'Auth/AuthOnboarding.php',
        'Runtime/Runner.php',
    ];
}

function prontoo_full_runtime_modules(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_full_runtime_modules
     * Responsabilidade: Implementa a responsabilidade “prontoo full runtime modules” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_load_runtime_core_modules`, `prontoo_load_full_runtime_modules`, `prontoo_runtime_layer_coverage`.
     * Dependências chamadas: `array_values`, `array_unique`, `array_merge`, `prontoo_runtime_core_modules`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return array_values(array_unique(array_merge(prontoo_runtime_core_modules(), [
        'Domain/Patients/Patients.php',
        'Domain/Appointments/Appointments.php',
        'Domain/Leads/Leads.php',
        'Domain/Documents/Documents.php',
        'Domain/Documents/DocumentPdf.php',
        'Domain/Tasks/TasksNotices.php',
        'Domain/Financial/Financial.php',
        'Domain/Maestro/Maestro.php',
        'Admin/AdminPages.php',
        'Pages/Dashboards.php',
    ])));
}

function prontoo_load_runtime_core_modules(): void
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_load_runtime_core_modules
     * Responsabilidade: Localiza, carrega ou resolve os dados de “prontoo load runtime core modules” para consumo pelas camadas superiores.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `prontoo_require_modules`, `prontoo_use_light_boot`, `prontoo_runtime_core_modules`, `prontoo_full_runtime_modules`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    prontoo_require_modules(prontoo_use_light_boot() ? prontoo_runtime_core_modules() : prontoo_full_runtime_modules());
}

function prontoo_load_full_runtime_modules(): void
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_load_full_runtime_modules
     * Responsabilidade: Localiza, carrega ou resolve os dados de “prontoo load full runtime modules” para consumo pelas camadas superiores.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_run_runtime_maintenance_cycle`.
     * Dependências chamadas: `prontoo_require_modules`, `prontoo_full_runtime_modules`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $done = false;
    if ($done) {
        return;
    }
    prontoo_require_modules(prontoo_full_runtime_modules());
    $done = true;
}

function prontoo_load_route_modules(string $route): void
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_load_route_modules
     * Responsabilidade: Localiza, carrega ou resolve os dados de “prontoo load route modules” para consumo pelas camadas superiores.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_run`.
     * Dependências chamadas: `prontoo_route_module_groups`, `prontoo_require_modules`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $loaded = [];
    foreach (prontoo_route_module_groups($route) as $group => $files) {
        if (isset($loaded[$group])) {
            continue;
        }
        prontoo_require_modules($files);
        $loaded[$group] = true;
    }
}

function prontoo_route_module_groups(string $route): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_route_module_groups
     * Responsabilidade: Implementa a responsabilidade “prontoo route module groups” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_load_route_modules`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    $commonClinic = ['dashboards' => ['Pages/Dashboards.php']];
    $patients = ['patients' => ['Domain/Patients/Patients.php']];
    $leads = ['leads' => ['Domain/Leads/Leads.php']];
    $tasks = ['tasks' => ['Domain/Tasks/TasksNotices.php']];
    $financial = ['financial' => ['Domain/Financial/Financial.php']];
    $appointments = ['appointments' => ['Domain/Appointments/Appointments.php']];
    $documents = ['documents' => ['Domain/Documents/Documents.php', 'Domain/Documents/DocumentPdf.php']];
    $audit = ['audit' => ['Domain/Audit/AuditActivity.php']];
    $clinic = ['clinic' => ['Domain/Clinic/ClinicConfig.php', 'Domain/Clinic/SubscriptionSettings.php']];
    $users = ['users' => ['Domain/Appointments/Appointments.php', 'Domain/Permissions/UsersPermissions.php']];
    $admin = ['admin' => ['Domain/Leads/Leads.php', 'Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];
    $map = [
        'home' => $commonClinic + $appointments + $tasks + $financial + $patients + $leads,
        'painel' => $commonClinic + $appointments + $patients + $leads + $tasks + $financial + $documents,
        'login' => [], 'login_autotest' => [], 'mfa' => [], 'signup' => [], 'logout' => [], 'switch' => [], 'profile' => [], 'global_reauth' => [], 'onboarding' => [], 'mobile_web_access' => [],
        'patient_lookup' => $patients, 'patient_suggest' => $patients, 'person_lookup' => $patients,
        'lead_lookup' => $leads, 'lead_patient_lookup' => $leads + $patients, 'leads' => $leads,
        'appointments' => $appointments + $patients + $tasks + $financial,
        'patients' => $patients + $appointments,
        'patient' => $patients + $documents + $financial + $tasks + $appointments,
        'financial' => $financial + $patients, 'creditors' => $financial + $patients,
        'operations' => $financial, 'goal_status' => $financial,
        'counterparty_lookup' => $financial, 'counterparty_suggest' => $financial,
        'tasks' => $tasks, 'notices' => $tasks,
        'documents' => $documents + $patients + $financial + $appointments,
        'document_view' => $documents + $patients + $financial + $appointments,
        'document_print' => $documents + $patients + $financial + $appointments,
        'document_pdf' => $documents + $patients + $financial + $appointments,
        'document_pdf_file' => $documents + $patients + $financial + $appointments,
        'procedures' => $documents + $financial + $appointments,
        'users' => $users, 'user' => $users, 'permissions' => $users,
        'audit' => $audit + $documents + $financial + $tasks,
        'settings' => $clinic,
        'maestro' => ['maestro' => ['Domain/Maestro/Maestro.php']],
        'admin_painel' => $admin + $clinic, 'admin_stats' => $admin,
        'admin_clinics' => $admin + $clinic, 'admin_onboarding' => $admin,
        'admin_users' => $admin + $users, 'admin_people' => $admin,
        'admin_operations' => $admin + $financial,
        'admin_global_notices' => $tasks + $admin,
        'admin_alerts' => $admin, 'admin_maintenance' => $admin,
        'admin_health' => $admin, 'admin_performance' => $admin,
        'admin_deleted' => $admin, 'admin_errors' => $admin,
        'admin_diagnostics' => $admin, 'admin_integrity' => $admin,
        'admin_security' => $admin, 'admin_settings' => $admin,
        'admin_payment_proof' => $clinic + $admin, 'admin_audit' => $admin,
    ];
    return $map[$route] ?? $commonClinic;
}

function prontoo_runtime_layer_coverage(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_runtime_layer_coverage
     * Responsabilidade: Implementa a responsabilidade “prontoo runtime layer coverage” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `prontoo_full_runtime_modules`, `prontoo_module_layer`, `array_sum`, `count`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $modules = prontoo_full_runtime_modules();
    $layers = [];
    foreach ($modules as $module) {
        $layer = prontoo_module_layer((string) $module);
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

function prontoo_load_financial_guard_module(): void
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_load_financial_guard_module
     * Responsabilidade: Avalia ou impõe a regra “prontoo load financial guard module”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/ModuleLoader.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `prontoo_require_module`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    prontoo_require_module('Domain/Financial/Financial.php');
}
